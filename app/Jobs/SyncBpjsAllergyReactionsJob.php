<?php

namespace App\Jobs;

use App\Models\Bpjs\BpjsAllergyReaction;
use App\Models\Simrs\AlergiReaksi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncBpjsAllergyReactionsJob implements ShouldQueue
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
        Log::info('SyncBpjsAllergyReactions: Starting sync');

        $existingCodes = BpjsAllergyReaction::pluck('local_code')->flip()->toArray();
        $now = now();
        $inserted = 0;

        AlergiReaksi::orderBy('id')
            ->chunk(500, function ($items) use ($existingCodes, $now, &$inserted) {
                $rows = $items
                    ->reject(fn($item) => isset($existingCodes[(string) $item->id]))
                    ->map(fn($item) => [
                        'id'         => (string) Str::orderedUuid(),
                        'local_code' => (string) $item->id,
                        'name'       => $item->nama_reaksi,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->values()->toArray();

                if (!empty($rows)) {
                    BpjsAllergyReaction::insertOrIgnore($rows);
                    $inserted += count($rows);
                }
            });

        Log::info('SyncBpjsAllergyReactions: Done', ['inserted' => $inserted]);
    }

    public function tags(): array
    {
        return ['bpjs', 'bpjs-allergy-reactions-sync'];
    }
}
