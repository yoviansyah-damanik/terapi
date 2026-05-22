<?php

namespace App\Helpers;

use App\Helpers\ConfigurationHelper;
use App\Models\Simrs\RegPeriksa;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ErmHelper
{
    /**
     * Generate ID dengan format: {KODE_PPK}-{KODE_PPK_KEMENKES}-{JENIS_PELAYANAN}-{UUID}
     */
    public static function generateId(string $jenisPelayanan, ?string $id = null): string
    {
        return sprintf(
            '%s-%s-%s-%s',
            ConfigurationHelper::get('bpjs.kode_ppk') ?? config('bpjs.kode_ppk'),
            ConfigurationHelper::get('satusehat.kode_ppk_kemenkes') ?? config('satusehat.kode_ppk_kemenkes'),
            $jenisPelayanan,
            $id ?? Str::uuid()
        );
    }

    /**
     * Get discharge disposition code based on patient status
     */
    public static function getDischargeDisposition(string $stts): array
    {
        // Dirujuk: other-hcf (Other healthcare facility)
        // Pulang Paksa: aadvice (Left against advice)
        // Meninggal: exp (Expired)
        // selain itu: home (Home)

        $mapping = [
            'Dirujuk' => ['code' => 'other-hcf', 'display' => 'Other healthcare facility'],
            'Pulang Paksa' => ['code' => 'aadvice', 'display' => 'Left against advice'],
            'Meninggal' => ['code' => 'exp', 'display' => 'Expired'],
        ];

        $disposition = $mapping[$stts] ?? ['code' => 'home', 'display' => 'Home'];

        return [
            'coding' => [
                [
                    'system' => 'http://terminology.hl7.org/CodeSystem/discharge-disposition',
                    'code' => $disposition['code'],
                    'display' => $disposition['display'],
                ],
            ],
            'text' => $stts
        ];
    }

    /**
     * Format datetime ke ISO 8601 dengan timezone WIB: 2026-02-14T01:29:19+07:00
     */
    public static function formatDateTime($datetime): string
    {
        return Carbon::parse($datetime)->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i:sP');
    }

    /**
     * Get jenis pelayanan code: 1 = Rawat Inap, 2 = Rawat Jalan
     */
    public static function getJenisPelayanan(RegPeriksa $reg): string
    {
        return $reg->status_lanjut === 'Ranap' ? '1' : '2';
    }

    /**
     * Get practitioner ID (NIP atau kd_dokter)
     */
    public static function getPractitionerId($dokter): string
    {
        if (!$dokter) {
            return '';
        }

        // Prioritas: No KTP dari tabel pegawai
        if (!empty($dokter->pegawai->no_ktp)) {
            return $dokter->pegawai->no_ktp;
        }

        return $dokter->nip ?: $dokter->kd_dokter;
    }

    public static function getPpkRsBpjs(): array
    {
        return [
            'code' => ConfigurationHelper::get('bpjs.kode_ppk')        ?? config('bpjs.kode_ppk'),
            'name' => ConfigurationHelper::get('bpjs.nama_ppk')        ?? config('bpjs.nama_ppk'),
        ];
    }

    public static function getPpkRsKemenkes(): array
    {
        return [
            'code' => ConfigurationHelper::get('satusehat.kode_ppk_kemenkes') ?? config('satusehat.kode_ppk_kemenkes'),
            'name' => ConfigurationHelper::get('satusehat.nama_ppk_kemenkes') ?? config('satusehat.nama_ppk_kemenkes'),
        ];
    }

    public static function getPpkApotekBpjs(): array
    {
        return [
            'code' => ConfigurationHelper::get('bpjs.kode_ppk_apotek') ?? config('bpjs.kode_ppk_apotek'),
            'name' => ConfigurationHelper::get('bpjs.nama_ppk_apotek') ?? config('bpjs.nama_ppk_apotek'),
        ];
    }
}
