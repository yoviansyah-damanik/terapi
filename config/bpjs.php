<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Modul BPJS Kesehatan
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk integrasi dengan API BPJS Kesehatan.
    | Setiap modul memiliki base_url, cons_id, secret_key, dan user_key sendiri.
    |
    */

    'vclaim' => [
        'name' => 'VClaim',
        'description' => 'Verifikasi klaim dan data peserta BPJS',
        'base_url' => env('VCLAIM_BPJS_URL'),
        'cons_id' => env('CONS_ID_VCLAIM_BPJS'),
        'secret_key' => env('SECRET_KEY_VCLAIM_BPJS'),
        'user_key' => env('USER_KEY_VCLAIM_BPJS'),
        'test_endpoints' => [
            ['method' => 'GET', 'path' => '/Peserta/nokartu/{noKartu}/tglSEP/{tglSep}', 'test_path' => '/Peserta/nokartu/0/tglSEP/2024-01-01', 'label' => 'Get Peserta'],
            ['method' => 'GET', 'path' => '/referensi/poli/{nama}', 'test_path' => '/referensi/poli/INT', 'label' => 'Referensi Poli'],
            ['method' => 'GET', 'path' => '/RencanaKontrol/nosep/{noSep}', 'test_path' => '/RencanaKontrol/nosep/0', 'label' => 'Cari SEP'],
            ['method' => 'POST', 'path' => '/SEP/2.0/insert', 'test_path' => '/SEP/2.0/insert', 'label' => 'Insert SEP'],
            ['method' => 'POST', 'path' => '/RencanaKontrol/v2/Insert', 'test_path' => '/RencanaKontrol/v2/Insert', 'label' => 'Insert Rencana Kontrol'],
            ['method' => 'POST', 'path' => '/RencanaKontrol/InsertSPRI', 'test_path' => '/RencanaKontrol/InsertSPRI', 'label' => 'Insert SPRI'],
            ['method' => 'POST', 'path' => '/Rujukan/insert', 'test_path' => '/Rujukan/insert', 'label' => 'Insert Rujukan'],
            ['method' => 'GET', 'path' => '/Rujukan/{noRujukan}', 'test_path' => '/Rujukan/0', 'label' => 'Get Rujukan'],
        ]
    ],

    'antrian_online' => [
        'name' => 'Antrian Online',
        'description' => 'Manajemen antrian online rumah sakit',
        'base_url' => env('ANTRIAN_ONLINE_BPJS_URL'),
        'cons_id' => env('CONS_ID_ANTRIAN_ONLINE_BPJS'),
        'secret_key' => env('SECRET_KEY_ANTRIAN_ONLINE_BPJS'),
        'user_key' => env('USER_KEY_ANTRIAN_ONLINE_BPJS'),
        'test_endpoints' => [
            ['method' => 'GET', 'path' => '/ref/poli', 'test_path' => '/ref/poli', 'label' => 'Referensi Poli'],
            ['method' => 'POST', 'path' => '/antrean/add', 'test_path' => '/antrean/add', 'label' => 'Tambah Antrian'],
            ['method' => 'POST', 'path' => '/antrean/updatewaktu', 'test_path' => '/antrean/updatewaktu', 'label' => 'Update Waktu Antrian'],
            ['method' => 'POST', 'path' => '/antrean/farmasi/add', 'test_path' => '/antrean/farmasi/add', 'label' => 'Tambah Antrian Farmasi']
        ]
    ],

    'apotek_online' => [
        'name' => 'Apotek Online',
        'description' => 'Integrasi resep dan obat apotek',
        'base_url' => env('APOTEK_ONLINE_BPJS_URL'),
        'cons_id' => env('CONS_ID_APOTEK_ONLINE_BPJS'),
        'secret_key' => env('SECRET_KEY_APOTEK_ONLINE_BPJS'),
        'user_key' => env('USER_KEY_APOTEK_ONLINE_BPJS'),
        'test_endpoints' => [
            ['method' => 'GET', 'path' => '/referensi/dpho', 'test_path' => '/referensi/dpho', 'label' => 'Referensi DPHO'],
            ['method' => 'GET', 'path' => '/referensi/obat/{kd}/{awal}/{akhir}', 'test_path' => '/referensi/obat/1/0/10', 'label' => 'Referensi Obat'],
            ['method' => 'POST', 'path' => '/obatnonracikan/v3/insert', 'test_path' => '/obatnonracikan/v3/insert', 'label' => 'Simpan Obat Non Racikan'],
            ['method' => 'POST', 'path' => '/obatracikan/v3/insert', 'test_path' => '/obatracikan/v3/insert', 'label' => 'Simpan Obat Racikan'],
            ['method' => 'GET', 'path' => '/obat/daftar/{bulan}', 'test_path' => '/obat/daftar/012024', 'label' => 'Daftar Pelayanan Obat'],
            ['method' => 'POST', 'path' => '/daftarresep', 'test_path' => '/daftarresep', 'label' => 'Daftar Resep'],
            ['method' => 'POST', 'path' => '/sjpresep/v3/insert', 'test_path' => '/sjpresep/v3/insert', 'label' => 'Simpan Resep'],
            ['method' => 'DELETE', 'path' => '/hapusresep', 'test_path' => '/hapusresep', 'label' => 'Hapus Resep'],

        ]
    ],

    'icare' => [
        'name' => 'ICare',
        'description' => 'Integrasi ICare BPJS Kesehatan',
        'base_url' => env('ICARE_BPJS_URL'),
        'cons_id' => env('CONS_ID_ICARE_BPJS'),
        'secret_key' => env('SECRET_KEY_ICARE_BPJS'),
        'user_key' => env('USER_KEY_ICARE_BPJS'),
        'test_endpoints' => [
            ['method' => 'POST', 'path' => '/api/rs/validate', 'test_path' => '/api/rs/validate', 'label' => 'Validasi ICare RS']
        ]
    ],

    'erm' => [
        'name' => 'eRM',
        'description' => 'Rekam medis elektronik BPJS',
        'base_url' => env('ERM_BPJS_URL'),
        'cons_id' => env('CONS_ID_ERM_BPJS'),
        'secret_key' => env('SECRET_KEY_ERM_BPJS'),
        'user_key' => env('USER_KEY_ERM_BPJS'),
        'test_endpoints' => [
            ['method' => 'POST', 'path' => '/eclaim/rekammedis/insert', 'test_path' => '/eclaim/rekammedis/insert', 'label' => 'Insert eRM']
        ]
    ],

    'antrian_rs' => [
        'name' => 'Antrian RS',
        'description' => 'Antrian JKN Mobile dari sisi RS',
        'base_url' => env('JKN_RS_URL'),
        'username' => env('JKN_RS_USERNAME'),
        'password' => env('JKN_RS_PASSWORD'),
        'test_endpoints' => [
            ['method' => 'GET', 'path' => '/auth', 'test_path' => '/auth', 'label' => 'Get Token'],
            ['method' => 'POST', 'path' => '/ambilantrean', 'test_path' => '/ambilantrean', 'label' => 'Ambil Antrian'],
            ['method' => 'POST', 'path' => '/checkinantrean', 'test_path' => '/checkinantrean', 'label' => 'Check In'],
            ['method' => 'POST', 'path' => '/jadwaloperasirs', 'test_path' => '/jadwaloperasirs', 'label' => 'Jadwal Operasi']
        ]
    ],

    'aplicare' => [
        'name' => 'Aplicare',
        'description' => 'Ketersediaan tempat tidur real-time BPJS',
        'base_url' => env('APLICARE_BPJS_URL'),
        'cons_id' => env('CONS_ID_APLICARE_BPJS'),
        'secret_key' => env('SECRET_KEY_APLICARE_BPJS'),
        'user_key' => env('USER_KEY_APLICARE_BPJS'),
        'kode_kelas_options' => array_map('trim', explode(',', env(
            'APLICARE_KODE_KELAS',
            'NON,Kelas 1,Kelas 2,Kelas 3,Kelas Utama,Kelas VIP,Kelas VVIP,-,VVIP,VIP,Kelas I,Kelas II,Kelas III,ICU,ICCU,NICU,PICU,IGD,UGD,RUANG BERSALIN,HCU,RUANG ISOLASI'
        ))),
    ],

    'kode_ppk' => env('KODE_PPK_RS_BPJS'),
    'nama_ppk' => env('NAMA_PPK_RS_BPJS'),
    'kode_ppk_apotek' => env('KODE_PPK_APOTEK_BPJS'),
    'nama_ppk_apotek' => env('NAMA_PPK_APOTEK_BPJS'),
    'default_codes' => array_filter(array_map('trim', explode(',', env('BPJS_DEFAULT_CODE', ''))))
];
