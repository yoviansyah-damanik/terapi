<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Informasi Rumah Sakit
    |--------------------------------------------------------------------------
    | Konfigurasi identitas dan kontak rumah sakit yang digunakan di berbagai
    | modul (eRM BPJS, Satu Sehat, header laporan, dll.)
    */

    'name'        => env('HOSPITAL_NAME', 'Rumah Sakit'),
    'alias'       => env('HOSPITAL_ALIAS', 'RS'),
    'phone'       => env('HOSPITAL_PHONE', ''),
    'email'       => env('HOSPITAL_EMAIL', ''),
    'website'     => env('HOSPITAL_WEBSITE', ''),
    'address'     => env('HOSPITAL_ADDRESS', ''),
    'city'        => env('HOSPITAL_CITY', ''),
    'province'    => env('HOSPITAL_PROVINCE', ''),
    'postal_code' => env('HOSPITAL_POSTAL_CODE', ''),
    'country'     => env('HOSPITAL_COUNTRY', 'ID'),

    /*
    |--------------------------------------------------------------------------
    | Kode Administratif (untuk Satu Sehat)
    |--------------------------------------------------------------------------
    | Kode wilayah sesuai standar Kemendagri yang digunakan pada FHIR resource.
    */

    'propinsi'  => env('HOSPITAL_PROVINCE_CODE', ''),
    'kabupaten' => env('HOSPITAL_CITY_CODE', ''),
    'kecamatan' => env('HOSPITAL_DISTRICT_CODE', ''),
    'kelurahan' => env('HOSPITAL_VILLAGE_CODE', ''),

];
