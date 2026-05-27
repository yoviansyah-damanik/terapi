{{-- ==================== IDENTITAS RUMAH SAKIT ==================== --}}
@if ($activeSection === 'hospital-info')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="blue" size="sm">GET</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['hospital'] }}/hospital</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Identitas Rumah Sakit</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mengembalikan informasi lengkap identitas rumah sakit meliputi nama, kontak, alamat, dan kode
            administratif wilayah. Data bersumber dari konfigurasi sistem (<code
                class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">config/hospital.php</code>
            dan variabel environment <code
                class="text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 rounded">HOSPITAL_*</code>).
        </p>

        {{-- Scope --}}
        <div
            class="mb-4 flex items-center gap-2 p-3 rounded-lg bg-cyan-50 dark:bg-cyan-900/20 border border-cyan-200 dark:border-cyan-800">
            <flux:icon name="shield-check" class="w-4 h-4 text-cyan-600 dark:text-cyan-400 shrink-0" />
            <span class="text-sm text-cyan-800 dark:text-cyan-300">
                Scope yang dibutuhkan: <strong>hospital</strong>
            </span>
        </div>

        {{-- Headers --}}
        <div class="mb-4">
            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                Request
                Headers</h4>
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Header</x-atoms.table-heading>
                    <x-atoms.table-heading>Nilai</x-atoms.table-heading>
                </x-slot:headings>

                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">Authorization</x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">Bearer &lt;token&gt;</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">Accept</x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">application/json</x-atoms.table-cell>
                </x-molecules.table-row>
            </x-organisms.table>
        </div>

        {{-- Contoh Response --}}
        <div class="mb-4">
            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                Contoh
                Response <span class="text-emerald-500">200 OK</span></h4>
            <x-atoms.code-block language="json">{
  "success": true,
  "data": {
    "identity": {
      "name": "RSUD Contoh Kota",
      "alias": "RSUD",
      "phone": "0291-123456",
      "email": "info@rsud.contoh.go.id",
      "website": "https://rsud.contoh.go.id"
    },
    "location": {
      "address": "Jl. Contoh No. 1",
      "city": "Kota Contoh",
      "province": "Jawa Tengah",
      "postal_code": "59100",
      "country": "ID"
    },
    "administrative_codes": {
      "province": "33",
      "city": "3315",
      "district": "331501",
      "village": "3315010001"
    }
  }
}</x-atoms.code-block>
        </div>

        {{-- Field Description --}}
        <div>
            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                Keterangan
                Field</h4>
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Field</x-atoms.table-heading>
                    <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                    <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                </x-slot:headings>

                @foreach ([['data.identity.name', 'string', 'Nama lengkap rumah sakit'], ['data.identity.alias', 'string', 'Nama singkat / alias'], ['data.identity.phone', 'string', 'Nomor telepon utama'], ['data.identity.email', 'string', 'Alamat email resmi'], ['data.identity.website', 'string', 'URL website resmi'], ['data.location.address', 'string', 'Alamat lengkap'], ['data.location.city', 'string', 'Kota / kabupaten'], ['data.location.province', 'string', 'Provinsi'], ['data.location.postal_code', 'string', 'Kode pos'], ['data.location.country', 'string', 'Kode negara (ISO 3166-1 alpha-2, misal: ID)'], ['data.administrative_codes.province', 'string', 'Kode provinsi Kemendagri (untuk Satu Sehat)'], ['data.administrative_codes.city', 'string', 'Kode kabupaten/kota Kemendagri'], ['data.administrative_codes.district', 'string', 'Kode kecamatan Kemendagri'], ['data.administrative_codes.village', 'string', 'Kode kelurahan/desa Kemendagri']] as [$field, $type, $desc])
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">{{ $field }}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500 dark:text-primary-dark-400">{{ $type }}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">{{ $desc }}</x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforeach
            </x-organisms.table>
        </div>

        {{-- Contoh Request --}}
        <div class="mt-4">
            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                Contoh
                Request</h4>
            <x-atoms.code-block language="bash">curl -X GET "{{ $appUrl }}/api/{{ $activeVersions['hospital'] }}/hospital" \
  -H "Authorization: Bearer &lt;token&gt;" \
  -H "Accept: application/json"</x-atoms.code-block>
        </div>
    </div>
@endif

{{-- ==================== INFO LAYANAN SISTEM ==================== --}}
@if ($activeSection === 'hospital-service')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="blue" size="sm">GET</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">/api/{{ $activeVersions['hospital'] }}/hospital/service</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Info Layanan Sistem</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mengembalikan informasi tentang sistem integrasi yang sedang berjalan — nama aplikasi, versi,
            zona waktu, dan waktu server saat ini. Berguna untuk verifikasi konektivitas dan kompatibilitas
            versi antar sistem.
        </p>

        {{-- Scope --}}
        <div
            class="mb-4 flex items-center gap-2 p-3 rounded-lg bg-cyan-50 dark:bg-cyan-900/20 border border-cyan-200 dark:border-cyan-800">
            <flux:icon name="shield-check" class="w-4 h-4 text-cyan-600 dark:text-cyan-400 shrink-0" />
            <span class="text-sm text-cyan-800 dark:text-cyan-300">
                Scope yang dibutuhkan: <strong>hospital</strong>
            </span>
        </div>

        {{-- Headers --}}
        <div class="mb-4">
            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                Request
                Headers</h4>
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Header</x-atoms.table-heading>
                    <x-atoms.table-heading>Nilai</x-atoms.table-heading>
                </x-slot:headings>

                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">Authorization</x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">Bearer &lt;token&gt;</x-atoms.table-cell>
                </x-molecules.table-row>
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">Accept</x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">application/json</x-atoms.table-cell>
                </x-molecules.table-row>
            </x-organisms.table>
        </div>

        {{-- Contoh Response --}}
        <div class="mb-4">
            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                Contoh
                Response <span class="text-emerald-500">200 OK</span></h4>
            <x-atoms.code-block language="json">{
  "success": true,
  "data": {
    "name": "Teknologi Integrasi &amp; Pertukaran Informasi Kesehatan",
    "alias": "TERAPI",
    "version": "beta-1.0.0",
    "timezone": "Asia/Jakarta",
    "server_time": "2026-03-31T10:30:00+07:00"
  }
}</x-atoms.code-block>
        </div>

        {{-- Field Description --}}
        <div class="mb-4">
            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                Keterangan
                Field</h4>
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Field</x-atoms.table-heading>
                    <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                    <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                </x-slot:headings>

                @foreach ([['data.name', 'string', 'Nama lengkap sistem integrasi'], ['data.alias', 'string', 'Nama alias / singkatan sistem'], ['data.version', 'string', 'Versi sistem yang sedang berjalan'], ['data.timezone', 'string', 'Zona waktu server (misal: Asia/Jakarta)'], ['data.server_time', 'string (ISO 8601)', 'Waktu server saat request diterima, termasuk offset timezone']] as [$field, $type, $desc])
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">{{ $field }}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500 dark:text-primary-dark-400">{{ $type }}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">{{ $desc }}</x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforeach
            </x-organisms.table>
        </div>

        {{-- Use Cases --}}
        <div class="p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
            <p class="text-xs font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wider mb-1">Catatan
                Penggunaan</p>
            <ul class="text-sm text-amber-800 dark:text-amber-300 space-y-1 list-disc list-inside">
                <li>Gunakan <code class="text-xs bg-amber-100 dark:bg-amber-900 px-1 rounded">server_time</code> untuk
                    sinkronisasi waktu antara SIMRS dan sistem integrasi.</li>
                <li>Periksa <code class="text-xs bg-amber-100 dark:bg-amber-900 px-1 rounded">version</code> di startup
                    aplikasi klien untuk memastikan kompatibilitas API.</li>
            </ul>
        </div>

        {{-- Contoh Request --}}
        <div class="mt-4">
            <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                Contoh
                Request</h4>
            <x-atoms.code-block language="bash">curl -X GET "{{ $appUrl }}/api/{{ $activeVersions['hospital'] }}/hospital/service" \
  -H "Authorization: Bearer &lt;token&gt;" \
  -H "Accept: application/json"</x-atoms.code-block>
        </div>
    </div>
@endif
