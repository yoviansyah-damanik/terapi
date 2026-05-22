<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SatuSehat API Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk integrasi dengan API FHIR SatuSehat (Kemenkes).
    | Dokumentasi: https://satusehat.kemkes.go.id/platform/docs/
    |
    */

    'auth_url' => env('SATUSEHAT_AUTH_URL', 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1'),

    'base_url' => env('SATUSEHAT_BASE_URL', 'https://api-satusehat-stg.dto.kemkes.go.id/'),

    'fhir_url' => env('SATUSEHAT_FHIR_URL', 'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1'),

    'consent_url' => env('SATUSEHAT_CONSENT_URL', 'https://api-satusehat-stg.dto.kemkes.go.id/consent/v1'),

    'client_id' => env('SATUSEHAT_CLIENT_ID'),

    'client_secret' => env('SATUSEHAT_CLIENT_SECRET'),

    'organization_id' => env('SATUSEHAT_ORGANIZATION_ID'),

    'timeout' => env('SATUSEHAT_TIMEOUT', 30),

    'debug' => env('SATUSEHAT_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Token Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk caching OAuth token.
    |
    */

    'cache' => [
        'key' => 'satusehat_access_token',
        'buffer_seconds' => 60, // Refresh token 60 detik sebelum expired
    ],

    /*
    |--------------------------------------------------------------------------
    | Daftar FHIR Resource untuk uji koneksi
    |--------------------------------------------------------------------------
    */

    'test_resources' => [
        ['type' => 'Patient',              'label' => 'Patient',              'group' => 'Master',    'method' => 'GET'],
        ['type' => 'Practitioner',         'label' => 'Practitioner',         'group' => 'Master',    'method' => 'GET'],
        ['type' => 'Organization',         'label' => 'Organization',         'group' => 'Master',    'method' => 'GET'],
        ['type' => 'Location',             'label' => 'Location',             'group' => 'Master',    'method' => 'GET'],
        ['type' => 'Encounter',            'label' => 'Encounter',            'group' => 'Klinis',    'method' => 'GET'],
        ['type' => 'Condition',            'label' => 'Condition',            'group' => 'Klinis',    'method' => 'GET'],
        ['type' => 'Observation',          'label' => 'Observation',          'group' => 'Klinis',    'method' => 'GET'],
        ['type' => 'Procedure',            'label' => 'Procedure',            'group' => 'Klinis',    'method' => 'GET'],
        ['type' => 'Composition',          'label' => 'Composition',          'group' => 'Klinis',    'method' => 'GET'],
        ['type' => 'ClinicalImpression',   'label' => 'Clinical Impression',  'group' => 'Klinis',    'method' => 'GET'],
        ['type' => 'AllergyIntolerance',   'label' => 'Allergy Intolerance',  'group' => 'Klinis',    'method' => 'GET'],
        ['type' => 'Immunization',         'label' => 'Immunization',         'group' => 'Klinis',    'method' => 'GET'],
        ['type' => 'CarePlan',             'label' => 'Care Plan',            'group' => 'Klinis',    'method' => 'GET'],
        ['type' => 'EpisodeOfCare',        'label' => 'Episode of Care',      'group' => 'Klinis',    'method' => 'GET'],
        ['type' => 'Medication',           'label' => 'Medication',           'group' => 'Farmasi',   'method' => 'GET'],
        ['type' => 'MedicationRequest',    'label' => 'Medication Request',   'group' => 'Farmasi',   'method' => 'GET'],
        ['type' => 'MedicationDispense',   'label' => 'Medication Dispense',  'group' => 'Farmasi',   'method' => 'GET'],
        ['type' => 'ServiceRequest',       'label' => 'Service Request',      'group' => 'Penunjang', 'method' => 'GET'],
        ['type' => 'DiagnosticReport',     'label' => 'Diagnostic Report',    'group' => 'Penunjang', 'method' => 'GET'],
        ['type' => 'Specimen',             'label' => 'Specimen',             'group' => 'Penunjang', 'method' => 'GET'],
    ],

    'kode_ppk_kemenkes' => env('KODE_PPK_RS_KEMENKES'),
    'nama_ppk_kemenkes' => env('NAMA_PPK_RS_KEMENKES'),

    /*
     * Jeda antar API call (ms) untuk menghindari rate limiter Satu Sehat.
     * Default 300ms. Set 0 untuk nonaktifkan.
     */
    'api_delay_ms' => (int) env('SATUSEHAT_API_DELAY_MS', 300),

    /*
     * Maksimal request write ke Satu Sehat per menit.
     * Jika tercapai, proses akan menunggu hingga awal menit berikutnya.
     * Set 0 untuk nonaktifkan rate limit.
     */
    'rate_limit_per_minute' => (int) env('SATUSEHAT_RATE_LIMIT_PER_MINUTE', 50),
];
