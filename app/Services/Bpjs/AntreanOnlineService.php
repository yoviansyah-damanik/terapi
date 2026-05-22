<?php

namespace App\Services\Bpjs;

class AntreanOnlineService extends BpjsBaseService
{
    protected string $module = 'antrian_online';

    /**
     * Referensi poli.
     * GET /ref/poli
     */
    public function getReferensiPoli(): array
    {
        return $this->get('/ref/poli');
    }

    /**
     * Tambah antrean baru.
     * POST /antrean/add
     */
    public function tambahAntrean(array $data): array
    {
        return $this->post('/antrean/add', $data);
    }

    /**
     * Update waktu antrean.
     * POST /antrean/updatewaktu
     */
    public function updateWaktuAntrean(array $data): array
    {
        return $this->post('/antrean/updatewaktu', $data);
    }

    /**
     * Tambah antrean farmasi.
     * POST /antrean/farmasi/add
     */
    public function tambahAntreanFarmasi(array $data): array
    {
        return $this->post('/antrean/farmasi/add', $data);
    }

    /**
     * Data antrean pendaftaran per tanggal.
     * GET /antrean/pendaftaran/tanggal/{tanggal}
     */
    public function getAntreanByTanggal(string $tanggal): array
    {
        return $this->get('/antrean/pendaftaran/tanggal/' . $tanggal);
    }

    /** Kembalikan URL lengkap endpoint antrean per tanggal. */
    public function endpointAntreanByTanggal(string $tanggal): string
    {
        return $this->baseUrl() . '/antrean/pendaftaran/tanggal/' . $tanggal;
    }
}
