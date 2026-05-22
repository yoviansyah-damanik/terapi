<?php

namespace App\Jobs;

use App\Models\Bpjs\BpjsAntreanBooking;
use App\Models\Bpjs\BpjsAntreanRegistration;
use App\Models\Bpjs\BpjsLog;
use App\Models\Simrs\RegPeriksa;
use App\Services\Bpjs\AntreanOnlineService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableTrait;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class PullAntreanJob implements ShouldQueue
{
    use QueueableTrait, InteractsWithQueue, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public readonly array $dates,
        public readonly int $perDateTimeout = 15,
    ) {
        $this->onQueue('messaging');
    }

    public function handle(): void
    {
        $service = (new AntreanOnlineService())->withTimeout($this->perDateTimeout);

        foreach ($this->dates as $date) {
            $this->pullDate($service, $date);
        }
    }

    private function pullDate(AntreanOnlineService $service, string $date): void
    {
        $endpoint = $service->endpointAntreanByTanggal($date);

        try {
            $result = $service->getAntreanByTanggal($date);

            if (($result['code'] ?? '') !== '200') {
                $errMsg = $result['message'] ?? 'Kode respons tidak 200';
                BpjsLog::record(
                    service: 'antrean',
                    status: 'failed',
                    method: 'GET',
                    endpoint: $endpoint,
                    responseStatus: (int) ($result['code'] ?? 0),
                    responsePayload: $result,
                    errorMessage: "[{$date}] {$errMsg}",
                );
                return;
            }

            $items = is_array($result['response'] ?? null) ? $result['response'] : [];

            $upsertData = collect($items)
                ->filter(fn($item) => !empty($item['kodebooking']))
                ->map(fn($item) => [
                    'kode_booking' => $item['kodebooking'],
                    'tanggal' => $date,
                    'kd_poli' => $item['kodepoli'] ?? null,
                    'kd_dokter' => $item['kodedokter'] ?? null,
                    'jam_praktek' => $item['jampraktek'] ?? null,
                    'nik' => $item['nik'] ?? null,
                    'no_kartu' => $item['nokapst'] ?? null,
                    'no_hp' => $item['nohp'] ?? null,
                    'no_rm' => $item['norekammedis'] ?? null,
                    'jenis_kunjungan' => (string) ($item['jeniskunjungan'] ?? ''),
                    'no_referensi' => $item['nomorreferensi'] ?? null,
                    'sumber_data' => $item['sumberdata'] ?? null,
                    'is_peserta' => (bool) ($item['ispeserta'] ?? false),
                    'no_antrean' => $item['noantrean'] ?? null,
                    'estimasi_timestamp' => isset($item['estimasidilayani']) && $item['estimasidilayani'] ? (int) $item['estimasidilayani'] : null,
                    'status' => $item['status'] ?? null,
                    'created_time_timestamp' => isset($item['createdtime']) && $item['createdtime'] ? (int) $item['createdtime'] : null,
                ])
                ->values()
                ->toArray();

            if (!empty($upsertData)) {
                BpjsAntreanBooking::upsert(
                    $upsertData,
                    ['kode_booking'],
                    ['tanggal', 'kd_poli', 'kd_dokter', 'jam_praktek', 'nik', 'no_kartu', 'no_hp', 'no_rm', 'jenis_kunjungan', 'no_referensi', 'sumber_data', 'is_peserta', 'no_antrean', 'estimasi_timestamp', 'status', 'created_time_timestamp'],
                );
            }

            BpjsLog::record(
                service: 'antrean',
                status: 'success',
                method: 'GET',
                endpoint: $endpoint,
                responseStatus: 200,
                responsePayload: ['date' => $date, 'count' => count($upsertData)],
                success: true,
            );

            $this->saveRegistrations($date);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            $errMsg = "[{$date}] Timeout setelah {$this->perDateTimeout}d";
            BpjsLog::record(service: 'antrean', status: 'failed', method: 'GET', endpoint: $endpoint, errorMessage: $errMsg);
        } catch (\Exception $e) {
            BpjsLog::record(service: 'antrean', status: 'failed', method: 'GET', endpoint: $endpoint, errorMessage: "[{$date}] " . Str::limit($e->getMessage(), 200));
        }
    }

    private function saveRegistrations(string $date): void
    {
        $exPolyclinics = array_filter(array_map('trim', explode(',', env('ANTROL_EX_POLYCLINICS', ''))));
        $exDoctors = array_filter(array_map('trim', explode(',', env('ANTROL_EX_DOCTORS', ''))));

        try {
            $query = RegPeriksa::bpjsOnly()
                ->whereNotLike('no_rawat', 'C%')
                ->with(['poliklinik', 'dokter'])
                ->whereDate('tgl_registrasi', $date)
                ->select(['no_rawat', 'kd_poli', 'kd_dokter', 'status_lanjut']);

            if (!empty($exPolyclinics)) {
                $query->whereNotIn('kd_poli', $exPolyclinics);
            }
            if (!empty($exDoctors)) {
                $query->whereNotIn('kd_dokter', $exDoctors);
            }

            $regs = $query->get();
            if ($regs->isEmpty()) {
                return;
            }

            BpjsAntreanRegistration::upsert(
                $regs->map(fn($r) => [
                    'no_rawat' => $r->no_rawat,
                    'tanggal' => $date,
                    'kd_poli' => $r->kd_poli,
                    'nm_poli' => $r->poliklinik->nm_poli,
                    'kd_dokter' => $r->kd_dokter,
                    'nm_dokter' => $r->dokter->nm_dokter,
                    'status_lanjut' => $r->status_lanjut,
                ])->toArray(),
                ['no_rawat'],
                ['tanggal', 'kd_poli', 'nm_poli', 'kd_dokter', 'nm_dokter', 'status_lanjut'],
            );
        } catch (\Exception) {
            // Koneksi SIMRS tidak tersedia, skip
        }
    }
}
