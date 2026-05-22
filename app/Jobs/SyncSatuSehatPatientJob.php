<?php

namespace App\Jobs;

use App\Models\SatuSehat\SatuSehatPatient;
use App\Models\Simrs\Pasien;
use App\Services\SatuSehat\Resources\PatientService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSatuSehatPatientJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $nik,
        public ?string $noRkmMedis = null,
    ) {
        $this->onQueue('sync');
    }

    public function handle(PatientService $service): void
    {
        try {
            $result = $service->findByNik($this->nik);

            if (!$result) {
                Log::warning("SyncSatuSehatPatient: Patient not found in SatuSehat", [
                    'nik' => $this->nik,
                    'no_rkm_medis' => $this->noRkmMedis,
                ]);
                return;
            }

            $resource = $result['resource'] ?? $result;
            $ihsNumber = $resource['id'] ?? null;

            if (!$ihsNumber) {
                Log::warning("SyncSatuSehatPatient: IHS Number not found", [
                    'nik' => $this->nik,
                ]);
                return;
            }

            SatuSehatPatient::updateOrCreate(
                ['nik' => $this->nik],
                [
                    'ihs_number' => $ihsNumber,
                    'name' => $this->extractName($resource),
                    'gender' => $resource['gender'] ?? null,
                    'birth_date' => $resource['birthDate'] ?? null,
                    'phone' => $this->extractTelecom($resource, 'phone'),
                    'email' => $this->extractTelecom($resource, 'email'),
                    'address' => $this->extractAddress($resource),
                    'city' => $this->extractAddressPart($resource, 'city'),
                    'province' => $this->extractAddressPart($resource, 'state'),
                    'postal_code' => $this->extractAddressPart($resource, 'postalCode'),
                    'raw_response' => $resource,
                    'synced_at' => now(),
                ]
            );

            Log::info("SyncSatuSehatPatient: Successfully synced", [
                'nik' => $this->nik,
                'ihs_number' => $ihsNumber,
            ]);

        } catch (\Exception $e) {
            Log::error("SyncSatuSehatPatient: Error syncing patient", [
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

    private function extractAddress(array $resource): ?string
    {
        $addresses = $resource['address'] ?? [];
        if (!empty($addresses[0])) {
            $lines = $addresses[0]['line'] ?? [];
            return implode(', ', $lines);
        }
        return null;
    }

    private function extractAddressPart(array $resource, string $part): ?string
    {
        $addresses = $resource['address'] ?? [];
        if (!empty($addresses[0])) {
            return $addresses[0][$part] ?? null;
        }
        return null;
    }

    public function tags(): array
    {
        return ['satusehat', 'patient-sync', 'nik:' . $this->nik];
    }
}
