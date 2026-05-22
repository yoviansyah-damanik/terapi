<?php

namespace App\Jobs;

use App\Models\Patient;
use App\Models\Simrs\Pasien as SimrsPatient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncBatchSimrsPatientsJob implements ShouldQueue
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
        Log::info("SyncBatchSimrsPatients: Starting batch sync", [
            'sync_all' => $this->syncAll,
            'patient_ids' => count($this->patientIds),
            'limit' => $this->limit,
        ]);

        $existingRms = Patient::pluck('medical_record_number')->toArray();

        $query = SimrsPatient::query()
            ->whereNotIn('no_rkm_medis', $existingRms);

        if (!$this->syncAll && !empty($this->patientIds)) {
            $query->whereIn('no_rkm_medis', $this->patientIds);
        }

        $patients = $query->limit($this->limit)->get();

        $dispatched = 0;
        foreach ($patients as $patient) {
            SyncSimrsPatientJob::dispatch($patient->no_rkm_medis)
                ->delay(now()->addSeconds($dispatched));
            $dispatched++;
        }

        Log::info("SyncBatchSimrsPatients: Dispatched jobs", [
            'count' => $dispatched,
        ]);
    }

    public function tags(): array
    {
        return ['simrs', 'batch-sync'];
    }
}
