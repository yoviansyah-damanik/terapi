<?php

namespace App\Services\SatuSehat;

use App\Models\SatuSehat\SatuSehatLocation;
use App\Models\SatuSehat\SatuSehatOrganization;
use App\Models\SatuSehat\SatuSehatPatient;
use App\Models\SatuSehat\SatuSehatPractitioner;
use App\Models\Simrs\RegPeriksa;

class SatuSehatErmValidator
{
    /**
     * Validasi prasyarat pengiriman FHIR eRM ke Satu Sehat.
     *
     * @return array<int, array{type: 'error'|'warning', section: string, text: string}>
     */
    public function validate(RegPeriksa $reg): array
    {
        $reg->loadMissing(['pasien', 'dokter.pegawai', 'poliklinik']);

        $issues = [];

        // 1. Organization RS harus terdaftar
        $orgId = config('satusehat.organization_id');
        if (!$orgId) {
            $issues[] = ['type' => 'error', 'section' => 'Organization', 'text' => 'Organization ID Satu Sehat belum dikonfigurasi. Atur melalui menu Konfigurasi RS.'];
        } elseif (!SatuSehatOrganization::where('identifier', 'RS')->exists()) {
            $issues[] = ['type' => 'error', 'section' => 'Organization', 'text' => "Organization RS (Identifier: RS) belum tersinkronkan ke database lokal. Tarik data Organization dari modul FHIR Resource."];
        }

        // 2. Pasien harus terdaftar di Satu Sehat
        $nik = $reg->pasien?->no_ktp;
        if (!$nik) {
            $issues[] = ['type' => 'error', 'section' => 'Pasien', 'text' => 'NIK pasien tidak tersedia di data SIMRS.'];
        } elseif (!SatuSehatPatient::findByNik($nik)) {
            $issues[] = ['type' => 'error', 'section' => 'Pasien', 'text' => "Pasien ({$reg->pasien->nm_pasien}, NIK: {$nik}) belum terdaftar di Satu Sehat. Sinkronkan data pasien terlebih dahulu."];
        }

        // 3. Dokter DPJP harus terdaftar sebagai Practitioner di Satu Sehat
        $nikDokter = $reg->dokter?->pegawai?->no_ktp;
        if (!$reg->dokter) {
            $issues[] = ['type' => 'error', 'section' => 'Practitioner', 'text' => 'Data dokter DPJP tidak tersedia pada kunjungan ini.'];
        } elseif (!$nikDokter) {
            $issues[] = ['type' => 'error', 'section' => 'Practitioner', 'text' => "Dokter DPJP ({$reg->dokter->nm_dokter}) tidak memiliki data pegawai atau NIK yang terhubung di SIMRS."];
        } elseif (!SatuSehatPractitioner::findByNik($nikDokter)) {
            $issues[] = ['type' => 'error', 'section' => 'Practitioner', 'text' => "Dokter DPJP ({$reg->dokter->nm_dokter}, NIK: {$nikDokter}) belum terdaftar di Satu Sehat. Sinkronkan data practitioner terlebih dahulu."];
        }

        // 4. Location poliklinik harus terdaftar di Satu Sehat
        $kdPoli = $reg->kd_poli;
        $location = SatuSehatLocation::where('identifier', $kdPoli)->first();
        if (!$location) {
            $issues[] = ['type' => 'error', 'section' => 'Location', 'text' => "Poliklinik '{$reg->poliklinik?->nm_poli}' ({$kdPoli}) belum terdaftar di Satu Sehat. Tarik data Location dari modul FHIR Resource."];
        } elseif (!$location->managing_organization) {
            $issues[] = ['type' => 'warning', 'section' => 'Location', 'text' => "Location poliklinik '{$reg->poliklinik?->nm_poli}' tidak memiliki managing organization yang terhubung."];
        }

        return $issues;
    }

    public function hasErrors(array $issues): bool
    {
        return collect($issues)->contains(fn($i) => $i['type'] === 'error');
    }
}
