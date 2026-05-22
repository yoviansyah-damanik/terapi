<?php

namespace App\Services\Bpjs;

class VClaimService extends BpjsBaseService
{
    protected string $module = 'vclaim';

    /**
     * Get data peserta berdasarkan nomor kartu.
     * GET /Peserta/nokartu/{noKartu}/tglSEP/{tglSep}
     */
    public function getPeserta(string $noKartu, string $tglSep): array
    {
        return $this->get("/Peserta/nokartu/{$noKartu}/tglSEP/{$tglSep}");
    }

    /**
     * Referensi poli berdasarkan nama.
     * GET /referensi/poli/{nama}
     */
    public function getReferensiPoli(string $nama): array
    {
        return $this->get("/referensi/poli/{$nama}");
    }

    /**
     * Cari SEP berdasarkan nomor SEP (via RencanaKontrol).
     * GET /RencanaKontrol/nosep/{noSep}
     */
    public function cariSep(string $noSep): array
    {
        return $this->get("/RencanaKontrol/nosep/{$noSep}");
    }

    /**
     * Insert SEP baru (versi 2.0).
     * POST /SEP/2.0/insert
     */
    public function insertSep(array $data): array
    {
        return $this->post('/SEP/2.0/insert', $data);
    }

    /**
     * Insert rencana kontrol (versi 2).
     * POST /RencanaKontrol/v2/Insert
     */
    public function insertRencanaKontrol(array $data): array
    {
        return $this->post('/RencanaKontrol/v2/Insert', $data);
    }

    /**
     * Insert SPRI (Surat Perintah Rawat Inap).
     * POST /RencanaKontrol/InsertSPRI
     */
    public function insertSpri(array $data): array
    {
        return $this->post('/RencanaKontrol/InsertSPRI', $data);
    }

    /**
     * Insert rujukan baru.
     * POST /Rujukan/insert
     */
    public function insertRujukan(array $data): array
    {
        return $this->post('/Rujukan/insert', $data);
    }

    /**
     * Get rujukan berdasarkan nomor rujukan.
     * GET /Rujukan/{noRujukan}
     */
    public function getRujukan(string $noRujukan): array
    {
        return $this->get("/Rujukan/{$noRujukan}");
    }
}
