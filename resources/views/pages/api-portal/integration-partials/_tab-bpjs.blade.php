{{-- ==================== OVERVIEW & AUTH ==================== --}}
@if ($activeSection === 'overview')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="heart" class="w-5 h-5 text-red-500" />
            BPJS Kesehatan — Overview
        </h2>
        <div class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Terapi mengintegrasikan beberapa layanan <strong>BPJS Kesehatan</strong> secara langsung:
                <strong>VClaim</strong> (SEP, peserta, rujukan), <strong>Antrian RS</strong>,
                <strong>eRM</strong> (rekam medis elektronik), dan <strong>Apotek Online</strong>.
                Setiap layanan memiliki <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">cons_id</code>,
                <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">secret_key</code>, dan
                <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">user_key</code> masing-masing dari BPJS.
            </p>

            {{-- Autentikasi HMAC --}}
            <div class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4 class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Autentikasi — HMAC-SHA256 (VClaim, eRM, Apotek)</h4>
                <p class="mb-3 text-xs text-zinc-600 dark:text-primary-dark-300">
                    Signature digenerate dari <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">HMAC-SHA256(cons_id&amp;timestamp, secret_key)</code>,
                    lalu di-base64-encode. Semua response dikembalikan terenkripsi AES-256-CBC dan perlu didekripsi.
                </p>
                <x-atoms.code-block language="http"><span class="text-blue-400">X-cons-id</span>: <span class="text-emerald-400">{cons_id}</span>
<span class="text-blue-400">X-timestamp</span>: <span class="text-emerald-400">{unix_timestamp}</span>
<span class="text-blue-400">X-signature</span>: <span class="text-emerald-400">base64(HMAC-SHA256("{cons_id}&amp;{timestamp}", secret_key))</span>
<span class="text-blue-400">user_key</span>: <span class="text-emerald-400">{user_key}</span>
<span class="text-blue-400">Content-Type</span>: <span class="text-emerald-400">application/json</span></x-atoms.code-block>
            </div>

            {{-- Response Format --}}
            <div class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4 class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Format Response</h4>
                <p class="mb-2 text-xs text-zinc-600 dark:text-primary-dark-300">
                    Field <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">response</code>
                    berisi string terenkripsi AES-256-CBC yang perlu didekripsi menggunakan
                    <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">SHA256(cons_id + secret_key + timestamp)</code>
                    sebagai kunci. Terapi menangani dekripsi ini secara otomatis.
                </p>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"metaData"</span>: {
  <span class="text-blue-400">"code"</span>: <span class="text-emerald-400">"200"</span>,
  <span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"OK"</span>
},
<span class="text-blue-400">"response"</span>: <span class="text-emerald-400">"&lt;encrypted_string&gt;"</span>
}</x-atoms.code-block>
            </div>

            {{-- Layanan & Kredensial --}}
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Layanan</x-atoms.table-heading>
                    <x-atoms.table-heading>Module</x-atoms.table-heading>
                    <x-atoms.table-heading>Auth Mechanism</x-atoms.table-heading>
                </x-slot:headings>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-medium text-zinc-700 dark:text-primary-dark-300">VClaim</x-atoms.table-cell>
                    <x-atoms.table-cell><code class="font-mono text-xs text-primary-600 dark:text-primary-400">bpjs.vclaim.*</code></x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">HMAC-SHA256</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-medium text-zinc-700 dark:text-primary-dark-300">Antrian RS</x-atoms.table-cell>
                    <x-atoms.table-cell><code class="font-mono text-xs text-primary-600 dark:text-primary-400">bpjs.antrian_rs.*</code></x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">Token (username/password → Bearer)</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-medium text-zinc-700 dark:text-primary-dark-300">eRM</x-atoms.table-cell>
                    <x-atoms.table-cell><code class="font-mono text-xs text-primary-600 dark:text-primary-400">bpjs.erm.*</code></x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">HMAC-SHA256 + enkripsi payload khusus</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-medium text-zinc-700 dark:text-primary-dark-300">Apotek Online</x-atoms.table-cell>
                    <x-atoms.table-cell><code class="font-mono text-xs text-primary-600 dark:text-primary-400">bpjs.apotek_online.*</code></x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">HMAC-SHA256</x-atoms.table-cell>
                </x-molecules.table-row>
            </x-organisms.table>
        </div>
    </div>
@endif

{{-- ==================== VCLAIM ==================== --}}
@if ($activeSection === 'vclaim')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-1 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="document-text" class="w-5 h-5 text-primary-500" />
            VClaim API
        </h2>
        <p class="mb-4 text-sm text-zinc-500 dark:text-primary-dark-400">
            Layanan VClaim untuk manajemen SEP, data peserta, dan rujukan BPJS. Auth: HMAC-SHA256.
        </p>

        <div class="space-y-5">
            {{-- Peserta --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Data Peserta</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Method</x-atoms.table-heading>
                        <x-atoms.table-heading>Endpoint</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/Peserta/nokartu/{noKartu}/tglSEP/{tglSep}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Data peserta berdasarkan nomor kartu</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/referensi/poli/{nama}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Referensi poli berdasarkan nama</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- SEP --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    SEP (Surat Eligibilitas Peserta)</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Method</x-atoms.table-heading>
                        <x-atoms.table-heading>Endpoint</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/SEP/2.0/insert</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Insert SEP baru (v2.0)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/RencanaKontrol/nosep/{noSep}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Cari SEP berdasarkan nomor SEP</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/RencanaKontrol/v2/Insert</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Insert rencana kontrol (v2)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/RencanaKontrol/InsertSPRI</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Insert SPRI (Surat Perintah Rawat Inap)</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- Rujukan --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Rujukan</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Method</x-atoms.table-heading>
                        <x-atoms.table-heading>Endpoint</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/Rujukan/insert</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Insert rujukan baru</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/Rujukan/{noRujukan}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Ambil data rujukan berdasarkan nomor</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- Catatan --}}
            <div class="p-3 border rounded-xl bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800">
                <div class="flex gap-2">
                    <flux:icon name="exclamation-triangle" class="flex-shrink-0 w-4 h-4 mt-0.5 text-amber-600 dark:text-amber-400" />
                    <p class="text-xs text-amber-800 dark:text-amber-200">
                        Semua response dari VClaim dikembalikan dalam format terenkripsi AES-256-CBC.
                        Terapi mendekripsi response secara otomatis sebelum dikembalikan ke caller.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ==================== ANTRIAN RS ==================== --}}
@if ($activeSection === 'antrian-rs')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-1 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="queue-list" class="w-5 h-5 text-primary-500" />
            Antrian RS
        </h2>
        <p class="mb-4 text-sm text-zinc-500 dark:text-primary-dark-400">
            Layanan antrian rumah sakit BPJS. Auth berbeda dari layanan lain — menggunakan
            username/password untuk mendapatkan token, token disimpan di cache selama 12 jam.
        </p>

        <div class="space-y-4">
            {{-- Auth flow --}}
            <div class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">Alur Autentikasi</h4>
                <div class="space-y-2">
                    <div class="flex items-start gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-6 h-6 mt-0.5 text-xs font-bold rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">1</div>
                        <p class="text-xs text-zinc-600 dark:text-primary-dark-300">
                            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">POST /auth</code> dengan header
                            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">x-username</code> dan
                            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">x-password</code>
                            → mendapatkan token
                        </p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-6 h-6 mt-0.5 text-xs font-bold rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">2</div>
                        <p class="text-xs text-zinc-600 dark:text-primary-dark-300">
                            Token di-cache selama 12 jam. Request berikutnya menggunakan header
                            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">x-token</code>
                        </p>
                    </div>
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
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/auth</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Mendapatkan token autentikasi</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/ambilantrean</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Ambil data antrian pasien</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/checkinantrean</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Check in antrian pasien</x-atoms.table-cell>
                </x-molecules.table-row>
            </x-organisms.table>
        </div>
    </div>
@endif

{{-- ==================== eRM BPJS ==================== --}}
@if ($activeSection === 'erm')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-1 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="clipboard-document-check" class="w-5 h-5 text-primary-500" />
            eRM BPJS — Rekam Medis Elektronik
        </h2>
        <p class="mb-4 text-sm text-zinc-500 dark:text-primary-dark-400">
            Layanan pengiriman data rekam medis elektronik ke BPJS. Payload dienkripsi secara khusus
            sebelum dikirim — beda dari layanan BPJS lainnya.
        </p>

        <div class="space-y-4">
            {{-- Enkripsi payload --}}
            <div class="p-4 border rounded-xl bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
                <div class="flex gap-3">
                    <flux:icon name="information-circle" class="flex-shrink-0 w-5 h-5 text-blue-600 dark:text-blue-400" />
                    <div class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                        <p class="font-medium">Enkripsi Payload (khusus eRM)</p>
                        <p class="text-xs">
                            Alur: <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50">JSON string</code>
                            → <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50">gzencode</code>
                            → <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50">base64</code>
                            → <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50">AES-256-CBC</code>
                            → <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50">base64</code>
                        </p>
                        <p class="text-xs">
                            Kunci enkripsi: <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50">SHA256(cons_id + secret_key + kode_ppk)</code>.
                            IV: 16 byte pertama dari kunci. Request dikirim dengan
                            <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50">Content-Type: text/plain</code>.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Endpoint --}}
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Method</x-atoms.table-heading>
                    <x-atoms.table-heading>Endpoint</x-atoms.table-heading>
                    <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                </x-slot:headings>
                <x-molecules.table-row>
                    <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/eclaim/rekammedis/insert</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Insert data rekam medis elektronik (payload terenkripsi, Content-Type: text/plain)</x-atoms.table-cell>
                </x-molecules.table-row>
            </x-organisms.table>

            {{-- Contoh Response --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">Contoh Response Sukses</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"metaData"</span>: {
  <span class="text-blue-400">"code"</span>: <span class="text-emerald-400">"200"</span>,
  <span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"OK"</span>
},
<span class="text-blue-400">"response"</span>: {
  <span class="text-blue-400">"result"</span>: <span class="text-amber-400">true</span>,
  <span class="text-blue-400">"noSep"</span>: <span class="text-emerald-400">"1234567890123-01"</span>,
  <span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Data berhasil disimpan"</span>
}
}</x-atoms.code-block>
            </div>

            {{-- Catatan validator --}}
            <div class="p-3 border rounded-xl bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800">
                <div class="flex gap-2">
                    <flux:icon name="exclamation-triangle" class="flex-shrink-0 w-4 h-4 mt-0.5 text-amber-600 dark:text-amber-400" />
                    <p class="text-xs text-amber-800 dark:text-amber-200">
                        Sebelum mengirim eRM, Terapi menjalankan <strong>ErmValidator</strong> untuk memeriksa kelengkapan data:
                        SEP aktif, mapping ICD-10/ICD-9, prosedur tindakan, data obat, lab, dan radiologi.
                        Error validasi (SEP, ICD, prosedur) mencegah pengiriman. Warning (obat, lab, rad) hanya notifikasi.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ==================== APOTEK ONLINE ==================== --}}
@if ($activeSection === 'apotek')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-1 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="beaker" class="w-5 h-5 text-primary-500" />
            Apotek Online
        </h2>
        <p class="mb-4 text-sm text-zinc-500 dark:text-primary-dark-400">
            Layanan Apotek Online BPJS untuk pengelolaan obat, resep, dan pelayanan farmasi.
            Auth: HMAC-SHA256 (sama dengan VClaim).
        </p>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading>Method</x-atoms.table-heading>
                <x-atoms.table-heading>Endpoint</x-atoms.table-heading>
                <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
            </x-slot:headings>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/referensi/dpho</x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Referensi DPHO (Daftar dan Plafon Harga Obat)</x-atoms.table-cell>
            </x-molecules.table-row>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/referensi/obat/{kdJnsobat}/{pgAwal}/{pgAkhir}</x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Referensi obat dengan paginasi</x-atoms.table-cell>
            </x-molecules.table-row>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/obatnonracikan/v3/insert</x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Simpan obat non racikan (v3)</x-atoms.table-cell>
            </x-molecules.table-row>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/obatracikan/v3/insert</x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Simpan obat racikan (v3)</x-atoms.table-cell>
            </x-molecules.table-row>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/obat/daftar/{bulan}</x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Daftar pelayanan obat per bulan</x-atoms.table-cell>
            </x-molecules.table-row>
            <x-molecules.table-row>
                <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/daftarresep</x-atoms.table-cell>
                <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Daftar resep</x-atoms.table-cell>
            </x-molecules.table-row>
        </x-organisms.table>
    </div>
@endif
