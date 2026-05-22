<?php

namespace App\Jobs;

use App\Models\Bpjs\BpjsPractitioner;
use App\Models\Simrs\Dokter;
use App\Models\Simrs\Pegawai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncBpjsPractitionersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    /**
     * @param string|null $bidang
     *   null         = semua pegawai aktif
     *   'dokter'     = dari tabel dokter (identifier = kd_dokter)
     *   'Keperawatan'= bidang Keperawatan + Kebidanan
     *   'Non Medis'  = semua selain Medis/Keperawatan/Kebidanan/Penunjang Medis
     *   lainnya      = where('bidang', $bidang)
     */
    public function __construct(public readonly ?string $bidang = null)
    {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        Log::info('SyncBpjsPractitioners: Starting sync', ['bidang' => $this->bidang ?? 'all']);

        $existingIdentifiers = BpjsPractitioner::pluck('identifier')->toArray();
        $now = now();
        $inserted = 0;

        if ($this->bidang === 'dokter') {
            $this->syncDokter($existingIdentifiers, $now, $inserted);
        } else {
            $this->syncPegawai($existingIdentifiers, $now, $inserted);
        }

        Log::info('SyncBpjsPractitioners: Done', ['bidang' => $this->bidang ?? 'all', 'inserted' => $inserted]);
    }

    private function syncDokter(array $existingIdentifiers, $now, int &$inserted): void
    {
        Dokter::active()
            ->whereNotNull('kd_dokter')
            ->where('kd_dokter', '!=', '')
            ->whereNotIn('kd_dokter', $existingIdentifiers)
            ->chunkById(500, function ($doctors) use ($now, &$inserted) {
                $rows = $doctors->map(fn($doc) => [
                    'id'         => (string) Str::orderedUuid(),
                    'identifier' => $doc->kd_dokter,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->toArray();

                BpjsPractitioner::insertOrIgnore($rows);
                $inserted += count($rows);
            }, 'kd_dokter');
    }

    private function syncPegawai(array $existingIdentifiers, $now, int &$inserted): void
    {
        $query = Pegawai::where('stts_aktif', 'AKTIF')
            ->whereNotNull('nik')
            ->where('nik', '!=', '')
            ->whereNotIn('nik', $existingIdentifiers);

        if ($this->bidang === null) {
            // Sync semua
        } elseif ($this->bidang === 'Keperawatan') {
            $query->whereIn('bidang', ['Keperawatan', 'Kebidanan']);
        } elseif ($this->bidang === 'Non Medis') {
            $query->whereNotIn('bidang', ['Medis', 'Keperawatan', 'Kebidanan', 'Penunjang Medis']);
        } else {
            $query->where('bidang', $this->bidang);
        }

        $query->chunkById(500, function ($employees) use ($now, &$inserted) {
            $rows = $employees->map(fn($emp) => [
                'id'         => (string) Str::orderedUuid(),
                'identifier' => $emp->nik,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

            BpjsPractitioner::insertOrIgnore($rows);
            $inserted += count($rows);
        }, 'id');
    }

    public function tags(): array
    {
        return ['bpjs', 'bpjs-practitioner-sync', 'bidang:' . ($this->bidang ?? 'all')];
    }
}
