{{-- ==================== WA: KIRIM TEKS ==================== --}}
@if ($activeSection === 'wa-send-text')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['whatsapp'] }}/whatsapp/send/text</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Kirim Pesan Teks</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mengirim pesan teks WhatsApp ke nomor tujuan. Pesan dikirim langsung (synchronous) dan
            status
            pengiriman dikembalikan dalam response.
        </p>

        <div class="space-y-4">
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
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">phone</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nomor tujuan (format: 08xx atau 628xx). Min 10, max 15 karakter.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">message</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Isi pesan teks. Maksimal 4096 karakter.</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['whatsapp'] }}/whatsapp/send/text \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{
"phone": "08123456789",
"message": "Halo, ini pesan dari SIMRS."
}'</span></x-atoms.code-block>
            </div>

            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response Sukses (200)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Pesan berhasil dikirim"</span>,
<span class="text-blue-400">"data"</span>: {
<span class="text-blue-400">"id"</span>: <span class="text-emerald-400">"9e1a2b3c-..."</span>,
<span class="text-blue-400">"phone"</span>: <span class="text-emerald-400">"08123456789"</span>,
<span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"sent"</span>,
<span class="text-blue-400">"sent_at"</span>: <span class="text-emerald-400">"2026-02-10T10:30:00+07:00"</span>
}
}</x-atoms.code-block>
            </div>

            <div class="p-4 border rounded-lg bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-800">
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-red-600 dark:text-red-400">
                    Response Gagal (502)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">false</span>,
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Gagal mengirim pesan"</span>,
<span class="text-blue-400">"data"</span>: {
<span class="text-blue-400">"id"</span>: <span class="text-emerald-400">"9e1a2b3c-..."</span>,
<span class="text-blue-400">"phone"</span>: <span class="text-emerald-400">"08123456789"</span>,
<span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"failed"</span>
}
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== WA: KIRIM GAMBAR ==================== --}}
@if ($activeSection === 'wa-send-image')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['whatsapp'] }}/whatsapp/send/image</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Kirim Gambar</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mengirim gambar beserta caption (opsional) ke nomor WhatsApp tujuan. Gambar dikirim dalam
            format
            Base64.
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
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">phone</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nomor tujuan (format: 08xx atau 628xx)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">image</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Data gambar dalam Base64 (tanpa prefix <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">data:image/...</code>)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">filename</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nama file gambar (contoh: foto.jpg)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">caption</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Keterangan gambar (opsional)</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['whatsapp'] }}/whatsapp/send/image \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{
"phone": "08123456789",
"image": "iVBORw0KGgoAAAA...",
"filename": "hasil-lab.png",
"caption": "Hasil laboratorium pasien"
}'</span></x-atoms.code-block>
            </div>

            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response Sukses (200)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Gambar berhasil dikirim"</span>,
<span class="text-blue-400">"data"</span>: {
<span class="text-blue-400">"id"</span>: <span class="text-emerald-400">"9e1a2b3c-..."</span>,
<span class="text-blue-400">"phone"</span>: <span class="text-emerald-400">"08123456789"</span>,
<span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"sent"</span>,
<span class="text-blue-400">"sent_at"</span>: <span class="text-emerald-400">"2026-02-10T10:30:00+07:00"</span>
}
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== WA: KIRIM FILE ==================== --}}
@if ($activeSection === 'wa-send-file')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['whatsapp'] }}/whatsapp/send/file</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Kirim File / Dokumen
        </h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mengirim file atau dokumen (PDF, DOCX, dll) ke nomor WhatsApp tujuan. File dikirim dalam
            format
            Base64.
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
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">phone</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nomor tujuan (format: 08xx atau 628xx)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">file</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Data file dalam Base64</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">filename</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nama file (contoh: laporan.pdf)</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['whatsapp'] }}/whatsapp/send/file \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{
"phone": "08123456789",
"file": "JVBERi0xLjQKMSAw...",
"filename": "surat-keterangan.pdf"
}'</span></x-atoms.code-block>
            </div>

            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response Sukses (200)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"File berhasil dikirim"</span>,
<span class="text-blue-400">"data"</span>: {
<span class="text-blue-400">"id"</span>: <span class="text-emerald-400">"9e1a2b3c-..."</span>,
<span class="text-blue-400">"phone"</span>: <span class="text-emerald-400">"08123456789"</span>,
<span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"sent"</span>,
<span class="text-blue-400">"sent_at"</span>: <span class="text-emerald-400">"2026-02-10T10:30:00+07:00"</span>
}
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== WA: STATUS & CEK PESAN ==================== --}}
@if ($activeSection === 'wa-status')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="blue" size="sm">GET</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['whatsapp'] }}/whatsapp/status</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Cek Status Koneksi</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Memeriksa status koneksi WhatsApp session. Gunakan endpoint ini untuk memastikan WhatsApp
            terhubung
            sebelum mengirim pesan.
        </p>

        <div class="space-y-4">
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X GET {{ $appUrl }}/api/{{ $activeVersions['whatsapp'] }}/whatsapp/status \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span></x-atoms.code-block>
            </div>

            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response (200)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"data"</span>: {
<span class="text-blue-400">"session"</span>: <span class="text-emerald-400">"default"</span>,
<span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"WORKING"</span>,
<span class="text-blue-400">"connected"</span>: <span class="text-amber-400">true</span>
}
}</x-atoms.code-block>
            </div>

            <div class="p-4 border rounded-lg bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
                <div class="flex gap-3">
                    <flux:icon name="information-circle"
                        class="flex-shrink-0 w-5 h-5 text-blue-600 dark:text-blue-400" />
                    <div class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                        <p><strong>Kemungkinan nilai <code
                                    class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">status</code>:</strong>
                        </p>
                        <ul class="ml-4 list-disc space-y-0.5">
                            <li><code
                                    class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">WORKING</code>
                                — Terhubung dan siap kirim pesan</li>
                            <li><code
                                    class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">SCAN_QR_CODE</code>
                                — Menunggu scan QR Code</li>
                            <li><code
                                    class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">STARTING</code>
                                — Session sedang dimulai</li>
                            <li><code
                                    class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-xs">STOPPED</code>
                                — Session dihentikan</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="blue" size="sm">GET</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['whatsapp'] }}/whatsapp/message/{id}</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Cek Status Pesan</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Memeriksa status pengiriman pesan berdasarkan ID yang didapat dari response endpoint kirim
            pesan.
        </p>

        <div class="space-y-4">
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X GET {{ $appUrl }}/api/{{ $activeVersions['whatsapp'] }}/whatsapp/message/9e1a2b3c-... \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span></x-atoms.code-block>
            </div>

            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response (200)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"data"</span>: {
<span class="text-blue-400">"id"</span>: <span class="text-emerald-400">"9e1a2b3c-..."</span>,
<span class="text-blue-400">"phone"</span>: <span class="text-emerald-400">"08123456789"</span>,
<span class="text-blue-400">"type"</span>: <span class="text-emerald-400">"text"</span>,
<span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"sent"</span>,
<span class="text-blue-400">"error_message"</span>: <span class="text-amber-400">null</span>,
<span class="text-blue-400">"sent_at"</span>: <span class="text-emerald-400">"2026-02-10T10:30:00+07:00"</span>,
<span class="text-blue-400">"created_at"</span>: <span class="text-emerald-400">"2026-02-10T10:29:58+07:00"</span>
}
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif
