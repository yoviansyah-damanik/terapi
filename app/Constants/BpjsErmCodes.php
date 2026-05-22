<?php

namespace App\Constants;

/**
 * Konstanta kode standar FHIR untuk BPJS eRM.
 *
 * LOINC codes — referensi: https://loinc.org
 * HL7 categories — referensi: https://terminology.hl7.org
 */
final class BpjsErmCodes
{
    // =========================================================
    // LOINC — Vital Signs
    // =========================================================

    /** Suhu tubuh */
    const LOINC_VITAL_BODY_TEMPERATURE = '8310-5';

    /** Panel tekanan darah */
    const LOINC_VITAL_BLOOD_PRESSURE_PANEL = '18684-1';

    /** Tekanan darah sistolik */
    const LOINC_VITAL_BP_SYSTOLIC = '8480-6';

    /** Tekanan darah diastolik */
    const LOINC_VITAL_BP_DIASTOLIC = '8462-4';

    /** Denyut nadi / heart rate */
    const LOINC_VITAL_HEART_RATE = '8867-4';

    /** Laju pernapasan */
    const LOINC_VITAL_RESPIRATORY_RATE = '9279-1';

    /** Saturasi oksigen (SpO2) */
    const LOINC_VITAL_SPO2 = '59408-5';

    /** GCS — Glasgow Coma Scale total */
    const LOINC_VITAL_GCS = '9269-2';

    /** Tingkat kesadaran (verbal/deskriptif) */
    const LOINC_VITAL_CONSCIOUSNESS = '80288-4';

    /** Tinggi badan */
    const LOINC_VITAL_BODY_HEIGHT = '8302-2';

    /** Berat badan */
    const LOINC_VITAL_BODY_WEIGHT = '29463-7';

    /** Lingkar perut */
    const LOINC_VITAL_WAIST_CIRCUMFERENCE = '8280-0';

    // =========================================================
    // LOINC — Composition Sections
    // =========================================================

    /** Hospital discharge instructions */
    const LOINC_SECTION_DISCHARGE_INSTRUCTIONS = '8653-8';

    /** Plan of care note */
    const LOINC_SECTION_PLAN_OF_CARE = '18776-5';

    /** Physical findings Narrative */
    const LOINC_SECTION_PHYSICAL_FINDINGS = '29545-1';

    /** Assessment note Narrative */
    const LOINC_SECTION_ASSESSMENT = '51848-0';

    /** Chief complaint Narrative */
    const LOINC_SECTION_CHIEF_COMPLAINT = '10154-3';

    /** Medical records */
    const LOINC_SECTION_MEDICAL_RECORDS = '11503-0';

    /** Relevant diagnostic tests/results Narrative */
    const LOINC_SECTION_DIAGNOSTIC_RESULTS = '30954-2';

    /** Relevant vital signs */
    const LOINC_SECTION_VITAL_SIGNS_RESULTS = '85353-1';

    /** Relevant Admission Reason */
    const LOINC_SECTION_ADMISSION_REASON = '104889-1';

    /** Relevant Admission Diagnosis */
    const LOINC_SECTION_ADMISSION_DIAGNOSIS = '42347-5';

    // =========================================================
    // HL7 System URLs — dipakai di coding dinamis (code/display bervariasi)
    // =========================================================

    const SYSTEM_V2_0203 = 'http://terminology.hl7.org/CodeSystem/v2-0203';
    const SYSTEM_V3_ACT_CODE = 'http://terminology.hl7.org/CodeSystem/v3-ActCode';
    const SYSTEM_V3_MARITAL_STATUS = 'http://terminology.hl7.org/CodeSystem/v3-MaritalStatus';
    const SYSTEM_OBSERVATION_INTERPRETATION = 'http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation';
    const SYSTEM_DIAGNOSIS_ROLE = 'http://terminology.hl7.org/CodeSystem/diagnosis-role';
    const SYSTEM_ORGANIZATION_TYPE = 'http://terminology.hl7.org/CodeSystem/organization-type';
    const SYSTEM_CONTACT_ENTITY_TYPE = 'http://terminology.hl7.org/CodeSystem/contactentity-type';
    const SYSTEM_DIAGNOSTIC_SERVICE = 'http://terminology.hl7.org/CodeSystem/v2-0074';
    const SYSTEM_PRESCRIPTION_CATEGORY = 'http://terminology.hl7.org/prescription-category';
    const SYSTEM_LOINC = 'http://loinc.org';
    const SYSTEM_UCUM = 'http://unitsofmeasure.org';
    const SYSTEM_NIK = 'https://dukcapil.kemendagri.go.id';
    const SYSTEM_SIP = 'https://satusehat.kemkes.go.id/sdmk';

    // =========================================================
    // Static Codings — system + code + display sudah pasti
    // =========================================================

    /** Medical record number identifier */
    const CODING_ID_MEDICAL_RECORD = [
        'system' => self::SYSTEM_V2_0203,
        'code' => 'MR',
        'display' => 'Medical record number',
    ];

    /** BPJS member number identifier */
    const CODING_ID_MEMBER_NUMBER = [
        'system' => self::SYSTEM_V2_0203,
        'code' => 'MB',
        'display' => 'Member Number',
    ];

    /** NIK / national ID identifier */
    const CODING_ID_NIK = [
        'system' => self::SYSTEM_V2_0203,
        'code' => 'NI',
        'display' => 'National unique individual identifier',
    ];

    /** Medical license number (SIP) identifier */
    const CODING_ID_LICENSE = [
        'system' => self::SYSTEM_V2_0203,
        'code' => 'MD',
        'display' => 'Medical license number',
    ];

    /** Visit / encounter number identifier */
    const CODING_ID_VISIT_NUMBER = [
        'system' => self::SYSTEM_V2_0203,
        'code' => 'VN',
        'display' => 'Visit number',
    ];

    /** Resource identifier (nomor rujukan) */
    const CODING_ID_RESOURCE = [
        'system' => self::SYSTEM_V2_0203,
        'code' => 'RI',
        'display' => 'Resource identifier',
    ];

    /** Organization type: healthcare provider */
    const CODING_ORG_TYPE_PROVIDER = [
        'system' => self::SYSTEM_ORGANIZATION_TYPE,
        'code' => 'prov',
        'display' => 'Healthcare Provider',
    ];

    /** Contact entity type: administrative */
    const CODING_CONTACT_ADMIN = [
        'system' => self::SYSTEM_CONTACT_ENTITY_TYPE,
        'code' => 'ADMIN',
        'display' => 'Administrative',
    ];

    /** Diagnosis role: discharge diagnosis */
    const CODING_DIAGNOSIS_ROLE_DD = [
        'system' => self::SYSTEM_DIAGNOSIS_ROLE,
        'code' => 'DD',
        'display' => 'Discharge Diagnosis',
    ];

    // =========================================================
    // HL7 Categories — setiap item berisi system, code, display
    // =========================================================

    /** Vital signs observation category */
    const CATEGORY_VITAL_SIGNS = [
        'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
        'code' => 'vital-signs',
        'display' => 'Vital Signs',
    ];

    /** Encounter diagnosis condition category */
    const CATEGORY_ENCOUNTER_DIAGNOSIS = [
        'system' => 'http://terminology.hl7.org/CodeSystem/condition-category',
        'code' => 'encounter-diagnosis',
        'display' => 'Encounter Diagnosis',
    ];

    /** Laboratory observation category */
    const CATEGORY_LABORATORY = [
        'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
        'code' => 'laboratory',
        'display' => 'Laboratory',
    ];

    /** Procedure category */
    const CATEGORY_PROCEDURE = [
        'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
        'code' => 'procedure',
        'display' => 'Procedure',
    ];

    /** Marital Map */
    const MAP_MARITAL_STATUS = [
        'MENIKAH' => [
            'code' => 'M',
            'text' => 'Married',
        ],
        'BELUM MENIKAH' => [
            'code' => 'S',
            'text' => 'Never Married',
        ],
        'JANDA' => [
            'code' => 'W',
            'text' => 'Widowed',
        ],
        'DUDHA' => [
            'code' => 'W',
            'text' => 'Widowed',
        ],
        'CERAI HIDUP' => [
            'code' => 'D',
            'text' => 'Divorced',
        ],
        'CERAI MATI' => [
            'code' => 'W',
            'text' => 'Widowed',
        ],
    ];

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * Bungkus satu coding entry ke dalam struktur FHIR category:
     * [["coding" => [{ system, code, display }]]]
     */
    public static function fhirCategory(array $coding): array
    {
        return [['coding' => [$coding]]];
    }

    /**
     * Bungkus satu coding entry ke dalam struktur FHIR coding:
     * ["coding" => [{ system, code, display }]]
     */
    public static function fhirCoding(array $coding): array
    {
        return ['coding' => [$coding]];
    }
}
