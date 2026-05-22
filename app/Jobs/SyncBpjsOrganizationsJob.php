<?php

namespace App\Jobs;

use App\Models\Bpjs\BpjsOrganization;
use App\Models\Simrs\Departemen;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncBpjsOrganizationsJob implements ShouldQueue
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
        Log::info('SyncBpjsOrganizations: Starting sync');

        $existing = BpjsOrganization::pluck('identifier')
            ->map(fn($v) => strtoupper((string) $v))
            ->flip()
            ->toArray();

        $now      = now();
        $inserted = 0;

        Departemen::orderBy('dep_id')
            ->chunk(500, function ($depts) use ($existing, $now, &$inserted) {
                $rows = $depts
                    ->reject(fn($d) => isset($existing[strtoupper((string) $d->dep_id)]))
                    ->map(fn($d) => [
                        'id'         => (string) Str::orderedUuid(),
                        'identifier' => $d->dep_id,
                        'name'       => $d->nama,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->values()->toArray();

                if (!empty($rows)) {
                    BpjsOrganization::insertOrIgnore($rows);
                    $inserted += count($rows);
                }
            });

        Log::info('SyncBpjsOrganizations: Done', ['inserted' => $inserted]);
    }

    public function tags(): array
    {
        return ['bpjs', 'bpjs-organizations-sync'];
    }
}
