{{-- ==================== CEK KONEKSI SERVER ==================== --}}
@if ($activeSection === 'tte-status')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="blue" size="sm">GET</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/tte/status</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Cek Koneksi Server TTE</h3>
        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Memeriksa keterjangkauan dan autentikasi ke server ESign Client Service (BSrE).
            Mengukur latency koneksi dan mendeteksi apakah kredensial Basic Auth yang dikonfigurasi masih valid.
        </p>
        <div class="space-y-4">
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh
                    Request</h4>
                <x-atoms.code-block language="bash">curl -X GET {{ $appUrl }}/api/tte/status \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span></x-atoms.code-block>
            </div>
            <div>
                <h4 class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response Fields</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Field</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">
                            status
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <flux:badge size="sm" color="purple" inset="top bottom">string</flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-300">
                            Status integrasi (<code
                                class="text-xs bg-zinc-100 dark:bg-primary-dark-900 px-1 py-0.5 rounded">active</code>
                            / <code
                                class="text-xs bg-zinc-100 dark:bg-primary-dark-900 px-1 py-0.5 rounded">inactive</code>)
                        </x-atoms.table-cell>
                    </x-molecules.table-row>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">
                            mode
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <flux:badge size="sm" color="purple" inset="top bottom">string</flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-300">
                            Mode operasional (<code
                                class="text-xs bg-zinc-100 dark:bg-primary-dark-900 px-1 py-0.5 rounded">production</code>
                            / <code
                                class="text-xs bg-zinc-100 dark:bg-primary-dark-900 px-1 py-0.5 rounded">development</code>)
                        </x-atoms.table-cell>
                    </x-molecules.table-row>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">
                            client_version
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <flux:badge size="sm" color="purple" inset="top bottom">string</flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-300">
                            Versi ESign Client yang terpasang
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response Sukses (200)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"connected"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"authenticated"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"latency_ms"</span>: <span class="text-amber-400">42</span>,
<span class="text-blue-400">"status_code"</span>: <span class="text-amber-400">200</span>,
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Koneksi dan autentikasi berhasil."</span>
}</x-atoms.code-block>
            </div>
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response Gagal (503)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"connected"</span>: <span class="text-amber-400">false</span>,
<span class="text-blue-400">"authenticated"</span>: <span class="text-amber-400">false</span>,
<span class="text-blue-400">"latency_ms"</span>: <span class="text-amber-400">null</span>,
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Tidak dapat terhubung ke server TTE: ..."</span>
}</x-atoms.code-block>
            </div>
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Field</x-atoms.table-heading>
                    <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                    <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                </x-slot:headings>

                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">connected</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">boolean</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Server dapat dijangkau</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">authenticated</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">boolean</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Kredensial Basic Auth valid (bukan 401/403)</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">latency_ms</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer|null</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Latency dalam milidetik, null jika tidak terhubung</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">status_code</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer|null</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">HTTP status code dari server TTE</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">message</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Pesan status koneksi</x-atoms.table-cell>
                </x-molecules.table-row>
            </x-organisms.table>
        </div>
    </div>
@endif

{{-- ==================== STATISTIK HIT ==================== --}}
@if ($activeSection === 'tte-hits')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="blue" size="sm">GET</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/tte/hits</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Statistik Hit TTE</h3>
        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mengembalikan jumlah dokumen yang berhasil ditandatangani atau disegel, berdasarkan rentang waktu
            dan NIK pegawai (opsional).
        </p>
        <div class="space-y-4">
            {{-- Query Params --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Query
                    Parameters</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Wajib</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">mode</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">
                            Rentang waktu:
                            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">full</code>
                            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">today</code>
                            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">this_week</code>
                            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">this_month</code>
                            <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">this_year</code>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">nik</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Filter per NIK pegawai. Jika tidak diisi, menghitung semua pegawai.</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>
            {{-- Contoh Request --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh
                    Request</h4>
                <x-atoms.code-block language="bash">curl -X GET <span class="text-emerald-400">"{{ $appUrl }}/api/tte/hits?mode=this_month&nik=3201234567890001"</span> \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span></x-atoms.code-block>
            </div>
            {{-- Response: mode spesifik --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response — mode spesifik (today / this_week / this_month / this_year)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"mode"</span>: <span class="text-emerald-400">"this_month"</span>,
<span class="text-blue-400">"nik"</span>: <span class="text-emerald-400">"3201234567890001"</span>,
<span class="text-blue-400">"total"</span>: <span class="text-amber-400">89</span>,
<span class="text-blue-400">"sign_pdf"</span>: <span class="text-amber-400">60</span>,
<span class="text-blue-400">"seal_pdf"</span>: <span class="text-amber-400">29</span>
}</x-atoms.code-block>
            </div>
            {{-- Response: mode full --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response — mode full</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"mode"</span>: <span class="text-emerald-400">"full"</span>,
<span class="text-blue-400">"nik"</span>: <span class="text-amber-400">null</span>,
<span class="text-blue-400">"total"</span>:      { <span class="text-blue-400">"total"</span>: <span class="text-amber-400">1234</span>, <span class="text-blue-400">"sign_pdf"</span>: <span class="text-amber-400">800</span>, <span class="text-blue-400">"seal_pdf"</span>: <span class="text-amber-400">434</span> },
<span class="text-blue-400">"this_year"</span>:  { <span class="text-blue-400">"total"</span>: <span class="text-amber-400">320</span>,  <span class="text-blue-400">"sign_pdf"</span>: <span class="text-amber-400">210</span>, <span class="text-blue-400">"seal_pdf"</span>: <span class="text-amber-400">110</span> },
<span class="text-blue-400">"this_month"</span>: { <span class="text-blue-400">"total"</span>: <span class="text-amber-400">89</span>,   <span class="text-blue-400">"sign_pdf"</span>: <span class="text-amber-400">60</span>,  <span class="text-blue-400">"seal_pdf"</span>: <span class="text-amber-400">29</span>  },
<span class="text-blue-400">"this_week"</span>:  { <span class="text-blue-400">"total"</span>: <span class="text-amber-400">21</span>,   <span class="text-blue-400">"sign_pdf"</span>: <span class="text-amber-400">14</span>,  <span class="text-blue-400">"seal_pdf"</span>: <span class="text-amber-400">7</span>   },
<span class="text-blue-400">"today"</span>:      { <span class="text-blue-400">"total"</span>: <span class="text-amber-400">12</span>,   <span class="text-blue-400">"sign_pdf"</span>: <span class="text-amber-400">8</span>,   <span class="text-blue-400">"seal_pdf"</span>: <span class="text-amber-400">4</span>   }
}</x-atoms.code-block>
            </div>
            {{-- Response Fields --}}
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Field</x-atoms.table-heading>
                    <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                    <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                </x-slot:headings>

                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">mode</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Rentang waktu yang digunakan</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">nik</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string|null</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">NIK filter yang digunakan, null jika menghitung semua</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">total / this_year / this_month / this_week / today</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">
                        object <flux:badge color="amber" size="sm">full</flux:badge>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">
                        Breakdown per periode, masing-masing berisi 
                        <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-[10px]">total</code>,
                        <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-[10px]">sign_pdf</code>,
                        <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-[10px]">seal_pdf</code>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">total / sign_pdf / seal_pdf</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">
                        integer <flux:badge color="blue" size="sm">mode spesifik</flux:badge>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">
                        Jumlah flat untuk mode selain <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-[10px]">full</code>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            </x-organisms.table>
        </div>
    </div>
@endif

{{-- ==================== SIGN PDF ==================== --}}
@if ($activeSection === 'sign')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/tte/sign/pdf</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Tanda Tangan PDF</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Membubuhkan tanda tangan elektronik pada dokumen PDF. Mendukung tanda tangan
            <strong>visible</strong> (dengan gambar/koordinat/tag) dan <strong>invisible</strong>, serta
            <strong>bulk signing</strong> (banyak dokumen sekaligus).
        </p>

        <div class="space-y-4">
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
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">NIK penanda tangan. Wajib jika email tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">email</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="amber" size="sm">*</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Email BSrE. Wajib jika NIK tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">passphrase</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="amber" size="sm">*</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Passphrase sertifikat. Wajib jika TOTP tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">totp</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="amber" size="sm">*</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">OTP dari request sign TOTP. Wajib jika passphrase tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">signatureProperties</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">array</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Array properti tanda tangan</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">file</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">array</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Array file PDF dalam Base64</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- signatureProperties --}}
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
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Gambar spesimen TTE dalam Base64</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">page</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nomor halaman (wajib untuk VISIBLE dengan koordinat)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">originX / originY</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">number</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Koordinat posisi spesimen</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">width / height</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">number</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Ukuran spesimen dalam pixel</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">tag_koordinat</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Karakter penanda posisi (alternatif koordinat)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">location / reason</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Lokasi dan alasan penandatanganan (opsional)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">pdfPassword</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Password PDF (opsional)</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- Contoh Request --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/tte/sign/pdf \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{
"nik": "3201234567890001",
"passphrase": "secret_passphrase",
"signatureProperties": [{
"tampilan": "VISIBLE",
"imageBase64": "iVBORw0KGgoAAAA...",
"page": 1,
"originX": 10,
"originY": 10,
"width": 200,
"height": 100
}],
"file": ["JVBERi0xLjQKMSAw..."]
}'</span></x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== REQUEST SIGN TOTP ==================== --}}
@if ($activeSection === 'sign-totp')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/tte/sign/totp</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Request Sign TOTP</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Meminta kode OTP yang dikirimkan ke email pengguna BSrE. OTP digunakan sebagai kredensial
            alternatif pengganti passphrase saat menandatangani dokumen.
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
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Email BSrE. Wajib jika NIK tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">data</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Jumlah file yang akan ditandatangani (default: 1)</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/tte/sign/totp \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"nik": "3201234567890001", "data": 2}'</span></x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== VERIFY PDF ==================== --}}
@if ($activeSection === 'verify')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/tte/verify/pdf</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Verifikasi Tanda Tangan PDF
        </h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Memverifikasi keaslian tanda tangan elektronik pada dokumen PDF.
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
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">File PDF dalam Base64</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">password</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Password PDF jika dikunci</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/tte/verify/pdf \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"file": "JVBERi0xLjQKMSAw..."}'</span></x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== MANAJEMEN USER BSrE ==================== --}}
@if ($activeSection === 'user')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/tte/user/status</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Cek Status User</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Memeriksa status sertifikat elektronik pengguna di BSrE.
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
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Email BSrE. Wajib jika NIK tidak diisi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/tte/user/status \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"nik": "3201234567890001"}'</span></x-atoms.code-block>
            </div>
        </div>
    </div>

    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/tte/user/register</code>
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
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/tte/user/register \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"nama": "Dr. Budi Santoso", "email": "budi@rs-example.go.id"}'</span></x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== SEGEL ELEKTRONIK ==================== --}}
@if ($activeSection === 'seal')
    <div class="p-4 border rounded-lg bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
        <div class="flex gap-3">
            <flux:icon name="information-circle" class="flex-shrink-0 w-5 h-5 text-blue-600 dark:text-blue-400" />
            <p class="text-sm text-blue-800 dark:text-blue-200">
                <strong>Segel Elektronik</strong> digunakan untuk menandatangani dokumen atas nama
                instansi/organisasi.
                Memerlukan <code
                    class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">idSubscriber</code>
                dari BSrE.
            </p>
        </div>
    </div>

    {{-- Alur Proses --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h3 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="arrow-path" class="w-5 h-5 text-primary-500" />
            Alur Proses
        </h3>
        <div class="space-y-3">
            <div class="flex items-start gap-3">
                <div
                    class="flex items-center justify-center flex-shrink-0 w-8 h-8 text-sm font-bold rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                    1</div>
                <div>
                    <p class="font-medium text-zinc-900 dark:text-primary-dark-100">Aktivasi TOTP Seal</p>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400"><code
                            class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">POST
                            /api/tte/seal/activation</code></p>
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
                            /api/tte/seal/totp</code></p>
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
                            /api/tte/seal/pdf</code></p>
                </div>
            </div>
        </div>
    </div>

    {{-- Seal Activation --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/tte/seal/activation</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Aktivasi TOTP Seal</h3>
        <div>
            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                Contoh Request</h4>
            <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/tte/seal/activation \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"idSubscriber": "SUB-001"}'</span></x-atoms.code-block>
        </div>
    </div>

    {{-- Seal Refresh --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/tte/seal/refresh</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Refresh Aktivasi Seal</h3>
        <div>
            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                Contoh Request</h4>
            <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/tte/seal/refresh \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"idSubscriber": "SUB-001", "totp": "123456"}'</span></x-atoms.code-block>
        </div>
    </div>

    {{-- Seal Revoke --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/tte/seal/revoke</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Revoke Aktivasi Seal</h3>
        <div>
            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                Contoh Request</h4>
            <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/tte/seal/revoke \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"idSubscriber": "SUB-001", "totp": "123456"}'</span></x-atoms.code-block>
        </div>
    </div>

    {{-- Seal Get TOTP --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/tte/seal/totp</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Request OTP Seal</h3>
        <div class="space-y-4">
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Request Body</h4>
                <div class="overflow-x-auto">
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
            </div>
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/tte/seal/totp \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"idSubscriber": "SUB-001", "data": 2, "totp": "123456"}'</span></x-atoms.code-block>
            </div>
        </div>
    </div>

    {{-- Seal PDF --}}
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/tte/seal/pdf</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Seal Dokumen PDF</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Membubuhkan segel elektronik instansi pada dokumen PDF. Memerlukan OTP dari endpoint Request OTP
            Seal.
        </p>

        <div>
            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                Contoh Request</h4>
            <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/tte/seal/pdf \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{
"idSubscriber": "SUB-001",
"totp": "654321",
"signatureProperties": [{"tampilan": "INVISIBLE"}],
"file": ["JVBERi0xLjQKMSAw..."]
}'</span></x-atoms.code-block>
        </div>
    </div>
@endif
