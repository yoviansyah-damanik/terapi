<?php

namespace App\Jobs;

use App\Models\Bpjs\BpjsLog;
use App\Models\Simrs\RegPeriksa;
use App\Services\Bpjs\Erm\ErmBundleBuilder;
use App\Services\Bpjs\ErmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendBpjsErmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public readonly string $noRawat,
    ) {
        $this->onQueue('high');
    }

    public function handle(ErmService $service): void
    {
        Log::info('SendBpjsErm: Starting', ['no_rawat' => $this->noRawat]);

        $reg = RegPeriksa::with([
            'pasien', 'dokter', 'poliklinik', 'penjab',
            'bridgingSep', 'diagnosaPasien.penyakit',
            'prosedurPasien.icd9', 'kamarInap',
        ])->find($this->noRawat);

        if (!$reg) {
            Log::warning('SendBpjsErm: RegPeriksa not found', ['no_rawat' => $this->noRawat]);
            return;
        }

        if (!$reg->bridgingSep) {
            Log::warning('SendBpjsErm: SEP tidak ditemukan', ['no_rawat' => $this->noRawat]);
            BpjsLog::record(
                service: 'erm',
                status: 'failed',
                noRawat: $this->noRawat,
                errorMessage: 'Data SEP tidak ditemukan.',
            );
            return;
        }

        $bundle = (new ErmBundleBuilder())->build($reg);
        $noSep = $reg->bridgingSep->no_sep;
        $isRanap = $reg->status_lanjut === 'Ranap';
        $jnsPelayanan = $isRanap ? 1 : 2;
        $tglSep = $reg->bridgingSep->tglsep ?? now();
        $roomCode = $isRanap
            ? $reg->kamarInap->last()?->kd_kamar ?? $reg->kd_poli
            : $reg->kd_poli;

        $response = $service->insertRekamMedis(
            $reg->no_rawat,
            $noSep,
            $jnsPelayanan,
            (int) $tglSep->format('m'),
            (int) $tglSep->format('Y'),
            $roomCode,
            $reg->kd_dokter,
            $bundle,
        );

        $success = isset($response['code']) && $response['code'] == 200;

        Log::info('SendBpjsErm: Done', [
            'no_rawat' => $this->noRawat,
            'success' => $success,
            'code' => $response['code'] ?? null,
        ]);
    }

    public function failed(Throwable $e): void
    {
        BpjsLog::record(
            service: 'erm',
            status: 'failed',
            noRawat: $this->noRawat,
            errorMessage: $e->getMessage(),
        );

        Log::error('SendBpjsErm: Job failed', [
            'no_rawat' => $this->noRawat,
            'error' => $e->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['bpjs', 'bpjs-erm', "no-rawat:{$this->noRawat}"];
    }
}
