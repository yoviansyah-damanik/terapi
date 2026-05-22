<?php

namespace App\Services\Bpjs;

use App\Models\Bpjs\BpjsOrganization;
use App\Models\Bpjs\BpjsPatient;
use App\Models\Bpjs\BpjsPractitioner;
use App\Models\Bpjs\BpjsMedication;
use App\Models\Bpjs\BpjsProcedure;
use App\Models\Mapping\Icd10Map;
use App\Models\Mapping\Icd9Map;
use App\Models\Mapping\LabMap;
use App\Models\Mapping\LabSpecimenMap;
use App\Models\Mapping\LabItemMap;
use App\Models\Mapping\MedicationMap;
use App\Models\Mapping\ProcedureMap;
use App\Models\Mapping\RadMap;
use App\Models\Mapping\RadProcedureMap;
use App\Models\Simrs\RegPeriksa;
use Illuminate\Support\Collection;

class ErmValidator
{
    /**
     * Validasi kelengkapan data mapping sebelum generate bundle eRM.
     *
     * @param array|null $enabledModules Modul aktif; null = semua modul dicek
     * @return array<int, array{type: 'error'|'warning', section: string, text: string}>
     */
    public function validate(RegPeriksa $reg, ?array $enabledModules = null): array
    {
        $reg->loadMissing([
            'bridgingSep',
            'diagnosaPasien',
            'prosedurPasien',
            'resepPulang.dataBarang',
            'detailPemberianObat.dataBarang',
            'permintaanLab.periksaLab.detailPeriksaLab.template',
            'permintaanRadiologi',
            'rawatJlDr',
            'rawatJlPr',
            'rawatJlDrPr',
            'rawatInapDr',
            'rawatInapPr',
            'rawatInapDrPr',
        ]);

        // null = semua modul aktif (backward compatible)
        $active = $enabledModules ?? ['procedure', 'medication', 'lab', 'radiologi', 'vital_sign'];

        $issues = [];

        // 1. SEP wajib ada (error)
        if (!$reg->bridgingSep) {
            $issues[] = ['type' => 'error', 'section' => 'SEP', 'text' => 'Pasien tidak memiliki SEP. eRM hanya dapat dikirim untuk pasien BPJS dengan SEP.'];
        }

        // 2. Diagnosa wajib ada (error)
        if ($reg->diagnosaPasien->isEmpty()) {
            $issues[] = ['type' => 'error', 'section' => 'Diagnosa', 'text' => 'Tidak ada data diagnosa untuk kunjungan ini.'];
        } else {
            // 3. Mapping ICD-10 (warning)
            $icd10Codes = $reg->diagnosaPasien->pluck('kd_penyakit')->filter()->unique()->values();
            if ($icd10Codes->isNotEmpty()) {
                $missing = $icd10Codes->diff(Icd10Map::whereIn('icd10_code', $icd10Codes)->pluck('icd10_code'));
                if ($missing->isNotEmpty()) {
                    $issues[] = ['type' => 'warning', 'section' => 'ICD-10', 'text' => 'Kode ICD-10 belum dipetakan ke SNOMED: ' . $missing->implode(', ')];
                }
            }
        }

        // 4. Mapping ICD-9 / Prosedur (error) — hanya jika modul procedure aktif
        if (in_array('procedure', $active)) {
            $icd9Codes = $reg->prosedurPasien->pluck('kode')->filter()->unique()->values();
            if ($icd9Codes->isNotEmpty()) {
                $missing = $icd9Codes->diff(Icd9Map::whereIn('icd9_code', $icd9Codes)->pluck('icd9_code'));
                if ($missing->isNotEmpty()) {
                    $issues[] = ['type' => 'error', 'section' => 'ICD-9', 'text' => 'Kode prosedur ICD-9 belum dipetakan ke SNOMED: ' . $missing->implode(', ')];
                }
            }

            // 5. Mapping Tindakan (error)
            $tindakanKeys = $this->collectTindakanKeys($reg);
            if ($tindakanKeys->isNotEmpty()) {
                $mapped = ProcedureMap::whereIn('procedure_code', $tindakanKeys->pluck('procedure_code'))
                    ->get(['procedure_code', 'source_table'])
                    ->map(fn($m) => $m->procedure_code . '|' . $m->source_table)
                    ->toArray();

                $missingTindakan = $tindakanKeys
                    ->filter(fn($k) => !in_array($k['procedure_code'] . '|' . $k['source_table'], $mapped))
                    ->pluck('procedure_code')
                    ->unique();

                if ($missingTindakan->isNotEmpty()) {
                    $issues[] = ['type' => 'error', 'section' => 'Tindakan', 'text' => 'Kode tindakan belum dipetakan: ' . $missingTindakan->implode(', ')];
                }
            }
        }

        // 6. Mapping Obat (warning) — hanya jika modul medication aktif
        if (in_array('medication', $active)) {
            $obatCodes = $this->collectObatCodes($reg);
            if ($obatCodes->isNotEmpty()) {
                $missing = $obatCodes->diff(MedicationMap::whereIn('local_code', $obatCodes)->pluck('local_code'));
                if ($missing->isNotEmpty()) {
                    $issues[] = ['type' => 'warning', 'section' => 'Obat', 'text' => $missing->count() . ' kode obat belum dipetakan: ' . $missing->implode(', ')];
                }
            }
        }

        // 7. Mapping Radiologi (warning) — hanya jika modul radiologi aktif
        if (in_array('radiologi', $active)) {
            $radCodes = $reg->permintaanRadiologi
                ->flatMap(fn($p) => $p->periksaRad->pluck('kd_jenis_prw'))
                ->filter()->unique()->values();
            if ($radCodes->isNotEmpty()) {
                $missingLoinc = $radCodes->diff(RadMap::whereIn('local_code', $radCodes)->pluck('local_code'));
                if ($missingLoinc->isNotEmpty()) {
                    $issues[] = ['type' => 'warning', 'section' => 'Radiologi (LOINC)', 'text' => $missingLoinc->count() . ' kode radiologi (LOINC) belum dipetakan: ' . $missingLoinc->implode(', ')];
                }

                $missingSnomed = $radCodes->diff(RadProcedureMap::whereIn('local_code', $radCodes)->pluck('local_code'));
                if ($missingSnomed->isNotEmpty()) {
                    $issues[] = ['type' => 'warning', 'section' => 'Radiologi (SNOMED)', 'text' => $missingSnomed->count() . ' prosedur/specimen SNOMED radiologi belum dipetakan: ' . $missingSnomed->implode(', ')];
                }
            }
        }

        // 8. Mapping Lab (warning/error) — hanya jika modul lab aktif
        if (in_array('lab', $active)) {
            $labCodes = $reg->permintaanLab
                ->flatMap(fn($p) => $p->periksaLab->pluck('kd_jenis_prw'))
                ->filter()->unique()->values();
            if ($labCodes->isNotEmpty()) {
                $missingLoinc = $labCodes->diff(LabMap::whereIn('local_code', $labCodes)->pluck('local_code'));
                if ($missingLoinc->isNotEmpty()) {
                    $issues[] = ['type' => 'warning', 'section' => 'Laboratorium (LOINC)', 'text' => $missingLoinc->count() . ' kode lab (LOINC) belum dipetakan: ' . $missingLoinc->implode(', ')];
                }

                $missingSnomed = $labCodes->diff(LabSpecimenMap::whereIn('local_code', $labCodes)->pluck('local_code'));
                if ($missingSnomed->isNotEmpty()) {
                    $issues[] = ['type' => 'error', 'section' => 'Laboratorium (SNOMED)', 'text' => $missingSnomed->count() . ' specimen SNOMED lab belum dipetakan: ' . $missingSnomed->implode(', ')];
                }
            }

            // Validasi LabItemMap (error jika tidak dipetakan karena wajib dan tidak boleh null)
            $labItemPairs = $reg->permintaanLab->flatMap(function($p) {
                return $p->periksaLab->flatMap(function($periksa) {
                    return $periksa->detailPeriksaLab->map(function($detail) {
                        return [
                            'kd_jenis_prw' => $detail->kd_jenis_prw,
                            'id_template' => $detail->id_template,
                            'name' => $detail->template?->Pemeriksaan ?? $detail->id_template
                        ];
                    });
                });
            })->filter()->unique(fn($x) => $x['kd_jenis_prw'] . '|' . $x['id_template'])->values();

            if ($labItemPairs->isNotEmpty()) {
                $mappedItems = LabItemMap::whereIn('kd_jenis_prw', $labItemPairs->pluck('kd_jenis_prw')->unique())
                    ->get(['kd_jenis_prw', 'id_template'])
                    ->map(fn($m) => $m->kd_jenis_prw . '|' . $m->id_template)
                    ->toArray();

                $missingItems = $labItemPairs->filter(function($pair) use ($mappedItems) {
                    return !in_array($pair['kd_jenis_prw'] . '|' . $pair['id_template'], $mappedItems);
                });

                if ($missingItems->isNotEmpty()) {
                    $missingText = $missingItems->map(fn($x) => "{$x['name']} ({$x['kd_jenis_prw']} - {$x['id_template']})")->implode(', ');
                    $issues[] = [
                        'type' => 'error',
                        'section' => 'Laboratorium (Item LOINC)',
                        'text' => $missingItems->count() . ' rincian item lab belum dipetakan ke LOINC di master LabItemMap: ' . $missingText
                    ];
                }
            }
        }

        // 9. Validasi Master BPJS
        $this->validateBpjsMasterData($reg, $issues, $active);

        return $issues;
    }

    public function hasErrors(array $issues): bool
    {
        return collect($issues)->contains(fn($i) => $i['type'] === 'error');
    }

    private function collectTindakanKeys(RegPeriksa $reg): Collection
    {
        $collections = $reg->status_lanjut === 'Ranap'
            ? [$reg->rawatInapDr, $reg->rawatInapPr, $reg->rawatInapDrPr]
            : [$reg->rawatJlDr, $reg->rawatJlPr, $reg->rawatJlDrPr];

        return collect($collections)
            ->flatMap(fn($c) => $c)
            ->map(fn($t) => [
                'procedure_code' => $t->kd_jenis_prw,
                'source_table' => str_contains($t->getTable(), 'jl_') ? 'jalan' : 'inap',
            ])
            ->unique(fn($k) => $k['procedure_code'] . '|' . $k['source_table'])
            ->values();
    }

    private function collectObatCodes(RegPeriksa $reg): Collection
    {
        return collect()
            ->merge($reg->resepPulang->filter(fn($r) => $r->dataBarang)->pluck('kode_brng'))
            ->merge($reg->detailPemberianObat->filter(fn($d) => $d->dataBarang)->pluck('kode_brng'))
            ->filter()->unique()->values();
    }

    private function validateBpjsMasterData(RegPeriksa $reg, array &$issues, array $active): void
    {
        // Pasien
        if ($reg->pasien) {
            $patient = BpjsPatient::where('nik', $reg->pasien->no_ktp)->first();
            if (!$patient) {
                $issues[] = ['type' => 'error', 'section' => 'BPJS Patient', 'text' => 'Pasien belum memiliki data IHS ID di master BPJS (NIK: ' . $reg->pasien->no_ktp . ').'];
            }
        }

        // Dokter
        if ($reg->dokter) {
            $practitioner = BpjsPractitioner::where('identifier', $reg->kd_dokter)->first();
            if (!$practitioner) {
                $issues[] = ['type' => 'error', 'section' => 'BPJS Practitioner', 'text' => 'Dokter DPJP belum memiliki data IHS ID di master BPJS (Kode: ' . $reg->kd_dokter . ').'];
            }
        }

        // RS Org
        $rsOrg = BpjsOrganization::where('identifier', 'RS')->first();
        if (!$rsOrg) {
            $issues[] = ['type' => 'error', 'section' => 'BPJS Organization', 'text' => 'ID Organisasi Rumah Sakit belum dibentuk di master BPJS (identifier: RS).'];
        }
        $rsOrgMap = \App\Models\Mapping\OrganizationMap::where('dep_id', 'RS')->first();
        if (!$rsOrgMap) {
            $issues[] = ['type' => 'error', 'section' => 'Organization Type', 'text' => 'Tipe Organisasi Rumah Sakit belum dipetakan (identifier: RS).'];
        }

        // Unit Org (RI / RJ / IGD) — selalu wajib
        $unitCode = $reg->status_lanjut === 'Ranap' ? 'RI' : (str_contains(strtolower($reg->poliklinik->nm_poli), 'darurat') ? 'IGD' : 'RJ');
        $unitOrg = BpjsOrganization::where('identifier', $unitCode)->first();
        if (!$unitOrg) {
            $issues[] = ['type' => 'error', 'section' => 'BPJS Organization', 'text' => "ID Organisasi Unit ({$unitCode}) belum dibentuk di master BPJS (identifier: {$unitCode})."];
        }
        $unitOrgMap = \App\Models\Mapping\OrganizationMap::where('dep_id', $unitCode)->first();
        if (!$unitOrgMap) {
            $issues[] = ['type' => 'error', 'section' => 'Organization Type', 'text' => "Tipe Organisasi Unit ({$unitCode}) belum dipetakan (identifier: {$unitCode})."];
        }

        // Farmasi Org — hanya jika modul medication aktif
        if (in_array('medication', $active) && ($reg->resepPulang->isNotEmpty() || $reg->detailPemberianObat->isNotEmpty())) {
            $farmasiOrg = BpjsOrganization::where('identifier', 'FAR')->first();
            if (!$farmasiOrg) {
                $issues[] = ['type' => 'error', 'section' => 'BPJS Organization', 'text' => 'ID Organisasi Farmasi belum dibentuk di master BPJS (identifier: FAR).'];
            }
            $farmasiOrgMap = \App\Models\Mapping\OrganizationMap::where('dep_id', 'FAR')->first();
            if (!$farmasiOrgMap) {
                $issues[] = ['type' => 'error', 'section' => 'Organization Type', 'text' => 'Tipe Organisasi Farmasi belum dipetakan (identifier: FAR).'];
            }
        }

        // Lab Org — hanya jika modul lab aktif
        if (in_array('lab', $active) && $reg->permintaanLab->isNotEmpty()) {
            $labOrg = BpjsOrganization::where('identifier', 'LAB')->first();
            if (!$labOrg) {
                $issues[] = ['type' => 'error', 'section' => 'BPJS Organization', 'text' => 'ID Organisasi Laboratorium belum dibentuk di master BPJS (identifier: LAB).'];
            }
            $labOrgMap = \App\Models\Mapping\OrganizationMap::where('dep_id', 'LAB')->first();
            if (!$labOrgMap) {
                $issues[] = ['type' => 'error', 'section' => 'Organization Type', 'text' => 'Tipe Organisasi Laboratorium belum dipetakan (identifier: LAB).'];
            }
        }

        // Rad Org — hanya jika modul radiologi aktif
        if (in_array('radiologi', $active) && $reg->permintaanRadiologi->isNotEmpty()) {
            $radOrg = BpjsOrganization::where('identifier', 'RAD')->first();
            if (!$radOrg) {
                $issues[] = ['type' => 'error', 'section' => 'BPJS Organization', 'text' => 'ID Organisasi Radiologi belum dibentuk di master BPJS (identifier: RAD).'];
            }
            $radOrgMap = \App\Models\Mapping\OrganizationMap::where('dep_id', 'RAD')->first();
            if (!$radOrgMap) {
                $issues[] = ['type' => 'error', 'section' => 'Organization Type', 'text' => 'Tipe Organisasi Radiologi belum dipetakan (identifier: RAD).'];
            }
        }

        // Validasi UUID Obat — hanya jika modul medication aktif
        if (in_array('medication', $active)) {
            $obatCodes = $this->collectObatCodes($reg);
            if ($obatCodes->isNotEmpty()) {
                $missingBpjsObat = $obatCodes->diff(BpjsMedication::whereIn('local_code', $obatCodes)->pluck('local_code'));
                if ($missingBpjsObat->isNotEmpty()) {
                    $issues[] = ['type' => 'error', 'section' => 'BPJS Medication', 'text' => $missingBpjsObat->count() . ' item obat belum terdaftar UUID-nya di master BPJS Medication. Kode: ' . $missingBpjsObat->implode(', ')];
                }
            }
        }

        // Validasi UUID Prosedur Lab — hanya jika modul lab aktif
        if (in_array('lab', $active) && $reg->permintaanLab->isNotEmpty()) {
            $labCodes = $reg->permintaanLab->flatMap(fn($p) => $p->periksaLab->pluck('kd_jenis_prw'))->filter()->unique()->values();
            if ($labCodes->isNotEmpty()) {
                $missingLab = $labCodes->diff(BpjsProcedure::where('type', 'lab')->whereIn('local_code', $labCodes)->pluck('local_code'));
                if ($missingLab->isNotEmpty()) {
                    $issues[] = ['type' => 'error', 'section' => 'BPJS Procedure (Lab)', 'text' => $missingLab->count() . ' pemeriksaan lab belum diregistrasi UUID di master BPJS Procedure. Kode: ' . $missingLab->implode(', ')];
                }
            }
            $labItemCodes = $reg->permintaanLab->flatMap(fn($p) => $p->periksaLab->flatMap(fn($periksa) => $periksa->detailPeriksaLab->pluck('id_template')))->filter()->unique()->values();
            if ($labItemCodes->isNotEmpty()) {
                $missingLabItem = $labItemCodes->diff(BpjsProcedure::where('type', 'item_lab')->whereIn('local_code', $labItemCodes)->pluck('local_code'));
                if ($missingLabItem->isNotEmpty()) {
                    $issues[] = ['type' => 'error', 'section' => 'BPJS Procedure (Item Lab)', 'text' => $missingLabItem->count() . ' rincian item lab belum diregistrasi UUID di master BPJS Procedure. Kode: ' . $missingLabItem->implode(', ')];
                }
            }
        }

        // Validasi UUID Prosedur Rad — hanya jika modul radiologi aktif
        if (in_array('radiologi', $active) && $reg->permintaanRadiologi->isNotEmpty()) {
            $radCodes = $reg->permintaanRadiologi->flatMap(fn($p) => $p->periksaRad->pluck('kd_jenis_prw'))->filter()->unique()->values();
            if ($radCodes->isNotEmpty()) {
                $missingRad = $radCodes->diff(BpjsProcedure::where('type', 'rad')->whereIn('local_code', $radCodes)->pluck('local_code'));
                if ($missingRad->isNotEmpty()) {
                    $issues[] = ['type' => 'error', 'section' => 'BPJS Procedure (Rad)', 'text' => $missingRad->count() . ' pemeriksaan radiologi belum diregistrasi UUID di master BPJS Procedure. Kode: ' . $missingRad->implode(', ')];
                }
            }
        }

        // Validasi UUID Prosedur ICD-9 (prosedurPasien) — hanya jika modul procedure aktif
        if (in_array('procedure', $active) && $reg->prosedurPasien->isNotEmpty()) {
            $icd9Codes = $reg->prosedurPasien->pluck('kode')->filter()->unique()->values();
            if ($icd9Codes->isNotEmpty()) {
                // Ensure model BpjsIcd9 is imported, wait, let me check if it's imported at the top of the file!
                $missingIcd9 = $icd9Codes->diff(\App\Models\Bpjs\BpjsIcd9::whereIn('code', $icd9Codes)->pluck('code'));
                if ($missingIcd9->isNotEmpty()) {
                    $issues[] = ['type' => 'error', 'section' => 'BPJS ICD-9', 'text' => $missingIcd9->count() . ' prosedur ICD-9 belum diregistrasi UUID di master BPJS. Kode: ' . $missingIcd9->implode(', ')];
                }
            }
        }

        // Validasi UUID Tindakan — hanya jika modul procedure aktif
        if (in_array('procedure', $active)) {
            $tindakanKeys = $this->collectTindakanKeys($reg);
            if ($tindakanKeys->isNotEmpty()) {
                $type = strtolower($reg->status_lanjut);
                $tindakanCodes = $tindakanKeys->pluck('procedure_code')->unique()->values();
                $missingTindakan = $tindakanCodes->diff(BpjsProcedure::where('type', $type)->whereIn('local_code', $tindakanCodes)->pluck('local_code'));
                if ($missingTindakan->isNotEmpty()) {
                    $issues[] = ['type' => 'error', 'section' => 'BPJS Procedure (Tindakan)', 'text' => $missingTindakan->count() . ' tindakan belum diregistrasi UUID di master BPJS Procedure. Kode: ' . $missingTindakan->implode(', ')];
                }
            }
        }
    }
}
