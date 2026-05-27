{{-- ==================== SIMRS LOG: KIRIM LOG ==================== --}}
@if ($activeSection === 'simrs-log-store')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['simrs'] }}/simrs/log</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Kirim Log SIMRS</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mengirim satu entri log dari aplikasi SIMRS. Cocok untuk pencatatan error secara real-time
            pada saat exception terjadi. IP address server pengirim dicatat otomatis dari request.
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
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">message</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Pesan log / deskripsi error. Maks 5000 karakter.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">level</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Level log: <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">error</code> (default), <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">warning</code>, <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">info</code>, <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">debug</code>.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">category</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nama kategori / tipe exception Java. Contoh: <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">NullPointerException</code>, <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">SQLException</code>, <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">ConnectionException</code>.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">module</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Modul SIMRS tempat error terjadi. Contoh: <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">pendaftaran</code>, <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">rawat_jalan</code>, <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">farmasi</code>.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">exception_class</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Fully-qualified class name exception Java. Contoh: <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">java.sql.SQLException</code>.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">stack_trace</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Stack trace lengkap dari exception Java (output <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">e.printStackTrace()</code>).</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">app_version</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Versi aplikasi SIMRS. Maks 50 karakter.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">host_name</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nama hostname server SIMRS.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">simrs_user</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Username yang sedang login di SIMRS saat error terjadi.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">simrs_user_role</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Role pengguna SIMRS.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">db_host</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Host database SIMRS yang digunakan.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">db_name</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nama database SIMRS yang digunakan.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">db_connected</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">boolean</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Status koneksi database saat error: <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">true</code> = terhubung, <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">false</code> = terputus.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">db_response_time_ms</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Waktu respons database dalam milidetik.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">context</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">object</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Data konteks tambahan dalam format JSON bebas (request parameter, session data, dll).</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- Contoh Request --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['simrs'] }}/simrs/log \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{
"message": "Gagal memuat data pasien: No. RM tidak ditemukan",
"level": "error",
"category": "NullPointerException",
"module": "rawat_jalan",
"exception_class": "java.lang.NullPointerException",
"stack_trace": "java.lang.NullPointerException\n\tat com.simrs.service.PasienService.findByRm(PasienService.java:142)\n\tat com.simrs.controller.RawatJalanController.loadPasien(RawatJalanController.java:87)",
"app_version": "3.2.1",
"host_name": "SERVER-SIMRS-01",
"simrs_user": "bidan.sari",
"simrs_user_role": "Keperawatan",
"db_host": "192.168.1.10",
"db_name": "db_simrs",
"db_connected": true,
"db_response_time_ms": 45,
"context": {
"no_rm": "RM-2026-00123",
"poli": "poli-umum",
"action": "load_pasien"
}
}'</span></x-atoms.code-block>
            </div>

            {{-- Contoh Java --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Implementasi Java</h4>
                <x-atoms.code-block language="bash"><span class="text-blue-400">try</span> {
pasienService.findByRm(noRm);
} <span class="text-blue-400">catch</span> (Exception e) {
<span class="text-zinc-500">// Kirim log ke API terapi</span>
Map&lt;String, Object&gt; payload = <span class="text-blue-400">new</span> HashMap&lt;&gt;();
payload.put(<span class="text-emerald-400">"message"</span>, e.getMessage());
payload.put(<span class="text-emerald-400">"level"</span>, <span class="text-emerald-400">"error"</span>);
payload.put(<span class="text-emerald-400">"category"</span>, e.getClass().getSimpleName());
payload.put(<span class="text-emerald-400">"exception_class"</span>, e.getClass().getName());
payload.put(<span class="text-emerald-400">"stack_trace"</span>, getStackTraceAsString(e));
payload.put(<span class="text-emerald-400">"module"</span>, <span class="text-emerald-400">"rawat_jalan"</span>);
payload.put(<span class="text-emerald-400">"simrs_user"</span>, getCurrentUsername());
payload.put(<span class="text-emerald-400">"app_version"</span>, AppConfig.VERSION);
payload.put(<span class="text-emerald-400">"db_connected"</span>, dbPool.isConnected());

terapiLogClient.send(payload); <span class="text-zinc-500">// POST /api/{{ $activeVersions['simrs'] }}/simrs/log</span>
}</x-atoms.code-block>
            </div>

            {{-- Response Sukses --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response Sukses (201)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Log berhasil disimpan"</span>,
<span class="text-blue-400">"id"</span>: <span class="text-emerald-400">"01956b2a-c1d2-7e3f-a4b5-c6d7e8f9a0b1"</span>
}</x-atoms.code-block>
            </div>

            {{-- Response Error --}}
            <div class="p-4 border rounded-lg bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-800">
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-red-600 dark:text-red-400">
                    Response Error (422)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">false</span>,
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Validasi gagal"</span>,
<span class="text-blue-400">"errors"</span>: {
<span class="text-blue-400">"message"</span>: [<span class="text-emerald-400">"The message field is required."</span>]
}
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== SIMRS LOG: KIRIM BATCH ==================== --}}
@if ($activeSection === 'simrs-log-batch')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['simrs'] }}/simrs/log/batch</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Kirim Log Batch</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mengirim banyak entri log sekaligus dalam satu request. Cocok untuk pengiriman log yang
            dikumpulkan terlebih dahulu (buffered logging) sebelum dikirim secara berkala.
            Maksimal <strong>100 log</strong> per request.
        </p>

        <div class="space-y-4">
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
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">logs</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">array</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Array berisi 1–100 objek log. Setiap objek memiliki field yang sama seperti endpoint <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">POST /api/{{ $activeVersions['simrs'] }}/simrs/log</code>.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">logs[].message</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Pesan log (wajib per item).</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">logs[].*</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">mixed</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Field opsional sama seperti endpoint single: <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">level</code>, <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">category</code>, <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">module</code>, <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">stack_trace</code>, dll.</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>v>
            </div>

            {{-- Contoh Request --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['simrs'] }}/simrs/log/batch \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{
"logs": [
{
"message": "Koneksi database timeout setelah 30 detik",
"level": "error",
"category": "TimeoutException",
"module": "rawat_inap",
"exception_class": "java.net.SocketTimeoutException",
"db_connected": false,
"db_response_time_ms": 30000
},
{
"message": "Data obat tidak ditemukan untuk kode: OBT-9923",
"level": "warning",
"category": "NullPointerException",
"module": "farmasi",
"simrs_user": "apt.dewi",
"context": { "kode_obat": "OBT-9923" }
}
]
}'</span></x-atoms.code-block>
            </div>

            {{-- Response Sukses --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response Sukses (201)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"2 log berhasil disimpan"</span>,
<span class="text-blue-400">"count"</span>: <span class="text-amber-400">2</span>
}</x-atoms.code-block>
            </div>

            {{-- Catatan --}}
            <div class="p-4 border rounded-lg bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800">
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-amber-700 dark:text-amber-400">
                    Catatan Penting</h4>
                <ul class="space-y-1 text-sm text-amber-800 dark:text-amber-300 list-disc list-inside">
                    <li>Maksimal <strong>100 log</strong> per request batch.</li>
                    <li>IP address pengirim dicatat satu kali untuk semua log dalam batch (IP server
                        SIMRS).</li>
                    <li>Jika satu log gagal validasi, seluruh batch ditolak. Pastikan semua item valid.
                    </li>
                    <li>Untuk pengiriman performa tinggi, disarankan menggunakan buffer dan mengirim
                        setiap 5–30 detik.</li>
                </ul>
            </div>
        </div>
    </div>
@endif

{{-- ==================== SIMRS LOG: AMBIL DAFTAR LOG ==================== --}}
@if ($activeSection === 'simrs-log-list')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="blue" size="sm">GET</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['simrs'] }}/simrs/logs</code>
        </div>
        <h3 class="mb-1 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Ambil Daftar Log</h3>
        <p class="mb-4 text-sm text-zinc-400 dark:text-primary-dark-500">
            Juga tersedia: <flux:badge color="blue" size="sm">GET</flux:badge>
            <code class="text-sm font-mono">/api/{{ $activeVersions['simrs'] }}/simrs/logs/{id}</code> — untuk mengambil detail satu
            log.
        </p>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mengambil daftar log SIMRS dengan paginasi. Mendukung berbagai filter untuk mempersempit
            hasil pencarian.
            Dapat digunakan oleh sistem monitoring atau dashboard eksternal.
        </p>

        <div class="space-y-4">
            {{-- Query Parameters --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Query Parameters</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Parameter</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Contoh</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>

                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">level</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-zinc-500">error</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Filter berdasarkan level: <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">error</code>, <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">warning</code>, <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">info</code>, <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">debug</code>.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">category</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-zinc-500">SQLException</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Filter berdasarkan kategori exception.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">module</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-zinc-500">farmasi</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Filter berdasarkan modul SIMRS.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">search</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-zinc-500">timeout</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Cari di kolom message, exception_class, category, dan simrs_user.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">date</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-zinc-500">2026-03-18</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Filter berdasarkan tanggal (format: <code class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">YYYY-MM-DD</code>).</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">per_page</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-zinc-500">25</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Jumlah item per halaman. Default: 25, maksimal: 100.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">page</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">integer</x-atoms.table-cell>
                        <x-atoms.table-cell class="font-mono text-xs text-zinc-500">1</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nomor halaman. Default: 1.</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            {{-- Contoh Request --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X GET "{{ $appUrl }}/api/{{ $activeVersions['simrs'] }}/simrs/logs?level=error&module=farmasi&per_page=10" \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span></x-atoms.code-block>
            </div>

            {{-- Contoh Request Detail --}}
            <div>
                <h4
                    class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request (Detail)</h4>
                <x-atoms.code-block language="bash">curl -X GET "{{ $appUrl }}/api/{{ $activeVersions['simrs'] }}/simrs/logs/01956b2a-c1d2-7e3f-a4b5-c6d7e8f9a0b1" \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span></x-atoms.code-block>
            </div>

            {{-- Response Sukses --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4
                    class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response Sukses (200)</h4>
                <x-atoms.code-block language="json">{
<span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"data"</span>: [
{
<span class="text-blue-400">"id"</span>: <span class="text-emerald-400">"01956b2a-c1d2-7e3f-a4b5-c6d7e8f9a0b1"</span>,
<span class="text-blue-400">"app_version"</span>: <span class="text-emerald-400">"3.2.1"</span>,
<span class="text-blue-400">"host_name"</span>: <span class="text-emerald-400">"SERVER-SIMRS-01"</span>,
<span class="text-blue-400">"ip_address"</span>: <span class="text-emerald-400">"192.168.1.50"</span>,
<span class="text-blue-400">"level"</span>: <span class="text-emerald-400">"error"</span>,
<span class="text-blue-400">"category"</span>: <span class="text-emerald-400">"NullPointerException"</span>,
<span class="text-blue-400">"module"</span>: <span class="text-emerald-400">"rawat_jalan"</span>,
<span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Gagal memuat data pasien: No. RM tidak ditemukan"</span>,
<span class="text-blue-400">"exception_class"</span>: <span class="text-emerald-400">"java.lang.NullPointerException"</span>,
<span class="text-blue-400">"stack_trace"</span>: <span class="text-emerald-400">"java.lang.NullPointerException\n\tat ..."</span>,
<span class="text-blue-400">"simrs_user"</span>: <span class="text-emerald-400">"bidan.sari"</span>,
<span class="text-blue-400">"simrs_user_role"</span>: <span class="text-emerald-400">"Keperawatan"</span>,
<span class="text-blue-400">"db_host"</span>: <span class="text-emerald-400">"192.168.1.10"</span>,
<span class="text-blue-400">"db_name"</span>: <span class="text-emerald-400">"db_simrs"</span>,
<span class="text-blue-400">"db_connected"</span>: <span class="text-amber-400">true</span>,
<span class="text-blue-400">"db_response_time_ms"</span>: <span class="text-amber-400">45</span>,
<span class="text-blue-400">"context"</span>: { <span class="text-blue-400">"no_rm"</span>: <span class="text-emerald-400">"RM-2026-00123"</span> },
<span class="text-blue-400">"created_at"</span>: <span class="text-emerald-400">"2026-03-18T09:15:32+07:00"</span>
}
],
<span class="text-blue-400">"meta"</span>: {
<span class="text-blue-400">"current_page"</span>: <span class="text-amber-400">1</span>,
<span class="text-blue-400">"last_page"</span>: <span class="text-amber-400">5</span>,
<span class="text-blue-400">"per_page"</span>: <span class="text-amber-400">10</span>,
<span class="text-blue-400">"total"</span>: <span class="text-amber-400">47</span>
}
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif
