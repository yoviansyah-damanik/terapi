<?php

namespace App\Models\SatuSehat;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SatuSehatLog extends BaseModel
{

    protected $table = 'satu_sehat_logs';

    protected $fillable = [
        'user_id',
        'resource_type',
        'action',
        'method',
        'endpoint',
        'request_params',
        'request_body',
        'response_status',
        'response_body',
        'ihs_number',
        'patient_nik',
        'response_time',
        'is_success',
        'error_message',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'request_params' => 'array',
            'request_body' => 'array',
            'response_body' => 'array',
            'response_time' => 'decimal:2',
            'is_success' => 'boolean',
        ];
    }

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ========== SCOPES ==========

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('is_success', true);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('is_success', false);
    }

    public function scopeForResource(Builder $query, string $resourceType): Builder
    {
        return $query->where('resource_type', $resourceType);
    }

    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('endpoint', 'like', "%{$search}%")
                ->orWhere('ihs_number', 'like', "%{$search}%")
                ->orWhere('patient_nik', 'like', "%{$search}%")
                ->orWhere('error_message', 'like', "%{$search}%");
        });
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    // ========== HELPERS ==========

    public static function log(
        string $resourceType,
        string $action,
        string $method,
        string $endpoint,
        ?array $requestParams = null,
        ?array $requestBody = null,
        ?int $responseStatus = null,
        ?array $responseBody = null,
        ?string $ihsNumber = null,
        ?string $patientNik = null,
        ?float $responseTime = null,
        bool $isSuccess = false,
        ?string $errorMessage = null,
    ): static {
        return static::create([
            'user_id' => auth()->id(),
            'resource_type' => $resourceType,
            'action' => $action,
            'method' => $method,
            'endpoint' => $endpoint,
            'request_params' => $requestParams,
            'request_body' => $requestBody,
            'response_status' => $responseStatus,
            'response_body' => $responseBody,
            'ihs_number' => $ihsNumber,
            'patient_nik' => $patientNik,
            'response_time' => $responseTime,
            'is_success' => $isSuccess,
            'error_message' => $errorMessage,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function getStatusBadgeColorAttribute(): string
    {
        if ($this->is_success) {
            return 'green';
        }

        return match (true) {
            $this->response_status >= 500 => 'red',
            $this->response_status >= 400 => 'amber',
            default => 'zinc',
        };
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'search' => 'Pencarian',
            'find' => 'Detail',
            'create' => 'Buat Baru',
            'update' => 'Update',
            'patch' => 'Patch',
            'delete' => 'Hapus',
            'auth' => 'Autentikasi',
            default => ucfirst($this->action),
        };
    }

    public function getMethodBadgeColorAttribute(): string
    {
        return match ($this->method) {
            'GET' => 'blue',
            'POST' => 'green',
            'PUT' => 'amber',
            'PATCH' => 'purple',
            'DELETE' => 'red',
            default => 'zinc',
        };
    }

    public static function getResourceTypes(): array
    {
        return [
            'Patient' => 'Patient',
            'Practitioner' => 'Practitioner',
            'Organization' => 'Organization',
            'Location' => 'Location',
            'Encounter' => 'Encounter',
            'Condition' => 'Condition',
            'Observation' => 'Observation',
            'Medication' => 'Medication',
            'MedicationRequest' => 'Medication Request',
            'MedicationDispense' => 'Medication Dispense',
            'MedicationAdministration' => 'Medication Administration',
            'NutritionOrder' => 'Nutrition Order',
            'DiagnosticReport' => 'Diagnostic Report',
            'Specimen' => 'Specimen',
            'Procedure' => 'Procedure',
            'Composition' => 'Composition',
            'AllergyIntolerance' => 'Allergy Intolerance',
            'ClinicalImpression' => 'Clinical Impression',
            'Immunization' => 'Immunization',
            'CarePlan' => 'Care Plan',
            'EpisodeOfCare' => 'Episode Of Care',
            'OAuth' => 'OAuth Token',
        ];
    }

    public static function getActions(): array
    {
        return [
            'search' => 'Pencarian',
            'find' => 'Detail',
            'create' => 'Buat Baru',
            'update' => 'Update',
            'patch' => 'Patch',
            'delete' => 'Hapus',
            'auth' => 'Autentikasi',
        ];
    }
}
