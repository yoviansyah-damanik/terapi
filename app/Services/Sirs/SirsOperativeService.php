<?php

namespace App\Services\Sirs;

use App\Helpers\SirsHelper;
use Illuminate\Support\Facades\DB;

class SirsOperativeService
{
    /** RL 3.11 - Gigi dan Mulut (Tahunan) */
    public function getRL311(int $tahun): array
    {
        $rows = DB::connection('simrs')->select("
            SELECT
                CASE WHEN rp.status_lanjut = 'Ranap' THEN 'ranap' ELSE 'ralan' END as tipe,
                pa.jk,
                COUNT(DISTINCT rp.no_rawat) as jumlah
            FROM reg_periksa rp
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            INNER JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
            WHERE YEAR(rp.tgl_registrasi) = ?
            AND (UPPER(pol.nm_poli) LIKE '%GIGI%' OR UPPER(pol.nm_poli) LIKE '%MULUT%')
            GROUP BY tipe, pa.jk
        ", [$tahun]);

        $data = ['ralan' => SirsHelper::emptyLPTotal(), 'ranap' => SirsHelper::emptyLPTotal()];
        foreach ($rows as $row) {
            $jk = SirsHelper::jkKey($row->jk);
            $data[$row->tipe][$jk] += $row->jumlah;
            $data[$row->tipe]['total'] = $data[$row->tipe]['l'] + $data[$row->tipe]['p'];
        }

        return $data;
    }

    /** RL 3.12 - Pembedahan (Bulanan) */
    public function getRL312(int $tahun, int $bulan): array
    {
        $range = SirsHelper::getDateRange($tahun, $bulan);

        $rows = DB::connection('simrs')->select("
            SELECT
                CASE
                    WHEN o.kategori = 'Besar' THEN 'besar'
                    WHEN o.kategori = 'Sedang' THEN 'sedang'
                    WHEN o.kategori = 'Kecil' THEN 'kecil'
                    ELSE 'khusus'
                END as kategori,
                pa.jk,
                COUNT(DISTINCT o.no_rawat) as jumlah
            FROM operasi o
            INNER JOIN reg_periksa rp ON o.no_rawat = rp.no_rawat
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            WHERE o.tgl_operasi BETWEEN ? AND ?
            GROUP BY kategori, pa.jk
        ", [$range['start'], $range['end']]);

        $data = [];
        foreach (['besar', 'sedang', 'kecil', 'khusus'] as $kat) {
            $data[$kat] = SirsHelper::emptyLPTotal();
        }

        foreach ($rows as $row) {
            $jk = SirsHelper::jkKey($row->jk);
            if (isset($data[$row->kategori])) {
                $data[$row->kategori][$jk] += $row->jumlah;
                $data[$row->kategori]['total'] = $data[$row->kategori]['l'] + $data[$row->kategori]['p'];
            }
        }

        return $data;
    }

    /** RL 3.13 - Rehabilitasi Medik (Tahunan) */
    public function getRL313(int $tahun): array
    {
        $rows = DB::connection('simrs')->select("
            SELECT pa.jk, COUNT(DISTINCT rp.no_rawat) as jumlah
            FROM reg_periksa rp
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            INNER JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
            WHERE YEAR(rp.tgl_registrasi) = ?
            AND (UPPER(pol.nm_poli) LIKE '%REHAB%' OR UPPER(pol.nm_poli) LIKE '%FISIOTERAPI%')
            GROUP BY pa.jk
        ", [$tahun]);

        $data = SirsHelper::emptyLPTotal();
        foreach ($rows as $row) {
            $data[SirsHelper::jkKey($row->jk)] += $row->jumlah;
        }
        $data['total'] = $data['l'] + $data['p'];

        return $data;
    }

    /** RL 3.15 - Kesehatan Jiwa (Tahunan) */
    public function getRL315(int $tahun): array
    {
        $rows = DB::connection('simrs')->select("
            SELECT
                CASE WHEN rp.status_lanjut = 'Ranap' THEN 'ranap' ELSE 'ralan' END as tipe,
                pa.jk,
                COUNT(DISTINCT rp.no_rawat) as jumlah
            FROM reg_periksa rp
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            INNER JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
            WHERE YEAR(rp.tgl_registrasi) = ?
            AND (UPPER(pol.nm_poli) LIKE '%JIWA%' OR UPPER(pol.nm_poli) LIKE '%PSIKIATRI%')
            GROUP BY tipe, pa.jk
        ", [$tahun]);

        $data = ['ralan' => SirsHelper::emptyLPTotal(), 'ranap' => SirsHelper::emptyLPTotal()];
        foreach ($rows as $row) {
            $jk = SirsHelper::jkKey($row->jk);
            $data[$row->tipe][$jk] += $row->jumlah;
            $data[$row->tipe]['total'] = $data[$row->tipe]['l'] + $data[$row->tipe]['p'];
        }

        return $data;
    }

    /** RL 3.16 - Keluarga Berencana (Tahunan) */
    public function getRL316(int $tahun): array
    {
        $rows = DB::connection('simrs')->select("
            SELECT pa.jk, COUNT(DISTINCT rp.no_rawat) as jumlah
            FROM reg_periksa rp
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            INNER JOIN diagnosa_pasien dp ON rp.no_rawat = dp.no_rawat
            WHERE YEAR(rp.tgl_registrasi) = ?
            AND dp.kd_penyakit LIKE 'Z30%'
            GROUP BY pa.jk
        ", [$tahun]);

        $data = SirsHelper::emptyLPTotal();
        foreach ($rows as $row) {
            $data[SirsHelper::jkKey($row->jk)] += $row->jumlah;
        }
        $data['total'] = $data['l'] + $data['p'];

        return $data;
    }
}
