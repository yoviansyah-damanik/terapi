<?php

return [

    // ── Data SIMRS ───────────────────────────────────────────────────────
    ['key' => 'simrs',                  'label' => 'Data SIMRS',              'group' => 'Data',       'is_parent' => true,  'parent' => null],
    ['key' => 'simrs.patient',          'label' => 'Pasien',                  'group' => 'Data',       'is_parent' => false, 'parent' => 'simrs'],
    ['key' => 'simrs.polyclinic',       'label' => 'Poliklinik / Unit',       'group' => 'Data',       'is_parent' => false, 'parent' => 'simrs'],
    ['key' => 'simrs.employee',         'label' => 'Pegawai',                 'group' => 'Data',       'is_parent' => false, 'parent' => 'simrs'],
    ['key' => 'simrs.procedure',        'label' => 'Tindakan',                'group' => 'Data',       'is_parent' => false, 'parent' => 'simrs'],
    ['key' => 'simrs.icd9',             'label' => 'ICD-9',                   'group' => 'Data',       'is_parent' => false, 'parent' => 'simrs'],
    ['key' => 'simrs.icd10',            'label' => 'ICD-10',                  'group' => 'Data',       'is_parent' => false, 'parent' => 'simrs'],
    ['key' => 'simrs.room',             'label' => 'Kamar',                   'group' => 'Data',       'is_parent' => false, 'parent' => 'simrs'],
    ['key' => 'simrs.allergy',          'label' => 'Alergi',                  'group' => 'Data',       'is_parent' => false, 'parent' => 'simrs'],
    ['key' => 'simrs.department',       'label' => 'Departemen',              'group' => 'Data',       'is_parent' => false, 'parent' => 'simrs'],

    // ── Source Terminology ───────────────────────────────────────────────
    ['key' => 'terminology',                    'label' => 'Source Terminology',  'group' => 'Data', 'is_parent' => true,  'parent' => null],
    ['key' => 'terminology.smart_search',       'label' => 'Pencarian Pintar',    'group' => 'Data', 'is_parent' => false, 'parent' => 'terminology'],
    ['key' => 'terminology.fhir_dictionary',    'label' => 'Kamus FHIR',          'group' => 'Data', 'is_parent' => false, 'parent' => 'terminology'],
    ['key' => 'terminology.snomed',             'label' => 'SNOMED CT',           'group' => 'Data', 'is_parent' => false, 'parent' => 'terminology'],
    ['key' => 'terminology.loinc',              'label' => 'LOINC',               'group' => 'Data', 'is_parent' => false, 'parent' => 'terminology'],
    ['key' => 'terminology.kfa',                'label' => 'KFA',                 'group' => 'Data', 'is_parent' => false, 'parent' => 'terminology'],
    ['key' => 'terminology.icd_o_morphology',   'label' => 'ICD-O Morfologi',     'group' => 'Data', 'is_parent' => false, 'parent' => 'terminology'],
    ['key' => 'terminology.icd_o_topography',   'label' => 'ICD-O Topografi',     'group' => 'Data', 'is_parent' => false, 'parent' => 'terminology'],
    ['key' => 'terminology.icd9cm',             'label' => 'ICD-9CM',             'group' => 'Data', 'is_parent' => false, 'parent' => 'terminology'],
    ['key' => 'terminology.icd10',              'label' => 'ICD-10',              'group' => 'Data', 'is_parent' => false, 'parent' => 'terminology'],
    ['key' => 'terminology.icd_mm',             'label' => 'ICD-MM',              'group' => 'Data', 'is_parent' => false, 'parent' => 'terminology'],
    ['key' => 'terminology.icd_pm',             'label' => 'ICD-PM',              'group' => 'Data', 'is_parent' => false, 'parent' => 'terminology'],

    // ── Local Terminology ────────────────────────────────────────────────
    ['key' => 'local',                      'label' => 'Local Terminology',       'group' => 'Data', 'is_parent' => true,  'parent' => null],
    ['key' => 'local.organization',         'label' => 'Organization',            'group' => 'Data', 'is_parent' => false, 'parent' => 'local'],
    ['key' => 'local.patient',              'label' => 'Patient',                 'group' => 'Data', 'is_parent' => false, 'parent' => 'local'],
    ['key' => 'local.practitioner',         'label' => 'Practitioner',            'group' => 'Data', 'is_parent' => false, 'parent' => 'local'],
    ['key' => 'local.source',               'label' => 'Source ICD',              'group' => 'Data', 'is_parent' => false, 'parent' => 'local'],
    ['key' => 'local.clinical',             'label' => 'Clinical',                'group' => 'Data', 'is_parent' => false, 'parent' => 'local'],
    ['key' => 'local.observation',          'label' => 'Observation',             'group' => 'Data', 'is_parent' => false, 'parent' => 'local'],
    ['key' => 'local.medication',           'label' => 'Medication',              'group' => 'Data', 'is_parent' => false, 'parent' => 'local'],
    ['key' => 'local.device',               'label' => 'Device',                  'group' => 'Data', 'is_parent' => false, 'parent' => 'local'],
    ['key' => 'local.allergy',              'label' => 'Allergy Intolerance',     'group' => 'Data', 'is_parent' => false, 'parent' => 'local'],
    ['key' => 'local.healthcare_service',   'label' => 'Healthcare Service',      'group' => 'Data', 'is_parent' => false, 'parent' => 'local'],
    ['key' => 'local.episode_of_care',      'label' => 'Episode of Care',         'group' => 'Data', 'is_parent' => false, 'parent' => 'local'],

    // ── eRM ─────────────────────────────────────────────────────────────
    ['key' => 'erm',             'label' => 'eRM',         'group' => 'Integrasi', 'is_parent' => true,  'parent' => null],
    ['key' => 'erm.igd',         'label' => 'IGD',         'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'erm'],
    ['key' => 'erm.rawat_jalan', 'label' => 'Rawat Jalan', 'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'erm'],
    ['key' => 'erm.rawat_inap',  'label' => 'Rawat Inap',  'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'erm'],

    // ── Satu Sehat ───────────────────────────────────────────────────────
    ['key' => 'satusehat',               'label' => 'Satu Sehat',       'group' => 'Integrasi', 'is_parent' => true,  'parent' => null],
    ['key' => 'satusehat.summary',       'label' => 'Ringkasan',        'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'satusehat'],
    ['key' => 'satusehat.scheduler',     'label' => 'Penjadwalan',      'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'satusehat'],
    ['key' => 'satusehat.rule_number',   'label' => 'Kamus Rule Number','group' => 'Integrasi', 'is_parent' => false, 'parent' => 'satusehat'],
    ['key' => 'satusehat.kyc',           'label' => 'KYC',              'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'satusehat'],
    ['key' => 'satusehat.fhir_resource', 'label' => 'FHIR Resource',    'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'satusehat'],

    // ── BPJS Kesehatan ───────────────────────────────────────────────────
    ['key' => 'bpjs',               'label' => 'BPJS Kesehatan', 'group' => 'Integrasi', 'is_parent' => true,  'parent' => null],
    ['key' => 'bpjs.summary',       'label' => 'Ringkasan',      'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'bpjs'],
    ['key' => 'bpjs.erm',           'label' => 'eRM BPJS',       'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'bpjs'],
    ['key' => 'bpjs.antrean_online','label' => 'Antrean Online', 'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'bpjs'],
    ['key' => 'bpjs.vclaim',        'label' => 'vClaim',         'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'bpjs'],
    ['key' => 'bpjs.aplicare',      'label' => 'Aplicare',       'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'bpjs'],

    // ── RS Online ────────────────────────────────────────────────────────
    ['key' => 'rsonline',              'label' => 'RS Online',        'group' => 'Integrasi', 'is_parent' => true,  'parent' => null],
    ['key' => 'rsonline.referensi',    'label' => 'Master Referensi', 'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'rsonline'],
    ['key' => 'rsonline.pasien',       'label' => 'Data Pasien',      'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'rsonline'],
    ['key' => 'rsonline.fasyankes',    'label' => 'Data Fasyankes',   'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'rsonline'],

    // ── SIRS Online ──────────────────────────────────────────────────────
    ['key' => 'sirs',             'label' => 'SIRS Online',   'group' => 'Integrasi', 'is_parent' => true,  'parent' => null],
    ['key' => 'sirs.dashboard',   'label' => 'Dashboard',     'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'sirs'],
    ['key' => 'sirs.rl3_bulanan', 'label' => 'RL 3 Bulanan',  'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'sirs'],
    ['key' => 'sirs.rl3_tahunan', 'label' => 'RL 3 Tahunan',  'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'sirs'],
    ['key' => 'sirs.rl4',         'label' => 'RL 4',          'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'sirs'],
    ['key' => 'sirs.rl5',         'label' => 'RL 5',          'group' => 'Integrasi', 'is_parent' => false, 'parent' => 'sirs'],

    // ── TTE ──────────────────────────────────────────────────────────────
    ['key' => 'tte',              'label' => 'Tanda Tangan Elektronik', 'group' => 'Layanan', 'is_parent' => true,  'parent' => null],
    ['key' => 'tte.simulation',   'label' => 'Simulasi',                'group' => 'Layanan', 'is_parent' => false, 'parent' => 'tte'],
    ['key' => 'tte.verification', 'label' => 'Verifikasi',              'group' => 'Layanan', 'is_parent' => false, 'parent' => 'tte'],
    ['key' => 'tte.history',      'label' => 'Riwayat',                 'group' => 'Layanan', 'is_parent' => false, 'parent' => 'tte'],

    // ── DICOM / PACS ─────────────────────────────────────────────────────
    ['key' => 'dicom',          'label' => 'DICOM / PACS',     'group' => 'Layanan', 'is_parent' => true,  'parent' => null],
    ['key' => 'dicom.summary',  'label' => 'Ringkasan',        'group' => 'Layanan', 'is_parent' => false, 'parent' => 'dicom'],
    ['key' => 'dicom.worklist', 'label' => 'Worklist',         'group' => 'Layanan', 'is_parent' => false, 'parent' => 'dicom'],
    ['key' => 'dicom.modality', 'label' => 'Router & Modality','group' => 'Layanan', 'is_parent' => false, 'parent' => 'dicom'],
    ['key' => 'dicom.convert',  'label' => 'Convert Image',    'group' => 'Layanan', 'is_parent' => false, 'parent' => 'dicom'],

    // ── WhatsApp Gateway ─────────────────────────────────────────────────
    ['key' => 'whatsapp',           'label' => 'WhatsApp Gateway', 'group' => 'Layanan', 'is_parent' => true,  'parent' => null],
    ['key' => 'whatsapp.messages',  'label' => 'Pesan',            'group' => 'Layanan', 'is_parent' => false, 'parent' => 'whatsapp'],
    ['key' => 'whatsapp.broadcast', 'label' => 'Broadcast',        'group' => 'Layanan', 'is_parent' => false, 'parent' => 'whatsapp'],
    ['key' => 'whatsapp.contacts',  'label' => 'Kontak',           'group' => 'Layanan', 'is_parent' => false, 'parent' => 'whatsapp'],

    // ── API Portal ───────────────────────────────────────────────────────
    ['key' => 'api_portal',              'label' => 'API Portal',     'group' => 'Sistem', 'is_parent' => true,  'parent' => null],
    ['key' => 'api_portal.summary',      'label' => 'Ringkasan',      'group' => 'Sistem', 'is_parent' => false, 'parent' => 'api_portal'],
    ['key' => 'api_portal.management',   'label' => 'Manajemen API',  'group' => 'Sistem', 'is_parent' => false, 'parent' => 'api_portal'],
    ['key' => 'api_portal.api_version',  'label' => 'Versi Modul',    'group' => 'Sistem', 'is_parent' => false, 'parent' => 'api_portal'],
    ['key' => 'api_portal.logs',         'label' => 'Log API',        'group' => 'Sistem', 'is_parent' => false, 'parent' => 'api_portal'],
    ['key' => 'api_portal.documentation','label' => 'Dokumentasi API','group' => 'Sistem', 'is_parent' => false, 'parent' => 'api_portal'],
    ['key' => 'api_portal.integration',  'label' => 'Integrasi API',  'group' => 'Sistem', 'is_parent' => false, 'parent' => 'api_portal'],
    ['key' => 'api_portal.security',     'label' => 'Keamanan API',   'group' => 'Sistem', 'is_parent' => false, 'parent' => 'api_portal'],

    // ── Log ──────────────────────────────────────────────────────────────
    ['key' => 'logs',            'label' => 'Log',           'group' => 'Sistem', 'is_parent' => true,  'parent' => null],
    ['key' => 'logs.satusehat',  'label' => 'Satu Sehat',    'group' => 'Sistem', 'is_parent' => false, 'parent' => 'logs'],
    ['key' => 'logs.bpjs',       'label' => 'BPJS Kesehatan','group' => 'Sistem', 'is_parent' => false, 'parent' => 'logs'],
    ['key' => 'logs.whatsapp',   'label' => 'WA Gateway',    'group' => 'Sistem', 'is_parent' => false, 'parent' => 'logs'],
    ['key' => 'logs.simrs',      'label' => 'SIMRS',         'group' => 'Sistem', 'is_parent' => false, 'parent' => 'logs'],
    ['key' => 'logs.dicom',      'label' => 'DICOM',         'group' => 'Sistem', 'is_parent' => false, 'parent' => 'logs'],
    ['key' => 'logs.tte',        'label' => 'TTE',           'group' => 'Sistem', 'is_parent' => false, 'parent' => 'logs'],
    ['key' => 'logs.qrcode',     'label' => 'QR Code',       'group' => 'Sistem', 'is_parent' => false, 'parent' => 'logs'],
    ['key' => 'logs.ai',         'label' => 'AI Provider',   'group' => 'Sistem', 'is_parent' => false, 'parent' => 'logs'],

    // ── Utilitas Sistem ──────────────────────────────────────────────────
    ['key' => 'utility',                   'label' => 'Utilitas Sistem',  'group' => 'Sistem', 'is_parent' => true,  'parent' => null],
    ['key' => 'utility.qrcode',            'label' => 'QR Code Generator','group' => 'Sistem', 'is_parent' => false, 'parent' => 'utility'],
    ['key' => 'utility.connection_status', 'label' => 'Status Koneksi',   'group' => 'Sistem', 'is_parent' => false, 'parent' => 'utility'],
    ['key' => 'utility.simrs_version',     'label' => 'Versi SIMRS',      'group' => 'Sistem', 'is_parent' => false, 'parent' => 'utility'],
    ['key' => 'utility.database_backup',   'label' => 'Backup Database',  'group' => 'Sistem', 'is_parent' => false, 'parent' => 'utility'],

    // ── Pengaturan ───────────────────────────────────────────────────────
    ['key' => 'configuration',              'label' => 'Pengaturan',           'group' => 'Sistem', 'is_parent' => true,  'parent' => null],
    ['key' => 'configuration.hospital',     'label' => 'Informasi RS',         'group' => 'Sistem', 'is_parent' => false, 'parent' => 'configuration'],
    ['key' => 'configuration.connectivity', 'label' => 'Konektivitas',         'group' => 'Sistem', 'is_parent' => false, 'parent' => 'configuration'],
    ['key' => 'configuration.qrcode',       'label' => 'QRCode',               'group' => 'Sistem', 'is_parent' => false, 'parent' => 'configuration'],
    ['key' => 'configuration.users',        'label' => 'Manajemen Pengguna',   'group' => 'Sistem', 'is_parent' => false, 'parent' => 'configuration'],
    ['key' => 'configuration.jobs',         'label' => 'Job & Queue',          'group' => 'Sistem', 'is_parent' => false, 'parent' => 'configuration'],
    ['key' => 'configuration.security',     'label' => 'Keamanan Umum',        'group' => 'Sistem', 'is_parent' => false, 'parent' => 'configuration'],

];
