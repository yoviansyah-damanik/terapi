{{-- ==================== GOWA: KIRIM PESAN ==================== --}}
@if ($activeSection === 'gowa-send')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="paper-airplane" class="w-5 h-5 text-primary-500" />
            GOWA: Kirim Pesan Teks
        </h2>

        <div class="space-y-4">
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900">
                <div class="flex items-center gap-3">
                    <span class="px-2 py-1 text-xs font-bold text-white bg-green-600 rounded">POST</span>
                    <code class="text-sm text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['whatsapp'] }}/gowa/send/message</code>
                </div>
            </div>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Body (JSON)</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Wajib</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">phone</x-atoms.table-cell>
                        <x-atoms.table-cell>string</x-atoms.table-cell>
                        <x-atoms.table-cell>Ya</x-atoms.table-cell>
                        <x-atoms.table-cell>Nomor tujuan (08xx/62xx)</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">message</x-atoms.table-cell>
                        <x-atoms.table-cell>string</x-atoms.table-cell>
                        <x-atoms.table-cell>Ya</x-atoms.table-cell>
                        <x-atoms.table-cell>Isi pesan teks</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['whatsapp'] }}/gowa/send/message \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"phone": "08123456789", "message": "Halo dari GOWA!"}'</span></x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== GOWA: KIRIM MEDIA ==================== --}}
@if ($activeSection === 'gowa-media')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="photo" class="w-5 h-5 text-primary-500" />
            GOWA: Kirim Media (Image, File, Video, Audio)
        </h2>

        <div class="space-y-6">
            {{-- Tabel endpoint --}}
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Method</x-atoms.table-heading>
                    <x-atoms.table-heading>Endpoint</x-atoms.table-heading>
                    <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                </x-slot:headings>

                <x-molecules.table-row>
                    <x-atoms.table-cell><span class="px-2 py-0.5 text-xs font-bold text-white bg-green-600 rounded">POST</span></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/{{ $activeVersions['whatsapp'] }}/gowa/send/image</x-atoms.table-cell>
                    <x-atoms.table-cell>Kirim gambar (base64)</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell><span class="px-2 py-0.5 text-xs font-bold text-white bg-green-600 rounded">POST</span></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/{{ $activeVersions['whatsapp'] }}/gowa/send/file</x-atoms.table-cell>
                    <x-atoms.table-cell>Kirim file/dokumen (base64)</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell><span class="px-2 py-0.5 text-xs font-bold text-white bg-green-600 rounded">POST</span></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/{{ $activeVersions['whatsapp'] }}/gowa/send/video</x-atoms.table-cell>
                    <x-atoms.table-cell>Kirim video (base64)</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell><span class="px-2 py-0.5 text-xs font-bold text-white bg-green-600 rounded">POST</span></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/{{ $activeVersions['whatsapp'] }}/gowa/send/audio</x-atoms.table-cell>
                    <x-atoms.table-cell>Kirim audio (base64)</x-atoms.table-cell>
                </x-molecules.table-row>
            </x-organisms.table>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Body (JSON) — Image/Video</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Wajib</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">phone</x-atoms.table-cell>
                        <x-atoms.table-cell>string</x-atoms.table-cell>
                        <x-atoms.table-cell>Ya</x-atoms.table-cell>
                        <x-atoms.table-cell>Nomor tujuan</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">image/video/file/audio</x-atoms.table-cell>
                        <x-atoms.table-cell>string</x-atoms.table-cell>
                        <x-atoms.table-cell>Ya</x-atoms.table-cell>
                        <x-atoms.table-cell>Data file dalam format base64</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">filename</x-atoms.table-cell>
                        <x-atoms.table-cell>string</x-atoms.table-cell>
                        <x-atoms.table-cell>Ya</x-atoms.table-cell>
                        <x-atoms.table-cell>Nama file</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">caption</x-atoms.table-cell>
                        <x-atoms.table-cell>string</x-atoms.table-cell>
                        <x-atoms.table-cell>Tidak</x-atoms.table-cell>
                        <x-atoms.table-cell>Caption (image/video saja)</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request (Image)</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['whatsapp'] }}/gowa/send/image \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"phone": "08123456789", "image": "iVBORw0KGgo...", "filename": "foto.jpg", "caption": "Foto pasien"}'</span></x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== GOWA: TIPE LAINNYA ==================== --}}
@if ($activeSection === 'gowa-other')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="squares-2x2" class="w-5 h-5 text-primary-500" />
            GOWA: Tipe Pesan Lainnya (Location, Contact, Link, Poll)
        </h2>

        <div class="space-y-6">
            {{-- Location --}}
            <div class="p-4 border rounded-lg border-zinc-200 dark:border-primary-dark-700">
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-2 py-1 text-xs font-bold text-white bg-green-600 rounded">POST</span>
                    <code class="text-sm text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['whatsapp'] }}/gowa/send/location</code>
                </div>
                <p class="mb-2 text-sm text-zinc-600 dark:text-primary-dark-400">Body: <code
                        class="text-xs bg-zinc-100 dark:bg-primary-dark-900 px-1 py-0.5 rounded">{"phone",
                        "latitude", "longitude"}</code></p>
            </div>

            {{-- Contact --}}
            <div class="p-4 border rounded-lg border-zinc-200 dark:border-primary-dark-700">
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-2 py-1 text-xs font-bold text-white bg-green-600 rounded">POST</span>
                    <code class="text-sm text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['whatsapp'] }}/gowa/send/contact</code>
                </div>
                <p class="mb-2 text-sm text-zinc-600 dark:text-primary-dark-400">Body: <code
                        class="text-xs bg-zinc-100 dark:bg-primary-dark-900 px-1 py-0.5 rounded">{"phone",
                        "contact_name", "contact_phone"}</code></p>
            </div>

            {{-- Link --}}
            <div class="p-4 border rounded-lg border-zinc-200 dark:border-primary-dark-700">
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-2 py-1 text-xs font-bold text-white bg-green-600 rounded">POST</span>
                    <code class="text-sm text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['whatsapp'] }}/gowa/send/link</code>
                </div>
                <p class="mb-2 text-sm text-zinc-600 dark:text-primary-dark-400">Body: <code
                        class="text-xs bg-zinc-100 dark:bg-primary-dark-900 px-1 py-0.5 rounded">{"phone",
                        "link",
                        "caption?"}</code></p>
            </div>

            {{-- Poll --}}
            <div class="p-4 border rounded-lg border-zinc-200 dark:border-primary-dark-700">
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-2 py-1 text-xs font-bold text-white bg-green-600 rounded">POST</span>
                    <code class="text-sm text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['whatsapp'] }}/gowa/send/poll</code>
                </div>
                <p class="mb-2 text-sm text-zinc-600 dark:text-primary-dark-400">Body: <code
                        class="text-xs bg-zinc-100 dark:bg-primary-dark-900 px-1 py-0.5 rounded">{"phone",
                        "question", "options[]", "max_answer"}</code></p>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request (Poll)</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['whatsapp'] }}/gowa/send/poll \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"phone": "08123456789", "question": "Pilih waktu konsultasi", "options": ["Pagi", "Siang", "Sore"], "max_answer": 1}'</span></x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== GOWA: STATUS & MANAJEMEN ==================== --}}
@if ($activeSection === 'gowa-status')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <h2 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
            <flux:icon name="signal" class="w-5 h-5 text-primary-500" />
            GOWA: Status & Manajemen Pesan
        </h2>

        <div class="space-y-4">
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Method</x-atoms.table-heading>
                    <x-atoms.table-heading>Endpoint</x-atoms.table-heading>
                    <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                </x-slot:headings>

                <x-molecules.table-row>
                    <x-atoms.table-cell><span class="px-2 py-0.5 text-xs font-bold text-white bg-blue-600 rounded">GET</span></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/{{ $activeVersions['whatsapp'] }}/gowa/status</x-atoms.table-cell>
                    <x-atoms.table-cell>Cek status koneksi GOWA</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell><span class="px-2 py-0.5 text-xs font-bold text-white bg-blue-600 rounded">GET</span></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/{{ $activeVersions['whatsapp'] }}/gowa/user/check?phone={phone}</x-atoms.table-cell>
                    <x-atoms.table-cell>Cek nomor terdaftar di WhatsApp</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell><span class="px-2 py-0.5 text-xs font-bold text-white bg-blue-600 rounded">GET</span></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/{{ $activeVersions['whatsapp'] }}/gowa/message/{id}</x-atoms.table-cell>
                    <x-atoms.table-cell>Cek status pesan</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell><span class="px-2 py-0.5 text-xs font-bold text-white bg-green-600 rounded">POST</span></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/{{ $activeVersions['whatsapp'] }}/gowa/message/{id}/revoke</x-atoms.table-cell>
                    <x-atoms.table-cell>Tarik pesan</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell><span class="px-2 py-0.5 text-xs font-bold text-white bg-green-600 rounded">POST</span></x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">/api/{{ $activeVersions['whatsapp'] }}/gowa/message/{id}/react</x-atoms.table-cell>
                    <x-atoms.table-cell>Kirim reaksi emoji</x-atoms.table-cell>
                </x-molecules.table-row>
            </x-organisms.table>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request (Cek Status)</h4>
                <x-atoms.code-block language="bash">curl -X GET {{ $appUrl }}/api/{{ $activeVersions['whatsapp'] }}/gowa/status \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span></x-atoms.code-block>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request (Cek Nomor)</h4>
                <x-atoms.code-block language="bash">curl -X GET "{{ $appUrl }}/api/{{ $activeVersions['whatsapp'] }}/gowa/user/check?phone=08123456789" \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span></x-atoms.code-block>
            </div>

            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request (React)</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['whatsapp'] }}/gowa/message/{id}/react \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{"emoji": "👍"}'</span></x-atoms.code-block>
            </div>
        </div>
    </div>
@endif
