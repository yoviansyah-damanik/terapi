<?php

namespace App\Jobs;

use App\Models\Simrs\KelompokUmur;
use App\Models\Simrs\Pasien;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HitungKelompokUmurJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 3600;

    public function __construct()
    {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        Log::info('HitungKelompokUmur: mulai');

        $kelompoks = KelompokUmur::orderBy('urut')->get();
        $today     = today()->toDateString();
        $updated   = 0;

        Pasien::whereNotNull('tgl_lahir')
            ->chunkById(500, function ($pasiens) use ($kelompoks, $today, &$updated) {
                $rows = [];

                foreach ($pasiens as $p) {
                    $hari     = Carbon::parse($p->tgl_lahir)->diffInDays(Carbon::today());
                    $kelompok = KelompokUmur::matchFromCollection($kelompoks, $hari);
                    if (!$kelompok) continue;

                    $rows[] = [
                        'no_rkm_medis'      => $p->no_rkm_medis,
                        'kode_kelompok_umur' => $kelompok->kode,
                        'umur_hari'         => $hari,
                        'tanggal_hitung'    => $today,
                    ];
                }

                if (!empty($rows)) {
                    DB::connection('simrs')
                        ->table('kelompok_umur_pasien')
                        ->upsert($rows, ['no_rkm_medis'], ['kode_kelompok_umur', 'umur_hari', 'tanggal_hitung']);
                    $updated += count($rows);
                }
            }, 'no_rkm_medis');

        Log::info('HitungKelompokUmur: selesai', ['updated' => $updated]);
    }

    public function tags(): array
    {
        return ['simrs', 'kelompok-umur'];
    }
}
