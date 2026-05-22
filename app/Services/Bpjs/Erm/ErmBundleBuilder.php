<?php

namespace App\Services\Bpjs\Erm;

use App\Helpers\ErmHelper;
use App\Models\Simrs\RegPeriksa;
use App\Services\Bpjs\Erm\Resources\CompositionBuilder;
use App\Services\Bpjs\Erm\Resources\ConditionBuilder;
use App\Services\Bpjs\Erm\Resources\DiagnosticLabBuilder;
use App\Services\Bpjs\Erm\Resources\DiagnosticRadBuilder;
use App\Services\Bpjs\Erm\Resources\EncounterBuilder;
use App\Services\Bpjs\Erm\Resources\MedicationRequestBuilder;
use App\Services\Bpjs\Erm\Resources\OrganizationBuilder;
use App\Services\Bpjs\Erm\Resources\PatientBuilder;
use App\Services\Bpjs\Erm\Resources\PractitionerBuilder;
use App\Services\Bpjs\Erm\Resources\ProcedureBuilder;
use App\Services\Bpjs\Erm\Resources\VitalSignBuilder;
use App\Services\Bpjs\ErmValidator;

class ErmBundleBuilder
{
    public function build(RegPeriksa $reg): array
    {
        $enabledModules = ErmModuleRegistry::enabled();
        $validator = new ErmValidator();
        $issues = $validator->validate($reg, $enabledModules);

        if ($validator->hasErrors($issues)) {
            $errors = collect($issues)
                ->filter(fn($i) => $i['type'] === 'error')
                ->map(fn($i) => "[{$i['section']}] {$i['text']}")
                ->implode('; ');
            throw new \RuntimeException("Mapping belum lengkap: {$errors}");
        }

        $ctx = ErmBuildContext::init($reg);
        $bundle = $this->initBundle($ctx);

        // Wajib
        $bundle['entry'] = array_merge($bundle['entry'], (new ConditionBuilder($ctx))->build());
        $bundle['entry'][] = (new EncounterBuilder($ctx))->build();
        $bundle['entry'][] = (new OrganizationBuilder($ctx))->buildHospital();
        $bundle['entry'][] = (new OrganizationBuilder($ctx))->buildForOrganization($ctx->unitOrganization);
        $bundle['entry'][] = (new PatientBuilder($ctx))->build();
        $bundle['entry'][] = (new PractitionerBuilder($ctx))->build();

        // Opsional
        if (ErmModuleRegistry::isEnabled('procedure')) {
            $bundle['entry'] = array_merge($bundle['entry'], (new ProcedureBuilder($ctx))->build());
        }
        if (ErmModuleRegistry::isEnabled('medication')) {
            $bundle['entry'] = array_merge($bundle['entry'], (new MedicationRequestBuilder($ctx))->build());
        }
        if (ErmModuleRegistry::isEnabled('lab')) {
            $bundle['entry'] = array_merge($bundle['entry'], (new DiagnosticLabBuilder($ctx))->build());
        }
        if (ErmModuleRegistry::isEnabled('radiologi')) {
            $bundle['entry'] = array_merge($bundle['entry'], (new DiagnosticRadBuilder($ctx))->build());
        }
        if (ErmModuleRegistry::isEnabled('vital_sign')) {
            $bundle['entry'] = array_merge($bundle['entry'], (new VitalSignBuilder($ctx))->build());
        }

        // Composition selalu terakhir (butuh semua section terakumulasi)
        $bundle['entry'][] = (new CompositionBuilder($ctx))->build();

        return [
            ...$bundle,
            'entry' => $this->sortBundle($bundle['entry']),
        ];
    }

    private function initBundle(ErmBuildContext $ctx): array
    {
        return [
            'resourceType' => 'Bundle',
            'id' => $ctx->generateId(),
            'meta' => ['lastUpdated' => ErmHelper::formatDateTime(now())],
            'identifier' => [
                'use' => null,
                'type' => ['coding' => [], 'text' => null],
                'system' => 'SEP',
                'value' => $ctx->sepData['no_sep'],
                'assigner' => ['display' => null],
            ],
            'type' => 'Document',
            'entry' => [],
        ];
    }

    private function sortBundle(array $entries): array
    {
        $sortOrder = ['Encounter', 'Patient', 'MedicationRequest', 'Practitioner', 'Organization', 'Condition', 'DiagnosticReport', 'Procedure', 'Device', 'Composition'];
        $sorted = [];

        foreach ($sortOrder as $resourceType) {
            foreach ($entries as $index => $entry) {
                $res = $entry['resource'];
                $type = $res['resourceType'] ?? ($res[0]['resourceType'] ?? null);
                if ($type === $resourceType) {
                    $sorted[] = $entry;
                    unset($entries[$index]);
                }
            }
        }

        foreach ($entries as $entry) {
            $sorted[] = $entry;
        }

        return $sorted;
    }
}
