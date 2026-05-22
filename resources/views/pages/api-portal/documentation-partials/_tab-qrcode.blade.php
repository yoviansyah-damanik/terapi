{{-- ==================== GENERATE QR CODE ==================== --}}
@if ($activeSection === 'qrcode-generate')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/qrcode/generate</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Generate QR Code</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Membuat gambar QR Code dari teks atau URL yang diberikan. Mendukung kustomisasi warna,
            ukuran,
            margin, dan logo di tengah QR Code. Nilai default diambil dari konfigurasi sistem jika
            parameter opsional tidak diisi.
        </p>

        <div class="space-y-4">
            {{-- Request Body --}}
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Request Body</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Wajib</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">content</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Konten QR Code (teks, URL, dll)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">image</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">object</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Konfigurasi logo di tengah QR Code</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">image.base64</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Gambar logo dalam Base64 (PNG atau JPG). Jika tidak diisi, menggunakan logo dari konfigurasi sistem.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">image.size</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Lebar logo dalam pixel (1–500). Default dari konfigurasi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">qrCode</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">object</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Konfigurasi tampilan QR Code</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">qrCode.color</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Warna modul QR (hex, mis. <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">#000000</code>). Default dari konfigurasi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">qrCode.backgroundColor</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Warna latar belakang (hex). Default dari konfigurasi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">qrCode.margin</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Jarak tepi dalam pixel (0–100). Default dari konfigurasi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">qrCode.size</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Ukuran gambar dalam pixel (50–2000). Default dari konfigurasi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- Contoh Request Minimal --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request — Minimal</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/qrcode/generate \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"content": "https://example.com"}'</span></x-atoms.code-block>
            </div>

            {{-- Contoh Request Lengkap --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request — Lengkap</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/qrcode/generate \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{
"content": "https://example.com",
"image": {
"base64": "iVBORw0KGgoAAAA...",
"size": 60
},
"qrCode": {
"color": "#1e3a5f",
"backgroundColor": "#FFFFFF",
"margin": 10,
"size": 300
}
}'</span></x-atoms.code-block>
            </div>

            {{-- Response --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response (200)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"image"</span>: <span class="text-emerald-400">"iVBORw0KGgoAAAANSUhEUgAA..."</span>,
<span class="text-blue-400">"mime"</span>: <span class="text-emerald-400">"image/png"</span>
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
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">success</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">boolean</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Status keberhasilan generate</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">image</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Gambar QR Code hasil generate dalam format Base64 PNG</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">mime</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">MIME type gambar hasil (<code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">image/png</code>)</x-atoms.table-cell>
                </x-molecules.table-row>
            </x-organisms.table>

            {{-- Info penggunaan base64 --}}
            <div class="p-4 border rounded-lg bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
                <div class="flex gap-3">
                    <flux:icon name="information-circle"
                        class="flex-shrink-0 w-5 h-5 text-blue-600 dark:text-blue-400" />
                    <div class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                        <p>Untuk menampilkan gambar hasil, gunakan data URI pada tag <code
                                class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">&lt;img&gt;</code>:
                        </p>
                        <code
                            class="block px-2 py-1.5 mt-1 text-xs rounded bg-blue-100 dark:bg-blue-900/50 font-mono break-all">&lt;img
                            src="data:image/png;base64,{image}" /&gt;</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
