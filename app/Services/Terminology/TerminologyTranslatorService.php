<?php

namespace App\Services\Terminology;

use App\Models\Terminology\Icd10;
use App\Models\Terminology\Icd9;
use App\Models\Terminology\IcdOTopography;
use App\Models\Terminology\IcdOMorphology;
use App\Models\Terminology\IcdPm;
use App\Models\Terminology\IcdMm;
use App\Models\Terminology\Loinc;
use App\Models\FhirDictionary;

class TerminologyTranslatorService
{
    /**
     * Prompt Tahap 1: Interpretasi & konversi ke bahasa medis baku.
     * Menghasilkan terminologi medis standar SEBELUM mencari kode.
     */
    private const PROMPT_STAGE1 = <<<'PROMPT'
You are a senior clinical terminologist with expertise in international clinical coding (ICD-10, SNOMED CT, LOINC, ICD-O).

Your task is MEDICAL INTERPRETATION and TERM NORMALIZATION.

Step 1 - INTERPRET: Understand the clinical condition. Input may be:
- ICD/SNOMED code + label (e.g. "A04.5 Campylobacter enteritis")
- Indonesian layman term (e.g. "kencing manis", "sesak napas")
- Mixed or ambiguous clinical phrase

Step 2 - NORMALIZE to the most precise internationally-recognized English medical term:
- Use the most SPECIFIC clinical term. "Campylobacter enteritis" → use "Enteric campylobacteriosis" (never generalize to just "enteritis")
- "Salmonella enteritis" → use "Salmonella gastroenteritis" NOT just "gastroenteritis"
- If a specific pathogen/organism/agent is mentioned, KEEP it in the primary term
- Use SNOMED preferred terms when possible

Step 3 - Generate 2-4 alternative search synonyms ordered by clinical specificity.

Respond ONLY with valid JSON (no markdown, no extra text):
{
  "original_input": "exact copy of input",
  "interpretation": "Penjelasan kondisi klinis ini dalam Bahasa Indonesia (1-2 kalimat)",
  "medical_terms": {
    "primary": "Most specific English medical term for SNOMED/database search",
    "alternatives": ["synonym 1", "synonym 2", "synonym 3"],
    "organism_or_agent": "Pathogen or etiologic agent name if present, else null"
  },
  "type_hints": ["disorder"]
}

type_hints allowed values: disorder, procedure, finding, substance, organism, body structure, situation
PROMPT;

    /**
     * Prompt Tahap 2: Pencarian kode berdasarkan terminologi yang sudah dinormalisasi.
     */
    private const PROMPT_STAGE2 = <<<'PROMPT'
You are a clinical coding specialist. Using the pre-analyzed medical data below, suggest the most accurate terminology codes ONLY for the requested targets.

Rules:
- ONLY fill in targets listed in "Requested targets"
- For unlisted targets: use empty array []
- SNOMED concepts: Provide your best known conceptId, but mark as uncertain in reason if unsure
- ICD-10: Use the most specific code (prefer 4-character codes)
- Leave array empty [] if you are not confident - do not hallucinate

Respond ONLY with valid JSON (no markdown, no extra text):
{
  "suggestions": {
    "icd10":            [{"code": "A04.5", "reason": "..."}],
    "icd9":             [],
    "snomed":           [{"code": "18081009", "reason": "..."}],
    "loinc":            [],
    "icd_o_topography": [],
    "icd_o_morphology": [],
    "icd_pm":           [],
    "icd_mm":           [],
    "kfa":              [],
    "hl7":              []
  }
}
PROMPT;

    /**
     * Terjemahkan teks klinis dengan pipeline 2 tahap.
     *
     * @param  string  $clinicalText  Input mentah
     * @param  array   $targets       Terminologi yang diming (misal: ['snomed', 'icd10']). Kosong = semua.
     * @return array
     */
    public function translate(string $clinicalText, array $targets = []): array
    {
        try {
            $ai = app(\App\Services\AiService::class);

            // === TAHAP 1: Medical Interpretation & Normalization ===
            $stage1Raw = $ai->sendPrompt(self::PROMPT_STAGE1, $clinicalText, 'json_object');
            $stage1    = $this->decodeJson($stage1Raw);

            $primaryTerm   = $stage1['medical_terms']['primary'] ?? $clinicalText;
            $alternatives  = $stage1['medical_terms']['alternatives'] ?? [];
            $organism      = $stage1['medical_terms']['organism_or_agent'] ?? null;
            $typeHints     = $stage1['type_hints'] ?? ['disorder'];
            $interpretation = $stage1['interpretation'] ?? '';

            // Susun konteks kaya untuk tahap 2
            $targetList = empty($targets)
                ? 'icd10, icd9, snomed, loinc, icd_o_topography, icd_o_morphology, icd_pm, icd_mm, kfa, hl7'
                : implode(', ', $targets);

            $context = "Original input: \"{$clinicalText}\"\n"
                . "Normalized primary medical term: \"{$primaryTerm}\"\n"
                . "Alternative terms: " . $this->formatList($alternatives) . "\n"
                . "Organism/agent: " . $this->formatValue($organism) . "\n"
                . "SNOMED semantic tags: " . $this->formatList($typeHints) . "\n"
                . "Requested targets: {$targetList}";

            // === TAHAP 2: Pencarian Kode ===
            $stage2Raw = $ai->sendPrompt(self::PROMPT_STAGE2, $context, 'json_object');
            $stage2    = $this->decodeJson($stage2Raw);

            // Gabungkan semua hasil
            $result = [
                'interpretation' => $interpretation,
                'original_input' => $stage1['original_input'] ?? $clinicalText,
                'medical_terms'  => $stage1['medical_terms'] ?? [],
                'clinical_terms' => array_values(array_filter(array_unique(array_merge([$primaryTerm], $alternatives)))),
                'suggestions'    => $stage2['suggestions'] ?? [],
            ];

            return $this->enrichWithLocalDb($result);
        } catch (\Exception $e) {
            throw new \RuntimeException("AI Translator Error: " . $e->getMessage());
        }
    }

    /**
     * Versi ringkas: translate fokus SNOMED saja (digunakan oleh WithSmartMapping).
     */
    public function translateForSnomed(string $clinicalText, bool $deepScan = false): array
    {
        return $this->translate($clinicalText, ['snomed']);
    }

    // ------------------------------------------------------------------ //
    //  Helpers
    // ------------------------------------------------------------------ //

    private function formatList(array $items): string
    {
        return empty($items) ? 'none' : '"' . implode('", "', $items) . '"';
    }

    private function formatValue(?string $val): string
    {
        return $val ? "\"{$val}\"" : 'none';
    }

    private function decodeJson(string $content): array
    {
        // Strip markdown code fences jika ada
        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/m', '', $content);

        $decoded = json_decode(trim($content), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Respons AI bukan JSON valid: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Enrich saran AI dengan display name dari DB lokal.
     * Kode yang tidak ditemukan di DB diberi flag unverified: true.
     */
    private function enrichWithLocalDb(array $result): array
    {
        $suggestions = $result['suggestions'] ?? [];

        $suggestions['icd10']           = $this->enrichFromModel(Icd10::class,         'code',       'display',         $suggestions['icd10']           ?? []);
        $suggestions['icd9']            = $this->enrichFromModel(Icd9::class,          'code',       'display',         $suggestions['icd9']            ?? []);
        $suggestions['icd_o_topography']= $this->enrichFromModel(IcdOTopography::class,'code',       'display',         $suggestions['icd_o_topography'] ?? []);
        $suggestions['icd_o_morphology']= $this->enrichFromModel(IcdOMorphology::class,'code',       'display',         $suggestions['icd_o_morphology'] ?? []);
        $suggestions['icd_pm']          = $this->enrichFromModel(IcdPm::class,         'code',       'display',         $suggestions['icd_pm']          ?? []);
        $suggestions['icd_mm']          = $this->enrichFromModel(IcdMm::class,         'code',       'display',         $suggestions['icd_mm']          ?? []);
        $suggestions['loinc']           = $this->enrichFromModel(Loinc::class,         'loinc_num',  'long_common_name',$suggestions['loinc']           ?? []);
        $suggestions['hl7']             = $this->enrichFromQuery(FhirDictionary::fromSource('hl7'),   'system_code', 'system_display',  $suggestions['hl7']             ?? []);
        $suggestions['kemkes']          = $this->enrichFromQuery(FhirDictionary::fromSource('kemkes'),'system_code', 'system_term',     $suggestions['kemkes']          ?? []);

        $result['suggestions'] = $suggestions;
        return $result;
    }

    private function enrichFromModel(string $modelClass, string $codeCol, string $displayCol, array $items): array
    {
        return $this->enrichFromQuery($modelClass::query(), $codeCol, $displayCol, $items);
    }

    private function enrichFromQuery($query, string $codeCol, string $displayCol, array $items): array
    {
        if (empty($items)) return [];

        $codes = collect($items)->pluck('code')->filter()->toArray();

        try {
            $found = $query->whereIn($codeCol, $codes)
                ->get([$codeCol, $displayCol])
                ->keyBy($codeCol);

            return collect($items)->map(function ($item) use ($found, $codeCol, $displayCol) {
                $record = $found->get($item['code'] ?? '');
                if ($record) {
                    $item['display']    = $record->{$displayCol};
                    $item['unverified'] = false;
                } else {
                    $item['unverified'] = true;
                }
                return $item;
            })->toArray();
        } catch (\Exception $e) {
            return collect($items)->map(fn ($i) => array_merge($i, ['unverified' => true]))->toArray();
        }
    }
}
