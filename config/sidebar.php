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
        'children' => [
            ['title' => 'Pasien', 'route' => 'simrs.patient', 'icon' => 'user-group'],
            ['title' => 'Poliklinik / Unit', 'route' => 'simrs.polyclinic', 'icon' => 'building-office-2'],
            ['title' => 'Pegawai', 'route' => 'simrs.employee', 'icon' => 'users'],
            ['title' => 'Tindakan', 'route' => 'simrs.procedure', 'icon' => 'clipboard-document-list'],
            ['title' => 'ICD-9', 'route' => 'simrs.icd9', 'icon' => 'document-text'],
            ['title' => 'ICD-10', 'route' => 'simrs.icd10', 'icon' => 'document-text'],
            ['title' => 'Kamar', 'route' => 'simrs.room', 'icon' => 'home-modern'],
            ['title' => 'Alergi', 'route' => 'simrs.allergy', 'icon' => 'shield-exclamation'],
            ['title' => 'Departemen', 'route' => 'simrs.department', 'icon' => 'building-office'],
        ]
    ],
    [
        'title' => 'Source Terminology',
        'icon' => 'globe-alt',
        'expanded' => 'terminology.*',
        'children' => [
            ['title' => 'Pencarian Pintar', 'route' => 'terminology.smart-search', 'icon' => 'sparkles'],
            ['title' => 'Kamus FHIR', 'route' => 'terminology.fhir-dictionary', 'icon' => 'book-open'],
            ['title' => 'SNOMED CT', 'route' => 'terminology.snomed', 'icon' => 'cog-6-tooth'],
            ['title' => 'LOINC', 'route' => 'terminology.loinc', 'icon' => 'beaker'],
            ['title' => 'KFA', 'route' => 'terminology.kfa', 'icon' => 'archive-box'],
            ['title' => 'ICD-O Morphology', 'route' => 'terminology.icd-o-morphology', 'icon' => 'hashtag'],
            ['title' => 'ICD-O Topography', 'route' => 'terminology.icd-o-topography', 'icon' => 'hashtag'],
            ['title' => 'ICD-9CM', 'route' => 'terminology.icd9cm', 'icon' => 'hashtag'],
            ['title' => 'ICD-10', 'route' => 'terminology.icd10', 'icon' => 'hashtag'],
            ['title' => 'ICD-MM', 'route' => 'terminology.icd-mm', 'icon' => 'hashtag'],
            ['title' => 'ICD-PM', 'route' => 'terminology.icd-pm', 'icon' => 'hashtag'],
        ]
    ],
    [
        'title' => 'Local Terminology',
        'icon' => 'rectangle-stack',
        'expanded' => 'local.*',
        'children' => [
            ['title' => 'Ringkasan', 'route' => 'local.summary', 'icon' => 'chart-pie'],
            ['title' => 'Organization', 'route' => 'local.organization', 'icon' => 'building-library', 'active_match' => ['local.organization', 'bpjs.fhir-resource.organization', 'satusehat.fhir-resource.organizations']],
            ['title' => 'Patient', 'route' => 'local.patient', 'icon' => 'users', 'active_match' => ['local.patient', 'bpjs.fhir-resource.patient', 'satusehat.fhir-resource.patients']],
            [
                'title' => 'Source',
                'icon' => 'document-text',
                'expanded' => 'local.source*',
                'children' => [
                    ['title' => 'ICD-10', 'route' => 'local.source.icd10', 'icon' => 'hashtag'],
                    ['title' => 'ICD-9-CM', 'route' => 'local.source.icd9', 'icon' => 'hashtag'],
                    ['title' => 'ICD-O Topografi', 'route' => 'local.source.icd-o-topography', 'icon' => 'hashtag'],
                    ['title' => 'ICD-O Morfologi', 'route' => 'local.source.icd-o-morphology', 'icon' => 'hashtag'],
                    ['title' => 'ICD-PM', 'route' => 'local.source.icd-pm', 'icon' => 'hashtag'],
                    ['title' => 'ICD-MM', 'route' => 'local.source.icd-mm', 'icon' => 'hashtag'],
                ]
            ],
            [
                'title' => 'Clinical',
                'icon' => 'clipboard-document-check',
                'expanded' => 'local.clinical*',
                'children' => [
                    ['title' => 'Tindakan', 'route' => 'local.clinical.procedure', 'icon' => 'scissors', 'active_match' => ['local.clinical.procedure', 'bpjs.fhir-resource.procedure']],
                    ['title' => 'Operasi', 'route' => 'local.clinical.surgery', 'icon' => 'scissors'],
                ]
            ],
            [
                'title' => 'Practitioner',
                'icon' => 'user-group',
                'expanded' => 'local.practitioner*',
                'children' => [
                    ['title' => 'Dokter', 'route' => 'local.practitioner.doctor', 'icon' => 'user'],
                    ['title' => 'Tenaga Medis', 'route' => 'local.practitioner.medical', 'icon' => 'user-plus'],
                    ['title' => 'Keperawatan/Kebidanan', 'route' => 'local.practitioner.nursing', 'icon' => 'heart'],
                    ['title' => 'Penunjang Medis', 'route' => 'local.practitioner.support', 'icon' => 'wrench-screwdriver'],
                    ['title' => 'Non Medis', 'route' => 'local.practitioner.non-medical', 'icon' => 'briefcase'],
                ]
            ],
            [
                'title' => 'Observation',
                'icon' => 'eye',
                'expanded' => 'local.observation*',
                'children' => [
                    ['title' => 'Laboratorium', 'route' => 'local.observation.laboratory', 'icon' => 'beaker', 'active_match' => ['local.observation.laboratory', 'bpjs.fhir-resource.observation.lab']],
                    ['title' => 'Radiologi', 'route' => 'local.observation.radiology', 'icon' => 'camera', 'active_match' => ['local.observation.radiology', 'bpjs.fhir-resource.observation.radiology']],
                ]
            ],
            [
                'title' => 'Medication',
                'icon' => 'archive-box',
                'expanded' => 'local.medication*',
                'children' => [
                    ['title' => 'Obat', 'route' => 'local.medication.medicine', 'icon' => 'cube', 'active_match' => ['local.medication.medicine', 'bpjs.fhir-resource.medication', 'satusehat.fhir-resource.medication']],
                    ['title' => 'Vaksin', 'route' => 'local.medication.vaccine', 'icon' => 'shield-check', 'active_match' => ['local.medication.vaccine', 'bpjs.fhir-resource.medication.vaccine']],
                ]
            ],
            [
                'title' => 'Device',
                'icon' => 'cpu-chip',
                'expanded' => 'local.device*',
                'children' => [
                    ['title' => 'Alat Kesehatan', 'route' => 'local.device.equipment', 'icon' => 'wrench', 'active_match' => ['local.device.equipment', 'bpjs.fhir-resource.device.equipment']],
                ]
            ],
            [
                'title' => 'Allergy Intolerance',
                'icon' => 'shield-exclamation',
                'expanded' => 'local.allergy*',
                'children' => [
                    ['title' => 'Alergi', 'route' => 'local.allergy.allergy', 'icon' => 'exclamation-circle', 'active_match' => ['local.allergy.allergy', 'bpjs.fhir-resource.allergy.allergy']],
                    ['title' => 'Reaksi Alergi', 'route' => 'local.allergy.reaction', 'icon' => 'fire', 'active_match' => ['local.allergy.reaction', 'bpjs.fhir-resource.allergy.reaction']],
                ]
            ],
            [
                'title' => 'Healthcare Service',
                'icon' => 'building-office-2',
                'expanded' => 'local.healthcare-service*',
                'children' => [
                    ['title' => 'Poliklinik', 'route' => 'local.healthcare-service.polyclinic', 'icon' => 'building-storefront', 'active_match' => ['local.healthcare-service.polyclinic', 'bpjs.fhir-resource.healthcare-service', 'satusehat.fhir-resource.healthcare-services']],
                    ['title' => 'Bangsal', 'route' => 'local.healthcare-service.ward', 'icon' => 'home-modern', 'active_match' => ['local.healthcare-service.ward', 'satusehat.fhir-resource.locations']],
                ]
            ],
            [
                'title' => 'Episode of Care',
                'route' => 'local.episode-of-care.index',
                'icon' => 'clipboard-document-check',
                'active_match' => 'local.episode-of-care.*',
            ],
        ]
    ],
    [
        'title' => 'eRM',
        'icon' => 'document-text',
        'expanded' => 'erm/*',
        'children' => [
            ['title' => 'IGD', 'route' => 'erm.igd', 'icon' => 'exclamation-triangle'],
            ['title' => 'Rawat Jalan', 'route' => 'erm.rawat-jalan', 'icon' => 'user'],
            ['title' => 'Rawat Inap', 'route' => 'erm.rawat-inap', 'icon' => 'building-office'],
        ]
    ],
    [
        'title' => 'Satu Sehat',
        'icon' => 'heart',
        'expanded' => 'satusehat/*',
        'children' => [
            ['title' => 'Ringkasan', 'route' => 'satusehat.summary', 'icon' => 'chart-bar-square'],
            ['title' => 'Penjadwalan', 'route' => 'satusehat.scheduler', 'icon' => 'calendar-days'],
            ['title' => 'Kamus Rule Number', 'route' => 'satusehat.rule-number', 'icon' => 'book-open'],
            [
                'title' => 'KYC',
                'icon' => 'identification',
                'expanded' => ['satusehat/kyc/*', 'satusehat/kyc*'],
                'children' => [
                    ['title' => 'Verifikasi Pasien', 'route' => 'satusehat.kyc.generate-url', 'icon' => 'shield-check'],
                    ['title' => 'Riwayat KYC', 'route' => 'satusehat.kyc.logs', 'icon' => 'clipboard-document-list'],
                ]
            ],
            [
                'title' => 'FHIR Resource',
                'icon' => 'building-office-2',
                'expanded' => 'satusehat.fhir-resource*',
                'children' => [
                    ['title' => 'Episode of Care', 'route' => 'satusehat.fhir-resource.episode-of-care', 'icon' => 'document-magnifying-glass'],
                    ['title' => 'Healthcare Services', 'route' => 'satusehat.fhir-resource.healthcare-services', 'icon' => 'building-office-2'],
                    ['title' => 'Locations', 'route' => 'satusehat.fhir-resource.locations', 'icon' => 'building-storefront'],
                    ['title' => 'Medication', 'route' => 'satusehat.fhir-resource.medication', 'icon' => 'eye-dropper'],
                    ['title' => 'Organizations', 'route' => 'satusehat.fhir-resource.organizations', 'icon' => 'building-office'],
                    ['title' => 'Patients', 'route' => 'satusehat.fhir-resource.patients', 'icon' => 'users'],
                    ['title' => 'Practitioners', 'route' => 'satusehat.fhir-resource.practitioners', 'icon' => 'users'],
                ]
            ]
        ],
    ],
    [
        'title' => 'BPJS Kesehatan',
        'icon' => 'heart',
        'expanded' => 'bpjs/*',
        'children' => [
            ['title' => 'Ringkasan', 'route' => 'bpjs.summary', 'icon' => 'chart-bar-square'],
            ['title' => 'Antrean Online', 'route' => 'bpjs.antrean-online', 'icon' => 'queue-list'],
            ['title' => 'Aplicare', 'route' => 'bpjs.aplicare', 'icon' => 'home-modern'],
            ['title' => 'eRM', 'route' => 'bpjs.erm', 'icon' => 'document-check', 'active_match' => 'bpjs.erm*'],
        ]
    ],
    [
        'title' => 'RS Online',
        'icon' => 'heart',
        'expanded' => 'rsonline/*',
        'children' => [
            ['title' => 'Master Referensi', 'route' => 'rsonline.referensi', 'icon' => 'book-open'],
            ['title' => 'Data Pasien', 'route' => 'rsonline.pasien', 'icon' => 'users'],
            ['title' => 'Data Fasyankes', 'route' => 'rsonline.fasyankes', 'icon' => 'building-office-2'],
        ]
    ],
    [
        'title' => 'SIRS Online',
        'icon' => 'heart',
        'expanded' => 'sirs*',
        'children' => [
            ['title' => 'Dashboard', 'route' => 'sirs.index', 'icon' => 'chart-bar-square'],
            [
                'title' => 'RL 3 Bulanan',
                'expanded' => 'sirs.rl3*',
                'children' => [
                    ['title' => 'RL 3.1', 'route' => 'sirs.rl31', 'icon' => 'document-text'],
                    ['title' => 'RL 3.2', 'route' => 'sirs.rl32', 'icon' => 'document-text'],
                    ['title' => 'RL 3.3', 'route' => 'sirs.rl33', 'icon' => 'document-text'],
                    ['title' => 'RL 3.4', 'route' => 'sirs.rl34', 'icon' => 'document-text'],
                    ['title' => 'RL 3.5', 'route' => 'sirs.rl35', 'icon' => 'document-text'],
                    ['title' => 'RL 3.6', 'route' => 'sirs.rl36', 'icon' => 'document-text'],
                    ['title' => 'RL 3.7', 'route' => 'sirs.rl37', 'icon' => 'document-text'],
                    ['title' => 'RL 3.8', 'route' => 'sirs.rl38', 'icon' => 'document-text'],
                    ['title' => 'RL 3.9', 'route' => 'sirs.rl39', 'icon' => 'document-text'],
                    ['title' => 'RL 3.10', 'route' => 'sirs.rl310', 'icon' => 'document-text'],
                    ['title' => 'RL 3.12', 'route' => 'sirs.rl312', 'icon' => 'document-text'],
                    ['title' => 'RL 3.14', 'route' => 'sirs.rl314', 'icon' => 'document-text'],
                    ['title' => 'RL 3.19', 'route' => 'sirs.rl319', 'icon' => 'document-text'],
                ]
            ],
            [
                'title' => 'RL 3 Tahunan',
                'expanded' => ['sirs.rl311', 'sirs.rl313', 'sirs.rl31[5-8]'],
                'children' => [
                    ['title' => 'RL 3.11', 'route' => 'sirs.rl311', 'icon' => 'document-text'],
                    ['title' => 'RL 3.13', 'route' => 'sirs.rl313', 'icon' => 'document-text'],
                    ['title' => 'RL 3.15', 'route' => 'sirs.rl315', 'icon' => 'document-text'],
                    ['title' => 'RL 3.16', 'route' => 'sirs.rl316', 'icon' => 'document-text'],
                    ['title' => 'RL 3.17', 'route' => 'sirs.rl317', 'icon' => 'document-text'],
                    ['title' => 'RL 3.18', 'route' => 'sirs.rl318', 'icon' => 'document-text'],
                ]
            ],
            [
                'title' => 'RL 4-5 Penyakit',
                'expanded' => ['sirs.rl4*', 'sirs.rl5*'],
                'children' => [
                    ['title' => 'RL 4.1', 'route' => 'sirs.rl41', 'icon' => 'document-text'],
                    ['title' => 'RL 4.2', 'route' => 'sirs.rl42', 'icon' => 'document-text'],
                    ['title' => 'RL 4.3', 'route' => 'sirs.rl43', 'icon' => 'document-text'],
                    ['title' => 'RL 5.1', 'route' => 'sirs.rl51', 'icon' => 'document-text'],
                    ['title' => 'RL 5.2', 'route' => 'sirs.rl52', 'icon' => 'document-text'],
                    ['title' => 'RL 5.3', 'route' => 'sirs.rl53', 'icon' => 'document-text'],
                ]
            ]
        ]
    ],
    [
        'title' => 'Tanda Tangan Elektronik',
        'icon' => 'shield-check',
        'expanded' => 'tte*',
        'children' => [
            ['title' => 'Simulasi', 'route' => 'tte.simulation', 'icon' => 'pencil-square'],
            ['title' => 'Verifikasi', 'route' => 'tte.verification', 'icon' => 'shield-check'],
            ['title' => 'Riwayat', 'route' => 'tte.history', 'icon' => 'clock'],
        ]
    ],
    [
        'title' => 'DICOM / PACS',
        'icon' => 'photo',
        'expanded' => 'dicom.*',
        'children' => [
            ['title' => 'Ringkasan', 'route' => 'dicom.summary', 'icon' => 'chart-bar'],
            ['title' => 'Worklist', 'route' => 'dicom.worklist', 'icon' => 'queue-list'],
            ['title' => 'Viewer PACS', 'route' => 'dicom.viewer', 'icon' => 'eye'],
            ['title' => 'Modality', 'route' => 'dicom.modality', 'icon' => 'computer-desktop'],
            ['title' => 'Convert Image', 'route' => 'dicom.convert', 'icon' => 'arrow-path'],
            ['title' => 'DICOM Router', 'route' => 'dicom.router', 'icon' => 'arrows-right-left'],
        ]
    ],
    [
        'title' => 'WhatsApp Gateway',
        'icon' => 'device-phone-mobile',
        'expanded' => 'whatsapp/*',
        'children' => [
            ['title' => 'Pesan', 'route' => 'whatsapp.messages', 'icon' => 'chat-bubble-left-right'],
            ['title' => 'Broadcast', 'route' => 'whatsapp.broadcast', 'icon' => 'megaphone'],
            ['title' => 'Kontak', 'route' => 'whatsapp.contacts', 'icon' => 'user-group'],
        ]
    ],
    [
        'title' => 'API',
        'icon' => 'cpu-chip',
        'expanded' => 'api-portal*',
        'children' => [
            ['title' => 'Ringkasan', 'route' => 'api-portal.summary', 'icon' => 'chart-bar'],
            ['title' => 'Manajemen API', 'route' => 'api-portal.management', 'icon' => 'key'],
            ['title' => 'Log API', 'route' => 'api-portal.logs', 'icon' => 'queue-list'],
            ['title' => 'Dokumentasi API', 'route' => 'api-portal.documentation', 'icon' => 'book-open'],
            ['title' => 'Integrasi API', 'route' => 'api-portal.integration', 'icon' => 'code-bracket'],
            ['title' => 'Keamanan API', 'route' => 'api-portal.security', 'icon' => 'shield-check'],
        ]
    ],
    [
        'title' => 'Log',
        'icon' => 'queue-list',
        'expanded' => ['logs*', 'logs/*'],
        'children' => [
            ['title' => 'Satu Sehat', 'route' => 'logs.satusehat', 'icon' => 'globe-alt'],
            ['title' => 'BPJS Kesehatan', 'route' => 'logs.bpjs', 'icon' => 'clipboard-document-list'],
            ['title' => 'WA Gateway', 'route' => 'logs.whatsapp', 'icon' => 'device-phone-mobile'],
            ['title' => 'SIMRS', 'route' => 'logs.simrs', 'icon' => 'server'],
            ['title' => 'DICOM', 'route' => 'logs.dicom', 'icon' => 'photo'],
            ['title' => 'TTE', 'route' => 'logs.tte', 'icon' => 'finger-print'],
            ['title' => 'QR Code', 'route' => 'logs.qrcode', 'icon' => 'qr-code'],
            ['title' => 'AI Provider', 'route' => 'logs.ai', 'icon' => 'cpu-chip'],
        ]
    ],
    [
        'title' => 'Utilitas Sistem',
        'icon' => 'wrench-screwdriver',
        'expanded' => 'utility.*',
        'children' => [
            ['title' => 'QR Code Generator', 'route' => 'utility.qrcode-generator', 'icon' => 'qr-code'],
            ['title' => 'Status Koneksi', 'route' => 'utility.connection-status', 'icon' => 'signal'],
            ['title' => 'Versi SIMRS', 'route' => 'utility.simrs-version', 'icon' => 'bookmark-square'],
            ['title' => 'Backup Database', 'route' => 'utility.database-backup', 'icon' => 'circle-stack'],
        ]
    ],
    [
        'title' => 'Pengaturan',
        'icon' => 'cog-6-tooth',
        'expanded' => 'configuration/*',
        'children' => [
            ['title' => 'Informasi RS', 'route' => 'configuration.hospital', 'icon' => 'building-office'],
            ['title' => 'Konektivitas', 'route' => 'configuration.connectivity', 'icon' => 'arrows-right-left'],
            ['title' => 'QRCode', 'route' => 'configuration.qrcode', 'icon' => 'qr-code'],
            ['title' => 'Manajemen Pengguna', 'route' => 'configuration.users', 'icon' => 'users'],
            ['title' => 'Job & Queue', 'route' => 'configuration.jobs', 'icon' => 'queue-list'],
            ['title' => 'Keamanan Umum', 'route' => 'configuration.security', 'icon' => 'shield-check'],
            ['title' => 'Monitoring', 'route' => 'configuration.monitoring', 'icon' => 'chart-bar'],
        ]
    ],
    [
        'title' => 'Panduan Aplikasi',
        'route' => 'guide',
        'icon' => 'academic-cap',
        'active_match' => 'guide',
    ]
];
