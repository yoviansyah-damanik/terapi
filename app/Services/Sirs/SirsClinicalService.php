<?php

namespace App\Services\Sirs;

use App\Helpers\SirsHelper;
use Illuminate\Support\Facades\DB;

class SirsClinicalService
{
    /** RL 3.6 - Pelayanan Kebidanan */
    public function getRL36(int $tahun, int $bulan): array
    {
        $range = SirsHelper::getDateRange($tahun, $bulan);

        $persalinan = DB::connection('simrs')->select("
            SELECT
                CASE
                    WHEN dp.kd_penyakit LIKE 'O80%' THEN 'normal'
                    WHEN dp.kd_penyakit LIKE 'O82%' THEN 'sectio'
                    WHEN dp.kd_penyakit LIKE 'O81%' THEN 'buatan'
                    ELSE 'lainnya'
                END as jenis,
                pa.jk as jk_bayi,
                COUNT(DISTINCT dp.no_rawat) as jumlah
            FROM diagnosa_pasien dp
            INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            INNER JOIN kamar_inap ki ON dp.no_rawat = ki.no_rawat
            WHERE dp.status = 'Ranap'
            AND MONTH(ki.tgl_keluar) = ? AND YEAR(ki.tgl_keluar) = ?
            AND (dp.kd_penyakit LIKE 'O8%')
            GROUP BY jenis, pa.jk
        ", [$bulan, $tahun]);

        $data = array_fill_keys(
            ['normal', 'sectio', 'buatan', 'lainnya'],
            SirsHelper::emptyLPTotal()
        );

        foreach ($persalinan as $row) {
            $jenis = $row->jenis;
            if (isset($data[$jenis])) {
                $jk = strtolower($row->jk_bayi);
                if (in_array($jk, ['l', 'p'])) {
                    $data[$jenis][$jk] += $row->jumlah;
                }
                $data[$jenis]['total'] = $data[$jenis]['l'] + $data[$jenis]['p'];
            }
        }

        return $data;
    }

    /** RL 3.7 - Neonatal/Bayi/Balita */
    public function getRL37(int $tahun, int $bulan): array
    {
        $range = SirsHelper::getDateRange($tahun, $bulan);

        $rows = DB::connection('simrs')->select("
            SELECT
                CASE
                    WHEN DATEDIFF(ki.tgl_keluar, pa.tgl_lahir) <= 28 THEN 'neonatal'
                    WHEN DATEDIFF(ki.tgl_keluar, pa.tgl_lahir) <= 365 THEN 'bayi'
                    WHEN DATEDIFF(ki.tgl_keluar, pa.tgl_lahir) <= 1825 THEN 'balita'
                    ELSE 'lainnya'
                END as kategori,
                pa.jk,
                ki.stts_pulang,
                COUNT(DISTINCT ki.no_rawat) as jumlah
            FROM kamar_inap ki
            INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            WHERE ki.tgl_keluar BETWEEN ? AND ?
            AND DATEDIFF(ki.tgl_keluar, pa.tgl_lahir) <= 1825
            GROUP BY kategori, pa.jk, ki.stts_pulang
        ", [$range['start'], $range['end']]);

        $data = [];
        foreach (['neonatal', 'bayi', 'balita'] as $kat) {
            $data[$kat] = ['hidup_l' => 0, 'hidup_p' => 0, 'mati_l' => 0, 'mati_p' => 0, 'total' => 0];
        }

        foreach ($rows as $row) {
            if (!isset($data[$row->kategori]))
                continue;
            $jk = strtolower($row->jk);
            if ($row->stts_pulang === 'Meninggal') {
                $data[$row->kategori]["mati_{$jk}"] += $row->jumlah;
            } else {
                $data[$row->kategori]["hidup_{$jk}"] += $row->jumlah;
            }
            $data[$row->kategori]['total'] += $row->jumlah;
        }

        return $data;
    }

    /** RL 3.8 - Laboratorium */
    public function getRL38(int $tahun, int $bulan): array
    {
        $range = SirsHelper::getDateRange($tahun, $bulan);

        $rows = DB::connection('simrs')->select("
            SELECT
                CASE WHEN pl.status = 'Ranap' THEN 'ranap' ELSE 'ralan' END as tipe,
                COUNT(DISTINCT pl.no_rawat) as jumlah_pasien,
                COUNT(*) as jumlah_pemeriksaan
            FROM periksa_lab pl
            WHERE pl.tgl_periksa BETWEEN ? AND ?
            GROUP BY tipe
        ", [$range['start'], $range['end']]);

        $data = ['ralan' => ['pasien' => 0, 'pemeriksaan' => 0], 'ranap' => ['pasien' => 0, 'pemeriksaan' => 0]];
        foreach ($rows as $row) {
            $data[$row->tipe] = ['pasien' => $row->jumlah_pasien, 'pemeriksaan' => $row->jumlah_pemeriksaan];
        }

        return $data;
    }

    /** RL 3.9 - Radiologi */
    public function getRL39(int $tahun, int $bulan): array
    {
        $range = SirsHelper::getDateRange($tahun, $bulan);

        $rows = DB::connection('simrs')->select("
            SELECT
                CASE WHEN pr.status = 'Ranap' THEN 'ranap' ELSE 'ralan' END as tipe,
                COUNT(DISTINCT pr.no_rawat) as jumlah_pasien,
                COUNT(*) as jumlah_pemeriksaan
            FROM periksa_radiologi pr
            WHERE pr.tgl_periksa BETWEEN ? AND ?
            GROUP BY tipe
        ", [$range['start'], $range['end']]);

        $data = ['ralan' => ['pasien' => 0, 'pemeriksaan' => 0], 'ranap' => ['pasien' => 0, 'pemeriksaan' => 0]];
        foreach ($rows as $row) {
            $data[$row->tipe] = ['pasien' => $row->jumlah_pasien, 'pemeriksaan' => $row->jumlah_pemeriksaan];
        }

        return $data;
    }

    /** RL 3.10 - Rujukan */
    public function getRL310(int $tahun, int $bulan): array
    {
        $range = SirsHelper::getDateRange($tahun, $bulan);

        // Rujukan masuk
        $masuk = DB::connection('simrs')->select("
            SELECT COUNT(DISTINCT rm.no_rawat) as jumlah
            FROM rujuk_masuk rm
            INNER JOIN reg_periksa rp ON rm.no_rawat = rp.no_rawat
            WHERE rp.tgl_registrasi BETWEEN ? AND ?
        ", [$range['start'], $range['end']]);

        // Rujukan keluar
        $keluar = DB::connection('simrs')->select("
            SELECT COUNT(DISTINCT r.no_rawat) as jumlah
            FROM rujuk r
            INNER JOIN reg_periksa rp ON r.no_rawat = rp.no_rawat
            WHERE rp.tgl_registrasi BETWEEN ? AND ?
        ", [$range['start'], $range['end']]);

        return [
            'masuk' => $masuk[0]->jumlah ?? 0,
            'keluar' => $keluar[0]->jumlah ?? 0,
        ];
    }

    /** RL 3.14 - Pelayanan Khusus */
    public function getRL314(int $tahun, int $bulan): array
    {
        $range = SirsHelper::getDateRange($tahun, $bulan);

        // Hemodialisa
        $hemodialisa = DB::connection('simrs')->select("
            SELECT COUNT(DISTINCT rp.no_rawat) as jumlah
            FROM reg_periksa rp
            INNER JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
            WHERE rp.tgl_registrasi BETWEEN ? AND ?
            AND (UPPER(pol.nm_poli) LIKE '%HEMODIAL%' OR UPPER(pol.nm_poli) LIKE '%HD%')
        ", [$range['start'], $range['end']]);

        // Operasi
        $operasi = DB::connection('simrs')->select("
            SELECT COUNT(DISTINCT o.no_rawat) as jumlah
            FROM operasi o
            WHERE o.tgl_operasi BETWEEN ? AND ?
        ", [$range['start'], $range['end']]);

        return [
            'hemodialisa' => $hemodialisa[0]->jumlah ?? 0,
            'operasi' => $operasi[0]->jumlah ?? 0,
        ];
    }
}
