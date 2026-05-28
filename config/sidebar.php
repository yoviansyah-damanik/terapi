<?php

return [
    [
        'title' => 'Dashboard',
        'route' => 'home',
        'icon' => 'home',
        'active_match' => 'home',
    ],
    [
        'title' => 'Data SIMRS',
        'icon' => 'circle-stack',
        'expanded' => 'simrs/*',
        'permission' => 'simrs',
        'children' => [
            ['title' => 'Pasien',          'route' => 'simrs.patient',    'icon' => 'user-group',             'permission' => 'simrs.patient'],
            ['title' => 'Poliklinik / Unit','route' => 'simrs.polyclinic', 'icon' => 'building-office-2',     'permission' => 'simrs.polyclinic'],
            ['title' => 'Pegawai',          'route' => 'simrs.employee',   'icon' => 'users',                  'permission' => 'simrs.employee'],
            ['title' => 'Tindakan',         'route' => 'simrs.procedure',  'icon' => 'clipboard-document-list','permission' => 'simrs.procedure'],
            ['title' => 'ICD-9',            'route' => 'simrs.icd9',       'icon' => 'document-text',          'permission' => 'simrs.icd9'],
            ['title' => 'ICD-10',           'route' => 'simrs.icd10',      'icon' => 'document-text',          'permission' => 'simrs.icd10'],
            ['title' => 'Kamar',            'route' => 'simrs.room',       'icon' => 'home-modern',            'permission' => 'simrs.room'],
            ['title' => 'Alergi',           'route' => 'simrs.allergy',    'icon' => 'shield-exclamation',     'permission' => 'simrs.allergy'],
            ['title' => 'Departemen',       'route' => 'simrs.department', 'icon' => 'building-office',        'permission' => 'simrs.department'],
        ]
    ],
    [
        'title' => 'Source Terminology',
        'icon' => 'globe-alt',
        'expanded' => 'terminology.*',
        'permission' => 'terminology',
        'children' => [
            ['title' => 'Pencarian Pintar', 'route' => 'terminology.smart-search',     'icon' => 'sparkles',    'permission' => 'terminology.smart_search'],
            ['title' => 'Kamus FHIR',       'route' => 'terminology.fhir-dictionary',  'icon' => 'book-open',   'permission' => 'terminology.fhir_dictionary'],
            ['title' => 'SNOMED CT',        'route' => 'terminology.snomed',           'icon' => 'cog-6-tooth', 'permission' => 'terminology.snomed'],
            ['title' => 'LOINC',            'route' => 'terminology.loinc',            'icon' => 'beaker',      'permission' => 'terminology.loinc'],
            ['title' => 'KFA',              'route' => 'terminology.kfa',              'icon' => 'archive-box', 'permission' => 'terminology.kfa'],
            ['title' => 'ICD-O Morphology', 'route' => 'terminology.icd-o-morphology', 'icon' => 'hashtag',    'permission' => 'terminology.icd_o_morphology'],
            ['title' => 'ICD-O Topography', 'route' => 'terminology.icd-o-topography', 'icon' => 'hashtag',    'permission' => 'terminology.icd_o_topography'],
            ['title' => 'ICD-9CM',          'route' => 'terminology.icd9cm',           'icon' => 'hashtag',    'permission' => 'terminology.icd9cm'],
            ['title' => 'ICD-10',           'route' => 'terminology.icd10',            'icon' => 'hashtag',    'permission' => 'terminology.icd10'],
            ['title' => 'ICD-MM',           'route' => 'terminology.icd-mm',           'icon' => 'hashtag',    'permission' => 'terminology.icd_mm'],
            ['title' => 'ICD-PM',           'route' => 'terminology.icd-pm',           'icon' => 'hashtag',    'permission' => 'terminology.icd_pm'],
        ]
    ],
    [
        'title' => 'Local Terminology',
        'icon' => 'rectangle-stack',
        'expanded' => 'local.*',
        'permission' => 'local',
        'children' => [
            ['title' => 'Ringkasan',    'route' => 'local.summary',      'icon' => 'chart-pie',          'permission' => 'local'],
            ['title' => 'Organization', 'route' => 'local.organization', 'icon' => 'building-library',   'permission' => 'local.organization',
                'active_match' => ['local.organization', 'bpjs.fhir-resource.organization', 'satusehat.fhir-resource.organizations']],
            ['title' => 'Patient',      'route' => 'local.patient',      'icon' => 'users',              'permission' => 'local.patient',
                'active_match' => ['local.patient', 'bpjs.fhir-resource.patient', 'satusehat.fhir-resource.patients']],
            ['title' => 'Practitioner', 'route' => 'local.practitioner', 'icon' => 'user-group',         'permission' => 'local.practitioner'],
            [
                'title' => 'Source',
                'icon' => 'document-text',
                'expanded' => 'local.source*',
                'permission' => 'local.source',
                'children' => [
                    ['title' => 'ICD-10',          'route' => 'local.source.icd10',            'icon' => 'hashtag', 'permission' => 'local.source'],
                    ['title' => 'ICD-9-CM',         'route' => 'local.source.icd9',            'icon' => 'hashtag', 'permission' => 'local.source'],
                    ['title' => 'ICD-O Topografi',  'route' => 'local.source.icd-o-topography','icon' => 'hashtag', 'permission' => 'local.source'],
                    ['title' => 'ICD-O Morfologi',  'route' => 'local.source.icd-o-morphology','icon' => 'hashtag', 'permission' => 'local.source'],
                    ['title' => 'ICD-PM',           'route' => 'local.source.icd-pm',          'icon' => 'hashtag', 'permission' => 'local.source'],
                    ['title' => 'ICD-MM',           'route' => 'local.source.icd-mm',          'icon' => 'hashtag', 'permission' => 'local.source'],
                ]
            ],
            [
                'title' => 'Clinical',
                'icon' => 'clipboard-document-check',
                'expanded' => 'local.clinical*',
                'permission' => 'local.clinical',
                'children' => [
                    ['title' => 'Tindakan', 'route' => 'local.clinical.procedure', 'icon' => 'scissors', 'permission' => 'local.clinical',
                        'active_match' => ['local.clinical.procedure', 'bpjs.fhir-resource.procedure']],
                    ['title' => 'Operasi',  'route' => 'local.clinical.surgery',   'icon' => 'scissors', 'permission' => 'local.clinical'],
                ]
            ],
            [
                'title' => 'Observation',
                'icon' => 'eye',
                'expanded' => 'local.observation*',
                'permission' => 'local.observation',
                'children' => [
                    ['title' => 'Laboratorium', 'route' => 'local.observation.laboratory', 'icon' => 'beaker', 'permission' => 'local.observation',
                        'active_match' => ['local.observation.laboratory', 'bpjs.fhir-resource.observation.lab']],
                    ['title' => 'Radiologi',    'route' => 'local.observation.radiology',  'icon' => 'camera', 'permission' => 'local.observation',
                        'active_match' => ['local.observation.radiology', 'bpjs.fhir-resource.observation.radiology']],
                ]
            ],
            [
                'title' => 'Medication',
                'icon' => 'archive-box',
                'expanded' => 'local.medication*',
                'permission' => 'local.medication',
                'children' => [
                    ['title' => 'Obat',  'route' => 'local.medication.medicine', 'icon' => 'cube',        'permission' => 'local.medication',
                        'active_match' => ['local.medication.medicine', 'bpjs.fhir-resource.medication', 'satusehat.fhir-resource.medication']],
                    ['title' => 'Vaksin','route' => 'local.medication.vaccine',  'icon' => 'shield-check', 'permission' => 'local.medication',
                        'active_match' => ['local.medication.vaccine', 'bpjs.fhir-resource.medication.vaccine']],
                ]
            ],
            [
                'title' => 'Device',
                'icon' => 'cpu-chip',
                'expanded' => 'local.device*',
                'permission' => 'local.device',
                'children' => [
                    ['title' => 'Alat Kesehatan', 'route' => 'local.device.equipment', 'icon' => 'wrench', 'permission' => 'local.device',
                        'active_match' => ['local.device.equipment', 'bpjs.fhir-resource.device.equipment']],
                ]
            ],
            [
                'title' => 'Allergy Intolerance',
                'icon' => 'shield-exclamation',
                'expanded' => 'local.allergy*',
                'permission' => 'local.allergy',
                'children' => [
                    ['title' => 'Alergi',        'route' => 'local.allergy.allergy',  'icon' => 'exclamation-circle', 'permission' => 'local.allergy',
                        'active_match' => ['local.allergy.allergy', 'bpjs.fhir-resource.allergy.allergy']],
                    ['title' => 'Reaksi Alergi', 'route' => 'local.allergy.reaction', 'icon' => 'fire',               'permission' => 'local.allergy',
                        'active_match' => ['local.allergy.reaction', 'bpjs.fhir-resource.allergy.reaction']],
                ]
            ],
            [
                'title' => 'Healthcare Service',
                'icon' => 'building-office-2',
                'expanded' => 'local.healthcare-service*',
                'permission' => 'local.healthcare_service',
                'children' => [
                    ['title' => 'Poliklinik', 'route' => 'local.healthcare-service.polyclinic', 'icon' => 'building-storefront', 'permission' => 'local.healthcare_service',
                        'active_match' => ['local.healthcare-service.polyclinic', 'bpjs.fhir-resource.healthcare-service', 'satusehat.fhir-resource.healthcare-services']],
                    ['title' => 'Bangsal',    'route' => 'local.healthcare-service.ward',       'icon' => 'home-modern',         'permission' => 'local.healthcare_service',
                        'active_match' => ['local.healthcare-service.ward', 'satusehat.fhir-resource.locations']],
                ]
            ],
            [
                'title' => 'Episode of Care',
                'route' => 'local.episode-of-care.index',
                'icon' => 'clipboard-document-check',
                'active_match' => 'local.episode-of-care.*',
                'permission' => 'local.episode_of_care',
            ],
        ]
    ],
    [
        'title' => 'eRM',
        'icon' => 'document-text',
        'expanded' => 'erm/*',
        'permission' => 'erm',
        'children' => [
            ['title' => 'IGD',         'route' => 'erm.igd',        'icon' => 'exclamation-triangle', 'permission' => 'erm.igd'],
            ['title' => 'Rawat Jalan', 'route' => 'erm.rawat-jalan','icon' => 'user',                 'permission' => 'erm.rawat_jalan'],
            ['title' => 'Rawat Inap',  'route' => 'erm.rawat-inap', 'icon' => 'building-office',      'permission' => 'erm.rawat_inap'],
        ]
    ],
    [
        'title' => 'Satu Sehat',
        'icon' => 'heart',
        'expanded' => 'satusehat/*',
        'permission' => 'satusehat',
        'children' => [
            ['title' => 'Ringkasan',        'route' => 'satusehat.summary',     'icon' => 'chart-bar-square', 'permission' => 'satusehat.summary'],
            ['title' => 'Penjadwalan',      'route' => 'satusehat.scheduler',   'icon' => 'calendar-days',    'permission' => 'satusehat.scheduler'],
            ['title' => 'Kamus Rule Number','route' => 'satusehat.rule-number', 'icon' => 'book-open',        'permission' => 'satusehat.rule_number'],
            [
                'title' => 'KYC',
                'icon' => 'identification',
                'expanded' => ['satusehat/kyc/*', 'satusehat/kyc*'],
                'permission' => 'satusehat.kyc',
                'children' => [
                    ['title' => 'Verifikasi Pasien', 'route' => 'satusehat.kyc.generate-url', 'icon' => 'shield-check',           'permission' => 'satusehat.kyc'],
                    ['title' => 'Riwayat KYC',       'route' => 'satusehat.kyc.logs',         'icon' => 'clipboard-document-list','permission' => 'satusehat.kyc'],
                ]
            ],
            [
                'title' => 'FHIR Resource',
                'icon' => 'building-office-2',
                'expanded' => 'satusehat.fhir-resource*',
                'permission' => 'satusehat.fhir_resource',
                'children' => [
                    ['title' => 'Episode of Care',   'route' => 'satusehat.fhir-resource.episode-of-care',  'icon' => 'document-magnifying-glass', 'permission' => 'satusehat.fhir_resource'],
                    ['title' => 'Healthcare Services','route' => 'satusehat.fhir-resource.healthcare-services','icon' => 'building-office-2',       'permission' => 'satusehat.fhir_resource'],
                    ['title' => 'Locations',         'route' => 'satusehat.fhir-resource.locations',        'icon' => 'building-storefront',       'permission' => 'satusehat.fhir_resource'],
                    ['title' => 'Medication',        'route' => 'satusehat.fhir-resource.medication',       'icon' => 'eye-dropper',               'permission' => 'satusehat.fhir_resource'],
                    ['title' => 'Organizations',     'route' => 'satusehat.fhir-resource.organizations',    'icon' => 'building-office',           'permission' => 'satusehat.fhir_resource'],
                    ['title' => 'Patients',          'route' => 'satusehat.fhir-resource.patients',         'icon' => 'users',                     'permission' => 'satusehat.fhir_resource'],
                    ['title' => 'Practitioners',     'route' => 'satusehat.fhir-resource.practitioners',    'icon' => 'users',                     'permission' => 'satusehat.fhir_resource'],
                ]
            ]
        ],
    ],
    [
        'title' => 'BPJS Kesehatan',
        'icon' => 'heart',
        'expanded' => 'bpjs/*',
        'permission' => 'bpjs',
        'children' => [
            ['title' => 'Ringkasan',      'route' => 'bpjs.summary',       'icon' => 'chart-bar-square', 'permission' => 'bpjs.summary'],
            ['title' => 'vClaim',         'route' => 'bpjs.vclaim',        'icon' => 'document-text',    'permission' => 'bpjs.vclaim'],
            ['title' => 'Antrean Online', 'route' => 'bpjs.antrean-online','icon' => 'queue-list',       'permission' => 'bpjs.antrean_online'],
            ['title' => 'Aplicare',       'route' => 'bpjs.aplicare',      'icon' => 'home-modern',      'permission' => 'bpjs.aplicare'],
            ['title' => 'eRM',            'route' => 'bpjs.erm',           'icon' => 'document-check',   'permission' => 'bpjs.erm',
                'active_match' => 'bpjs.erm*'],
        ]
    ],
    [
        'title' => 'RS Online',
        'icon' => 'heart',
        'expanded' => 'rsonline/*',
        'permission' => 'rsonline',
        'children' => [
            ['title' => 'Master Referensi', 'route' => 'rsonline.referensi', 'icon' => 'book-open',        'permission' => 'rsonline.referensi'],
            ['title' => 'Data Pasien',      'route' => 'rsonline.pasien',    'icon' => 'users',             'permission' => 'rsonline.pasien'],
            ['title' => 'Data Fasyankes',   'route' => 'rsonline.fasyankes', 'icon' => 'building-office-2', 'permission' => 'rsonline.fasyankes'],
        ]
    ],
    [
        'title' => 'SIRS Online',
        'icon' => 'heart',
        'expanded' => 'sirs*',
        'permission' => 'sirs',
        'children' => [
            ['title' => 'Dashboard', 'route' => 'sirs.index', 'icon' => 'chart-bar-square', 'permission' => 'sirs.dashboard'],
            [
                'title' => 'RL 3 Bulanan',
                'expanded' => 'sirs.rl3*',
                'permission' => 'sirs.rl3_bulanan',
                'children' => [
                    ['title' => 'RL 3.1',  'route' => 'sirs.rl31',  'icon' => 'document-text', 'permission' => 'sirs.rl3_bulanan'],
                    ['title' => 'RL 3.2',  'route' => 'sirs.rl32',  'icon' => 'document-text', 'permission' => 'sirs.rl3_bulanan'],
                    ['title' => 'RL 3.3',  'route' => 'sirs.rl33',  'icon' => 'document-text', 'permission' => 'sirs.rl3_bulanan'],
                    ['title' => 'RL 3.4',  'route' => 'sirs.rl34',  'icon' => 'document-text', 'permission' => 'sirs.rl3_bulanan'],
                    ['title' => 'RL 3.5',  'route' => 'sirs.rl35',  'icon' => 'document-text', 'permission' => 'sirs.rl3_bulanan'],
                    ['title' => 'RL 3.6',  'route' => 'sirs.rl36',  'icon' => 'document-text', 'permission' => 'sirs.rl3_bulanan'],
                    ['title' => 'RL 3.7',  'route' => 'sirs.rl37',  'icon' => 'document-text', 'permission' => 'sirs.rl3_bulanan'],
                    ['title' => 'RL 3.8',  'route' => 'sirs.rl38',  'icon' => 'document-text', 'permission' => 'sirs.rl3_bulanan'],
                    ['title' => 'RL 3.9',  'route' => 'sirs.rl39',  'icon' => 'document-text', 'permission' => 'sirs.rl3_bulanan'],
                    ['title' => 'RL 3.10', 'route' => 'sirs.rl310', 'icon' => 'document-text', 'permission' => 'sirs.rl3_bulanan'],
                    ['title' => 'RL 3.12', 'route' => 'sirs.rl312', 'icon' => 'document-text', 'permission' => 'sirs.rl3_bulanan'],
                    ['title' => 'RL 3.14', 'route' => 'sirs.rl314', 'icon' => 'document-text', 'permission' => 'sirs.rl3_bulanan'],
                    ['title' => 'RL 3.19', 'route' => 'sirs.rl319', 'icon' => 'document-text', 'permission' => 'sirs.rl3_bulanan'],
                ]
            ],
            [
                'title' => 'RL 3 Tahunan',
                'expanded' => ['sirs.rl311', 'sirs.rl313', 'sirs.rl31[5-8]'],
                'permission' => 'sirs.rl3_tahunan',
                'children' => [
                    ['title' => 'RL 3.11', 'route' => 'sirs.rl311', 'icon' => 'document-text', 'permission' => 'sirs.rl3_tahunan'],
                    ['title' => 'RL 3.13', 'route' => 'sirs.rl313', 'icon' => 'document-text', 'permission' => 'sirs.rl3_tahunan'],
                    ['title' => 'RL 3.15', 'route' => 'sirs.rl315', 'icon' => 'document-text', 'permission' => 'sirs.rl3_tahunan'],
                    ['title' => 'RL 3.16', 'route' => 'sirs.rl316', 'icon' => 'document-text', 'permission' => 'sirs.rl3_tahunan'],
                    ['title' => 'RL 3.17', 'route' => 'sirs.rl317', 'icon' => 'document-text', 'permission' => 'sirs.rl3_tahunan'],
                    ['title' => 'RL 3.18', 'route' => 'sirs.rl318', 'icon' => 'document-text', 'permission' => 'sirs.rl3_tahunan'],
                ]
            ],
            [
                'title' => 'RL 4-5 Penyakit',
                'expanded' => ['sirs.rl4*', 'sirs.rl5*'],
                'permission' => 'sirs',
                'children' => [
                    ['title' => 'RL 4.1', 'route' => 'sirs.rl41', 'icon' => 'document-text', 'permission' => 'sirs.rl4'],
                    ['title' => 'RL 4.2', 'route' => 'sirs.rl42', 'icon' => 'document-text', 'permission' => 'sirs.rl4'],
                    ['title' => 'RL 4.3', 'route' => 'sirs.rl43', 'icon' => 'document-text', 'permission' => 'sirs.rl4'],
                    ['title' => 'RL 5.1', 'route' => 'sirs.rl51', 'icon' => 'document-text', 'permission' => 'sirs.rl5'],
                    ['title' => 'RL 5.2', 'route' => 'sirs.rl52', 'icon' => 'document-text', 'permission' => 'sirs.rl5'],
                    ['title' => 'RL 5.3', 'route' => 'sirs.rl53', 'icon' => 'document-text', 'permission' => 'sirs.rl5'],
                ]
            ]
        ]
    ],
    [
        'title' => 'Tanda Tangan Elektronik',
        'icon' => 'shield-check',
        'expanded' => 'tte*',
        'permission' => 'tte',
        'children' => [
            ['title' => 'Simulasi',  'route' => 'tte.simulation',  'icon' => 'pencil-square', 'permission' => 'tte.simulation'],
            ['title' => 'Verifikasi','route' => 'tte.verification','icon' => 'shield-check',  'permission' => 'tte.verification'],
            ['title' => 'Riwayat',   'route' => 'tte.history',     'icon' => 'clock',         'permission' => 'tte.history'],
        ]
    ],
    [
        'title' => 'DICOM / PACS',
        'icon' => 'photo',
        'expanded' => 'dicom.*',
        'permission' => 'dicom',
        'children' => [
            ['title' => 'Ringkasan',        'route' => 'dicom.summary',  'icon' => 'chart-bar',       'permission' => 'dicom.summary'],
            ['title' => 'Worklist',         'route' => 'dicom.worklist', 'icon' => 'queue-list',      'permission' => 'dicom.worklist'],
            ['title' => 'Router & Modality','route' => 'dicom.modality', 'icon' => 'computer-desktop','permission' => 'dicom.modality'],
            ['title' => 'Convert Image',    'route' => 'dicom.convert',  'icon' => 'arrow-path',      'permission' => 'dicom.convert'],
        ]
    ],
    [
        'title' => 'WhatsApp Gateway',
        'icon' => 'device-phone-mobile',
        'expanded' => 'whatsapp/*',
        'permission' => 'whatsapp',
        'children' => [
            ['title' => 'Pesan',     'route' => 'whatsapp.messages',  'icon' => 'chat-bubble-left-right', 'permission' => 'whatsapp.messages'],
            ['title' => 'Broadcast', 'route' => 'whatsapp.broadcast', 'icon' => 'megaphone',              'permission' => 'whatsapp.broadcast'],
            ['title' => 'Kontak',    'route' => 'whatsapp.contacts',  'icon' => 'user-group',             'permission' => 'whatsapp.contacts'],
        ]
    ],
    [
        'title' => 'API',
        'icon' => 'cpu-chip',
        'expanded' => 'api-portal*',
        'permission' => 'api_portal',
        'children' => [
            ['title' => 'Ringkasan',      'route' => 'api-portal.summary',       'icon' => 'chart-bar',         'permission' => 'api_portal.summary'],
            ['title' => 'Manajemen API',  'route' => 'api-portal.management',    'icon' => 'key',               'permission' => 'api_portal.management'],
            ['title' => 'Versi Modul',    'route' => 'api-portal.api-version',   'icon' => 'code-bracket-square','permission' => 'api_portal.api_version'],
            ['title' => 'Log API',        'route' => 'api-portal.logs',          'icon' => 'queue-list',        'permission' => 'api_portal.logs'],
            ['title' => 'Dokumentasi API','route' => 'api-portal.documentation', 'icon' => 'book-open',         'permission' => 'api_portal.documentation'],
            ['title' => 'Integrasi API',  'route' => 'api-portal.integration',   'icon' => 'code-bracket',      'permission' => 'api_portal.integration'],
            ['title' => 'Keamanan API',   'route' => 'api-portal.security',      'icon' => 'shield-check',      'permission' => 'api_portal.security'],
        ]
    ],
    [
        'title' => 'Log',
        'icon' => 'queue-list',
        'expanded' => ['logs*', 'logs/*'],
        'permission' => 'logs',
        'children' => [
            ['title' => 'Satu Sehat',   'route' => 'logs.satusehat', 'icon' => 'globe-alt',              'permission' => 'logs.satusehat'],
            ['title' => 'BPJS Kesehatan','route' => 'logs.bpjs',     'icon' => 'clipboard-document-list','permission' => 'logs.bpjs'],
            ['title' => 'WA Gateway',   'route' => 'logs.whatsapp',  'icon' => 'device-phone-mobile',    'permission' => 'logs.whatsapp'],
            ['title' => 'SIMRS',        'route' => 'logs.simrs',     'icon' => 'server',                 'permission' => 'logs.simrs'],
            ['title' => 'DICOM',        'route' => 'logs.dicom',     'icon' => 'photo',                  'permission' => 'logs.dicom'],
            ['title' => 'TTE',          'route' => 'logs.tte',       'icon' => 'finger-print',           'permission' => 'logs.tte'],
            ['title' => 'QR Code',      'route' => 'logs.qrcode',    'icon' => 'qr-code',               'permission' => 'logs.qrcode'],
            ['title' => 'AI Provider',  'route' => 'logs.ai',        'icon' => 'cpu-chip',               'permission' => 'logs.ai'],
        ]
    ],
    [
        'title' => 'Utilitas Sistem',
        'icon' => 'wrench-screwdriver',
        'expanded' => 'utility.*',
        'permission' => 'utility',
        'children' => [
            ['title' => 'QR Code Generator', 'route' => 'utility.qrcode-generator',  'icon' => 'qr-code',        'permission' => 'utility.qrcode'],
            ['title' => 'Status Koneksi',     'route' => 'utility.connection-status', 'icon' => 'signal',         'permission' => 'utility.connection_status'],
            ['title' => 'Versi SIMRS',        'route' => 'utility.simrs-version',     'icon' => 'bookmark-square','permission' => 'utility.simrs_version'],
            ['title' => 'Backup Database',    'route' => 'utility.database-backup',   'icon' => 'circle-stack',   'permission' => 'utility.database_backup'],
        ]
    ],
    [
        'title' => 'Pengaturan',
        'icon' => 'cog-6-tooth',
        'expanded' => 'configuration/*',
        'permission' => 'configuration',
        'children' => [
            ['title' => 'Informasi RS',       'route' => 'configuration.hospital',    'icon' => 'building-office',  'permission' => 'configuration.hospital'],
            ['title' => 'Konektivitas',        'route' => 'configuration.connectivity','icon' => 'arrows-right-left','permission' => 'configuration.connectivity'],
            ['title' => 'QRCode',              'route' => 'configuration.qrcode',     'icon' => 'qr-code',          'permission' => 'configuration.qrcode'],
            ['title' => 'Manajemen Pengguna',  'route' => 'configuration.users',      'icon' => 'users',            'permission' => 'configuration.users'],
            ['title' => 'Job & Queue',         'route' => 'configuration.jobs',       'icon' => 'queue-list',       'permission' => 'configuration.jobs'],
            ['title' => 'Keamanan Umum',       'route' => 'configuration.security',   'icon' => 'shield-check',     'permission' => 'configuration.security'],
            ['title' => 'Monitoring',          'route' => 'configuration.monitoring', 'icon' => 'chart-bar',        'permission' => 'configuration'],
        ]
    ],
    [
        'title' => 'Panduan Aplikasi',
        'route' => 'guide',
        'icon' => 'academic-cap',
        'active_match' => 'guide',
    ]
];
