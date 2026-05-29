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

    public int $tries = 3;
    public int $timeout = 600;
    public int $backoff = 60;

    public function __construct(
        public array $employeeIds = [],
        public bool $syncAll = false,
        public int $limit = 50,
    ) {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        Log::info("SyncBatchSatuSehatPractitioners: Starting batch sync", [
            'sync_all'     => $this->syncAll,
            'employee_ids' => count($this->employeeIds),
            'limit'        => $this->limit,
        ]);

        $dispatched = 0;

        $this->buildQuery()->cursor()->each(function ($employee) use (&$dispatched) {
            SyncSatuSehatPractitionerJob::dispatch($employee->no_ktp, $employee->nik)
                ->delay(now()->addSeconds($dispatched * 2));
            $dispatched++;
        });

        Log::info("SyncBatchSatuSehatPractitioners: Dispatched jobs", ['count' => $dispatched]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SyncBatchSatuSehatPractitioners: Job failed", [
            'error'        => $e->getMessage(),
            'sync_all'     => $this->syncAll,
            'employee_ids' => count($this->employeeIds),
            'attempt'      => $this->attempts(),
        ]);
    }

    /**
     * Bangun query pegawai SIMRS yang belum tersinkronisasi ke SatuSehat.
     * NIK (KTP) yang sudah ada di lokal diambil dalam chunk kecil (500) untuk
     * menghindari SQL clause NOT IN yang terlalu besar dan memory exhaustion.
     */
    protected function buildQuery()
    {
        $query = Pegawai::query()
            ->select(['no_ktp', 'nik'])
            ->whereNotNull('no_ktp')
            ->where('stts_aktif', 'AKTIF')
            ->whereRaw('no_ktp REGEXP "^[0-9]{16}$"')
            ->where('no_ktp', '!=', '0000000000000000');

        if (!$this->syncAll && !empty($this->employeeIds)) {
            $query->whereIn('nik', $this->employeeIds);
        } else {
            $syncedNiks = [];
            \App\Models\SatuSehat\SatuSehatPractitioner::select('nik')
                ->whereNotNull('nik')
                ->chunk(500, function ($rows) use (&$syncedNiks) {
                    foreach ($rows as $row) {
                        $syncedNiks[] = $row->nik;
                    }
                });

            if (!empty($syncedNiks)) {
                foreach (array_chunk($syncedNiks, 1000) as $chunk) {
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
        return ['satusehat', 'practitioner-batch-sync'];
    }
}
