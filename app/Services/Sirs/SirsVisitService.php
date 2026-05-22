<?php

namespace App\Services\Sirs;

use App\Helpers\SirsHelper;
use Illuminate\Support\Facades\DB;

class SirsVisitService
{
    /** RL 3.3 - Pelayanan Rawat Darurat/IGD */
    public function getRL33(int $tahun, int $bulan): array
    {
        $range = SirsHelper::getDateRange($tahun, $bulan);

        // Kunjungan IGD per jenis kelamin
        $kunjungan = DB::connection('simrs')->select("
            SELECT pa.jk, COUNT(DISTINCT rp.no_rawat) as jumlah
            FROM reg_periksa rp
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            INNER JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
            WHERE rp.tgl_registrasi BETWEEN ? AND ?
            AND UPPER(pol.nm_poli) LIKE '%IGD%'
            GROUP BY pa.jk
        ", [$range['start'], $range['end']]);

        // Pasien diterima rawat inap dari IGD
        $diterima = DB::connection('simrs')->select("
            SELECT pa.jk, COUNT(DISTINCT rp.no_rawat) as jumlah
            FROM reg_periksa rp
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            INNER JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
            INNER JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
            WHERE rp.tgl_registrasi BETWEEN ? AND ?
            AND UPPER(pol.nm_poli) LIKE '%IGD%'
            GROUP BY pa.jk
        ", [$range['start'], $range['end']]);

        // Dirujuk
        $dirujuk = DB::connection('simrs')->select("
            SELECT pa.jk, COUNT(DISTINCT rp.no_rawat) as jumlah
            FROM reg_periksa rp
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            INNER JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
            LEFT JOIN rujuk r ON rp.no_rawat = r.no_rawat
            WHERE rp.tgl_registrasi BETWEEN ? AND ?
            AND UPPER(pol.nm_poli) LIKE '%IGD%'
            AND r.no_rawat IS NOT NULL
            GROUP BY pa.jk
        ", [$range['start'], $range['end']]);

        // Meninggal di IGD
        $meninggal = DB::connection('simrs')->select("
            SELECT pa.jk,
                CASE WHEN TIMESTAMPDIFF(HOUR, CONCAT(rp.tgl_registrasi, ' ', rp.jam_reg), CONCAT(pm.tanggal, ' ', pm.jam)) < 48 THEN 'kurang48' ELSE 'lebih48' END as waktu,
                COUNT(DISTINCT pm.no_rkm_medis) as jumlah
            FROM pasien_mati pm
            INNER JOIN pasien pa ON pm.no_rkm_medis = pa.no_rkm_medis
            INNER JOIN reg_periksa rp ON pm.no_rawat = rp.no_rawat
            INNER JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
            WHERE pm.tanggal BETWEEN ? AND ?
            AND UPPER(pol.nm_poli) LIKE '%IGD%'
            GROUP BY pa.jk, waktu
        ", [$range['start'], $range['end']]);

        return [
            'kunjungan' => collect($kunjungan)->keyBy('jk')->toArray(),
            'diterima' => collect($diterima)->keyBy('jk')->toArray(),
            'dirujuk' => collect($dirujuk)->keyBy('jk')->toArray(),
            'meninggal' => $meninggal,
        ];
    }

    /** RL 3.4 - Rekapitulasi Pengunjung */
    public function getRL34(int $tahun, int $bulan): array
    {
        $range = SirsHelper::getDateRange($tahun, $bulan);

        // Rawat jalan
        $rajal = DB::connection('simrs')->select("
            SELECT
                CASE WHEN rp.stts_daftar = 'Baru' THEN 'baru' ELSE 'lama' END as status,
                pa.jk,
                COUNT(DISTINCT rp.no_rawat) as jumlah
            FROM reg_periksa rp
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            WHERE rp.tgl_registrasi BETWEEN ? AND ?
            AND rp.status_lanjut = 'Ralan'
            GROUP BY status, pa.jk
        ", [$range['start'], $range['end']]);

        // Rawat inap
        $ranap = DB::connection('simrs')->select("
            SELECT pa.jk, COUNT(DISTINCT ki.no_rawat) as jumlah
            FROM kamar_inap ki
            INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            WHERE ki.tgl_masuk BETWEEN ? AND ?
            GROUP BY pa.jk
        ", [$range['start'], $range['end']]);

        // IGD
        $igd = DB::connection('simrs')->select("
            SELECT pa.jk, COUNT(DISTINCT rp.no_rawat) as jumlah
            FROM reg_periksa rp
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            INNER JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
            WHERE rp.tgl_registrasi BETWEEN ? AND ?
            AND UPPER(pol.nm_poli) LIKE '%IGD%'
            GROUP BY pa.jk
        ", [$range['start'], $range['end']]);

        return [
            'rajal' => $rajal,
            'ranap' => $ranap,
            'igd' => collect($igd)->keyBy('jk')->toArray(),
        ];
    }

    /** RL 3.5 - Kunjungan Rawat Jalan per Poliklinik */
    public function getRL35(int $tahun, int $bulan): array
    {
        $range = SirsHelper::getDateRange($tahun, $bulan);

        $rows = DB::connection('simrs')->select("
            SELECT pol.nm_poli,
                CASE WHEN rp.stts_daftar = 'Baru' THEN 'baru' ELSE 'lama' END as status,
                pa.jk,
                COUNT(DISTINCT rp.no_rawat) as jumlah
            FROM reg_periksa rp
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            INNER JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
            WHERE rp.tgl_registrasi BETWEEN ? AND ?
            AND rp.status_lanjut = 'Ralan'
            GROUP BY pol.nm_poli, status, pa.jk
            ORDER BY pol.nm_poli
        ", [$range['start'], $range['end']]);

        $data = [];
        foreach ($rows as $row) {
            $poli = $row->nm_poli;
            if (!isset($data[$poli])) {
                $data[$poli] = ['baru_l' => 0, 'baru_p' => 0, 'lama_l' => 0, 'lama_p' => 0, 'total' => 0];
            }
            $key = $row->status . '_' . strtolower($row->jk);
            if (isset($data[$poli][$key])) {
                $data[$poli][$key] += $row->jumlah;
            }
            $data[$poli]['total'] += $row->jumlah;
        }

        return $data;
    }

    /** RL 3.19 - Cara Bayar Pasien (Tahunan) */
    public function getRL319(int $tahun): array
    {
        $kategoriList = ['JKN', 'JAMKESDA', 'PEMDA_LAIN', 'SWASTA', 'KERINGANAN', 'GRATIS', 'SENDIRI'];

        $emptyRow = [
            'ranap_keluar' => 0,
            'ranap_lama' => 0,
            'ralan_lab' => 0,
            'ralan_rad' => 0,
            'ralan_lain' => 0,
            'ralan_total' => 0,
        ];

        $data = [];
        foreach ($kategoriList as $kat) {
            $data[$kat] = $emptyRow;
        }

        // Rawat Inap
        $ranap = DB::connection('simrs')->select("
            SELECT rp.kd_pj, pj.png_jawab,
                COUNT(DISTINCT ki.no_rawat) as jumlah_keluar,
                COALESCE(SUM(CASE WHEN ki.tgl_keluar IS NOT NULL THEN DATEDIFF(ki.tgl_keluar, ki.tgl_masuk) + 1 ELSE 1 END), 0) as lama
            FROM kamar_inap ki
            INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
            INNER JOIN penjab pj ON rp.kd_pj = pj.kd_pj
            WHERE YEAR(ki.tgl_masuk) = ?
            AND ki.stts_pulang IN ('Sehat','Rujuk','APS','+','Meninggal','Sembuh','Membaik','Pulang Paksa','-','Atas Persetujuan Dokter','Atas Permintaan Sendiri','Isoman','Lain-lain')
            GROUP BY rp.kd_pj, pj.png_jawab
        ", [$tahun]);

        foreach ($ranap as $row) {
            $kat = SirsHelper::kategoriPenjab($row->png_jawab);
            if (!isset($data[$kat]))
                $data[$kat] = $emptyRow;
            $data[$kat]['ranap_keluar'] += $row->jumlah_keluar;
            $data[$kat]['ranap_lama'] += $row->lama;
        }

        // Rawat Jalan Lab
        $ralanLab = DB::connection('simrs')->select("
            SELECT rp.kd_pj, pj.png_jawab, COUNT(DISTINCT pl.no_rawat) as jumlah
            FROM periksa_lab pl
            INNER JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
            INNER JOIN penjab pj ON rp.kd_pj = pj.kd_pj
            WHERE YEAR(pl.tgl_periksa) = ?
            GROUP BY rp.kd_pj, pj.png_jawab
        ", [$tahun]);

        foreach ($ralanLab as $row) {
            $kat = SirsHelper::kategoriPenjab($row->png_jawab);
            if (!isset($data[$kat]))
                $data[$kat] = $emptyRow;
            $data[$kat]['ralan_lab'] += $row->jumlah;
        }

        // Rawat Jalan Radiologi
        $ralanRad = DB::connection('simrs')->select("
            SELECT rp.kd_pj, pj.png_jawab, COUNT(DISTINCT pr.no_rawat) as jumlah
            FROM periksa_radiologi pr
            INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
            INNER JOIN penjab pj ON rp.kd_pj = pj.kd_pj
            WHERE YEAR(pr.tgl_periksa) = ?
            GROUP BY rp.kd_pj, pj.png_jawab
        ", [$tahun]);

        foreach ($ralanRad as $row) {
            $kat = SirsHelper::kategoriPenjab($row->png_jawab);
            if (!isset($data[$kat]))
                $data[$kat] = $emptyRow;
            $data[$kat]['ralan_rad'] += $row->jumlah;
        }

        // Rawat Jalan Lainnya (rawat_jl_dr + rawat_jl_drpr + rawat_jl_pr)
        $ralanLain = DB::connection('simrs')->select("
            SELECT rp.kd_pj, pj.png_jawab, COUNT(DISTINCT rp.no_rawat) as jumlah
            FROM reg_periksa rp
            INNER JOIN penjab pj ON rp.kd_pj = pj.kd_pj
            WHERE YEAR(rp.tgl_registrasi) = ?
            AND rp.status_lanjut = 'Ralan'
            GROUP BY rp.kd_pj, pj.png_jawab
        ", [$tahun]);

        foreach ($ralanLain as $row) {
            $kat = SirsHelper::kategoriPenjab($row->png_jawab);
            if (!isset($data[$kat]))
                $data[$kat] = $emptyRow;
            $data[$kat]['ralan_lain'] += $row->jumlah;
        }

        // Hitung total rawat jalan
        foreach ($data as $kat => &$d) {
            $d['ralan_total'] = $d['ralan_lab'] + $d['ralan_rad'] + $d['ralan_lain'];
        }

        return $data;
    }
}
