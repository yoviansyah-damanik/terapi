<?php

namespace App\Traits;

use App\Services\Snomed\SnowstormService;
use App\Services\Terminology\TerminologyTranslatorService;
use App\Helpers\StringHelper;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;

trait WithSmartMapping
{
    // State Modal Smart Mapping AI
    public bool $showAiModal = false;
    public bool $isAiProcessing = false;
    public array $aiQueue = [];
    public array $aiFailedQueue = [];
    public array $aiLogs = [];
    public array $aiResults = [];
    public array $selectedAiResults = []; // [icd_code => snomed_code]
    public int $aiTotalQueue = 0;

    public ?string $mappingCode = null;
    public bool $useDeepScanAi = false;

    // State paused saat error 504/timeout
    public bool $isAiPaused = false;
    public string $aiPauseReason = '';
    public int $consecutiveErrors = 0;
    public const MAX_CONSECUTIVE_ERRORS = 3;

    /**
     * @return bool
     */
    abstract protected function saveSmartMapping(string $sourceCode, string $snomedCode, string $snomedTerm): bool;

    /**
     * @return array Array of ['code' => string, 'name' => string] that are unmapped
     */
    abstract protected function getUnmappedItemsForPage(int $page, bool $forAiQueue = false);

    public function smartMap(string $code, string $name, string $mode): void
    {
        if ($mode === 'ai') {
            $this->aiQueue = [
                ['code' => $code, 'name' => $name]
            ];
            $this->aiTotalQueue = 1;
            $this->aiFailedQueue = [];
            $this->aiLogs = [];
            $this->aiResults = [];
            $this->selectedAiResults = [];
            $this->isAiProcessing = true;
            $this->showAiModal = true;
            $this->addAiLog("Mencari term AI untuk {$code}...");
            $this->dispatch('process-next-ai');
            return;
        }

        $this->mappingCode = $code;
        $success = $this->executeSmartMap($code, $name, $mode);

        if ($success) {
            Cache::flush();
            $this->toastSuccess("Mapping otomatis untuk $code berhasil disimpan", 'Sukses');
        } else {
            $this->toastWarning("Tidak dapat menemukan padanan otomatis untuk $code", 'Tidak Ditemukan');
        }
        $this->mappingCode = null;
    }

    private function executeSmartMap(string $code, string $name, string $mode): bool
    {
        $snomedCode = null;
        $snomedTerm = null;

        if ($mode === 'snowstorm') {
            $cleaned = preg_replace('/\[.*?\]/', '', $name);
            $cleaned = trim($cleaned);
            $result = app(SnowstormService::class)->search($cleaned, limit: 1, offset: 0, semanticTag: property_exists($this, 'snomedSemanticTag') ? $this->snomedSemanticTag : 'disorder');

            if (!empty($result['items'])) {
                $itemInfo = $result['items'][0];
                $snomedCode = $itemInfo['conceptId'] ?? null;
                $snomedTerm = $itemInfo['pt']['term'] ?? $itemInfo['fsn']['term'] ?? 'Unknown';
            }
        } elseif ($mode === 'ai') {
            try {
                // 1. Cek Leksikon Lokal Dulu (Kilat)
                $lexicon = \App\Models\Terminology\MedicalLexicon::where('layman_term', strtolower(trim($name)))->first();
                if ($lexicon && $lexicon->snomed_concept_id) {
                    return $this->saveSmartMapping($code, $lexicon->snomed_concept_id, $lexicon->clinical_term);
                }

                // 2. Pipeline AI 2-Tahap dengan target SNOMED
                $inputText = "{$code} - {$name}";
                $aiResp = app(TerminologyTranslatorService::class)->translate($inputText, ['snomed']);

                $snomedList    = $aiResp['suggestions']['snomed'] ?? [];
                $clinicalTerms = $aiResp['clinical_terms'] ?? [];
                // Gunakan primary term dari tahap 1 sebagai acuan scoring
                $primaryMedTerm = $aiResp['medical_terms']['primary'] ?? ($clinicalTerms[0] ?? $name);

                $concept = null;
                $sCode = null;

                if (!empty($snomedList) && !empty($snomedList[0]['code'])) {
                    $sCode = $snomedList[0]['code'];
                    $check = app(SnowstormService::class)->getConcept($sCode);
                    if ($check && ($check['active'] ?? false)) {
                        $concept = $check;
                    }
                }
                    
                if (!$concept) {
                    // Bangun daftar kata pencarian dari hasil tahap 1
                    $searchTerms = array_filter(array_unique(array_merge(
                        [$primaryMedTerm],
                        $clinicalTerms,
                        [$snomedList[0]['reason'] ?? null]
                    )));

                    $bestConcept = null;
                    $highestScore = -1;

                    foreach ($searchTerms as $term) {
                        if (empty(trim($term))) continue;
                        
                        $result = app(SnowstormService::class)->search($term, limit: $this->useDeepScanAi ? 50 : 10, offset: 0, semanticTag: property_exists($this, 'snomedSemanticTag') ? $this->snomedSemanticTag : 'disorder');
                        if (!empty($result['items'])) {
                            foreach ($result['items'] as $item) {
                                if (!($item['active'] ?? false)) continue;

                                $itemTerm = $item['pt']['term'] ?? $item['fsn']['term'] ?? '';
                                $score = \App\Helpers\StringHelper::calculateSimilarityScore($primaryMedTerm, $itemTerm);
                                
                                if ($score > $highestScore) {
                                    $highestScore = $score;
                                    $bestConcept = $item;
                                }
                            }
                        }

                        if ($highestScore >= 80) break;
                    }

                    if ($bestConcept) {
                        $concept = $bestConcept;
                        $sCode = $concept['conceptId'];
                    }
                }

                if ($concept) {
                    $snomedCode = $concept['conceptId'] ?? $sCode;
                    $snomedTerm = $concept['pt']['term'] ?? $concept['fsn']['term'] ?? 'Unknown';
                }
            } catch (\Exception $e) {
                return false;
            }
        }

        if ($snomedCode) {
            return $this->saveSmartMapping($code, $snomedCode, $snomedTerm);
        }
        return false;
    }

    public function smartMapPage(string $mode): void
    {
        set_time_limit(300); // 5 menit
        $halaman = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();

        $unmapped = $this->getUnmappedItemsForPage($halaman, true);

        if ($mode === 'ai') {
            if (empty($unmapped)) {
                $this->toastWarning("Tidak ada data belum ter-mapping di halaman ini.", "Selesai");
                return;
            }
            $this->aiQueue = $unmapped;
            $this->aiTotalQueue = count($unmapped);
            $this->aiFailedQueue = [];
            $this->aiLogs = [];
            $this->aiResults = [];
            $this->selectedAiResults = [];
            $this->isAiProcessing = true;
            $this->showAiModal = true;
            $this->addAiLog("Mulai memproses {$this->aiTotalQueue} data ke AI...");
            $this->dispatch('process-next-ai');
            return;
        }

        $mappedCount = 0;
        foreach ($unmapped as $item) {
            if ($this->executeSmartMap($item['code'], $item['name'], $mode)) {
                $mappedCount++;
            }
        }

        if ($mappedCount > 0) {
            Cache::flush();
            $this->toastSuccess("Selesai memproses otomatis. Menambahkan $mappedCount mapping baru.", "Sukses Bulk Map");
        } else {
            $this->toastWarning("Tidak ada mapping baru yg ditambahkan.", "Selesai Bulk Map");
        }
    }

    public function addAiLog(string $message): void
    {
        $time = now()->format('H:i:s');
        $this->aiLogs[] = "[$time] $message";
        if (count($this->aiLogs) > 30) {
            array_shift($this->aiLogs);
        }
    }

    public function retryFailedAi(): void
    {
        if (empty($this->aiFailedQueue)) return;
        $this->aiQueue = $this->aiFailedQueue;
        $this->aiTotalQueue += count($this->aiQueue);
        $this->aiFailedQueue = [];
        $this->isAiProcessing = true;
        $this->addAiLog("» Mengulang " . count($this->aiQueue) . " data yang gagal...");
        $this->dispatch('process-next-ai');
    }

    /**
     * Composite scoring: membandingkan dua term secara semantik, bukan hanya teks.
     * Komponen:
     *  - Text similarity (primaryMedTerm vs SNOMED term) → bobot 60%
     *  - Bonus jika organism/agent ditemukan dalam SNOMED FSN → +20%
     *  - Bonus jika semantic tag cocok dengan type_hints AI → +10%
     *  - Text similarity (originalIcdName) sebagai secondary check → bobot 10%
     */
    private function compositeScore(
        string  $primaryMedTerm,
        string  $snomedTerm,
        ?string $organism,
        array   $typeHints,
        string  $fsnTerm = ''
    ): int {
        $base      = \App\Helpers\StringHelper::calculateSimilarityScore($primaryMedTerm, $snomedTerm);
        $composite = $base * 0.7;

        // Bonus organism: nama patogen/agen muncul di SNOMED term/FSN
        if ($organism) {
            $orgLower = strtolower($organism);
            if (str_contains(strtolower($snomedTerm), $orgLower) || str_contains(strtolower($fsnTerm), $orgLower)) {
                $composite += 20;
            }
        }

        // Bonus semantic tag: type hint cocok dengan isi FSN (disorder, procedure, finding, dll.)
        if (!empty($typeHints) && !empty($fsnTerm)) {
            foreach ($typeHints as $hint) {
                if (str_contains(strtolower($fsnTerm), strtolower($hint))) {
                    $composite += 8;
                    break;
                }
            }
        }

        return (int) min(100, round($composite));
    }

    #[On('process-next-ai')]
    public function processNextAi(): void
    {
        set_time_limit(120);

        if (empty($this->aiQueue)) {
            $this->isAiProcessing = false;
            $this->addAiLog("» Selesai mengumpulkan tebakan AI.");
            return;
        }

        if ($this->isAiPaused) {
            return; // Tunggu konfirmasi pengguna
        }

        $current = array_shift($this->aiQueue);
        $this->addAiLog("Menganalisa {$current['code']}...");
        $startTime = microtime(true);

        try {
            // 1. Cek Leksikon Lokal Dulu (Bypass AI)
            $lexicon = \App\Models\Terminology\MedicalLexicon::where('layman_term', strtolower(trim($current['name'])))->first();
            if ($lexicon && $lexicon->snomed_concept_id) {
                $this->aiResults[] = [
                    'icd_code'   => $current['code'],
                    'icd_name'   => $current['name'],
                    'candidates' => [
                        [
                            'snomed_code' => $lexicon->snomed_concept_id,
                            'snomed_term' => $lexicon->clinical_term,
                            'score'       => 100,
                            'source'      => 'lexicon',
                        ]
                    ],
                ];
                $this->selectedAiResults[$current['code']] = $lexicon->snomed_concept_id;
                $duration = number_format(microtime(true) - $startTime, 1);
                $this->addAiLog("✓ (Lexicon Hit) Ketemu {$lexicon->snomed_concept_id} dlm {$duration} dtk.");
                $this->dispatch('process-next-ai');
                return;
            }

            // 2. Pipeline AI 2-Tahap dengan target SNOMED
            $inputText = "{$current['code']} - {$current['name']}";
            $aiResp = app(TerminologyTranslatorService::class)->translate($inputText, ['snomed']);

            $snomedList     = $aiResp['suggestions']['snomed'] ?? [];
            $clinicalTerms  = $aiResp['clinical_terms'] ?? [];
            $primaryMedTerm = $aiResp['medical_terms']['primary'] ?? ($clinicalTerms[0] ?? $current['name']);

            if (!empty($aiResp['interpretation'])) {
                $this->addAiLog("📋 " . $aiResp['interpretation']);
            }
            $this->addAiLog("⇒ Term medis: {$primaryMedTerm}");
            $concept = null;
            $sCode = null;

            // ── Kumpulkan SEMUA kandidat dari semua search term ──
            $allCandidates = []; // [conceptId => ['term'=>..., 'score'=>..., 'item'=>...]]

            // Validasi kode SNOMED dari AI dulu
            if (!empty($snomedList) && !empty($snomedList[0]['code'])) {
                $sCode = $snomedList[0]['code'];
                $check = app(SnowstormService::class)->getConcept($sCode);
                if ($check && ($check['active'] ?? false)) {
                    $itemTerm = $check['pt']['term'] ?? $check['fsn']['term'] ?? '';
                    $fsnTerm  = $check['fsn']['term'] ?? $itemTerm;
                    $score = $this->compositeScore(
                        $primaryMedTerm, $itemTerm,
                        $aiResp['medical_terms']['organism_or_agent'] ?? null,
                        $aiResp['type_hints'] ?? [],
                        $fsnTerm
                    );
                    $allCandidates[$sCode] = [
                        'snomed_code' => $sCode,
                        'snomed_term' => $itemTerm,
                        'fsn_term'    => $fsnTerm,
                        'score'       => $score,
                        'source'      => 'ai_direct',
                    ];
                } else {
                    $this->addAiLog("⚠ Kode $sCode " . ($check ? 'Inactive' : 'tidak ditemukan') . ". Lanjut fallback...");
                }
            }

            // Fallback: cari dari semua kombinasi term
            $searchTermsToTry = array_values(array_filter(array_unique(array_merge(
                [$primaryMedTerm],
                $clinicalTerms,
                [$snomedList[0]['reason'] ?? null]
            ))));

            $snowstorm = app(SnowstormService::class);
            $semanticTag = property_exists($this, 'snomedSemanticTag') ? $this->snomedSemanticTag : 'disorder';
            $limit = $this->useDeepScanAi ? 50 : 20;
            $organism = $aiResp['medical_terms']['organism_or_agent'] ?? null;
            $typeHints = $aiResp['type_hints'] ?? [];

            foreach ($searchTermsToTry as $term) {
                if (empty(trim($term))) continue;
                $result = $snowstorm->search($term, limit: $limit, offset: 0, semanticTag: $semanticTag);

                foreach ($result['items'] ?? [] as $item) {
                    if (!($item['active'] ?? false)) continue;
                    $cid     = $item['conceptId'];
                    $itemTerm = $item['pt']['term'] ?? $item['fsn']['term'] ?? '';
                    $fsnTerm  = $item['fsn']['term'] ?? $itemTerm;
                    $score   = $this->compositeScore($primaryMedTerm, $itemTerm, $organism, $typeHints, $fsnTerm);

                    if (!isset($allCandidates[$cid]) || $score > $allCandidates[$cid]['score']) {
                        $allCandidates[$cid] = [
                            'snomed_code' => $cid,
                            'snomed_term' => $itemTerm,
                            'fsn_term'    => $fsnTerm,
                            'score'       => $score,
                            'source'      => 'search',
                        ];
                    }
                }

                // Jika sudah ada kandidat sangat tinggi, stop
                if (!empty($allCandidates) && max(array_column($allCandidates, 'score')) >= 90) break;
            }

            if (!empty($allCandidates)) {
                // Urutkan berdasarkan score DESC, ambil top 5
                usort($allCandidates, fn($a, $b) => $b['score'] <=> $a['score']);
                $topCandidates = array_slice($allCandidates, 0, 5);
                $best = $topCandidates[0];

                $this->aiResults[] = [
                    'icd_code'            => $current['code'],
                    'icd_name'            => $current['name'],
                    'interpretation'      => $aiResp['interpretation'] ?? '',
                    'primary_medical_term'=> $primaryMedTerm,
                    'candidates'          => $topCandidates,
                ];

                if ($best['score'] >= 50) {
                    $this->selectedAiResults[$current['code']] = $best['snomed_code'];
                }

                $duration = number_format(microtime(true) - $startTime, 1);
                $this->addAiLog("✓ {$best['snomed_code']} " . count($topCandidates) . " kandidat (Terbaik {$best['score']}%) dlm {$duration} dtk.");
            } else {
                $duration = number_format(microtime(true) - $startTime, 1);
                $this->addAiLog("✗ Tidak ada kandidat ditemukan ({$duration}s).");
                $this->aiFailedQueue[] = $current;
            }
        } catch (\Exception $e) {
            $duration = number_format(microtime(true) - $startTime, 1);
            $rawMsg   = $e->getMessage();
            $errorMsg = explode("\n", $rawMsg)[0];

            // Ekstrak HTTP status code dari format [HTTP:NNN] atau "HTTP NNN"
            $httpCode = 0;
            if (preg_match('/\[HTTP:(\d{3})\]/', $rawMsg, $m)) {
                $httpCode = (int) $m[1];
            } elseif (preg_match('/HTTP (\d{3})/', $rawMsg, $m)) {
                $httpCode = (int) $m[1];
            }

            $isGatewayError = in_array($httpCode, [502, 503, 504])
                           || str_contains($rawMsg, '504')
                           || str_contains($rawMsg, 'Gateway Timeout')
                           || str_contains($rawMsg, 'cURL error 28')
                           || str_contains($rawMsg, 'Request Timeout')
                           || str_contains($rawMsg, 'timed out');

            $this->aiFailedQueue[] = $current;
            $this->consecutiveErrors++;

            if ($isGatewayError || $this->consecutiveErrors >= self::MAX_CONSECUTIVE_ERRORS) {
                $this->isAiProcessing = false;
                $this->isAiPaused     = true;

                $codeLabel = $httpCode > 0 ? "HTTP {$httpCode}" : 'Timeout';

                if ($isGatewayError) {
                    $this->aiPauseReason = "Server AI mengembalikan error {$codeLabel} (Gateway Timeout / Overload) saat memproses {$current['code']}. Server AI mungkin sedang sibuk.\n\nDetail: {$errorMsg}";
                    $this->addAiLog("🟥 [{$codeLabel}] Timeout pada {$current['code']}. Proses dijeda — tunggu konfirmasi.");
                } else {
                    $this->aiPauseReason = "{$this->consecutiveErrors} error berturut-turut. Error terakhir: {$errorMsg}";
                    $this->addAiLog("🟥 [{$this->consecutiveErrors}x Error] Proses dijeda — tunggu konfirmasi.");
                }
                return;
            }

            $this->addAiLog("⚠ Error ({$duration}s): {$errorMsg} → Melanjutkan item berikutnya...");
        }

        $this->dispatch('process-next-ai');
    }

    /** Lanjutkan proses setelah dijeda karena error. */
    public function resumeAi(): void
    {
        $this->isAiPaused       = false;
        $this->aiPauseReason    = '';
        $this->consecutiveErrors = 0; // Reset counter
        $this->isAiProcessing   = true;
        $this->addAiLog('▶ Proses dilanjutkan oleh pengguna...');
        $this->dispatch('process-next-ai');
    }

    /** Hentikan proses setelah dijeda karena error. */
    public function stopAi(): void
    {
        $this->isAiPaused     = false;
        $this->isAiProcessing = false;
        $this->aiPauseReason  = '';
        $this->addAiLog('⛔ Proses dihentikan oleh pengguna. ' . count($this->aiQueue) . ' item belum diproses dipindahkan ke antrian Gagal.');
        // Pindahkan sisa queue ke failedQueue agar bisa di-retry
        foreach ($this->aiQueue as $q) {
            $this->aiFailedQueue[] = $q;
        }
        $this->aiQueue = [];
    }

    /**
     * Dipanggil dari JavaScript ketika Livewire request gagal di level network/nginx (HTTP 504, 502, 503).
     * PHP try/catch tidak bisa menangkap error ini karena nginx
     * memutus koneksi sebelum PHP selesai menjalankan kode.
     */
    public function handleNetworkError(int $httpStatus): void
    {
        $this->isAiProcessing = false;
        $this->isAiPaused     = true;
        $this->consecutiveErrors++;

        $label = match ($httpStatus) {
            502 => 'Bad Gateway (502)',
            503 => 'Service Unavailable (503)',
            504 => 'Gateway Time-out (504)',
            default => "HTTP Error ({$httpStatus})",
        };

        $this->aiPauseReason = "Server mengembalikan error {$label}. Nginx memutus koneksi karena PHP terlalu lama memproses permintaan AI.\n\nKemungkinan penyebab: model AI sedang overload atau timeout server terlalu pendek.";
        $this->addAiLog("🟥 [Network {$httpStatus}] Koneksi diputus oleh nginx. Proses dijeda — tunggu konfirmasi.");
    }

    public function saveAiMapping(): void
    {
        if (empty($this->selectedAiResults)) {
            $this->showAiModal = false;
            return;
        }

        $count = 0;
        // selectedAiResults sekarang: [icd_code => snomed_code]
        foreach ($this->selectedAiResults as $icdCode => $chosenSnomedCode) {
            $resultEntry = collect($this->aiResults)->firstWhere('icd_code', $icdCode);
            if (!$resultEntry) continue;

            // Cari kandidat yang dipilih
            $candidate = collect($resultEntry['candidates'] ?? [])->firstWhere('snomed_code', $chosenSnomedCode);
            if (!$candidate) continue;

            if ($this->saveSmartMapping($icdCode, $candidate['snomed_code'], $candidate['snomed_term'])) {
                $count++;
            }
        }

        $this->showAiModal = false;
        Cache::flush();
        $this->toastSuccess("Berhasil menyimpan $count mapping dari prediksi AI.", "Sukses AI Map");
    }
}
