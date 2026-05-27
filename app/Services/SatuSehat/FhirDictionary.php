<?php

namespace App\Services\SatuSehat;

/**
 * Kamus URL sistem FHIR yang digunakan dalam integrasi Satu Sehat.
 * Semua URL HL7, KEMKES, DICOM, SNOMED, LOINC harus diambil dari sini.
 */
final class FhirDictionary
{
    // ─── KEMKES Identifier Systems ────────────────────────────────────────────
    const KEMKES_NIK = 'https://fhir.kemkes.go.id/id/nik';
    const KEMKES_NIK_IBU = 'https://fhir.kemkes.go.id/id/nik-ibu';
    const KEMKES_FHIR_BASE = 'https://fhir.kemkes.go.id';
    const KEMKES_FHIR_R4 = 'https://fhir.kemkes.go.id/r4';
    const KEMKES_STRUCT_DEF = 'https://fhir.kemkes.go.id/r4/StructureDefinition';
    const KEMKES_SYS_IDS = 'http://sys-ids.kemkes.go.id';
    const KEMKES_TERMINOLOGY = 'http://terminology.kemkes.go.id';

    // ─── KEMKES Resource-Specific Identifier Systems ──────────────────────────
    const KEMKES_SYS_IMAGING = 'http://sys-ids.kemkes.go.id/imaging';
    const KEMKES_SYS_OBSERVATION = 'http://sys-ids.kemkes.go.id/observation';
    const KEMKES_SYS_SPECIMEN = 'http://sys-ids.kemkes.go.id/specimen';
    const KEMKES_SYS_LOCATION = 'http://sys-ids.kemkes.go.id/location';
    const KEMKES_SYS_ORGANIZATION = 'http://sys-ids.kemkes.go.id/organization';
    const KEMKES_SYS_PRESCRIPTION = 'http://sys-ids.kemkes.go.id/prescription';
    const KEMKES_SYS_PRESCRIPTION_ITEM = 'http://sys-ids.kemkes.go.id/prescription-item';
    const KEMKES_SYS_MEDICATION = 'http://sys-ids.kemkes.go.id/medication';
    const KEMKES_SYS_MEDICATION_STATEMENT = 'http://sys-ids.kemkes.go.id/medicationstatement';
    const KEMKES_SYS_PROCEDURE  = 'http://sys-ids.kemkes.go.id/procedure';
    const KEMKES_SYS_SERVICEREQ = 'http://sys-ids.kemkes.go.id/servicerequest';
    const KEMKES_SYS_ACSN = 'http://sys-ids.kemkes.go.id/acsn';
    const KEMKES_SYS_KFA = 'http://sys-ids.kemkes.go.id/kfa';
    const KEMKES_SYS_ENCOUNTER = 'http://sys-ids.kemkes.go.id/encounter';
    const KEMKES_SYS_EPISODE = 'http://sys-ids.kemkes.go.id/episode-of-care';
    const KEMKES_SYS_DIAGNOSTIC_LAB = 'http://sys-ids.kemkes.go.id/diagnostic';
    const KEMKES_SYS_COMPOSITION = 'http://sys-ids.kemkes.go.id/composition';
    const KEMKES_SYS_CLAIM_NUMBER   = 'http://sys-ids.kemkes.go.id/claim-number';
    const KEMKES_CS_COVERAGE_TYPE   = 'http://terminology.kemkes.go.id/CodeSystem/coverage-type';
    const KEMKES_CS_DOCUMENT_FORMAT = 'http://terminology.kemkes.go.id/CodeSystem/documentformat';
    const KEMKES_SYS_CLINICAL_IMP = 'http://sys-ids.kemkes.go.id/clinicalimpression';
    const KEMKES_SYS_CAREPLAN = 'http://sys-ids.kemkes.go.id/careplan';
    const KEMKES_SYS_HEALTHCARE_SVC = 'http://sys-ids.kemkes.go.id/healthcareservice';
    const KEMKES_SYS_BPJS_POLI = 'http://sys-ids.kemkes.go.id/bpjs-poli';
    const KEMKES_CS_SPECIALITY = 'http://terminology.kemkes.go.id/CodeSystem/clinical-speciality';
    const KEMKES_CS_PROGRAM = 'http://terminology.kemkes.go.id/CodeSystem/program';

    const KEMKES_CS_MEDICATION_TYPE = 'http://terminology.kemkes.go.id/CodeSystem/medication-type';
    const KEMKES_SD_MEDICATION_TYPE = 'https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType';
    const KEMKES_CS_IMMUNIZATION_REASON = 'http://terminology.kemkes.go.id/CodeSystem/immunization-reason';
    const KEMKES_CS_IMMUNIZATION_TIMING = 'http://terminology.kemkes.go.id/CodeSystem/immunization-routine-timing';
    const KEMKES_SD_ADM_CODE = 'https://fhir.kemkes.go.id/r4/StructureDefinition/administrativeCode/';

    // ─── HL7 FHIR Systems ─────────────────────────────────────────────────────
    const HL7 = 'http://terminology.hl7.org/CodeSystem';
    const HL7_ICD10 = 'http://hl7.org/fhir/sid/icd-10';
    const HL7_ICD9CM = 'http://hl7.org/fhir/sid/icd-9-cm';
    const HL7_DRUG_FORM = self::HL7 . '/v3-orderableDrugForm';
    const HL7_CS_OBS_CATEGORY = self::HL7 . '/observation-category';
    const HL7_CS_ORG_TYPE = self::HL7 . '/organization-type';
    const HL7_CS_LOC_PHYSICAL_TYPE = self::HL7 . '/location-physical-type';
    const HL7_CS_MED_STATEMENT_CAT = self::HL7 . '/medication-statement-category';
    const HL7_CS_ALLERGY_CLINICAL = self::HL7 . '/allergyintolerance-clinical';
    const HL7_CS_ALLERGY_VERIFY = self::HL7 . '/allergyintolerance-verification';
    const HL7_CS_EPISODE_TYPE = self::HL7 . '/episodeofcare-type';
    const HL7_CS_SERVICE_CATEGORY = self::HL7 . '/service-category';
    const HL7_CS_SERVICE_TYPE = self::HL7 . '/service-type';
    const HL7_CS_V2_0443 = self::HL7 . '/v2-0443';
    const HL7_CS_ACT_SITE = self::HL7 . '/v3-ActSite';
    const HL7_CS_ROUTE_ADMIN = self::HL7 . '/v3-RouteOfAdministration';
    const HL7_CS_V3_ACT_CODE = self::HL7 . '/v3-ActCode';
    const HL7_CS_V3_PARTICIPATION = self::HL7 . '/v3-ParticipationType';
    const HL7_CS_DIAGNOSIS_ROLE = self::HL7 . '/diagnosis-role';
    const HL7_CS_V2_0074 = self::HL7 . '/v2-0074';
    const HL7_CS_CONDITION_CLINICAL = self::HL7 . '/condition-clinical';
    const HL7_CS_CONDITION_CATEGORY = self::HL7 . '/condition-category';

    // ─── External Terminologies ───────────────────────────────────────────────
    const LOINC = 'http://loinc.org';
    const SNOMED = 'http://snomed.info/sct';
    const UCUM = 'http://unitsofmeasure.org';
    const DICOM_DCM = 'http://dicom.nema.org/resources/ontology/DCM';
    const RFC_3986 = 'urn:ietf:rfc:3986';

    // ─── Helper: KEMKES org-scoped identifier system ──────────────────────────
    public static function imagingSystem(string $orgId): string
    {
        return self::KEMKES_SYS_IMAGING . '/' . $orgId;
    }

    public static function observationSystem(string $orgId): string
    {
        return self::KEMKES_SYS_OBSERVATION . '/' . $orgId;
    }

    public static function specimenSystem(string $orgId): string
    {
        return self::KEMKES_SYS_SPECIMEN . '/' . $orgId;
    }

    public static function locationSystem(string $orgId): string
    {
        return self::KEMKES_SYS_LOCATION . '/' . $orgId;
    }

    public static function organizationSystem(string $orgId): string
    {
        return self::KEMKES_SYS_ORGANIZATION . '/' . $orgId;
    }

    public static function prescriptionSystem(string $orgId): string
    {
        return self::KEMKES_SYS_PRESCRIPTION . '/' . $orgId;
    }

    public static function claimNumberSystem(string $orgId): string
    {
        return self::KEMKES_SYS_CLAIM_NUMBER . '/' . $orgId;
    }

    public static function prescriptionItemSystem(string $orgId): string
    {
        return self::KEMKES_SYS_PRESCRIPTION_ITEM . '/' . $orgId;
    }

    public static function medicationSystem(string $orgId): string
    {
        return self::KEMKES_SYS_MEDICATION . '/' . $orgId;
    }

    public static function medicationStatementSystem(string $orgId): string
    {
        return self::KEMKES_SYS_MEDICATION_STATEMENT . '/' . $orgId;
    }

    public static function serviceRequestSystem(string $orgId): string
    {
        return self::KEMKES_SYS_SERVICEREQ . '/' . $orgId;
    }

    public static function acsnSystem(string $orgId): string
    {
        return self::KEMKES_SYS_ACSN . '/' . $orgId;
    }
}
