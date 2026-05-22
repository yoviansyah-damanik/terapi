<?php

namespace App\Services\SatuSehat;

use App\Exceptions\SatuSehat\SatuSehatException;
use App\Models\Mapping\Icd10Map;
use App\Models\Mapping\{MedicationMap, LabMap, RadMap, ProcedureMap, SurgeryNoteMap};
use App\Models\SatuSehat\{SatuSehatCondition, SatuSehatEncounter, SatuSehatObservation, SatuSehatProcedure};
use App\Models\SatuSehat\{SatuSehatLocation, SatuSehatPatient, SatuSehatPractitioner, SatuSehatOrganization};
use App\Models\SatuSehat\{SatuSehatMedication, SatuSehatMedicationRequest, SatuSehatMedicationDispense, SatuSehatServiceRequest};
use App\Models\SatuSehat\{SatuSehatMedicationStatement, SatuSehatMedicationAdministration, SatuSehatCarePlan};
use App\Models\SatuSehat\{SatuSehatAllergyIntolerance, SatuSehatClinicalImpression, SatuSehatSpecimen, SatuSehatImagingStudy, SatuSehatDiagnosticReport};
use App\Models\SatuSehat\SatuSehatComposition;
use App\Models\SatuSehat\SatuSehatQuestionnaireResponse;
use App\Models\SatuSehat\SatuSehatBundle;
use App\Models\SatuSehat\SatuSehatBundleLog;
use App\Models\Simrs\{DiagnosaPasien, PemeriksaanRalan, PemeriksaanRanap, ProsedurPasien, RegPeriksa, DetailPemberianObat, ResepPulang, PeriksaLab, Pegawai, CatatanAdimeGizi, SaranKesanLab, HasilRadiologi, Operasi, PermintaanRadiologi};
use App\Services\SatuSehat\Resources\{ConditionService, EncounterService, ObservationService, ProcedureService, CompositionService};
use App\Services\SatuSehat\Resources\{MedicationService, MedicationRequestService, MedicationDispenseService, MedicationAdministrationService, ServiceRequestService, CarePlanService};
use App\Services\SatuSehat\Resources\QuestionnaireResponseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Orkestrasi pengiriman data FHIR eRM dari SIMRS ke Satu Sehat.
 */
class ErmFhirService
{
    private array $encounterValidStatuses = ['arrived', 'triaged', 'in-progress', 'onleave', 'finished', 'cancelled'];

    /** Jeda antar API call (µs) */
    private int $apiDelayUs;

    /** Maks request per menit ke Satu Sehat */
    private int $rateLimitPerMinute;

    /** Cache key prefix untuk rate limit counter */
    private const RL_KEY_PREFIX = 'sse_rl_';

    public function __construct()
    {
        $this->apiDelayUs = (int) (config('satusehat.api_delay_ms', 300) * 1_000);
        $this->rateLimitPerMinute = (int) config('satusehat.rate_limit_per_minute', 50);
    }

    private function getOrganizationId(): string
    {
        return config('satusehat.organization_id', '');
    }

    /**
     * Terapkan delay + rate limit (maks N req/menit).
     * Jika kuota menit ini penuh, tunggu sampai awal menit berikutnya.
     */
    private function throttle(): void
    {
        if ($this->rateLimitPerMinute > 0) {
            $windowKey = self::RL_KEY_PREFIX . (int) (time() / 60);
            $count = (int) Cache::get($windowKey, 0) + 1;

            if ($count > $this->rateLimitPerMinute) {
                // Kuota menit ini penuh — tunggu ke awal menit berikutnya + 1s buffer
                $waitSeconds = 60 - (time() % 60) + 1;
                sleep(max(1, $waitSeconds));

                // Reset counter di window baru
                $windowKey = self::RL_KEY_PREFIX . (int) (time() / 60);
                $count = 1;
            }

            // Simpan counter dengan TTL 2 menit agar key bersih otomatis
            Cache::put($windowKey, $count, 120);
        }

        if ($this->apiDelayUs > 0) {
            usleep($this->apiDelayUs);
        }
    }

    /** Cek apakah error message menandakan duplicate (RuleNumber: 20002). */
    private function isDuplicate(\Throwable|string $error): bool
    {
        $msg = $error instanceof \Throwable ? $error->getMessage() : (string) $error;
        return str_contains($msg, '20002') || str_contains(strtolower($msg), 'duplicate');
    }

    /**
     * Cari resource duplikat di Satu Sehat berdasarkan hasil search, cocokkan dengan local identifier.
     * Return resource array jika ditemukan, null jika tidak.
     */
    private function findByIdentifier(object $searchResponse, string $localId): ?array
    {
        if (!$searchResponse->success)
            return null;

        foreach ($searchResponse->getResources() as $resource) {
            if (!empty($resource['identifier'])) {
                foreach ($resource['identifier'] as $identifier) {
                    if (isset($identifier['value']) && $identifier['value'] == $localId) {
                        return $resource;
                    }
                }
            }
            if (!empty($resource['code'])) {
                foreach ($resource['code']['coding'] as $coding) {
                    if (isset($coding['code']) && $coding['code'] == $localId) {
                        return $resource;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Kirim Encounter berdasarkan data kunjungan SIMRS.
     *
     * @return array{success: bool, message: string, encounter?: SatuSehatEncounter}
     */
    public function sendEncounter(RegPeriksa $reg, string $status, ?SatuSehatBundle $bundle = null): array
    {
        // Pastikan bundle log ada jika belum dilempar dari pemanggil
        $bundle = $this->ensureBundleLog($reg->no_rawat, $bundle);

        $reg->loadMissing(['pasien', 'dokter', 'poliklinik']);

        // Cek status
        if (!in_array($status, $this->encounterValidStatuses)) {
            return ['success' => false, 'message' => "Status tidak valid. Harus salah satu dari: " . implode(', ', $this->encounterValidStatuses)];
        }

        // Cek jika sudah ada
        $existing = SatuSehatEncounter::where('local_id', $reg->no_rawat)->first();
        if ($existing) {
            return ['success' => false, 'message' => 'Encounter sudah pernah dikirim (IHS: ' . $existing->ihs_number . ').'];
        }

        // Lookup patient IHS via NIK
        $nik = $reg->pasien?->no_ktp;
        if (!$nik) {
            return ['success' => false, 'message' => 'Data NIK pasien tidak ditemukan.'];
        }
        $patient = SatuSehatPatient::findByNik($nik);
        if (!$patient) {
            return ['success' => false, 'message' => 'Pasien belum terdaftar di Satu Sehat. Sinkronkan pasien terlebih dahulu.'];
        }

        // Lookup practitioner IHS via NIK dokter (kd_dokter = NIK di tabel pegawai)
        $practitioner = SatuSehatPractitioner::findByNik($reg->dokter->pegawai->no_ktp);
        if (!$practitioner) {
            return ['success' => false, 'message' => 'Dokter DPJP belum terdaftar di Satu Sehat. Sinkronkan practitioner terlebih dahulu.'];
        }

        // Lookup location IHS via kd_poli
        $location = SatuSehatLocation::where('identifier', $reg->kd_poli)->first();
        if (!$location) {
            return ['success' => false, 'message' => "Lokasi poliklinik '{$reg->kd_poli}' belum terdaftar di Satu Sehat."];
        }

        // Tentukan class encounter
        $class = $reg->status_lanjut === 'Ranap' ? 'IMP' : 'AMB';
        if (
            Str::contains(strtolower($reg->poliklinik?->nm_poli ?? ''), 'gawat') ||
            Str::contains(strtolower($reg->kd_poli ?? ''), ['igd', 'ugd'])
        ) {
            $class = 'EMER';
        }

        // Tentukan period berdasarkan jenis kunjungan
        if ($reg->status_lanjut === 'Ranap') {
            $reg->loadMissing(['kamarInap']);
            $firstRoom = $reg->kamarInap?->sortBy(fn($k) => $k->tgl_masuk->toDateString() . $k->jam_masuk)->first();
            $lastRoom = $reg->kamarInap?->sortBy(fn($k) => $k->tgl_masuk->toDateString() . $k->jam_masuk)->last();
            $periodStart = $firstRoom?->tgl_masuk?->format('Y-m-d') . 'T' . ($firstRoom?->jam_masuk ?? '00:00:00') . '+07:00';
            $periodEnd = $lastRoom?->tgl_keluar
                ? $lastRoom->tgl_keluar->format('Y-m-d') . 'T' . ($lastRoom->jam_keluar ?? '00:00:00') . '+07:00'
                : null;
        } else {
            $periodStart = $reg->tgl_registrasi?->format('Y-m-d') . 'T' . ($reg->jam_reg ?? '00:00:00') . '+07:00';
            $periodEnd = $reg->mutasiBerkas?->kembali?->format('Y-m-d\TH:i:sP') ?? null;
        }

        try {
            $service = new EncounterService();
            $response = $service->createEncounter(
                patient: $patient,
                practitioner: $practitioner,
                location: $location,
                careNumber: $reg->no_rawat,
                status: $status,
                class: $class,
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                condition: null
            );

            $this->logBundleItem(
                bundle: $bundle,
                type: 'Encounter',
                localId: $reg->no_rawat,
                status: $response->success ? 'success' : 'failed',
                payload: $service->getLastPayload(),
                response: $response->data,
                error: $response->error,
                ihsId: $response->resourceId
            );

            $this->throttle();
        } catch (SatuSehatException $e) {
            if (str_contains($e->getMessage(), '20002')) {
                return $this->findAndSaveExistingEncounter($reg, $patient, $practitioner, $location, $class, $periodStart, $periodEnd, $bundle);
            }

            $this->logBundleItem(
                bundle: $bundle,
                type: 'Encounter',
                localId: $reg->no_rawat,
                status: 'failed',
                error: $e->getMessage()
            );

            return ['success' => false, 'message' => 'Satu Sehat API error: ' . $e->getMessage()];
        } catch (\Exception $e) {
            $this->logBundleItem(
                bundle: $bundle,
                type: 'Encounter',
                localId: $reg->no_rawat,
                status: 'failed',
                error: $e->getMessage()
            );

            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }

        if (!$response->success) {
            return ['success' => false, 'message' => 'Satu Sehat API error: ' . $response->error];
        }

        $encounter = SatuSehatEncounter::create([
            'ihs_number' => $response->resourceId,
            'local_id' => $reg->no_rawat,
            'status' => 'arrived',
            'class' => $class,
            'patient_ihs' => $patient->ihs_number,
            'patient_name' => $reg->pasien?->nm_pasien,
            'practitioner_ihs' => $practitioner->ihs_number,
            'practitioner_name' => $reg->dokter?->nm_dokter,
            'location_ihs' => $location->ihs_number,
            'location_name' => $location->name,
            'service_provider' => config('satusehat.organization_id'),
            'period_start' => $periodStart ? Carbon::parse($periodStart) : null,
            'period_end' => $periodEnd ? Carbon::parse($periodEnd) : null,
            'raw_response' => $response->data,
            'status_history' => $response->data['statusHistory'],
            'diagnosis' => $response->data['diagnosis'] ?? null,
            'synced_at' => now(),
        ]);

        return ['success' => true, 'message' => 'Encounter berhasil dikirim ke Satu Sehat.', 'encounter' => $encounter];
    }

    /**
     * Kirim Condition (diagnosa ICD-10) ke Satu Sehat.
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public function sendConditions(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $bundle = $this->ensureBundleLog($reg->no_rawat, $bundle);

        $diagnosas = DiagnosaPasien::where('no_rawat', $reg->no_rawat)
            ->with('penyakit')
            ->orderBy('prioritas')
            ->get();

        if ($diagnosas->isEmpty()) {
            return ['success' => false, 'message' => 'Tidak ada data diagnosa untuk kunjungan ini.'];
        }

        $icd10Map = Icd10Map::getCached();
        $service = new ConditionService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        foreach ($diagnosas as $diag) {
            $code = $diag->kd_penyakit;
            if (!$code) {
                continue;
            }

            $localId = $reg->no_rawat . '-CON_' . $code . '-' . $reg->tgl_registrasi->format('Ymd') . '-' . str_replace(':', '', $reg->jam_reg ?? '000000');
            if ($selectedIds !== null && !in_array($localId, $selectedIds)) {
                continue;
            }

            // Lewati jika sudah ada
            if (SatuSehatCondition::where('local_id', $localId)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                $this->logBundleItem($bundle, 'Condition', $localId, 'skipped', null, null, 'Sudah tersinkronisasi.');
                continue;
            }

            $display = $diag->penyakit?->nm_penyakit ?? $icd10Map->get($code)?->system_term ?? $code;
            $itemLabel = "Condition: {$code} — {$display}";

            try {
                $sentAt = now()->toIso8601String();
                $response = $service->createDiagnosis(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    icdCode: $code,
                    icdDisplay: $display,
                    practitionerId: $encounter->practitioner_ihs,
                );

                $this->logBundleItem(
                    bundle: $bundle,
                    type: 'Condition',
                    localId: $localId,
                    status: $response->success ? 'success' : 'failed',
                    payload: $service->getLastPayload(),
                    response: $response->data,
                    error: $response->error,
                    ihsId: $response->resourceId
                );

                $this->throttle();

                if (!$response->success) {
                    $errors[] = "{$code}: " . $response->error;
                    $items[] = ['id' => $code, 'label' => $itemLabel, 'status' => 'fail', 'message' => $response->error, 'sent_at' => $sentAt];
                    continue;
                }

                SatuSehatCondition::create([
                    'ihs_number' => $response->resourceId,
                    'local_id' => $localId,
                    'identifier' => json_encode($response->data['identifier']),
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'clinical_status' => 'active',
                    'category' => 'encounter-diagnosis',
                    'icd_code' => $code,
                    'icd_display' => $display,
                    'recorder_ihs' => $encounter->practitioner_ihs,
                    'raw_response' => $response->data,
                    'synced_at' => now(),
                ]);

                $items[] = ['id' => $code, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                $this->logBundleItem(
                    bundle: $bundle,
                    type: 'Condition',
                    localId: $code,
                    status: 'failed',
                    error: $e->getMessage()
                );
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByEncounter($encounter->ihs_number);
                    $found = $this->findByIdentifier($searchResp, $code);

                    if ($found) {
                        $localId = $reg->no_rawat . '-CON_' . $code . '-' . $reg->tgl_registrasi->format('Ymd') . '-' . str_replace(':', '', $reg->jam_reg ?? '000000');
                        SatuSehatCondition::create([
                            'ihs_number' => $found['id'],
                            'local_id' => $localId,
                            'identifier' => json_encode($found['identifier'] ?? []),
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'clinical_status' => $found['clinicalStatus']['coding'][0]['code'] ?? 'active',
                            'category' => 'encounter-diagnosis',
                            'icd_code' => $code,
                            'icd_display' => $display,
                            'recorder_ihs' => $encounter->practitioner_ihs,
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $code, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt ?? now()->toIso8601String()];
                        $sent++;
                        continue;
                    }
                }
                $errors[] = "{$code}: " . $e->getMessage();
                $items[] = ['id' => $code, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => now()->toIso8601String()];
            }
        }

        $msg = $sent > 0
            ? "{$sent} Diagnosa berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Diagnosa.' : 'Tidak ada Diagnosa yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'Condition', $msg, $errors, $warnings, '/^([^:]+):/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    /**
     * Kirim Procedure (prosedur ICD-9) ke Satu Sehat.
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public function sendProcedures(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $bundle = $this->ensureBundleLog($reg->no_rawat, $bundle);

        $prosedurs = ProsedurPasien::where('no_rawat', $reg->no_rawat)
            ->with('icd9')
            ->orderBy('prioritas')
            ->get();

        // Tindakan dari SIMRS (rawat_jl_* / rawat_inap_*)
        $isRalan = $reg->status_lanjut === 'Ralan';
        $refTable = $isRalan ? 'jns_perawatan' : 'jns_perawatan_inap';
        $tndTables = $isRalan
            ? ['DR' => 'rawat_jl_dr', 'PR' => 'rawat_jl_pr', 'DRPR' => 'rawat_jl_drpr']
            : ['DR' => 'rawat_inap_dr', 'PR' => 'rawat_inap_pr', 'DRPR' => 'rawat_inap_drpr'];

        $allTindakan = collect();
        try {
            $simrs = DB::connection('simrs');
            foreach ($tndTables as $suffix => $table) {
                $simrs->table("{$table} as t")
                    ->join("{$refTable} as ref", 't.kd_jenis_prw', '=', 'ref.kd_jenis_prw')
                    ->where('t.no_rawat', $reg->no_rawat)
                    ->select('t.kd_jenis_prw', 't.tgl_perawatan', 't.jam_rawat', 'ref.nm_perawatan')
                    ->orderBy('t.tgl_perawatan')->orderBy('t.jam_rawat')
                    ->get()
                    ->each(fn($row) => $allTindakan->push((object) array_merge((array) $row, ['_suffix' => $suffix])));
            }
        } catch (\Throwable) {
            // SIMRS tidak tersedia — lewati tindakan
        }

        if ($prosedurs->isEmpty() && $allTindakan->isEmpty()) {
            return ['success' => false, 'message' => 'Tidak ada data prosedur maupun tindakan untuk kunjungan ini.'];
        }

        $service = new ProcedureService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];
        $orgId = SatuSehatOrganization::where('identifier', 'RS')->value('ihs_number');
        $tndSystem = FhirDictionary::KEMKES_SYS_PROCEDURE . ($orgId ? '/' . $orgId : '');

        // ── ICD-9 Prosedur ─────────────────────────────────────────────────────
        foreach ($prosedurs as $prosed) {
            $code = $prosed->kode;
            if (!$code) {
                continue;
            }

            $localId = $reg->no_rawat . '-PRO_' . $code . '-' . $reg->tgl_registrasi->format('Ymd') . '-' . str_replace(':', '', $reg->jam_reg ?? '000000');
            if ($selectedIds !== null && !in_array($localId, $selectedIds)) {
                continue;
            }

            if (SatuSehatProcedure::where('local_id', $localId)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                $this->logBundleItem($bundle, 'Procedure', $localId, 'skipped', null, null, 'Sudah tersinkronisasi.');
                continue;
            }

            $display = $prosed->icd9?->deskripsi_panjang ?? $prosed->icd9?->deskripsi_pendek ?? $code;
            $itemLabel = "Procedure ICD-9: {$code} — {$display}";

            try {
                $sentAt = now()->toIso8601String();
                $response = $service->createProcedure(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    performerId: $encounter->practitioner_ihs,
                    codeSystem: FhirDictionary::HL7_ICD9CM,
                    codeTerm: $code,
                    codeDisplay: $display,
                    categoryCode: '103693007',
                    categoryDisplay: 'Diagnostic procedure',
                    categorySystem: FhirDictionary::SNOMED,
                    performedDateTime: $reg->tgl_registrasi->format('Y-m-d') . 'T' . $reg->jam_reg . '+07:00'
                );

                $this->logBundleItem(bundle: $bundle, type: 'Procedure', localId: $localId, status: $response->success ? 'success' : 'failed', payload: $service->getLastPayload(), response: $response->data, error: $response->error, ihsId: $response->resourceId);
                $this->throttle();

                if (!$response->success) {
                    $errors[] = "{$code}: " . $response->error;
                    $items[] = ['id' => $code, 'label' => $itemLabel, 'status' => 'fail', 'message' => $response->error, 'sent_at' => $sentAt];
                    continue;
                }

                SatuSehatProcedure::create([
                    'ihs_number' => $response->resourceId,
                    'local_id' => $localId,
                    'identifier' => json_encode($response->data['identifier']),
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'status' => 'completed',
                    'code' => $code,
                    'code_display' => $display,
                    'raw_response' => $response->data,
                    'synced_at' => now(),
                ]);

                $items[] = ['id' => $code, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                $this->logBundleItem(bundle: $bundle, type: 'Procedure', localId: $code, status: 'failed', error: $e->getMessage());
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByEncounter($encounter->ihs_number);
                    $found = $this->findByIdentifier($searchResp, $code);
                    if ($found) {
                        SatuSehatProcedure::create([
                            'ihs_number' => $found['id'],
                            'local_id' => $localId,
                            'identifier' => $code,
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'performer_ihs' => $encounter->practitioner_ihs,
                            'status' => 'completed',
                            'code' => $code,
                            'code_display' => $display,
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $code, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        continue;
                    }
                }
                $errors[] = "{$code}: " . $e->getMessage();
            }
        }

        // ── Tindakan SIMRS ────────────────────────────────────────────────────
        // Pre-load semua mapping sekaligus agar tidak N+1
        $tndSourceTable = $isRalan ? 'jalan' : 'inap';
        $tndCodes = $allTindakan->pluck('kd_jenis_prw')->unique()->values()->toArray();
        $tndMappings = \App\Models\Mapping\ProcedureMap::where('source_table', $tndSourceTable)
            ->whereIn('procedure_code', $tndCodes)
            ->get()
            ->keyBy('procedure_code');

        foreach ($allTindakan as $tnd) {
            $code = $tnd->kd_jenis_prw;
            $display = $tnd->nm_perawatan ?? $code;
            $tglFmt = $tnd->tgl_perawatan ? \Carbon\Carbon::parse($tnd->tgl_perawatan)->format('Ymd') : '';
            $jamFmt = str_replace(':', '', $tnd->jam_rawat ?? '000000');
            $localId = $reg->no_rawat . '-TND_' . $tnd->_suffix . '_' . $code . '-' . $tglFmt . '-' . $jamFmt;

            if ($selectedIds !== null && !in_array($localId, $selectedIds)) {
                continue;
            }

            if (SatuSehatProcedure::where('local_id', $localId)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                $this->logBundleItem($bundle, 'Procedure', $localId, 'skipped', null, null, 'Sudah tersinkronisasi.');
                continue;
            }

            $mapping = $tndMappings->get($code);

            // Warning: belum ada mapping SNOMED — skip kirim
            if (!$mapping || !$mapping->system_code) {
                $warnings[] = "[TND:{$code}] Belum ada mapping SNOMED CT. Petakan di Local Terminology → Tindakan.";
                $this->logBundleItem($bundle, 'Procedure', $localId, 'warning', null, null, 'Belum ada mapping SNOMED CT.');
                $items[] = ['id' => $localId, 'label' => "Tindakan ({$tnd->_suffix}): {$code} — {$display}", 'status' => 'warn', 'message' => 'Belum ter-mapping SNOMED CT.', 'sent_at' => now()->toIso8601String()];
                continue;
            }

            // Warning: belum ada category — skip kirim
            if (!$mapping->category_code) {
                $warnings[] = "[TND:{$code}] Belum ada kategori Procedure. Petakan di Local Terminology → Tindakan.";
                $this->logBundleItem($bundle, 'Procedure', $localId, 'warning', null, null, 'Belum ada kategori Procedure.');
                $items[] = ['id' => $localId, 'label' => "Tindakan ({$tnd->_suffix}): {$code} — {$display}", 'status' => 'warn', 'message' => 'Belum ada kategori Procedure.', 'sent_at' => now()->toIso8601String()];
                continue;
            }
            $snomedCode = $mapping->system_code;
            $snomedDisplay = $mapping->system_term ?? $display;
            $snomedSystem = $mapping->system_display ?? FhirDictionary::SNOMED;
            $categoryCode = $mapping->category_code ?? null;
            $categoryDisplay = $mapping->category_term ?? null;
            $categorySystem = $mapping->category_display ?? FhirDictionary::SNOMED;

            $performedDt = ($tnd->tgl_perawatan
                ? \Carbon\Carbon::parse($tnd->tgl_perawatan)->format('Y-m-d')
                : $reg->tgl_registrasi->format('Y-m-d'))
                . 'T' . ($tnd->jam_rawat ?? '00:00:00') . '+07:00';

            $itemLabel = "Tindakan ({$tnd->_suffix}): {$snomedCode} — {$snomedDisplay}";

            try {
                $sentAt = now()->toIso8601String();
                $response = $service->createProcedure(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    performerId: $encounter->practitioner_ihs,
                    codeSystem: $snomedSystem,
                    codeTerm: $snomedCode,
                    codeDisplay: $snomedDisplay,
                    categoryCode: $categoryCode,
                    categoryDisplay: $categoryDisplay,
                    categorySystem: $categorySystem,
                    performedDateTime: $performedDt
                );

                $this->logBundleItem(bundle: $bundle, type: 'Procedure', localId: $localId, status: $response->success ? 'success' : 'failed', payload: $service->getLastPayload(), response: $response->data, error: $response->error, ihsId: $response->resourceId);
                $this->throttle();

                if (!$response->success) {
                    $errors[] = "{$code}: " . $response->error;
                    $items[] = ['id' => $localId, 'label' => $itemLabel, 'status' => 'fail', 'message' => $response->error, 'sent_at' => $sentAt];
                    continue;
                }

                SatuSehatProcedure::create([
                    'ihs_number' => $response->resourceId,
                    'local_id' => $localId,
                    'identifier' => json_encode($response->data['identifier'] ?? []),
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'status' => 'completed',
                    'code' => $snomedCode,
                    'code_display' => $snomedDisplay,
                    'raw_response' => $response->data,
                    'synced_at' => now(),
                ]);

                $items[] = ['id' => $localId, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                $this->logBundleItem(bundle: $bundle, type: 'Procedure', localId: $localId, status: 'failed', error: $e->getMessage());
                $errors[] = "{$code}: " . $e->getMessage();
            }
        }

        $msg = $sent > 0
            ? "{$sent} Prosedur/Tindakan berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Prosedur/Tindakan.' : 'Tidak ada Prosedur/Tindakan yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'Procedure', $msg, $errors, $warnings, '/^([^:]+):/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    /**
     * Kirim Observation (tanda vital) dari data pemeriksaan ke Satu Sehat.
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public function sendObservations(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $bundle = $this->ensureBundleLog($reg->no_rawat, $bundle);

        $isRanap = $reg->status_lanjut === 'Ranap';
        $pemeriksaans = $isRanap
            ? PemeriksaanRanap::where('no_rawat', $reg->no_rawat)->orderBy('tgl_perawatan')->orderBy('jam_rawat')->get()
            : PemeriksaanRalan::where('no_rawat', $reg->no_rawat)->orderBy('tgl_perawatan')->orderBy('jam_rawat')->get();

        if ($pemeriksaans->isEmpty()) {
            return ['success' => false, 'message' => 'Tidak ada data pemeriksaan (tanda vital) untuk kunjungan ini.'];
        }

        $service = new ObservationService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        foreach ($pemeriksaans as $periksa) {
            $baseIdStr = $periksa->tgl_perawatan?->format('Y-m-d') . '|' . ($periksa->jam_rawat ?? '00:00:00');

            $effectiveDt = $periksa->tgl_perawatan?->format('Y-m-d')
                . 'T' . ($periksa->jam_rawat ?? '00:00:00')
                . '+07:00';

            foreach ($this->extractVitalSigns($periksa) as $type => $value) {
                // Gunakan format id per item
                $localId = $reg->no_rawat . '-OBS_' . strtoupper($type) . '-' . $periksa->tgl_perawatan?->format('Ymd') . '-' . str_replace(':', '', $periksa->jam_rawat ?? '000000');
                if ($selectedIds !== null && !in_array($localId, $selectedIds)) {
                    continue;
                }
                if (SatuSehatObservation::where('local_id', $localId)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                    $this->logBundleItem($bundle, 'Observation', $localId, 'skipped', null, null, 'Sudah tersinkronisasi.');
                    continue;
                }
                $vsCfg = $this->vitalSignCode($type);
                $itemLabel = "TTV {$type}: {$value} " . ($vsCfg['unit'] ?? '');
                $sentAt = now()->toIso8601String();

                try {
                    $response = $service->createVitalSign(
                        patientId: $encounter->patient_ihs,
                        encounterId: $encounter->ihs_number,
                        type: $type,
                        value: (float) $value,
                        effectiveDateTime: $effectiveDt,
                        performerId: $encounter->practitioner_ihs,
                    );

                    $this->logBundleItem(
                        bundle: $bundle,
                        type: 'Observation',
                        localId: $localId,
                        status: $response->success ? 'success' : 'failed',
                        payload: $service->getLastPayload(),
                        response: $response->data,
                        error: $response->error,
                        ihsId: $response->resourceId
                    );

                    $this->throttle();

                    if (!$response->success) {
                        $errors[] = "{$type}: " . $response->error;
                        $items[] = ['id' => $localId, 'label' => $itemLabel, 'status' => 'fail', 'message' => $response->error, 'sent_at' => $sentAt];
                        continue;
                    }

                    SatuSehatObservation::create([
                        'ihs_number' => $response->resourceId,
                        'local_id' => $localId,
                        'identifier' => json_encode($response->data['identifier']),
                        'patient_ihs' => $encounter->patient_ihs,
                        'encounter_ihs' => $encounter->ihs_number,
                        'status' => 'final',
                        'category' => 'vital-signs',
                        'code' => $vsCfg['code'],
                        'code_display' => $vsCfg['display'],
                        'value_type' => 'Quantity',
                        'value_quantity' => (float) $value,
                        'value_unit' => $vsCfg['unit'],
                        'effective_datetime' => Carbon::parse($effectiveDt),
                        'performer_ihs' => $encounter->practitioner_ihs,
                        'raw_response' => $response->data,
                        'synced_at' => now(),
                    ]);

                    $items[] = ['id' => $localId, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                    $sent++;
                } catch (\Exception $e) {
                    $this->logBundleItem(
                        bundle: $bundle,
                        type: 'Observation',
                        localId: $localId,
                        status: 'failed',
                        error: $e->getMessage()
                    );

                    if ($this->isDuplicate($e)) {
                        $searchResp = $service->searchByEncounter($encounter->ihs_number);
                        $found = $this->findByIdentifier($searchResp, $localId);
                        if ($found) {
                            SatuSehatObservation::create([
                                'ihs_number' => $found['id'],
                                'local_id' => $reg->no_rawat,
                                'identifier' => $itemIdStr,
                                'patient_ihs' => $encounter->patient_ihs,
                                'encounter_ihs' => $encounter->ihs_number,
                                'status' => 'final',
                                'category' => 'vital-signs',
                                'code' => $vsCfg['code'],
                                'code_display' => $vsCfg['display'],
                                'value_type' => 'Quantity',
                                'value_quantity' => (float) $value,
                                'value_unit' => $vsCfg['unit'],
                                'effective_datetime' => Carbon::parse($effectiveDt),
                                'performer_ihs' => $encounter->practitioner_ihs,
                                'raw_response' => $found,
                                'synced_at' => now(),
                            ]);
                            $items[] = ['id' => $itemIdStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                            $sent++;
                            continue;
                        }
                    }
                    $errors[] = "{$type}: " . $e->getMessage();
                    $items[] = ['id' => $itemIdStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
                }
            }
        }

        $msg = $sent > 0
            ? "{$sent} Tanda Vital berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Tanda Vital.' : 'Tidak ada Tanda Vital yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'Observation', $msg, $errors, $warnings, '/^([^:]+):/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    /**
     * Kirim Medication Request ke Satu Sehat.
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public function sendMedicationRequests(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $bundle = $this->ensureBundleLog($reg->no_rawat, $bundle);
        $obats = DetailPemberianObat::where('no_rawat', $reg->no_rawat)
            ->with(['aturanPakai'])
            ->orderBy('tgl_perawatan')
            ->orderBy('jam')
            ->get();

        $resepPulangs = collect();
        if ($reg->status_lanjut === 'Ranap') {
            $resepPulangs = ResepPulang::where('no_rawat', $reg->no_rawat)
                ->orderBy('tanggal')
                ->orderBy('jam')
                ->get();
        }

        if ($obats->isEmpty() && $resepPulangs->isEmpty()) {
            return ['success' => true, 'skipped' => true, 'count' => 0, 'message' => 'Tidak ada data pemberian obat untuk kunjungan ini.', 'items' => []];
        }

        $errors = [];
        $warnings = [];
        $serviceMed = new MedicationService();
        $serviceReq = new MedicationRequestService();
        $sent = 0;
        $items = [];

        $processObat = function ($localCode, $tgl, $jam, $jml, $aturan) use ($reg, $encounter, $serviceMed, $serviceReq, &$sent, &$errors, &$warnings, &$items, $selectedIds, $bundle) {
            if (!$localCode)
                return;

            $idStr = $reg->no_rawat . '-MED_REQ_' . $localCode . '-' . ($tgl ? $tgl->format('Ymd') : '') . '-' . str_replace(':', '', $jam ?? '');

            if ($selectedIds !== null && !in_array($idStr, $selectedIds)) {
                return;
            }

            if (SatuSehatMedicationRequest::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {

                $this->logBundleItem($bundle, 'MedicationRequest', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');

                return;
            }

            $map = MedicationMap::where('local_code', $localCode)->first();
            if (!$map || !$map->kfa_code) {
                $warnMsg = "[{$localCode}] Belum ada KFA mapping. Item dilewati.";
                $warnings[] = $warnMsg;
                $this->logBundleItem($bundle, 'MedicationRequest', $idStr, 'warning', null, null, $warnMsg);
                $items[] = ['id' => $idStr, 'label' => "Med.Req: {$localCode}", 'status' => 'warn', 'message' => 'Belum ada KFA mapping.', 'sent_at' => now()->toIso8601String()];
                return;
            }

            $itemLabel = "Med.Req: " . ($map?->kfa_name ?? $localCode);
            $sentAt = now()->toIso8601String();

            $medication = SatuSehatMedication::findByKfaCode($map->kfa_code);
            if (!$medication) {
                try {
                    $form = null;
                    if ($map->form_code) {
                        $form = [
                            'coding' => [
                                [
                                    'system' => 'http://terminology.kemkes.go.id/CodeSystem/medication-form',
                                    'code' => $map->form_code,
                                    'display' => $map->form_name ?: $map->form_code,
                                ]
                            ]
                        ];
                    }
                    $medResp = $serviceMed->createMedication($map->kfa_code, $map->kfa_name, null, 'active', $form);
                    $this->throttle();
                    if (!$medResp->success) {
                        $this->logBundleItem(bundle: $bundle, type: 'Medication', localId: $map->kfa_code, status: 'failed', payload: $serviceMed->getLastPayload(), response: $medResp->data, error: $medResp->error);
                        $errors[] = "[{$localCode}]: Gagal buat Medication: " . $medResp->error;
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => 'Gagal buat Medication: ' . $medResp->error, 'sent_at' => $sentAt];
                        return;
                    }
                    $this->logBundleItem(bundle: $bundle, type: 'Medication', localId: $map->kfa_code, status: 'success', payload: $serviceMed->getLastPayload(), response: $medResp->data, ihsId: $medResp->resourceId);
                    $medication = SatuSehatMedication::create([
                        'ihs_number' => $medResp->resourceId,
                        'kfa_code' => $map->kfa_code,
                        'kfa_display' => $map->kfa_name,
                        'form_code' => $map->form_code,
                        'form_display' => $map->form_name,
                        'raw_response' => $medResp->data,
                        'synced_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    $errors[] = "[{$localCode}]: " . $e->getMessage();
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
                    return;
                }
            }

            try {
                $quantity = (int) ceil($jml);
                $unitCode = $map->denominator_code ?? 'TAB';
                $unitTerm = $map->denominator_name ?? $unitCode;

                $dosage = [
                    'sequence' => 1,
                    'text' => $aturan ?: 'Sesuai petunjuk dokter',
                ];

                $reqResp = $serviceReq->createPrescription(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    requesterId: $encounter->practitioner_ihs,
                    medicationId: $medication->ihs_number,
                    quantity: $quantity > 0 ? $quantity : 1,
                    unitCode: $unitCode,
                    unitTerm: $unitTerm,
                    dosage: $dosage,
                    identifier: $idStr
                );
                $this->throttle();

                if (!$reqResp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'MedicationRequest', localId: $idStr, status: 'failed', payload: $serviceReq->getLastPayload(), response: $reqResp->data, error: $reqResp->error);
                    $errors[] = "[{$localCode} (Req)]: " . $reqResp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $reqResp->error, 'sent_at' => $sentAt];
                    return;
                }
                $this->logBundleItem(bundle: $bundle, type: 'MedicationRequest', localId: $idStr, status: 'success', payload: $serviceReq->getLastPayload(), response: $reqResp->data, ihsId: $reqResp->resourceId);

                SatuSehatMedicationRequest::create([
                    'ihs_number' => $reqResp->resourceId,
                    'identifier' => json_encode($reqResp->data['identifier']),
                    'local_id' => $idStr,
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'medication_ihs' => $medication->ihs_number,
                    'requester_ihs' => $encounter->practitioner_ihs,
                    'status' => 'active',
                    'intent' => 'order',
                    'authored_on' => now(),
                    'raw_response' => $reqResp->data,
                    'synced_at' => now(),
                ]);

                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $serviceReq->searchByEncounter($encounter->ihs_number);
                    $found = $this->findByIdentifier($searchResp, $idStr);
                    if ($found) {
                        SatuSehatMedicationRequest::create([
                            'ihs_number' => $found['id'],
                            'local_id' => $idStr,
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'medication_ihs' => $medication->ihs_number,
                            'requester_ihs' => $encounter->practitioner_ihs,
                            'status' => 'active',
                            'intent' => 'order',
                            'authored_on' => now(),
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        return;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'MedicationRequest', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[{$localCode} (Req)]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        };

        foreach ($obats as $obat) {
            $processObat($obat->kode_brng, $obat->tgl_perawatan, $obat->jam, $obat->jml, $obat->aturanPakai?->aturan);
        }

        foreach ($resepPulangs as $resep) {
            $processObat($resep->kode_brng, $resep->tanggal, $resep->jam, $resep->jml_barang, $resep->dosis);
        }

        $msg = $sent > 0
            ? "{$sent} Medication Request berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Medication Request.' : 'Tidak ada Medication Request yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'MedicationRequest', $msg, $errors, $warnings, '/\[([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    /**
     * Kirim Medication Dispense ke Satu Sehat.
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public function sendMedicationDispenses(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $obats = DetailPemberianObat::where('no_rawat', $reg->no_rawat)->get();
        $resepPulangs = collect();
        if ($reg->status_lanjut === 'Ranap') {
            $resepPulangs = ResepPulang::where('no_rawat', $reg->no_rawat)->get();
        }

        if ($obats->isEmpty() && $resepPulangs->isEmpty()) {
            return ['success' => true, 'skipped' => true, 'count' => 0, 'message' => 'Tidak ada data pemberian obat untuk dikirim Dispense.', 'items' => []];
        }

        $serviceDisp = new MedicationDispenseService();
        $sent = 0;
        $warnings = [];

        // Warning: jika MedicationRequest belum ada sama sekali untuk encounter ini
        if (!SatuSehatMedicationRequest::where('encounter_ihs', $encounter->ihs_number)->exists()) {
            $warnings[] = 'MedicationRequest belum dikirim untuk encounter ini. Setiap item Dispense akan dilewati hingga MedReq-nya tersedia.';
        }

        $errors = [];
        $items = [];

        $processDispense = function ($localCode, $tgl, $jam, $jml) use ($reg, $encounter, $serviceDisp, &$sent, &$errors, &$warnings, &$items, $selectedIds, $bundle) {
            if (!$localCode)
                return;

            $idStr = $reg->no_rawat . '-MED_DISP_' . $localCode . '-' . ($tgl ? $tgl->format('Ymd') : '') . '-' . str_replace(':', '', $jam ?? '');
            $requestId = $reg->no_rawat . '-MED_REQ_' . $localCode . '-' . ($tgl ? $tgl->format('Ymd') : '') . '-' . str_replace(':', '', $jam ?? '');

            if ($selectedIds !== null && !in_array($idStr, $selectedIds)) {
                return;
            }

            if (SatuSehatMedicationDispense::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {

                $this->logBundleItem($bundle, 'MedicationDispense', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');

                return;
            }

            $request = SatuSehatMedicationRequest::where('local_id', $requestId)->first();
            if (!$request || !$request->ihs_number) {
                $warnMsg = "[{$localCode} (Disp)]: MedicationRequest belum dikirim atau IHS number belum tersedia.";
                $warnings[] = $warnMsg;
                $this->logBundleItem($bundle, 'MedicationDispense', $idStr, 'warning', null, null, $warnMsg);
                $items[] = ['id' => $idStr, 'label' => "Med.Disp: {$localCode}", 'status' => 'warn', 'message' => 'MedicationRequest belum dikirim.', 'sent_at' => now()->toIso8601String()];
                return;
            }

            $map = MedicationMap::where('local_code', $localCode)->first();
            if (!$map || !$map->kfa_code) {
                $warnMsg = "[{$localCode} (Disp)]: Belum ada mapping Medication (KFA).";
                $warnings[] = $warnMsg;
                $this->logBundleItem($bundle, 'MedicationDispense', $idStr, 'warning', null, null, $warnMsg);
                $items[] = ['id' => $idStr, 'label' => "Med.Disp: {$localCode}", 'status' => 'warn', 'message' => 'Belum ada KFA mapping.', 'sent_at' => now()->toIso8601String()];
                return;
            }
            $itemLabel = "Med.Disp: {$localCode}";
            $sentAt = now()->toIso8601String();

            try {
                $quantity = (int) ceil($jml);
                $unitCode = $map->denominator_code ?? 'TAB';
                $unitTerm = $map->denominator_name ?? $unitCode;

                $dispResp = $serviceDisp->dispense(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    medicationRequestId: $request->ihs_number,
                    medicationId: $request->medication_ihs,
                    performerId: $encounter->practitioner_ihs,
                    quantity: $quantity > 0 ? $quantity : 1,
                    unitCode: $unitCode,
                    unitTerm: $unitTerm,
                    identifier: $idStr
                );
                $this->throttle();

                if (!$dispResp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'MedicationDispense', localId: $idStr, status: 'failed', payload: $serviceDisp->getLastPayload(), response: $dispResp->data, error: $dispResp->error);
                    $errors[] = "[{$localCode} (Disp)]: " . $dispResp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $dispResp->error, 'sent_at' => $sentAt];
                    return;
                }
                $this->logBundleItem(bundle: $bundle, type: 'MedicationDispense', localId: $idStr, status: 'success', payload: $serviceDisp->getLastPayload(), response: $dispResp->data, ihsId: $dispResp->resourceId);

                SatuSehatMedicationDispense::create([
                    'ihs_number' => $dispResp->resourceId,
                    'identifier' => json_encode($dispResp->data['identifier']),
                    'local_id' => $idStr,
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'medication_ihs' => $request->medication_ihs,
                    'medication_request_ihs' => $request->ihs_number,
                    'performer_ihs' => $encounter->practitioner_ihs,
                    'status' => 'completed',
                    'quantity_value' => $quantity > 0 ? $quantity : 1,
                    'quantity_unit' => $unitCode,
                    'when_prepared' => now(),
                    'when_handed_over' => now(),
                    'raw_response' => $dispResp->data,
                    'synced_at' => now(),
                ]);

                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $serviceDisp->searchByContext($encounter->ihs_number);
                    $found = $this->findByIdentifier($searchResp, $idStr);
                    if ($found) {
                        $quantity = (int) ceil($jml);
                        $unitCode = $map->denominator_code ?? 'TAB';
                        SatuSehatMedicationDispense::create([
                            'ihs_number' => $found['id'],
                            'identifier' => json_encode($found['identifier'] ?? []),
                            'local_id' => $idStr,
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'medication_ihs' => $request->medication_ihs,
                            'medication_request_ihs' => $request->ihs_number,
                            'performer_ihs' => $encounter->practitioner_ihs,
                            'status' => 'completed',
                            'quantity_value' => $quantity > 0 ? $quantity : 1,
                            'quantity_unit' => $unitCode,
                            'when_prepared' => now(),
                            'when_handed_over' => now(),
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        return;
                    }
                }

                $this->logBundleItem(bundle: $bundle, type: 'MedicationDispense', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[ERR] [{$localCode} (Disp)]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        };

        foreach ($obats as $obat) {
            $processDispense($obat->kode_brng, $obat->tgl_perawatan, $obat->jam, $obat->jml);
        }
        foreach ($resepPulangs as $resep) {
            $processDispense($resep->kode_brng, $resep->tanggal, $resep->jam, $resep->jml_barang);
        }

        $msg = $sent > 0
            ? "{$sent} Medication Dispense berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Medication Dispense.' : 'Tidak ada Medication Dispense yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'MedicationDispense', $msg, $errors, $warnings, '/\[([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    /**
     * Kirim MedicationAdministration ke Satu Sehat.
     * Prasyarat: MedicationRequest harus sudah dikirim terlebih dahulu.
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public function sendMedicationAdministrations(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $obats = DetailPemberianObat::where('no_rawat', $reg->no_rawat)
            ->with(['dataBarang'])
            ->orderBy('tgl_perawatan')
            ->orderBy('jam')
            ->get();

        $resepPulangs = collect();
        if ($reg->status_lanjut === 'Ranap') {
            $resepPulangs = ResepPulang::where('no_rawat', $reg->no_rawat)
                ->orderBy('tanggal')
                ->orderBy('jam')
                ->get();
        }

        if ($obats->isEmpty() && $resepPulangs->isEmpty()) {
            return ['success' => false, 'message' => 'Tidak ada data pemberian obat untuk kunjungan ini.'];
        }

        $service = new MedicationAdministrationService();
        $category = $reg->status_lanjut === 'Ranap' ? 'inpatient' : 'outpatient';
        $catDisplay = $reg->status_lanjut === 'Ranap' ? 'Inpatient' : 'Outpatient';
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        $processAdmin = function ($localCode, $tgl, $jam, $jml) use ($reg, $encounter, $service, $category, $catDisplay, &$sent, &$errors, &$items, $selectedIds, $bundle) {
            if (!$localCode)
                return;

            $idStr = $reg->no_rawat . '-MED_ADMIN_' . $localCode . '-'
                . ($tgl ? $tgl->format('Ymd') : '')
                . '-' . str_replace(':', '', $jam ?? '');
            $requestId = $reg->no_rawat . '-MED_REQ_' . $localCode . '-'
                . ($tgl ? $tgl->format('Ymd') : '')
                . '-' . str_replace(':', '', $jam ?? '');

            if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                return;
            if (SatuSehatMedicationAdministration::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                $this->logBundleItem($bundle, 'MedicationAdministration', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                return;
            }

            // MedicationRequest wajib ada
            $request = SatuSehatMedicationRequest::where('local_id', $requestId)->first();
            if (!$request) {
                $warnMsg = "[{$localCode} (MedAdmin)]: MedicationRequest belum dikirim.";
                $warnings[] = $warnMsg;
                $this->logBundleItem($bundle, 'MedicationAdministration', $idStr, 'warning', null, null, $warnMsg);
                $items[] = ['id' => $idStr, 'label' => "Med.Admin: {$localCode}", 'status' => 'warn', 'message' => 'MedicationRequest belum dikirim.', 'sent_at' => now()->toIso8601String()];
                return;
            }

            $map = MedicationMap::where('local_code', $localCode)->first();
            if (!$map || !$map->kfa_code) {
                $warnMsg = "[{$localCode} (MedAdmin)]: Belum ada mapping Medication (KFA).";
                $warnings[] = $warnMsg;
                $this->logBundleItem($bundle, 'MedicationAdministration', $idStr, 'warning', null, null, $warnMsg);
                $items[] = ['id' => $idStr, 'label' => "Med.Admin: {$localCode}", 'status' => 'warn', 'message' => 'Belum ada KFA mapping.', 'sent_at' => now()->toIso8601String()];
                return;
            }
            $quantity = (int) ceil($jml);
            $unitCode = $map->denominator_code ?? 'TAB';
            $unitTerm = $map->denominator_name ?? $unitCode;
            $unitDisplay = $map->denominator_display;

            // Bangun contained Medication
            $containedMed = [
                'resourceType' => 'Medication',
                'id' => $map->kfa_code,
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://sys-ids.kemkes.go.id/kfa',
                            'code' => $map->kfa_code,
                            'display' => $map->kfa_name,
                        ]
                    ],
                ],
                'status' => 'active',
            ];

            $containedMed['form'] = [
                'coding' => [
                    [
                        'system' => 'http://terminology.kemkes.go.id/CodeSystem/medication-form',
                        'code' => $map->form_code,
                        'display' => $map->form_name ?: $map->form_code,
                    ]
                ],
            ];

            $containedMed['extension'] = [
                [
                    'url' => FhirDictionary::KEMKES_FHIR_R4 . '/StructureDefinition/MedicationType',
                    'valueCodeableConcept' => [
                        'coding' => [
                            [
                                'system' => FhirDictionary::KEMKES_CS_MEDICATION_TYPE,
                                'code' => $map->medication_type_code ?? 'NC',
                                'display' => $map->medication_type_name ?? 'Non-compound',
                            ]
                        ],
                    ],
                ]
            ];

            $tglCarbon = $tgl ?? now();
            $effective = $tglCarbon->format('Y-m-d') . 'T' . ltrim($jam ?? '00:00:00') . '+07:00';

            $dosage = [];
            $dosage['route_code'] = $map->route_code;
            $dosage['route_display'] = $map->route_name ?? $map->route_code;
            $dosage['dose_value'] = $quantity;
            $dosage['dose_unit'] = $unitCode;
            $dosage['dose_term'] = $unitTerm;
            $dosage['dose_display'] = $unitDisplay;

            $itemLabel = "Med.Admin: {$localCode}";
            $sentAt = now()->toIso8601String();

            try {
                $resp = $service->createMedicationAdministration(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    performerId: $encounter->practitioner_ihs,
                    medicationRequestId: $request->ihs_number,
                    effectiveStart: $effective,
                    effectiveEnd: $effective,
                    medicationIhs: $request->medication_ihs,
                    status: 'completed',
                    category: $category,
                    categoryDisplay: $catDisplay,
                    identifier: $idStr,
                    medicationContained: $containedMed,
                    dosage: $dosage,
                );
                $this->throttle();

                if (!$resp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'MedicationAdministration', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $resp->data, error: $resp->error);
                    $errors[] = "[{$localCode} (MedAdmin)]: " . $resp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $resp->error, 'sent_at' => $sentAt];
                    return;
                }
                $this->logBundleItem(bundle: $bundle, type: 'MedicationAdministration', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $resp->data, ihsId: $resp->resourceId);

                SatuSehatMedicationAdministration::create([
                    'ihs_number' => $resp->resourceId,
                    'identifier' => json_encode($resp->data['identifier']),
                    'local_id' => $idStr,
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'medication_ihs' => $request->medication_ihs,
                    'medication_request_ihs' => $request->ihs_number,
                    'performer_ihs' => $encounter->practitioner_ihs,
                    'status' => 'completed',
                    'category' => $category,
                    'effective_start' => $tglCarbon,
                    'effective_end' => $tglCarbon,
                    'dosage_route_code' => $map?->route_code,
                    'dosage_route_display' => $map?->route_display ?? $map?->route_name,
                    'dosage_dose_value' => $quantity > 0 ? $quantity : null,
                    'dosage_dose_unit' => $unitCode,
                    'raw_response' => $resp->data,
                    'synced_at' => now(),
                ]);

                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByContext($encounter->ihs_number);
                    $found = $this->findByIdentifier($searchResp, $idStr);
                    if ($found) {
                        SatuSehatMedicationAdministration::create([
                            'ihs_number' => $found['id'],
                            'identifier' => json_encode($found['identifier'] ?? []),
                            'local_id' => $idStr,
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'medication_ihs' => $request->medication_ihs,
                            'medication_request_ihs' => $request->ihs_number,
                            'performer_ihs' => $encounter->practitioner_ihs,
                            'status' => 'completed',
                            'category' => $category,
                            'effective_start' => $tglCarbon,
                            'effective_end' => $tglCarbon,
                            'dosage_route_code' => $map?->route_code,
                            'dosage_route_display' => $map?->route_display ?? $map?->route_name,
                            'dosage_dose_value' => $quantity > 0 ? $quantity : null,
                            'dosage_dose_unit' => $unitCode,
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        return;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'MedicationAdministration', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[{$localCode} (MedAdmin)]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        };

        foreach ($obats as $obat) {
            $processAdmin($obat->kode_brng, $obat->tgl_perawatan, $obat->jam, $obat->jml);
        }
        foreach ($resepPulangs as $resep) {
            $processAdmin($resep->kode_brng, $resep->tanggal, $resep->jam, $resep->jml_barang);
        }

        $msg = $sent > 0
            ? "{$sent} Medication Administration berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Medication Administration.' : 'Tidak ada Medication Administration yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'MedicationAdministration', $msg, $errors, $warnings, '/\[([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }


    public function sendLabServiceRequests(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $labs = PeriksaLab::where('no_rawat', $reg->no_rawat)->with('jenisPerawatan')->get();
        if ($labs->isEmpty()) {
            return ['success' => true, 'count' => 0, 'items' => [], 'errors' => [], 'warnings' => []];
        }

        $service = new ServiceRequestService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        $labOrgId = SatuSehatOrganization::where('identifier', 'LAB')->value('ihs_number');

        foreach ($labs as $lab) {
            $localCode = $lab->kd_jenis_prw;
            if (!$localCode)
                continue;

            $idStr = $reg->no_rawat . '-SR_LAB_' . $localCode . '-' . ($lab->tgl_periksa ? $lab->tgl_periksa->format('Ymd') : '') . '-' . str_replace(':', '', $lab->jam ?? '');

            if ($selectedIds !== null && !in_array($idStr, $selectedIds)) {
                continue;
            }

            if (SatuSehatServiceRequest::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {

                $this->logBundleItem($bundle, 'ServiceRequest', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');

                continue;
            }

            $map = LabMap::where('local_code', $localCode)->first();
            if (!$map || !$map->system_code) {
                $warnings[] = "[LAB:{$localCode}] Belum ada LOINC mapping.";
                continue;
            }
            if (!$labOrgId) {
                $errors[] = "IHS Organization LAB tidak ditemukan di database lokal.";
                continue;
            }

            $itemLabel = "SR-LAB: " . ($lab->jenisPerawatan?->nm_perawatan ?? $localCode);
            $sentAt = now()->toIso8601String();

            try {
                $sentResp = $service->createLabRequest(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    requesterId: $encounter->practitioner_ihs,
                    code: $map->system_code,
                    codeSystem: $map->system_display,
                    display: $map->system_term ?? $map->system_code,
                    codeText: $lab->jenisPerawatan?->nm_perawatan ?? 'Laboratorium',
                    identifier: $idStr,
                    encounterDisplay: 'Kunjungan ' . $reg->no_rawat,
                    requesterDisplay: $encounter->practitioner_name ?? 'Dokter',
                    performerOrgId: $labOrgId
                );
                $this->throttle();

                if (!$sentResp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'ServiceRequest Lab', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                    $errors[] = "[LAB:{$localCode}] " . $sentResp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                    continue;
                }
                $this->logBundleItem(bundle: $bundle, type: 'ServiceRequest Lab', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                SatuSehatServiceRequest::create([
                    'ihs_number' => $sentResp->resourceId,
                    'identifier' => json_encode($sentResp->data['identifier']),
                    'local_id' => $idStr,
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'requester_ihs' => $encounter->practitioner_ihs,
                    'status' => 'active',
                    'intent' => 'order',
                    'priority' => 'routine',
                    'category' => '108252007',
                    'code' => $map->system_code,
                    'code_display' => $map->system_display ?? $map->system_term ?? $map->system_code,
                    'authored_on' => now(),
                    'note' => 'LAB',
                    'raw_response' => $sentResp->data,
                    'synced_at' => now(),
                ]);
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByEncounter($encounter->ihs_number);
                    $found = $this->findByIdentifier($searchResp, $idStr);
                    if ($found) {
                        SatuSehatServiceRequest::create([
                            'ihs_number' => $found['id'],
                            'identifier' => json_encode($found['identifier'] ?? []),
                            'local_id' => $idStr,
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'requester_ihs' => $encounter->practitioner_ihs,
                            'status' => 'active',
                            'intent' => 'order',
                            'priority' => 'routine',
                            'category' => '108252007',
                            'code' => $map->system_code,
                            'code_display' => $map->system_display ?? $map->system_term ?? $map->system_code,
                            'authored_on' => now(),
                            'note' => 'LAB',
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        continue;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'ServiceRequest Lab', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[LAB:{$localCode}]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        }

        $msg = $sent > 0
            ? "{$sent} Service Request Lab berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Service Request Lab.' : 'Tidak ada Service Request Lab yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'ServiceRequest Lab', $msg, $errors, $warnings, '/\[LAB:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'errors' => $errors, 'warnings' => $warnings, 'items' => $items];
    }

    public function sendRadServiceRequests(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $rads = \App\Models\Simrs\PermintaanRadiologi::where('no_rawat', $reg->no_rawat)
            ->with(['allPeriksaRad.jenisPerawatan', 'dokterPerujuk'])
            ->orderBy('tgl_permintaan')
            ->orderBy('jam_permintaan')
            ->get();

        if ($rads->isEmpty()) {
            return ['success' => true, 'count' => 0, 'items' => [], 'errors' => [], 'warnings' => []];
        }

        $service = new ServiceRequestService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        $radOrgId = SatuSehatOrganization::where('identifier', 'RAD')->value('ihs_number');

        foreach ($rads as $rad) {
            try {
                // $rad adalah PermintaanRadiologi; kd_jenis_prw diambil dari periksa_rad terkait
                $noOrder = $rad->noorder;
                $idStr = $reg->no_rawat . '-SR_RAD_' . $noOrder . '-' . ($rad->tgl_permintaan ? $rad->tgl_permintaan->format('Ymd') : '') . '-' . str_replace(':', '', $rad->jam_permintaan ?? '');
                $periksaRad = $rad->periksa_rad->first();
                $localCode = $periksaRad?->kd_jenis_prw;

                if ($selectedIds !== null && !in_array($idStr, $selectedIds)) {
                    continue;
                }

                if (SatuSehatServiceRequest::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {

                    $this->logBundleItem($bundle, 'ServiceRequest', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');

                    continue;
                }

                if (!$localCode) {
                    $warnings[] = "[RAD:{$noOrder}] Pemeriksaan radiologi belum dilakukan, kode tindakan tidak tersedia.";
                    $items[] = ['id' => $idStr, 'label' => "SR-RAD: {$noOrder}", 'status' => 'fail', 'message' => 'Pemeriksaan belum dilakukan.', 'sent_at' => now()->toIso8601String()];
                    continue;
                }

                $map = RadMap::where('local_code', $localCode)->first();
                if (!$map || !$map->system_code) {
                    $warnings[] = "[RAD:{$noOrder}] Belum ada LOINC mapping untuk kode {$localCode}.";
                    continue;
                }
                if (!$radOrgId) {
                    $errors[] = "IHS Organization RAD tidak ditemukan di database lokal.";
                    continue;
                }

                $itemLabel = "SR-RAD: {$noOrder}";
                $sentAt = now()->toIso8601String();

                try {
                    $sentResp = $service->createRadiologyRequest(
                        patientId: $encounter->patient_ihs,
                        encounterId: $encounter->ihs_number,
                        requesterId: $encounter->practitioner_ihs,
                        code: $map->system_code,
                        codeSystem: $map->system_display,
                        display: $map->system_term ?? $map->system_code,
                        codeText: $periksaRad?->jenisPerawatan?->nm_perawatan ?? 'Radiologi',
                        identifier: $noOrder,
                        encounterDisplay: 'Kunjungan ' . $reg->no_rawat,
                        requesterDisplay: $encounter->practitioner_name ?? 'Dokter',
                        performerOrgId: $radOrgId
                    );
                    $this->throttle();

                    if (!$sentResp->success) {
                        $this->logBundleItem(bundle: $bundle, type: 'ServiceRequest Rad', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                        $errors[] = "[RAD:{$noOrder}] " . $sentResp->error;
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                        continue;
                    }
                    $this->logBundleItem(bundle: $bundle, type: 'ServiceRequest Rad', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                    SatuSehatServiceRequest::create([
                        'ihs_number' => $sentResp->resourceId,
                        'identifier' => json_encode($sentResp->data['identifier']),
                        'local_id' => $idStr,
                        'patient_ihs' => $encounter->patient_ihs,
                        'encounter_ihs' => $encounter->ihs_number,
                        'requester_ihs' => $encounter->practitioner_ihs,
                        'status' => 'active',
                        'intent' => 'order',
                        'priority' => 'routine',
                        'category' => '363679005',
                        'code' => $map->system_code,
                        'code_display' => $map->system_display ?? $map->system_term ?? $map->system_code,
                        'authored_on' => $rad->tgl_permintaan ?? now(),
                        'note' => 'RAD',
                        'raw_response' => $sentResp->data,
                        'synced_at' => now(),
                    ]);
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                    $sent++;
                } catch (\Exception $e) {
                    if ($this->isDuplicate($e)) {
                        $searchResp = $service->searchByEncounter($encounter->ihs_number);
                        $found = $this->findByIdentifier($searchResp, $noOrder) ?? $this->findByIdentifier($searchResp, $idStr);
                        if ($found) {
                            SatuSehatServiceRequest::create([
                                'ihs_number' => $found['id'],
                                'identifier' => json_encode($found['identifier'] ?? []),
                                'local_id' => $idStr,
                                'patient_ihs' => $encounter->patient_ihs,
                                'encounter_ihs' => $encounter->ihs_number,
                                'requester_ihs' => $encounter->practitioner_ihs,
                                'status' => 'active',
                                'intent' => 'order',
                                'priority' => 'routine',
                                'category' => '363679005',
                                'code' => $map->system_code,
                                'code_display' => $map->system_display ?? $map->system_term ?? $map->system_code,
                                'authored_on' => $rad->tgl_permintaan ?? now(),
                                'note' => 'RAD',
                                'raw_response' => $found,
                                'synced_at' => now(),
                            ]);
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                            $sent++;
                            continue;
                        }
                    }
                    $this->logBundleItem(bundle: $bundle, type: 'ServiceRequest Rad', localId: $idStr, status: 'failed', error: $e->getMessage());
                    $errors[] = "[RAD:{$noOrder}]: " . $e->getMessage();
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
                }
            } catch (\Throwable $e) {
                $noOrder ??= '?';
                $this->logBundleItem($bundle, 'ServiceRequest Rad', $idStr ?? null, 'failed', null, null, $e->getMessage());
                $errors[] = "[RAD:{$noOrder}] " . $e->getMessage();
                $items[] = ['id' => $idStr ?? '', 'label' => "SR-RAD: {$noOrder}", 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => now()->toIso8601String()];
            }
        }

        $msg = $sent > 0
            ? "{$sent} Service Request Radiologi berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Service Request Radiologi.' : 'Tidak ada Service Request Radiologi yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'ServiceRequest Rad', $msg, $errors, $warnings, '/\[RAD:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'errors' => $errors, 'warnings' => $warnings, 'items' => $items];
    }

    public function sendUsgServiceRequests(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $requests = \App\Models\Simrs\Usg\PermintaanUsg::where('no_rawat', $reg->no_rawat)
            ->orderBy('waktu_permintaan')
            ->get();

        // Fallback: gunakan data hasil_pemeriksaan_usg jika permintaan_usg kosong
        if ($requests->isEmpty()) {
            return $this->sendUsgServiceRequestsFromResults($reg, $encounter, $selectedIds, $bundle);
        }

        $service = new ServiceRequestService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        $radOrgId = SatuSehatOrganization::where('identifier', 'RAD')->value('ihs_number');
        foreach ($requests as $req) {
            $noOrder = $req->noorder;
            $idStr = $reg->no_rawat . '-SR_USG_' . $noOrder . '-' . ($req->waktu_permintaan ? $req->waktu_permintaan->format('Ymd') : '') . '-' . str_replace(':', '', $req->waktu_permintaan ? $req->waktu_permintaan->format('His') : '');

            if ($selectedIds !== null && !in_array($idStr, $selectedIds)) {
                continue;
            }

            if (SatuSehatServiceRequest::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {

                $this->logBundleItem($bundle, 'ServiceRequest', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');

                continue;
            }

            $itemLabel = "SR-USG: {$noOrder}";
            $sentAt = now()->toIso8601String();

            try {
                $sentResp = $service->createRadiologyRequest(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    requesterId: $encounter->practitioner_ihs,
                    code: '16310003',
                    codeSystem: 'http://snomed.info/sct',
                    display: 'Ultrasound',
                    codeText: $req->jenis_permintaan ?? 'USG',
                    identifier: $noOrder,
                    encounterDisplay: 'Kunjungan ' . $reg->no_rawat,
                    requesterDisplay: $encounter->practitioner_name ?? 'Dokter',
                    performerOrgId: $radOrgId
                );
                $this->throttle();

                if (!$sentResp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'ServiceRequest Usg', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                    $errors[] = "[USG:{$noOrder}] " . $sentResp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                    continue;
                }
                $this->logBundleItem(bundle: $bundle, type: 'ServiceRequest Usg', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                SatuSehatServiceRequest::create([
                    'ihs_number' => $sentResp->resourceId,
                    'identifier' => json_encode($sentResp->data['identifier']),
                    'local_id' => $idStr,
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'requester_ihs' => $encounter->practitioner_ihs,
                    'status' => 'active',
                    'intent' => 'order',
                    'priority' => 'routine',
                    'category' => '363679005',
                    'code' => '16310003',
                    'code_display' => 'Ultrasound',
                    'authored_on' => $req->waktu_permintaan ?? now(),
                    'note' => 'USG',
                    'raw_response' => $sentResp->data,
                    'synced_at' => now(),
                ]);
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByEncounter($encounter->ihs_number);
                    $found = $this->findByIdentifier($searchResp, $noOrder) ?? $this->findByIdentifier($searchResp, $idStr);
                    if ($found) {
                        SatuSehatServiceRequest::create([
                            'ihs_number' => $found['id'],
                            'identifier' => json_encode($found['identifier'] ?? []),
                            'local_id' => $idStr,
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'requester_ihs' => $encounter->practitioner_ihs,
                            'status' => 'active',
                            'intent' => 'order',
                            'priority' => 'routine',
                            'category' => '363679005',
                            'code' => '16310003',
                            'code_display' => 'Ultrasound',
                            'authored_on' => $req->waktu_permintaan ?? now(),
                            'note' => 'USG',
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        continue;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'ServiceRequest Usg', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[USG:{$noOrder}]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        }

        $msg = $sent > 0
            ? "{$sent} Service Request Usg berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Service Request Usg.' : 'Tidak ada Service Request Usg yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'ServiceRequest Usg', $msg, $errors, $warnings, '/\[USG:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'errors' => $errors, 'warnings' => $warnings, 'items' => $items];
    }

    /** Fallback: kirim ServiceRequest USG dari tabel hasil_pemeriksaan_usg_* ketika permintaan_usg kosong */
    protected function sendUsgServiceRequestsFromResults(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds, ?SatuSehatBundle $bundle): array
    {
        $usgConfigs = \App\Services\UsgService::getUsgTypeConfigs();
        $service = new ServiceRequestService();
        $radOrgId = SatuSehatOrganization::where('identifier', 'RAD')->value('ihs_number');

        $sent = 0;
        $errors = [];
        $items = [];

        foreach ($usgConfigs as $key => $cfg) {
            try {
                $data = $cfg['model']::where('no_rawat', $reg->no_rawat)->get();
                foreach ($data as $item) {
                    try {
                        $noOrder = $item->noorder;
                        $tglUSG = \Carbon\Carbon::parse($item->tanggal);
                        $jamUSG = $item->jam ?? $tglUSG->format('H:i:s');
                        $idStr = $reg->no_rawat . '-SR_USG_' . $noOrder . '-' . $tglUSG->format('Ymd') . '-' . str_replace(':', '', $jamUSG);

                        if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                            continue;
                        if (SatuSehatServiceRequest::where('local_id', $idStr)->exists())
                            continue;

                        $itemLabel = "SR-USG: {$cfg['label']}";
                        $sentAt = now()->toIso8601String();

                        try {
                            $sentResp = $service->createRadiologyRequest(
                                patientId: $encounter->patient_ihs,
                                encounterId: $encounter->ihs_number,
                                requesterId: $encounter->practitioner_ihs,
                                code: '16310003',
                                codeSystem: 'http://snomed.info/sct',
                                display: 'Ultrasound',
                                codeText: $cfg['label'],
                                identifier: $noOrder,
                                encounterDisplay: 'Kunjungan ' . $reg->no_rawat,
                                requesterDisplay: $encounter->practitioner_name ?? 'Dokter',
                                performerOrgId: $radOrgId
                            );
                            $this->throttle();

                            if (!$sentResp->success) {
                                $this->logBundleItem(bundle: $bundle, type: 'ServiceRequest Usg', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                                $errors[] = "[USG:{$noOrder}] " . $sentResp->error;
                                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                                continue;
                            }
                            $this->logBundleItem(bundle: $bundle, type: 'ServiceRequest Usg', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                            SatuSehatServiceRequest::create([
                                'ihs_number' => $sentResp->resourceId,
                                'identifier' => json_encode($sentResp->data['identifier'] ?? []),
                                'local_id' => $idStr,
                                'patient_ihs' => $encounter->patient_ihs,
                                'encounter_ihs' => $encounter->ihs_number,
                                'requester_ihs' => $encounter->practitioner_ihs,
                                'status' => 'active',
                                'intent' => 'order',
                                'priority' => 'routine',
                                'category' => '363679005',
                                'code' => '16310003',
                                'code_display' => 'Ultrasound',
                                'authored_on' => $tglUSG->format('Y-m-d') . ' ' . $jamUSG,
                                'note' => 'USG',
                                'raw_response' => $sentResp->data,
                                'synced_at' => now(),
                            ]);
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                            $sent++;
                        } catch (\Exception $e) {
                            $this->logBundleItem(bundle: $bundle, type: 'ServiceRequest Usg', localId: $idStr, status: 'failed', error: $e->getMessage());
                            $errors[] = "[USG:{$noOrder}] " . $e->getMessage();
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
                        }
                    } catch (\Throwable $e) {
                        $noOrder ??= '?';
                        $this->logBundleItem($bundle, 'ServiceRequest Usg', $idStr ?? null, 'failed', null, null, $e->getMessage());
                        $errors[] = "[USG:{$noOrder}] " . $e->getMessage();
                        $items[] = ['id' => $idStr ?? '', 'label' => "SR-USG: {$noOrder}", 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => now()->toIso8601String()];
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "[USG:{$key}] DB error: " . $e->getMessage();
            }
        }

        $msg = $sent > 0
            ? "{$sent} Service Request Usg berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Service Request Usg.' : 'Tidak ada Service Request Usg yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'ServiceRequest Usg', $msg, $errors, [], '/\[USG:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'errors' => $errors, 'warnings' => [], 'items' => $items];
    }


    /**
     * Cari encounter yang sudah ada di Satu Sehat (saat terjadi error duplikat 20002)
     * dan simpan ke database lokal.
     */
    private function findAndSaveExistingEncounter(
        RegPeriksa $reg,
        SatuSehatPatient $patient,
        SatuSehatPractitioner $practitioner,
        SatuSehatLocation $location,
        string $class,
        ?string $periodStart,
        ?string $periodEnd,
        ?SatuSehatBundle $bundle = null
    ): array {
        $searchResponse = (new EncounterService())->searchBySubject($patient->ihs_number);

        if (!$searchResponse->success) {
            return ['success' => false, 'message' => 'Encounter duplikat di Satu Sehat, namun pencarian juga gagal: ' . $searchResponse->error];
        }

        foreach ($searchResponse->getResources() as $resource) {
            $matched = collect($resource['identifier'] ?? [])
                ->first(fn($id) => ($id['value'] ?? '') === $reg->no_rawat);

            if (!$matched) {
                continue;
            }

            $encounter = SatuSehatEncounter::create([
                'ihs_number' => $resource['id'],
                'local_id' => $reg->no_rawat,
                'status' => $resource['status'] ?? 'arrived',
                'class' => $class,
                'patient_ihs' => $patient->ihs_number,
                'patient_name' => $reg->pasien?->nm_pasien,
                'practitioner_ihs' => $practitioner->ihs_number,
                'practitioner_name' => $reg->dokter?->nm_dokter,
                'location_ihs' => $location->ihs_number,
                'location_name' => $location->name,
                'service_provider' => config('satusehat.organization_id'),
                'period_start' => $periodStart ? Carbon::parse($periodStart) : null,
                'period_end' => $periodEnd ? Carbon::parse($periodEnd) : null,
                'raw_response' => $resource,
                'synced_at' => now(),
            ]);

            $this->logBundleItem(
                bundle: $bundle,
                type: 'Encounter',
                localId: $reg->no_rawat,
                status: 'success',
                payload: null,
                response: $resource,
                ihsId: $resource['id']
            );

            return ['success' => true, 'message' => 'Encounter sudah ada di Satu Sehat dan berhasil disinkronkan ke lokal.', 'encounter' => $encounter];
        }

        return ['success' => false, 'message' => 'Encounter duplikat di Satu Sehat tetapi tidak ditemukan untuk kunjungan ' . $reg->no_rawat . '.'];
    }

    /**
     * PATCH status Encounter ke SatuSehat dan update DB lokal.
     *
     * @return array{success: bool, message: string}
     */
    public function updateEncounterStatus(string $localId, string $newStatus, ?SatuSehatBundle $bundle = null): array
    {
        // Pastikan bundle log ada jika belum dilempar dari pemanggil
        $bundle = $this->ensureBundleLog($localId, $bundle);

        $encounter = SatuSehatEncounter::where('local_id', $localId)->first();

        if (!$encounter) {
            return ['success' => false, 'message' => 'Encounter tidak ditemukan di lokal.'];
        }

        if (!$encounter->ihs_number) {
            return ['success' => false, 'message' => 'IHS Number Encounter tidak tersedia.'];
        }

        $ops = [['op' => 'replace', 'path' => '/status', 'value' => $newStatus]];

        if ($newStatus === 'finished') {
            $ops[] = ['op' => 'add', 'path' => '/period/end', 'value' => now()->toIso8601String()];
        }

        // Bangun diagnosis dari Condition yang sudah dikirim untuk encounter ini
        $conditions = SatuSehatCondition::where('encounter_ihs', $encounter->ihs_number)
            ->whereNotNull('ihs_number')
            ->orderBy('icd_code')
            ->get();

        if ($conditions->isNotEmpty()) {
            $diagnosis = $conditions->values()->map(fn($c, $idx) => [
                'condition' => [
                    'reference' => 'Condition/' . $c->ihs_number,
                    'display' => $c->icd_display ?? $c->icd_code,
                ],
                'use' => [
                    'coding' => [
                        [
                            'system' => FhirDictionary::HL7_CS_DIAGNOSIS_ROLE,
                            'code' => 'DD',
                            'display' => 'Discharge diagnosis',
                        ]
                    ],
                ],
                'rank' => $idx + 1,
            ])->all();

            $ops[] = ['op' => 'add', 'path' => '/diagnosis', 'value' => $diagnosis];
        }

        $response = (new EncounterService())->patch($encounter->ihs_number, $ops);
        $this->throttle();

        $this->logBundleItem(
            bundle: $bundle,
            type: 'Encounter',
            localId: $localId,
            status: $response->success ? 'success' : 'failed',
            payload: $ops,
            response: $response->data,
            error: $response->error,
            ihsId: $encounter->ihs_number
        );

        if (!$response->success) {
            return ['success' => false, 'message' => $response->error ?? 'Gagal memperbarui status Encounter.'];
        }

        $encounter->update([
            'status' => $newStatus,
            'synced_at' => now(),

            ...($newStatus === 'finished' ? ['period_end' => now()] : []),
        ]);

        return ['success' => true, 'message' => "Status Encounter diperbarui menjadi '{$newStatus}'."];
    }

    /** Ekstrak nilai tanda vital dari satu baris pemeriksaan. */
    public function extractVitalSigns(object $periksa): array
    {
        // Normalisasi: trim whitespace + ganti koma desimal dengan titik
        $norm = fn($v) => is_string($v) ? str_replace(',', '.', trim($v)) : $v;

        $result = [
            'temperature' => $norm($periksa->suhu_tubuh ?? null),
            'heart_rate' => $norm($periksa->nadi ?? null),
            'respiratory_rate' => $norm($periksa->respirasi ?? null),
            'height' => $norm($periksa->tinggi ?? null),
            'weight' => $norm($periksa->berat ?? null),
            'oxygen_saturation' => $norm($periksa->spo2 ?? null),
        ];

        // Parse tensi "sistol/diastol"
        if (!empty($periksa->tensi)) {
            $parts = explode('/', $periksa->tensi);
            if (count($parts) === 2) {
                $result['systolic'] = $norm($parts[0]);
                $result['diastolic'] = $norm($parts[1]);
            }
        }

        return array_filter($result, fn($v) => is_numeric($v) && (float) $v > 0);
    }

    /** Mapping tipe tanda vital ke kode LOINC dan satuan. */
    private function vitalSignCode(string $type): array
    {
        return match ($type) {
            'systolic' => ['code' => '8480-6', 'display' => 'Systolic blood pressure', 'unit' => 'mm[Hg]'],
            'diastolic' => ['code' => '8462-4', 'display' => 'Diastolic blood pressure', 'unit' => 'mm[Hg]'],
            'heart_rate' => ['code' => '8867-4', 'display' => 'Heart rate', 'unit' => 'beats/minute'],
            'respiratory_rate' => ['code' => '9279-1', 'display' => 'Respiratory rate', 'unit' => 'breaths/minute'],
            'temperature' => ['code' => '8310-5', 'display' => 'Body temperature', 'unit' => 'C'],
            'oxygen_saturation' => ['code' => '2708-6', 'display' => 'Oxygen saturation in Arterial blood', 'unit' => '%'],
            'height' => ['code' => '8302-2', 'display' => 'Body height', 'unit' => 'cm'],
            'weight' => ['code' => '29463-7', 'display' => 'Body weight', 'unit' => 'kg'],
            default => ['code' => $type, 'display' => $type, 'unit' => ''],
        };
    }

    /**
     * Mengirim resource ImagingStudy radiologi ke Satu Sehat
     */
    public function sendImagingStudies(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $sent = 0;
        $errors = [];
        $warnings = [];
        $items = [];

        \Illuminate\Support\Facades\Log::info('RADIOLOGI TOLONG');

        // 1. PROSES RADIOLOGI (DICOM/PACS)
        $rads = \App\Models\Simrs\PermintaanRadiologi::where('no_rawat', $reg->no_rawat)
            ->with(['allPeriksaRad.jenisPerawatan'])
            ->orderBy('tgl_permintaan')
            ->orderBy('jam_permintaan')
            ->get();

        foreach ($rads as $rad) {
            try {
                $noOrder = $rad->noorder;
                $periksaRad = $rad->periksa_rad->first();
                $localCode = $periksaRad?->kd_jenis_prw;
                $tglIS = $periksaRad?->tgl_periksa;
                $jamIS = $periksaRad?->jam;
                $idStr = $reg->no_rawat . '-IMG_RAD_' . $noOrder . '-' . ($tglIS ? $tglIS->format('Ymd') : '') . '-' . str_replace(':', '', $jamIS ?? '000000');

                if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                    continue;

                $itemLabel = "Imaging RAD: {$noOrder}";
                $sentAt = now()->toIso8601String();

                // Validator: ServiceRequest (RAD) harus sudah ada untuk Radiologi
                if (!SatuSehatServiceRequest::where('encounter_ihs', $encounter->ihs_number)->where('note', 'RAD')->exists()) {
                    $msg = 'Service Request (Radiologi) belum dikirim.';
                    $errors[] = "[IMG:{$noOrder}] {$msg}";
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => $sentAt];
                    continue;
                }

                // Cek apakah sudah sinkron secara lokal berdasarkan local_id
                if (\App\Models\SatuSehat\SatuSehatImagingStudy::where('local_id', $idStr)->exists()) {
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah tersinkronisasi.', 'sent_at' => $sentAt];
                    $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'success', response: ['message' => 'Sudah tersinkronisasi (local_id ditemukan).']);
                    $sent++;
                    continue;
                }

                if (!$periksaRad) {
                    $msg = 'Pemeriksaan radiologi belum dilakukan.';
                    $warnings[] = "[IMG:{$noOrder}] {$msg}";
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => $sentAt];
                    $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: $msg);
                    continue;
                }

                [$modalityCode, $modalityDisplay] = $this->detectModality($periksaRad->jenisPerawatan?->nm_perawatan ?? '', $localCode);

                // ALUR BARU: DICOM ROUTER (Orthanc -> Satu Sehat)
                $accessionNumber = $noOrder;
                $dicomStudy = \App\Models\Dicom\Worklist::where('accession_number', $accessionNumber)->first();
                if (!$dicomStudy) {
                    // Fallback to searching by noorder if accession_number not found
                    $dicomStudy = \App\Models\Dicom\Worklist::where('noorder', $noOrder)->first();
                }

                if (!$dicomStudy) {
                    $msg = 'Belum terdaftar di worklist PACS. Lakukan registrasi PACS terlebih dahulu.';
                    $warnings[] = "[IMG:{$noOrder}] {$msg}";
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => $sentAt];
                    $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: $msg);
                    continue;
                }

                // Jika sudah ada IHS ID dari webhook, cek sekali lagi di tabel lokal berdasarkan IHS ID
                // if ($dicomStudy->imaging_study_ihs && \App\Models\SatuSehat\SatuSehatImagingStudy::where('ihs_number', $dicomStudy->imaging_study_ihs)->exists()) {
                //     $sent++; // Anggap berhasil karena sudah ada
                //     $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah tersinkronisasi (IHS ID ditemukan).', 'sent_at' => $sentAt];
                //     $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'success', ihsId: $dicomStudy->imaging_study_ihs, response: ['message' => 'Sudah ada di database lokal.']);
                //     continue;
                // }

                // Verifikasi study di Orthanc menggunakan Accession Number dari worklist (atau noorder sebagai fallback)
                $orthancService = new \App\Services\Dicom\OrthancService();
                $studyIds = $orthancService->findStudyIdsByAccession($dicomStudy->accession_number ?? $noOrder);
                if (empty($studyIds) && $dicomStudy->accession_number !== $noOrder) {
                    $studyIds = $orthancService->findStudyIdsByAccession($noOrder);
                }

                if (empty($studyIds)) {
                    $msg = 'Study tidak ditemukan di Orthanc. Pastikan gambar sudah difoto.';
                    $warnings[] = "[IMG:{$noOrder}] {$msg}";
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => $sentAt];
                    $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: $msg);
                    continue;
                }

                // Cek apakah sudah ada Series (exam selesai)
                $orthancStudy = $orthancService->getStudy($studyIds[0]);
                if (!($orthancStudy['success'] ?? false) || empty($orthancStudy['data']['Series'] ?? [])) {
                    $msg = 'Study ada di Orthanc tetapi belum memiliki gambar (exam belum selesai).';
                    $warnings[] = "[IMG:{$noOrder}] {$msg}";
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => $sentAt];
                    $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: $msg);
                    continue;
                }

                // Transfer DICOM ke Satu Sehat
                $aeTitleSS = \App\Helpers\ConfigurationHelper::get('dicom.satusehat.ae_title', '');
                if (empty($aeTitleSS)) {
                    $msg = 'AE Title Satu Sehat belum dikonfigurasi di pengaturan DICOM.';
                    $warnings[] = "[IMG:{$noOrder}] {$msg}";
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => $sentAt];
                    $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: $msg);
                    continue;
                }

                $transferResp = $orthancService->storeToModality($aeTitleSS, [$studyIds[0]]);
                if (!($transferResp['success'] ?? false)) {
                    $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'failed', response: $transferResp, error: 'Gagal transfer DICOM via Orthanc.');
                    $errors[] = "[IMG:{$noOrder}] Gagal mengirim DICOM ke Satu Sehat via Orthanc.";
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => 'Gagal transfer DICOM.', 'sent_at' => $sentAt];
                    continue;
                }

                // Cek konfirmasi dari webhook (imaging_study_ihs)
                if (empty($dicomStudy->imaging_study_ihs)) {
                    $msg = 'Menunggu IHS ID dari DICOM Router...';
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'warning', 'message' => $msg, 'sent_at' => $sentAt];
                    $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: $msg);
                    continue;
                }

                $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'success', response: $transferResp, ihsId: $dicomStudy->imaging_study_ihs);

                // Simpan IHS ID (Gunakan ihs_number sebagai kunci pencarian utama untuk menghindari duplicate entry)
                \App\Models\SatuSehat\SatuSehatImagingStudy::updateOrCreate(
                    ['ihs_number' => $dicomStudy->imaging_study_ihs],
                    [
                        'local_id' => $idStr,
                        'identifier' => json_encode([['system' => 'http://sys-ids.kemkes.go.id/acsn/' . $this->getOrganizationId(), 'value' => $acsn]]),
                        'patient_ihs' => $encounter->patient_ihs,
                        'encounter_ihs' => $encounter->ihs_number,
                        'status' => 'available',
                        'modality_code' => $dicomStudy->modality ?? $modalityCode,
                        'modality_display' => $modalityDisplay,
                        'description' => $dicomStudy->procedure_desc ?? $periksaRad->jenisPerawatan?->nm_perawatan,
                        'started_at' => $dicomStudy->scheduled_date ?? $periksaRad->tgl_periksa,
                        'synced_at' => now(),
                    ]
                );

                $sent++;
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Tersinkronisasi dari PACS.', 'sent_at' => $sentAt];
            } catch (\Throwable $e) {
                $noOrder ??= '?';
                $this->logBundleItem($bundle, 'ImagingStudy', $idStr ?? null, 'failed', null, null, "[IMG:{$noOrder}] " . $e->getMessage());
                $errors[] = "[IMG:{$noOrder}] " . $e->getMessage();
                $items[] = ['id' => $idStr ?? '', 'label' => "Imaging RAD: {$noOrder}", 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => now()->toIso8601String()];
            }
        }

        \Illuminate\Support\Facades\Log::info('USG TOLONG');

        // 2. PROSES USG (DICOM Router — sama dengan RAD)
        $usgConfigs = \App\Services\UsgService::getUsgTypeConfigs();
        $orthancService = new \App\Services\Dicom\OrthancService();
        $aeTitleSS = \App\Helpers\ConfigurationHelper::get('dicom.satusehat.ae_title', '');

        foreach ($usgConfigs as $key => $cfg) {
            try {
                $data = $cfg['model']::where('no_rawat', $reg->no_rawat)->get();

                foreach ($data as $item) {
                    try {
                        $noOrder = $item->noorder ?? strtoupper($key);
                        $tglUSG = \Carbon\Carbon::parse($item->tanggal);
                        $jamUSG = $tglUSG->format('H:i:s');
                        $idStr = $reg->no_rawat . '-IMG_USG_' . $noOrder . '-' . $tglUSG->format('Ymd') . '-' . str_replace(':', '', $jamUSG);

                        if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                            continue;

                        $itemLabel = "Imaging USG: {$cfg['label']} ({$noOrder})";
                        $sentAt = now()->toIso8601String();

                        // Validator: ServiceRequest (USG) harus sudah ada
                        $hasSR = SatuSehatServiceRequest::where('encounter_ihs', $encounter->ihs_number)
                            ->where('note', 'USG')
                            ->where('local_id', 'like', "%{$noOrder}%")
                            ->exists();

                        if (!$hasSR) {
                            $msg = 'Service Request (USG) belum dikirim.';
                            $warnings[] = "[IMG-USG:{$key}] {$msg}";
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => $sentAt];
                            $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: $msg);
                            continue;
                        }

                        if (\App\Models\SatuSehat\SatuSehatImagingStudy::where('local_id', $idStr)->exists()) {
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah tersinkronisasi.', 'sent_at' => $sentAt];
                            $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: 'Sudah tersinkronisasi.');
                            $sent++;
                            continue;
                        }

                        // Cari worklist DICOM berdasarkan noorder
                        $dicomStudy = \App\Models\Dicom\Worklist::where('noorder', $noOrder)->first();

                        if (!$dicomStudy) {
                            $msg = 'Belum terdaftar di worklist PACS. Lakukan registrasi PACS terlebih dahulu.';
                            $warnings[] = "[IMG-USG:{$key}] {$msg}";
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => $sentAt];
                            $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: $msg);
                            continue;
                        }

                        // Cari study di Orthanc
                        $studyIds = $orthancService->findStudyIdsByAccession($dicomStudy->accession_number ?? $noOrder);
                        if (empty($studyIds) && $dicomStudy->accession_number !== $noOrder) {
                            $studyIds = $orthancService->findStudyIdsByAccession($noOrder);
                        }

                        if (empty($studyIds)) {
                            $msg = 'Study tidak ditemukan di Orthanc. Pastikan gambar sudah difoto.';
                            $warnings[] = "[IMG-USG:{$key}] {$msg}";
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => $sentAt];
                            $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: $msg);
                            continue;
                        }

                        // Cek apakah series sudah ada
                        $orthancStudy = $orthancService->getStudy($studyIds[0]);
                        if (!($orthancStudy['success'] ?? false) || empty($orthancStudy['data']['Series'] ?? [])) {
                            $msg = 'Study ada di Orthanc tetapi belum memiliki gambar (exam belum selesai).';
                            $warnings[] = "[IMG-USG:{$key}] {$msg}";
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => $sentAt];
                            $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: $msg);
                            continue;
                        }

                        if (empty($aeTitleSS)) {
                            $msg = 'AE Title Satu Sehat belum dikonfigurasi di pengaturan DICOM.';
                            $warnings[] = "[IMG-USG:{$key}] {$msg}";
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => $sentAt];
                            $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: $msg);
                            continue;
                        }

                        // Transfer DICOM ke Satu Sehat via Orthanc
                        $transferResp = $orthancService->storeToModality($aeTitleSS, [$studyIds[0]]);
                        if (!($transferResp['success'] ?? false)) {
                            $msg = 'Gagal transfer DICOM USG via Orthanc.';
                            $warnings[] = "[IMG-USG:{$key}] {$msg}";
                            $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: $msg);
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => $sentAt];
                            continue;
                        }

                        if (empty($dicomStudy->imaging_study_ihs)) {
                            $msg = 'Menunggu IHS ID dari DICOM Router...';
                            $warnings[] = "[IMG-USG:{$key}] {$msg}";
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'warning', 'message' => $msg, 'sent_at' => $sentAt];
                            $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'warning', error: $msg);
                            continue;
                        }

                        $this->logBundleItem(bundle: $bundle, type: 'ImagingStudy', localId: $idStr, status: 'success', response: $transferResp, ihsId: $dicomStudy->imaging_study_ihs);

                        \App\Models\SatuSehat\SatuSehatImagingStudy::updateOrCreate(
                            ['ihs_number' => $dicomStudy->imaging_study_ihs],
                            [
                                'local_id' => $idStr,
                                'identifier' => json_encode([['system' => 'http://sys-ids.kemkes.go.id/acsn/' . $this->getOrganizationId(), 'value' => $noOrder]]),
                                'patient_ihs' => $encounter->patient_ihs,
                                'encounter_ihs' => $encounter->ihs_number,
                                'status' => 'available',
                                'modality_code' => $dicomStudy->modality ?? 'US',
                                'modality_display' => 'Ultrasound',
                                'description' => $dicomStudy->procedure_desc ?? $cfg['label'],
                                'started_at' => $dicomStudy->scheduled_date ?? ($tglUSG->format('Y-m-d') . ' ' . $jamUSG),
                                'synced_at' => now(),
                            ]
                        );

                        $sent++;
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Tersinkronisasi dari PACS.', 'sent_at' => $sentAt];
                    } catch (\Throwable $e) {
                        $noOrder ??= '?';
                        $this->logBundleItem($bundle, 'ImagingStudy', $idStr ?? null, 'failed', null, null, "[IMG-USG:{$key}] " . $e->getMessage());
                        $errors[] = "[IMG-USG:{$key}] " . $e->getMessage();
                        $items[] = ['id' => $idStr ?? '', 'label' => "Imaging USG: {$key}", 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => now()->toIso8601String()];
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "[USG:{$key}] DB error: " . $e->getMessage();
            }
        }

        $msg = $sent > 0
            ? "{$sent} Imaging Study berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Imaging Study.' : 'Tidak ada Imaging Study yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'ImagingStudy', $msg, $errors, $warnings, '/\[(?:IMG|USG):([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'errors' => $errors, 'items' => $items];
    }

    public function sendDiagnosticReports(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $labRes = $this->sendLabDiagnosticReports($reg, $encounter, $selectedIds, $bundle);
        $radRes = $this->sendRadDiagnosticReports($reg, $encounter, $selectedIds, $bundle);
        $usgRes = $this->sendUsgDiagnosticReports($reg, $encounter, $selectedIds, $bundle);

        $sent = ($labRes['count'] ?? 0) + ($radRes['count'] ?? 0) + ($usgRes['count'] ?? 0);
        $errors = array_merge($labRes['errors'] ?? [], $radRes['errors'] ?? [], $usgRes['errors'] ?? []);
        $warnings = array_merge($labRes['warnings'] ?? [], $radRes['warnings'] ?? [], $usgRes['warnings'] ?? []);
        $items = array_merge($labRes['items'] ?? [], $radRes['items'] ?? [], $usgRes['items'] ?? []);

        $msg = $sent > 0
            ? "{$sent} Diagnostic Report berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Diagnostic Report.' : 'Tidak ada Diagnostic Report yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'DiagnosticReport', $msg, $errors, $warnings, '/\[(?:LAB|RAD|DR|OBS|USG):([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    public function sendLabDiagnosticReports(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $labs = \App\Models\Simrs\PeriksaLab::where('no_rawat', $reg->no_rawat)->with('jenisPerawatan')->get();
        if ($labs->isEmpty()) {
            return ['success' => true, 'count' => 0, 'items' => [], 'errors' => [], 'warnings' => []];
        }

        $service = new \App\Services\SatuSehat\Resources\DiagnosticReportService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        foreach ($labs as $lab) {
            $localCode = $lab->kd_jenis_prw;
            if (!$localCode)
                continue;

            $idStr = $reg->no_rawat . '-DR_LAB_' . $localCode . '-' . ($lab->tgl_periksa ? $lab->tgl_periksa->format('Ymd') : '') . '-' . str_replace(':', '', $lab->jam ?? '');
            if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                continue;
            if (\App\Models\SatuSehat\SatuSehatDiagnosticReport::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                $this->logBundleItem($bundle, 'DiagnosticReport', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                continue;
            }

            // Validator: ServiceRequest (LAB) & Specimen (LAB) harus sudah ada
            $srIdStr = str_replace('DR_LAB_', 'SR_LAB_', $idStr);
            $specIdStr = str_replace('DR_LAB_', 'SPEC_LAB_', $idStr);
            $itemLabel = "DR-LAB: " . ($lab->jenisPerawatan?->nm_perawatan ?? $localCode);

            $sr = SatuSehatServiceRequest::where('local_id', $srIdStr)->first();
            if (!$sr) {
                // catat bundle warning
                $warnings[] = "[DR-LAB:{$localCode}] ServiceRequest LAB belum dikirim.";
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => 'ServiceRequest LAB belum dikirim.', 'sent_at' => now()->toIso8601String()];
                continue;
            }

            $spec = SatuSehatSpecimen::where('local_id', $specIdStr)->first();
            if (!$spec) {
                // catat bundle warning

                $warnings[] = "[DR-LAB:{$localCode}] Specimen LAB belum dikirim.";
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => 'Specimen LAB belum dikirim.', 'sent_at' => now()->toIso8601String()];
                continue;
            }

            $map = \App\Models\Mapping\LabMap::where('local_code', $localCode)->first();
            if (!$map || !$map->system_code) {
                $warnings[] = "[LAB:{$localCode}] Belum ada LOINC mapping.";
                $items[] = ['id' => $idStr, 'label' => "DR-LAB: " . ($lab->jenisPerawatan?->nm_perawatan ?? $localCode), 'status' => 'fail', 'message' => 'Belum ada LOINC mapping.', 'sent_at' => now()->toIso8601String()];
                continue;
            }
            if (!str_contains($map->system_code, '-')) {
                $msg = "Kode '{$map->system_code}' bukan LOINC (format harus mengandung '-'). Perbaiki di Local Terminology → Laboratorium.";
                $warnings[] = "[LAB:{$localCode}] {$msg}";
                $items[] = ['id' => $idStr, 'label' => "DR-LAB: " . ($lab->jenisPerawatan?->nm_perawatan ?? $localCode), 'status' => 'fail', 'message' => $msg, 'sent_at' => now()->toIso8601String()];
                continue;
            }

            // Validasi: hasil lab (saran/kesan) harus sudah diisi
            $saranKesan = SaranKesanLab::where('no_rawat', $reg->no_rawat)
                ->where('tgl_periksa', $lab->tgl_periksa)
                ->where('jam', $lab->jam)
                ->first();
            if (!$saranKesan || (!trim($saranKesan->saran ?? '') && !trim($saranKesan->kesan ?? ''))) {
                $msg = 'Hasil laboratorium belum diisi. Isi saran/kesan dahulu sebelum mengirim.';
                $warnings[] = "[LAB:{$localCode}] {$msg}";
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => now()->toIso8601String()];
                continue;
            }

            // Kumpulkan Observation hasil lab yang sudah terkirim untuk lab ini
            $labObsIds = SatuSehatObservation::where('local_id', 'like', "{$reg->no_rawat}-OBS_LAB_{$localCode}_%")
                ->whereNotNull('ihs_number')
                ->pluck('ihs_number')
                ->values()
                ->all();

            if (empty($labObsIds)) {
                // Mencoba kirim Observation LAB jika belum ada
                $obsRes = $this->sendLabObservations($reg, $encounter, null, $bundle);
                if (!$obsRes['success'] || empty($obsRes['count'])) {
                    $errors[] = "[OBS-LAB:{$localCode}] Gagal atau belum bisa mengirim Observation LAB. Pastikan hasil pemeriksaan sudah diisi.";
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => '[OBS-LAB] Gagal kirim observasi.', 'sent_at' => now()->toIso8601String()];
                    continue;
                }

                // Ambil ulang setelah berhasil dikirim
                $labObsIds = SatuSehatObservation::where('local_id', 'like', "{$reg->no_rawat}-OBS_LAB_{$localCode}_%")
                    ->whereNotNull('ihs_number')
                    ->pluck('ihs_number')
                    ->values()
                    ->all();
            }

            $catMap = \App\Models\Mapping\DiagnosticCategoryMap::where('local_code', $localCode)->first();
            if (!$catMap || !$catMap->diagnostic_category) {
                $msg = 'Belum ada Diagnostic Category mapping.';
                $warnings[] = "[LAB:{$localCode}] {$msg}";
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => now()->toIso8601String()];
                continue;
            }
            $labCategory = $catMap->diagnostic_category;
            $labCategoryDisplay = $catMap->diagnostic_category_term ?: null;

            $sentAt = now()->toIso8601String();

            try {
                $sentResp = $service->createLabReport(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    performerId: $encounter->practitioner_ihs,
                    loincCode: $map->system_code,
                    loincDisplay: $map->system_term ?? $map->system_code,
                    observationIds: $labObsIds,
                    identifier: $idStr,
                    serviceRequestId: $sr?->ihs_number,
                    category: $labCategory,
                    categoryDisplay: $labCategoryDisplay,
                );
                $this->throttle();

                if (!$sentResp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'DiagnosticReport Lab', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                    $errors[] = "[LAB:{$localCode}] " . $sentResp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                    continue;
                }
                $this->logBundleItem(bundle: $bundle, type: 'DiagnosticReport Lab', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                \App\Models\SatuSehat\SatuSehatDiagnosticReport::create([
                    'ihs_number' => $sentResp->resourceId,
                    'identifier' => json_encode($sentResp->data['identifier']),
                    'local_id' => $idStr,
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'status' => 'final',
                    'category' => $labCategory,
                    'code' => $map->system_code,
                    'code_display' => $map->system_display ?? $map->system_code,
                    'effective_datetime' => $lab->tgl_periksa ? $lab->tgl_periksa->format('Y-m-d') . ' ' . $lab->jam : now(),
                    'raw_response' => $sentResp->data,
                    'synced_at' => now(),
                ]);
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByEncounter($encounter->ihs_number);
                    $found = $this->findByIdentifier($searchResp, $idStr);
                    if ($found) {
                        \App\Models\SatuSehat\SatuSehatDiagnosticReport::create([
                            'ihs_number' => $found['id'],
                            'identifier' => json_encode($found['identifier'] ?? []),
                            'local_id' => $idStr,
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'status' => 'final',
                            'category' => $labCategory,
                            'code' => $map->system_code,
                            'code_display' => $map->system_display ?? $map->system_code,
                            'effective_datetime' => $lab->tgl_periksa ? $lab->tgl_periksa->format('Y-m-d') . ' ' . $lab->jam : now(),
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        continue;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'DiagnosticReport Lab', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[LAB:{$localCode}]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        }

        $msg = $sent > 0 ? "{$sent} Diagnostic Report Lab berhasil dikirim." : 'Semua Diagnostic Report Lab sudah dikirim.';
        $msg = $this->logAndFormatSummary($bundle, 'DiagnosticReport Lab', $msg, $errors, $warnings, '/\[LAB:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'errors' => $errors, 'warnings' => $warnings, 'items' => $items];
    }

    public function sendRadDiagnosticReports(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $rads = \App\Models\Simrs\PermintaanRadiologi::where('no_rawat', $reg->no_rawat)
            ->with(['allPeriksaRad.jenisPerawatan'])
            ->orderBy('tgl_permintaan')
            ->orderBy('jam_permintaan')
            ->get();

        if ($rads->isEmpty()) {
            return ['success' => true, 'count' => 0, 'items' => [], 'errors' => [], 'warnings' => []];
        }

        $service = new \App\Services\SatuSehat\Resources\DiagnosticReportService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        foreach ($rads as $rad) {
            try {
                $noOrder = $rad->noorder;
                $periksaRad = $rad->periksa_rad->first();
                $localCode = $periksaRad?->kd_jenis_prw;
                $tglR = $periksaRad?->tgl_periksa ?? $rad->tgl_permintaan;
                $jamR = $periksaRad?->jam ?? $rad->jam_permintaan;
                $idStr = $reg->no_rawat . '-DR_RAD_' . $noOrder . '-' . ($tglR ? $tglR->format('Ymd') : '') . '-' . str_replace(':', '', $jamR ?? '000000');
                $acsn = $noOrder;

                if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                    continue;
                if (\App\Models\SatuSehat\SatuSehatDiagnosticReport::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                    $this->logBundleItem($bundle, 'DiagnosticReport', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                    continue;
                }

                if (!$periksaRad || !$localCode) {
                    $warnings[] = "[DR-RAD:{$noOrder}] Pemeriksaan radiologi belum dilakukan.";
                    $items[] = ['id' => $idStr, 'label' => "DR-RAD: {$noOrder}", 'status' => 'fail', 'message' => 'Pemeriksaan belum dilakukan.', 'sent_at' => now()->toIso8601String()];
                    continue;
                }

                // Validator: ServiceRequest (RAD) harus sudah ada menggunakan tgl_permintaan (bukan tgl_periksa)
                $srIdStr = $reg->no_rawat . '-SR_RAD_' . $noOrder . '-' . ($rad->tgl_permintaan ? $rad->tgl_permintaan->format('Ymd') : '') . '-' . str_replace(':', '', $rad->jam_permintaan ?? '000000');
                $sr = SatuSehatServiceRequest::where('local_id', $srIdStr)->first();
                if (!$sr) {
                    $warnings[] = "[DR-RAD:{$noOrder}] ServiceRequest RAD belum dikirim.";
                    $items[] = ['id' => $idStr, 'label' => "DR-RAD: {$noOrder}", 'status' => 'fail', 'message' => 'ServiceRequest RAD belum dikirim.', 'sent_at' => now()->toIso8601String()];
                    continue;
                }

                // $specIdStr = str_replace('DR_RAD_', 'SPEC_RAD_', $idStr);
                // $spec = SatuSehatSpecimen::where('local_id', $specIdStr)->first();
                // if (!$spec) {
                //     $warnings[] = "[DR-RAD:{$noOrder}] Specimen RAD belum dikirim.";
                //     $items[] = ['id' => $idStr, 'label' => "DR-RAD: {$noOrder}", 'status' => 'fail', 'message' => 'Specimen RAD belum dikirim.', 'sent_at' => now()->toIso8601String()];
                //     continue;
                // }

                $map = \App\Models\Mapping\RadMap::where('local_code', $localCode)->first();
                if (!$map || !$map->system_code) {
                    $warnings[] = "[RAD:{$noOrder}] Belum ada LOINC mapping.";
                    $items[] = ['id' => $idStr, 'label' => "DR-RAD: {$noOrder}", 'status' => 'fail', 'message' => 'Belum ada LOINC mapping.', 'sent_at' => now()->toIso8601String()];
                    continue;
                }
                if (!str_contains($map->system_code, '-')) {
                    $msg = "Kode '{$map->system_code}' bukan LOINC (format harus mengandung '-'). Kemungkinan kode SNOMED — perbaiki di Local Terminology → Radiologi.";
                    $warnings[] = "[RAD:{$noOrder}] {$msg}";
                    $items[] = ['id' => $idStr, 'label' => "DR-RAD: {$noOrder}", 'status' => 'fail', 'message' => $msg, 'sent_at' => now()->toIso8601String()];
                    continue;
                }

                $itemLabel = "DR-RAD: {$noOrder}";

                $hasilRad = HasilRadiologi::where('no_rawat', $reg->no_rawat)
                    ->where('tgl_periksa', $periksaRad->tgl_periksa)
                    ->where('jam', $periksaRad->jam)
                    ->first();

                if (!$hasilRad || !trim(strip_tags($hasilRad->hasil ?? ''))) {
                    $msg = 'Hasil radiologi belum diisi. Isi hasil pemeriksaan dahulu sebelum mengirim.';
                    $warnings[] = "[RAD:{$noOrder}] {$msg}";
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => now()->toIso8601String()];
                    continue;
                }

                $effectiveDateTime = $periksaRad->tgl_periksa
                    ? $periksaRad->tgl_periksa->format('Y-m-d') . 'T' . ($periksaRad->jam ?? '00:00:00') . '+07:00'
                    : now()->toIso8601String();

                // Cari ImagingStudy yang sesuai dengan order ini
                $isIdStr = str_replace(['DR_RAD_', 'OBS_RAD_'], 'IMG_RAD_', $idStr ?? $obsIdStr);
                $imagingStudy = \App\Models\SatuSehat\SatuSehatImagingStudy::where('local_id', $isIdStr)
                    ->orWhere('local_id', 'like', "%{$noOrder}")
                    ->first();

                if (!$imagingStudy) {
                    // Cek di worklist DICOM/PACS
                    $dicomStudy = \App\Models\Dicom\Worklist::where('noorder', $noOrder)->first();
                    if ($dicomStudy) {
                        if ($dicomStudy->imaging_study_ihs) {
                            // Jika ada di worklist tapi belum di tabel Satu Sehat lokal, buatkan recordnya
                            $imagingStudy = \App\Models\SatuSehat\SatuSehatImagingStudy::updateOrCreate(
                                ['ihs_number' => $dicomStudy->imaging_study_ihs],
                                [
                                    'local_id' => $isIdStr,
                                    'patient_ihs' => $encounter->patient_ihs,
                                    'encounter_ihs' => $encounter->ihs_number,
                                    'status' => 'available',
                                    'modality_code' => $dicomStudy->modality,
                                    'description' => $dicomStudy->procedure_desc,
                                    'started_at' => $dicomStudy->scheduled_date,
                                    'synced_at' => now(),
                                ]
                            );
                        } else {
                            $reason = $dicomStudy->error_message ?: 'Menunggu proses DICOM Router/PACS.';
                            $warnings[] = "[DR:{$noOrder}] ImagingStudy belum siap: {$reason}";
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => "ImagingStudy belum siap: {$reason}", 'sent_at' => now()->toIso8601String()];
                            continue;
                        }
                    } else {
                        $warnings[] = "[DR:{$noOrder}] ImagingStudy belum disinkronisasi. Pastikan order sudah masuk ke PACS.";
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => 'ImagingStudy belum dikirim.', 'sent_at' => now()->toIso8601String()];
                        continue;
                    }
                }

                // ── 2. Kirim Observation RAD ────────────────────────────────────
                $obsIdStr = str_replace('DR_RAD_', 'OBS_RAD_', $idStr);
                $observation = \App\Models\SatuSehat\SatuSehatObservation::where('local_id', $obsIdStr)->first();

                if (!$observation) {
                    $obsRes = $this->sendRadObservations($reg, $encounter, [$obsIdStr], $bundle);
                    if (!$obsRes['success'] || empty($obsRes['count'])) {
                        $errors[] = "[OBS-RAD:{$noOrder}] Gagal atau belum bisa mengirim Observation RAD.";
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => '[OBS-RAD] Gagal kirim observasi.', 'sent_at' => now()->toIso8601String()];
                        continue;
                    }
                    $observation = \App\Models\SatuSehat\SatuSehatObservation::where('local_id', $obsIdStr)->first();
                }

                // ── 3. Kirim DiagnosticReport RAD ───────────────────────────────
                $catMap = \App\Models\Mapping\DiagnosticCategoryMap::where('local_code', $localCode)->first();
                if (!$catMap || !$catMap->diagnostic_category) {
                    $msg = 'Belum ada Diagnostic Category mapping.';
                    $warnings[] = "[RAD:{$noOrder}] {$msg}";
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $msg, 'sent_at' => now()->toIso8601String()];
                    continue;
                }
                $radCategory = $catMap->diagnostic_category;
                $radCategoryDisplay = $catMap->diagnostic_category_term ?: null;

                $sentAt = now()->toIso8601String();

                try {
                    $sentResp = $service->createRadiologyReport(
                        patientId: $encounter->patient_ihs,
                        encounterId: $encounter->ihs_number,
                        performerId: $encounter->practitioner_ihs,
                        code: $map->system_code,
                        display: $map->system_term ?? $map->system_code,
                        observationIds: [$observation->ihs_number],
                        identifier: $acsn,
                        serviceRequestId: $sr?->ihs_number,
                        conclusion: $hasilRad?->hasil ? strip_tags($hasilRad->hasil) : 'Hasil Expertise Radiologi',
                        imagingStudyIds: [$imagingStudy->ihs_number],
                        effectiveDateTime: $effectiveDateTime,
                        issued: $effectiveDateTime,
                        category: $radCategory,
                        categoryDisplay: $radCategoryDisplay,
                    );
                    $this->throttle();

                    if (!$sentResp->success) {
                        $this->logBundleItem(bundle: $bundle, type: 'DiagnosticReport Rad', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                        $errors[] = "[RAD:{$noOrder}] " . $sentResp->error;
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                        continue;
                    }
                    $this->logBundleItem(bundle: $bundle, type: 'DiagnosticReport Rad', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                    \App\Models\SatuSehat\SatuSehatDiagnosticReport::create([
                        'ihs_number' => $sentResp->resourceId,
                        'local_id' => $idStr,
                        'patient_ihs' => $encounter->patient_ihs,
                        'encounter_ihs' => $encounter->ihs_number,
                        'status' => 'final',
                        'category' => $radCategory,
                        'code' => $map->system_code,
                        'code_display' => $map->system_display ?? $map->system_code,
                        'conclusion' => \Illuminate\Support\Str::limit($hasilRad?->hasil ? strip_tags($hasilRad->hasil) : 'Hasil Expertise Radiologi', 200),
                        'effective_datetime' => $periksaRad->tgl_periksa ? $periksaRad->tgl_periksa->format('Y-m-d') . ' ' . $periksaRad->jam : now(),
                        'raw_response' => $sentResp->data,
                        'synced_at' => now(),
                    ]);
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                    $sent++;
                } catch (\Exception $e) {
                    if ($this->isDuplicate($e)) {
                        $searchResp = $service->searchByEncounter($encounter->ihs_number);
                        $found = $this->findByIdentifier($searchResp, $acsn) ?? $this->findByIdentifier($searchResp, $idStr);
                        if ($found) {
                            \App\Models\SatuSehat\SatuSehatDiagnosticReport::create([
                                'ihs_number' => $found['id'],
                                'local_id' => $idStr,
                                'patient_ihs' => $encounter->patient_ihs,
                                'encounter_ihs' => $encounter->ihs_number,
                                'status' => 'final',
                                'category' => $radCategory,
                                'code' => $map->system_code,
                                'code_display' => $map->system_display ?? $map->system_code,
                                'conclusion' => \Illuminate\Support\Str::limit($hasilRad?->hasil ? strip_tags($hasilRad->hasil) : 'Hasil Expertise Radiologi', 200),
                                'effective_datetime' => $periksaRad->tgl_periksa ? $periksaRad->tgl_periksa->format('Y-m-d') . ' ' . $periksaRad->jam : now(),
                                'raw_response' => $found,
                                'synced_at' => now(),
                            ]);
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                            $sent++;
                            continue;
                        }
                    }
                    $this->logBundleItem(bundle: $bundle, type: 'DiagnosticReport Rad', localId: $idStr, status: 'failed', error: $e->getMessage());
                    $errors[] = "[RAD:{$noOrder}]: " . $e->getMessage();
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
                }
            } catch (\Throwable $e) {
                $noOrder ??= '?';
                $this->logBundleItem($bundle, 'DiagnosticReport Rad', $idStr ?? null, 'failed', null, null, $e->getMessage());
                $errors[] = "[RAD:{$noOrder}] " . $e->getMessage();
                $items[] = ['id' => $idStr ?? '', 'label' => "DR-RAD: {$noOrder}", 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => now()->toIso8601String()];
            }
        }

        $msg = $sent > 0
            ? "{$sent} Diagnostic Report Rad berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Diagnostic Report Rad.' : 'Tidak ada Diagnostic Report Rad yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'DiagnosticReport Rad', $msg, $errors, $warnings, '/\[RAD:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'errors' => $errors, 'warnings' => $warnings, 'items' => $items];
    }

    public function sendLabSpecimens(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $labs = \App\Models\Simrs\PeriksaLab::where('no_rawat', $reg->no_rawat)->with('jenisPerawatan')->get();
        if ($labs->isEmpty()) {
            return ['success' => true, 'count' => 0, 'items' => [], 'errors' => [], 'warnings' => []];
        }

        $service = new \App\Services\SatuSehat\Resources\SpecimenService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        foreach ($labs as $lab) {
            $localCode = $lab->kd_jenis_prw;
            if (!$localCode)
                continue;

            $idStr = $reg->no_rawat . '-SPEC_LAB_' . $localCode . '-' . ($lab->tgl_periksa ? $lab->tgl_periksa->format('Ymd') : '') . '-' . str_replace(':', '', $lab->jam ?? '');
            if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                continue;
            if (\App\Models\SatuSehat\SatuSehatSpecimen::where('local_id', $idStr)->exists()) {
                $this->logBundleItem($bundle, 'Specimen', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                continue;
            }

            $srIdStr = str_replace('SPEC_LAB_', 'SR_LAB_', $idStr);
            $req = \App\Models\SatuSehat\SatuSehatServiceRequest::where('local_id', $srIdStr)->first();
            if (!$req) {
                $warnings[] = "[SPEC-LAB:{$localCode}] ServiceRequest LAB belum dikirim.";
                continue;
            }

            $itemLabel = "Specimen LAB: " . ($lab->jenisPerawatan?->nm_perawatan ?? $localCode);
            $sentAt = now()->toIso8601String();

            try {
                $isUrine = stripos(strtolower($lab->jenisPerawatan?->nm_perawatan ?? ''), 'urin') !== false;

                if ($isUrine) {
                    $sentResp = $service->createUrineSpecimen(
                        patientId: $encounter->patient_ihs,
                        identifier: $idStr,
                        collectorId: $encounter->practitioner_ihs,
                        serviceRequestId: $req?->ihs_number
                    );
                    $typeCode = '122575003';
                    $typeDisplay = 'Urine specimen';
                } else {
                    $sentResp = $service->createBloodSpecimen(
                        patientId: $encounter->patient_ihs,
                        identifier: $idStr,
                        collectorId: $encounter->practitioner_ihs,
                        serviceRequestId: $req?->ihs_number
                    );
                    $typeCode = '119297000';
                    $typeDisplay = 'Blood specimen';
                }
                $this->throttle();

                if (!$sentResp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'Specimen Lab', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                    $errors[] = "[SPEC-LAB:{$localCode}] " . $sentResp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                    continue;
                }
                $this->logBundleItem(bundle: $bundle, type: 'Specimen Lab', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                \App\Models\SatuSehat\SatuSehatSpecimen::create([
                    'ihs_number' => $sentResp->resourceId,
                    'local_id' => $idStr,
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'service_request_ihs' => $req?->ihs_number,
                    'type_code' => $typeCode,
                    'type_display' => $typeDisplay,
                    'status' => 'available',
                    'collected_datetime' => $lab->tgl_periksa ? $lab->tgl_periksa->format('Y-m-d') . ' ' . $lab->jam : now(),
                    'raw_response' => $sentResp->data,
                    'synced_at' => now(),
                ]);
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByIdentifier($idStr);
                    if ($searchResp->success && !empty($searchResp->getResources())) {
                        $found = $searchResp->getResources()[0];
                        \App\Models\SatuSehat\SatuSehatSpecimen::create([
                            'ihs_number' => $found['id'],
                            'local_id' => $idStr,
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'service_request_ihs' => $req?->ihs_number,
                            'type_code' => $typeCode,
                            'type_display' => $typeDisplay,
                            'status' => 'available',
                            'collected_datetime' => $lab->tgl_periksa ? $lab->tgl_periksa->format('Y-m-d') . ' ' . $lab->jam : now(),
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        continue;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'Specimen Lab', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[SPEC-LAB:{$localCode}]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        }

        $msg = $sent > 0
            ? "{$sent} Specimen Lab berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Specimen Lab.' : 'Tidak ada Specimen Lab yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'Specimen Lab', $msg, $errors, $warnings, '/\[SPEC-LAB:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'errors' => $errors, 'warnings' => $warnings, 'items' => $items];
    }

    public function sendRadSpecimens(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        return ['success' => true, 'count' => 0, 'items' => [], 'errors' => [], 'warnings' => [], 'message' => 'Pengiriman Specimen Radiologi dinonaktifkan.'];

        if ($err = $this->validateEncounter($encounter))
            return $err;

        $rads = \App\Models\Simrs\PermintaanRadiologi::where('no_rawat', $reg->no_rawat)
            ->with(['allPeriksaRad.jenisPerawatan'])
            ->orderBy('tgl_permintaan')
            ->orderBy('jam_permintaan')
            ->get();

        if ($rads->isEmpty()) {
            return ['success' => true, 'count' => 0, 'items' => [], 'errors' => [], 'warnings' => []];
        }

        $service = new \App\Services\SatuSehat\Resources\SpecimenService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        foreach ($rads as $rad) {
            try {
                $noOrder = $rad->noorder;
                $periksaRad = $rad->periksa_rad->first();
                $localCode = $periksaRad?->kd_jenis_prw;
                $tglSpec = $periksaRad?->tgl_periksa ? $periksaRad->tgl_periksa->format('Ymd') : ($rad->tgl_permintaan ? $rad->tgl_permintaan->format('Ymd') : '');
                $jamSpec = str_replace(':', '', $periksaRad?->jam ?? ($rad->jam_permintaan ?? '000000'));
                $idStr = $reg->no_rawat . '-SPEC_RAD_' . $noOrder . '-' . $tglSpec . '-' . $jamSpec;
                $acsn = $noOrder;

                if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                    continue;
                if (\App\Models\SatuSehat\SatuSehatSpecimen::where('local_id', $idStr)->exists()) {
                    $this->logBundleItem($bundle, 'Specimen', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                    continue;
                }

                if (!$periksaRad || !$localCode) {
                    $warnings[] = "[SPEC-RAD:{$noOrder}] Pemeriksaan radiologi belum dilakukan.";
                    $items[] = ['id' => $idStr, 'label' => "Specimen RAD: {$noOrder}", 'status' => 'fail', 'message' => 'Pemeriksaan belum dilakukan.', 'sent_at' => now()->toIso8601String()];
                    continue;
                }

                // ServiceRequest RAD menggunakan tgl_permintaan, bukan tgl_periksa/specimen
                $srIdStr = $reg->no_rawat . '-SR_RAD_' . $noOrder . '-' . ($rad->tgl_permintaan ? $rad->tgl_permintaan->format('Ymd') : '') . '-' . str_replace(':', '', $rad->jam_permintaan ?? '000000');
                $req = \App\Models\SatuSehat\SatuSehatServiceRequest::where('local_id', $srIdStr)->first();
                if (!$req) {
                    $warnings[] = "[SPEC-RAD:{$noOrder}] ServiceRequest RAD belum dikirim.";
                    continue;
                }

                $itemLabel = "Specimen RAD: " . ($periksaRad->jenisPerawatan?->nm_perawatan ?? $localCode);
                $sentAt = now()->toIso8601String();

                try {
                    $sentResp = $service->createBloodSpecimen(
                        patientId: $encounter->patient_ihs,
                        identifier: $acsn,
                        collectorId: $encounter->practitioner_ihs,
                        serviceRequestId: $req?->ihs_number
                    );
                    $this->throttle();

                    if (!$sentResp->success) {
                        $this->logBundleItem(bundle: $bundle, type: 'Specimen Rad', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                        $errors[] = "[SPEC-RAD:{$noOrder}] " . $sentResp->error;
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                        continue;
                    }
                    $this->logBundleItem(bundle: $bundle, type: 'Specimen Rad', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                    \App\Models\SatuSehat\SatuSehatSpecimen::create([
                        'ihs_number' => $sentResp->resourceId,
                        'local_id' => $idStr,
                        'patient_ihs' => $encounter->patient_ihs,
                        'encounter_ihs' => $encounter->ihs_number,
                        'service_request_ihs' => $req?->ihs_number,
                        'type_code' => '119297000',
                        'type_display' => 'Blood specimen',
                        'status' => 'available',
                        'collected_datetime' => $periksaRad->tgl_periksa ? $periksaRad->tgl_periksa->format('Y-m-d') . ' ' . $periksaRad->jam : now(),
                        'raw_response' => $sentResp->data,
                        'synced_at' => now(),
                    ]);
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                    $sent++;
                } catch (\Exception $e) {
                    if ($this->isDuplicate($e)) {
                        $searchResp = $service->searchByIdentifier($acsn);
                        if ($searchResp->success && !empty($searchResp->getResources())) {
                            $found = $searchResp->getResources()[0];
                            \App\Models\SatuSehat\SatuSehatSpecimen::create([
                                'ihs_number' => $found['id'],
                                'local_id' => $idStr,
                                'patient_ihs' => $encounter->patient_ihs,
                                'encounter_ihs' => $encounter->ihs_number,
                                'service_request_ihs' => $req?->ihs_number,
                                'type_code' => '119297000',
                                'type_display' => 'Blood specimen',
                                'status' => 'available',
                                'collected_datetime' => $periksaRad->tgl_periksa ? $periksaRad->tgl_periksa->format('Y-m-d') . ' ' . $periksaRad->jam : now(),
                                'raw_response' => $found,
                                'synced_at' => now(),
                            ]);
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                            $sent++;
                            continue;
                        }
                    }
                    $this->logBundleItem(bundle: $bundle, type: 'Specimen Rad', localId: $idStr, status: 'failed', error: $e->getMessage());
                    $errors[] = "[SPEC-RAD:{$noOrder}]: " . $e->getMessage();
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
                }
            } catch (\Throwable $e) {
                $noOrder ??= '?';
                $this->logBundleItem($bundle, 'Specimen Rad', $idStr ?? null, 'failed', null, null, $e->getMessage());
                $errors[] = "[SPEC-RAD:{$noOrder}] " . $e->getMessage();
                $items[] = ['id' => $idStr ?? '', 'label' => "Specimen RAD: {$noOrder}", 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => now()->toIso8601String()];
            }
        }

        $msg = $sent > 0
            ? "{$sent} Specimen Rad berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Specimen Rad.' : 'Tidak ada Specimen Rad yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'Specimen Rad', $msg, $errors, $warnings, '/\[SPEC-RAD:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'errors' => $errors, 'warnings' => $warnings, 'items' => $items];
    }

    /**
     * Kirim Observation hasil lab PK ke Satu Sehat.
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public function sendLabObservations(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        // Validator: ServiceRequest (LAB) harus sudah ada
        if (!SatuSehatServiceRequest::where('encounter_ihs', $encounter->ihs_number)->where('note', 'LAB')->exists()) {
            return ['success' => false, 'count' => 0, 'message' => 'Service Request (Laboratorium) belum dikirim.', 'errors' => ['ServiceRequest LAB belum ada.']];
        }

        // Ambil semua detail hasil lab PK (dengan template untuk nama pemeriksaan)
        $details = \App\Models\Simrs\DetailPeriksaLab::with(['jenisPerawatan', 'template'])
            ->where('no_rawat', $reg->no_rawat)
            ->get();

        if ($details->isEmpty()) {
            return ['success' => false, 'message' => 'Tidak ada data hasil lab PK untuk dikirim.'];
        }

        $service = new \App\Services\SatuSehat\Resources\ObservationService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];
        $labOrgId = \App\Models\SatuSehat\SatuSehatOrganization::where('identifier', 'LAB')->value('ihs_number');

        foreach ($details as $detail) {
            $localCode = $detail->kd_jenis_prw;
            if (!$localCode)
                continue;

            // ID unik per item template
            $templateId = $detail->id_template ?? 'item';
            $idStr = $reg->no_rawat . '-OBS_LAB_' . $localCode . '_' . $templateId . '-'
                . ($detail->tgl_periksa ? $detail->tgl_periksa->format('Ymd') : '')
                . '-' . str_replace(':', '', $detail->jam ?? '');

            if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                continue;

            // Cek sudah terkirim
            if (\App\Models\SatuSehat\SatuSehatObservation::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                $this->logBundleItem($bundle, 'Observation', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                continue;
            }

            // Map LOINC
            $map = \App\Models\Mapping\LabMap::where('local_code', $localCode)->first();
            if (!$map || !$map->system_code) {
                $warnings[] = "[OBS:{$localCode}] Belum ada LOINC mapping.";
                continue;
            }

            // Bangun valueString
            $hasil = trim($detail->nilai ?? '-');
            $nilaiRujukan = trim($detail->nilai_rujukan ?? $detail->template?->nilai_rujukan_ld ?? '');
            $keterangan = trim($detail->keterangan ?? '');

            $valueString = "Hasil Lab: {$hasil}";
            if ($nilaiRujukan) {
                $valueString .= ", Nilai Rujukan: {$nilaiRujukan}";
            }
            if ($keterangan) {
                $valueString .= ", Keterangan: {$keterangan}";
            }

            // Label display: nama template item jika ada, fallback ke nama jenis perawatan
            $display = $detail->template?->Pemeriksaan
                ?? $detail->jenisPerawatan?->nm_perawatan
                ?? $map->system_term
                ?? $map->system_code;

            // Specimen terkait — wajib ada sebelum kirim Observation
            $specimenIdStr = $reg->no_rawat . '-SPEC_LAB_' . $localCode . '-'
                . ($detail->tgl_periksa ? $detail->tgl_periksa->format('Ymd') : '')
                . '-' . str_replace(':', '', $detail->jam ?? '');
            $specimenIhs = \App\Models\SatuSehat\SatuSehatSpecimen::where('local_id', $specimenIdStr)->value('ihs_number');
            if (!$specimenIhs) {
                // Mencoba kirim Specimen LAB jika belum ada
                $specRes = $this->sendLabSpecimens($reg, $encounter, [$specimenIdStr], $bundle);
                if (!$specRes['success'] || empty($specRes['count'])) {
                    $warnings[] = "[OBS:{$localCode}:{$templateId}] Gagal atau belum bisa mengirim Specimen LAB.";
                    continue;
                }
                $specimenIhs = \App\Models\SatuSehat\SatuSehatSpecimen::where('local_id', $specimenIdStr)->value('ihs_number');
            }

            if (!$specimenIhs) {
                $warnings[] = "[OBS:{$localCode}:{$templateId}] Specimen belum dikirim. Kirim Specimen terlebih dahulu.";
                continue;
            }

            $effectiveDateTime = $detail->tgl_periksa
                ? $detail->tgl_periksa->format('Y-m-d') . 'T' . ($detail->jam ?? '00:00:00') . '+07:00'
                : now()->toIso8601String();

            $labName = $detail->jenisPerawatan?->nm_perawatan ?? $display;
            $tglStr = $detail->tgl_periksa ? $detail->tgl_periksa->format('Y-m-d') : '';
            $encounterDisplay = "Hasil Pemeriksaan Lab {$labName} No.Rawat {$reg->no_rawat}"
                . ($reg->pasien?->nm_pasien ? ", Atas Nama Pasien {$reg->pasien->nm_pasien}" : '')
                . ", No.RM {$reg->no_rkm_medis}, Pada Tanggal {$tglStr}";

            $itemLabel = "Obs Lab: {$display}";
            $sentAt = now()->toIso8601String();

            try {
                $sentResp = $service->createLabObservation(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    performerId: $encounter->practitioner_ihs,
                    code: $map->system_code,
                    organizationId: $labOrgId,
                    display: $display,
                    codeSystem: 'http://loinc.org',
                    valueString: $valueString,
                    specimenId: $specimenIhs,
                    effectiveDateTime: $effectiveDateTime,
                    identifier: $idStr,
                    encounterDisplay: $encounterDisplay,
                );
                $this->throttle();

                if (!$sentResp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'Observation Lab', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                    $errors[] = "[OBS:{$localCode}:{$templateId}] " . $sentResp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                    continue;
                }
                $this->logBundleItem(bundle: $bundle, type: 'Observation Lab', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                \App\Models\SatuSehat\SatuSehatObservation::create([
                    'ihs_number' => $sentResp->resourceId,
                    'local_id' => $idStr,
                    'identifier' => json_encode($sentResp->data['identifier']),
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'performer_ihs' => $encounter->practitioner_ihs,
                    'status' => 'final',
                    'category' => 'laboratory',
                    'code' => $map->system_code,
                    'code_display' => $display,
                    'value_type' => 'String',
                    'value_string' => $valueString,
                    'effective_datetime' => $detail->tgl_periksa
                        ? $detail->tgl_periksa->format('Y-m-d') . ' ' . ($detail->jam ?? '00:00:00')
                        : now(),
                    'raw_response' => $sentResp->data,
                    'synced_at' => now(),
                ]);
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByEncounter($encounter->ihs_number);
                    $found = $this->findByIdentifier($searchResp, $idStr);
                    if ($found) {
                        \App\Models\SatuSehat\SatuSehatObservation::create([
                            'ihs_number' => $found['id'],
                            'local_id' => $idStr,
                            'identifier' => json_encode($found['identifier'] ?? []),
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'performer_ihs' => $encounter->practitioner_ihs,
                            'status' => 'final',
                            'category' => 'laboratory',
                            'code' => $map->system_code,
                            'code_display' => $display,
                            'value_type' => 'String',
                            'value_string' => $valueString,
                            'effective_datetime' => $detail->tgl_periksa
                                ? $detail->tgl_periksa->format('Y-m-d') . ' ' . ($detail->jam ?? '00:00:00')
                                : now(),
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        continue;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'Observation Lab', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[OBS:{$localCode}:{$templateId}]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        }

        $msg = $sent > 0
            ? "{$sent} Hasil Lab berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Hasil Lab.' : 'Tidak ada Hasil Lab yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'Observation Lab', $msg, $errors, $warnings, '/\[OBS:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    /**
     * Kirim Observation hasil radiologi ke Satu Sehat.
     * Prasyarat: Imaging Study harus sudah dikirim.
     */
    public function sendRadObservations(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        // Validator: ServiceRequest (RAD) harus sudah ada
        if (!SatuSehatServiceRequest::where('encounter_ihs', $encounter->ihs_number)->where('note', 'RAD')->exists()) {
            return ['success' => false, 'count' => 0, 'message' => 'Service Request (Radiologi) belum dikirim.', 'errors' => ['ServiceRequest RAD belum ada.']];
        }

        $rads = \App\Models\Simrs\PermintaanRadiologi::where('no_rawat', $reg->no_rawat)
            ->with(['allPeriksaRad.jenisPerawatan'])
            ->orderBy('tgl_permintaan')
            ->orderBy('jam_permintaan')
            ->get();

        if ($rads->isEmpty()) {
            return ['success' => false, 'message' => 'Tidak ada data pelayanan Radiologi untuk dikirim.'];
        }

        $obsService = new \App\Services\SatuSehat\Resources\ObservationService();
        $sent = 0;
        $warnings = [];
        $errors = [];

        foreach ($rads as $rad) {
            try {
                $noOrder = $rad->noorder;
                $periksaRad = $rad->periksa_rad->first();
                $localCode = $periksaRad?->kd_jenis_prw;
                $tglR = $periksaRad?->tgl_periksa ?? $rad->tgl_permintaan;
                $jamR = $periksaRad?->jam ?? $rad->jam_permintaan;
                $obsIdStr = $reg->no_rawat . '-OBS_RAD_' . $noOrder . '-' . ($tglR ? $tglR->format('Ymd') : '') . '-' . str_replace(':', '', $jamR ?? '000000');

                if ($selectedIds !== null && !in_array($obsIdStr, $selectedIds))
                    continue;
                if (\App\Models\SatuSehat\SatuSehatObservation::where('local_id', $obsIdStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                    $this->logBundleItem($bundle, 'Observation', $obsIdStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                    continue;
                }

                if (!$periksaRad || !$localCode) {
                    $warnings[] = "[OBS-RAD:{$noOrder}] Pemeriksaan radiologi belum dilakukan.";
                    continue;
                }

                // Cari ImagingStudy yang sesuai dengan order ini
                $isIdStr = str_replace(['DR_RAD_', 'OBS_RAD_'], 'IMG_RAD_', $idStr ?? $obsIdStr);
                $imagingStudy = \App\Models\SatuSehat\SatuSehatImagingStudy::where('local_id', $isIdStr)
                    ->orWhere('local_id', 'like', "%{$noOrder}")
                    ->first();

                $srIdStr = $reg->no_rawat . '-SR_RAD_' . $noOrder . '-' . ($rad->tgl_permintaan ? $rad->tgl_permintaan->format('Ymd') : '') . '-' . str_replace(':', '', $rad->jam_permintaan ?? '000000');
                // $specIdStr = str_replace('OBS_RAD_', 'SPEC_RAD_', $obsIdStr); // specimen rad dinonaktifkan

                if (!$imagingStudy) {
                    // Mencoba kirim ImagingStudy jika belum ada
                    $isRes = $this->sendImagingStudies($reg, $encounter, [$isIdStr], $bundle);
                    $imagingStudy = \App\Models\SatuSehat\SatuSehatImagingStudy::where('local_id', $isIdStr)
                        ->orWhere('local_id', 'like', "%{$noOrder}")
                        ->first();
                }

                if (!$imagingStudy) {
                    // Cek di worklist DICOM/PACS sebagai fallback
                    $dicomStudy = \App\Models\Dicom\Worklist::where('noorder', $noOrder)->first();
                    if ($dicomStudy && $dicomStudy->imaging_study_ihs) {
                        $imagingStudy = \App\Models\SatuSehat\SatuSehatImagingStudy::updateOrCreate(
                            ['ihs_number' => $dicomStudy->imaging_study_ihs],
                            [
                                'local_id' => $isIdStr,
                                'patient_ihs' => $encounter->patient_ihs,
                                'encounter_ihs' => $encounter->ihs_number,
                                'status' => 'available',
                                'modality_code' => $dicomStudy->modality,
                                'description' => $dicomStudy->procedure_desc,
                                'started_at' => $dicomStudy->scheduled_date,
                                'synced_at' => now(),
                            ]
                        );
                    }
                }

                if (!$imagingStudy) {
                    $warnings[] = "[RAD:{$noOrder}] ImagingStudy belum disinkronisasi. Pastikan order sudah masuk ke PACS.";
                    continue;
                }

                $req = \App\Models\SatuSehat\SatuSehatServiceRequest::where('local_id', $srIdStr)->first();
                $map = \App\Models\Mapping\RadMap::where('local_code', $localCode)->first();
                if (!$map || !$map->system_code) {
                    $warnings[] = "[RAD:{$noOrder}] Belum ada LOINC mapping.";
                    continue;
                }

                $hasilRad = \App\Models\Simrs\HasilRadiologi::where('no_rawat', $reg->no_rawat)
                    ->where('tgl_periksa', $periksaRad->tgl_periksa)
                    ->where('jam', $periksaRad->jam)
                    ->first();

                $effectiveDateTime = $periksaRad->tgl_periksa
                    ? $periksaRad->tgl_periksa->format('Y-m-d') . 'T' . ($periksaRad->jam ?? '00:00:00') . '+07:00'
                    : now()->toIso8601String();

                $valueString = $hasilRad?->hasil ? strip_tags($hasilRad->hasil) : 'Hasil Pemeriksaan Radiologi';

                $radName = $periksaRad->jenisPerawatan?->nm_perawatan ?? '';
                $tglStr = $periksaRad->tgl_periksa ? $periksaRad->tgl_periksa->format('Y-m-d') : '';
                $encounterDisplay = "Hasil Pemeriksaan Radiologi {$radName} No.Rawat {$reg->no_rawat}"
                    . ($reg->pasien?->nm_pasien ? ", Atas Nama Pasien {$reg->pasien->nm_pasien}" : '')
                    . ", No.RM {$reg->no_rkm_medis}, Pada Tanggal {$tglStr}";

                // specimen rad dinonaktifkan — langkah ini dilewati
                // $specimenIhs = \App\Models\SatuSehat\SatuSehatSpecimen::where('local_id', $specIdStr)->value('ihs_number');
                // if (!$specimenIhs) { ... }

                try {
                    $obsResp = $obsService->createRadiologyObservation(
                        patientId: $encounter->patient_ihs,
                        encounterId: $encounter->ihs_number,
                        performerId: $encounter->practitioner_ihs,
                        code: $map->system_code,
                        display: $map->system_term ?? $map->system_code,
                        valueString: $valueString,
                        codeSystem: 'http://loinc.org',
                        derivedFromId: $imagingStudy->ihs_number,
                        basedOnId: $req?->ihs_number,
                        effectiveDateTime: $effectiveDateTime,
                        issued: $effectiveDateTime,
                        identifier: $obsIdStr,
                        encounterDisplay: $encounterDisplay,
                        specimenId: null, // specimen rad dinonaktifkan
                    );
                    $this->throttle();

                    if (!$obsResp->success) {
                        $this->logBundleItem(bundle: $bundle, type: 'Observation Rad', localId: $obsIdStr, status: 'failed', payload: $obsService->getLastPayload(), response: $obsResp->data, error: $obsResp->error);
                        $errors[] = "[OBS-RAD:{$noOrder}] " . $obsResp->error;
                        continue;
                    }
                    $this->logBundleItem(bundle: $bundle, type: 'Observation Rad', localId: $obsIdStr, status: 'success', payload: $obsService->getLastPayload(), response: $obsResp->data, ihsId: $obsResp->resourceId);

                    \App\Models\SatuSehat\SatuSehatObservation::create([
                        'ihs_number' => $obsResp->resourceId,
                        'local_id' => $obsIdStr,
                        'identifier' => json_encode($obsResp->data['identifier']),
                        'patient_ihs' => $encounter->patient_ihs,
                        'encounter_ihs' => $encounter->ihs_number,
                        'performer_ihs' => $encounter->practitioner_ihs,
                        'status' => 'final',
                        'category' => 'imaging',
                        'code' => $map->system_code,
                        'code_display' => $map->system_term ?? $map->system_code,
                        'value_type' => 'String',
                        'value_string' => \Illuminate\Support\Str::limit($valueString, 200),
                        'effective_datetime' => $periksaRad->tgl_periksa
                            ? $periksaRad->tgl_periksa->format('Y-m-d') . ' ' . ($periksaRad->jam ?? '00:00:00')
                            : now(),
                        'raw_response' => $obsResp->data,
                        'synced_at' => now(),
                    ]);
                    $sent++;
                } catch (\Exception $e) {
                    $this->logBundleItem(bundle: $bundle, type: 'Observation Rad', localId: $obsIdStr, status: 'failed', error: $e->getMessage());
                    $errors[] = "[OBS-RAD:{$noOrder}]: " . $e->getMessage();
                }
            } catch (\Throwable $e) {
                $noOrder ??= '?';
                $this->logBundleItem($bundle, 'Observation Rad', $obsIdStr ?? null, 'failed', null, null, $e->getMessage());
                $errors[] = "[OBS-RAD:{$noOrder}] " . $e->getMessage();
            }
        }

        $msg = $sent > 0
            ? "{$sent} Hasil Radiologi berhasil dikirim."
            : 'Tidak ada Hasil Radiologi yang dikirim.';
        $msg = $this->logAndFormatSummary($bundle, 'Observation Rad', $msg, $errors, $warnings, '/\[(?:OBS-RAD|RAD):([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent];
    }

    /**
     * Validasi kelengkapan semua resource FHIR sebelum Resume Composition boleh dikirim.
     * Setiap check dibungkus try/catch — jika SIMRS tidak tersedia, check dilewati.
     */
    private function validateResumeReadiness(RegPeriksa $reg, SatuSehatEncounter $encounter): array
    {
        $issues = [];
        $noRawat = $reg->no_rawat;
        $encIhs = $encounter->ihs_number;

        // 1. Condition (Diagnosa)
        try {
            $exp = DiagnosaPasien::where('no_rawat', $noRawat)->count();
            $sent = SatuSehatCondition::where('encounter_ihs', $encIhs)->count();
            if ($sent < $exp)
                $issues[] = "Condition: {$sent}/{$exp} terkirim";
        } catch (\Exception) {
        }

        // 2. Procedure (Prosedur & Tindakan)
        try {
            $exp = ProsedurPasien::where('no_rawat', $noRawat)->count();
            $tndTables = $reg->status_lanjut === 'Ralan'
                ? ['rawat_jl_dr', 'rawat_jl_pr', 'rawat_jl_drpr']
                : ['rawat_inap_dr', 'rawat_inap_pr', 'rawat_inap_drpr'];
            foreach ($tndTables as $tbl) {
                $exp += DB::connection('simrs')->table($tbl)->where('no_rawat', $noRawat)->count();
            }
            $sent = SatuSehatProcedure::where('encounter_ihs', $encIhs)->count();
            if ($sent < $exp)
                $issues[] = "Procedure: {$sent}/{$exp} terkirim";
        } catch (\Exception) {
        }

        // 3. Observation TTV
        try {
            $pemModel = $reg->status_lanjut === 'Ranap' ? PemeriksaanRanap::class : PemeriksaanRalan::class;
            $exp = $pemModel::where('no_rawat', $noRawat)->count();
            $sent = SatuSehatObservation::where('encounter_ihs', $encIhs)->where('category', 'vital-signs')->count();
            if ($exp > 0 && $sent === 0)
                $issues[] = "Observation TTV: belum ada yang terkirim ({$exp} pemeriksaan)";
        } catch (\Exception) {
        }

        // 4. Medication (jika ada obat di SIMRS)
        try {
            $exp = DetailPemberianObat::where('no_rawat', $noRawat)->count();
            if ($exp > 0) {
                $sentReq = SatuSehatMedicationRequest::where('encounter_ihs', $encIhs)->count();
                $sentDisp = SatuSehatMedicationDispense::where('encounter_ihs', $encIhs)->count();
                $sentAdm = SatuSehatMedicationAdministration::where('encounter_ihs', $encIhs)->count();
                if ($sentReq < $exp)
                    $issues[] = "MedicationRequest: {$sentReq}/{$exp} terkirim";
                if ($sentDisp < $exp)
                    $issues[] = "MedicationDispense: {$sentDisp}/{$exp} terkirim";
                if ($sentAdm < $exp)
                    $issues[] = "MedicationAdministration: {$sentAdm}/{$exp} terkirim";
            }
        } catch (\Exception) {
        }

        // 5. Laboratorium (jika ada lab di SIMRS)
        try {
            $exp = PeriksaLab::where('no_rawat', $noRawat)->count();
            if ($exp > 0) {
                $sentSr = SatuSehatServiceRequest::where('encounter_ihs', $encIhs)->where('note', 'LAB')->count();
                $sentSpec = SatuSehatSpecimen::where('local_id', 'like', "{$noRawat}-SPEC_LAB_%")->count();
                $sentObs = SatuSehatObservation::where('encounter_ihs', $encIhs)->where('category', 'laboratory')->count();
                $sentDr = SatuSehatDiagnosticReport::where('encounter_ihs', $encIhs)->where('category', 'LAB')->count();
                if ($sentSr < $exp)
                    $issues[] = "ServiceRequest LAB: {$sentSr}/{$exp} terkirim";
                if ($sentSpec < $exp)
                    $issues[] = "Specimen LAB: {$sentSpec}/{$exp} terkirim";
                if ($sentObs === 0)
                    $issues[] = "Observation LAB: belum ada yang terkirim";
                if ($sentDr < $exp)
                    $issues[] = "DiagnosticReport LAB: {$sentDr}/{$exp} terkirim";
            }
        } catch (\Exception) {
        }

        // 6. Radiologi (jika ada permintaan rad di SIMRS)
        try {
            $exp = PermintaanRadiologi::where('no_rawat', $noRawat)->count();
            if ($exp > 0) {
                $sentSr = SatuSehatServiceRequest::where('encounter_ihs', $encIhs)->where('note', 'RAD')->count();
                $sentDr = SatuSehatDiagnosticReport::where('encounter_ihs', $encIhs)->where('category', 'RAD')->count();
                $sentObs = SatuSehatObservation::where('encounter_ihs', $encIhs)->where('local_id', 'like', "{$noRawat}-OBS_RAD_%")->count();
                $sentImg = SatuSehatImagingStudy::where('local_id', 'like', "{$noRawat}-IMG_RAD_%")->count();
                if ($sentSr < $exp)
                    $issues[] = "ServiceRequest RAD: {$sentSr}/{$exp} terkirim";
                if ($sentDr < $exp)
                    $issues[] = "DiagnosticReport RAD: {$sentDr}/{$exp} terkirim";
                if ($sentObs === 0)
                    $issues[] = "Observation RAD: belum ada yang terkirim";
                if ($sentImg === 0)
                    $issues[] = "ImagingStudy RAD: belum ada yang terkirim";
            }
        } catch (\Exception) {
        }

        // 7. USG (jika ada permintaan usg di SIMRS)
        try {
            $expUsg = \App\Models\Simrs\Usg\PermintaanUsg::where('no_rawat', $noRawat)->count();
            if ($expUsg > 0) {
                $sentSr = SatuSehatServiceRequest::where('encounter_ihs', $encIhs)->where('note', 'USG')->count();
                $sentDr = SatuSehatDiagnosticReport::where('encounter_ihs', $encIhs)->where('note', 'USG')->count();
                $sentObs = SatuSehatObservation::where('encounter_ihs', $encIhs)->where('local_id', 'like', "{$noRawat}-OBS_USG_%")->count();
                $sentImg = SatuSehatImagingStudy::where('local_id', 'like', "{$noRawat}-IMG_USG_%")->count();
                if ($sentSr < $expUsg)
                    $issues[] = "ServiceRequest USG: {$sentSr}/{$expUsg} terkirim";
                if ($sentDr < $expUsg)
                    $issues[] = "DiagnosticReport USG: {$sentDr}/{$expUsg} terkirim";
                if ($sentObs === 0)
                    $issues[] = "Observation USG: belum ada yang terkirim";
                if ($sentImg === 0)
                    $issues[] = "ImagingStudy USG: belum ada yang terkirim";
            }
        } catch (\Exception) {
        }

        return $issues;
    }

    public function sendCompositions(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        // Validasi: seluruh resource harus sudah terkirim sebelum Resume boleh dibuat
        $readinessIssues = $this->validateResumeReadiness($reg, $encounter);
        if (!empty($readinessIssues)) {
            $msg = 'Resume belum dapat dikirim — resource berikut belum lengkap: ' . implode('; ', $readinessIssues) . '.';
            $this->logBundleItem($bundle, 'Composition', "{$reg->no_rawat}-COMP_RESUME", 'warning', null, null, $msg);
            return ['success' => false, 'message' => $msg, 'count' => 0, 'items' => []];
        }

        $compositions = [];

        if ($reg->resumePasien) {
            $compositions[] = (object) [
                'idStr' => $reg->no_rawat . '-RESUME_RALAN-' . $reg->tgl_registrasi->format('Ymd') . '-' . str_replace(':', '', $reg->jam_reg),
                'judul' => 'Resume Medis Rawat Jalan',
                'tanggal' => $reg->resumePasien->tgl_periksa ?? $reg->tgl_registrasi,
                'type' => 'ralan',
            ];
        }

        if ($reg->resumePasienRanap) {
            $compositions[] = (object) [
                'idStr' => $reg->no_rawat . '-RESUME_RANAP-' . $reg->tgl_registrasi->format('Ymd') . '-' . str_replace(':', '', $reg->jam_reg),
                'judul' => 'Resume Medis Rawat Inap',
                'tanggal' => $reg->resumePasienRanap->tgl_periksa ?? $reg->tgl_registrasi,
                'type' => 'ranap',
            ];
        }

        if (empty($compositions)) {
            return ['success' => false, 'message' => 'Tidak ada data Resume untuk dikirim.'];
        }

        $service = new \App\Services\SatuSehat\Resources\CompositionService();
        $noRawat = $reg->no_rawat;
        $encIhs = $encounter->ihs_number;
        $patIhs = $encounter->patient_ihs;
        $authIhs = $encounter->practitioner_ihs;
        $nmPasien = $reg->pasien->nm_pasien ?? 'Pasien';

        // Kumpulkan IHS semua resource yang sudah terkirim untuk encounter ini
        $resourceRefs = $this->collectEncounterResourceRefs($noRawat, $encIhs, $patIhs);

        $sent = 0;
        $errors = [];
        $warnings = [];
        $items = [];

        foreach ($compositions as $comp) {
            if ($selectedIds !== null && !in_array($comp->idStr, $selectedIds))
                continue;

            if (
                \App\Models\SatuSehat\SatuSehatComposition::where('local_id', $comp->idStr)
                    ->where('encounter_ihs', $encIhs)->exists()
            )
                continue;

            $itemLabel = "Composition: {$comp->judul}";
            $sentAt = now()->toIso8601String();

            $dateIso = Carbon::parse($comp->tanggal)->toIso8601String();
            $identifier = $comp->idStr;
            try {
                if ($comp->type === 'ranap') {
                    $sentResp = $service->createResumeRanap(
                        patientId: $patIhs,
                        encounterId: $encIhs,
                        authorId: $authIhs,
                        patientName: $nmPasien,
                        resume: $reg->resumePasienRanap,
                        resourceRefs: $resourceRefs,
                        identifier: $identifier,
                        date: $dateIso,
                    );
                    $typeCode = '34105-7';
                    $typeDisplay = 'Hospital Discharge summary';
                } else {
                    $sentResp = $service->createResumeRalan(
                        patientId: $patIhs,
                        encounterId: $encIhs,
                        authorId: $authIhs,
                        patientName: $nmPasien,
                        resume: $reg->resumePasien,
                        resourceRefs: $resourceRefs,
                        identifier: $identifier,
                        date: $dateIso,
                    );
                    $typeCode = '88645-7';
                    $typeDisplay = 'Outpatient hospital Discharge summary';
                }
                $this->throttle();

                if (!$sentResp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'Composition', localId: $identifier, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                    $errors[] = "[COMP:{$comp->idStr}] " . $sentResp->error;
                    $items[] = ['id' => $comp->idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                    continue;
                }
                $this->logBundleItem(bundle: $bundle, type: 'Composition', localId: $identifier, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                \App\Models\SatuSehat\SatuSehatComposition::create([
                    'ihs_number' => $sentResp->resourceId,
                    'identifier' => json_encode($sentResp->data['identifier']),
                    'local_id' => $identifier,
                    'composition_type' => $comp->type === 'ranap'
                        ? \App\Models\SatuSehat\SatuSehatComposition::TYPE_RESUME_RANAP
                        : \App\Models\SatuSehat\SatuSehatComposition::TYPE_RESUME_RALAN,
                    'patient_ihs' => $patIhs,
                    'encounter_ihs' => $encIhs,
                    'author_ihs' => $authIhs,
                    'custodian_ihs' => SatuSehatOrganization::where('identifier', 'RS')->first()?->ihs_number,
                    'status' => 'final',
                    'type_code' => $typeCode,
                    'type_display' => $typeDisplay,
                    'title' => $comp->judul,
                    'date' => Carbon::parse($comp->tanggal)->format('Y-m-d H:i:s'),
                    'raw_response' => $sentResp->data,
                    'synced_at' => now(),
                ]);

                $items[] = ['id' => $comp->idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByEncounter($encIhs);
                    $found = $this->findByIdentifier($searchResp, $identifier);
                    if ($found) {
                        \App\Models\SatuSehat\SatuSehatComposition::create([
                            'ihs_number' => $found['id'],
                            'identifier' => json_encode($found['identifier'] ?? []),
                            'local_id' => $identifier,
                            'composition_type' => $comp->type === 'ranap'
                                ? \App\Models\SatuSehat\SatuSehatComposition::TYPE_RESUME_RANAP
                                : \App\Models\SatuSehat\SatuSehatComposition::TYPE_RESUME_RALAN,
                            'patient_ihs' => $patIhs,
                            'encounter_ihs' => $encIhs,
                            'author_ihs' => $authIhs,
                            'custodian_ihs' => SatuSehatOrganization::where('identifier', 'RS')->first()?->ihs_number,
                            'status' => 'final',
                            'type_code' => $typeCode,
                            'type_display' => $typeDisplay,
                            'title' => $comp->judul,
                            'date' => Carbon::parse($comp->tanggal)->format('Y-m-d H:i:s'),
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $comp->idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        continue;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'Composition', localId: $identifier, status: 'failed', error: $e->getMessage());
                $errors[] = "[COMP:{$comp->idStr}]: " . $e->getMessage();
                $items[] = ['id' => $comp->idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        }

        $msg = $sent > 0 ? "{$sent} Composition berhasil dikirim." : 'Semua Composition sudah dikirim.';
        $msg = $this->logAndFormatSummary($bundle, 'Composition', $msg, $errors, $warnings, '/\[COMP:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    /** Kumpulkan IHS semua resource yang sudah terkirim untuk satu encounter. */
    private function collectEncounterResourceRefs(string $noRawat, string $encIhs, string $patIhs): array
    {
        return [
            'conditions' => SatuSehatCondition::where('encounter_ihs', $encIhs)->pluck('ihs_number')->all(),
            'observations_ttv' => SatuSehatObservation::where('encounter_ihs', $encIhs)->where('category', 'vital-signs')->pluck('ihs_number')->all(),
            'procedures' => SatuSehatProcedure::where('encounter_ihs', $encIhs)->pluck('ihs_number')->all(),
            'allergies' => SatuSehatAllergyIntolerance::where('patient_ihs', $patIhs)->pluck('ihs_number')->all(),
            'med_requests' => SatuSehatMedicationRequest::where('encounter_ihs', $encIhs)->pluck('ihs_number')->all(),
            'med_dispenses' => SatuSehatMedicationDispense::where('encounter_ihs', $encIhs)->pluck('ihs_number')->all(),
            'med_admins' => SatuSehatMedicationAdministration::where('encounter_ihs', $encIhs)->pluck('ihs_number')->all(),
            'med_statements' => SatuSehatMedicationStatement::where('encounter_ihs', $encIhs)->pluck('ihs_number')->all(),
            'sr_lab' => SatuSehatServiceRequest::where('encounter_ihs', $encIhs)->where('note', 'LAB')->pluck('ihs_number')->all(),
            'sr_rad' => SatuSehatServiceRequest::where('encounter_ihs', $encIhs)->where('note', 'RAD')->pluck('ihs_number')->all(),
            'specimens' => SatuSehatSpecimen::where('local_id', 'like', "{$noRawat}%")->pluck('ihs_number')->all(),
            'imaging' => SatuSehatImagingStudy::where('encounter_ihs', $encIhs)->pluck('ihs_number')->all(),
            'dr_lab' => SatuSehatDiagnosticReport::where('encounter_ihs', $encIhs)->where('category', 'LAB')->pluck('ihs_number')->all(),
            'dr_rad' => SatuSehatDiagnosticReport::where('encounter_ihs', $encIhs)->where('category', 'RAD')->pluck('ihs_number')->all(),
            'clinical_imps' => SatuSehatClinicalImpression::where('encounter_ihs', $encIhs)->pluck('ihs_number')->all(),
            'care_plans' => SatuSehatCarePlan::where('encounter_ihs', $encIhs)->pluck('ihs_number')->all(),
        ];
    }

    public function sendAdimeGiziCompositions(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $adimes = CatatanAdimeGizi::where('no_rawat', $reg->no_rawat)->orderBy('tanggal')->get();

        if ($adimes->isEmpty()) {
            return ['success' => false, 'message' => 'Tidak ada Catatan Gizi ADIME untuk kunjungan ini.', 'items' => []];
        }

        $service = new \App\Services\SatuSehat\Resources\CompositionService();
        $sent = 0;
        $errors = [];
        $items = [];

        foreach ($adimes as $adime) {
            $idStr = "{$reg->no_rawat}-ADIME-{$adime->tanggal->format('Ymd')}-" . str_replace(':', '', $adime->jam ?? '000000');
            $identifier = $idStr; // samakan dengan local_id

            if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                continue;
            if (
                \App\Models\SatuSehat\SatuSehatComposition::where('local_id', $idStr)
                    ->where('encounter_ihs', $encounter->ihs_number)->exists()
            )
                continue;

            // Resolusi author: coba dari Pegawai (gizi), fallback ke practitioner encounter
            $authorIhs = $encounter->practitioner_ihs;
            if ($adime->nip) {
                $pegawai = Pegawai::find($adime->nip) ?? Pegawai::where('nik', $adime->nip)->first();
                if ($pegawai?->no_ktp) {
                    $p = SatuSehatPractitioner::findByNik($pegawai->no_ktp);
                    if ($p)
                        $authorIhs = $p->ihs_number;
                }
            }

            $itemLabel = "ADIME Gizi: " . ($adime->tanggal?->format('Y-m-d') ?? $idStr);
            $sentAt = now()->toIso8601String();

            try {
                $monev = trim(($adime->monitoring ?? '') . ' ' . ($adime->evaluasi ?? ''));
                $intervensi = trim(($adime->intervensi ?? '') . ($adime->instruksi ? "\n" . $adime->instruksi : ''));

                $resp = $service->createAdimeGiziComposition(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    authorId: $authorIhs,
                    asesmen: $adime->asesmen ?: '-',
                    diagnosaGizi: $adime->diagnosis ?: '-',
                    intervensi: $intervensi ?: '-',
                    monitoringEvaluasi: $monev ?: '-',
                    identifier: $identifier,
                    date: $adime->tanggal?->toIso8601String(),
                );
                $this->throttle();

                if (!$resp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'Composition', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $resp->data, error: $resp->error);
                    $errors[] = "[ADIME:{$idStr}] " . $resp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $resp->error, 'sent_at' => $sentAt];
                    continue;
                }
                $this->logBundleItem(bundle: $bundle, type: 'Composition', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $resp->data, ihsId: $resp->resourceId);

                \App\Models\SatuSehat\SatuSehatComposition::create([
                    'ihs_number' => $resp->resourceId,
                    'identifier' => json_encode($resp->data['identifier']),
                    'local_id' => $idStr,
                    'composition_type' => \App\Models\SatuSehat\SatuSehatComposition::TYPE_CATATAN_GIZI,
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'author_ihs' => $authorIhs,
                    'custodian_ihs' => SatuSehatOrganization::where('identifier', 'RS')->first()?->ihs_number,
                    'status' => 'final',
                    'type_code' => '18842-5',
                    'type_display' => 'Discharge summary',
                    'title' => 'Catatan Asuhan Gizi ADIME',
                    'date' => $adime->tanggal?->format('Y-m-d H:i:s'),
                    'raw_response' => $resp->data,
                    'synced_at' => now(),
                ]);
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByEncounter($encounter->ihs_number);
                    $found = $this->findByIdentifier($searchResp, $identifier);
                    if ($found) {
                        \App\Models\SatuSehat\SatuSehatComposition::create([
                            'ihs_number' => $found['id'],
                            'identifier' => $identifier,
                            'local_id' => $idStr,
                            'composition_type' => \App\Models\SatuSehat\SatuSehatComposition::TYPE_CATATAN_GIZI,
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'author_ihs' => $authorIhs,
                            'custodian_ihs' => SatuSehatOrganization::where('identifier', 'RS')->first()?->ihs_number,
                            'status' => 'final',
                            'type_code' => '18842-5',
                            'type_display' => 'Discharge summary',
                            'title' => 'Catatan Asuhan Gizi ADIME',
                            'date' => $adime->tanggal?->format('Y-m-d H:i:s'),
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        continue;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'Composition', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[ADIME:{$idStr}]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        }

        $msg = $sent > 0 ? "{$sent} Catatan Gizi ADIME berhasil dikirim." : 'Semua Catatan Gizi ADIME sudah dikirim.';
        $msg = $this->logAndFormatSummary($bundle, 'Composition', $msg, $errors, $warnings, '/\[ADIME:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    public function sendClinicalImpressions(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $pemeriksaans = $reg->status_lanjut === 'Ralan'
            ? \App\Models\Simrs\PemeriksaanRalan::where('no_rawat', $reg->no_rawat)->get()
            : \App\Models\Simrs\PemeriksaanRanap::where('no_rawat', $reg->no_rawat)->get();

        if ($pemeriksaans->isEmpty()) {
            return ['success' => false, 'message' => 'Tidak ada data Clinical Impression untuk dikirim.'];
        }

        $service = new \App\Services\SatuSehat\Resources\ClinicalImpressionService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        foreach ($pemeriksaans as $p) {
            $idStr = $reg->no_rawat . '-CLINICAL_IMPRESSION-' . ($p->tgl_perawatan ? $p->tgl_perawatan->format('Ymd') : '') . '-' . str_replace(':', '', $p->jam_rawat ?? '000000');
            if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                continue;
            if (\App\Models\SatuSehat\SatuSehatClinicalImpression::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                $this->logBundleItem($bundle, 'ClinicalImpression', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                continue;
            }

            $desc = collect([$p->keluhan, $p->penilaian, $p->tindak_lanjut])->filter()->implode('; ');
            if (empty($desc)) {
                $warnings[] = "[IMP:{$idStr}] Keluhan/Pemeriksaan fisik kosong, tidak dapat membuat Clinical Impression.";
                continue;
            }

            $itemLabel = "ClinImp: " . str_replace('|', ' ', $idStr);
            $sentAt = now()->toIso8601String();

            try {
                $sentResp = $service->createClinicalImpression(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    assessorId: $encounter->practitioner_ihs,
                    status: 'completed',
                    description: \Illuminate\Support\Str::limit($desc, 1500),
                    summary: \Illuminate\Support\Str::limit($p->penilaian ?? $p->keluhan, 200),
                    effectiveDateTime: $p->tgl_perawatan ? $p->tgl_perawatan->format('Y-m-d') . 'T' . ($p->jam_rawat ?? '00:00:00') . '+07:00' : now()->toIso8601String(),
                    identifier: $idStr
                );
                $this->throttle();

                if (!$sentResp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'ClinicalImpression', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                    $errors[] = "[IMP:{$idStr}] " . $sentResp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                    continue;
                }
                $this->logBundleItem(bundle: $bundle, type: 'ClinicalImpression', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                \App\Models\SatuSehat\SatuSehatClinicalImpression::create([
                    'ihs_number' => $sentResp->resourceId,
                    'identifier' => json_encode($sentResp->data['identifier']),
                    'local_id' => $idStr,
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'assessor_ihs' => $encounter->practitioner_ihs,
                    'status' => 'completed',
                    'description' => \Illuminate\Support\Str::limit($desc, 200),
                    'effective_datetime' => $p->tgl_perawatan ? $p->tgl_perawatan->format('Y-m-d') . ' ' . $p->jam_rawat : now(),
                    'raw_response' => $sentResp->data,
                    'synced_at' => now(),
                ]);
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByEncounter($encounter->ihs_number);
                    $found = $this->findByIdentifier($searchResp, $idStr);
                    if ($found) {
                        \App\Models\SatuSehat\SatuSehatClinicalImpression::create([
                            'ihs_number' => $found['id'],
                            'identifier' => json_encode($found['identifier'] ?? []),
                            'local_id' => $idStr,
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'assessor_ihs' => $encounter->practitioner_ihs,
                            'status' => 'completed',
                            'description' => \Illuminate\Support\Str::limit($desc, 200),
                            'effective_datetime' => $p->tgl_perawatan ? $p->tgl_perawatan->format('Y-m-d') . ' ' . $p->jam_rawat : now(),
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        continue;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'ClinicalImpression', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[IMP:{$idStr}]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        }

        $msg = $sent > 0 ? "{$sent} Clinical Impression berhasil dikirim." : 'Semua Clinical Impression sudah dikirim.';
        $msg = $this->logAndFormatSummary($bundle, 'ClinicalImpression', $msg, $errors, $warnings, '/\[IMP:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    public function sendAllergyIntolerances(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $alergiPasiens = \App\Models\Simrs\AlergiPasien::where('no_rawat_ref', $reg->no_rawat)
            ->with(['alergi', 'reaksi', 'tingkatKeparahan', 'kritisitas', 'pegawai'])
            ->get();

        if ($alergiPasiens->isEmpty()) {
            return ['success' => true, 'skipped' => true, 'count' => 0, 'message' => 'Tidak ada data Allergy Intolerance untuk kunjungan ini.', 'items' => []];
        }

        $allergyMaps = \App\Models\Mapping\AllergyMap::getCached();
        $reactionMaps = \App\Models\Mapping\AllergyReactionMap::getCached();
        $service = new \App\Services\SatuSehat\Resources\AllergyIntoleranceService();
        $practitionerCache = [];
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        foreach ($alergiPasiens as $a) {
            $rawTanggal = $a->getRawOriginal('tanggal');
            $idStr = $reg->no_rawat . '-AI_' . $a->id_alergi . '-' . $a->tanggal->format('Ymd') . '-' . str_replace(':', '', $a->jam ?? '000000');
            if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                continue;
            if (\App\Models\SatuSehat\SatuSehatAllergyIntolerance::where('local_id', $idStr)->where('patient_ihs', $encounter->patient_ihs)->exists()) {
                $this->logBundleItem($bundle, 'AllergyIntolerance', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                continue;
            }

            $allergyMap = $allergyMaps->get($a->id_alergi);
            if (!$allergyMap) {
                $warnings[] = "[ALERG:{$idStr} ] {$a->alergi->nama_alergi} #{$a->id_alergi} belum dipetakan ke SNOMED CT.";
                continue;
            }

            try {
                // Recorder dari pegawai pencatat alergi; fallback ke practitioner encounter
                $recorderIhs = $encounter->practitioner_ihs;
                if ($a->pegawai && $a->pegawai->no_ktp) {
                    $nik = $a->pegawai->no_ktp;
                    if (!array_key_exists($nik, $practitionerCache)) {
                        $practitionerCache[$nik] = SatuSehatPractitioner::findByNik($nik)?->ihs_number;
                    }
                    $recorderIhs = $practitionerCache[$nik] ?? $encounter->practitioner_ihs;
                }

                // Category dari kolom tipe di tabel alergi
                $category = $this->mapTipeToFhirCategory($a->alergi?->tipe);

                // Criticality dari tabel alergi_kritisitas
                $criticality = $this->mapKritisitasToFhir($a->kritisitas?->kritisitas);

                // Reaction: manifestation dari mapping + severity dari tingkat keparahan
                $reaction = null;
                if ($a->id_reaksi) {
                    $reactionMap = $reactionMaps->get($a->id_reaksi);
                    if ($reactionMap) {
                        $reactionEntry = [
                            'manifestation' => [
                                [
                                    'coding' => [
                                        [
                                            'system' => \App\Services\SatuSehat\FhirDictionary::SNOMED,
                                            'code' => $reactionMap->system_code,
                                            'display' => $reactionMap->system_display,
                                        ]
                                    ],
                                ]
                            ],
                        ];
                        $severity = $this->mapKeparahanToFhir($a->tingkatKeparahan?->keparahan);
                        if ($severity) {
                            $reactionEntry['severity'] = $severity;
                        }
                        $reaction = [$reactionEntry];
                    }
                }

                $onsetDateTime = $rawTanggal
                    ? $rawTanggal . 'T' . ($a->jam ?? '00:00:00') . '+07:00'
                    : now()->toIso8601String();

                $itemLabel = "Alergi: {$a->alergi->nama_alergi} #{$a->id_alergi}";
                $sentAt = now()->toIso8601String();

                $sentResp = $service->createAllergyIntolerance(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    code: $allergyMap->system_code,
                    display: $allergyMap->system_display,
                    clinicalStatus: 'active',
                    verificationStatus: 'confirmed',
                    category: $category,
                    criticality: $criticality,
                    recorderId: $recorderIhs,
                    onsetDateTime: $onsetDateTime,
                    reaction: $reaction,
                );
                $this->throttle();

                if (!$sentResp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'AllergyIntolerance', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                    $errors[] = "[ALERG:{$idStr} ] {$a->alergi->nama_alergi} #{$a->id_alergi}] " . $sentResp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                    continue;
                }
                $this->logBundleItem(bundle: $bundle, type: 'AllergyIntolerance', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                \App\Models\SatuSehat\SatuSehatAllergyIntolerance::create([
                    'ihs_number' => $sentResp->resourceId,
                    'local_id' => $idStr,
                    'identifier' => json_encode($sentResp->data['identifier']),
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'recorder_ihs' => $recorderIhs,
                    'clinical_status' => 'active',
                    'type' => 'allergy',
                    'category' => $category,
                    'criticality' => $criticality,
                    'code' => $allergyMap->system_code,
                    'code_display' => $allergyMap->system_display,
                    'raw_response' => $sentResp->data,
                    'synced_at' => now(),
                ]);
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                $itemLabel = "Alergi: " . ($allergyMap?->system_display ?? $allergyMap->system_code ?? $idStr);
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByPatient($encounter->patient_ihs);
                    $found = $this->findByIdentifier($searchResp, $idStr);
                    if ($found) {
                        \App\Models\SatuSehat\SatuSehatAllergyIntolerance::create([
                            'ihs_number' => $found['id'],
                            'local_id' => $idStr,
                            'identifier' => json_encode($found['identifier'] ?? []),
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'recorder_ihs' => $recorderIhs,
                            'clinical_status' => 'active',
                            'type' => 'allergy',
                            'category' => $category,
                            'criticality' => $criticality,
                            'code' => $allergyMap->system_code,
                            'code_display' => $allergyMap->system_display,
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => now()->toIso8601String()];
                        $sent++;
                        continue;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'AllergyIntolerance', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[ALERG:{$idStr}]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => now()->toIso8601String()];
            }
        }

        $msg = $sent > 0
            ? "{$sent} data alergi berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Alergi.' : 'Tidak ada Alergi yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'AllergyIntolerance', $msg, $errors, $warnings, '/\[ALERG:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    /** Map kolom tipe alergi SIMRS ke FHIR AllergyIntolerance category (food|medication|environment|biologic) */
    private function mapTipeToFhirCategory(?string $tipe): string
    {
        if (!$tipe)
            return 'environment';
        $val = strtolower($tipe);
        if (str_contains($val, 'obat') || str_contains($val, 'medik') || str_contains($val, 'medication'))
            return 'medication';
        if (str_contains($val, 'makanan') || str_contains($val, 'food'))
            return 'food';
        if (str_contains($val, 'biologi') || str_contains($val, 'biologic'))
            return 'biologic';
        return 'environment';
    }

    /** Map nilai keparahan SIMRS ke FHIR reaction.severity (mild|moderate|severe) */
    private function mapKeparahanToFhir(?string $keparahan): ?string
    {
        if (!$keparahan)
            return null;
        $val = strtolower($keparahan);
        if (str_contains($val, 'ringan'))
            return 'mild';
        if (str_contains($val, 'sedang'))
            return 'moderate';
        if (str_contains($val, 'berat') || str_contains($val, 'parah'))
            return 'severe';
        return null;
    }

    /** Map nilai kritisitas SIMRS ke FHIR criticality (low|high|unable-to-assess) */
    private function mapKritisitasToFhir(?string $kritisitas): string
    {
        if (!$kritisitas)
            return 'low';
        $val = strtolower($kritisitas);
        if (str_contains($val, 'tinggi') || str_contains($val, 'high'))
            return 'high';
        if (str_contains($val, 'tidak') || str_contains($val, 'unable'))
            return 'unable-to-assess';
        return 'low';
    }

    /**
     * Deteksi kode dan nama modality DICOM dari nama jenis perawatan radiologi.
     * Urutan pengecekan: yang paling spesifik didahulukan untuk menghindari false match.
     *
     * @return array{0: string, 1: string} [modalityCode, modalityDisplay]
     */
    private function detectModality(string $nmPerawatan, ?string $kdJenisPrw = null): array
    {
        $displayMap = [
            'CR' => 'Computed Radiography',
            'DX' => 'Digital Radiography',
            'XR' => 'X-ray (Radiografi Umum)',
            'MG' => 'Mammography',
            'CT' => 'Computed Tomography',
            'MR' => 'Magnetic Resonance Imaging',
            'US' => 'Ultrasonography',
            'NM' => 'Nuclear Medicine',
            'PT' => 'Positron Emission Tomography',
            'ST' => 'Single Photon Emission CT (SPECT)',
            'XA' => 'X-ray Angiography',
            'RF' => 'Radiofluoroscopy',
            'DS' => 'Digital Subtraction Angiography',
            'DXA' => 'Dual-energy X-ray Absorptiometry (DEXA)',
            'ECG' => 'Electrocardiography',
            'IVUS' => 'Intravascular Ultrasound',
            'OCT' => 'Optical Coherence Tomography',
            'OP' => 'Ophthalmic Photography',
            'ES' => 'Endoscopy',
            'RG' => 'Radiographic Imaging',
        ];

        if ($kdJenisPrw) {
            $mapped = \App\Models\Simrs\MappingRadiologiModality::where('kd_jenis_prw', $kdJenisPrw)->first();

            if ($mapped && isset($displayMap[$mapped->modality_code])) {
                return [$mapped->modality_code, $displayMap[$mapped->modality_code]];
            }
        }

        $nm = strtolower($nmPerawatan);

        $code = match (true) {
            // Paling spesifik — harus dicek sebelum turunannya
            str_contains($nm, 'dsa') || str_contains($nm, 'digital subtraction') => 'DS',
            str_contains($nm, 'dexa') || str_contains($nm, 'dxa') || str_contains($nm, 'densitometri') || str_contains($nm, 'bone density') => 'DXA',
            str_contains($nm, 'spect') || str_contains($nm, 'single photon') => 'ST',
            str_contains($nm, 'pet scan') || str_contains($nm, 'positron') => 'PT',
            str_contains($nm, 'ivus') || str_contains($nm, 'intravascular') => 'IVUS',
            str_contains($nm, 'oct ') || str_contains($nm, 'optical coherence') || str_contains($nm, 'retinografi') => 'OCT',
            str_contains($nm, 'mammografi') || str_contains($nm, 'mammography') || str_contains($nm, 'mammo') => 'MG',
            // CT — 'oct'/'spect'/'dsa' sudah ditangkap di atas
            str_contains($nm, 'ct scan') || str_contains($nm, 'computed tomography') || (str_contains($nm, ' ct') && !str_contains($nm, 'oct')) => 'CT',
            str_contains($nm, 'mri') || str_contains($nm, 'magnetic resonance') => 'MR',
            str_contains($nm, 'usg') || str_contains($nm, 'ultrasound') || str_contains($nm, 'ultrasonografi') || str_contains($nm, 'sonografi') || str_contains($nm, 'echo') => 'US',
            str_contains($nm, 'angiografi') || str_contains($nm, 'angiography') || str_contains($nm, 'angiogram') => 'XA',
            str_contains($nm, 'fluoroskopi') || str_contains($nm, 'fluoroscopy') || str_contains($nm, 'fluorosk') => 'RF',
            str_contains($nm, 'nuklir') || str_contains($nm, 'nuclear medicine') => 'NM',
            str_contains($nm, 'ekg') || str_contains($nm, 'ecg') || str_contains($nm, 'elektrokardiografi') => 'ECG',
            str_contains($nm, 'endoskopi') || str_contains($nm, 'endoscopy') || str_contains($nm, 'endoskop') => 'ES',
            str_contains($nm, 'fundus') || str_contains($nm, 'ophthalm') || str_contains($nm, 'foto mata') => 'OP',
            str_contains($nm, 'rontgen digital') || str_contains($nm, 'foto digital') || str_contains($nm, 'digital radiografi') => 'DX',
            str_contains($nm, 'foto polos') || str_contains($nm, 'rontgen') || str_contains($nm, 'x-ray') || str_contains($nm, 'xray') || str_contains($nm, 'plain') => 'XR',
            default => 'CR',
        };

        return [$code, $displayMap[$code] ?? $displayMap['CR']];
    }

    /**
     * Pengiriman imunisasi berdasarkan data riwayat imunisasi
     * Belum fix karena belum ada mapping terkait riwayat penggunaan vaksin untuk imunisasi
     */
    // public function sendImmunizationsFromHistory(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    // {
    //     try {
    //         $imunisasiData = \Illuminate\Support\Facades\DB::connection('simrs')->table('riwayat_imunisasi')->where('no_rkm_medis', $reg->no_rkm_medis ?? '')->get();
    //     } catch (\Exception $e) {
    //         return ['success' => true, 'skipped' => true, 'count' => 0, 'message' => 'Tabel riwayat_imunisasi tidak tersedia di SIMRS.', 'items' => []];
    //     }

    //     if ($imunisasiData->isEmpty()) {
    //         return ['success' => true, 'skipped' => true, 'count' => 0, 'message' => 'Tidak ada data Immunization untuk kunjungan ini.', 'items' => []];
    //     }

    //     $service = new \App\Services\SatuSehat\Resources\ImmunizationService();
    //     $sent = 0;
    //     $errors = [];
    //     $items = [];

    //     foreach ($imunisasiData as $i) {
    //         $idStr = $i->kode_imunisasi . '_' . str_replace('-', '', $i->tgl_imunisasi);
    //         if ($selectedIds !== null && !in_array($idStr, $selectedIds))
    //             continue;
    //         if (\App\Models\SatuSehat\SatuSehatImmunization::where('local_id', $idStr)->where('patient_ihs', $encounter->patient_ihs)->exists())
    //             continue;

    //         try {
    //             $nmImunisasi = \Illuminate\Support\Facades\DB::connection('simrs')->table('imunisasi')->where('kode_imunisasi', $i->kode_imunisasi)->value('nm_imunisasi');

    //             $itemLabel = "Imunisasi: " . ($nmImunisasi ?? $i->kode_imunisasi);
    //             $sentAt = now()->toIso8601String();

    //             $sentResp = $service->createImmunization(
    //                 patientId: $encounter->patient_ihs,
    //                 encounterId: $encounter->ihs_number,
    //                 vaccineCode: '1119349007', // Generic Vaccine product
    //                 vaccineDisplay: \Illuminate\Support\Str::limit($nmImunisasi ?? $i->kode_imunisasi, 100),
    //                 locationId: $encounter->location_ihs ?? config('services.satu-sehat.location_id', ''),
    //                 occurrenceDateTime: \Carbon\Carbon::parse($i->tgl_imunisasi)->toIso8601String(),
    //                 doseQuantity: 1,
    //                 doseUnit: 'ml',
    //                 performerId: $encounter->practitioner_ihs,
    //                 identifier: $idStr
    //             );
    //             $this->throttle();

    //             if (!$sentResp->success) {
    //                 $this->logBundleItem(bundle: $bundle, type: 'Immunization', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
    //                 $errors[] = "[IMUN:{$idStr}] " . $sentResp->error;
    //                 $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
    //                 continue;
    //             }
    //             $this->logBundleItem(bundle: $bundle, type: 'Immunization', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

    //             \App\Models\SatuSehat\SatuSehatImmunization::create([
    //                 'ihs_number' => $sentResp->resourceId,
    //                 'local_id' => $idStr,
    //                 'identifier' => json_encode($sentResp->data['identifier']),
    //                 'patient_ihs' => $encounter->patient_ihs,
    //                 'encounter_ihs' => $encounter->ihs_number,
    //                 'status' => 'completed',
    //                 'vaccine_code' => '1119349007',
    //                 'vaccine_display' => \Illuminate\Support\Str::limit($nmImunisasi ?? $i->kode_imunisasi, 100),
    //                 'occurrence_datetime' => \Carbon\Carbon::parse($i->tgl_imunisasi)->format('Y-m-d H:i:s'),
    //                 'dose_quantity' => 1,
    //                 'dose_unit' => 'ml',
    //                 'raw_response' => $sentResp->data,
    //                 'synced_at' => now(),
    //             ]);
    //             $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
    //             $sent++;
    //         } catch (\Exception $e) {
    //             if ($this->isDuplicate($e)) {
    //                 $searchResp = $service->searchByPatient($encounter->patient_ihs);
    //                 $found = $this->findByIdentifier($searchResp, $idStr);
    //                 if ($found) {
    //                     \App\Models\SatuSehat\SatuSehatImmunization::create([
    //                         'ihs_number' => $found['id'],
    //                         'local_id' => $idStr,
    //                         'identifier' => json_encode($found['identifier'] ?? []),
    //                         'patient_ihs' => $encounter->patient_ihs,
    //                         'encounter_ihs' => $encounter->ihs_number,
    //                         'status' => 'completed',
    //                         'vaccine_code' => '1119349007',
    //                         'vaccine_display' => \Illuminate\Support\Str::limit($nmImunisasi ?? $i->kode_imunisasi, 100),
    //                         'occurrence_datetime' => \Carbon\Carbon::parse($i->tgl_imunisasi)->format('Y-m-d H:i:s'),
    //                         'dose_quantity' => 1,
    //                         'dose_unit' => 'ml',
    //                         'raw_response' => $found,
    //                         'synced_at' => now(),
    //                     ]);
    //                     $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
    //                     $sent++;
    //                     continue;
    //                 }
    //             }
    //             $this->logBundleItem(bundle: $bundle, type: 'Immunization', localId: $idStr, status: 'failed', error: $e->getMessage());
    //             $errors[] = "[IMUN:{$idStr}]: " . $e->getMessage();
    //             $items[] = ['id' => $idStr, 'label' => "Imunisasi: {$i->kode_imunisasi}", 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => now()->toIso8601String()];
    //         }
    //     }

    //     $msg = $sent > 0 ? "{$sent} Immunization berhasil dikirim." : 'Semua Immunization sudah dikirim.';
    //     if (!empty($errors)) {
    //         $msg .= ' ' . count($errors) . ' gagal: ' . implode('; ', array_slice($errors, 0, 3));
    //     }

    //     return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    // }

    /**
     * Kirim Immunization vaksin dari DetailPemberianObat (nama_brng LIKE 'Vaksin%').
     * vaccineCode/vaccineDisplay dari MedicationMap (KFA), doseUnit dari numerator_code.
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public function sendImmunizations(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $vaksin = DetailPemberianObat::where('no_rawat', $reg->no_rawat)
            ->whereHas('dataBarang', fn($q) => $q->where('nama_brng', 'like', 'Vaksin%'))
            ->with(['dataBarang', 'dataBatch'])
            ->orderBy('tgl_perawatan')
            ->orderBy('jam')
            ->get();

        if ($vaksin->isEmpty()) {
            return ['success' => true, 'skipped' => true, 'count' => 0, 'message' => 'Tidak ada data vaksin pada kunjungan ini.', 'items' => []];
        }

        $locationId = $encounter->location_ihs ?? config('services.satu-sehat.location_id', '');
        if (!$locationId) {
            return ['success' => false, 'message' => 'Location IHS tidak tersedia di Encounter.', 'items' => []];
        }

        // Preload semua KFA mapping untuk kode_brng yang ada
        $medicationMaps = MedicationMap::whereIn('local_code', $vaksin->pluck('kode_brng')->unique())
            ->get()
            ->keyBy('local_code');

        $service = new \App\Services\SatuSehat\Resources\ImmunizationService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        foreach ($vaksin as $v) {
            $rawTanggal = $v->getRawOriginal('tgl_perawatan') ?? '';
            $idStr = $reg->no_rawat . '-IMM_' . $v->kode_brng . '-' . str_replace('-', '', $rawTanggal) . '-' . str_replace(':', '', $v->jam ?? '');

            if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                continue;

            if (\App\Models\SatuSehat\SatuSehatImmunization::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                $this->logBundleItem($bundle, 'Immunization', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                continue;
            }

            $medMap = $medicationMaps->get($v->kode_brng);
            if (!$medMap || !$medMap->kfa_code) {
                $warnings[] = "[VKO:{$v->kode_brng}] Belum ada mapping KFA — lakukan pemetaan di Local Mapping → Medication.";
                continue;
            }

            $itemLabel = "Vaksin: " . ($medMap->kfa_name ?? $v->kode_brng);
            $sentAt = now()->toIso8601String();

            try {
                $batch = $v->dataBatch;
                $occurrence = $rawTanggal ? $rawTanggal . 'T' . ($v->jam ?? '00:00:00') . '+07:00' : now()->toIso8601String();
                $doseUnit = $medMap->numerator_code ?: 'ml';
                $reasonCode = $medMap->immunization_reason_code;
                $reasonDisplay = $medMap->immunization_reason_name;
                $timingCode = $medMap->immunization_routine_timing_code ?: null;
                $timingDisplay = $medMap->immunization_routine_timing_name ?: null;

                $sentResp = $service->createImmunization(
                    patientId: $encounter->patient_ihs,
                    vaccineCode: $medMap->kfa_code,
                    vaccineDisplay: Str::limit($medMap->kfa_name, 100),
                    performerId: $encounter->practitioner_ihs,
                    locationId: $locationId,
                    encounterId: $encounter->ihs_number,
                    occurrenceDateTime: $occurrence,
                    lotNumber: $v->no_batch ?: null,
                    expirationDate: $batch?->tgl_kadaluarsa?->format('Y-m-d'),
                    doseQuantity: $v->jml > 0 ? (float) $v->jml : null,
                    reasonCode: $reasonCode,
                    reasonDisplay: $reasonDisplay,
                    timingCode: $timingCode,
                    timingDisplay: $timingDisplay,
                );
                $this->throttle();

                if (!$sentResp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'Immunization', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                    $errors[] = "[VKO:{$idStr}] " . $sentResp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                    continue;
                }
                $this->logBundleItem(bundle: $bundle, type: 'Immunization', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                \App\Models\SatuSehat\SatuSehatImmunization::create([
                    'ihs_number' => $sentResp->resourceId,
                    'local_id' => $idStr,
                    'identifier' => json_encode($sentResp->data['identifier']),
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'performer_ihs' => $encounter->practitioner_ihs,
                    'location_ihs' => $locationId,
                    'status' => 'completed',
                    'vaccine_code' => $medMap->kfa_code,
                    'vaccine_display' => Str::limit($medMap->kfa_name, 100),
                    'occurrence_datetime' => $rawTanggal ? $rawTanggal . ' ' . ($v->jam ?? '00:00:00') : now(),
                    'lot_number' => $v->no_batch ?: null,
                    'expiration_date' => $batch?->tgl_kadaluarsa?->format('Y-m-d'),
                    'dose_quantity' => $v->jml > 0 ? (float) $v->jml : null,
                    'dose_unit' => $doseUnit,
                    'reason_code' => $reasonCode,
                    'raw_response' => $sentResp->data,
                    'synced_at' => now(),
                ]);
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByPatient($encounter->patient_ihs);
                    $found = $this->findByIdentifier($searchResp, $idStr);
                    if ($found) {
                        \App\Models\SatuSehat\SatuSehatImmunization::create([
                            'ihs_number' => $found['id'],
                            'local_id' => $idStr,
                            'identifier' => json_encode($found['identifier'] ?? []),
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'performer_ihs' => $encounter->practitioner_ihs,
                            'location_ihs' => $locationId,
                            'status' => 'completed',
                            'vaccine_code' => $medMap->kfa_code,
                            'vaccine_display' => Str::limit($medMap->kfa_name, 100),
                            'occurrence_datetime' => $rawTanggal ? $rawTanggal . ' ' . ($v->jam ?? '00:00:00') : now(),
                            'lot_number' => $v->no_batch ?: null,
                            'expiration_date' => $batch?->tgl_kadaluarsa?->format('Y-m-d'),
                            'dose_quantity' => $v->jml > 0 ? (float) $v->jml : null,
                            'dose_unit' => $doseUnit,
                            'reason_code' => $reasonCode,
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        continue;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'Immunization', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[VKO:{$idStr}]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        }

        $msg = $sent > 0
            ? "{$sent} Vaksin berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Vaksin.' : 'Tidak ada Vaksin yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'Immunization', $msg, $errors, $warnings, '/\[VKO:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    /**
     * Kirim Medication Statement ke Satu Sehat.
     * Sumber data: DetailPemberianObat (ranap/ralan) + ResepPulang (ranap).
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public function sendMedicationStatements(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $obats = DetailPemberianObat::where('no_rawat', $reg->no_rawat)
            ->with(['dataBarang', 'aturanPakai'])
            ->orderBy('tgl_perawatan')
            ->orderBy('jam')
            ->get();

        $resepPulangs = collect();
        if ($reg->status_lanjut === 'Ranap') {
            $resepPulangs = ResepPulang::where('no_rawat', $reg->no_rawat)
                ->with(['dataBarang'])
                ->orderBy('tanggal')
                ->orderBy('jam')
                ->get();
        }

        if ($obats->isEmpty() && $resepPulangs->isEmpty()) {
            return ['success' => true, 'skipped' => true, 'count' => 0, 'message' => 'Tidak ada data pemberian/resep obat untuk dikirim Statement.', 'items' => []];
        }

        // Validator: MedicationRequest harus sudah dikirim terlebih dahulu
        if (!SatuSehatMedicationRequest::where('encounter_ihs', $encounter->ihs_number)->exists()) {
            return ['success' => false, 'count' => 0, 'message' => 'MedicationRequest belum dikirim. Kirim Medication Request terlebih dahulu sebelum Statement.', 'errors' => ['MedicationRequest belum ada untuk encounter ini.'], 'items' => []];
        }

        $service = new \App\Services\SatuSehat\Resources\MedicationStatementService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];
        $category = $reg->status_lanjut === 'Ranap' ? 'inpatient' : 'outpatient';
        $catDisplay = $reg->status_lanjut === 'Ranap' ? 'Inpatient' : 'Outpatient';

        $process = function ($localCode, $namaObat, $tgl, $jam, $aturan, $jml) use ($reg, $encounter, $service, $category, $catDisplay, &$sent, &$errors, &$items, $selectedIds, $bundle) {
            if (!$localCode)
                return;

            $tglCarbon = $tgl instanceof \Carbon\Carbon ? $tgl : ($tgl ? \Carbon\Carbon::parse($tgl) : now());
            $idStr = $reg->no_rawat . '-MED_STMT_' . $localCode . '-' . $tglCarbon->format('Ymd') . '-' . str_replace(':', '', $jam ?? '');
            $requestId = $reg->no_rawat . '-MED_REQ_' . $localCode . '-' . $tglCarbon->format('Ymd') . '-' . str_replace(':', '', $jam ?? '');

            if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                return;
            if (SatuSehatMedicationStatement::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                $this->logBundleItem($bundle, 'MedicationStatement', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                return;
            }

            // Cari Medication IHS dari MedicationRequest atau Medication lokal
            $map = \App\Models\Mapping\MedicationMap::where('local_code', $localCode)->first();
            if (!$map || !$map->kfa_code) {
                $warnings[] = "[STMT:{$localCode}] Belum ada mapping Medication (KFA).";
                return;
            }

            $request = SatuSehatMedicationRequest::where('local_id', $requestId)->first();
            if (!$request) {
                $warnings[] = "[STMT:{$localCode}] MedicationRequest belum dikirim.";
                return;
            }

            $medication = \App\Models\SatuSehat\SatuSehatMedication::where('kfa_code', $map->kfa_code)->first();
            if (!$medication) {
                $warnings[] = "[STMT:{$localCode}] Resource Medication (KFA) belum tersedia di lokal.";
                return;
            }

            // Hitung frekuensi dari aturan pakai (default 1)
            $frequency = 1;
            if (is_string($aturan) && preg_match('/(\d+)/', $aturan, $m)) {
                $frequency = (int) $m[1];
            }

            $effectiveDateTime = $tglCarbon->format('Y-m-d') . 'T' . ltrim($jam ?? '00:00:00') . '+07:00';
            $dateAsserted = $effectiveDateTime;

            $itemLabel = "Med.Stmt: {$localCode}";
            $sentAt = now()->toIso8601String();

            try {
                $resp = $service->createMedicationStatement(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    medicationId: $medication->ihs_number,
                    medicationDisplay: $namaObat ?? $localCode,
                    categoryCode: $category,
                    categoryDisplay: $catDisplay,
                    dosageText: is_string($aturan) ? $aturan : ($aturan->aturan ?? '-'),
                    dosageFrequency: $frequency,
                    dosagePeriod: 1,
                    dosagePeriodUnit: 'h',
                    effectiveDateTime: $effectiveDateTime,
                    dateAsserted: $dateAsserted,
                    identifier: $idStr
                );
                $this->throttle();

                if (!$resp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'MedicationStatement', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $resp->data, error: $resp->error);
                    $errors[] = "[STMT:{$localCode}] " . $resp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $resp->error, 'sent_at' => $sentAt];
                    return;
                }
                $this->logBundleItem(bundle: $bundle, type: 'MedicationStatement', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $resp->data, ihsId: $resp->resourceId);

                SatuSehatMedicationStatement::create([
                    'ihs_number' => $resp->resourceId,
                    'local_id' => $idStr,
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'medication_ihs' => $medication->ihs_number,
                    'status' => 'completed',
                    'category' => $category,
                    'dosage_text' => is_string($aturan) ? Str::limit($aturan, 100) : Str::limit($aturan->aturan ?? '-', 100),
                    'effective_datetime' => $tglCarbon->format('Y-m-d H:i:s'),
                    'raw_response' => $resp->data,
                    'synced_at' => now(),
                ]);
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchBySubject($encounter->patient_ihs);
                    $found = $this->findByIdentifier($searchResp, $idStr);
                    if ($found) {
                        SatuSehatMedicationStatement::create([
                            'ihs_number' => $found['id'],
                            'local_id' => $idStr,
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'medication_ihs' => $medication->ihs_number,
                            'status' => 'completed',
                            'category' => $category,
                            'dosage_text' => is_string($aturan) ? Str::limit($aturan, 100) : Str::limit($aturan->aturan ?? '-', 100),
                            'effective_datetime' => $tglCarbon->format('Y-m-d H:i:s'),
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        return;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'MedicationStatement', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[STMT:{$localCode}]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        };

        foreach ($obats as $o) {
            $process($o->kode_brng, $o->dataBarang?->nama_brng, $o->tgl_perawatan, $o->jam, $o->aturanPakai?->aturan ?? $o->kd_aturan, $o->jml);
        }
        foreach ($resepPulangs as $rp) {
            $process($rp->kode_brng, $rp->dataBarang?->nama_brng, $rp->tanggal, $rp->jam, $rp->aturan, $rp->jml_barang);
        }

        $msg = $sent > 0
            ? "{$sent} Riwayat Obat berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Riwayat Obat.' : 'Tidak ada Riwayat Obat yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'MedicationStatement', $msg, $errors, $warnings, '/\[STMT:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    /**
     * Kirim Care Plan (Instruksi Medik) ke Satu Sehat.
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public function sendCarePlans(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $isRanap = $reg->status_lanjut === 'Ranap';
        $pemeriksaans = $isRanap
            ? PemeriksaanRanap::where('no_rawat', $reg->no_rawat)->where('instruksi', '!=', '')->orderBy('tgl_perawatan')->orderBy('jam_rawat')->get()
            : PemeriksaanRalan::where('no_rawat', $reg->no_rawat)->where('instruksi', '!=', '')->orderBy('tgl_perawatan')->orderBy('jam_rawat')->get();

        if ($pemeriksaans->isEmpty()) {
            return ['success' => false, 'message' => 'Tidak ada data Instruksi Medik untuk dikirim sebagai Care Plan.'];
        }

        $service = new CarePlanService();
        $sent = 0;
        $errors = [];
        $items = [];

        foreach ($pemeriksaans as $p) {
            $tgl = $p->tgl_perawatan instanceof Carbon ? $p->tgl_perawatan->format('Y-m-d') : $p->tgl_perawatan;
            $idStr = $reg->no_rawat . '-CARE_PLAN-' . str_replace('-', '', $tgl) . '-' . str_replace(':', '', $p->jam_rawat ?? '000000');

            if ($selectedIds !== null && !in_array($idStr, $selectedIds)) {
                continue;
            }

            if (SatuSehatCarePlan::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {

                $this->logBundleItem($bundle, 'CarePlan', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');

                continue;
            }

            // Praktisi pencatat instruksi
            $praktisiIhs = $encounter->practitioner_ihs;
            if ($p->nip) {
                $pegawai = Pegawai::where('id', $p->nip)->orWhere('nik', $p->nip)->first();
                if ($pegawai && $pegawai->no_ktp) {
                    $petugas = SatuSehatPractitioner::findByNik($pegawai->no_ktp);
                    if ($petugas) {
                        $praktisiIhs = $petugas->ihs_number;
                    }
                }
            }

            $categoryCode = $isRanap ? '736353004' : '736271009';
            $categoryDisplay = $isRanap ? 'Inpatient care plan' : 'Outpatient care plan';
            $itemLabel = "CarePlan: " . str_replace('|', ' ', $idStr);

            try {
                $sentAt = now()->toIso8601String();

                $sentResp = $service->createCarePlan(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    authorId: $praktisiIhs,
                    title: 'Instruksi Medik dan Keperawatan Pasien',
                    categoryCode: $categoryCode,
                    categoryDisplay: $categoryDisplay,
                    description: str_replace("\t", " ", str_replace(["\r\n", "\r", "\n"], "<br>", $p->instruksi)),
                    identifier: $idStr
                );
                $this->throttle();

                if (!$sentResp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'CarePlan', localId: $idStr, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                    $errors[] = "[CARE:{$idStr}] " . $sentResp->error;
                    $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                    continue;
                }
                $this->logBundleItem(bundle: $bundle, type: 'CarePlan', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                SatuSehatCarePlan::create([
                    'ihs_number' => $sentResp->resourceId,
                    'identifier' => json_encode($sentResp->data['identifier']),
                    'local_id' => $idStr,
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'author_ihs' => $praktisiIhs,
                    'status' => 'active',
                    'intent' => 'plan',
                    'title' => 'Instruksi Medik dan Keperawatan Pasien',
                    'category_code' => $categoryCode,
                    'category_display' => $categoryDisplay,
                    'description' => $p->instruksi,
                    'created' => now(),
                    'raw_response' => $sentResp->data,
                    'synced_at' => now(),
                ]);
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                $itemLabel = "CarePlan: " . str_replace('|', ' ', $idStr);
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByEncounter($encounter->ihs_number);
                    $found = $this->findByIdentifier($searchResp, $idStr);
                    if ($found) {
                        SatuSehatCarePlan::create([
                            'ihs_number' => $found['id'],
                            'identifier' => json_encode($found['identifier'] ?? []),
                            'local_id' => $idStr,
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'author_ihs' => $praktisiIhs,
                            'status' => 'active',
                            'intent' => 'plan',
                            'title' => 'Instruksi Medik dan Keperawatan Pasien',
                            'category_code' => $categoryCode,
                            'category_display' => $categoryDisplay,
                            'description' => $p->instruksi,
                            'created' => now(),
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => now()->toIso8601String()];
                        $sent++;
                        continue;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'CarePlan', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[CARE:{$idStr}]: " . $e->getMessage();
                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => now()->toIso8601String()];
            }
        }

        $msg = $sent > 0
            ? "{$sent} Care Plan berhasil dikirim."
            : (empty($items) ? 'Tidak ada data Care Plan.' : 'Tidak ada Care Plan yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'CarePlan', $msg, $errors, $warnings, '/\[CARE:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $items];
    }

    /**
     * Kirim QuestionnaireResponse Telaah Farmasi (Questionnaire Q0007) ke Satu Sehat.
     *
     * @param array|null $selectedIds Array no_resep yang dipilih; null = kirim semua
     */
    public function sendQuestionnaireResponses(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $conn = DB::connection('simrs');

        // Ambil data telaah_farmasi melalui join dengan tabel resep. Coba beberapa nama tabel umum SIMRS.
        $telaahData = collect();
        foreach (['nota_resep' => 'no_nota', 'resep_obat' => 'no_resep'] as $joinTable => $joinKey) {
            try {
                $telaahData = $conn->table('telaah_farmasi as tf')
                    ->join("{$joinTable} as r", 'tf.no_resep', '=', "r.{$joinKey}")
                    ->where('r.no_rawat', $reg->no_rawat)
                    ->select('tf.*', 'r.tgl_peresepan as tgl_telaah', 'r.jam as jam_telaah')
                    ->get();
                break;
            } catch (\Exception $e) {
                // Coba tabel berikutnya
            }
        }

        if ($telaahData->isEmpty()) {
            return ['success' => false, 'message' => 'Tidak ada data Telaah Farmasi untuk kunjungan ini.', 'items' => []];
        }

        $service = new QuestionnaireResponseService();
        $sent = 0;
        $errors = [];
        $sentItems = [];

        $patientDisplay = $reg->pasien?->nm_pasien ?? '';

        foreach ($telaahData as $tf) {
            $noResep = $tf->no_resep;
            $idStr = $reg->no_rawat . '-QR_' . $noResep . '-' . ($tf->tgl_telaah ? Carbon::parse($tf->tgl_telaah)->format('Ymd') : '') . '-' . str_replace(':', '', $tf->jam_telaah ?? '');

            if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                continue;

            if (SatuSehatQuestionnaireResponse::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                $this->logBundleItem($bundle, 'QuestionnaireResponse', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                continue;
            }

            // Lookup author via NIP pegawai
            $authorIhs = $encounter->practitioner_ihs;
            $authorDisplay = '';
            if (!empty($tf->nip)) {
                $pegawai = Pegawai::where('id', $tf->nip)->orWhere('nik', $tf->nip)->first();
                if ($pegawai?->no_ktp) {
                    $practitioner = SatuSehatPractitioner::findByNik($pegawai->no_ktp);
                    if ($practitioner) {
                        $authorIhs = $practitioner->ihs_number;
                        $authorDisplay = $pegawai->nama ?? '';
                    }
                }
            }

            $authored = $encounter->period_start
                ? Carbon::parse($encounter->period_start)->format('Y-m-d\TH:i:sP')
                : now()->format('Y-m-d\TH:i:sP');
            if (!empty($tf->tgl_telaah)) {
                $jam = $tf->jam_telaah ?? '00:00:00';
                $authored = Carbon::parse($tf->tgl_telaah)->format('Y-m-d') . 'T' . $jam . '+07:00';
            }

            $items = [
                [
                    'linkId' => '1',
                    'text' => 'Telaah Resep',
                    'item' => [
                        ['linkId' => '1.1', 'text' => 'Tepat Identifikasi Pasien ?', 'answer' => [['valueBoolean' => (bool) $tf->resep_identifikasi_pasien]]],
                        ['linkId' => '1.2', 'text' => 'Tepat Obat ?', 'answer' => [['valueBoolean' => (bool) $tf->resep_tepat_obat]]],
                        ['linkId' => '1.3', 'text' => 'Tepat Dosis ?', 'answer' => [['valueBoolean' => (bool) $tf->resep_tepat_dosis]]],
                        ['linkId' => '1.4', 'text' => 'Tepat Cara Pemberian ?', 'answer' => [['valueBoolean' => (bool) $tf->resep_tepat_cara_pemberian]]],
                        ['linkId' => '1.5', 'text' => 'Tepat Waktu Pemberian ?', 'answer' => [['valueBoolean' => (bool) $tf->resep_tepat_waktu_pemberian]]],
                        ['linkId' => '1.6', 'text' => 'Ada Tidak Duplikasi Obat ?', 'answer' => [['valueBoolean' => (bool) $tf->resep_ada_tidak_duplikasi_obat]]],
                        ['linkId' => '1.7', 'text' => 'Interaksi Obat ?', 'answer' => [['valueBoolean' => (bool) $tf->resep_interaksi_obat]]],
                        ['linkId' => '1.8', 'text' => 'Kontra Indikasi Obat ?', 'answer' => [['valueBoolean' => (bool) $tf->resep_kontra_indikasi_obat]]],
                    ],
                ],
                [
                    'linkId' => '2',
                    'text' => 'Telaah Obat',
                    'item' => [
                        ['linkId' => '2.1', 'text' => 'Tepat Pasien ?', 'answer' => [['valueBoolean' => (bool) $tf->obat_tepat_pasien]]],
                        ['linkId' => '2.2', 'text' => 'Tepat Obat ?', 'answer' => [['valueBoolean' => (bool) $tf->obat_tepat_obat]]],
                        ['linkId' => '2.3', 'text' => 'Tepat Dosis ?', 'answer' => [['valueBoolean' => (bool) $tf->obat_tepat_dosis]]],
                        ['linkId' => '2.4', 'text' => 'Tepat Cara Pemberian ?', 'answer' => [['valueBoolean' => (bool) $tf->obat_tepat_cara_pemberian]]],
                        ['linkId' => '2.5', 'text' => 'Tepat Waktu Pemberian ?', 'answer' => [['valueBoolean' => (bool) $tf->obat_tepat_waktu_pemberian]]],
                    ],
                ],
            ];

            $itemLabel = "Telaah Farmasi: {$noResep}";
            $sentAt = now()->toIso8601String();

            try {
                $sentResp = $service->createResponse(
                    questionnaire: 'https://fhir.kemkes.go.id/Questionnaire/Q0007',
                    patientId: $encounter->patient_ihs,
                    patientDisplay: $patientDisplay,
                    encounterId: $encounter->ihs_number,
                    authored: $authored,
                    authorId: $authorIhs,
                    authorDisplay: $authorDisplay,
                    items: $items,
                );
                $this->throttle();

                if (!$sentResp->success) {
                    $this->logBundleItem(bundle: $bundle, type: 'QuestionnaireResponse', localId: $noResep, status: 'failed', payload: $service->getLastPayload(), response: $sentResp->data, error: $sentResp->error);
                    $errors[] = "[QR:{$noResep}] " . $sentResp->error;
                    $sentItems[] = ['id' => $noResep, 'label' => $itemLabel, 'status' => 'fail', 'message' => $sentResp->error, 'sent_at' => $sentAt];
                    continue;
                }
                $this->logBundleItem(bundle: $bundle, type: 'QuestionnaireResponse', localId: $idStr, status: 'success', payload: $service->getLastPayload(), response: $sentResp->data, ihsId: $sentResp->resourceId);

                SatuSehatQuestionnaireResponse::create([
                    'ihs_number' => $sentResp->resourceId,
                    'identifier' => json_encode($sentResp->data['identifier']),
                    'local_id' => $idStr,
                    'type' => 'telaah_farmasi',
                    'questionnaire' => 'https://fhir.kemkes.go.id/Questionnaire/Q0007',
                    'status' => 'completed',
                    'patient_ihs' => $encounter->patient_ihs,
                    'encounter_ihs' => $encounter->ihs_number,
                    'author_ihs' => $authorIhs,
                    'authored' => Carbon::parse($authored),
                    'raw_response' => $sentResp->data,
                    'synced_at' => now(),
                ]);
                $sentItems[] = ['id' => $noResep, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                $sent++;
            } catch (\Exception $e) {
                if ($this->isDuplicate($e)) {
                    $searchResp = $service->searchByPatient($encounter->patient_ihs);
                    $found = $this->findByIdentifier($searchResp, $noResep);
                    if ($found) {
                        SatuSehatQuestionnaireResponse::create([
                            'ihs_number' => $found['id'],
                            'identifier' => $noResep,
                            'local_id' => $noResep,
                            'type' => 'telaah_farmasi',
                            'questionnaire' => 'https://fhir.kemkes.go.id/Questionnaire/Q0007',
                            'status' => 'completed',
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'author_ihs' => $authorIhs,
                            'authored' => Carbon::parse($authored),
                            'raw_response' => $found,
                            'synced_at' => now(),
                        ]);
                        $sentItems[] = ['id' => $noResep, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Sudah ada di Satu Sehat (duplikat disinkronkan).', 'sent_at' => $sentAt];
                        $sent++;
                        continue;
                    }
                }
                $this->logBundleItem(bundle: $bundle, type: 'QuestionnaireResponse', localId: $idStr, status: 'failed', error: $e->getMessage());
                $errors[] = "[QR:{$idStr}] " . $e->getMessage();
                $sentItems[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        }

        $msg = $sent > 0
            ? "{$sent} Questionnaire Response berhasil dikirim."
            : (empty($sentItems) ? 'Tidak ada data kuesioner.' : 'Tidak ada kuesioner yang dikirim.');
        $msg = $this->logAndFormatSummary($bundle, 'QuestionnaireResponse', $msg, $errors, [], '/\[QR:([^\]]+)\]/');

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent, 'items' => $sentItems];
    }

    /**
     * Kirim semua resource FHIR satu encounter secara berurutan (best-effort),
     * lalu tutup Encounter dengan status 'finished'.
     */
    public function sendAllBundle(RegPeriksa $reg, SatuSehatBundle $bundle): array
    {
        set_time_limit(0); // Unlimited time for background process

        // Jika belum ada log, buatkan log baru
        if (!$bundle) {
            $bundle = SatuSehatBundle::create([
                'no_rawat' => $reg->no_rawat,
                'status' => SatuSehatBundle::STATUS_RUNNING,
                'triggered_by' => auth()->id(),
                'started_at' => now(),
            ]);
        } else {
            // Jika sudah ada (biasanya dipassing dari Job dengan status 'queued'), update ke 'running'
            $bundle->update([
                'status' => SatuSehatBundle::STATUS_RUNNING,
                'started_at' => now()
            ]);
        }

        // Validasi prasyarat sebelum mulai
        $warnings = $this->validateBundlePrerequisites($reg);
        foreach ($warnings as $warning) {
            $this->logBundleItem($bundle, 'Prerequisite', null, 'warning', null, null, $warning);
        }

        $encounter = \App\Models\SatuSehat\SatuSehatEncounter::where('local_id', $reg->no_rawat)->first();

        // Kirim Encounter jika belum ada
        if (!$encounter) {
            $encResult = $this->sendEncounter($reg, 'arrived', $bundle);
            if (!$encResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Gagal mengirim Encounter: ' . $encResult['message'],
                    'results' => [],
                    'warnings' => $warnings,
                    'errors' => ['[Encounter] ' . $encResult['message']],
                    'finished' => false,
                ];
            }
            $encounter = \App\Models\SatuSehat\SatuSehatEncounter::where('local_id', $reg->no_rawat)->first();
        }

        if (!$encounter) {
            return ['success' => false, 'message' => 'Encounter tidak ditemukan setelah pengiriman.', 'results' => [], 'warnings' => $warnings, 'errors' => [], 'finished' => false];
        }

        $results = [];
        $allErrors = [];

        $steps = [
            'Condition' => fn() => $this->sendConditions($reg, $encounter, null, $bundle),
            'Procedure' => fn() => $this->sendProcedures($reg, $encounter, null, $bundle),
            // 'Surgery' => fn() => $this->sendSurgeries($reg, $encounter, null, $bundle),
            'Observation TTV' => fn() => $this->sendObservations($reg, $encounter, null, $bundle),
            'ServiceRequest Rad' => fn() => $this->sendRadServiceRequests($reg, $encounter, null, $bundle),
            'ServiceRequest Usg' => fn() => $this->sendUsgServiceRequests($reg, $encounter, null, $bundle),
            'ImagingStudy' => fn() => $this->sendImagingStudies($reg, $encounter, null, $bundle),
            'ServiceRequest Lab' => fn() => $this->sendLabServiceRequests($reg, $encounter, null, $bundle),
            'Specimen Lab' => fn() => $this->sendLabSpecimens($reg, $encounter, null, $bundle),
            // 'Specimen Rad' => fn() => $this->sendRadSpecimens($reg, $encounter, null, $bundle),
            'Observation Lab' => fn() => $this->sendLabObservations($reg, $encounter, null, $bundle),
            'DiagnosticReport Lab' => fn() => $this->sendLabDiagnosticReports($reg, $encounter, null, $bundle),
            'AllergyIntolerance' => fn() => $this->sendAllergyIntolerances($reg, $encounter, null, $bundle),
            'ClinicalImpression' => fn() => $this->sendClinicalImpressions($reg, $encounter, null, $bundle),
            'CarePlan' => fn() => $this->sendCarePlans($reg, $encounter, null, $bundle),
            'QuestionnaireResponse' => fn() => $this->sendQuestionnaireResponses($reg, $encounter, null, $bundle),

            // DiagnosticReport Rad dan Observation Rad tidak dikirimkan dikarenakan menunggu trigger dari webhook ImagingStudy
            // 'DiagnosticReport Rad' => fn() => $this->sendRadDiagnosticReports($reg, $encounter, null, $bundle),
            // 'Observation Rad' => fn() => $this->sendRadObservations($reg, $encounter, null, $bundle),

            'MedicationRequest' => fn() => $this->sendMedicationRequests($reg, $encounter, null, $bundle),
            'MedicationDispense' => fn() => $this->sendMedicationDispenses($reg, $encounter, null, $bundle),
            'MedicationStatement' => fn() => $this->sendMedicationStatements($reg, $encounter, null, $bundle),
            'MedicationAdministration' => fn() => $this->sendMedicationAdministrations($reg, $encounter, null, $bundle),

            'Immunization' => fn() => $this->sendImmunizations($reg, $encounter, null, $bundle),
            'Composition' => fn() => $this->sendCompositions($reg, $encounter, null, $bundle),
            'Catatan Gizi ADIME' => fn() => $this->sendAdimeGiziCompositions($reg, $encounter, null, $bundle),
            'Medication Composition' => fn() => $this->sendMedicationCompositions($reg, $encounter, $bundle),
        ];

        foreach ($steps as $label => $fn) {
            $stepStart = now();
            try {
                $res = $fn();
                $stepEnd = now();
                $count = $res['count'] ?? 0;
                $stepErrors = $res['errors'] ?? [];
                $isSkipped = ($res['skipped'] ?? false)
                    || ($count === 0 && empty($stepErrors) && ($res['success'] ?? false));
                // WARN: ada yang berhasil dan ada yang gagal dalam satu step
                $isPartial = !$isSkipped && $count > 0 && !empty($stepErrors);

                $results[$label] = [
                    'success' => $res['success'] ?? false,
                    'skipped' => $isSkipped,
                    'partial' => $isPartial,
                    'count' => $count,
                    'message' => $res['message'] ?? '',
                    'errors' => $stepErrors,
                    'items' => $res['items'] ?? [],
                    'started_at' => $stepStart->toIso8601String(),
                    'ended_at' => $stepEnd->toIso8601String(),
                    'duration_ms' => $stepStart->diffInMilliseconds($stepEnd),
                ];

                foreach ($stepErrors as $err) {
                    $allErrors[] = "[{$label}] {$err}";
                }
            } catch (\Throwable $e) {
                $stepEnd = now();
                $results[$label] = [
                    'success' => false,
                    'skipped' => false,
                    'partial' => false,
                    'count' => 0,
                    'message' => $e->getMessage(),
                    'errors' => [$e->getMessage()],
                    'items' => [],
                    'started_at' => $stepStart->toIso8601String(),
                    'ended_at' => $stepEnd->toIso8601String(),
                    'duration_ms' => $stepStart->diffInMilliseconds($stepEnd),
                ];
                $allErrors[] = "[{$label}] " . $e->getMessage();
            }
        }

        // Tutup encounter di akhir
        $finished = false;
        if ($encounter->status !== 'finished') {
            try {
                $finishResult = $this->updateEncounterStatus($reg->no_rawat, 'finished');
                $finished = $finishResult['success'] ?? false;
                if (!$finished) {
                    $allErrors[] = '[Finish Encounter] ' . ($finishResult['message'] ?? 'Gagal mengubah status Encounter.');
                }
            } catch (\Throwable $e) {
                $allErrors[] = '[Finish Encounter] ' . $e->getMessage();
            }
        } else {
            $finished = true;
        }

        $totalSent = collect($results)->sum('count');
        $failedSteps = collect($results)->filter(fn($r) => !$r['success'] && !($r['skipped'] ?? false))->count();

        return [
            'success' => $failedSteps === 0 && $finished,
            'message' => "{$totalSent} resource terkirim dari " . \count($steps) . " step. Encounter " . ($finished ? 'berhasil diselesaikan.' : 'gagal diselesaikan.'),
            'results' => $results,
            'warnings' => $warnings,
            'errors' => $allErrors,
            'finished' => $finished,
        ];
    }

    /** Memastikan ada bundle untuk pencatatan detail */
    private function ensureBundleLog(string $noRawat, ?SatuSehatBundle $bundle = null): SatuSehatBundle
    {
        if ($bundle)
            return $bundle;

        $bundle = SatuSehatBundle::where('no_rawat', $noRawat)->latest()->first();

        if (!$bundle) {
            $bundle = SatuSehatBundle::create([
                'no_rawat' => $noRawat,
                'status' => SatuSehatBundle::STATUS_RUNNING,
                'triggered_by' => auth()->id(),
                'started_at' => now(),
            ]);
        }

        return $bundle;
    }

    /** Log detail pengiriman per resource — tulis ke bundle_items (current state) + bundle_logs (audit trail).
     *  Status 'skipped': hanya audit trail, bundle_items tidak diubah. */
    private function logBundleItem(?SatuSehatBundle $bundle, string $type, ?string $localId, string $status, ?array $payload = null, ?array $response = null, ?string $error = null, ?string $ihsId = null): void
    {
        if (!$bundle)
            return;

        // Setiap pengiriman aktif → tandai bundle sebagai running
        if ($status !== 'skipped' && $bundle->status !== SatuSehatBundle::STATUS_RUNNING) {
            $bundle->update(['status' => SatuSehatBundle::STATUS_RUNNING, 'started_at' => $bundle->started_at ?? now()]);
            $bundle->refresh();
        }

        // Current state per-resource — skip jika resource sudah ada (skipped)
        if ($status !== 'skipped') {
            \App\Models\SatuSehat\SatuSehatBundleItem::updateOrCreate(
                ['bundle_log_id' => $bundle->id, 'resource_type' => $type, 'local_id' => $localId],
                ['bundle_log_id' => $bundle->id, 'resource_type' => $type, 'ihs_id' => $ihsId, 'status' => $status, 'payload' => $payload, 'response' => $response, 'error_message' => $error]
            );
        }

        // Audit trail — setiap aksi dicatat sebagai baris baru
        SatuSehatBundleLog::create([
            'bundle_id' => $bundle->id,
            'resource_type' => $type,
            'local_id' => $localId,
            'ihs_id' => $ihsId,
            'status' => $status,
            'payload' => $payload,
            'response' => $response,
            'error_message' => $error,
        ]);

        // Evaluasi ulang status bundle setelah item tersimpan
        if ($status !== 'skipped') {
            $this->checkAndUpdateBundleStatus($bundle);
        }
    }

    /** Evaluasi status bundle berdasarkan seluruh item yang sudah dikirim */
    private function checkAndUpdateBundleStatus(SatuSehatBundle $bundle): void
    {
        $query = $bundle->items()->where('status', '!=', 'skipped');
        $total = $query->count();
        $success = (clone $query)->where('status', 'success')->count();
        $errors = (clone $query)->where('status', 'failed')->count();

        if ($total === 0)
            return;

        $status = $success >= $total
            ? SatuSehatBundle::STATUS_COMPLETED
            : SatuSehatBundle::STATUS_PARTIAL;

        $bundle->update([
            'status' => $status,
            'total_sent' => $success,
            'total_errors' => $errors,
            'completed_at' => now(),
        ]);
    }

    /** Validasi prasyarat sebelum bundle dikirim — kembalikan daftar warning */
    private function validateBundlePrerequisites(RegPeriksa $reg): array
    {
        $reg->loadMissing(['pasien', 'dokter.pegawai', 'poliklinik']);
        $warnings = [];

        $nik = $reg->pasien?->no_ktp;
        if (!$nik) {
            $warnings[] = '[Prasyarat] NIK pasien tidak ditemukan di SIMRS.';
        } elseif (!SatuSehatPatient::findByNik($nik)) {
            $warnings[] = '[Prasyarat] Pasien (NIK: ' . $nik . ') belum terdaftar di Satu Sehat. Sinkronkan pasien terlebih dahulu.';
        }

        $praktNik = $reg->dokter?->pegawai?->no_ktp;
        if (!$praktNik) {
            $warnings[] = '[Prasyarat] NIK dokter DPJP tidak ditemukan.';
        } elseif (!SatuSehatPractitioner::findByNik($praktNik)) {
            $warnings[] = '[Prasyarat] Dokter DPJP (NIK: ' . $praktNik . ') belum terdaftar di Satu Sehat.';
        }

        if (!SatuSehatLocation::where('identifier', $reg->kd_poli)->exists()) {
            $warnings[] = '[Prasyarat] Lokasi poliklinik "' . $reg->kd_poli . '" belum terdaftar di Satu Sehat.';
        }

        if (!config('satusehat.organization_id')) {
            $warnings[] = '[Prasyarat] Organization ID Satu Sehat belum dikonfigurasi.';
        }

        return $warnings;
    }

    public function sendUsgDiagnosticReports(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $usgConfigs = \App\Services\UsgService::getUsgTypeConfigs();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        $serviceDR = new \App\Services\SatuSehat\Resources\DiagnosticReportService();

        foreach ($usgConfigs as $key => $cfg) {
            try {
                $data = $cfg['model']::where('no_rawat', $reg->no_rawat)->get();
                foreach ($data as $item) {
                    try {
                        $noOrder = $item->noorder ?? strtoupper($key);
                        $tglUSG = \Carbon\Carbon::parse($item->tanggal);
                        $jamUSG = $item->jam ?? $tglUSG->format('H:i:s');
                        $idStr = $reg->no_rawat . '-DR_USG_' . $noOrder . '-' . $tglUSG->format('Ymd') . '-' . str_replace(':', '', $jamUSG);

                        if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                            continue;
                        if (\App\Models\SatuSehat\SatuSehatDiagnosticReport::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                            $this->logBundleItem($bundle, 'DiagnosticReport', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                            continue;
                        }

                        $itemLabel = "DiagnosticReport USG: " . strtoupper($key) . ($noOrder ? " ({$noOrder})" : "");
                        $sentAt = now()->toIso8601String();

                        // 1. Validator: ServiceRequest (USG) harus sudah ada
                        $sr = null;
                        if ($noOrder) {
                            $sr = SatuSehatServiceRequest::where('encounter_ihs', $encounter->ihs_number)
                                ->where('note', 'USG')
                                ->where('local_id', 'like', "%{$noOrder}%")
                                ->first();
                        }
                        if (!$sr) {
                            $warnings[] = "[DR-USG:{$key}] ServiceRequest USG belum dikirim.";
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => 'ServiceRequest USG belum dikirim.', 'sent_at' => $sentAt];
                            continue;
                        }

                        // 2. Validator: ImagingStudy (USG) harus sudah ada
                        $isIdStr = str_replace('DR_USG_', 'IMG_USG_', $idStr);
                        $imagingStudy = \App\Models\SatuSehat\SatuSehatImagingStudy::where('local_id', $isIdStr)->first();
                        if (!$imagingStudy) {
                            $warnings[] = "[DR-USG:{$key}] ImagingStudy USG belum dikirim.";
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => 'ImagingStudy USG belum dikirim.', 'sent_at' => $sentAt];
                            continue;
                        }

                        // 3. Kirim Observation USG (jika belum ada)
                        $obsIdStr = str_replace('DR_USG_', 'OBS_USG_', $idStr);
                        $observation = \App\Models\SatuSehat\SatuSehatObservation::where('local_id', $obsIdStr)->first();
                        if (!$observation) {
                            $obsRes = $this->sendUsgObservations($reg, $encounter, [$obsIdStr], $bundle);
                            if (!$obsRes['success'] || empty($obsRes['count'])) {
                                $errors[] = "[OBS-USG:{$key}] Gagal mengirim Observation USG.";
                                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => 'Gagal kirim observasi.', 'sent_at' => $sentAt];
                                continue;
                            }
                            $observation = \App\Models\SatuSehat\SatuSehatObservation::where('local_id', $obsIdStr)->first();
                        }

                        $loinc = $this->getUsgLoincCode($key);
                        $effectiveDateTime = $tglUSG->format('Y-m-d') . 'T' . $jamUSG . '+07:00';

                        try {
                            $response = $serviceDR->createRadiologyReport(
                                patientId: $encounter->patient_ihs,
                                encounterId: $encounter->ihs_number,
                                performerId: $encounter->practitioner_ihs,
                                code: $loinc['code'],
                                display: $loinc['display'],
                                observationIds: [$observation->ihs_number],
                                identifier: $noOrder ?: $idStr,
                                serviceRequestId: $sr->ihs_number,
                                conclusion: $item->kesimpulan ?: 'Hasil Pemeriksaan USG',
                                imagingStudyIds: [$imagingStudy->ihs_number],
                                effectiveDateTime: $effectiveDateTime,
                                category: 'RAD',
                                categoryDisplay: 'Radiology'
                            );

                            if ($response->success) {
                                \App\Models\SatuSehat\SatuSehatDiagnosticReport::create([
                                    'ihs_number' => $response->resourceId,
                                    'local_id' => $idStr,
                                    'patient_ihs' => $encounter->patient_ihs,
                                    'encounter_ihs' => $encounter->ihs_number,
                                    'status' => 'final',
                                    'category' => 'RAD',
                                    'code' => $loinc['code'],
                                    'code_display' => $loinc['display'],
                                    'conclusion' => \Illuminate\Support\Str::limit($item->kesimpulan ?: 'Hasil Pemeriksaan USG', 200),
                                    'effective_datetime' => $tglUSG->format('Y-m-d') . ' ' . $jamUSG,
                                    'raw_response' => $response->data,
                                    'synced_at' => now(),
                                ]);
                                $sent++;
                                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'ok', 'message' => '', 'sent_at' => $sentAt];
                                $this->logBundleItem(bundle: $bundle, type: 'DiagnosticReport USG', localId: $idStr, status: 'success', ihsId: $response->resourceId);
                            } else {
                                $errors[] = "[USG:{$key}] " . $response->error;
                                $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $response->error, 'sent_at' => $sentAt];
                                $this->logBundleItem(bundle: $bundle, type: 'DiagnosticReport USG', localId: $idStr, status: 'failed', error: $response->error);
                            }
                            $this->throttle();
                        } catch (\Exception $e) {
                            $errors[] = "[USG:{$key}] " . $e->getMessage();
                            $items[] = ['id' => $idStr, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
                        }
                    } catch (\Throwable $e) {
                        $noOrder ??= '?';
                        $this->logBundleItem($bundle, 'DiagnosticReport USG', $idStr ?? null, 'failed', null, null, $e->getMessage());
                        $errors[] = "[DR-USG:{$key}] " . $e->getMessage();
                        $items[] = ['id' => $idStr ?? '', 'label' => "DR-USG: {$key}", 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => now()->toIso8601String()];
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "[USG:{$key}] DB error: " . $e->getMessage();
            }
        }

        $msg = $sent > 0 ? "{$sent} DiagnosticReport USG berhasil dikirim." : 'Tidak ada DiagnosticReport USG yang dikirim.';
        $msg = $this->logAndFormatSummary($bundle, 'DiagnosticReport USG', $msg, $errors, $warnings);

        return ['success' => $sent > 0 || empty($errors), 'count' => $sent, 'message' => $msg, 'errors' => $errors, 'warnings' => $warnings, 'items' => $items];
    }

    public function sendUsgObservations(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $usgConfigs = \App\Services\UsgService::getUsgTypeConfigs();
        $sent = 0;
        $errors = [];
        $items = [];

        $obsService = new \App\Services\SatuSehat\Resources\ObservationService();

        foreach ($usgConfigs as $key => $cfg) {
            try {
                $data = $cfg['model']::where('no_rawat', $reg->no_rawat)->get();
                foreach ($data as $item) {
                    try {
                        $noOrder = $item->noorder ?? strtoupper($key);
                        $tglUSG = \Carbon\Carbon::parse($item->tanggal);
                        $jamUSG = $item->jam ?? $tglUSG->format('H:i:s');
                        $idStr = $reg->no_rawat . '-OBS_USG_' . $noOrder . '-' . $tglUSG->format('Ymd') . '-' . str_replace(':', '', $jamUSG);

                        if ($selectedIds !== null && !in_array($idStr, $selectedIds))
                            continue;
                        if (\App\Models\SatuSehat\SatuSehatObservation::where('local_id', $idStr)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                            $this->logBundleItem($bundle, 'Observation', $idStr, 'skipped', null, null, 'Sudah tersinkronisasi.');
                            continue;
                        }

                        // Prasyarat: ImagingStudy USG harus sudah terkirim
                        $imgIdStr = str_replace('-OBS_USG_', '-IMG_USG_', $idStr);
                        $imagingStudy = \App\Models\SatuSehat\SatuSehatImagingStudy::where('local_id', $imgIdStr)->first();
                        if (!$imagingStudy) {
                            $errors[] = "[OBS-USG:{$key}] ImagingStudy belum dikirim untuk noorder {$noOrder}.";
                            continue;
                        }

                        $loinc = $this->getUsgLoincCode($key);
                        $effectiveDateTime = $tglUSG->format('Y-m-d') . 'T' . $jamUSG . '+07:00';

                        // Gabungkan semua fields menjadi satu string hasil untuk observation value
                        $results = [];
                        foreach ($cfg['fields'] as $field => $label) {
                            if (!empty($item->{$field})) {
                                $results[] = "{$label}: " . $item->{$field};
                            }
                        }
                        $valueString = implode("\n", $results) ?: 'Hasil Pemeriksaan USG';

                        try {
                            $response = $obsService->createRadiologyObservation(
                                patientId: $encounter->patient_ihs,
                                encounterId: $encounter->ihs_number,
                                performerId: $encounter->practitioner_ihs,
                                code: $loinc['code'],
                                display: $loinc['display'],
                                valueString: $valueString,
                                effectiveDateTime: $effectiveDateTime,
                                derivedFromId: $imagingStudy->ihs_number,
                            );

                            \App\Models\SatuSehat\SatuSehatObservation::create([
                                'ihs_number' => $response->resourceId,
                                'local_id' => $idStr,
                                'patient_ihs' => $encounter->patient_ihs,
                                'encounter_ihs' => $encounter->ihs_number,
                                'status' => 'final',
                                'category' => 'imaging',
                                'code' => $loinc['code'],
                                'code_display' => $loinc['display'],
                                'value_string' => \Illuminate\Support\Str::limit($valueString, 500),
                                'effective_datetime' => $tglUSG->format('Y-m-d') . ' ' . $jamUSG,
                                'raw_response' => $response->data,
                                'synced_at' => now(),
                            ]);
                            $sent++;
                            $this->logBundleItem(bundle: $bundle, type: 'Observation USG', localId: $idStr, status: 'success', ihsId: $response->resourceId);
                            $this->throttle();
                        } catch (\Exception $e) {
                            if ($this->isDuplicate($e)) {
                                $searchResp = $obsService->searchByEncounter($encounter->ihs_number);
                                $found = $this->findByIdentifier($searchResp, $loinc['code']);

                                if ($found) {
                                    \App\Models\SatuSehat\SatuSehatObservation::create([
                                        'ihs_number' => $found['id'],
                                        'local_id' => $idStr,
                                        'patient_ihs' => $encounter->patient_ihs,
                                        'encounter_ihs' => $encounter->ihs_number,
                                        'status' => 'final',
                                        'category' => 'imaging',
                                        'code' => $loinc['code'],
                                        'code_display' => $loinc['display'],
                                        'value_string' => \Illuminate\Support\Str::limit($valueString, 500),
                                        'effective_datetime' => $tglUSG->format('Y-m-d') . ' ' . $jamUSG,
                                        'raw_response' => $found,
                                        'synced_at' => now(),
                                    ]);
                                    $sent++;
                                    $this->logBundleItem(bundle: $bundle, type: 'Observation USG', localId: $idStr, status: 'success', ihsId: $found['id']);
                                    continue;
                                }
                            }
                            $errors[] = "[OBS-USG:{$key}] " . $e->getMessage();
                            $this->logBundleItem(bundle: $bundle, type: 'Observation USG', localId: $idStr, status: 'failed', error: $e->getMessage());
                        }
                    } catch (\Throwable $e) {
                        $noOrder ??= '?';
                        $this->logBundleItem($bundle, 'Observation USG', $idStr ?? null, 'failed', null, null, $e->getMessage());
                        $errors[] = "[OBS-USG:{$key}] " . $e->getMessage();
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "[USG:{$key}] DB error: " . $e->getMessage();
            }
        }

        $msg = $sent > 0 ? "{$sent} Observation USG berhasil dikirim." : 'Tidak ada Observation USG yang dikirim.';
        $msg = $this->logAndFormatSummary($bundle, 'Observation USG', $msg, $errors, []);

        return ['success' => $sent > 0 || empty($errors), 'count' => $sent, 'errors' => $errors, 'items' => $items, 'message' => $msg];
    }

    private function getUsgLoincCode(string $type): array
    {
        return match ($type) {
            'obstetri' => ['code' => '11525-3', 'display' => 'US Obstetric Study'],
            'abdomen' => ['code' => '11524-6', 'display' => 'US Abdomen Study'],
            'thyroid' => ['code' => '11526-1', 'display' => 'US Thyroid Study'],
            'scrotum' => ['code' => '11521-2', 'display' => 'US Scrotum Study'],
            'mamma' => ['code' => '11520-4', 'display' => 'US Breast Study'],
            'urologi' => ['code' => '11523-8', 'display' => 'US Pelvis Study'],
            default => ['code' => '43501-6', 'display' => 'US General Study'],
        };
    }

    /** Format ringkasan pesan sinkronisasi. Setiap item sudah dicatat via logBundleItem di dalam loop. */
    private function logAndFormatSummary(?SatuSehatBundle $bundle, string $resourceType, string $msg, array $errors, array $warnings, ?string $errorRegex = null): string
    {
        if (!empty($errors)) {
            $msg .= ' ' . count($errors) . ' gagal: ' . implode('; ', array_slice($errors, 0, 3)) . (count($errors) > 3 ? '...' : '');
        }
        if (!empty($warnings)) {
            $msg .= ' ' . count($warnings) . ' perlu diperhatikan: ' . implode('; ', array_slice($warnings, 0, 3));
        }

        return $msg;
    }

    /** Validasi Encounter sebelum sinkronisasi resource terkait. */
    private function validateEncounter(?SatuSehatEncounter $encounter): ?array
    {
        if (!$encounter?->ihs_number) {
            return [
                'success' => false,
                'message' => 'Encounter belum dikirim. Kirim Encounter terlebih dahulu.',
                'errors' => ['IHS Encounter tidak ditemukan.'],
                'count' => 0,
                'items' => []
            ];
        }
        return null;
    }

    /**
     * Kirim data Operasi ke Satu Sehat sebagai Procedure.
     *
     * @return array{success: bool, message: string, count?: int}
     */
    public function sendSurgeries(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $bundle = $this->ensureBundleLog($reg->no_rawat, $bundle);

        $operasis = Operasi::where('no_rawat', $reg->no_rawat)->with(['paket', 'operator1Dr', 'operator2Dr', 'operator3Dr', 'asistenOperator1Dr', 'dokterAnestesiDr'])->get();

        if ($operasis->isEmpty()) {
            return ['success' => false, 'message' => 'Tidak ada data operasi untuk kunjungan ini.'];
        }

        $service = new ProcedureService();
        $sent = 0;
        $warnings = [];
        $errors = [];
        $items = [];

        foreach ($operasis as $op) {
            $tglOp = $op->tgl_operasi instanceof \Carbon\Carbon ? $op->tgl_operasi : \Carbon\Carbon::parse($op->tgl_operasi);
            $localId = $reg->no_rawat . '-SURGERY-' . $op->kode_paket . '-' . $tglOp->format('YmdHis');

            if ($selectedIds !== null && !in_array($localId, $selectedIds)) {
                continue;
            }
            if (SatuSehatProcedure::where('local_id', $localId)->where('encounter_ihs', $encounter->ihs_number)->exists()) {
                $this->logBundleItem($bundle, 'Procedure', $localId, 'skipped', null, null, 'Sudah tersinkronisasi.');
                continue;
            }

            $itemLabel = "Operasi: " . ($op->paket?->nm_perawatan ?? 'Paket ' . $op->kode_paket);
            $sentAt = now()->toIso8601String();

            // Mapping SNOMED (Procedure)
            $mapping = ProcedureMap::where('source_table', 'operasi')->where('procedure_code', $op->kode_paket)->first();
            $system = $mapping?->system_display ?? FhirDictionary::SNOMED;
            $code = $mapping?->system_code ?? '387713003'; // Default SNOMED surgery if not mapped
            $display = $mapping?->system_term ?? $op->paket?->nm_perawatan ?? 'Surgical procedure';

            // Practitioners
            $performers = [];
            $practitioners = [
                ['id' => $op->operator1, 'role' => 'operator'],
                ['id' => $op->operator2, 'role' => 'operator'],
                ['id' => $op->operator3, 'role' => 'operator'],
                ['id' => $op->asisten_operator1, 'role' => 'assistant'],
                ['id' => $op->dokter_anestesi, 'role' => 'anesthetist'],
            ];

            foreach ($practitioners as $p) {
                if ($p['id'] && $p['id'] !== '-') {
                    $ssPractitioner = SatuSehatPractitioner::where('nik', $p['id'])->orWhere('local_id', $p['id'])->first();
                    if ($ssPractitioner) {
                        $performers[] = [
                            'actor' => ['reference' => "Practitioner/{$ssPractitioner->ihs_number}"],
                        ];
                    }
                }
            }

            if (empty($performers)) {
                $performers[] = ['actor' => ['reference' => "Practitioner/{$encounter->practitioner_ihs}"]];
            }

            try {
                $response = $service->createGenericProcedure(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    performers: $performers,
                    system: $system,
                    code: $code,
                    display: $display,
                    performedDateTime: $tglOp->format('Y-m-d') . 'T' . ($op->jam_mulai ?? '00:00:00') . '+07:00',
                    note: "Operasi {$op->kategori} - {$op->jenis_anasthesi}"
                );

                $this->logBundleItem(
                    bundle: $bundle,
                    type: 'Procedure',
                    localId: $localId,
                    status: $response->success ? 'success' : 'failed',
                    payload: $service->getLastPayload(),
                    response: $response->data,
                    error: $response->error,
                    ihsId: $response->resourceId
                );

                if ($response->success) {
                    SatuSehatProcedure::updateOrCreate(
                        ['local_id' => $localId],
                        [
                            'ihs_number' => $response->resourceId,
                            'identifier' => $op->kode_paket,
                            'patient_ihs' => $encounter->patient_ihs,
                            'encounter_ihs' => $encounter->ihs_number,
                            'performer_ihs' => $performers[0]['actor']['reference'] ?? $encounter->practitioner_ihs,
                            'status' => 'completed',
                            'code' => $code,
                            'code_display' => $display,
                            'raw_response' => $response->data,
                            'synced_at' => now(),
                        ]
                    );
                    $sent++;
                    $items[] = ['id' => $localId, 'label' => $itemLabel, 'status' => 'ok', 'message' => 'Berhasil dikirim.', 'sent_at' => $sentAt];
                } else {
                    $errors[] = "Surgery {$op->kode_paket}: " . $response->error;
                    $items[] = ['id' => $localId, 'label' => $itemLabel, 'status' => 'fail', 'message' => $response->error, 'sent_at' => $sentAt];
                }

                $this->throttle();
            } catch (\Exception $e) {
                $errors[] = "Surgery {$op->kode_paket}: " . $e->getMessage();
                $items[] = ['id' => $localId, 'label' => $itemLabel, 'status' => 'fail', 'message' => $e->getMessage(), 'sent_at' => $sentAt];
            }
        }

        $msg = $sent > 0 ? "{$sent} Data Operasi (Procedure) berhasil dikirim." : "Gagal mengirim data operasi.";
        $msg = $this->logAndFormatSummary($bundle, 'Procedure', $msg, $errors, $warnings);

        // Setelah Procedure selesai, kirim Composition (Laporan Operasi)
        $compResult = $this->sendSurgicalCompositions($reg, $encounter, $selectedIds, $bundle);
        if ($compResult['count'] > 0) {
            $msg .= " " . $compResult['message'];
        }

        return ['success' => $sent > 0 || empty($errors), 'message' => $msg, 'count' => $sent + $compResult['count'], 'items' => array_merge($items, $compResult['items'])];
    }

    /**
     * Kirim Laporan Operasi ke Satu Sehat sebagai Composition.
     *
     * @return array{success: bool, message: string, count: int, items: array}
     */
    public function sendSurgicalCompositions(RegPeriksa $reg, SatuSehatEncounter $encounter, ?array $selectedIds = null, ?SatuSehatBundle $bundle = null): array
    {
        $operasis = Operasi::where('no_rawat', $reg->no_rawat)->get();
        if ($operasis->isEmpty())
            return ['success' => false, 'message' => '', 'count' => 0, 'items' => []];

        $service = new CompositionService();
        $sent = 0;
        $errors = [];
        $items = [];

        foreach ($operasis as $op) {
            $tglOp = $op->tgl_operasi instanceof \Carbon\Carbon ? $op->tgl_operasi : \Carbon\Carbon::parse($op->tgl_operasi);
            $localId = $reg->no_rawat . '-SURGERY-NOTE-' . $op->kode_paket . '-' . $tglOp->format('YmdHis');
            $procedureLocalId = $reg->no_rawat . '-SURGERY-' . $op->kode_paket . '-' . $tglOp->format('YmdHis');

            if ($selectedIds !== null && !in_array($procedureLocalId, $selectedIds))
                continue;

            // Cek apakah Procedure sudah terkirim (wajib ada entry di Composition)
            $ssProcedure = SatuSehatProcedure::where('local_id', $procedureLocalId)->first();
            if (!$ssProcedure?->ihs_number) {
                $errors[] = "Laporan {$op->kode_paket} dilewati: Procedure belum sinkron.";
                continue;
            }

            // Ambil Laporan dari SIMRS (laporan_operasi)
            $laporan = \Illuminate\Support\Facades\DB::connection('simrs')->table('laporan_operasi')
                ->where('no_rawat', $reg->no_rawat)
                ->where('tgl_operasi', $op->tgl_operasi)
                // ->where('jam_operasi', $op->jam_mulai) // Jika perlu spesifik jam
                ->first();

            $isiLaporan = $laporan->laporan_operasi ?? 'Tidak ada detail laporan operasi di SIMRS.';

            // Mapping LOINC (Composition Type)
            $noteMap = SurgeryNoteMap::where('procedure_code', $op->kode_paket)->first();
            $typeCode = $noteMap?->loinc_code ?? '11504-8'; // Surgical operation note
            $typeDisplay = $noteMap?->loinc_term ?? 'Surgical operation note';

            $authorIhs = $ssProcedure->performer_ihs ? str_replace('Practitioner/', '', $ssProcedure->performer_ihs) : $encounter->practitioner_ihs;

            try {
                $response = $service->createComposition(
                    patientId: $encounter->patient_ihs,
                    encounterId: $encounter->ihs_number,
                    authorId: $authorIhs,
                    typeCode: $typeCode,
                    typeDisplay: $typeDisplay,
                    title: "Laporan Operasi: " . ($op->paket?->nm_perawatan ?? $op->kode_paket),
                    sections: [
                        $service->buildSection(
                            code: $typeCode,
                            display: $typeDisplay,
                            text: $isiLaporan,
                            entries: ["Procedure/{$ssProcedure->ihs_number}"]
                        )
                    ],
                    identifier: $localId,
                    date: $tglOp->toIso8601String()
                );

                $this->logBundleItem($bundle, 'Composition', $localId, $response->success ? 'success' : 'failed', $service->getLastPayload(), $response->data, $response->error, $response->resourceId);

                if ($response->success) {
                    $sent++;
                    $items[] = ['id' => $localId, 'label' => "Laporan Operasi: " . $op->kode_paket, 'status' => 'ok', 'message' => 'Berhasil dikirim.', 'sent_at' => now()->toIso8601String()];
                } else {
                    $errors[] = "Composition {$op->kode_paket}: " . $response->error;
                }
                $this->throttle();
            } catch (\Exception $e) {
                $errors[] = "Composition {$op->kode_paket}: " . $e->getMessage();
            }
        }

        return [
            'success' => $sent > 0,
            'message' => $sent > 0 ? "{$sent} Laporan Operasi berhasil dikirim." : "",
            'count' => $sent,
            'items' => $items
        ];
    }

    // =========================================================================
    // Medication Composition (TK000013 / resume_farmasi)
    // =========================================================================

    public function sendMedicationCompositions(RegPeriksa $reg, SatuSehatEncounter $encounter, ?SatuSehatBundle $bundle = null): array
    {
        if ($err = $this->validateEncounter($encounter))
            return $err;

        $encIhs = $encounter->ihs_number;
        $patIhs = $encounter->patient_ihs;
        $authIhs = $encounter->practitioner_ihs;
        $noRawat = $reg->no_rawat;

        // Load MedRequests yang sudah terkirim untuk encounter ini
        $medRequests = SatuSehatMedicationRequest::where('encounter_ihs', $encIhs)
            ->whereNotNull('ihs_number')
            ->get();

        if ($medRequests->isEmpty()) {
            $this->logBundleItem($bundle, 'Composition', "{$noRawat}-COMP_MEDICATION", 'warning', null, null, 'Tidak ada MedicationRequest yang telah dikirim untuk encounter ini.');
            return ['success' => false, 'message' => 'Tidak ada MedicationRequest yang telah dikirim untuk encounter ini.', 'count' => 0, 'items' => []];
        }

        // Kelompokkan entries: per MedRequest → MedDispense(s) → MedAdmin(s)
        $groupedEntries = [];
        $missingPrereqs = [];
        foreach ($medRequests as $req) {
            $groupedEntries[] = "MedicationRequest/{$req->ihs_number}";

            $disp = SatuSehatMedicationDispense::where('medication_request_ihs', $req->ihs_number)
                ->whereNotNull('ihs_number')
                ->first();
            if ($disp) {
                $groupedEntries[] = "MedicationDispense/{$disp->ihs_number}";
            } else {
                $missingPrereqs[] = "MedicationDispense untuk MedReq {$req->ihs_number} belum dikirim.";
            }

            $admin = SatuSehatMedicationAdministration::where('medication_request_ihs', $req->ihs_number)
                ->whereNotNull('ihs_number')
                ->first();
            if ($admin) {
                $groupedEntries[] = "MedicationAdministration/{$admin->ihs_number}";
            } else {
                $missingPrereqs[] = "MedicationAdministration untuk MedReq {$req->ihs_number} belum dikirim.";
            }
        }

        // Log setiap prasyarat yang belum terpenuhi ke bundle
        foreach ($missingPrereqs as $prereq) {
            $this->logBundleItem($bundle, 'Composition', "{$noRawat}-COMP_MEDICATION", 'warning', null, null, $prereq);
        }

        $localId = "{$noRawat}-COMP_MEDICATION-{$reg->tgl_registrasi->format('Ymd')}";

        // Cek duplikat di DB lokal
        if (SatuSehatComposition::where('local_id', $localId)->where('encounter_ihs', $encIhs)->exists()) {
            return ['success' => true, 'message' => 'Medication Composition sudah pernah dikirim.', 'count' => 0, 'items' => []];
        }

        $service = new \App\Services\SatuSehat\Resources\CompositionService();
        $sentAt = now()->toIso8601String();
        $custodian = SatuSehatOrganization::where('identifier', 'RS')->first()?->ihs_number;

        $baseRecord = [
            'local_id' => $localId,
            'composition_type' => SatuSehatComposition::TYPE_RESUME_FARMASI,
            'patient_ihs' => $patIhs,
            'encounter_ihs' => $encIhs,
            'author_ihs' => $authIhs,
            'custodian_ihs' => $custodian,
            'status' => 'final',
            'type_code' => 'TK000013',
            'type_display' => 'Obat',
            'title' => 'Medication',
            'date' => $reg->tgl_registrasi->format('Y-m-d H:i:s'),
            'synced_at' => now(),
        ];

        try {
            $resp = $service->createMedicationComposition(
                patientId: $patIhs,
                encounterId: $encIhs,
                authorId: $authIhs,
                groupedEntries: $groupedEntries,
                identifier: $localId,
                date: $reg->tgl_registrasi->toIso8601String(),
            );
            $this->throttle();

            if (!$resp->success) {
                $this->logBundleItem(bundle: $bundle, type: 'Composition', localId: $localId, status: 'failed', payload: $service->getLastPayload(), response: $resp->data, error: $resp->error);
                return ['success' => false, 'message' => $resp->error, 'count' => 0, 'items' => []];
            }

            $this->logBundleItem(bundle: $bundle, type: 'Composition', localId: $localId, status: 'success', payload: $service->getLastPayload(), response: $resp->data, ihsId: $resp->resourceId);

            SatuSehatComposition::create(array_merge($baseRecord, [
                'ihs_number' => $resp->resourceId,
                'identifier' => json_encode($resp->data['identifier'] ?? []),
                'raw_response' => $resp->data,
            ]));

            return ['success' => true, 'message' => 'Medication Composition berhasil dikirim (' . count($groupedEntries) . ' entries).', 'count' => 1, 'items' => [['id' => $localId, 'status' => 'ok', 'sent_at' => $sentAt]]];
        } catch (\Exception $e) {
            if ($this->isDuplicate($e)) {
                $searchResp = $service->searchByEncounter($encIhs);
                $found = $this->findByIdentifier($searchResp, $localId);
                if ($found) {
                    SatuSehatComposition::create(array_merge($baseRecord, [
                        'ihs_number' => $found['id'],
                        'identifier' => json_encode($found['identifier'] ?? []),
                        'raw_response' => $found,
                    ]));
                    return ['success' => true, 'message' => 'Medication Composition sudah ada di Satu Sehat (duplikat disinkronkan).', 'count' => 1, 'items' => []];
                }
            }
            $this->logBundleItem(bundle: $bundle, type: 'Composition', localId: $localId, status: 'failed', error: $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'count' => 0, 'items' => []];
        }
    }
}
