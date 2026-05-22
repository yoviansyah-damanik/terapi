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

class SyncSimrsPatientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public string $noRkmMedis,
    ) {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        try {
            $simrsPatient = SimrsPatient::find($this->noRkmMedis);

            if (!$simrsPatient) {
                Log::warning("SyncSimrsPatient: Patient not found in SIMRS", [
                    'no_rkm_medis' => $this->noRkmMedis,
                ]);
                return;
            }

            Patient::createFromSimrs($simrsPatient);

            Log::info("SyncSimrsPatient: Successfully synced", [
                'no_rkm_medis' => $this->noRkmMedis,
            ]);

        } catch (\Exception $e) {
            Log::error("SyncSimrsPatient: Error syncing patient", [
                'no_rkm_medis' => $this->noRkmMedis,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function tags(): array
    {
        return ['simrs', 'patient-sync', 'no_rm:' . $this->noRkmMedis];
    }
}
