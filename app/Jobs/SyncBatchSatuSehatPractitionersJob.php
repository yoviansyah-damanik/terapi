<?php

namespace App\Jobs;

use App\Models\Simrs\Pegawai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncBatchSatuSehatPractitionersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public array $employeeIds = [],
        public bool $syncAll = false,
        public int $limit = 50,
    ) {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        try {
            $employees = $this->getUnsyncedPractitioners();

            $count = 0;
            foreach ($employees as $employee) {
                SyncSatuSehatPractitionerJob::dispatch($employee->no_ktp, $employee->nik)
                    ->delay(now()->addSeconds($count * 2));
                $count++;
            }

            Log::info("SyncBatchSatuSehatPractitioners: Dispatched {$count} sync jobs");

        } catch (\Exception $e) {
            Log::error("SyncBatchSatuSehatPractitioners: Error", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Memeriksa dan mengambil data pegawai yang belum tersinkronisasi ke SatuSehat.
     * Karena menggunakan koneksi database yang berbeda, kita ambil list NIK terlebih dahulu.
     */
    protected function getUnsyncedPractitioners()
    {
        $query = Pegawai::query()
            ->whereNotNull('no_ktp')
            ->where('stts_aktif', 'AKTIF')
            ->whereRaw('no_ktp REGEXP "^[0-9]{16}$"')
            ->where('no_ktp', '!=', '0000000000000000');

        if (!$this->syncAll && !empty($this->employeeIds)) {
            $query->whereIn('nik', $this->employeeIds);
        } else {
            // Ambil data NIK (KTP) practitioner yang sudah ada di database lokal/SatuSehat
            $syncedNiks = \App\Models\SatuSehat\SatuSehatPractitioner::select('nik')
                ->whereNotNull('nik')
                ->pluck('nik')
                ->toArray();

            // Kecualikan practitioner yang sudah tersinkronisasi
            if (!empty($syncedNiks)) {
                $query->whereNotIn('no_ktp', $syncedNiks);
            }
        }

        if ($this->limit > 0) {
            $query->limit($this->limit);
        }

        return $query;
    }

    public function tags(): array
    {
        return ['satusehat', 'practitioner-batch-sync'];
    }
}
