<?php

namespace App\Jobs;

use App\Models\Bpjs\BpjsIcd10;
use App\Models\Terminology\Icd10;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncBpjsIcd10Job implements ShouldQueue
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
        Log::info('SyncBpjsIcd10: Starting sync');

        $existingCodes = BpjsIcd10::pluck('code')->flip()->toArray();
        $now = now();
        $inserted = 0;

        Icd10::select('code', DB::raw('MAX(display) as display'))
            ->groupBy('code')
            ->orderBy('code')
            ->chunk(500, function ($items) use ($existingCodes, $now, &$inserted) {
                $rows = $items
                    ->reject(fn($item) => isset($existingCodes[$item->code]))
                    ->map(fn($item) => [
                        'id'         => (string) Str::orderedUuid(),
                        'code'       => $item->code,
                        'display'    => $item->display,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->values()->toArray();

                if (!empty($rows)) {
                    BpjsIcd10::insertOrIgnore($rows);
                    $inserted += count($rows);
                }
            });

        Log::info('SyncBpjsIcd10: Done', ['inserted' => $inserted]);
    }

    public function tags(): array
    {
        return ['bpjs', 'bpjs-icd10-sync'];
    }
}
