<?php

namespace App\Services\Sirs;

use App\Helpers\SirsHelper;
use Illuminate\Support\Facades\DB;

class SirsMorbidityService
{
    /** RL 4.1 - Morbiditas Rawat Inap (matrix kelompok umur × ICD-10) */
    public function getRL41(int $tahun, int $bulan): array
    {
        $range = SirsHelper::getDateRange($tahun, $bulan);
        $labels = SirsHelper::getKelompokUmurLabels();

        $rows = DB::connection('simrs')->select("
            SELECT dp.kd_penyakit, p.nm_penyakit, pa.jk, pa.tgl_lahir, ki.tgl_keluar
            FROM diagnosa_pasien dp
            INNER JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
            INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            INNER JOIN kamar_inap ki ON dp.no_rawat = ki.no_rawat
            WHERE dp.status = 'Ranap'
            AND MONTH(ki.tgl_keluar) = ? AND YEAR(ki.tgl_keluar) = ?
            AND dp.prioritas = 1
            AND ki.stts_pulang != 'Pindah Kamar'
            AND ki.tgl_keluar IS NOT NULL
        ", [$bulan, $tahun]);

        $data = [];
        foreach ($rows as $row) {
            $kode = $row->kd_penyakit;
            $kelUmur = SirsHelper::getKelompokUmur($row->tgl_lahir, $row->tgl_keluar);
            $jk = $row->jk;

            if (!isset($data[$kode])) {
                $data[$kode] = ['nama' => $row->nm_penyakit, 'detail' => [], 'total_l' => 0, 'total_p' => 0, 'total' => 0];
                foreach ($labels as $label) {
                    $data[$kode]['detail'][$label] = ['l' => 0, 'p' => 0];
                }
            }

            if (isset($data[$kode]['detail'][$kelUmur])) {
                $jkKey = SirsHelper::jkKey($jk);
                $data[$kode]['detail'][$kelUmur][$jkKey]++;
                $data[$kode]["total_{$jkKey}"]++;
                $data[$kode]['total']++;
            }
        }

        // Data kematian
        $kematian = DB::connection('simrs')->select("
            SELECT pm.icd1 as kd_penyakit, pa.jk, COUNT(DISTINCT pm.no_rkm_medis) as jumlah
            FROM pasien_mati pm
            INNER JOIN pasien pa ON pm.no_rkm_medis = pa.no_rkm_medis
            WHERE MONTH(pm.tanggal) = ? AND YEAR(pm.tanggal) = ?
            AND pm.icd1 IS NOT NULL AND pm.icd1 != ''
            GROUP BY pm.icd1, pa.jk
        ", [$bulan, $tahun]);

        $mati = [];
        foreach ($kematian as $row) {
            if (!isset($mati[$row->kd_penyakit])) {
                $mati[$row->kd_penyakit] = SirsHelper::emptyLPTotal();
            }
            $jkKey = SirsHelper::jkKey($row->jk);
            $mati[$row->kd_penyakit][$jkKey] += $row->jumlah;
            $mati[$row->kd_penyakit]['total'] += $row->jumlah;
        }

        // Urutkan berdasarkan total
        uasort($data, fn($a, $b) => $b['total'] - $a['total']);

        return ['data' => $data, 'kematian' => $mati, 'labels' => $labels];
    }

    /** RL 4.2 - 10 Besar Penyakit Rawat Inap */
    public function getRL42(int $tahun, int $bulan): array
    {
        $rows = DB::connection('simrs')->select("
            SELECT
                LEFT(dp.kd_penyakit, 3) as kd_group,
                p.nm_penyakit,
                pa.jk,
                COUNT(DISTINCT dp.no_rawat) as jumlah
            FROM diagnosa_pasien dp
            INNER JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
            INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            INNER JOIN kamar_inap ki ON dp.no_rawat = ki.no_rawat
            WHERE dp.status = 'Ranap'
            AND MONTH(ki.tgl_keluar) = ? AND YEAR(ki.tgl_keluar) = ?
            AND dp.prioritas = 1
            AND ki.tgl_keluar IS NOT NULL
            AND ki.stts_pulang != 'Pindah Kamar'
            AND SUBSTRING(dp.kd_penyakit, 1, 1) NOT IN ('R','V','W','X','Y','Z')
            AND LEFT(dp.kd_penyakit, 3) NOT IN ('O80','O82')
            GROUP BY LEFT(dp.kd_penyakit, 3), p.nm_penyakit, pa.jk
            ORDER BY jumlah DESC
        ", [$bulan, $tahun]);

        $data = [];
        foreach ($rows as $row) {
            $kd = $row->kd_group;
            if (!isset($data[$kd])) {
                $data[$kd] = ['nama' => $row->nm_penyakit, ...SirsHelper::emptyLPTotal()];
            }
            $jkKey = SirsHelper::jkKey($row->jk);
            $data[$kd][$jkKey] += $row->jumlah;
            $data[$kd]['total'] = $data[$kd]['l'] + $data[$kd]['p'];
        }

        uasort($data, fn($a, $b) => $b['total'] - $a['total']);
        $data = array_slice($data, 0, 10, true);

        // Kematian per kelompok ICD
        $kematian = $this->getKematianByGroup($bulan, $tahun);

        return ['data' => $data, 'kematian' => $kematian];
    }

    /** RL 4.3 - 10 Besar Kematian Rawat Inap */
    public function getRL43(int $tahun, int $bulan): array
    {
        $rows = DB::connection('simrs')->select("
            SELECT
                LEFT(pm.icd1, 3) as kd_group,
                p.nm_penyakit,
                pa.jk,
                COUNT(DISTINCT pm.no_rkm_medis) as jumlah_mati
            FROM pasien_mati pm
            INNER JOIN pasien pa ON pm.no_rkm_medis = pa.no_rkm_medis
            LEFT JOIN penyakit p ON pm.icd1 = p.kd_penyakit
            WHERE MONTH(pm.tanggal) = ? AND YEAR(pm.tanggal) = ?
            AND pm.icd1 IS NOT NULL AND pm.icd1 != ''
            AND LEFT(pm.icd1, 1) NOT IN ('R','V','W','X','Y','Z')
            AND LEFT(pm.icd1, 3) NOT IN ('O80','O82')
            GROUP BY LEFT(pm.icd1, 3), p.nm_penyakit, pa.jk
            ORDER BY jumlah_mati DESC
        ", [$bulan, $tahun]);

        $data = [];
        foreach ($rows as $row) {
            $kd = $row->kd_group;
            if (!isset($data[$kd])) {
                $data[$kd] = ['nama' => $row->nm_penyakit ?? 'Tidak diketahui', 'mati_l' => 0, 'mati_p' => 0, 'mati_total' => 0];
            }
            $jkKey = SirsHelper::jkKey($row->jk) === 'l' ? 'mati_l' : 'mati_p';
            $data[$kd][$jkKey] += $row->jumlah_mati;
            $data[$kd]['mati_total'] = $data[$kd]['mati_l'] + $data[$kd]['mati_p'];
        }

        uasort($data, fn($a, $b) => $b['mati_total'] - $a['mati_total']);

        return array_slice($data, 0, 10, true);
    }

    /** RL 5.1 - Morbiditas Rawat Jalan */
    public function getRL51(int $tahun, int $bulan): array
    {
        $labels = SirsHelper::getKelompokUmurLabels();

        $rows = DB::connection('simrs')->select("
            SELECT dp.kd_penyakit, p.nm_penyakit, pa.jk, pa.tgl_lahir, rp.tgl_registrasi
            FROM diagnosa_pasien dp
            INNER JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
            INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            WHERE dp.status = 'Ralan'
            AND MONTH(rp.tgl_registrasi) = ? AND YEAR(rp.tgl_registrasi) = ?
            AND dp.prioritas = 1
        ", [$bulan, $tahun]);

        $data = [];
        foreach ($rows as $row) {
            $kode = $row->kd_penyakit;
            $kelUmur = SirsHelper::getKelompokUmur($row->tgl_lahir, $row->tgl_registrasi);
            $jk = $row->jk;

            if (!isset($data[$kode])) {
                $data[$kode] = ['nama' => $row->nm_penyakit, 'detail' => [], 'total_l' => 0, 'total_p' => 0, 'total' => 0];
                foreach ($labels as $label) {
                    $data[$kode]['detail'][$label] = ['l' => 0, 'p' => 0];
                }
            }

            if (isset($data[$kode]['detail'][$kelUmur])) {
                $jkKey = SirsHelper::jkKey($jk);
                $data[$kode]['detail'][$kelUmur][$jkKey]++;
                $data[$kode]["total_{$jkKey}"]++;
                $data[$kode]['total']++;
            }
        }

        uasort($data, fn($a, $b) => $b['total'] - $a['total']);

        return ['data' => $data, 'labels' => $labels];
    }

    /** RL 5.2 - 10 Besar Kasus Baru Rawat Jalan */
    public function getRL52(int $tahun, int $bulan): array
    {
        $rows = DB::connection('simrs')->select("
            SELECT
                LEFT(dp.kd_penyakit, 3) as kd_group,
                p.nm_penyakit,
                pa.jk,
                COUNT(DISTINCT dp.no_rawat) as jumlah
            FROM diagnosa_pasien dp
            INNER JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
            INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            WHERE dp.status = 'Ralan'
            AND MONTH(rp.tgl_registrasi) = ? AND YEAR(rp.tgl_registrasi) = ?
            AND dp.prioritas = 1
            AND rp.stts_daftar = 'Baru'
            AND SUBSTRING(dp.kd_penyakit, 1, 1) NOT IN ('R','V','W','X','Y','Z')
            AND LEFT(dp.kd_penyakit, 3) NOT IN ('O80','O82')
            GROUP BY LEFT(dp.kd_penyakit, 3), p.nm_penyakit, pa.jk
            ORDER BY jumlah DESC
        ", [$bulan, $tahun]);

        $data = [];
        foreach ($rows as $row) {
            $kd = $row->kd_group;
            if (!isset($data[$kd])) {
                $data[$kd] = ['nama' => $row->nm_penyakit, ...SirsHelper::emptyLPTotal()];
            }
            $jkKey = SirsHelper::jkKey($row->jk);
            $data[$kd][$jkKey] += $row->jumlah;
            $data[$kd]['total'] = $data[$kd]['l'] + $data[$kd]['p'];
        }

        uasort($data, fn($a, $b) => $b['total'] - $a['total']);

        return array_slice($data, 0, 10, true);
    }

    /** RL 5.3 - 10 Besar Kunjungan Rawat Jalan */
    public function getRL53(int $tahun, int $bulan): array
    {
        $rows = DB::connection('simrs')->select("
            SELECT
                LEFT(dp.kd_penyakit, 3) as kd_group,
                p.nm_penyakit,
                pa.jk,
                COUNT(DISTINCT dp.no_rawat) as jumlah
            FROM diagnosa_pasien dp
            INNER JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
            INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
            INNER JOIN pasien pa ON rp.no_rkm_medis = pa.no_rkm_medis
            WHERE dp.status = 'Ralan'
            AND MONTH(rp.tgl_registrasi) = ? AND YEAR(rp.tgl_registrasi) = ?
            AND dp.prioritas = 1
            AND SUBSTRING(dp.kd_penyakit, 1, 1) NOT IN ('R','V','W','X','Y','Z')
            AND LEFT(dp.kd_penyakit, 3) NOT IN ('O80','O82')
            GROUP BY LEFT(dp.kd_penyakit, 3), p.nm_penyakit, pa.jk
            ORDER BY jumlah DESC
        ", [$bulan, $tahun]);

        $data = [];
        foreach ($rows as $row) {
            $kd = $row->kd_group;
            if (!isset($data[$kd])) {
                $data[$kd] = ['nama' => $row->nm_penyakit, ...SirsHelper::emptyLPTotal()];
            }
            $jkKey = SirsHelper::jkKey($row->jk);
            $data[$kd][$jkKey] += $row->jumlah;
            $data[$kd]['total'] = $data[$kd]['l'] + $data[$kd]['p'];
        }

        uasort($data, fn($a, $b) => $b['total'] - $a['total']);

        return array_slice($data, 0, 10, true);
    }

    /** Helper: data kematian per kelompok ICD-10 */
    private function getKematianByGroup(int $bulan, int $tahun): array
    {
        $rows = DB::connection('simrs')->select("
            SELECT LEFT(pm.icd1, 3) as kd_group, pa.jk, COUNT(DISTINCT pm.no_rkm_medis) as jumlah
            FROM pasien_mati pm
            INNER JOIN pasien pa ON pm.no_rkm_medis = pa.no_rkm_medis
            WHERE MONTH(pm.tanggal) = ? AND YEAR(pm.tanggal) = ?
            AND pm.icd1 IS NOT NULL AND pm.icd1 != ''
            AND LEFT(pm.icd1, 1) NOT IN ('R','V','W','X','Y','Z')
            AND LEFT(pm.icd1, 3) NOT IN ('O80','O82')
            GROUP BY LEFT(pm.icd1, 3), pa.jk
        ", [$bulan, $tahun]);

        $data = [];
        foreach ($rows as $row) {
            $kd = $row->kd_group;
            if (!isset($data[$kd])) {
                $data[$kd] = SirsHelper::emptyLPTotal();
            }
            $jkKey = SirsHelper::jkKey($row->jk);
            $data[$kd][$jkKey] += $row->jumlah;
            $data[$kd]['total'] = $data[$kd]['l'] + $data[$kd]['p'];
        }

        return $data;
    }
}
