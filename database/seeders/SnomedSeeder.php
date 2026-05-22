<?php

namespace Database\Seeders;

use App\Models\Mapping\DoctorMap;
use App\Models\Mapping\Icd10Map;
use App\Models\Mapping\Icd9Map;
use App\Models\Mapping\LabItemMap;
use App\Models\Mapping\LabMap;
use App\Models\Mapping\MedicationMap;
use App\Models\Mapping\ProcedureMap;
use App\Models\Mapping\RadMap;
use Illuminate\Database\Seeder;

class SnomedSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLab();
        $this->seedLabItem();
        $this->seedRad();
        $this->seedProcedure();
        $this->seedMedication();
        $this->seedIcd10();
        $this->seedIcd9();
    }

    private function seedLab(): void
    {
        $data = [
            ['local_code' => 'A001', 'system_code' => '166868003', 'system_term' => 'Alkaline phosphatase measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'A002', 'system_code' => '166717003', 'system_term' => 'Aspartate aminotransferase measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'A003', 'system_code' => '166718008', 'system_term' => 'Alanine aminotransferase measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'A004', 'system_code' => '166874000', 'system_term' => 'Gamma glutamyl transferase measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB001', 'system_code' => '252275004', 'system_term' => 'Complete blood count panel', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB002', 'system_code' => '252306008', 'system_term' => 'Bleeding time measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB003', 'system_code' => '252307004', 'system_term' => 'Clotting time measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB004', 'system_code' => '112144000', 'system_term' => 'ABO and Rh blood group determination', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB005', 'system_code' => '363680008', 'system_term' => 'Mycobacterium tuberculosis detection in sputum', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB006', 'system_code' => '252373009', 'system_term' => 'Stool examination panel', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB007', 'system_code' => '165839004', 'system_term' => 'Malaria screening test', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB008', 'system_code' => '166847005', 'system_term' => 'Fasting blood glucose measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB009', 'system_code' => '166847005', 'system_term' => 'Random blood glucose measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB010', 'system_code' => '166847005', 'system_term' => 'Postprandial blood glucose measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB011', 'system_code' => '166855000', 'system_term' => 'Total bilirubin measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB012', 'system_code' => '166856004', 'system_term' => 'Direct bilirubin measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB013', 'system_code' => '166868003', 'system_term' => 'Alkaline phosphatase measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB014', 'system_code' => '166874000', 'system_term' => 'Gamma glutamyl transferase measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB015', 'system_code' => '166717003', 'system_term' => 'Aspartate aminotransferase measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB016', 'system_code' => '166718008', 'system_term' => 'Alanine aminotransferase measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB017', 'system_code' => '166866006', 'system_term' => 'Total protein measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB018', 'system_code' => '166869004', 'system_term' => 'Albumin measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB019', 'system_code' => '252365002', 'system_term' => 'Lipid profile panel', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB020', 'system_code' => '166847005', 'system_term' => 'Total cholesterol measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB021', 'system_code' => '166842000', 'system_term' => 'HDL cholesterol measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB022', 'system_code' => '166843005', 'system_term' => 'LDL cholesterol measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB023', 'system_code' => '166848000', 'system_term' => 'Triglyceride measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB024', 'system_code' => '166854001', 'system_term' => 'Urea measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB025', 'system_code' => '166716002', 'system_term' => 'Creatinine measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB026', 'system_code' => '166715006', 'system_term' => 'Uric acid measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB027', 'system_code' => '252374003', 'system_term' => 'Electrolyte panel', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB028', 'system_code' => '167050000', 'system_term' => 'HIV antibody test', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB029', 'system_code' => '365863005', 'system_term' => 'Hepatitis B surface antigen test', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB030', 'system_code' => '365845005', 'system_term' => 'Hepatitis C antibody test', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB031', 'system_code' => '252379008', 'system_term' => 'Urinalysis panel', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB032', 'system_code' => '167252002', 'system_term' => 'Pregnancy test', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB033', 'system_code' => '252438009', 'system_term' => 'Drug screening test', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB038', 'system_code' => '166847005', 'system_term' => 'Blood glucose measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB039', 'system_code' => '167050000', 'system_term' => 'HIV test', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB040', 'system_code' => '43396009', 'system_term' => 'Hemoglobin A1c measurement', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB041', 'system_code' => '441742003', 'system_term' => 'Dengue antibody panel', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'LAB042', 'system_code' => '252275004', 'system_term' => 'Complete blood count panel', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'PA0001', 'system_code' => '108257001', 'system_term' => 'Histopathology examination', 'system_display' => 'http://snomed.info/sct'],
        ];

        foreach ($data as $row) {
            LabMap::updateOrCreate(
                ['local_code' => $row['local_code']],
                ['system_code' => $row['system_code'], 'system_term' => $row['system_term'], 'system_display' => $row['system_display']],
            );
        }
    }

    private function seedLabItem(): void
    {
        $data = [
            // ===== HEMATOLOGI =====
            ['kd_jenis_prw' => 'A003', 'id_template' => '473', 'system_code' => '365722008', 'system_term' => 'Hemoglobin measurement', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A003', 'id_template' => '474', 'system_code' => '365637007', 'system_term' => 'Leukocyte count', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A003', 'id_template' => '475', 'system_code' => '252275004', 'system_term' => 'Differential leukocyte count', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A003', 'id_template' => '476', 'system_code' => '102247007', 'system_term' => 'Lymphocyte percentage', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A003', 'id_template' => '477', 'system_code' => '250614007', 'system_term' => 'MID cell percentage', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A003', 'id_template' => '481', 'system_code' => '250615008', 'system_term' => 'Granulocyte percentage', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A003', 'id_template' => '482', 'system_code' => '365658009', 'system_term' => 'Hematocrit measurement', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A003', 'id_template' => '483', 'system_code' => '365602006', 'system_term' => 'Platelet count', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A003', 'id_template' => '484', 'system_code' => '365616007', 'system_term' => 'Erythrocyte count', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A003', 'id_template' => '486', 'system_code' => '365629002', 'system_term' => 'Mean corpuscular volume', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A003', 'id_template' => '487', 'system_code' => '365630007', 'system_term' => 'Mean corpuscular hemoglobin', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A003', 'id_template' => '2194', 'system_code' => '365631006', 'system_term' => 'Mean corpuscular hemoglobin concentration', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A003', 'id_template' => '2195', 'system_code' => '365635004', 'system_term' => 'Erythrocyte sedimentation rate', 'system_display' => 'http://snomed.info/sct'],
            // ===== GOL DARAH =====
            ['kd_jenis_prw' => 'A003', 'id_template' => '2196', 'system_code' => '112144000', 'system_term' => 'ABO blood group', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A003', 'id_template' => '2197', 'system_code' => '165743006', 'system_term' => 'Rh blood group', 'system_display' => 'http://snomed.info/sct'],
            // ===== KIMIA DARAH =====
            ['kd_jenis_prw' => 'A001', 'id_template' => '2208', 'system_code' => '166717003', 'system_term' => 'Aspartate aminotransferase measurement', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A001', 'id_template' => '2209', 'system_code' => '166718008', 'system_term' => 'Alanine aminotransferase measurement', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A001', 'id_template' => '2210', 'system_code' => '166847005', 'system_term' => 'Cholesterol measurement', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A001', 'id_template' => '2211', 'system_code' => '166848000', 'system_term' => 'Triglyceride measurement', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A001', 'id_template' => '2212', 'system_code' => '166842000', 'system_term' => 'HDL cholesterol measurement', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A001', 'id_template' => '2213', 'system_code' => '166843005', 'system_term' => 'LDL cholesterol measurement', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A001', 'id_template' => '2214', 'system_code' => '166854001', 'system_term' => 'Urea measurement', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A001', 'id_template' => '2215', 'system_code' => '166854001', 'system_term' => 'Blood urea nitrogen measurement', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A001', 'id_template' => '2216', 'system_code' => '166716002', 'system_term' => 'Creatinine measurement', 'system_display' => 'http://snomed.info/sct'],
            ['kd_jenis_prw' => 'A001', 'id_template' => '2217', 'system_code' => '166715006', 'system_term' => 'Uric acid measurement', 'system_display' => 'http://snomed.info/sct'],
        ];

        foreach ($data as $row) {
            LabItemMap::updateOrCreate(
                ['kd_jenis_prw' => $row['kd_jenis_prw'], 'id_template' => $row['id_template']],
                ['system_code' => $row['system_code'], 'system_term' => $row['system_term'], 'system_display' => $row['system_display']],
            );
        }
    }

    private function seedRad(): void
    {
        $data = [
            ['local_code' => 'J000001', 'system_code' => '363680008', 'system_term' => 'Skull X-ray', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD001', 'system_code' => '168731009', 'system_term' => 'Chest X-ray', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD002', 'system_code' => '168731009', 'system_term' => 'Chest X-ray lateral view', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD003', 'system_code' => '168731009', 'system_term' => 'Chest X-ray lordotic view', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD004', 'system_code' => '168537006', 'system_term' => 'Thoracic spine X-ray', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD005', 'system_code' => '168544001', 'system_term' => 'Lumbosacral spine X-ray', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD006', 'system_code' => '168548003', 'system_term' => 'Abdominal X-ray', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD05', 'system_code' => '363680008', 'system_term' => 'Skull X-ray', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD06', 'system_code' => '241615005', 'system_term' => 'Ultrasound of abdomen', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD07', 'system_code' => '241615005', 'system_term' => 'Lower abdominal ultrasound', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD08', 'system_code' => '241615005', 'system_term' => 'Complete abdominal ultrasound', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD09', 'system_code' => '241616006', 'system_term' => 'Thyroid ultrasound', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD10', 'system_code' => '241617002', 'system_term' => 'Doppler ultrasound', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD11', 'system_code' => '241618007', 'system_term' => 'Thoracic ultrasound', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD12', 'system_code' => '241615005', 'system_term' => 'Ultrasound examination', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD13', 'system_code' => '241615005', 'system_term' => 'Ultrasound examination', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RAD14', 'system_code' => '241619004', 'system_term' => 'Computed tomography scan', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RADRJ004', 'system_code' => '168731009', 'system_term' => 'Chest X-ray', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RADRJ005', 'system_code' => '168731009', 'system_term' => 'Chest X-ray', 'system_display' => 'http://snomed.info/sct'],
            ['local_code' => 'RADRJ006', 'system_code' => '168731009', 'system_term' => 'Chest X-ray', 'system_display' => 'http://snomed.info/sct'],
        ];

        foreach ($data as $row) {
            RadMap::updateOrCreate(
                ['local_code' => $row['local_code']],
                ['system_code' => $row['system_code'], 'system_term' => $row['system_term'], 'system_display' => $row['system_display']],
            );
        }
    }

    private function seedProcedure(): void
    {
        $data = [
            ['procedure_code' => 'ADM00001', 'source_table' => 'jalan', 'system_code' => '308335008', 'system_term' => 'Administrative procedure', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00002', 'source_table' => 'jalan', 'system_code' => '386053000', 'system_term' => 'Blood transfusion', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00003', 'source_table' => 'jalan', 'system_code' => '185389009', 'system_term' => 'Outpatient procedure', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00004', 'source_table' => 'jalan', 'system_code' => '308335008', 'system_term' => 'Administrative procedure', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00005', 'source_table' => 'jalan', 'system_code' => '185349003', 'system_term' => 'Inpatient medical examination', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00006', 'source_table' => 'jalan', 'system_code' => '185349003', 'system_term' => 'Inpatient medical examination', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00009', 'source_table' => 'jalan', 'system_code' => '185349003', 'system_term' => 'Medical examination', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00010', 'source_table' => 'jalan', 'system_code' => '252465000', 'system_term' => 'Slit lamp examination', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00011', 'source_table' => 'jalan', 'system_code' => '252468003', 'system_term' => 'Ophthalmoscopy', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00012', 'source_table' => 'jalan', 'system_code' => '252473002', 'system_term' => 'Visual acuity test', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00013', 'source_table' => 'jalan', 'system_code' => '252473002', 'system_term' => 'Visual acuity test', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00014', 'source_table' => 'jalan', 'system_code' => '252465000', 'system_term' => 'Slit lamp examination', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00017', 'source_table' => 'jalan', 'system_code' => '252468003', 'system_term' => 'Ophthalmoscopy', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00018', 'source_table' => 'jalan', 'system_code' => '252468003', 'system_term' => 'Ophthalmoscopy', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00019', 'source_table' => 'jalan', 'system_code' => '252473002', 'system_term' => 'Visual acuity test', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00023', 'source_table' => 'jalan', 'system_code' => '104697006', 'system_term' => 'Electrocardiography', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00025', 'source_table' => 'jalan', 'system_code' => '104697006', 'system_term' => 'Electrocardiography', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ00027', 'source_table' => 'jalan', 'system_code' => '252472007', 'system_term' => 'Tonometry', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ002', 'source_table' => 'jalan', 'system_code' => '387713003', 'system_term' => 'Suturing of wound', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ003', 'source_table' => 'jalan', 'system_code' => '387713003', 'system_term' => 'Suturing of wound', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ004', 'source_table' => 'jalan', 'system_code' => '387713003', 'system_term' => 'Suturing of wound', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ005', 'source_table' => 'jalan', 'system_code' => '387713003', 'system_term' => 'Suturing of wound', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ006', 'source_table' => 'jalan', 'system_code' => '387714009', 'system_term' => 'Removal of sutures', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ007', 'source_table' => 'jalan', 'system_code' => '387440003', 'system_term' => 'Removal of foreign body', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ008', 'source_table' => 'jalan', 'system_code' => '232717009', 'system_term' => 'Nasal packing', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ009', 'source_table' => 'jalan', 'system_code' => '387247003', 'system_term' => 'Incision and drainage', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ010', 'source_table' => 'jalan', 'system_code' => '387247003', 'system_term' => 'Incision and drainage', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ011', 'source_table' => 'jalan', 'system_code' => '387247003', 'system_term' => 'Incision and drainage', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ012', 'source_table' => 'jalan', 'system_code' => '225358003', 'system_term' => 'Application of splint', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ013', 'source_table' => 'jalan', 'system_code' => '236886002', 'system_term' => 'Circumcision', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ014', 'source_table' => 'jalan', 'system_code' => '65801008', 'system_term' => 'Excision', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ015', 'source_table' => 'jalan', 'system_code' => '387712008', 'system_term' => 'Closed reduction of dislocation', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ016', 'source_table' => 'jalan', 'system_code' => '225360001', 'system_term' => 'Bandaging', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ017', 'source_table' => 'jalan', 'system_code' => '185317003', 'system_term' => 'Observation', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ018', 'source_table' => 'jalan', 'system_code' => '185317003', 'system_term' => 'Observation', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ019', 'source_table' => 'jalan', 'system_code' => '243147009', 'system_term' => 'Oxygen therapy', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ020', 'source_table' => 'jalan', 'system_code' => '410429000', 'system_term' => 'Electrical cardioversion', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ021', 'source_table' => 'jalan', 'system_code' => '252465002', 'system_term' => 'Pulse oximetry', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ022', 'source_table' => 'jalan', 'system_code' => '302789003', 'system_term' => 'Physiologic monitoring', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ023', 'source_table' => 'jalan', 'system_code' => '264957007', 'system_term' => 'Suction', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ029', 'source_table' => 'jalan', 'system_code' => '428191000124101', 'system_term' => 'Insertion of intravenous catheter', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ030', 'source_table' => 'jalan', 'system_code' => '428191000124101', 'system_term' => 'Insertion of intravenous catheter', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ031', 'source_table' => 'jalan', 'system_code' => '129304002', 'system_term' => 'Injection', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ032', 'source_table' => 'jalan', 'system_code' => '428311008', 'system_term' => 'Insertion of nasogastric tube', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ033', 'source_table' => 'jalan', 'system_code' => '241616006', 'system_term' => 'Urinary catheterization', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ034', 'source_table' => 'jalan', 'system_code' => '241617002', 'system_term' => 'Removal of urinary catheter', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ035', 'source_table' => 'jalan', 'system_code' => '265764009', 'system_term' => 'Gastric lavage', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ036', 'source_table' => 'jalan', 'system_code' => '225358003', 'system_term' => 'Enema administration', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ037', 'source_table' => 'jalan', 'system_code' => '409622000', 'system_term' => 'Nebulizer therapy', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ038', 'source_table' => 'jalan', 'system_code' => '104697006', 'system_term' => 'Electrocardiography', 'system_display' => 'http://snomed.info/sct'],
            ['procedure_code' => 'RJ039', 'source_table' => 'jalan', 'system_code' => '232717009', 'system_term' => 'Cardiopulmonary resuscitation', 'system_display' => 'http://snomed.info/sct'],
        ];

        foreach ($data as $row) {
            ProcedureMap::updateOrCreate(
                ['source_table' => $row['source_table'], 'procedure_code' => $row['procedure_code']],
                ['system_code' => $row['system_code'], 'system_term' => $row['system_term'], 'system_display' => $row['system_display']],
            );
        }
    }

    private function seedMedication(): void
    {
        $data = [
            ['local_code' => 'A0001', 'kfa_code' => '93006499', 'kfa_name' => 'Aciclovir 400 mg Tablet (KIMIA FARMA)', 'system_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/kfa-v2'],
            ['local_code' => 'A0011', 'kfa_code' => '93003435', 'kfa_name' => 'Allopurinol 300 mg Tablet (TRIMAN)', 'system_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/kfa-v2'],
            ['local_code' => 'A0013', 'kfa_code' => '93004335', 'kfa_name' => 'Alprazolam 0,5 mg Tablet (DEXA MEDICA)', 'system_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/kfa-v2'],
            ['local_code' => 'A0014', 'kfa_code' => '93001225', 'kfa_name' => 'Alprazolam 1 mg Tablet (MERSIFARMA TIRMAKU MERCUSANA)', 'system_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/kfa-v2'],
            ['local_code' => 'A0097', 'kfa_code' => '93007699', 'kfa_name' => 'Ceftriaxone Sodium 1 g Serbuk Injeksi (INTERBAT)', 'system_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/kfa-v2'],
        ];

        foreach ($data as $row) {
            MedicationMap::updateOrCreate(
                ['local_code' => $row['local_code']],
                ['kfa_code' => $row['kfa_code'], 'kfa_name' => $row['kfa_name'], 'system_url' => $row['system_url']],
            );
        }
    }

    private function seedIcd10(): void
    {
        $data = [
            ['icd10_code' => 'A01', 'system_code' => '386661006', 'system_term' => 'Fever', 'system_display' => 'http://snomed.info/sct'],
            ['icd10_code' => 'A01.0', 'system_code' => '4834000', 'system_term' => 'Typhoid fever', 'system_display' => 'http://snomed.info/sct'],
        ];

        foreach ($data as $row) {
            Icd10Map::updateOrCreate(
                ['icd10_code' => $row['icd10_code']],
                ['system_code' => $row['system_code'], 'system_term' => $row['system_term'], 'system_display' => $row['system_display']],
            );
        }
    }

    private function seedIcd9(): void
    {
        $data = [
            ['icd9_code' => '03.92', 'system_code' => '91520000', 'system_term' => 'Injection of spinal steroid', 'system_display' => 'http://snomed.info/sct'],
        ];

        foreach ($data as $row) {
            Icd9Map::updateOrCreate(
                ['icd9_code' => $row['icd9_code']],
                ['system_code' => $row['system_code'], 'system_term' => $row['system_term'], 'system_display' => $row['system_display']],
            );
        }
    }
}
