<?php

namespace App\Services\Dicom;

use App\Models\Dicom\DicomRouter;
use App\Models\Dicom\Worklist;

class DicomRouterService
{
    public function __construct(
        private readonly OrthancService $orthanc,
    ) {}

    /**
     * Daftarkan router ke Orthanc (jika belum) lalu kirim studi via C-STORE.
     *
     * @return array  ['success' => bool, 'job_id' => string|null, 'message' => string]
     */
    public function sendStudy(DicomRouter $router, string $orthancStudyId): array
    {
        if (!$this->orthanc->isConfigured()) {
            return ['success' => false, 'job_id' => null, 'message' => 'Orthanc belum dikonfigurasi.'];
        }

        // Daftarkan / update modality di Orthanc
        $regResult = $this->orthanc->registerModality($router->ae_title, $router->host, $router->port);
        if (!$regResult['success']) {
            return [
                'success' => false,
                'job_id'  => null,
                'message' => 'Gagal mendaftarkan router ke Orthanc: ' . $regResult['message'],
            ];
        }

        // Kirim studi via C-STORE (async → return job ID)
        $storeResult = $this->orthanc->storeToModality($router->ae_title, [$orthancStudyId], sync: false);

        if (!$storeResult['success']) {
            return [
                'success' => false,
                'job_id'  => null,
                'message' => 'Gagal mengirim studi: ' . $storeResult['message'],
            ];
        }

        $jobId = $storeResult['data']['ID'] ?? null;

        // Perbarui status Worklist
        Worklist::where('orthanc_study_id', $orthancStudyId)->update([
            'status'            => 'sent',
            'sent_to_router_at' => now(),
            'router_job_id'     => $jobId,
        ]);

        return [
            'success' => true,
            'job_id'  => $jobId,
            'message' => 'Studi berhasil dikirim ke router ' . $router->ae_title . '.',
        ];
    }

    /**
     * Kirim banyak studi sekaligus ke satu router.
     *
     * @param  string[]  $orthancStudyIds
     */
    public function sendStudies(DicomRouter $router, array $orthancStudyIds): array
    {
        if (!$this->orthanc->isConfigured()) {
            return ['success' => false, 'job_id' => null, 'message' => 'Orthanc belum dikonfigurasi.'];
        }

        $regResult = $this->orthanc->registerModality($router->ae_title, $router->host, $router->port);
        if (!$regResult['success']) {
            return [
                'success' => false,
                'job_id'  => null,
                'message' => 'Gagal mendaftarkan router ke Orthanc: ' . $regResult['message'],
            ];
        }

        $storeResult = $this->orthanc->storeToModality($router->ae_title, $orthancStudyIds, sync: false);

        if (!$storeResult['success']) {
            return [
                'success' => false,
                'job_id'  => null,
                'message' => 'Gagal mengirim studi: ' . $storeResult['message'],
            ];
        }

        $jobId = $storeResult['data']['ID'] ?? null;
        $now   = now();

        Worklist::whereIn('orthanc_study_id', $orthancStudyIds)->update([
            'status'            => 'sent',
            'sent_to_router_at' => $now,
            'router_job_id'     => $jobId,
        ]);

        return [
            'success' => true,
            'job_id'  => $jobId,
            'message' => sprintf('%d studi berhasil dikirim ke router %s.', count($orthancStudyIds), $router->ae_title),
        ];
    }

    /** Cek status job Orthanc */
    public function checkJob(string $jobId): array
    {
        return $this->orthanc->getJob($jobId);
    }
}
