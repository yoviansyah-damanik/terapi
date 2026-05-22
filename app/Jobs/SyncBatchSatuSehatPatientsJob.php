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

    public int $tries = 1;
    public int $timeout = 3600;

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
            'sync_all' => $this->syncAll,
            'patient_ids' => count($this->patientIds),
            'limit' => $this->limit,
        ]);

        $patients = $this->getUnsyncedPatients();

        $dispatched = 0;
        foreach ($patients as $patient) {
            SyncSatuSehatPatientJob::dispatch($patient->no_ktp, $patient->no_rkm_medis)
                ->delay(now()->addSeconds($dispatched * 2));
            $dispatched++;
        }

        Log::info("SyncBatchSatuSehatPatients: Dispatched jobs", [
            'count' => $dispatched,
        ]);
    }

    /**
     * Memeriksa dan mengambil pasien yang belum tersinkronisasi ke SatuSehat.
     * Karena menggunakan koneksi database yang berbeda, kita ambil list NIK terlebih dahulu.
     */
    protected function getUnsyncedPatients()
    {
        $query = Pasien::query()
            ->whereNotNull('no_ktp')
            ->whereRaw('no_ktp REGEXP "^[0-9]{16}$"')
            ->where('no_ktp', '!=', '0000000000000000');

        if (!$this->syncAll && !empty($this->patientIds)) {
            $query->whereIn('no_rkm_medis', $this->patientIds);
        } else {
            // Ambil data NIK pasien yang sudah ada di database lokal/SatuSehat
            $syncedNiks = \App\Models\SatuSehat\SatuSehatPatient::select('nik')
                ->whereNotNull('nik')
                ->pluck('nik')
                ->toArray();

            // Kecualikan pasien yang sudah tersinkronisasi
            if (!empty($syncedNiks)) {
                $query->whereNotIn('no_ktp', $syncedNiks);
            }
        }

        if ($this->limit === 0)
            return $query->get();

        return $query->limit($this->limit)->get();
    }

    public function tags(): array
    {
        return ['satusehat', 'batch-sync'];
    }
}
