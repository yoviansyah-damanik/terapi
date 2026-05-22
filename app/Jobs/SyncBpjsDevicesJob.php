<?php

namespace App\Jobs;

use App\Models\Bpjs\BpjsDevice;
use App\Models\Simrs\InventarisBarang;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncBpjsDevicesJob implements ShouldQueue
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
        Log::info('SyncBpjsDevices: Starting sync');

        $existingCodes = BpjsDevice::pluck('local_code')->flip()->toArray();
        $now = now();
        $inserted = 0;

        InventarisBarang::where('id_jenis', 'ALKES')
            ->orderBy('kode_barang')
            ->chunk(500, function ($items) use ($existingCodes, $now, &$inserted) {
                $rows = $items
                    ->reject(fn($item) => isset($existingCodes[$item->kode_barang]))
                    ->map(fn($item) => [
                        'id'         => (string) Str::orderedUuid(),
                        'local_code' => $item->kode_barang,
                        'name'       => $item->nama_barang,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->values()->toArray();

                if (!empty($rows)) {
                    BpjsDevice::insertOrIgnore($rows);
                    $inserted += count($rows);
                }
            });

        Log::info('SyncBpjsDevices: Done', ['inserted' => $inserted]);
    }

    public function tags(): array
    {
        return ['bpjs', 'bpjs-devices-sync'];
    }
}
