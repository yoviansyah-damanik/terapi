<?php

namespace App\Jobs;

use App\Models\Bpjs\BpjsProcedure;
use App\Models\Simrs\JnsPerawatan;
use App\Models\Simrs\JnsPerawatanInap;
use App\Models\Simrs\JnsPerawatanLab;
use App\Models\Simrs\JnsPerawatanRadiologi;
use App\Models\Simrs\TemplateLaboratorium;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncBpjsProceduresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    /**
     * @param string $type 'all' | 'ralan' | 'ranap' | 'lab' | 'item_lab' | 'rad'
     */
    public function __construct(public string $type = 'all')
    {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        $types = $this->type === 'all'
            ? ['ralan', 'ranap', 'lab', 'item_lab', 'rad', 'operasi']
            : [$this->type];

        $totalInserted = 0;

        foreach ($types as $type) {
            $totalInserted += $this->syncType($type);
        }

        Log::info('SyncBpjsProcedures: Done', ['type' => $this->type, 'inserted' => $totalInserted]);
    }

    private function syncType(string $type): int
    {
        $existingCodes = BpjsProcedure::where('type', $type)->pluck('local_code')->flip()->toArray();
        $now = now();
        $inserted = 0;

        if ($type === 'item_lab') {
            TemplateLaboratorium::select('id_template', \DB::raw('MAX(Pemeriksaan) as Pemeriksaan'))
                ->groupBy('id_template')
                ->orderBy('id_template')
                ->chunk(500, function ($items) use ($type, $existingCodes, $now, &$inserted) {
                    $rows = $items
                        ->reject(fn($item) => isset($existingCodes[$item->id_template]))
                        ->map(fn($item) => [
                            'id'         => (string) Str::orderedUuid(),
                            'type'       => $type,
                            'local_code' => $item->id_template,
                            'name'       => $item->Pemeriksaan ?? $item->id_template,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->values()->toArray();

                    if (!empty($rows)) {
                        BpjsProcedure::insertOrIgnore($rows);
                        $inserted += count($rows);
                    }
                });
        } else {
            $modelClass = match ($type) {
                'ralan'   => JnsPerawatan::class,
                'ranap'   => JnsPerawatanInap::class,
                'lab'     => JnsPerawatanLab::class,
                'rad'     => JnsPerawatanRadiologi::class,
                'operasi' => \App\Models\Simrs\PaketOperasi::class,
            };

            $pk = $type === 'operasi' ? 'kode_paket' : 'kd_jenis_prw';

            $modelClass::orderBy('nm_perawatan')
                ->chunk(500, function ($items) use ($type, $existingCodes, $now, &$inserted, $pk) {
                    $rows = $items
                        ->reject(fn($item) => isset($existingCodes[$item->$pk]))
                        ->map(fn($item) => [
                            'id'         => (string) Str::orderedUuid(),
                            'type'       => $type,
                            'local_code' => $item->$pk,
                            'name'       => $item->nm_perawatan,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->values()->toArray();

                    if (!empty($rows)) {
                        BpjsProcedure::insertOrIgnore($rows);
                        $inserted += count($rows);
                    }
                });
        }

        return $inserted;
    }

    public function tags(): array
    {
        return ['bpjs', 'bpjs-procedures-sync'];
    }
}
