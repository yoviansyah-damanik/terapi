<?php

namespace App\Services\Sirs;

use Illuminate\Support\Facades\DB;

class SirsPharmacyService
{
    /** RL 3.17 - Farmasi Pengadaan Obat (Tahunan) */
    public function getRL317(int $tahun): array
    {
        $rows = DB::connection('simrs')->select("
            SELECT
                db.kode_sat,
                db.nama_brng,
                db.kode_kategori,
                COALESCE(SUM(ds.jumlah), 0) as jumlah_pengadaan
            FROM databarang db
            LEFT JOIN detailsurat ds ON db.kode_brng = ds.kode_brng
            LEFT JOIN surat_pemesanan sp ON ds.no_faktur = sp.no_faktur
            WHERE YEAR(sp.tgl_pesan) = ?
            AND db.status = '1'
            GROUP BY db.kode_brng, db.kode_sat, db.nama_brng, db.kode_kategori
            HAVING jumlah_pengadaan > 0
            ORDER BY jumlah_pengadaan DESC
            LIMIT 50
        ", [$tahun]);

        return collect($rows)->map(fn($r) => (array) $r)->toArray();
    }

    /** RL 3.18 - Farmasi Resep (Tahunan) */
    public function getRL318(int $tahun): array
    {
        // Total resep
        $resep = DB::connection('simrs')->select("
            SELECT
                CASE WHEN rp.status_lanjut = 'Ranap' THEN 'ranap' ELSE 'ralan' END as tipe,
                COUNT(DISTINCT ro.no_resep) as jumlah_resep
            FROM resep_obat ro
            INNER JOIN reg_periksa rp ON ro.no_rawat = rp.no_rawat
            WHERE YEAR(ro.tgl_peresepan) = ?
            GROUP BY tipe
        ", [$tahun]);

        $data = ['ralan' => 0, 'ranap' => 0, 'total' => 0];
        foreach ($resep as $row) {
            $data[$row->tipe] = $row->jumlah_resep;
        }
        $data['total'] = $data['ralan'] + $data['ranap'];

        return $data;
    }
}
