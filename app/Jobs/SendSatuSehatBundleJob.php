<?php

namespace App\Jobs;

use App\Models\SatuSehat\SatuSehatBundle;
use App\Models\SatuSehat\SatuSehatBundleItem;
use App\Models\Simrs\RegPeriksa;
use App\Services\SatuSehat\ErmFhirService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendSatuSehatBundleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly string $noRawat,
        public readonly SatuSehatBundle $bundle,
    ) {
        $this->onQueue('high');
    }

    public function handle(ErmFhirService $service): void
    {
        Log::info('SendSatuSehatBundle: Starting', ['no_rawat' => $this->noRawat]);

        $reg = RegPeriksa::with(['pasien', 'dokter', 'poliklinik'])->find($this->noRawat);

        if (!$reg) {
            SatuSehatBundleItem::create([
                'bundle_log_id' => $this->bundle->id,
                'resource_type' => 'System',
                'status'        => 'failed',
                'error_message' => 'Data registrasi tidak ditemukan.',
            ]);

            $this->bundle->update([
                'status'       => SatuSehatBundle::STATUS_FAILED,
                'completed_at' => now(),
            ]);

            Log::warning('SendSatuSehatBundle: RegPeriksa not found', ['no_rawat' => $this->noRawat]);
            return;
        }

        $result = $service->sendAllBundle($reg, $this->bundle);

        $totalSent   = collect($result['results'] ?? [])->sum('count');
        $totalErrors = count($result['errors'] ?? []);

        $status = match (true) {
            $result['success']                    => SatuSehatBundle::STATUS_COMPLETED,
            $totalSent > 0 || $result['finished'] => SatuSehatBundle::STATUS_PARTIAL,
            default                               => SatuSehatBundle::STATUS_FAILED,
        };

        $this->bundle->update([
            'status'              => $status,
            'total_sent'          => $totalSent,
            'total_errors'        => $totalErrors,
            'encounter_finished'  => $result['finished'] ?? false,
            'completed_at'        => now(),
        ]);

        Log::info('SendSatuSehatBundle: Done', [
            'no_rawat' => $this->noRawat,
            'status'   => $status,
            'sent'     => $totalSent,
            'errors'   => $totalErrors,
        ]);
    }

    public function failed(Throwable $e): void
    {
        SatuSehatBundleItem::create([
            'bundle_log_id' => $this->bundle->id,
            'resource_type' => 'System',
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
        ]);

        $this->bundle->update([
            'status'       => SatuSehatBundle::STATUS_FAILED,
            'completed_at' => now(),
        ]);

        Log::error('SendSatuSehatBundle: Job failed', [
            'no_rawat' => $this->noRawat,
            'error'    => $e->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['satusehat', 'satusehat-bundle', "no-rawat:{$this->noRawat}"];
    }
}
