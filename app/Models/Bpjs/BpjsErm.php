<?php

namespace App\Models\Bpjs;

use App\Models\BaseModel;
use Carbon\Carbon;

class BpjsErm extends BaseModel
{
    protected $table = 'bpjs_erm';

    protected $fillable = [
        'no_rawat',
        'no_sep',
        'bundle_id',
        'jenis_pelayanan',
        'bulan',
        'tahun',
        'room_code',
        'doctor_code',
        'encounter_type',
        'bundle',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'bundle'   => 'array',
            'sent_at'  => 'datetime',
        ];
    }

    // --- Accessor helpers untuk mengambil resource dari bundle entry ---

    private function findResource(string $resourceType): ?array
    {
        foreach ($this->bundle['entry'] ?? [] as $entry) {
            $r = $entry['resource'] ?? null;
            if (is_array($r) && !isset($r[0]) && ($r['resourceType'] ?? '') === $resourceType) {
                return $r;
            }
        }
        return null;
    }

    /** Kumpulkan semua resource yang dibungkus array dalam entry.resource */
    private function findAllArrayResources(string $resourceType): array
    {
        $results = [];
        foreach ($this->bundle['entry'] ?? [] as $entry) {
            $r = $entry['resource'] ?? null;
            if (is_array($r) && isset($r[0]) && ($r[0]['resourceType'] ?? '') === $resourceType) {
                $results[] = $r[0];
            }
        }
        return $results;
    }

    // --- Accessor: Patient ---

    public function getPatientResourceAttribute(): ?array
    {
        return $this->findResource('Patient');
    }

    public function getPatientNameAttribute(): ?string
    {
        $patient = $this->findResource('Patient');
        return $patient['name'][0]['text'] ?? null;
    }

    public function getPatientNikAttribute(): ?string
    {
        $patient = $this->findResource('Patient');
        foreach ($patient['identifier'] ?? [] as $ident) {
            if (($ident['type']['coding'][0]['code'] ?? '') === 'NI') {
                return $ident['value'] ?? null;
            }
        }
        return null;
    }

    public function getPatientBpjsAttribute(): ?string
    {
        $patient = $this->findResource('Patient');
        foreach ($patient['identifier'] ?? [] as $ident) {
            if (($ident['type']['coding'][0]['code'] ?? '') === 'MB') {
                return $ident['value'] ?? null;
            }
        }
        return null;
    }

    public function getEncounterTypeLabelAttribute(): string
    {
        return match ($this->encounter_type) {
            'EMER' => 'IGD',
            'AMB'  => 'Rawat Jalan',
            'IMP'  => 'Rawat Inap',
            default => $this->encounter_type ?? '-',
        };
    }

    // --- Accessor: Encounter ---

    public function getEncounterStatusAttribute(): ?string
    {
        return $this->findResource('Encounter')['status'] ?? null;
    }

    public function getPeriodStartAttribute(): ?Carbon
    {
        $val = $this->findResource('Encounter')['period']['start'] ?? null;
        return $val ? Carbon::parse($val) : null;
    }

    public function getPeriodEndAttribute(): ?Carbon
    {
        $val = $this->findResource('Encounter')['period']['end'] ?? null;
        return $val ? Carbon::parse($val) : null;
    }

    public function getDiagnosaUtamaAttribute(): ?string
    {
        $enc = $this->findResource('Encounter');
        return $enc['reason'][0]['coding'][0]['code'] ?? null;
    }

    public function getDiagnosaUtamaDisplayAttribute(): ?string
    {
        $enc = $this->findResource('Encounter');
        return $enc['reason'][0]['coding'][0]['display'] ?? null;
    }

    public function getDischargeDispositionAttribute(): ?string
    {
        $enc = $this->findResource('Encounter');
        return $enc['hospitalization']['dischargeDisposition'][0]['text'] ?? null;
    }

    // --- Accessor: Practitioner (via Composition author) ---

    public function getPractitionerNameAttribute(): ?string
    {
        $comp = $this->findResource('Composition');
        return $comp['author'][0]['display'] ?? null;
    }

    // --- Accessor: Composition sections (SOAP notes) ---

    public function getCompositionSectionsAttribute(): array
    {
        $comp = $this->findResource('Composition');
        $result = [];
        foreach ($comp['section'] ?? [] as $section) {
            if (!is_array($section) || !isset($section['title'])) {
                continue;
            }
            $coding = $section['code']['coding'][0] ?? null;
            $result[] = [
                'title'      => $section['title'],
                'text'       => $section['text']['div'] ?? null,
                'textStatus' => $section['text']['status'] ?? null,
                'code'       => $coding['code'] ?? null,
                'system'     => $coding['system'] ?? null,
                'display'    => $coding['display'] ?? null,
                'entries'    => array_column($section['entry'] ?? [], 'reference'),
            ];
        }
        return $result;
    }

    // --- Accessor: MedicationRequest ---

    public function getMedicationsAttribute(): array
    {
        return $this->findAllArrayResources('MedicationRequest');
    }

    // --- Accessor: Procedure ---

    public function getProceduresAttribute(): array
    {
        return $this->findAllArrayResources('Procedure');
    }

    // --- Accessor: DiagnosticReport per kategori ---

    public function getLabReportsAttribute(): array
    {
        return array_values(array_filter(
            $this->findAllArrayResources('DiagnosticReport'),
            fn($r) => ($r['category']['coding']['code'] ?? '') === 'LAB',
        ));
    }

    public function getRadiologyReportsAttribute(): array
    {
        return array_values(array_filter(
            $this->findAllArrayResources('DiagnosticReport'),
            fn($r) => ($r['category']['coding']['code'] ?? '') === 'RAD',
        ));
    }

    // --- Accessor: Condition resources ---

    public function getConditionsAttribute(): array
    {
        $conditions = [];
        foreach ($this->bundle['entry'] ?? [] as $entry) {
            $r = $entry['resource'] ?? null;
            if (is_array($r) && !isset($r[0]) && ($r['resourceType'] ?? '') === 'Condition') {
                $conditions[] = $r;
            }
        }
        return $conditions;
    }

    // --- Accessor: Organization name ---

    public function getOrganizationNameAttribute(): ?string
    {
        $patient = $this->findResource('Patient');
        return $patient['managingOrganization']['display'] ?? null;
    }

    // --- Accessor: Semua diagnosa dari Encounter.diagnosis ---

    public function getAllDiagnosesAttribute(): array
    {
        $enc = $this->findResource('Encounter');
        return $enc['diagnosis'] ?? [];
    }

    // --- Label jenis pelayanan ---

    public function getJenisPelayananLabelAttribute(): string
    {
        return match ((int) $this->jenis_pelayanan) {
            1 => 'Rawat Inap',
            2 => 'Rawat Jalan',
            default => '-',
        };
    }

    /**
     * Resolve a list of FHIR reference strings (e.g., 'DiagnosticReport/123') 
     * into their actual resource array payloads from the bundle.
     */
    public function resolveReferences(array $references): array
    {
        if (empty($references)) {
            return [];
        }
        
        $results = [];
        $refMap = array_flip($references);
        
        foreach ($this->bundle['entry'] ?? [] as $entry) {
            $r = $entry['resource'] ?? null;
            if (is_array($r) && !isset($r[0])) {
                $refStr = ($r['resourceType'] ?? '') . '/' . ($r['id'] ?? '');
                if (isset($refMap[$refStr])) {
                    $results[$refStr] = $r;
                }
            } else if (is_array($r) && isset($r[0])) {
                foreach ($r as $subR) {
                    if (is_array($subR)) {
                        $refStr = ($subR['resourceType'] ?? '') . '/' . ($subR['id'] ?? '');
                        if (isset($refMap[$refStr])) {
                            $results[$refStr] = $subR;
                        }
                    }
                }
            }
        }
        
        return $results;
    }
}
