<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Mapping\Specialty;

class SpecialtySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $specialties = [
            ['snomed_code' => '17561000', 'specialty_name_en' => 'Cardiologist', 'description_id' => 'Dokter Spesialis Jantung dan Pembuluh Darah'],
            ['snomed_code' => '309383003', 'specialty_name_en' => 'Paediatric surgeon', 'description_id' => 'Dokter Spesialis Bedah Anak'],
            ['snomed_code' => '8724009', 'specialty_name_en' => 'Plastic surgeon', 'description_id' => 'Dokter Spesialis Bedah Plastik'],
            ['snomed_code' => '11661002', 'specialty_name_en' => 'Neuropathologist', 'description_id' => 'Spesialis Neuropatologi'],
            ['snomed_code' => '11911009', 'specialty_name_en' => 'Nephrologist', 'description_id' => 'Dokter Spesialis Ginjal/Nefrologi'],
            ['snomed_code' => '394916005', 'specialty_name_en' => 'Haematology (specialty)', 'description_id' => 'Dokter Spesialis Hematologi'],
            ['snomed_code' => '394915009', 'specialty_name_en' => 'General pathology (specialty)', 'description_id' => 'Dokter Spesialis Patologi Umum'],
            ['snomed_code' => '408439002', 'specialty_name_en' => 'Allergy - specialty', 'description_id' => 'Dokter Spesialis Alergi-Imunologi'],
            ['snomed_code' => '408440000', 'specialty_name_en' => 'Public health medicine', 'description_id' => 'Dokter Spesialis Kesehatan Masyarakat'],
            ['snomed_code' => '61345009', 'specialty_name_en' => 'Otorhinolaryngologist', 'description_id' => 'Dokter Spesialis THT (Telinga Hidung Tenggorok)'],
            ['snomed_code' => '61894003', 'specialty_name_en' => 'Endocrinologist', 'description_id' => 'Dokter Spesialis Endokrinologi'],
            ['snomed_code' => '309355002', 'specialty_name_en' => 'Dermatologist', 'description_id' => 'Dokter Spesialis Dermatologi'],
            ['snomed_code' => '394605005', 'specialty_name_en' => 'Gastroenterologist', 'description_id' => 'Dokter Spesialis Penyakit Dalam (Gastroenterologi)'],
            ['snomed_code' => '394802001', 'specialty_name_en' => 'General Medicine (specialty)', 'description_id' => 'Dokter Spesialis Penyakit Dalam/Umum'],
            ['snomed_code' => '409979002', 'specialty_name_en' => 'Medical Oncology (specialty)', 'description_id' => 'Dokter Spesialis Onkologi Medis'],
            ['snomed_code' => '394612000', 'specialty_name_en' => 'Obstetrics and Gynaecology', 'description_id' => 'Dokter Spesialis Obstetri dan Ginekologi (Obgyn)'],
            ['snomed_code' => '394595009', 'specialty_name_en' => 'Paediatrics (specialty)', 'description_id' => 'Dokter Spesialis Anak'],
            ['snomed_code' => '394610003', 'specialty_name_en' => 'Radiologist', 'description_id' => 'Dokter Spesialis Radiologi'],

            // Tambahan Umum
            ['snomed_code' => '394582007', 'specialty_name_en' => 'Neurology', 'description_id' => 'Dokter Spesialis Saraf'],
            ['snomed_code' => '394594003', 'specialty_name_en' => 'Ophthalmology', 'description_id' => 'Dokter Spesialis Mata'],
            ['snomed_code' => '394583002', 'specialty_name_en' => 'Psychiatry', 'description_id' => 'Dokter Spesialis Jiwa (Psikiatri)'],
            ['snomed_code' => '394609007', 'specialty_name_en' => 'General surgery', 'description_id' => 'Dokter Spesialis Bedah Umum'],
            ['snomed_code' => '394576009', 'specialty_name_en' => 'Accident and emergency medicine', 'description_id' => 'Dokter Instalasi Gawat Darurat (IGD)'],
            ['snomed_code' => '394604000', 'specialty_name_en' => 'Anaesthetics', 'description_id' => 'Dokter Spesialis Anestesiologi'],
            ['snomed_code' => '394584008', 'specialty_name_en' => 'Rehabilitation', 'description_id' => 'Dokter Spesialis Rehabilitasi Medik'],
        ];

        foreach ($specialties as $key => $specialty) {
            $specialties[$key]['snomed_term'] = $specialty['specialty_name_en']; // Map English name to term
            $specialties[$key]['system_display'] = 'http://snomed.info/sct';
        }

        foreach ($specialties as $specialty) {
            Specialty::updateOrCreate(
                ['snomed_code' => $specialty['snomed_code']],
                collect($specialty)->except('snomed_code')->toArray()
            );
        }
    }
}
