{{-- ==================== OVERVIEW & AUTH ==================== --}}
@if ($activeSection === 'overview')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="globe-alt" class="w-5 h-5 text-emerald-500" />
            Satu Sehat — Overview
        </h2>
        <div class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Terapi mengintegrasikan <strong>Platform Satu Sehat (IHS)</strong> Kementerian Kesehatan RI
                menggunakan standar <strong>FHIR R4</strong>. Integrasi mencakup sinkronisasi data pasien, tenaga kesehatan,
                lokasi, dan pengiriman rekam medis elektronik (eRM) dalam format FHIR Bundle.
            </p>

            {{-- Auth --}}
            <div class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4 class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Autentikasi — OAuth2 Client Credentials</h4>
                <p class="mb-3 text-xs text-zinc-600 dark:text-primary-dark-300">
                    Token didapatkan dari endpoint OAuth2 dan di-cache otomatis. Setiap request ke FHIR API
                    menyertakan token sebagai Bearer pada header Authorization.
                </p>
                <x-atoms.code-block language="http"><span class="text-zinc-500"># Mendapatkan token (grant_type=client_credentials)</span>
<span class="text-blue-400">POST</span> <span class="text-emerald-400">{auth_url}/accesstoken?grant_type=client_credentials</span>
<span class="text-blue-400">Content-Type</span>: <span class="text-emerald-400">application/x-www-form-urlencoded</span>

<span class="text-amber-400">client_id</span>=<span class="text-emerald-400">{client_id}</span>&<span class="text-amber-400">client_secret</span>=<span class="text-emerald-400">{client_secret}</span>

<span class="text-zinc-500"># Gunakan pada request FHIR:</span>
<span class="text-blue-400">Authorization</span>: <span class="text-emerald-400">Bearer {access_token}</span></x-atoms.code-block>
            </div>

            {{-- URL Config --}}
            <div class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4 class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Konfigurasi URL</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Config Key</x-atoms.table-heading>
                        <x-atoms.table-heading>Deskripsi</x-atoms.table-heading>
                        <x-atoms.table-heading>Contoh (Production)</x-atoms.table-heading>
                    </x-slot:headings>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">satusehat.auth_url</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">URL autentikasi OAuth2</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400">https://api-satusehat.kemkes.go.id/oauth2/v1</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">satusehat.fhir_url</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Base URL FHIR R4</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400">https://api-satusehat.kemkes.go.id/fhir-r4/v1</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- Environment --}}
            <div class="p-3 border rounded-xl bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
                <div class="flex gap-3">
                    <flux:icon name="information-circle" class="flex-shrink-0 w-5 h-5 text-blue-600 dark:text-blue-400" />
                    <div class="text-sm text-blue-800 dark:text-blue-200">
                        <p class="font-medium mb-1">Environment Detection</p>
                        <p class="text-xs">
                            Terapi mendeteksi environment secara otomatis berdasarkan URL:
                            jika mengandung <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50">stg</code>
                            maka dianggap staging/development. Ini mempengaruhi pemilihan kunci enkripsi
                            pada modul KYC.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ==================== FHIR RESOURCES ==================== --}}
@if ($activeSection === 'fhir-resources')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="squares-2x2" class="w-5 h-5 text-primary-500" />
            FHIR Resources yang Tersedia
        </h2>
        <p class="mb-4 text-sm text-zinc-500 dark:text-primary-dark-400">
            Terapi mengimplementasikan resource FHIR R4 berikut untuk integrasi dengan Platform Satu Sehat.
            Semua operasi menggunakan base URL FHIR (<code class="font-mono text-xs">fhir_url</code>) sebagai prefix.
        </p>

        <div class="space-y-4">
            {{-- Master Data --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Master Data / Identitas</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Resource</x-atoms.table-heading>
                        <x-atoms.table-heading>Endpoint FHIR</x-atoms.table-heading>
                        <x-atoms.table-heading>Operasi</x-atoms.table-heading>
                    </x-slot:headings>
                    @foreach ([
                        ['Organization', '/Organization', 'GET, POST, PUT'],
                        ['Location', '/Location', 'GET, POST, PUT'],
                        ['HealthcareService', '/HealthcareService', 'GET, POST, PUT, DELETE'],
                        ['Patient', '/Patient', 'GET ($match), POST, PUT'],
                        ['Practitioner', '/Practitioner', 'GET ($match), POST, PUT'],
                    ] as [$res, $ep, $ops])
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-medium text-zinc-700 dark:text-primary-dark-300">{{ $res }}</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">{{ $ep }}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $ops }}</x-atoms.table-cell>
                    </x-molecules.table-row>
                    @endforeach
                </x-organisms.table>
            </div>

            {{-- Clinical Resources --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Clinical Resources (eRM / Rekam Medis)</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Resource</x-atoms.table-heading>
                        <x-atoms.table-heading>Endpoint FHIR</x-atoms.table-heading>
                        <x-atoms.table-heading>Deskripsi</x-atoms.table-heading>
                    </x-slot:headings>
                    @foreach ([
                        ['Encounter', '/Encounter', 'Data kunjungan pasien'],
                        ['Condition', '/Condition', 'Diagnosa ICD-10'],
                        ['Procedure', '/Procedure', 'Prosedur ICD-9 / tindakan'],
                        ['Observation', '/Observation', 'Hasil observasi & laboratorium'],
                        ['MedicationRequest', '/MedicationRequest', 'Resep obat'],
                        ['MedicationDispense', '/MedicationDispense', 'Pemberian obat'],
                        ['ServiceRequest', '/ServiceRequest', 'Permintaan layanan lab/rad'],
                        ['DiagnosticReport', '/DiagnosticReport', 'Laporan diagnostik'],
                        ['Specimen', '/Specimen', 'Spesimen laboratorium'],
                        ['Composition', '/Composition', 'Dokumen ringkasan klinis'],
                        ['ClinicalImpression', '/ClinicalImpression', 'Penilaian klinis'],
                        ['AllergyIntolerance', '/AllergyIntolerance', 'Alergi dan intoleransi'],
                        ['Immunization', '/Immunization', 'Imunisasi'],
                    ] as [$res, $ep, $desc])
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-medium text-zinc-700 dark:text-primary-dark-300">{{ $res }}</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">{{ $ep }}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $desc }}</x-atoms.table-cell>
                    </x-molecules.table-row>
                    @endforeach
                </x-organisms.table>
            </div>

            {{-- Catatan Bundle --}}
            <div class="p-3 border rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800">
                <div class="flex gap-2">
                    <flux:icon name="check-circle" class="flex-shrink-0 w-4 h-4 mt-0.5 text-emerald-600 dark:text-emerald-400" />
                    <p class="text-xs text-emerald-800 dark:text-emerald-200">
                        Pengiriman rekam medis eRM dilakukan melalui <strong>FHIR Bundle</strong> yang menggabungkan
                        beberapa resource sekaligus. Terapi mengelola alur validasi, pembangunan bundle, dan pengiriman
                        secara terpusat melalui <code class="px-1 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/50 text-xs">ErmFhirService</code>.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ==================== PATIENT ==================== --}}
@if ($activeSection === 'patient')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-1 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="user" class="w-5 h-5 text-primary-500" />
            Patient — Sinkronisasi Pasien
        </h2>
        <p class="mb-4 text-sm text-zinc-500 dark:text-primary-dark-400">
            Sinkronisasi data pasien ke IHS menggunakan operasi <code class="font-mono text-xs">$match</code> (pencarian berdasarkan NIK)
            dan <code class="font-mono text-xs">POST/PUT</code> untuk registrasi. Data pasien diambil dari model lokal
            <code class="font-mono text-xs">App\Models\Patient</code> — bukan langsung dari SIMRS.
        </p>

        <div class="space-y-4">
            {{-- Alur --}}
            <div class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4 class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">Alur Sinkronisasi</h4>
                <div class="space-y-2">
                    @foreach ([
                        ['Cari IHS ID', 'POST /Patient/$match dengan identifier NIK → mendapatkan IHS number dari IHS'],
                        ['Simpan Lokal', 'Hasil IHS number disimpan di tabel satu_sehat_patients untuk referensi resource lain'],
                        ['Update Data', 'PUT /Patient/{ihs_number} jika perlu memperbarui data demografis'],
                    ] as [$step, $desc])
                    <div class="flex items-start gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-6 h-6 mt-0.5 text-xs font-bold rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">{{ $loop->iteration }}</div>
                        <div>
                            <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300">{{ $step }}</p>
                            <p class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $desc }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Endpoints --}}
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Method</x-atoms.table-heading>
                    <x-atoms.table-heading>Endpoint</x-atoms.table-heading>
                    <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                </x-slot:headings>
                <x-molecules.table-row>
                    <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/Patient/$match</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Cari pasien di IHS berdasarkan NIK (identifier)</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/Patient</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Registrasi pasien baru ke IHS</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell><flux:badge color="amber" size="sm">PUT</flux:badge></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/Patient/{ihs_number}</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Update data pasien di IHS</x-atoms.table-cell>
                </x-molecules.table-row>
            </x-organisms.table>

            {{-- Info NIK --}}
            <div class="p-3 border rounded-xl bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800">
                <div class="flex gap-2">
                    <flux:icon name="exclamation-triangle" class="flex-shrink-0 w-4 h-4 mt-0.5 text-amber-600 dark:text-amber-400" />
                    <p class="text-xs text-amber-800 dark:text-amber-200">
                        Identifier yang digunakan adalah <strong>NIK (No. KTP)</strong> dari field
                        <code class="px-1 py-0.5 rounded bg-amber-100 dark:bg-amber-900/50 text-xs">no_ktp</code> pada data pasien lokal.
                        Pastikan NIK valid dan terdaftar di Dukcapil.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ==================== ENCOUNTER ==================== --}}
@if ($activeSection === 'encounter')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-1 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="clipboard-document" class="w-5 h-5 text-primary-500" />
            Encounter — Data Kunjungan
        </h2>
        <p class="mb-4 text-sm text-zinc-500 dark:text-primary-dark-400">
            Encounter adalah resource utama dalam rekam medis FHIR. Setiap kunjungan (rawat jalan, IGD, rawat inap)
            direpresentasikan sebagai Encounter. Resource klinis lain (Condition, Procedure, dll) mereferensikan Encounter.
        </p>

        <div class="space-y-4">
            {{-- Status Encounter --}}
            <div class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">Status Encounter</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Status</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>
                    @foreach ([
                        ['arrived', 'Pasien telah tiba'],
                        ['in-progress', 'Kunjungan sedang berlangsung'],
                        ['finished', 'Kunjungan selesai'],
                        ['cancelled', 'Kunjungan dibatalkan'],
                    ] as [$status, $desc])
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">{{ $status }}</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">{{ $desc }}</x-atoms.table-cell>
                    </x-molecules.table-row>
                    @endforeach
                </x-organisms.table>
            </div>

            {{-- Endpoints --}}
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Method</x-atoms.table-heading>
                    <x-atoms.table-heading>Endpoint</x-atoms.table-heading>
                    <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                </x-slot:headings>
                <x-molecules.table-row>
                    <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/Encounter</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Kirim data kunjungan baru</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell><flux:badge color="amber" size="sm">PUT</flux:badge></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/Encounter/{ihs_id}</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Update data kunjungan (mis. ubah status menjadi finished)</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/Encounter/{ihs_id}</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Ambil detail kunjungan dari IHS</x-atoms.table-cell>
                </x-molecules.table-row>
            </x-organisms.table>

            {{-- Referensi --}}
            <div class="p-3 border rounded-xl bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
                <div class="flex gap-2">
                    <flux:icon name="information-circle" class="flex-shrink-0 w-4 h-4 mt-0.5 text-blue-600 dark:text-blue-400" />
                    <p class="text-xs text-blue-800 dark:text-blue-200">
                        Encounter mereferensikan: <strong>Patient</strong> (IHS number), <strong>Practitioner</strong> (DPJP),
                        <strong>Location</strong> (kd_poli), dan <strong>Organization</strong> (RS). Semua referensi harus sudah
                        tersinkronisasi ke IHS sebelum Encounter dikirim. IHS ID Encounter disimpan di tabel
                        <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">satu_sehat_encounters</code>
                        dengan <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">local_id = no_rawat</code>.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ==================== BUNDLE eRM ==================== --}}
@if ($activeSection === 'erm')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-1 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="paper-airplane" class="w-5 h-5 text-primary-500" />
            Bundle eRM — Pengiriman Rekam Medis
        </h2>
        <p class="mb-4 text-sm text-zinc-500 dark:text-primary-dark-400">
            Pengiriman rekam medis elektronik ke Satu Sehat dilakukan melalui alur bundle yang terstruktur.
            Setiap kunjungan (no_rawat) menghasilkan satu bundle yang berisi kumpulan resource FHIR.
        </p>

        <div class="space-y-5">
            {{-- Alur bundle --}}
            <div>
                <h4 class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">Alur Pengiriman Bundle</h4>
                <div class="space-y-2">
                    @foreach ([
                        ['Validasi', 'ErmValidator memeriksa kelengkapan data: SEP, ICD mapping, prosedur, observasi, obat'],
                        ['Lookup Referensi', 'ErmFhirService mencari IHS ID patient (NIK), practitioner (NIK dari kd_dokter), dan location (kd_poli)'],
                        ['Kirim Encounter', 'Encounter dikirim terlebih dahulu karena resource lain mereferensikannya'],
                        ['Kirim Resource Klinis', 'Condition, Procedure, Observation, MedicationRequest, dll. dikirim satu per satu'],
                        ['Log Bundle', 'Setiap resource dicatat di SatuSehatBundleLog dengan status dan IHS ID yang diterima'],
                    ] as [$step, $desc])
                    <div class="flex items-start gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-6 h-6 mt-0.5 text-xs font-bold rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">{{ $loop->iteration }}</div>
                        <div>
                            <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300">{{ $step }}</p>
                            <p class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $desc }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Resource dalam bundle --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">Resource dalam Bundle eRM</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Resource</x-atoms.table-heading>
                        <x-atoms.table-heading>Sumber Data SIMRS</x-atoms.table-heading>
                        <x-atoms.table-heading>Wajib?</x-atoms.table-heading>
                    </x-slot:headings>
                    @foreach ([
                        ['Encounter', 'Data registrasi (no_rawat, kd_dokter, kd_poli)', true],
                        ['Condition', 'Diagnosa ICD-10 dari PemeriksaanRalan/PemeriksaanRanap', true],
                        ['Procedure', 'Prosedur ICD-9 dari ProsedurPasien', false],
                        ['Observation', 'Hasil lab dari TemplateLaboratorium', false],
                        ['MedicationRequest', 'Resep obat dari DetailPemberianObat', false],
                        ['MedicationDispense', 'Pemberian obat (diturunkan dari MedicationRequest)', false],
                        ['ServiceRequest', 'Permintaan lab/radiologi', false],
                        ['DiagnosticReport', 'Laporan hasil lab/rad', false],
                        ['Specimen', 'Spesimen laboratorium (linked by local_id)', false],
                        ['Composition', 'Ringkasan klinis kunjungan', false],
                        ['ClinicalImpression', 'Penilaian klinis DPJP', false],
                        ['AllergyIntolerance', 'Data alergi pasien', false],
                        ['Immunization', 'Data imunisasi', false],
                    ] as [$res, $src, $required])
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-medium text-zinc-700 dark:text-primary-dark-300">{{ $res }}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $src }}</x-atoms.table-cell>
                        <x-atoms.table-cell>
                            @if ($required)
                                <flux:badge color="red" size="sm">Wajib</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Opsional</flux:badge>
                            @endif
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                    @endforeach
                </x-organisms.table>
            </div>

            {{-- Catatan --}}
            <div class="p-3 border rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800">
                <div class="flex gap-2">
                    <flux:icon name="check-circle" class="flex-shrink-0 w-4 h-4 mt-0.5 text-emerald-600 dark:text-emerald-400" />
                    <p class="text-xs text-emerald-800 dark:text-emerald-200">
                        Pengiriman dapat dipicu dari halaman eRM per kunjungan. Setiap pengiriman dicatat di
                        <code class="px-1 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/50 text-xs">SatuSehatBundle</code>
                        (ringkasan) dan <code class="px-1 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/50 text-xs">SatuSehatBundleLog</code>
                        (detail per resource) untuk audit trail. Log dapat dilihat di
                        <strong>Log &gt; Satu Sehat &gt; Bundle Log</strong>.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endif
