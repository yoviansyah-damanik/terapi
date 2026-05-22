<?php

namespace App\Services\SatuSehat\Resources;

use App\Models\FhirDictionary;
use App\Models\Mapping\EpisodeOfCareMap;
use App\Models\SatuSehat\SatuSehatEpisodeOfCare;
use App\Models\SatuSehat\SatuSehatOrganization;
use App\Models\SatuSehat\SatuSehatPatient;
use App\Services\SatuSehat\DTO\FhirResponse;
use App\Services\SatuSehat\FhirDictionary as FhirDictionaryConstants;
use App\Services\SatuSehat\SatuSehatBaseService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EpisodeOfCareService extends SatuSehatBaseService
{
    private const CACHE_KEY = 'satusehat_eoc_detected';
    private const CACHE_TTL = 300; // 5 menit

    /**
     * Aturan prasyarat klinis per episode type.
     *
     * Kunci aturan yang didukung (berlaku di level global maupun per-diagnosa):
     *   gender            → 'L'|'P'  — hard block jika tidak sesuai
     *   min_age_days      → int       — hard block jika umur < nilai
     *   max_age_days      → int       — hard block jika umur > nilai
     *   soft_min_age_days → int       — warning saja, tetap bisa kirim
     *
     * Key khusus:
     *   diagnoses → array<pattern, rules>
     *     Pattern bersifat prefix case-insensitive, '*' opsional di akhir.
     *     'z75*' cocok untuk Z75, Z75.0, Z75.1, Z75.4, dst.
     *     Aturan dalam diagnoses berlaku HANYA jika SEMUA kode ICD-10 pasien
     *     cocok dengan prefix tersebut.
     */
    private const PREREQUISITES = [
        'cacp' => ['min_age_days' => 21_900],
        'hacc' => [
            'diagnoses' => [
                'z75*' => ['min_age_days' => 21_900],
            ],
        ],
        'ANC' => ['gender' => 'P'],
        'PNC' => ['gender' => 'P'],
        'Neonate' => ['max_age_days' => 28],
        'CAD' => ['soft_min_age_days' => 6_570],
    ];

    protected function getResourceType(): string
    {
        return 'EpisodeOfCare';
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // FHIR API — operasi CRUD ke SatuSehat
    // ═══════════════════════════════════════════════════════════════════════════

    public function searchByPatient(string $patientId): FhirResponse
    {
        return $this->search(['patient' => $patientId]);
    }

    public function searchByStatus(string $status): FhirResponse
    {
        return $this->search(['status' => $status]);
    }

    public function searchByType(string $type): FhirResponse
    {
        return $this->search(['type' => $type]);
    }

    public function createEpisodeOfCare(
        string $patientId,
        string $status = 'active',
        ?string $identifier = null,
        ?string $typeCode = null,
        ?string $typeDisplay = null,
        ?array $diagnosis = null,
        ?string $periodStart = null,
        ?string $periodEnd = null,
        ?string $careManagerId = null,
        ?string $managingOrganizationId = null,
    ): FhirResponse {
        $payload = [
            'status' => $status,
            'patient' => ['reference' => "Patient/{$patientId}"],
            'managingOrganization' => ['reference' => 'Organization/' . $managingOrganizationId],
        ];

        if ($identifier) {
            $payload['identifier'] = [
                [
                    'system' => FhirDictionaryConstants::KEMKES_SYS_EPISODE . '/' . $this->getOrganizationId(),
                    'value' => $identifier,
                ]
            ];
        }

        if ($typeCode) {
            $payload['type'] = [
                [
                    'coding' => [
                        [
                            'system' => FhirDictionaryConstants::HL7_CS_EPISODE_TYPE,
                            'code' => $typeCode,
                            'display' => $typeDisplay ?? $typeCode,
                        ]
                    ],
                ]
            ];
        }

        if ($diagnosis) {
            $payload['diagnosis'] = $diagnosis;
        }

        if ($periodStart) {
            $payload['period'] = ['start' => $periodStart];
            if ($periodEnd) {
                $payload['period']['end'] = $periodEnd;
            }
        }

        if ($careManagerId) {
            $payload['careManager'] = ['reference' => "Practitioner/{$careManagerId}"];
        }

        return $this->create($payload);
    }

    public function createOutpatientEpisode(
        string $patientId,
        ?string $identifier = null,
        ?string $careManagerId = null,
        ?array $conditionIds = null,
    ): FhirResponse {
        $diagnosis = $conditionIds
            ? array_map(fn($id, $rank) => [
                'condition' => ['reference' => "Condition/{$id}"],
                'rank' => $rank + 1,
            ], $conditionIds, array_keys($conditionIds))
            : null;

        return $this->createEpisodeOfCare(
            patientId: $patientId,
            identifier: $identifier,
            typeCode: 'hacc',
            typeDisplay: 'Home and Community Care',
            diagnosis: $diagnosis,
            periodStart: now()->toIso8601String(),
            careManagerId: $careManagerId,
        );
    }

    /** Patch status via JSON-Patch (low-level, langsung ke API). */
    public function updateStatus(string $id, string $status): FhirResponse
    {
        return $this->patch($id, [['op' => 'replace', 'path' => '/status', 'value' => $status]]);
    }

    public function finishEpisode(string $id, ?string $periodEnd = null): FhirResponse
    {
        $ops = [['op' => 'replace', 'path' => '/status', 'value' => 'finished']];

        if ($periodEnd) {
            $ops[] = ['op' => 'add', 'path' => '/period/end', 'value' => $periodEnd];
        }

        return $this->patch($id, $ops);
    }

    public function addDiagnosis(string $id, string $conditionId, int $rank = 1): FhirResponse
    {
        return $this->patch($id, [
            [
                'op' => 'add',
                'path' => '/diagnosis/-',
                'value' => ['condition' => ['reference' => "Condition/{$conditionId}"], 'rank' => $rank],
            ]
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Prasyarat klinis
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Validasi prasyarat klinis untuk kombinasi (eoc_code, pasien).
     *
     * @param  string   $eocCode        Kode episode type
     * @param  string   $gender         Jenis kelamin ('L'|'P')
     * @param  int|null $umurHari       Umur dalam hari (null = tidak diketahui)
     * @param  string[] $matchedIcd10   Kode ICD-10 yang cocok untuk episode ini
     * @return array{passed: bool, warning: bool, message: string}
     */
    public function validatePrerequisite(
        string $eocCode,
        string $gender,
        ?int $umurHari,
        array $matchedIcd10 = [],
    ): array {
        $rules = self::PREREQUISITES[$eocCode] ?? null;

        if (!$rules) {
            return $this->prereqOk();
        }

        // 1. Aturan global (semua key kecuali 'diagnoses')
        $globalRules = array_filter($rules, fn($k) => $k !== 'diagnoses', ARRAY_FILTER_USE_KEY);
        if (!empty($globalRules)) {
            $result = $this->applyRules($globalRules, $gender, $umurHari);
            if (!$result['passed'] || $result['warning']) {
                return $result;
            }
        }

        // 2. Aturan per-prefix diagnosa
        foreach ($rules['diagnoses'] ?? [] as $pattern => $diagRules) {
            if (empty($matchedIcd10)) {
                continue;
            }

            // Normalisasi prefix: strip '*' opsional di akhir, uppercase
            $prefix = strtoupper(rtrim($pattern, '*'));

            $matchingCodes = array_filter(
                $matchedIcd10,
                fn($code) => str_starts_with(strtoupper($code), $prefix),
            );

            // Berlaku hanya jika SEMUA kode pasien cocok dengan prefix ini
            if (!empty($matchingCodes) && \count($matchingCodes) === \count($matchedIcd10)) {
                $result = $this->applyRules($diagRules, $gender, $umurHari, $prefix);
                if (!$result['passed'] || $result['warning']) {
                    return $result;
                }
            }
        }

        return $this->prereqOk();
    }

    /**
     * Terapkan satu set aturan (global atau per-diagnosa) terhadap data pasien.
     *
     * @param  string|null $diagPrefix  Jika dari diagnoses[], prefix pemicunya (untuk pesan error)
     */
    private function applyRules(array $rules, string $gender, ?int $umurHari, ?string $diagPrefix = null): array
    {
        $prefixLabel = $diagPrefix ? " pada kode {$diagPrefix}" : '';

        if (isset($rules['gender']) && $gender !== $rules['gender']) {
            $label = $rules['gender'] === 'P' ? 'Perempuan' : 'Laki-laki';
            return $this->prereqBlock("Hanya untuk pasien {$label}{$prefixLabel}.");
        }

        if (isset($rules['min_age_days'])) {
            if ($umurHari === null) {
                return $this->prereqBlock("Tanggal lahir tidak tersedia untuk validasi umur{$prefixLabel}.");
            }
            if ($umurHari < $rules['min_age_days']) {
                $tahun = (int) round($rules['min_age_days'] / 365);
                return $this->prereqBlock("Hanya untuk Lansia (≥ {$tahun} tahun){$prefixLabel}.");
            }
        }

        if (isset($rules['max_age_days'])) {
            if ($umurHari === null) {
                return $this->prereqBlock("Tanggal lahir tidak tersedia untuk validasi umur{$prefixLabel}.");
            }
            if ($umurHari > $rules['max_age_days']) {
                return $this->prereqBlock("Hanya untuk Neonatus (≤ {$rules['max_age_days']} hari){$prefixLabel}.");
            }
        }

        if (isset($rules['soft_min_age_days']) && $umurHari !== null && $umurHari < $rules['soft_min_age_days']) {
            $tahun = (int) round($rules['soft_min_age_days'] / 365);
            return $this->prereqWarn("Biasanya untuk pasien ≥ {$tahun} tahun{$prefixLabel}. Tetap dapat dikirim.");
        }

        return $this->prereqOk();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Deteksi — cross-DB: SIMRS + lokal
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Deteksi semua episode type untuk pasien yang sudah memiliki IHS SatuSehat.
     *
     * Hasil di-cache 5 menit. Gunakan detectAllCached() untuk versi cached.
     */
    public function detectAll(): Collection
    {
        $mappings = EpisodeOfCareMap::getCached(); // grouped by eoc_code

        $allMappedCodes = $mappings->flatten()->pluck('icd10_code')->unique()->values()->all();

        if (empty($allMappedCodes)) {
            return collect();
        }

        $ssPatients = SatuSehatPatient::whereNotNull('ihs_number')
            ->whereNotNull('nik')
            ->where('nik', '!=', '')
            ->select(['nik', 'ihs_number', 'name'])
            ->get()
            ->keyBy('nik');

        if ($ssPatients->isEmpty()) {
            return collect();
        }

        // Reverse map: icd10_code → [eoc_codes]
        $icd10ToEoc = [];
        foreach ($mappings as $eocCode => $rows) {
            foreach ($rows as $row) {
                $icd10ToEoc[$row->icd10_code][] = $eocCode;
            }
        }

        try {
            $diagnoses = DB::connection('simrs')
                ->table('diagnosa_pasien as dp')
                ->join('reg_periksa as rp', 'dp.no_rawat', '=', 'rp.no_rawat')
                ->join('pasien as p', 'rp.no_rkm_medis', '=', 'p.no_rkm_medis')
                ->whereIn('dp.kd_penyakit', $allMappedCodes)
                ->whereIn('p.no_ktp', $ssPatients->keys()->all())
                ->select([
                    'p.no_rkm_medis',
                    'p.nm_pasien',
                    'p.no_ktp',
                    'p.jk',
                    'p.tgl_lahir',
                    'dp.kd_penyakit',
                    DB::raw('MIN(rp.tgl_registrasi) as first_date'),
                ])
                ->groupBy(['p.no_rkm_medis', 'p.nm_pasien', 'p.no_ktp', 'p.jk', 'p.tgl_lahir', 'dp.kd_penyakit'])
                ->get();
        } catch (\Throwable) {
            return collect();
        }

        $sentEpisodes = SatuSehatEpisodeOfCare::whereNotNull('local_id')
            ->select(['local_id', 'ihs_number', 'status'])
            ->get()
            ->keyBy('local_id');

        $episodes = [];

        foreach ($diagnoses as $row) {
            $eocCodes = $icd10ToEoc[$row->kd_penyakit] ?? [];
            $umurHari = $row->tgl_lahir
                ? (int) Carbon::parse($row->tgl_lahir)->diffInDays(today())
                : null;

            foreach ($eocCodes as $eocCode) {
                $localId = "{$row->no_rkm_medis}-{$eocCode}-" . (new \DateTime($row->first_date))->format('Ymd');
                $sent = $sentEpisodes->get($localId);

                if (!isset($episodes[$localId])) {
                    $episodes[$localId] = [
                        'local_id' => $localId,
                        'no_rkm_medis' => $row->no_rkm_medis,
                        'nm_pasien' => $row->nm_pasien,
                        'no_ktp' => $row->no_ktp,
                        'jk' => $row->jk,
                        'umur_hari' => $umurHari,
                        'eoc_code' => $eocCode,
                        'icd10_codes' => [],
                        'first_date' => $row->first_date,
                        'ihs_patient' => $ssPatients[$row->no_ktp]?->ihs_number,
                        'sent' => $sent !== null,
                        'sent_status' => $sent?->status,
                        'sent_ihs' => $sent?->ihs_number,
                        'prereq' => $this->prereqOk(),
                    ];
                }

                $episodes[$localId]['icd10_codes'][] = $row->kd_penyakit;

                if ($row->first_date < $episodes[$localId]['first_date']) {
                    $episodes[$localId]['first_date'] = $row->first_date;
                }
            }
        }

        // De-duplikasi ICD-10 dan jalankan validasi prasyarat
        foreach ($episodes as &$ep) {
            $ep['icd10_codes'] = array_values(array_unique($ep['icd10_codes']));
            $ep['prereq'] = $this->validatePrerequisite(
                $ep['eoc_code'],
                $ep['jk'] ?? '',
                $ep['umur_hari'],
                $ep['icd10_codes'],
            );
        }
        unset($ep);

        return collect(array_values($episodes))->sortBy(['eoc_code', 'nm_pasien']);
    }

    public function detectAllCached(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn() => $this->detectAll());
    }

    public function clearDetectionCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Deteksi episode type untuk satu pasien berdasarkan diagnosanya di SIMRS.
     * Lebih cepat dari detectAll() karena hanya scan satu pasien.
     */
    public function detectForPatient(string $noRkmMedis): Collection
    {
        $mappings = EpisodeOfCareMap::getCached();
        $allMappedCodes = $mappings->flatten()->pluck('icd10_code')->unique()->values()->all();

        if (empty($allMappedCodes)) {
            return collect();
        }

        $icd10ToEoc = [];
        foreach ($mappings as $eocCode => $rows) {
            foreach ($rows as $row) {
                $icd10ToEoc[$row->icd10_code][] = $eocCode;
            }
        }

        try {
            $diagnoses = DB::connection('simrs')
                ->table('diagnosa_pasien as dp')
                ->join('reg_periksa as rp', 'dp.no_rawat', '=', 'rp.no_rawat')
                ->join('pasien as p', 'rp.no_rkm_medis', '=', 'p.no_rkm_medis')
                ->where('rp.no_rkm_medis', $noRkmMedis)
                ->whereIn('dp.kd_penyakit', $allMappedCodes)
                ->select([
                    'p.no_rkm_medis',
                    'p.nm_pasien',
                    'p.no_ktp',
                    'p.jk',
                    'p.tgl_lahir',
                    'dp.kd_penyakit',
                    DB::raw('MIN(rp.tgl_registrasi) as first_date'),
                ])
                ->groupBy(['p.no_rkm_medis', 'p.nm_pasien', 'p.no_ktp', 'p.jk', 'p.tgl_lahir', 'dp.kd_penyakit'])
                ->get();
        } catch (\Throwable) {
            return collect();
        }

        $umurHari = null;
        $jk = '';
        if ($diagnoses->isNotEmpty()) {
            $first = $diagnoses->first();
            $umurHari = $first->tgl_lahir
                ? (int) Carbon::parse($first->tgl_lahir)->diffInDays(today())
                : null;
            $jk = $first->jk ?? '';
        }

        $sentEpisodes = SatuSehatEpisodeOfCare::where('local_id', 'like', "{$noRkmMedis}-%")
            ->select(['local_id', 'ihs_number', 'status'])
            ->get()
            ->keyBy('local_id');

        $episodes = [];

        foreach ($diagnoses as $row) {
            $eocCodes = $icd10ToEoc[$row->kd_penyakit] ?? [];
            foreach ($eocCodes as $eocCode) {
                $localId = "{$noRkmMedis}-{$eocCode}-" . (new \DateTime($row->first_date))->format('Ymd');
                $sent = $sentEpisodes->get($localId);

                if (!isset($episodes[$localId])) {
                    $episodes[$localId] = [
                        'local_id' => $localId,
                        'eoc_code' => $eocCode,
                        'icd10_codes' => [],
                        'first_date' => $row->first_date,
                        'sent' => $sent !== null,
                        'sent_status' => $sent?->status,
                        'sent_ihs' => $sent?->ihs_number,
                        'prereq' => $this->prereqOk(),
                    ];
                }

                $episodes[$localId]['icd10_codes'][] = $row->kd_penyakit;

                if ($row->first_date < $episodes[$localId]['first_date']) {
                    $episodes[$localId]['first_date'] = $row->first_date;
                }
            }
        }

        foreach ($episodes as &$ep) {
            $ep['icd10_codes'] = array_values(array_unique($ep['icd10_codes']));
            $ep['prereq'] = $this->validatePrerequisite(
                $ep['eoc_code'],
                $jk,
                $umurHari,
                $ep['icd10_codes'],
            );
        }
        unset($ep);

        // Enrich dengan deskripsi EoC dari FhirDictionary
        $eocCodes = collect($episodes)->pluck('eoc_code')->unique()->values()->all();
        $eocTypes = FhirDictionary::where('type', 'episode-of-care-type')
            ->whereIn('system_code', $eocCodes)
            ->get()
            ->keyBy('system_code');

        foreach ($episodes as &$ep) {
            $type = $eocTypes->get($ep['eoc_code']);
            $ep['eoc_term'] = $type?->system_term ?? $ep['eoc_code'];
            $ep['eoc_definition'] = $type?->system_defenition ?? null;
        }
        unset($ep);

        return collect(array_values($episodes))->sortBy('eoc_code');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // High-level send & status update (dengan validasi + simpan ke DB lokal)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Kirim EpisodeOfCare ke SatuSehat dan simpan hasilnya ke DB lokal.
     *
     * @throws \RuntimeException jika validasi gagal atau API menolak
     */
    public function sendEpisode(string $noRkmMedis, string $eocCode, \DateTime $date): SatuSehatEpisodeOfCare
    {
        $localId = "{$noRkmMedis}-{$eocCode}-{$date->format('Ymd')}";
        // Pastikan belum pernah dikirim
        $existing = SatuSehatEpisodeOfCare::where('local_id', $localId)->first();
        if ($existing?->ihs_number) {
            throw new \RuntimeException("Episode ini sudah terkirim (IHS: {$existing->ihs_number}).");
        }

        // Data pasien dari SIMRS
        try {
            $pasien = DB::connection('simrs')
                ->table('pasien')
                ->where('no_rkm_medis', $noRkmMedis)
                ->select(['no_ktp', 'jk', 'tgl_lahir'])
                ->first();
        } catch (\Throwable $e) {
            throw new \RuntimeException("Gagal mengakses SIMRS: {$e->getMessage()}");
        }

        if (!$pasien) {
            throw new \RuntimeException("Pasien {$noRkmMedis} tidak ditemukan di SIMRS.");
        }

        // Resolusi SatuSehat Patient
        $ssPatient = SatuSehatPatient::findByNik($pasien->no_ktp);
        if (!$ssPatient?->ihs_number) {
            throw new \RuntimeException("Pasien belum terdaftar di SatuSehat.");
        }

        // Kode ICD-10 yang cocok + prasyarat
        $icd10Codes = $this->getMatchedIcd10($noRkmMedis, $eocCode);
        $umurHari = $pasien->tgl_lahir
            ? (int) Carbon::parse($pasien->tgl_lahir)->diffInDays(today())
            : null;

        $prereq = $this->validatePrerequisite($eocCode, $pasien->jk ?? '', $umurHari, $icd10Codes);
        if (!$prereq['passed']) {
            throw new \RuntimeException("Prasyarat tidak terpenuhi: {$prereq['message']}");
        }

        // Tanggal pertama diagnosis sebagai period_start
        $firstDate = $this->getFirstDiagnosisDate($noRkmMedis, $icd10Codes);
        $periodStart = $firstDate
            ? Carbon::parse($firstDate)->toIso8601String()
            : now()->toIso8601String();

        $eocType = FhirDictionary::where('system_code', $eocCode)
            ->where('type', 'episode-of-care-type')
            ->first();

        // Cek apakah SatuSehat sudah punya episode aktif untuk tipe ini (Rule 10110)
        $existingIhs = $this->findActiveEpisodeOnFhir($ssPatient->ihs_number, $eocCode);

        $organizationIhs = $this->resolveOrgIhs();
        if ($existingIhs) {
            // Tautkan episode yang sudah ada ke lokal tanpa POST baru
            $existingDetail = $this->find($existingIhs);
            $existingData = $existingDetail->success ? ($existingDetail->data ?? []) : [];
            $existingStart = $existingData['period']['start'] ?? $periodStart;

            $record = SatuSehatEpisodeOfCare::updateOrCreate(
                ['local_id' => $localId],
                [
                    'ihs_number' => $existingIhs,
                    'identifier' => $localId,
                    'patient_ihs' => $ssPatient->ihs_number,
                    'managing_organization_ihs' => $organizationIhs,
                    'status' => $existingData['status'] ?? 'active',
                    'type_code' => $eocCode,
                    'type_display' => $eocType?->system_term ?? $eocCode,
                    'period_start' => $existingStart,
                    'raw_response' => $existingData ?: null,
                    'synced_at' => now(),
                ],
            );

            $this->clearDetectionCache();

            return $record;
        }

        $response = $this->createEpisodeOfCare(
            patientId: $ssPatient->ihs_number,
            status: 'active',
            identifier: $localId,
            typeCode: $eocCode,
            typeDisplay: $eocType?->system_term ?? $eocCode,
            periodStart: $periodStart,
            managingOrganizationId: $organizationIhs
        );

        if (!$response->success) {
            throw new \RuntimeException($response->error ?? 'SatuSehat menolak permintaan.');
        }

        $record = SatuSehatEpisodeOfCare::updateOrCreate(
            ['local_id' => $localId],
            [
                'ihs_number' => $response->resourceId,
                'identifier' => $localId,
                'patient_ihs' => $ssPatient->ihs_number,
                'managing_organization_ihs' => $organizationIhs,
                'status' => 'active',
                'type_code' => $eocCode,
                'type_display' => $eocType?->system_term ?? $eocCode,
                'period_start' => $periodStart,
                'raw_response' => $response->data,
                'synced_at' => now(),
            ],
        );

        $this->clearDetectionCache();

        return $record;
    }

    /**
     * Cari EpisodeOfCare aktif di SatuSehat berdasarkan patient IHS + type code.
     * Digunakan untuk mencegah duplikasi (Rule 10110).
     *
     * @return string|null IHS number jika ditemukan, null jika tidak
     */
    private function findActiveEpisodeOnFhir(string $patientIhs, string $eocCode): ?string
    {
        try {
            $response = $this->search([
                'patient' => $patientIhs,
                'type' => $eocCode,
                'status' => 'active',
            ]);

            if (!$response->success) {
                return null;
            }

            $entries = $response->data['entry'] ?? [];

            foreach ($entries as $entry) {
                $resource = $entry['resource'] ?? [];
                if (($resource['resourceType'] ?? '') === 'EpisodeOfCare' && isset($resource['id'])) {
                    return $resource['id'];
                }
            }
        } catch (\Throwable) {
            // Jika search gagal, lanjutkan dengan POST normal
        }

        return null;
    }

    /**
     * Perbarui status episode yang sudah terkirim (high-level: API + DB lokal).
     *
     * @throws \RuntimeException
     */
    public function updateEpisodeStatus(string $localId, string $newStatus): SatuSehatEpisodeOfCare
    {
        $record = SatuSehatEpisodeOfCare::where('local_id', $localId)->firstOrFail();

        if (!$record->ihs_number) {
            throw new \RuntimeException('IHS Number tidak tersedia.');
        }

        $ops = [['op' => 'replace', 'path' => '/status', 'value' => $newStatus]];

        if ($newStatus === 'finished') {
            $ops[] = ['op' => 'add', 'path' => '/period/end', 'value' => now()->toIso8601String()];
        }

        $response = $this->patch($record->ihs_number, $ops);

        if (!$response->success) {
            throw new \RuntimeException($response->error ?? 'Gagal memperbarui status.');
        }

        $record->update([
            'status' => $newStatus,
            'synced_at' => now(),
            ...($newStatus === 'finished' ? ['period_end' => now()] : []),
        ]);

        $this->clearDetectionCache();

        return $record->fresh();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Helpers privat
    // ═══════════════════════════════════════════════════════════════════════════

    private function getMatchedIcd10(string $noRkmMedis, string $eocCode): array
    {
        $mappedCodes = EpisodeOfCareMap::getCached()->get($eocCode)?->pluck('icd10_code')->all() ?? [];

        if (empty($mappedCodes)) {
            return [];
        }

        try {
            return DB::connection('simrs')
                ->table('diagnosa_pasien as dp')
                ->join('reg_periksa as rp', 'dp.no_rawat', '=', 'rp.no_rawat')
                ->where('rp.no_rkm_medis', $noRkmMedis)
                ->whereIn('dp.kd_penyakit', $mappedCodes)
                ->distinct()
                ->pluck('dp.kd_penyakit')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function getFirstDiagnosisDate(string $noRkmMedis, array $icd10Codes): ?string
    {
        if (empty($icd10Codes)) {
            return null;
        }

        try {
            return DB::connection('simrs')
                ->table('diagnosa_pasien as dp')
                ->join('reg_periksa as rp', 'dp.no_rawat', '=', 'rp.no_rawat')
                ->where('rp.no_rkm_medis', $noRkmMedis)
                ->whereIn('dp.kd_penyakit', $icd10Codes)
                ->min('rp.tgl_registrasi');
        } catch (\Throwable) {
            return null;
        }
    }

    /** Resolve IHS org yang valid dari tabel lokal — null jika belum tersimpan (hindari FK violation). */
    private function resolveOrgIhs(): ?string
    {
        return SatuSehatOrganization::where('identifier', 'RS')->value('ihs_number');
    }

    private function prereqOk(): array
    {
        return ['passed' => true, 'warning' => false, 'message' => ''];
    }

    private function prereqBlock(string $message): array
    {
        return ['passed' => false, 'warning' => false, 'message' => $message];
    }

    private function prereqWarn(string $message): array
    {
        return ['passed' => true, 'warning' => true, 'message' => $message];
    }
}
