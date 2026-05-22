<?php

namespace App\Jobs;

use App\Models\SatuSehat\SatuSehatPractitioner;
use App\Services\SatuSehat\Resources\PractitionerService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSatuSehatPractitionerJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $nik,
        public ?string $employeeId = null,
    ) {
        $this->onQueue('sync');
    }

    public function handle(PractitionerService $service): void
    {
        try {
            $result = $service->findByNik($this->nik);

            if (!$result) {
                Log::warning("SyncSatuSehatPractitioner: Practitioner not found in SatuSehat", [
                    'nik' => $this->nik,
                    'employee_id' => $this->employeeId,
                ]);
                return;
            }

            $resource = $result['resource'] ?? $result;
            $ihsNumber = $resource['id'] ?? null;

            if (!$ihsNumber) {
                Log::warning("SyncSatuSehatPractitioner: IHS Number not found", [
                    'nik' => $this->nik,
                ]);
                return;
            }

            SatuSehatPractitioner::updateOrCreate(
                ['nik' => $this->nik],
                [
                    'ihs_number' => $ihsNumber,
                    'name' => $this->extractName($resource),
                    'gender' => $resource['gender'] ?? null,
                    'birth_date' => $resource['birthDate'] ?? null,
                    'phone' => $this->extractTelecom($resource, 'phone'),
                    'email' => $this->extractTelecom($resource, 'email'),
                    'qualification' => $resource['qualification'] ?? null,
                    'raw_response' => $resource,
                    'synced_at' => now(),
                ]
            );

            Log::info("SyncSatuSehatPractitioner: Successfully synced", [
                'nik' => $this->nik,
                'ihs_number' => $ihsNumber,
            ]);

        } catch (\Exception $e) {
            Log::error("SyncSatuSehatPractitioner: Error syncing practitioner", [
                'nik' => $this->nik,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function extractName(array $resource): ?string
    {
        $names = $resource['name'] ?? [];
        if (!empty($names[0])) {
            return $names[0]['text'] ?? null;
        }
        return null;
    }

    private function extractTelecom(array $resource, string $system): ?string
    {
        $telecoms = $resource['telecom'] ?? [];
        foreach ($telecoms as $telecom) {
            if (($telecom['system'] ?? '') === $system) {
                return $telecom['value'] ?? null;
            }
        }
        return null;
    }

    public function tags(): array
    {
        return ['satusehat', 'practitioner-sync', 'nik:' . $this->nik];
    }
}
