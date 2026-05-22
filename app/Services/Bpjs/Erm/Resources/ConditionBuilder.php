<?php

namespace App\Services\Bpjs\Erm\Resources;

use App\Constants\BpjsErmCodes;
use App\Helpers\ErmHelper;
use App\Models\Mapping\Icd10Map;

class ConditionBuilder extends BaseResourceBuilder
{
    /** @return array Bundle entries for all condition resources */
    public function build(): array
    {
        $reg = $this->ctx->reg;

        if ($reg->diagnosaPasien->isEmpty()) {
            return [];
        }

        $conditions = [];
        $diagnoseCreateDateTime = ErmHelper::formatDateTime(
            $reg->tgl_registrasi->format('Y-m-d') . ' ' . $reg->jam_reg
        );

        foreach ($reg->diagnosaPasien as $index => $diagnosa) {
            $isPrimary = $index === 0;

            $diagnoseCode = $isPrimary && !empty($reg->bridgingSep->diagawal)
                ? $reg->bridgingSep->diagawal
                : ($diagnosa->kd_penyakit ?? '');
                
            $diagnoseDisplay = $isPrimary && !empty($reg->bridgingSep->nmdiagnosaawal)
                ? $reg->bridgingSep->nmdiagnosaawal
                : ($diagnosa->penyakit?->nm_penyakit ?? '');

            $icdMap = Icd10Map::where('icd10_code', $diagnosa->kd_penyakit)->first();

            $codings = [];
            
            // ICD-10 coding
            $codings[] = [
                'system' => 'http://hl7.org/fhir/sid/icd-10',
                'code' => $diagnoseCode,
                'display' => $diagnoseDisplay,
            ];

            // SNOMED coding from map
            if ($icdMap && $icdMap->system_code) {
                $codings[] = [
                    'system' => $icdMap->system_display ?? '',
                    'code' => $icdMap->system_code ?? '',
                    'display' => $icdMap->system_term ?? '',
                ];
            }

            $idCondition = $this->ctx->generateId();
            $conditions[] = [
                'resource' => [
                    'resourceType' => 'Condition',
                    'id' => $idCondition,
                    'clinicalStatus' => 'active',
                    'verificationStatus' => 'confirmed',
                    'category' => BpjsErmCodes::fhirCategory(BpjsErmCodes::CATEGORY_ENCOUNTER_DIAGNOSIS),
                    'code' => [
                        'coding' => $codings,
                        'text' => $diagnoseDisplay,
                    ],
                    'subject' => ['reference' => "Patient/{$this->ctx->patientData['id']}"],
                    'onsetDateTime' => $diagnoseCreateDateTime,
                ],
            ];
        }

        $this->ctx->conditions = $conditions;

        return $conditions;
    }
}
