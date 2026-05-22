<?php

namespace App\Jobs;

use App\Models\Bpjs\BpjsHealthcareService;
use App\Models\Simrs\Bangsal;
use App\Models\Simrs\Poliklinik;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncBpjsHealthcareServicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    /** @param 'poliklinik'|'bangsal'|'all' $type */
    public function __construct(public string $type = 'all')
    {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        Log::info('SyncBpjsHealthcareServices: Starting', ['type' => $this->type]);

        $now = now();

        if (in_array($this->type, ['poliklinik', 'all'])) {
            $this->syncPoliklinik($now);
        }

        if (in_array($this->type, ['bangsal', 'all'])) {
            $this->syncBangsal($now);
        }

        Log::info('SyncBpjsHealthcareServices: Done', ['type' => $this->type]);
    }

    private function syncPoliklinik(CarbonInterface $now): void
    {
        $existing = BpjsHealthcareService::where('type', 'poliklinik')->pluck('local_code')->toArray();

        $rows = Poliklinik::active()
            ->whereNotIn('kd_poli', $existing)
            ->get()
            ->map(fn($poli) => [
                'id'         => (string) Str::orderedUuid(),
                'type'       => 'poliklinik',
                'local_code' => $poli->kd_poli,
                'name'       => $poli->nm_poli,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

        if (!empty($rows)) {
            BpjsHealthcareService::insertOrIgnore($rows);
        }

        Log::info('SyncBpjsHealthcareServices: Poliklinik synced', ['count' => count($rows)]);
    }

    private function syncBangsal(CarbonInterface $now): void
    {
        $existing = BpjsHealthcareService::where('type', 'bangsal')->pluck('local_code')->toArray();

        $rows = Bangsal::where('status', '1')
            ->whereNotIn('kd_bangsal', $existing)
            ->get()
            ->map(fn($bangsal) => [
                'id'         => (string) Str::orderedUuid(),
                'type'       => 'bangsal',
                'local_code' => $bangsal->kd_bangsal,
                'name'       => $bangsal->nm_bangsal,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

        if (!empty($rows)) {
            BpjsHealthcareService::insertOrIgnore($rows);
        }

        Log::info('SyncBpjsHealthcareServices: Bangsal synced', ['count' => count($rows)]);
    }

    public function tags(): array
    {
        return ['bpjs', 'bpjs-healthcare-service-sync', "type:{$this->type}"];
    }
}
