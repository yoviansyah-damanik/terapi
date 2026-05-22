{{-- ==================== OVERVIEW ==================== --}}
@if ($activeSection === 'overview')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="book-open" class="w-5 h-5 text-primary-500" />
            Overview
        </h2>

        <div class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
                ESign Client Service adalah aplikasi penghubung antara Sistem Informasi milik pengguna
                dengan
                Sistem Penandatangan Elektronik berbasis cloud milik <strong>Balai Sertifikasi
                    Elektronik (BSrE) - BSSN</strong>.
                API ini digunakan untuk menandatangani dokumen PDF secara digital, memverifikasi tanda
                tangan,
                mengelola segel elektronik instansi, serta mengelola status sertifikat pengguna.
            </p>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Dokumentasi ini berdasarkan <strong>Petunjuk Teknis Penggunaan API ESign Client Service
                    v2.2.1</strong>
                (API Service Version 2). Semua endpoint menggunakan metode <strong>POST</strong> dengan
                format <strong>JSON</strong>.
            </p>

            {{-- Base URL --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Base URL</h4>
                <code
                    class="block px-3 py-2 text-sm font-mono rounded bg-zinc-900 dark:bg-primary-dark-950 text-emerald-400">
                    {{ $tteBaseUrl }}
                </code>
            </div>

            {{-- Daftar Endpoint --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Daftar Endpoint (API v2)</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Endpoint</x-atoms.table-heading>
                        <x-atoms.table-heading>Deskripsi</x-atoms.table-heading>
                        <x-atoms.table-heading>Kategori</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/v2/sign/pdf</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Tanda tangan PDF</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">Sign</flux:badge></x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/v2/sign/get/totp</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Request OTP untuk sign</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">Sign</flux:badge></x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/v2/verify/pdf</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Verifikasi tanda tangan PDF</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="amber" size="sm">Verify</flux:badge></x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/v2/user/check/status</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Cek status sertifikat user</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">User</flux:badge></x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/v2/user/registration</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Registrasi user BSrE baru</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">User</flux:badge></x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/v2/seal/get/activation</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Aktivasi / Refresh TOTP Seal</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="purple" size="sm">Seal</flux:badge></x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/v2/seal/revoke/activation</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Cabut aktivasi seal</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="purple" size="sm">Seal</flux:badge></x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/v2/seal/get/totp</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Request OTP untuk seal</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="purple" size="sm">Seal</flux:badge></x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/v2/seal/pdf</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Segel dokumen PDF</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="purple" size="sm">Seal</flux:badge></x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>
        </div>
    </div>
@endif

{{-- ==================== AUTENTIKASI ==================== --}}
@if ($activeSection === 'auth')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="lock-closed" class="w-5 h-5 text-primary-500" />
            Autentikasi
        </h2>

        <div class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Semua request ke API ESign Client Service menggunakan <strong>HTTP Basic
                    Authentication</strong>.
                Kredensial (username & password) didapatkan dari halaman admin ESign Client Service
                (<code
                    class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">http://{IP-Server}/login</code>).
            </p>

            {{-- Env Variables --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Environment Variables</h4>
                <x-atoms.code-block language="bash"><span class="text-zinc-500"># Konfigurasi TTE / ESign Client Service (BSrE)</span>
<span class="text-amber-400">TTE_BASE_URL</span>=<span class="text-emerald-400">http://{IP-Server-ESign-Client}</span>
<span class="text-amber-400">TTE_USERNAME</span>=<span class="text-emerald-400">username_aplikasi</span>
<span class="text-amber-400">TTE_PASSWORD</span>=<span class="text-emerald-400">password_aplikasi</span></x-atoms.code-block>
            </div>

            {{-- Header --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Request Header</h4>
                <x-atoms.code-block language="http"><span class="text-blue-400">Authorization</span>: Basic <span class="text-amber-400">base64(username:password)</span>
<span class="text-blue-400">Accept</span>: <span class="text-emerald-400">application/json</span>
<span class="text-blue-400">Content-Type</span>: <span class="text-emerald-400">application/json</span></x-atoms.code-block>
            </div>

            {{-- Status konfigurasi --}}
            <div
                class="p-4 border rounded-lg {{ config('services.tte.base_url') ? 'bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800' }}">
                <div class="flex items-center gap-2">
                    @if (config('services.tte.base_url'))
                        <flux:icon name="check-circle" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        <span class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Konfigurasi
                            TTE sudah diatur</span>
                    @else
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                        <span class="text-sm font-medium text-amber-700 dark:text-amber-300">Konfigurasi
                            TTE belum diatur di .env</span>
                    @endif
                </div>
            </div>

            {{-- Whitelist --}}
            <div class="p-4 border rounded-lg bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
                <div class="flex gap-3">
                    <flux:icon name="information-circle"
                        class="flex-shrink-0 w-5 h-5 text-blue-600 dark:text-blue-400" />
                    <div class="text-sm text-blue-800 dark:text-blue-200">
                        <p class="font-medium mb-1">Alamat yang perlu di-whitelist:</p>
                        <ul class="ml-4 space-y-1 list-disc">
                            <li><code
                                    class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">https://api-bsre.bssn.go.id</code>
                                (production)</li>
                            <li><code
                                    class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">https://registry-bsre.bssn.go.id</code>
                                (development & production)</li>
                            <li><code
                                    class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">mail.bssn.go.id</code>
                                (pengiriman OTP via email)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ==================== SIGN PDF ==================== --}}
@if ($activeSection === 'sign')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/v2/sign/pdf</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Tanda Tangan PDF</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Membubuhkan tanda tangan elektronik pada dokumen PDF. Mendukung tanda tangan
            <strong>visible</strong> (dengan gambar/koordinat/tag) dan <strong>invisible</strong>, serta
            <strong>bulk signing</strong> (banyak dokumen sekaligus) dan <strong>PDF
                berpassword</strong>.
        </p>

        <div class="space-y-4">
            {{-- Info identitas & kredensial --}}
            <div class="p-4 border rounded-lg bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
                <div class="flex gap-3">
                    <flux:icon name="information-circle"
                        class="flex-shrink-0 w-5 h-5 text-blue-600 dark:text-blue-400" />
                    <div class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                        <p><strong>Identitas:</strong> gunakan salah satu dari <code
                                class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">nik</code>
                            atau <code
                                class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">email</code>.
                        </p>
                        <p><strong>Kredensial:</strong> gunakan salah satu dari <code
                                class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">passphrase</code>
                            atau <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">totp</code>.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Request Body --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Request Body</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Wajib</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">nik</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="amber" size="sm">*</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">NIK penanda tangan (16 digit). Wajib jika email tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">email</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="amber" size="sm">*</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Email terdaftar di BSrE. Wajib jika NIK tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">passphrase</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="amber" size="sm">*</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Passphrase sertifikat user. Wajib jika TOTP tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">totp</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="amber" size="sm">*</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">OTP dari endpoint request sign TOTP. Wajib jika passphrase tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">signatureProperties</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">array</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Array properti tanda tangan (1 atau sebanyak jumlah file)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">file</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">array</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Array file PDF dalam format Base64 string</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- Signature Properties --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Struktur signatureProperties[]</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">tampilan</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300"><code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">VISIBLE</code> atau <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">INVISIBLE</code> (wajib)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">imageBase64</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Gambar spesimen TTE dalam Base64 (.jpg/.jpeg/.png)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">page</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nomor halaman penempatan (wajib untuk VISIBLE dengan koordinat)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">originX</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">number</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Koordinat X (horizontal) posisi spesimen</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">originY</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">number</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Koordinat Y (vertikal) posisi spesimen</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">width</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">number</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Lebar spesimen dalam pixel</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">height</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">number</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Tinggi spesimen dalam pixel</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">tag_koordinat</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Karakter penanda posisi di dalam dokumen (misal: <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">#</code> atau <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">$</code>). Alternatif dari koordinat.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">location</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Lokasi penandatanganan (opsional)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">reason</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Alasan/catatan penandatanganan (opsional)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">pdfPassword</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Password jika PDF dikunci (opsional)</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- Contoh: Visible Koordinat --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh: Visible dengan Koordinat (Passphrase)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"nik"</span>: <span class="text-emerald-400">"3201234567890001"</span>,
<span class="text-blue-400">"passphrase"</span>: <span class="text-emerald-400">"secret_passphrase"</span>,
<span class="text-blue-400">"signatureProperties"</span>: [
{
<span class="text-blue-400">"tampilan"</span>: <span class="text-emerald-400">"VISIBLE"</span>,
<span class="text-blue-400">"imageBase64"</span>: <span class="text-emerald-400">"iVBORw0KGgoAAAA..."</span>,
<span class="text-blue-400">"page"</span>: <span class="text-amber-400">1</span>,
<span class="text-blue-400">"originX"</span>: <span class="text-amber-400">10</span>,
<span class="text-blue-400">"originY"</span>: <span class="text-amber-400">10</span>,
<span class="text-blue-400">"width"</span>: <span class="text-amber-400">200</span>,
<span class="text-blue-400">"height"</span>: <span class="text-amber-400">100</span>
}
],
<span class="text-blue-400">"file"</span>: [<span class="text-emerald-400">"JVBERi0xLjQKMSAw..."</span>]
}</x-atoms.code-block>
            </div>

            {{-- Contoh: Visible Tag Koordinat --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh: Visible dengan Tag Koordinat (OTP)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"nik"</span>: <span class="text-emerald-400">"3201234567890001"</span>,
<span class="text-blue-400">"totp"</span>: <span class="text-emerald-400">"123456"</span>,
<span class="text-blue-400">"signatureProperties"</span>: [
{
<span class="text-blue-400">"tampilan"</span>: <span class="text-emerald-400">"VISIBLE"</span>,
<span class="text-blue-400">"tag_koordinat"</span>: <span class="text-emerald-400">"$"</span>,
<span class="text-blue-400">"imageBase64"</span>: <span class="text-emerald-400">"iVBORw0KGgoAAAA..."</span>,
<span class="text-blue-400">"width"</span>: <span class="text-amber-400">200</span>,
<span class="text-blue-400">"height"</span>: <span class="text-amber-400">100</span>
}
],
<span class="text-blue-400">"file"</span>: [<span class="text-emerald-400">"JVBERi0xLjQKMSAw..."</span>]
}</x-atoms.code-block>
            </div>

            {{-- Contoh: Invisible --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh: Invisible (Email)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"email"</span>: <span class="text-emerald-400">"dokter@rs-example.go.id"</span>,
<span class="text-blue-400">"passphrase"</span>: <span class="text-emerald-400">"secret_passphrase"</span>,
<span class="text-blue-400">"signatureProperties"</span>: [
{
<span class="text-blue-400">"tampilan"</span>: <span class="text-emerald-400">"INVISIBLE"</span>
}
],
<span class="text-blue-400">"file"</span>: [<span class="text-emerald-400">"JVBERi0xLjQKMSAw..."</span>]
}</x-atoms.code-block>
            </div>

            {{-- Contoh: Bulk Signing --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh: Bulk Signing (Banyak File, Banyak Jenis)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"email"</span>: <span class="text-emerald-400">"dokter@rs-example.go.id"</span>,
<span class="text-blue-400">"totp"</span>: <span class="text-emerald-400">"123456"</span>,
<span class="text-blue-400">"signatureProperties"</span>: [
{
<span class="text-blue-400">"tampilan"</span>: <span class="text-emerald-400">"VISIBLE"</span>,
<span class="text-blue-400">"imageBase64"</span>: <span class="text-emerald-400">"iVBORw0KGgoAAAA..."</span>,
<span class="text-blue-400">"page"</span>: <span class="text-amber-400">1</span>,
<span class="text-blue-400">"originX"</span>: <span class="text-amber-400">10</span>, <span class="text-blue-400">"originY"</span>: <span class="text-amber-400">10</span>,
<span class="text-blue-400">"width"</span>: <span class="text-amber-400">200</span>, <span class="text-blue-400">"height"</span>: <span class="text-amber-400">100</span>,
<span class="text-blue-400">"location"</span>: <span class="text-emerald-400">"Jakarta"</span>,
<span class="text-blue-400">"reason"</span>: <span class="text-emerald-400">"Tanda tangan elektronik"</span>
},
{ <span class="text-blue-400">"tampilan"</span>: <span class="text-emerald-400">"INVISIBLE"</span> },
{
<span class="text-blue-400">"tampilan"</span>: <span class="text-emerald-400">"VISIBLE"</span>,
<span class="text-blue-400">"tag_koordinat"</span>: <span class="text-emerald-400">"$"</span>,
<span class="text-blue-400">"imageBase64"</span>: <span class="text-emerald-400">"iVBORw0KGgoAAAA..."</span>,
<span class="text-blue-400">"width"</span>: <span class="text-amber-400">120</span>, <span class="text-blue-400">"height"</span>: <span class="text-amber-400">40</span>
}
],
<span class="text-blue-400">"file"</span>: [
<span class="text-emerald-400">"JVBERi0xLjQ..."</span>,
<span class="text-emerald-400">"JVBERi0xLjQ..."</span>,
<span class="text-emerald-400">"JVBERi0xLjQ..."</span>
]
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== REQUEST SIGN TOTP ==================== --}}
@if ($activeSection === 'sign-totp')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/v2/sign/get/totp</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Request Sign TOTP</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Meminta kode OTP yang akan dikirimkan ke email terdaftar pengguna di BSrE.
            OTP ini digunakan sebagai kredensial alternatif pengganti passphrase saat menandatangani
            dokumen.
        </p>

        <div class="space-y-4">
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Request Body</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Wajib</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">nik</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="amber" size="sm">*</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">NIK pengguna. Wajib jika email tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">email</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="amber" size="sm">*</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Email terdaftar di BSrE. Wajib jika NIK tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">data</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Jumlah file yang akan ditandatangani pada endpoint Sign PDF</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"nik"</span>: <span class="text-emerald-400">"3201234567890001"</span>,
<span class="text-blue-400">"data"</span>: <span class="text-amber-400">2</span>
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== VERIFY PDF ==================== --}}
@if ($activeSection === 'verify')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="amber" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/v2/verify/pdf</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Verifikasi Tanda Tangan
            PDF</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Memverifikasi keaslian tanda tangan elektronik pada dokumen PDF. Mendukung dokumen
            berpassword.
            Mengembalikan informasi penanda tangan, waktu tanda tangan, validitas sertifikat, dan
            integritas dokumen.
        </p>

        <div class="space-y-4">
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Request Body</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Wajib</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">file</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">File PDF dalam format Base64 string</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">password</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Password dokumen jika PDF dikunci</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"file"</span>: <span class="text-emerald-400">"JVBERi0xLjQKMSAw..."</span>
}</x-atoms.code-block>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request (PDF Berpassword)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"file"</span>: <span class="text-emerald-400">"JVBERi0xLjQKMSAw..."</span>,
<span class="text-blue-400">"password"</span>: <span class="text-emerald-400">"pdf_password"</span>
}</x-atoms.code-block>
            </div>

            {{-- Contoh Response --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Response</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"conclusion"</span>: <span class="text-emerald-400">"TOTAL_PASSED"</span>,
<span class="text-blue-400">"description"</span>: <span class="text-emerald-400">"..."</span>,
<span class="text-blue-400">"signatureCount"</span>: <span class="text-amber-400">1</span>,
<span class="text-blue-400">"signatureInformations"</span>: [
{
<span class="text-blue-400">"signerName"</span>: <span class="text-emerald-400">"Dr. Budi Santoso"</span>,
<span class="text-blue-400">"signatureDate"</span>: <span class="text-emerald-400">"2026-01-15T10:30:00.000+00:00"</span>,
<span class="text-blue-400">"reason"</span>: <span class="text-emerald-400">"Dokumen telah disetujui"</span>,
<span class="text-blue-400">"location"</span>: <span class="text-emerald-400">"Jakarta"</span>,
<span class="text-blue-400">"integrityValid"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"certificateTrusted"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"certificateDetails"</span>: [<span class="text-zinc-500">...</span>],
<span class="text-blue-400">"timestampInfomation"</span>: {<span class="text-zinc-500">...</span>}
}
]
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== MANAJEMEN USER ==================== --}}
@if ($activeSection === 'user')
    {{-- Check Status --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="blue" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/v2/user/check/status</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Cek Status User</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Memeriksa status sertifikat elektronik pengguna di BSrE. Hanya user dengan status
            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">ISSUE</code>
            yang dapat melakukan tanda tangan elektronik.
        </p>

        <div class="space-y-4">
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Request Body</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Wajib</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">nik</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="amber" size="sm">*</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">NIK pengguna. Wajib jika email tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">email</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="amber" size="sm">*</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Email terdaftar di BSrE. Wajib jika NIK tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="http"><span class="text-zinc-500">// Menggunakan NIK</span>
{ <span class="text-blue-400">"nik"</span>: <span class="text-emerald-400">"3201234567890001"</span> }

<span class="text-zinc-500">// Atau menggunakan Email</span>
{ <span class="text-blue-400">"email"</span>: <span class="text-emerald-400">"dokter@rs-example.go.id"</span> }</x-atoms.code-block>
            </div>
        </div>
    </div>

    {{-- Registrasi --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="blue" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/v2/user/registration</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Registrasi User</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mendaftarkan pengguna baru yang belum memiliki akun BSrE.
        </p>

        <div class="space-y-4">
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Request Body</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Wajib</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">nama</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nama lengkap pengguna</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">email</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Email aktif pengguna</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"nama"</span>: <span class="text-emerald-400">"Dr. Budi Santoso"</span>,
<span class="text-blue-400">"email"</span>: <span class="text-emerald-400">"budi.santoso@rs-example.go.id"</span>
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== SEGEL ELEKTRONIK ==================== --}}
@if ($activeSection === 'seal')
    {{-- Info Box --}}
    <div class="p-4 border rounded-lg bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
        <div class="flex gap-3">
            <flux:icon name="information-circle" class="flex-shrink-0 w-5 h-5 text-blue-600 dark:text-blue-400" />
            <p class="text-sm text-blue-800 dark:text-blue-200">
                <strong>Segel Elektronik</strong> digunakan untuk menandatangani dokumen atas nama
                instansi/organisasi,
                berbeda dengan TTE personal. Memerlukan <code
                    class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">idSubscriber</code>
                yang didapatkan saat mendaftarkan instansi di BSrE.
            </p>
        </div>
    </div>

    {{-- Alur Proses --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h3 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="arrow-path" class="w-5 h-5 text-primary-500" />
            Alur Proses Segel Elektronik
        </h3>
        <div class="space-y-3">
            <div class="flex items-start gap-3">
                <div
                    class="flex items-center justify-center flex-shrink-0 w-8 h-8 text-sm font-bold rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                    1</div>
                <div>
                    <p class="font-medium text-zinc-900 dark:text-primary-dark-100">Aktivasi TOTP Seal (pertama
                        kali)</p>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400"><code
                            class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">POST
                            /api/v2/seal/get/activation</code> &mdash; OTP dikirim ke email</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <div
                    class="flex items-center justify-center flex-shrink-0 w-8 h-8 text-sm font-bold rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                    2</div>
                <div>
                    <p class="font-medium text-zinc-900 dark:text-primary-dark-100">Request OTP Seal</p>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400"><code
                            class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">POST
                            /api/v2/seal/get/totp</code> &mdash; OTP untuk proses seal</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <div
                    class="flex items-center justify-center flex-shrink-0 w-8 h-8 text-sm font-bold rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                    3</div>
                <div>
                    <p class="font-medium text-zinc-900 dark:text-primary-dark-100">Seal PDF</p>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400"><code
                            class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">POST
                            /api/v2/seal/pdf</code> &mdash; Bubuhkan segel pada dokumen</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Seal Get Activation --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="purple" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/v2/seal/get/activation</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Aktivasi / Refresh TOTP
            Seal</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Aktivasi pertama kali: kirim hanya <code
                class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">idSubscriber</code>,
            OTP dikirim ke email.
            Refresh/perpanjang: tambahkan parameter <code
                class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">totp</code>.
        </p>

        <div class="space-y-4">
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh: Aktivasi Pertama Kali</h4>
                <x-atoms.code-block language="json">{ <span class="text-blue-400">"idSubscriber"</span>: <span class="text-emerald-400">"SUB-001"</span> }</x-atoms.code-block>
            </div>
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response Aktivasi</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"TOTP Aktivasi berhasil dikirim"</span>
}</x-atoms.code-block>
            </div>
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh: Refresh Aktivasi</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"idSubscriber"</span>: <span class="text-emerald-400">"SUB-001"</span>,
<span class="text-blue-400">"totp"</span>: <span class="text-emerald-400">"123456"</span>
}</x-atoms.code-block>
            </div>
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response Refresh</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"data"</span>: {
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"TOTP Aktivasi masih berlaku"</span>,
<span class="text-blue-400">"totp"</span>: <span class="text-emerald-400">"123456"</span>,
<span class="text-blue-400">"expires"</span>: <span class="text-emerald-400">"2026-02-10T04:24:40.927+0000"</span>,
<span class="text-blue-400">"result"</span>: <span class="text-amber-400">true</span>
}
}</x-atoms.code-block>
            </div>
        </div>
    </div>

    {{-- Revoke Activation --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="purple" size="sm">POST</flux:badge>
            <code
                class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/v2/seal/revoke/activation</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Revoke Aktivasi Seal
        </h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">Mencabut aktivasi seal yang sedang
            aktif.</p>

        <div>
            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                Contoh Request</h4>
            <x-atoms.code-block language="json">{
<span class="text-blue-400">"idSubscriber"</span>: <span class="text-emerald-400">"SUB-001"</span>,
<span class="text-blue-400">"totp"</span>: <span class="text-emerald-400">"123456"</span>
}</x-atoms.code-block>
        </div>
    </div>

    {{-- Seal Get TOTP --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="purple" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/v2/seal/get/totp</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Request OTP Seal</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mendapatkan kode OTP untuk proses seal dokumen. OTP dari aktivasi seal diperlukan sebagai
            parameter.
        </p>

        <div class="space-y-4">
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Request Body</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">idSubscriber</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">ID subscriber instansi (wajib)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">data</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Jumlah file yang akan disegel (wajib)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">totp</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">TOTP dari aktivasi seal (wajib)</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"idSubscriber"</span>: <span class="text-emerald-400">"SUB-001"</span>,
<span class="text-blue-400">"data"</span>: <span class="text-amber-400">2</span>,
<span class="text-blue-400">"totp"</span>: <span class="text-emerald-400">"123456"</span>
}</x-atoms.code-block>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Response</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"TOTP berhasil dibuat"</span>,
<span class="text-blue-400">"totp"</span>: <span class="text-emerald-400">"654321"</span>,
<span class="text-blue-400">"expires"</span>: <span class="text-emerald-400">"2026-02-10T04:24:40.927+0000"</span>,
<span class="text-blue-400">"result"</span>: <span class="text-amber-400">true</span>
}</x-atoms.code-block>
            </div>
        </div>
    </div>

    {{-- Seal PDF --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="purple" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/v2/seal/pdf</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Seal Dokumen PDF</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Membubuhkan segel elektronik instansi pada dokumen PDF. Memerlukan OTP dari endpoint Request
            OTP Seal.
            Mendukung VISIBLE dan INVISIBLE. Response berisi file yang sudah disegel.
        </p>

        <div class="space-y-4">
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"idSubscriber"</span>: <span class="text-emerald-400">"SUB-001"</span>,
<span class="text-blue-400">"totp"</span>: <span class="text-emerald-400">"654321"</span>,
<span class="text-blue-400">"signatureProperties"</span>: [
{
<span class="text-blue-400">"tampilan"</span>: <span class="text-emerald-400">"INVISIBLE"</span>,
<span class="text-blue-400">"location"</span>: <span class="text-amber-400">null</span>,
<span class="text-blue-400">"reason"</span>: <span class="text-amber-400">null</span>
}
],
<span class="text-blue-400">"file"</span>: [<span class="text-emerald-400">"JVBERi0xLjQKMSAw..."</span>]
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== STATUS USER ==================== --}}
@if ($activeSection === 'status-user')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="information-circle" class="w-5 h-5 text-primary-500" />
            Daftar Status User BSrE
        </h2>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Response dari endpoint <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">Check
                Status
                User</code>
            akan mengembalikan salah satu status berikut:
        </p>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading>Status</x-atoms.table-heading>
                <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                <x-atoms.table-heading class="text-center">Bisa TTE?</x-atoms.table-heading>
            </x-slot:headings>

            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="green" size="sm">ISSUE</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Sertifikat Elektronik aktif</x-atoms.table-cell>
                <x-atoms.table-cell class="text-center"><flux:icon name="check" class="inline w-4 h-4 text-emerald-500" /></x-atoms.table-cell>
            </x-molecules.table-row>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="red" size="sm">EXPIRED</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Masa berlaku Sertifikat Elektronik telah berakhir</x-atoms.table-cell>
                <x-atoms.table-cell class="text-center"><flux:icon name="x-mark" class="inline w-4 h-4 text-red-500" /></x-atoms.table-cell>
            </x-molecules.table-row>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="amber" size="sm">RENEW</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Sertifikat sedang dalam proses pembaruan</x-atoms.table-cell>
                <x-atoms.table-cell class="text-center"><flux:icon name="x-mark" class="inline w-4 h-4 text-red-500" /></x-atoms.table-cell>
            </x-molecules.table-row>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="amber" size="sm">WAITING_FOR_VERIFICATION</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Sertifikat sedang dalam proses verifikasi</x-atoms.table-cell>
                <x-atoms.table-cell class="text-center"><flux:icon name="x-mark" class="inline w-4 h-4 text-red-500" /></x-atoms.table-cell>
            </x-molecules.table-row>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="blue" size="sm">NEW</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Memiliki Sertifikat namun belum melakukan aktivasi</x-atoms.table-cell>
                <x-atoms.table-cell class="text-center"><flux:icon name="x-mark" class="inline w-4 h-4 text-red-500" /></x-atoms.table-cell>
            </x-molecules.table-row>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="zinc" size="sm">NO_CERTIFICATE</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Sudah terdaftar namun belum memiliki Sertifikat Elektronik</x-atoms.table-cell>
                <x-atoms.table-cell class="text-center"><flux:icon name="x-mark" class="inline w-4 h-4 text-red-500" /></x-atoms.table-cell>
            </x-molecules.table-row>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="zinc" size="sm">NOT_REGISTERED</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Belum terdaftar dan belum memiliki Sertifikat Elektronik</x-atoms.table-cell>
                <x-atoms.table-cell class="text-center"><flux:icon name="x-mark" class="inline w-4 h-4 text-red-500" /></x-atoms.table-cell>
            </x-molecules.table-row>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="red" size="sm">SUSPEND</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Terdaftar namun dalam kondisi suspend</x-atoms.table-cell>
                <x-atoms.table-cell class="text-center"><flux:icon name="x-mark" class="inline w-4 h-4 text-red-500" /></x-atoms.table-cell>
            </x-molecules.table-row>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="red" size="sm">REVOKE</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Sertifikat Elektronik sudah dicabut</x-atoms.table-cell>
                <x-atoms.table-cell class="text-center"><flux:icon name="x-mark" class="inline w-4 h-4 text-red-500" /></x-atoms.table-cell>
            </x-molecules.table-row>
        </x-organisms.table>
    </div>

    {{-- Dukungan Teknis --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h3 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="phone" class="w-5 h-5 text-primary-500" />
            Dukungan Teknis BSrE
        </h3>
        <div class="space-y-3 text-sm">
            <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                <span class="text-zinc-500 dark:text-primary-dark-400">Instansi</span>
                <span class="font-medium text-zinc-900 dark:text-primary-dark-100">Balai Sertifikasi Elektronik
                    (BSrE) - BSSN</span>
            </div>
            <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                <span class="text-zinc-500 dark:text-primary-dark-400">Alamat</span>
                <span class="font-medium text-zinc-900 dark:text-primary-dark-100">Jl. Harsono RM No.70,
                    Ragunan, Jakarta Selatan</span>
            </div>
            <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                <span class="text-zinc-500 dark:text-primary-dark-400">Telegram</span>
                <span class="font-mono text-sm text-primary-600 dark:text-primary-400">https://t.me/infobsre</span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-zinc-500 dark:text-primary-dark-400">Email</span>
                <span class="font-mono text-sm text-primary-600 dark:text-primary-400">info.bsre@bssn.go.id</span>
            </div>
        </div>
    </div>
@endif

{{-- ==================== UNDUH DOKUMEN ==================== --}}
@if ($activeSection === 'download')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="arrow-down-tray" class="w-5 h-5 text-primary-500" />
            Unduh Dokumen
        </h2>

        <p class="mb-6 text-sm text-zinc-600 dark:text-primary-dark-300">
            Dokumen pendukung untuk integrasi API TTE (BSrE). Unduh petunjuk teknis dan Postman
            collection
            untuk mempermudah pengembangan.
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            {{-- Petunjuk Teknis PDF --}}
            <div
                class="flex flex-col justify-between p-5 border rounded-lg border-zinc-200 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-900/50">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <flux:icon name="document-text" class="w-8 h-8 text-red-500" />
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">Petunjuk
                                Teknis API</h4>
                            <p class="text-xs text-zinc-500 dark:text-primary-dark-400">ESign Client Service
                                v2.2.1</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-primary-dark-400">
                        Dokumentasi resmi lengkap dari BSrE mengenai penggunaan API ESign Client Service
                        versi 2.
                    </p>
                </div>
                <a href="{{ route('docs.download', ['filename' => 'Petunjuk Teknis API Esign Client Service v.2.2.1_sign.pdf']) }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 mt-4 text-sm font-medium text-white transition-colors rounded-lg bg-primary-600 hover:bg-primary-700">
                    <flux:icon name="arrow-down-tray" class="w-4 h-4" />
                    Unduh PDF
                </a>
            </div>

            {{-- Postman Collection --}}
            <div
                class="flex flex-col justify-between p-5 border rounded-lg border-zinc-200 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-900/50">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <flux:icon name="code-bracket" class="w-8 h-8 text-orange-500" />
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">Postman
                                Collection</h4>
                            <p class="text-xs text-zinc-500 dark:text-primary-dark-400">TTE API v2</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-primary-dark-400">
                        Koleksi request Postman untuk menguji seluruh endpoint API TTE. Import ke
                        Postman untuk memulai testing.
                    </p>
                </div>
                <a href="{{ route('docs.download', ['filename' => 'tte_v_2_api.postman_collection.json']) }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 mt-4 text-sm font-medium text-white transition-colors rounded-lg bg-primary-600 hover:bg-primary-700">
                    <flux:icon name="arrow-down-tray" class="w-4 h-4" />
                    Unduh Collection
                </a>
            </div>
        </div>
    </div>
@endif
