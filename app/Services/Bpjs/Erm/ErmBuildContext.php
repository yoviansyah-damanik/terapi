<?php

namespace App\Services\Bpjs\Erm;

use App\Helpers\ErmHelper;
use App\Models\Bpjs\BpjsHealthcareService;
use App\Models\Bpjs\BpjsOrganization;
use App\Models\Bpjs\BpjsPatient;
use App\Models\Bpjs\BpjsPractitioner;
use App\Models\Mapping\OrganizationMap;
use App\Models\Simrs\RegPeriksa;

class ErmBuildContext
{
    public RegPeriksa $reg;
    public string $jenisPelayanan = '1';
    public array $sepData = [];
    public array $patientData = [];
    public array $hospitalOrganization = [];
    public array $unitOrganization = [];
    public ?array $pharmacyOrganization = null;
    public ?array $pharmacyPerformer = null;
    public ?array $labOrganization = null;
    public ?array $labPerformer = null;
    public ?array $radOrganization = null;
    public ?array $radPerformer = null;
    public array $healthServiceData = [];
    public string $idPractitioner = '';
    public string $idComposition = '';
    public array $encounterData = [];
    public array $conditions = [];
    public array $compositionSections = [];
    public array $diagnosticIds = [];
    public array $diagnosticTestResults = [];

    public string $hospitalCode = 'RS';
    public string $pharmacyCode = 'FAR';
    public string $labCode = 'LAB';
    public string $radCode = 'RAD';
    public string $unitCode = 'RJ';

    public static function init(RegPeriksa $reg): self
    {
        $ctx = new self();
        $ctx->reg = $reg;
        $ctx->jenisPelayanan = ErmHelper::getJenisPelayanan($reg);

        $reg->load([
            'pasien',
            'dokter.pegawai',
            'poliklinik',
            'penjab',
            'bridgingSep',
            'diagnosaPasien.penyakit',
            'prosedurPasien.icd9',
            'pemeriksaanRalan',
            'pemeriksaanRanap',
            'kamarInap',
            'mutasiBerkas',
            'resepPulang.dataBarang',
            'detailPemberianObat.dataBarang',
            'rawatJlDr',
            'rawatJlPr',
            'rawatJlDrPr',
            'rawatInapDr',
            'rawatInapPr',
            'rawatInapDrPr',
            'permintaanLab.periksaLab.jenisPerawatan',
            'permintaanLab.kesanSaran',
            'permintaanRadiologi.dokterPerujuk',
            'rawatJlDr.jenisPerawatan',
            'rawatJlPr.jenisPerawatan',
            'rawatJlDrPr.jenisPerawatan',
            'rawatInapDr.jenisPerawatan',
            'rawatInapPr.jenisPerawatan',
            'rawatInapDrPr.jenisPerawatan',
        ]);

        $isIgd = \Illuminate\Support\Str::contains(strtolower($reg->poliklinik?->nm_poli ?? ''), 'gawat') || \Illuminate\Support\Str::contains(strtolower($reg->poliklinik?->nm_poli ?? ''), 'darurat');
        $ctx->unitCode = $ctx->jenisPelayanan === '1' ? 'RI' : ($isIgd ? 'IGD' : 'RJ');

        $ctx->sepData = $reg->bridgingSep->toArray();

        $ctx->idPractitioner = $ctx->generateId(
            BpjsPractitioner::where('identifier', $reg->kd_dokter)->firstOrFail()->id
        );
        $ctx->patientData = [
            'id' => $ctx->generateId(BpjsPatient::where('nik', $reg->pasien->no_ktp)->firstOrFail()->id),
            'data' => $reg->pasien->toArray(),
        ];

        $hospitalOrgData = BpjsOrganization::where('identifier', $ctx->hospitalCode)->firstOrFail();
        $ctx->hospitalOrganization = [
            'id' => $ctx->generateId($hospitalOrgData->id),
            'display' => $hospitalOrgData->name,
            'code' => $ctx->hospitalCode,
            'map' => OrganizationMap::select('org_type_code', 'org_type_term', 'org_type_display')
                ->where('dep_id', $ctx->hospitalCode)->first()->toArray(),
        ];

        $unitOrgData = BpjsOrganization::where('identifier', $ctx->unitCode)->firstOrFail();
        $ctx->unitOrganization = [
            'id' => $ctx->generateId($unitOrgData->id),
            'display' => $unitOrgData->name,
            'code' => $ctx->unitCode,
            'map' => OrganizationMap::select('org_type_code', 'org_type_term', 'org_type_display')
                ->where('dep_id', $ctx->unitCode)->first()->toArray(),
        ];

        $healthServiceData = BpjsHealthcareService::where('type', $ctx->jenisPelayanan === '1' ? 'bangsal' : 'poliklinik')
            ->where('local_code', $ctx->jenisPelayanan === '1' ? $reg->kamarInap->kd_kamar : $reg->kd_poli)
            ->firstOrFail();
        $ctx->healthServiceData = [
            'id' => $ctx->generateId($healthServiceData->id),
            'local_code' => $healthServiceData->local_code,
            'name' => $healthServiceData->name,
            'service_type' => [
                'code' => $healthServiceData->map?->service_type_code,
                'term' => $healthServiceData->map?->service_type_term,
                'display' => $healthServiceData->map?->service_type_display,
            ],
            'service_category' => [
                'code' => $healthServiceData->map?->service_category_code,
                'term' => $healthServiceData->map?->service_category_term,
                'display' => $healthServiceData->map?->service_category_display,
            ],
            'location' => [
                'code' => $healthServiceData->map?->location_code,
                'term' => $healthServiceData->map?->location_term,
                'display' => $healthServiceData->map?->location_display,
            ],
        ];

        $ctx->idComposition = $ctx->generateId();

        return $ctx;
    }

    public function generateId(?string $id = null): string
    {
        return ErmHelper::generateId($this->jenisPelayanan, $id);
    }
}
