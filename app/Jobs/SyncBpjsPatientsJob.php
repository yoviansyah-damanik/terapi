<?php

namespace App\Jobs;

use App\Models\Bpjs\BpjsPatient;
use App\Models\Simrs\Pasien as SimrsPatient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncBpjsPatientsJob implements ShouldQueue
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
        Log::info('SyncBpjsPatients: Starting sync');

        $existingNiks = BpjsPatient::pluck('nik')->toArray();

        $now = now();
        $inserted = 0;

        SimrsPatient::whereNotNull('no_ktp')
            ->where('no_ktp', '!=', '')
            ->whereNotIn('no_ktp', $existingNiks)
            ->chunkById(500, function ($patients) use ($now, &$inserted) {
                $rows = $patients->map(fn($p) => [
                    'id'         => (string) Str::orderedUuid(),
                    'nik'        => $p->no_ktp,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->toArray();

                BpjsPatient::insertOrIgnore($rows);
                $inserted += count($rows);
            }, 'no_rkm_medis');

        Log::info('SyncBpjsPatients: Done', ['inserted' => $inserted]);
    }

    public function tags(): array
    {
        return ['bpjs', 'bpjs-patient-sync'];
    }
}
