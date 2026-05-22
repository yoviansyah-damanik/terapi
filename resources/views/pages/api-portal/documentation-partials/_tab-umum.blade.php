{{-- ==================== OVERVIEW ==================== --}}
@if ($activeSection === 'overview')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="book-open" class="w-5 h-5 text-primary-500" />
            Overview
        </h2>

        <div class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
                API ini menyediakan endpoint <strong>WhatsApp Gateway (WAHA)</strong>, <strong>GOWA
                    Gateway</strong>, dan <strong>TTE (Tanda Tangan Elektronik)</strong>
                yang dapat dipanggil dari aplikasi eksternal seperti <strong>SIMRS</strong>,
                <strong>RME</strong>, dan
                sistem lainnya melalui aplikasi {{ config('app.alias_name') }}.
            </p>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Semua endpoint menggunakan format <strong>JSON</strong> dan autentikasi <strong>Bearer
                    Token</strong>.
                User API dan token dikelola melalui menu
                <a href="{{ route('configuration.api-access') }}"
                    class="text-primary-600 dark:text-primary-400 hover:underline" wire:navigate>Pengaturan
                    &gt; Akses API</a>.
            </p>

            {{-- Base URL --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Base URL</h4>
                <code
                    class="block px-3 py-2 text-sm font-mono rounded bg-zinc-900 dark:bg-primary-dark-950 text-emerald-400">
                    {{ $appUrl }}/api
                </code>
            </div>

            {{-- Daftar Endpoint --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Daftar Endpoint</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Method</x-atoms.table-heading>
                        <x-atoms.table-heading>Endpoint</x-atoms.table-heading>
                        <x-atoms.table-heading>Deskripsi</x-atoms.table-heading>
                        <x-atoms.table-heading>Auth</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/auth/token</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Dapatkan token akses</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-50">Header</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">DELETE</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/auth/token</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Cabut token akses</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>

                    <x-molecules.table-row class="bg-zinc-50/50 dark:bg-primary-dark-800/50">
                        <x-atoms.table-cell colspan="4" class="text-xs font-semibold tracking-wider uppercase text-zinc-400 dark:text-primary-dark-500">
                            Informasi Rumah Sakit
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/hospital</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Identitas & lokasi rumah sakit</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/hospital/service</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Informasi layanan & versi sistem</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>

                    <x-molecules.table-row class="bg-zinc-50/50 dark:bg-primary-dark-800/50">
                        <x-atoms.table-cell colspan="4" class="text-xs font-semibold tracking-wider uppercase text-zinc-400 dark:text-primary-dark-500">
                            WhatsApp Gateway
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/whatsapp/send/text</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Kirim pesan teks</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/whatsapp/send/image</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Kirim gambar</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/whatsapp/send/file</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Kirim file/dokumen</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/whatsapp/status</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Cek status koneksi WhatsApp</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/whatsapp/message/{id}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Cek status pesan</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>

                    <x-molecules.table-row class="bg-zinc-50/50 dark:bg-primary-dark-800/50">
                        <x-atoms.table-cell colspan="4" class="text-xs font-semibold tracking-wider uppercase text-zinc-400 dark:text-primary-dark-500">
                            GOWA Gateway
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/gowa/send/message</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Kirim pesan teks</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/gowa/send/{type}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Kirim media (image/file/video/audio/location/contact/link/poll)</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/gowa/status</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Cek status koneksi GOWA</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/gowa/user/check</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Cek nomor WhatsApp</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/gowa/message/{id}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Cek status pesan</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/gowa/message/{id}/revoke</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Tarik pesan</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/gowa/message/{id}/react</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Kirim reaksi emoji</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>

                    <x-molecules.table-row class="bg-zinc-50/50 dark:bg-primary-dark-800/50">
                        <x-atoms.table-cell colspan="4" class="text-xs font-semibold tracking-wider uppercase text-zinc-400 dark:text-primary-dark-500">
                            TTE & Segel Elektronik
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/tte/sign/pdf</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Tanda tangan PDF</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/tte/sign/totp</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Request OTP untuk sign</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/tte/verify/pdf</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Verifikasi tanda tangan PDF</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/tte/user/status</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Cek status user BSrE</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/tte/user/register</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Registrasi user BSrE</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/tte/seal/activation</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Aktivasi TOTP Seal</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/tte/seal/refresh</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Refresh aktivasi seal</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/tte/seal/revoke</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Cabut aktivasi seal</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/tte/seal/totp</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Request OTP untuk seal</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/tte/seal/pdf</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Segel dokumen PDF</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>

                    <x-molecules.table-row class="bg-zinc-50/50 dark:bg-primary-dark-800/50">
                        <x-atoms.table-cell colspan="4" class="text-xs font-semibold tracking-wider uppercase text-zinc-400 dark:text-primary-dark-500">
                            Log SIMRS
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/simrs/log</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Kirim satu entri log</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="green" size="sm">POST</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/simrs/log/batch</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Kirim banyak log sekaligus</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/simrs/logs</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Ambil daftar log (paginate)</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell><flux:badge color="blue" size="sm">GET</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/simrs/logs/{id}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Ambil detail satu log</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">Bearer</x-atoms.table-cell>
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
                API menggunakan <strong>Bearer Token</strong> untuk autentikasi. Token didapatkan dengan
                mengirimkan
                kredensial melalui header <code
                    class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">x-username</code>
                dan <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">x-password</code>.
                User API dan token dikelola melalui menu
                <a href="{{ route('configuration.api-access') }}"
                    class="text-primary-600 dark:text-primary-400 hover:underline" wire:navigate>Pengaturan
                    &gt; Akses API</a>.
            </p>

            {{-- Langkah 1: Dapatkan Token --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Langkah 1: Dapatkan Token</h4>
                <div class="flex items-center gap-2 mb-2">
                    <flux:badge color="green" size="sm">POST</flux:badge>
                    <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/auth/token</code>
                </div>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/auth/token \
-H <span class="text-emerald-400">"x-username: username_api"</span> \
-H <span class="text-emerald-400">"x-password: password_api"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"name": "Token SIMRS", "expires_in_hours": 24}'</span></x-atoms.code-block>
            </div>

            {{-- Response Token --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response (200)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Token berhasil dibuat"</span>,
<span class="text-blue-400">"data"</span>: {
<span class="text-blue-400">"token"</span>: <span class="text-emerald-400">"a1b2c3d4e5f6..."</span>,
<span class="text-blue-400">"name"</span>: <span class="text-emerald-400">"Token SIMRS"</span>,
<span class="text-blue-400">"expires_at"</span>: <span class="text-emerald-400">"2026-02-09T10:00:00+07:00"</span>
}
}</x-atoms.code-block>
            </div>

            {{-- Langkah 2: Gunakan Token --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Langkah 2: Gunakan Token pada Setiap Request</h4>
                <x-atoms.code-block language="http"><span class="text-blue-400">Authorization</span>: Bearer <span class="text-amber-400">{token}</span>
<span class="text-blue-400">Accept</span>: <span class="text-emerald-400">application/json</span>
<span class="text-blue-400">Content-Type</span>: <span class="text-emerald-400">application/json</span></x-atoms.code-block>
            </div>

            {{-- Cabut Token --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Cabut Token (Opsional)</h4>
                <div class="flex items-center gap-2 mb-2">
                    <flux:badge color="red" size="sm">DELETE</flux:badge>
                    <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/auth/token</code>
                </div>
                <x-atoms.code-block language="bash">curl -X DELETE {{ $appUrl }}/api/auth/token \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span></x-atoms.code-block>
            </div>

            {{-- Parameter createToken --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Parameter Create Token (Opsional, body JSON)</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">name</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Label/nama token (default: "Token {tanggal}")</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">expires_in_hours</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Masa berlaku dalam jam (default: tidak kedaluwarsa)</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- Error Response --}}
            <div class="p-4 border rounded-lg bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-800">
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-red-600 dark:text-red-400">
                    Response Error (401)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">false</span>,
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Username atau password salah"</span>
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif
