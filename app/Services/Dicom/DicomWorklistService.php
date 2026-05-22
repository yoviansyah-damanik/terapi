<?php

namespace App\Services\Dicom;

use App\Models\Simrs\PermintaanRadiologi;
use App\Models\Dicom\DicomModality;
use App\Models\Dicom\DicomStudy;
use App\Models\Dicom\Worklist;
use App\Models\Mapping\RadDicomRouterMap;
use App\Services\Dicom\OrthancService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Helpers\ConfigurationHelper;

use App\Services\Dicom\OrthancSyncService;

class DicomWorklistService
{
    public function __construct(
        private readonly OrthancSyncService $syncService
    ) {}

    /** Ambil order radiologi dari SIMRS berdasarkan range tanggal dan opsional modality */
    public function getOrders(string $startDate, string $endDate, ?string $modalityType = null): Collection
    {
        $query = PermintaanRadiologi::with(['regPeriksa.pasien', 'dokterPerujuk'])
            ->leftJoin('periksa_radiologi', function ($join) {
                $join->on('permintaan_radiologi.no_rawat', '=', 'periksa_radiologi.no_rawat');
            })
            ->leftJoin('jns_perawatan_radiologi', function ($join) {
                $join->on('periksa_radiologi.kd_jenis_prw', '=', 'jns_perawatan_radiologi.kd_jenis_prw');
            })
            ->select([
                'permintaan_radiologi.*',
                'jns_perawatan_radiologi.nm_perawatan as nama_jenis_prw',
                'jns_perawatan_radiologi.kd_jenis_prw',
            ])
            ->whereBetween('permintaan_radiologi.tgl_permintaan', [$startDate, $endDate])
            ->groupBy('permintaan_radiologi.noorder');

        if ($modalityType) {
            $query->where('jns_perawatan_radiologi.kategori', $modalityType);
        }

        return $query->get();
    }

    /**
     * Kirim data Worklist secara langsung via API REST Orthanc (Tanpa File Folder).
     */
    public function sendToOrthanc(PermintaanRadiologi $order, ?string $aeTitle = null): bool
    {
        $pasien = $order->regPeriksa?->pasien;
        $noorder = $order->noorder;
        $studyUid = $this->generateStudyUid($noorder);

        $patientName = $pasien ? strtoupper(str_replace(' ', '^', $pasien->nm_pasien ?? '')) : 'UNKNOWN';
        $patientId = $pasien?->no_rkm_medis ?? '';
        $birthDate = $pasien?->tgl_lahir ? date('Ymd', strtotime($pasien->tgl_lahir)) : '';
        $sex = $this->mapSex($pasien?->jk ?? '');
        $studyDate = $order->tgl_permintaan ? $order->tgl_permintaan->format('Ymd') : date('Ymd');
        $studyTime = str_replace(':', '', $order->jam_permintaan);
        $description = $order->nama_jenis_prw ?? 'RADIOLOGY';
        $modality = $order->kd_jenis_prw ? $this->inferModality($order->kd_jenis_prw) : 'OT';

        // Tag Hierarchical standar DICOM Modality Worklist
        $tags = [
            'Modality' => $modality,
            'PatientName' => $patientName,
            'PatientID' => $patientId,
            'PatientBirthDate' => $birthDate,
            'PatientSex' => $sex,
            'StudyInstanceUID' => $studyUid,
            'StudyDate' => $studyDate,
            'StudyTime' => $studyTime,
            'ReferringPhysicianName' => 'Tes',
            'RequestingPhysician' => 'Tes',
            'RequestedProcedureDescription' => $description,
            'ScheduledProcedureStepSequence' => [
                [
                    'ScheduledStationAETitle' => $aeTitle ?? 'ORTHANC',
                    'ScheduledProcedureStepStartDate' => $studyDate,
                    'ScheduledProcedureStepStartTime' => $studyTime,
                    'ScheduledProcedureStepID' => $noorder,
                    'ScheduledStationName' => 'SATUSEHAT',
                    'ScheduledProcedureStepDescription' => $description,
                    'ScheduledPerformingPhysicianName' => '',
                    'Modality' => $modality,
                ]
            ],
            'AccessionNumber' => $noorder,
            'RequestedProcedureID' => $noorder,
        ];

        $orthanc = app(\App\Services\Dicom\OrthancService::class);
        $response = $orthanc->createWorklist($tags);

        if (!$response['success']) {
            throw new \Exception($response['message'] ?? 'Gagal membuat worklist di PACS.');
        }

        return true;
    }

    /**
     * Memproses satu order dari SIMRS (bisa berisi banyak tindakan).
     * Melakukan splitting tindakan menjadi entri worklist terpisah.
     */
    public function processSimrsOrder(string $noorder, bool $bypass = false, string $type = 'radiologi'): array
    {
        $orderData = match ($type) {
            'usg'   => $this->fetchUsgOrderData($noorder),
            default => $this->fetchSimrsOrderData($noorder),
        };

        if (!$orderData) {
            throw new \Exception("Data order {$noorder} tidak ditemukan di SIMRS (type: {$type}).");
        }

        $results = [];
        $orthanc = app(OrthancService::class);

        // Jika bypass = true, hapus semua data lama yang berkaitan dengan noorder ini di Orthanc
        if ($bypass && $orthanc->isConfigured()) {
            $orthanc->cleanupWorklists($noorder);
        }

        $itemCount = $orderData['items']->count();
        if ($itemCount > 1) {
            return [[
                'accession_number' => $noorder,
                'success' => false,
                'message' => 'Gagal: Satu nomor order hanya boleh berisi 1 tindakan (Terdeteksi ' . $itemCount . ' tindakan). Silakan pecah order di SIMRS.',
            ]];
        }

        $item = $orderData['items']->first();
        if (!$item) {
            return [[
                'accession_number' => $noorder,
                'success' => false,
                'message' => 'Tidak ada item pemeriksaan.',
            ]];
        }

        $kdJenisPrw = $item->kd_jenis_prw;
        $splitAcsn = $noorder;

        // 1. Cek duplikasi di DB lokal
        $existing = Worklist::where('accession_number', $splitAcsn)->first();
        if ($existing && !$bypass) {
            return [[
                'accession_number' => $splitAcsn,
                'success' => false,
                'message' => 'Sudah terdaftar (skip).',
            ]];
        }

        // 2. Cari Mapping Router & Modality
        if ($type === 'usg') {
            // Untuk USG: ambil AE Title dari dicom_modalities dengan modality_type = 'US'
            $usgModality = DicomModality::worklist()
                ->where('modality_type', 'US')
                ->first();

            $aeTitle      = $usgModality?->ae_title ?? 'ORTHANC';
            $modalityType = 'US';
        } else {
            $mapping = RadDicomRouterMap::with(['router', 'modality'])
                ->where('local_code', $kdJenisPrw)
                ->first();

            if (!$mapping || !$mapping->router) {
                // Fallback jika tidak ada mapping router
                $aeTitle      = 'ORTHANC';
                $modalityType = $this->inferModality($kdJenisPrw);
            } else {
                $modality = $mapping->modality;

                $aeTitle      = $modality->ae_title ?? $mapping->router->ae_title ?? 'ORTHANC';
                $modalityType = $modality->modality_type ?? $this->inferModality($kdJenisPrw);
            }
        }

        // 3. Bangun Tags
        $studyUid = $this->generateStudyUid($splitAcsn);
        $tags = $this->buildTags($orderData['pasien'], $orderData['order'], $item, $splitAcsn, $studyUid, $aeTitle, $modalityType);

        // 4. Simpan ke database dengan status "pending"
        Worklist::updateOrCreate(
            ['accession_number' => $splitAcsn],
            [
                'noorder' => $noorder,
                'no_rawat' => $orderData['order']->no_rawat,
                'patient_id' => $orderData['pasien']->no_rkm_medis,
                'patient_name' => strtoupper(str_replace('^', ' ', $tags['PatientName'] ?? 'UNKNOWN')),
                'birth_date' => $orderData['pasien']->tgl_lahir,
                'gender' => $orderData['pasien']->jk === 'L' ? 'M' : 'F',
                'modality' => $modalityType,
                'ae_title' => $aeTitle,
                'procedure_desc' => $item->nm_perawatan,
                'scheduled_date' => date('Y-m-d H:i:s', strtotime($orderData['order']->tgl_permintaan)),
                'study_instance_uid' => $studyUid,
                'status' => 'pending', // Status awal
            ]
        );

        // 5. Kirim ke Orthanc-Sync (Django) via API Bridge
        $webhookUrl = url('/api/webhooks/orthanc/worklist'); // absolute URL untuk webhook

        $payload = [
            'accession_number' => $splitAcsn,
            'patient_id' => $orderData['pasien']->no_rkm_medis,
            'patient_name' => strtoupper(str_replace('^', ' ', $tags['PatientName'] ?? 'UNKNOWN')),
            'birth_date' => $orderData['pasien']->tgl_lahir,
            'gender' => $orderData['pasien']->jk === 'L' ? 'M' : 'F',
            'modality' => $modalityType,
            'ae_title' => $aeTitle,
            'procedure_desc' => $item->nm_perawatan,
            'scheduled_date' => date('Y-m-d', strtotime($orderData['order']->tgl_permintaan)),
            'webhook_url' => $webhookUrl,
            'study_instance_uid' => $studyUid,
            'bypass' => $bypass
        ];

        $syncResp = $this->syncService->storeWorklist($payload, $bypass);

        if ($syncResp['success']) {
            $results[] = [
                'accession_number' => $splitAcsn,
                'success' => true,
                'study_instance_uid' => $studyUid,
                'ae_title' => $aeTitle,
                'modality' => $modalityType,
                'message' => 'Terkirim ke antrean Orthanc-Sync',
                'payload' => $syncResp
            ];
        } else {
            // Update status jadi failed
            Worklist::where('accession_number', $splitAcsn)->update(['status' => 'failed', 'error_message' => $syncResp['message']]);
            $results[] = [
                'accession_number' => $splitAcsn,
                'success' => false,
                'message' => 'Gagal sinkronisasi ke Orthanc-Sync: ' . $syncResp['message'],
            ];
        }
        return $results;
    }

    /**
     * Ambil data order USG dari SIMRS.
     * Sumber: permintaan_usg + hasil_pemeriksaan_usg + reg_periksa + pasien.
     */
    private function fetchUsgOrderData(string $noorder): ?array
    {
        $db = DB::connection('simrs');

        $row = $db->table('permintaan_usg as pu')
            ->join('reg_periksa as rp', 'rp.no_rawat', '=', 'pu.no_rawat')
            ->join('pasien as p', 'p.no_rkm_medis', '=', 'rp.no_rkm_medis')
            ->leftJoin(\App\Models\Simrs\Usg\HasilPemeriksaanUsg::make()->getTable() . ' as hpu', 'hpu.noorder', '=', 'pu.noorder')
            ->leftJoin('dokter as d', 'd.kd_dokter', '=', 'hpu.kd_dokter')
            ->where('pu.noorder', $noorder)
            ->select([
                'pu.noorder',
                'pu.no_rawat',
                'pu.jenis_permintaan',
                'pu.waktu_permintaan',
                'p.no_rkm_medis',
                'p.nm_pasien',
                'p.tgl_lahir',
                'p.jk',
                'hpu.kd_dokter',
                'd.nm_dokter',
            ])
            ->first();

        if (!$row) return null;

        return [
            'order' => (object)[
                'noorder'        => $row->noorder,
                'no_rawat'       => $row->no_rawat,
                'tgl_permintaan' => $row->waktu_permintaan,
                'jam_permintaan' => date('H:i:s', strtotime($row->waktu_permintaan)),
                'dokter_perujuk' => $row->kd_dokter,
            ],
            'pasien' => (object)[
                'no_rkm_medis' => $row->no_rkm_medis,
                'nm_pasien'    => $row->nm_pasien,
                'tgl_lahir'    => $row->tgl_lahir,
                'jk'           => $row->jk,
            ],
            'items' => collect([(object)[
                'kd_jenis_prw' => 'USG',
                'nm_perawatan' => $row->jenis_permintaan,
            ]]),
            'dokter_perujuk' => $row->nm_dokter ?? 'UNKNOWN',
        ];
    }

    /**
     * Ambil data lengkap dari SIMRS.
     */
    private function fetchSimrsOrderData(string $noorder): ?array
    {
        $db = DB::connection('simrs');

        $order = $db->table('permintaan_radiologi')
            ->where('noorder', $noorder)
            ->first();

        if (!$order) return null;

        $pasien = $db->table('reg_periksa as rp')
            ->join('pasien as p', 'rp.no_rkm_medis', '=', 'p.no_rkm_medis')
            ->where('rp.no_rawat', $order->no_rawat)
            ->select(['p.no_rkm_medis', 'p.nm_pasien', 'p.tgl_lahir', 'p.jk'])
            ->first();

        $items = $db->table('permintaan_pemeriksaan_radiologi as ppr')
            ->join('jns_perawatan_radiologi as jp', 'ppr.kd_jenis_prw', '=', 'jp.kd_jenis_prw')
            ->where('ppr.noorder', $noorder)
            ->select(['jp.kd_jenis_prw', 'jp.nm_perawatan'])
            ->get();

        $dokter = $db->table('dokter')
            ->where('kd_dokter', $order->dokter_perujuk)
            ->value('nm_dokter');

        return [
            'order' => $order,
            'pasien' => $pasien,
            'items' => $items,
            'dokter_perujuk' => $dokter ?? 'UNKNOWN',
        ];
    }

    /**
     * Bangun DICOM tags array.
     */
    private function buildTags($pasien, $order, $item, $accessionNumber, $studyUid, $aeTitle, $modality): array
    {
        $patientName = strtoupper(str_replace(' ', '^', $pasien->nm_pasien ?? 'UNKNOWN'));
        $birthDate = $pasien->tgl_lahir ? date('Ymd', strtotime($pasien->tgl_lahir)) : '';
        $studyDate = date('Ymd', strtotime($order->tgl_permintaan));
        $studyTime = str_replace(':', '', $order->jam_permintaan);

        return [
            'SpecificCharacterSet' => 'ISO_IR 192',
            'Modality' => $modality,
            'PatientName' => $patientName,
            'PatientID' => $pasien->no_rkm_medis,
            'PatientBirthDate' => $birthDate,
            'PatientSex' => $pasien->jk === 'L' ? 'M' : 'F',
            'StudyInstanceUID' => $studyUid,
            'StudyDate' => $studyDate,
            'StudyTime' => $studyTime,
            'StudyDescription' => $item->nm_perawatan,
            'StudyID' => $order->noorder,
            'AccessionNumber' => $accessionNumber,
            'RequestedProcedureID' => $accessionNumber,
            'RequestedProcedureDescription' => $item->nm_perawatan,
            'ScheduledProcedureStepSequence' => [
                [
                    'ScheduledStationAETitle' => $aeTitle,
                    'ScheduledProcedureStepStartDate' => $studyDate,
                    'ScheduledProcedureStepStartTime' => $studyTime,
                    'ScheduledProcedureStepID' => $accessionNumber,
                    'ScheduledProcedureStepDescription' => $item->nm_perawatan,
                    'Modality' => $modality,
                ],
            ],
            'InstitutionName' => \App\Helpers\ConfigurationHelper::get('hospital.name', 'Rumah Sakit'),
        ];
    }

    private function generateStudyUid(string $id): string
    {
        $appKeyHash = abs(crc32(config('app.key', 'terapi')));
        $idHash = abs(crc32($id));
        return "2.25.{$appKeyHash}.{$idHash}";
    }

    private function inferModality(string $kdJenisPrw): string
    {
        $upper = strtoupper($kdJenisPrw);
        if (str_contains($upper, 'CT')) return 'CT';
        if (str_contains($upper, 'MR')) return 'MR';
        if (str_contains($upper, 'USG')) return 'US';
        if (str_contains($upper, 'DR') || str_contains($upper, 'XR')) return 'DR';
        if (str_contains($upper, 'CR')) return 'CR';
        return 'OT';
    }
}
