<?php

namespace App\Jobs;

use App\Models\Bpjs\BpjsObservationRadiology;
use App\Models\Simrs\JnsPerawatanRadiologi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncBpjsObservationRadiologiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct()
    {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        Log::info('SyncBpjsObservationRadiologies: Starting sync');

        $existingCodes = BpjsObservationRadiology::pluck('local_code')->flip()->toArray();
        $now = now();
        $inserted = 0;

        JnsPerawatanRadiologi::orderBy('kd_jenis_prw')
            ->chunk(500, function ($items) use ($existingCodes, $now, &$inserted) {
                $rows = $items
                    ->reject(fn($item) => isset($existingCodes[$item->kd_jenis_prw]))
                    ->map(fn($item) => [
                        'id'         => (string) Str::orderedUuid(),
                        'local_code' => $item->kd_jenis_prw,
                        'name'       => $item->nm_perawatan,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->values()->toArray();

                if (!empty($rows)) {
                    BpjsObservationRadiology::insertOrIgnore($rows);
                    $inserted += count($rows);
                }
            });

        Log::info('SyncBpjsObservationRadiologies: Done', ['inserted' => $inserted]);
    }

    public function tags(): array
    {
        return ['bpjs', 'bpjs-observation-radiologies-sync'];
    }
}
