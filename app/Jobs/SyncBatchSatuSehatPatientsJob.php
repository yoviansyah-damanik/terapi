<?php

namespace App\Jobs;

use App\Models\Simrs\Pasien;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncBatchSatuSehatPatientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;
    public int $backoff = 60;

    public function __construct(
        public array $patientIds = [],
        public bool $syncAll = false,
        public int $limit = 100,
    ) {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        Log::info("SyncBatchSatuSehatPatients: Starting batch sync", [
            'sync_all'   => $this->syncAll,
            'patient_ids'=> count($this->patientIds),
            'limit'      => $this->limit,
        ]);

        $dispatched = 0;

        $this->buildQuery()->cursor()->each(function ($patient) use (&$dispatched) {
            SyncSatuSehatPatientJob::dispatch($patient->no_ktp, $patient->no_rkm_medis)
                ->delay(now()->addSeconds($dispatched * 2));
            $dispatched++;
        });

        Log::info("SyncBatchSatuSehatPatients: Dispatched jobs", ['count' => $dispatched]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SyncBatchSatuSehatPatients: Job failed", [
            'error'       => $e->getMessage(),
            'sync_all'    => $this->syncAll,
            'patient_ids' => count($this->patientIds),
            'attempt'     => $this->attempts(),
        ]);
    }

    /**
     * Bangun query pasien SIMRS yang belum tersinkronisasi.
     * NIK yang sudah ada di lokal diambil dalam chunk kecil (500) untuk
     * menghindari SQL clause NOT IN yang terlalu besar dan memory exhaustion.
     */
    protected function buildQuery()
    {
        $query = Pasien::query()
            ->select(['no_ktp', 'no_rkm_medis'])
            ->whereNotNull('no_ktp')
            ->whereRaw('no_ktp REGEXP "^[0-9]{16}$"')
            ->where('no_ktp', '!=', '0000000000000000');

        if (!$this->syncAll && !empty($this->patientIds)) {
            $query->whereIn('no_rkm_medis', $this->patientIds);
        } else {
            // Ambil NIK yang sudah tersinkronisasi dalam chunk kecil
            // agar tidak membuat IN clause raksasa di satu query
            $syncedNiks = [];
            \App\Models\SatuSehat\SatuSehatPatient::select('nik')
                ->whereNotNull('nik')
                ->chunk(500, function ($rows) use (&$syncedNiks) {
                    foreach ($rows as $row) {
                        $syncedNiks[] = $row->nik;
                    }
                });

            if (!empty($syncedNiks)) {
                // Split whereNotIn per 1000 nik untuk batasi ukuran SQL clause
                $chunks = array_chunk($syncedNiks, 1000);
                foreach ($chunks as $chunk) {
                    $query->whereNotIn('no_ktp', $chunk);
                }
            }
        }

        if ($this->limit > 0) {
            $query->limit($this->limit);
        }

        return $query;
    }

    public function tags(): array
    {
        return ['satusehat', 'batch-sync'];
    }
}
