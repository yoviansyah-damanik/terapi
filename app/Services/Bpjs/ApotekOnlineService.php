<?php

namespace App\Services\Bpjs;

class ApotekOnlineService extends BpjsBaseService
{
    protected string $module = 'apotek_online';

    /**
     * Referensi DPHO (Daftar dan Plafon Harga Obat).
     * GET /referensi/dpho
     */
    public function getReferensiDpho(): array
    {
        return $this->get('/referensi/dpho');
    }

    /**
     * Referensi obat.
     * GET /referensi/obat/{kdJnsobat}/{pgAwal}/{pgAkhir}
     */
    public function getReferensiObat(string $kdJnsObat, int $pgAwal, int $pgAkhir): array
    {
        return $this->get("/referensi/obat/{$kdJnsObat}/{$pgAwal}/{$pgAkhir}");
    }

    /**
     * Simpan obat non racikan (versi 3).
     * POST /obatnonracikan/v3/insert
     */
    public function simpanObatNonRacikan(array $data): array
    {
        return $this->post('/obatnonracikan/v3/insert', $data);
    }

    /**
     * Simpan obat racikan (versi 3).
     * POST /obatracikan/v3/insert
     */
    public function simpanObatRacikan(array $data): array
    {
        return $this->post('/obatracikan/v3/insert', $data);
    }

    /**
     * Daftar pelayanan obat.
     * GET /obat/daftar/{bulan}
     */
    public function getDaftarPelayananObat(string $bulan): array
    {
        return $this->get("/obat/daftar/{$bulan}");
    }

    /**
     * Daftar resep.
     * POST /daftarresep
     */
    public function getDaftarResep(array $data): array
    {
        return $this->post('/daftarresep', $data);
    }

    /**
     * Simpan SJP resep (versi 3).
     * POST /sjpresep/v3/insert
     */
    public function simpanResep(array $data): array
    {
        return $this->post('/sjpresep/v3/insert', $data);
    }

    /**
     * Hapus resep.
     * DELETE /hapusresep
     */
    public function hapusResep(array $data): array
    {
        return $this->delete('/hapusresep', $data);
    }
}
