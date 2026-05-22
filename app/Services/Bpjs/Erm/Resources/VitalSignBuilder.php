<?php

namespace App\Services\Bpjs\Erm\Resources;

use App\Constants\BpjsErmCodes;
use App\Helpers\ErmHelper;
use App\Models\Terminology\Loinc;

class VitalSignBuilder extends BaseResourceBuilder
{
    /** @return array Bundle entries: DiagnosticReport + Observation per vital sign */
    public function build(): array
    {
        $reg = $this->ctx->reg;

        $pemeriksaan = $this->ctx->jenisPelayanan === '1'
            ? $reg->pemeriksaanRanap()->where('no_rawat', $reg->no_rawat)->orderBy('tgl_perawatan', 'desc')->orderBy('jam_rawat', 'desc')->get()
            : $reg->pemeriksaanRalan()->where('no_rawat', $reg->no_rawat)->orderBy('tgl_perawatan', 'desc')->orderBy('jam_rawat', 'desc')->get();

        if ($pemeriksaan->isEmpty()) {
            return [];
        }

        $issueDateTime = ErmHelper::formatDateTime(
            substr($pemeriksaan->first()->tgl_perawatan, 0, 10) . ' ' . $pemeriksaan->first()->jam_rawat
        );

        $vitalValues = [
            'suhu' => $pemeriksaan->whereNotNull('suhu_tubuh')->first()?->suhu_tubuh,
            'tensi' => $pemeriksaan->whereNotNull('tensi')->first()?->tensi,
            'nadi' => $pemeriksaan->whereNotNull('nadi')->first()?->nadi,
            'respirasi' => $pemeriksaan->whereNotNull('respirasi')->first()?->respirasi,
            'spo2' => $pemeriksaan->whereNotNull('spo2')->first()?->spo2,
            'gcs' => $pemeriksaan->whereNotNull('gcs')->first()?->gcs,
            'kesadaran' => $pemeriksaan->whereNotNull('kesadaran')->first()?->kesadaran,
            'tinggi_badan' => $pemeriksaan->whereNotNull('tinggi')->first()?->tinggi,
            'berat_badan' => $pemeriksaan->whereNotNull('berat')->first()?->berat,
            'lingkar_perut' => $pemeriksaan->whereNotNull('lingkar_perut')->first()?->lingkar_perut,
        ];

        $textLabels = [
            'suhu' => fn($v) => "<div><strong>Suhu:</strong> {$v} °C</div>",
            'tensi' => fn($v) => "<div><strong>Tensi:</strong> {$v}</div>",
            'nadi' => fn($v) => "<div><strong>Nadi:</strong> {$v} x/menit</div>",
            'respirasi' => fn($v) => "<div><strong>Respirasi:</strong> {$v} x/menit</div>",
            'spo2' => fn($v) => "<div><strong>SpO2:</strong> {$v} %</div>",
            'gcs' => fn($v) => "<div><strong>GCS:</strong> {$v}</div>",
            'kesadaran' => fn($v) => "<div><strong>Kesadaran:</strong> {$v}</div>",
            'tinggi_badan' => fn($v) => "<div><strong>Tinggi </strong>Badan: {$v} cm</div>",
            'berat_badan' => fn($v) => "<div><strong>Berat </strong>Badan: {$v} kg</div>",
            'lingkar_perut' => fn($v) => "<div><strong>Lingkar Perut:</strong> {$v} cm</div>",
        ];

        $entries = [];
        $vitalSignIds = [];
        $vitalSignTexts = [];

        foreach ($vitalValues as $type => $value) {
            if (!$value) {
                continue;
            }

            $id = $this->ctx->generateId();
            $textEntry = $textLabels[$type]($value);
            $vitalSignIds[] = $id;
            $vitalSignTexts[] = $textEntry;

            $observation = $this->buildVitalSignObservation($id, $type, $value, $issueDateTime, $textEntry);
            $report = $this->buildDiagnosticReportResource($observation);
            $entries[] = ['resource' => [$report]];
        }

        if (!empty($vitalSignIds)) {
            $this->ctx->compositionSections[] = $this->buildVitalSignCompositionSection(
                $vitalSignIds,
                implode('', $vitalSignTexts)
            );
        }

        return $entries;
    }

    private function buildDiagnosticReportResource(array $observation): array
    {
        return [
            'resourceType' => 'DiagnosticReport',
            'id' => $this->ctx->generateId(),
            'status' => 'final',
            'category' => [
                'coding' => [
                    'system' => BpjsErmCodes::SYSTEM_DIAGNOSTIC_SERVICE,
                    'code' => 'vital-signs',
                    'display' => 'Vital Signs',
                ],
            ],
            'subject' => [
                'reference' => "Patient/{$this->ctx->patientData['id']}",
                'display' => $this->ctx->patientData['data']['nm_pasien'],
                'noSep' => $this->ctx->reg->bridgingSep?->no_sep ?? '',
            ],
            'performer' => [
                [
                    'reference' => 'Organization/' . $this->ctx->unitOrganization['id'],
                    'display' => $this->ctx->unitOrganization['display'],
                ],
            ],
            'specimen' => [],
            'result' => [$observation],
        ];
    }

    private function buildVitalSignObservation(string $id, string $type, $data, string $issueDateTime, string $text): array
    {
        $vitalCategory = BpjsErmCodes::fhirCategory(BpjsErmCodes::CATEGORY_VITAL_SIGNS);
        $additional = $this->buildVitalSignAdditional($type, $data);
        $code = $this->getLoincCode($type);
        $category = Loinc::where('loinc_num', $code)->firstOrFail();

        return [
            'resourceType' => 'Observation',
            'id' => $id,
            'status' => 'final',
            'category' => $vitalCategory,
            'code' => [
                'coding' => [
                    'system' => BpjsErmCodes::SYSTEM_LOINC,
                    'code' => $category->loinc_num,
                    'display' => $category->long_common_name,
                ],
            ],
            'subject' => ['reference' => "Patient/{$this->ctx->patientData['id']}"],
            'performer' => [['reference' => "Practitioner/{$this->ctx->idPractitioner}"]],
            'encounter' => [
                'reference' => "Encounter/{$this->ctx->encounterData['id']}",
                'display' => 'Pemeriksaan TTV',
            ],
            'issued' => $issueDateTime,
            'effectiveDateTime' => $issueDateTime,
            'interpretation' => ['coding' => ['value' => '', 'code' => '']],
            'referenceRange' => ['low' => ['value' => '', 'code' => ''], 'high' => ['value' => '', 'code' => '']],
            'valueQuantity' => ['value' => '', 'unit' => '', 'code' => ''],
            ...$additional,
            'conclusion' => $text,
        ];
    }

    private function getLoincCode(string $type): string
    {
        return match ($type) {
            'suhu' => BpjsErmCodes::LOINC_VITAL_BODY_TEMPERATURE,
            'tensi' => BpjsErmCodes::LOINC_VITAL_BLOOD_PRESSURE_PANEL,
            'nadi' => BpjsErmCodes::LOINC_VITAL_HEART_RATE,
            'respirasi' => BpjsErmCodes::LOINC_VITAL_RESPIRATORY_RATE,
            'spo2' => BpjsErmCodes::LOINC_VITAL_SPO2,
            'gcs' => BpjsErmCodes::LOINC_VITAL_GCS,
            'kesadaran' => BpjsErmCodes::LOINC_VITAL_CONSCIOUSNESS,
            'tinggi_badan' => BpjsErmCodes::LOINC_VITAL_BODY_HEIGHT,
            'berat_badan' => BpjsErmCodes::LOINC_VITAL_BODY_WEIGHT,
            'lingkar_perut' => BpjsErmCodes::LOINC_VITAL_WAIST_CIRCUMFERENCE,
            default => '',
        };
    }

    private function buildVitalSignAdditional(string $type, $data): array
    {
        return match ($type) {
            'suhu' => ['valueQuantity' => ['value' => $data, 'unit' => 'degree Celcius', 'system' => BpjsErmCodes::SYSTEM_UCUM, 'code' => 'Cel']],
            'tensi' => $this->buildBloodPressureComponent($data),
            'nadi' => ['valueQuantity' => ['value' => $data, 'unit' => '/min']],
            'respirasi' => ['valueQuantity' => ['value' => $data, 'unit' => '/min']],
            'spo2' => ['valueQuantity' => ['value' => $data, 'unit' => '%']],
            'gcs' => ['valueQuantity' => ['value' => $data, 'unit' => 'points']],
            'kesadaran' => ['valueCodeableConcept' => ['text' => $data]],
            'tinggi_badan' => ['valueQuantity' => ['value' => $data, 'unit' => 'cm']],
            'berat_badan' => ['valueQuantity' => ['value' => $data, 'unit' => 'kg']],
            'lingkar_perut' => ['valueQuantity' => ['value' => $data, 'unit' => 'cm']],
            default => [],
        };
    }

    private function buildBloodPressureComponent($data): array
    {
        $systolicCategory = Loinc::where('loinc_num', BpjsErmCodes::LOINC_VITAL_BP_SYSTOLIC)->firstOrFail();
        $diastolicCategory = Loinc::where('loinc_num', BpjsErmCodes::LOINC_VITAL_BP_DIASTOLIC)->firstOrFail();

        return [
            'component' => [
                [
                    'code' => [
                        'coding' => [['system' => BpjsErmCodes::SYSTEM_LOINC, 'code' => $systolicCategory->loinc_num, 'display' => $systolicCategory->long_common_name]],
                    ],
                    'valueQuantity' => ['value' => (int) explode('/', $data)[0] ?? 0, 'unit' => 'mmHg', 'system' => BpjsErmCodes::SYSTEM_UCUM, 'code' => 'mm[Hg]'],
                ],
                [
                    'code' => [
                        'coding' => [['system' => BpjsErmCodes::SYSTEM_LOINC, 'code' => $diastolicCategory->loinc_num, 'display' => $diastolicCategory->long_common_name]],
                    ],
                    'valueQuantity' => ['value' => explode('/', $data)[1] ?? 0, 'unit' => 'mmHg'],
                ],
            ],
        ];
    }

    private function buildVitalSignCompositionSection(array $references, string $hasilPemeriksaan): array
    {
        $category = Loinc::where('loinc_num', BpjsErmCodes::LOINC_SECTION_VITAL_SIGNS_RESULTS)->firstOrFail();
        $ids = array_map(fn($id) => ['reference' => "DiagnosticReport/{$id}"], $references);

        return [
            'title' => 'Vital Signs',
            'code' => [
                'coding' => [
                    ['system' => BpjsErmCodes::SYSTEM_LOINC, 'code' => $category->loinc_num, 'display' => $category->long_common_name],
                ],
            ],
            'text' => ['status' => 'additional', 'div' => $hasilPemeriksaan],
            'entry' => $ids,
        ];
    }
}
