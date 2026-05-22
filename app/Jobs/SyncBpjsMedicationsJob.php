<?php

namespace App\Jobs;

use App\Models\Bpjs\BpjsMedication;
use App\Models\Simrs\DataBarang;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncBpjsMedicationsJob implements ShouldQueue
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
        Log::info('SyncBpjsMedications: Starting sync');

        $existingCodes = BpjsMedication::pluck('local_code')->flip()->toArray();
        $now = now();
        $inserted = 0;

        DataBarang::orderBy('kode_brng')
            ->chunk(500, function ($items) use ($existingCodes, $now, &$inserted) {
                $rows = $items
                    ->reject(fn($item) => isset($existingCodes[$item->kode_brng]))
                    ->map(fn($item) => [
                        'id'         => (string) Str::orderedUuid(),
                        'local_code' => $item->kode_brng,
                        'name'       => $item->nama_brng,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->values()->toArray();

                if (!empty($rows)) {
                    BpjsMedication::insertOrIgnore($rows);
                    $inserted += count($rows);
                }
            });

        Log::info('SyncBpjsMedications: Done', ['inserted' => $inserted]);
    }

    public function tags(): array
    {
        return ['bpjs', 'bpjs-medications-sync'];
    }
}
