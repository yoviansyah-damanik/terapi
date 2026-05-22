<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateUsgData extends Command
{
    protected $signature   = 'usg:migrate-old-to-new {--dry-run : Tampilkan rencana tanpa menyimpan data}';
    protected $description = 'Pindahkan data USG dari tabel lama ke tabel baru dengan noorder';

    private bool $dryRun = false;

    private array $configs = [
        [
            'jenis'      => 'obstetri',
            'old'        => 'hasil_pemeriksaan_usg',
            'new'        => 'hasil_pemeriksaan_usg_new',
            'old_gambar' => 'hasil_pemeriksaan_usg_gambar',
            'new_gambar' => 'hasil_pemeriksaan_usg_gambar_new',
            'fields'     => [
                'tanggal', 'kd_dokter', 'diagnosa_klinis', 'kiriman_dari',
                'hta', 'kantong_gestasi', 'ukuran_bokongkepala', 'jenis_prestasi',
                'diameter_biparietal', 'panjang_femur', 'lingkar_abdomen', 'tafsiran_berat_janin',
                'usia_kehamilan', 'plasenta_berimplatansi', 'derajat_maturitas',
                'jumlah_air_ketuban', 'indek_cairan_ketuban', 'kelainan_kongenital',
                'peluang_sex', 'kesimpulan',
            ],
        ],
        [
            'jenis'      => 'gynecologi',
            'old'        => 'hasil_pemeriksaan_usg_gynecologi',
            'new'        => 'hasil_pemeriksaan_usg_gynecologi_new',
            'old_gambar' => 'hasil_pemeriksaan_usg_gynecologi_gambar',
            'new_gambar' => 'hasil_pemeriksaan_usg_gynecologi_gambar_new',
            'fields'     => [
                'tanggal', 'kd_dokter', 'diagnosa_klinis', 'kiriman_dari',
                'uterus', 'parametrium', 'ovarium', 'doppler', 'kesimpulan',
            ],
        ],
    ];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('=== DRY RUN — tidak ada data yang disimpan ===');
        }

        $conn = DB::connection('simrs');

        foreach ($this->configs as $cfg) {
            $this->migrateType($conn, $cfg);
        }

        $this->newLine();
        $this->info('Selesai.');

        return Command::SUCCESS;
    }

    private function migrateType($conn, array $cfg): void
    {
        $label = strtoupper($cfg['jenis']);
        $this->newLine();
        $this->line("── Migrasi <fg=cyan>{$label}</> ──────────────────────────────────");

        $records = $conn->table($cfg['old'])
            ->orderBy('no_rawat')
            ->orderBy('tanggal')
            ->get();

        $total    = $records->count();
        $inserted = 0;
        $skipped  = 0;
        $gambar   = 0;

        $this->line("  Ditemukan {$total} record di `{$cfg['old']}`.");

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('memulai...');
        $bar->start();

        foreach ($records as $record) {
            $tanggal = Carbon::parse($record->tanggal);
            $ymd     = $tanggal->format('Ymd');

            // Cek apakah sudah di-migrasi
            $exists = $conn->table($cfg['new'])
                ->where('no_rawat', $record->no_rawat)
                ->whereDate('tanggal', $tanggal->toDateString())
                ->exists();

            if ($exists) {
                $skipped++;
                $bar->setMessage("skip {$record->no_rawat}");
                $bar->advance();
                continue;
            }

            // Hitung urutan per tanggal pada tabel baru + permintaan_usg baru hari ini
            $existingCount = $conn->table($cfg['new'])
                ->where('noorder', 'like', "US{$ymd}%")
                ->count();

            $noorder = 'US' . $ymd . str_pad($existingCount + 1, 6, '0', STR_PAD_LEFT);

            $bar->setMessage($noorder);

            if (!$this->dryRun) {
                // 1. Insert permintaan_usg
                $conn->table('permintaan_usg')->insertOrIgnore([
                    'noorder'           => $noorder,
                    'no_rawat'          => $record->no_rawat,
                    'jenis_permintaan'  => $cfg['jenis'],
                    'waktu_permintaan'  => $record->tanggal,
                    'waktu_hasil'       => $record->tanggal,
                ]);

                // 2. Insert hasil baru
                $row = ['noorder' => $noorder, 'no_rawat' => $record->no_rawat];
                foreach ($cfg['fields'] as $field) {
                    $row[$field] = $record->{$field} ?? null;
                }
                $conn->table($cfg['new'])->insert($row);

                // 3. Salin gambar (jika ada)
                $gambarRecords = $conn->table($cfg['old_gambar'])
                    ->where('no_rawat', $record->no_rawat)
                    ->get();

                foreach ($gambarRecords as $g) {
                    $conn->table($cfg['new_gambar'])->insertOrIgnore([
                        'no_rawat' => $g->no_rawat,
                        'noorder'  => $noorder,
                        'photo'    => $g->photo,
                    ]);
                    $gambar++;
                }
            }

            $inserted++;
            $bar->advance();
        }

        $bar->setMessage('selesai');
        $bar->finish();
        $this->newLine();

        $this->table(
            ['Status', 'Jumlah'],
            [
                ['Inserted', $inserted],
                ['Skipped',  $skipped],
                ['Gambar',   $gambar],
            ]
        );
    }
}
