{{-- SIMRS Update: Overview --}}
@if ($activeSection === 'simrs-update-overview')
    <div id="simrs-update" class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-zinc-900 dark:text-primary-dark-100">API SIMRS Update</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-primary-dark-400">
                Endpoint untuk distribusi update otomatis ke aplikasi SIMRS — cek versi, unduh file, dan
                laporan hasil update.
            </p>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
            <div
                class="bg-zinc-50 dark:bg-primary-dark-700/50 px-4 py-3 border-b border-zinc-200 dark:border-primary-dark-700">
                <p class="text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 uppercase tracking-wider">
                    Alur Update Otomatis</p>
            </div>
            <div class="p-4 space-y-2 text-sm text-zinc-600 dark:text-primary-dark-400">
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/40 text-[10px] font-bold text-primary-700 dark:text-primary-300 mt-0.5">1</span>
                    <p>SIMRS mengirim request <code
                            class="bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded text-xs">GET
                            /api/simrs/version</code> untuk mengecek apakah ada versi baru.</p>
                </div>
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/40 text-[10px] font-bold text-primary-700 dark:text-primary-300 mt-0.5">2</span>
                    <p>Jika versi berbeda, SIMRS mengunduh file dengan <code
                            class="bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded text-xs">GET
                            /api/simrs/download/{version}</code>.</p>
                </div>
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/40 text-[10px] font-bold text-primary-700 dark:text-primary-300 mt-0.5">3</span>
                    <p>SIMRS memverifikasi checksum SHA-256, menjalankan update, lalu melaporkan hasil
                        ke <code class="bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded text-xs">POST
                            /api/simrs/update/report</code>.</p>
                </div>
            </div>
        </div>
        <div
            class="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-800/40 dark:bg-amber-900/10 px-4 py-3">
            <p class="text-sm text-amber-700 dark:text-amber-400 font-medium">Semua endpoint
                memerlukan autentikasi Bearer token dengan scope <code
                    class="bg-amber-100 dark:bg-amber-800/40 px-1.5 py-0.5 rounded text-xs font-mono">simrs-update</code>.
            </p>
        </div>
    </div>
@endif

{{-- SIMRS Update: Cek Versi --}}
@if ($activeSection === 'simrs-update-check-version')
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-zinc-900 dark:text-primary-dark-100">Cek Versi Aktif</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-primary-dark-400">Mengambil informasi versi SIMRS
                yang sedang aktif.</p>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
            <div
                class="flex items-center gap-3 bg-zinc-50 dark:bg-primary-dark-700/50 px-4 py-3 border-b border-zinc-200 dark:border-primary-dark-700">
                <flux:badge color="emerald" size="sm">GET</flux:badge>
                <code
                    class="text-xs text-zinc-700 dark:text-primary-dark-200 break-all">{{ $appUrl }}/api/simrs/version</code>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2">
                        Headers</p>
                    <x-atoms.code-block language="http">Authorization: Bearer {token}</x-atoms.code-block>
                </div>
                <div>
                    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2">
                        Response 200</p>
                    <x-atoms.code-block language="json">{
"version": "2.5.1",
"notes": "Perbaikan bug validasi diagnosa...",
"checksum": "a3f2c1d4e5b6...",
"file_size": 52428800,
"released_at": "2026-03-20T07:00:00+07:00"
}</x-atoms.code-block>
                </div>
                <div>
                    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2">
                        Response 404</p>
                    <x-atoms.code-block language="json">{ "message": "Tidak ada versi aktif" }</x-atoms.code-block>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- SIMRS Update: Unduh Update --}}
@if ($activeSection === 'simrs-update-download')
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-zinc-900 dark:text-primary-dark-100">Unduh File Update</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-primary-dark-400">Mengunduh file ZIP update untuk
                versi tertentu. Throttle: 5 request/menit.</p>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
            <div
                class="flex items-center gap-3 bg-zinc-50 dark:bg-primary-dark-700/50 px-4 py-3 border-b border-zinc-200 dark:border-primary-dark-700">
                <flux:badge color="emerald" size="sm">GET</flux:badge>
                <code
                    class="text-xs text-zinc-700 dark:text-primary-dark-200 break-all">{{ $appUrl }}/api/simrs/download/{version}</code>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2">
                        Headers</p>
                    <x-atoms.code-block language="http">Authorization: Bearer {token}</x-atoms.code-block>
                </div>
                <div>
                    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2">
                        Response Headers (200 OK)</p>
                    <x-atoms.code-block language="http">Content-Type: application/zip
X-Checksum-SHA256: a3f2c1d4e5b6...
Content-Disposition: attachment; filename="simrs-2.5.1.zip"</x-atoms.code-block>
                </div>
                <div
                    class="rounded-xl border border-blue-200 bg-blue-50 dark:border-blue-800/40 dark:bg-blue-900/10 px-4 py-3">
                    <p class="text-xs text-blue-700 dark:text-blue-300">Verifikasi checksum setelah
                        download: <code class="bg-blue-100 dark:bg-blue-800/40 px-1 rounded font-mono">sha256sum
                            simrs-{version}.zip</code> harus cocok dengan nilai di header <code
                            class="font-mono">X-Checksum-SHA256</code>.</p>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- SIMRS Update: Laporan Update --}}
@if ($activeSection === 'simrs-update-report')
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-zinc-900 dark:text-primary-dark-100">Laporan Hasil Update</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-primary-dark-400">SIMRS melaporkan hasil proses
                update ke server setelah selesai (berhasil, gagal, atau rollback).</p>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
            <div
                class="flex items-center gap-3 bg-zinc-50 dark:bg-primary-dark-700/50 px-4 py-3 border-b border-zinc-200 dark:border-primary-dark-700">
                <flux:badge color="blue" size="sm">POST</flux:badge>
                <code
                    class="text-xs text-zinc-700 dark:text-primary-dark-200 break-all">{{ $appUrl }}/api/simrs/update/report</code>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2">
                        Headers</p>
                    <x-atoms.code-block language="http">Authorization: Bearer {token}
Content-Type: application/json</x-atoms.code-block>
                </div>
                <div>
                    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2">
                        Request Body</p>
                    <x-atoms.code-block language="json">{
"status": "success",          // "success" | "failed" | "rollback"
"from_version": "2.4.0",      // versi sebelum update (opsional)
"to_version": "2.5.1",        // versi setelah update (opsional)
"duration_seconds": 45,       // lama proses update (opsional)
"host_name": "SIMRS-SERVER1", // nama server SIMRS (opsional)
"app_name": "SIMRS v2",       // nama aplikasi (opsional)
"error_message": null         // pesan error jika gagal (opsional)
}</x-atoms.code-block>
                </div>
                <div>
                    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-2">
                        Response 200</p>
                    <x-atoms.code-block language="json">{ "success": true, "message": "Laporan update berhasil dicatat." }</x-atoms.code-block>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- SIMRS Update: Slides Launcher --}}
@if ($activeSection === 'simrs-slide-list')
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-bold text-zinc-900 dark:text-primary-dark-100">Slides Launcher</h2>
            <p class="mt-1 text-zinc-500 dark:text-primary-dark-400">
                API untuk mengambil daftar slide aktif beserta gambarnya — digunakan oleh launcher SIMRS
                untuk menampilkan slideshow beranda dengan rasio <strong>3:4</strong> (portrait).
            </p>
        </div>

        <div class="p-4 rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-800/40 dark:bg-amber-900/10">
            <p class="text-sm text-amber-700 dark:text-amber-400 font-medium">
                Semua endpoint memerlukan autentikasi Bearer token dengan scope
                <code
                    class="bg-amber-100 dark:bg-amber-800/40 px-1.5 py-0.5 rounded text-xs font-mono">update-simrs</code>.
            </p>
        </div>

        {{-- GET daftar slide --}}
        <div class="rounded-lg border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
            <div
                class="flex items-center gap-3 px-4 py-3 bg-zinc-50 dark:bg-primary-dark-900/50 border-b border-zinc-200 dark:border-primary-dark-700">
                <span
                    class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">GET</span>
                <code
                    class="text-sm font-mono text-zinc-800 dark:text-primary-dark-100">{{ $appUrl }}/api/simrs/launcher/slides</code>
            </div>
            <div class="px-4 py-4 space-y-4">
                <p class="text-sm text-zinc-600 dark:text-primary-dark-400">
                    Mengembalikan daftar slide yang sedang aktif, diurutkan berdasarkan <code
                        class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">sort_order</code>.
                </p>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 mb-2">
                        Response 200</p>
                    <x-atoms.code-block language="json">{
"success": true,
"total": 2,
"data": [
{
"id": "018e1a2b-...",
"title": "Promo Kesehatan Maret",
"href": "https://example.com/promo",
"sort_order": 0,
"image_url": "{{ $appUrl }}/api/simrs/launcher/slides/018e1a2b-.../image"
},
{
"id": "018e1a2c-...",
"title": "Jadwal Dokter",
"href": null,
"sort_order": 10,
"image_url": "{{ $appUrl }}/api/simrs/launcher/slides/018e1a2c-.../image"
}
]
}</x-atoms.code-block>
                </div>
                <div class="text-xs text-zinc-500 dark:text-primary-dark-400 space-y-1">
                    <p><code class="bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">href</code> — URL
                        tujuan saat slide diklik. <code
                            class="bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">null</code>
                        berarti
                        slide tidak dapat diklik.</p>
                    <p><code class="bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">image_url</code> — URL
                        gambar slide, gunakan sebagai sumber gambar.</p>
                </div>
            </div>
        </div>

        {{-- GET image --}}
        <div class="rounded-lg border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
            <div
                class="flex items-center gap-3 px-4 py-3 bg-zinc-50 dark:bg-primary-dark-900/50 border-b border-zinc-200 dark:border-primary-dark-700">
                <span
                    class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">GET</span>
                <code
                    class="text-sm font-mono text-zinc-800 dark:text-primary-dark-100">{{ $appUrl }}/api/simrs/launcher/slides/{id}/image</code>
            </div>
            <div class="px-4 py-4 space-y-4">
                <p class="text-sm text-zinc-600 dark:text-primary-dark-400">
                    Mengstream binary gambar slide ke klien. Hanya slide berstatus
                    <strong>aktif</strong> yang dapat diakses.
                </p>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 mb-2">
                        Path Parameter</p>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">id</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">UUID</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500">ID slide dari endpoint daftar.</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
                </div>
                <div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 mb-2">
                        Response</p>
                    <div class="space-y-1.5 text-xs text-zinc-600 dark:text-primary-dark-400">
                        <p><span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">200</span>
                            — Binary stream gambar. <code
                                class="bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">Content-Type</code>
                            sesuai format file (<code
                                class="bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">image/jpeg</code>,
                            <code class="bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">image/png</code>,
                            atau <code class="bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">image/webp</code>).
                            Di-cache 1 jam.
                        </p>
                        <p><span class="font-mono font-semibold text-red-500">404</span> — Slide tidak
                            ditemukan atau tidak aktif.</p>
                    </div>
                </div>
                <div
                    class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50 border border-zinc-100 dark:border-primary-dark-700">
                    <p class="text-xs font-semibold text-zinc-600 dark:text-primary-dark-400 mb-1.5">Contoh
                        penggunaan (Java / Android)</p>
                    <x-atoms.code-block language="java">// 1. Ambil daftar slide
List&lt;SlideItem&gt; slides = apiClient.getSlides();

// 2. Render per slide (contoh dengan Glide)
for (SlideItem slide : slides) {
Glide.with(context)
    .load(slide.imageUrl)
    .placeholder(R.drawable.placeholder)
    .into(imageView);

// 3. Buka href jika ada
if (slide.href != null) {
imageView.setOnClickListener(v -&gt;
    startActivity(new Intent(Intent.ACTION_VIEW,
        Uri.parse(slide.href))));
}
}</x-atoms.code-block>
                </div>
            </div>
        </div>
    </div>
@endif
