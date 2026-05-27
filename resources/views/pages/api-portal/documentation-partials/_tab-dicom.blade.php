{{-- ==================== DICOM WORKLIST BATCH ==================== --}}
@if ($activeSection === 'dicom-worklist-batch')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">{{ $appUrl }}/api/{{ $activeVersions['dicom'] }}/worklists/batch</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Kirim Worklist (Batch)</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mengirim satu atau lebih order radiologi/USG ke PACS (Orthanc). Data pasien, modalitas, dan
            jadwal diambil otomatis dari SIMRS berdasarkan <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">accession_number</code>.
            Body boleh berupa array maupun objek tunggal (keduanya diproses sama).
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
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">accession_number</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nomor order dari SIMRS (<code class="text-xs">noorder</code>). Data DICOM diambil otomatis dari SIMRS.</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">type</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300"><code class="text-xs">radiologi</code> (default) atau <code class="text-xs">usg</code></x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">bypass</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">boolean</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Kirim ulang meskipun worklist sudah ada. Default <code class="text-xs">false</code>.</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['dicom'] }}/worklists/batch \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'[
  { "accession_number": "PR202602220001" },
  { "accession_number": "US202602220002", "type": "usg" }
]'</span></x-atoms.code-block>
            </div>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response (200)</h4>
                <x-atoms.code-block language="json">{
  <span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
  <span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"2 order berhasil disimpan"</span>,
  <span class="text-blue-400">"data"</span>: {
    <span class="text-blue-400">"total"</span>: <span class="text-amber-400">2</span>,
    <span class="text-blue-400">"success_count"</span>: <span class="text-amber-400">2</span>,
    <span class="text-blue-400">"failed_count"</span>: <span class="text-amber-400">0</span>,
    <span class="text-blue-400">"results"</span>: [
      { <span class="text-blue-400">"accession_number"</span>: <span class="text-emerald-400">"PR202602220001"</span>, <span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>, <span class="text-blue-400">"study_instance_uid"</span>: <span class="text-emerald-400">"1.2.840..."</span> },
      { <span class="text-blue-400">"accession_number"</span>: <span class="text-emerald-400">"US202602220002"</span>, <span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>, <span class="text-blue-400">"study_instance_uid"</span>: <span class="text-emerald-400">"1.2.840..."</span> }
    ]
  }
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== DICOM WORKLIST SINGLE ==================== --}}
@if ($activeSection === 'dicom-worklist')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">{{ $appUrl }}/api/{{ $activeVersions['dicom'] }}/worklists</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Kirim Worklist (Single)</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mengirim satu order ke PACS. Parameter sama dengan Batch, namun body berupa JSON object (bukan array).
        </p>

        <div class="space-y-4">
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X POST {{ $appUrl }}/api/{{ $activeVersions['dicom'] }}/worklists \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span> \
-H <span class="text-emerald-400">"Content-Type: application/json"</span> \
-d <span class="text-emerald-400">'{
  "accession_number": "PR202602220001",
  "type": "radiologi"
}'</span></x-atoms.code-block>
            </div>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response (200)</h4>
                <x-atoms.code-block language="json">{
  <span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
  <span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Order berhasil diproses"</span>,
  <span class="text-blue-400">"details"</span>: [
    {
      <span class="text-blue-400">"accession_number"</span>: <span class="text-emerald-400">"PR202602220001"</span>,
      <span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
      <span class="text-blue-400">"study_instance_uid"</span>: <span class="text-emerald-400">"1.2.840.10008..."</span>
    }
  ]
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== STATUS WORKLIST ==================== --}}
@if ($activeSection === 'dicom-status')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="blue" size="sm">GET</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">{{ $appUrl }}/api/{{ $activeVersions['dicom'] }}/worklists/{noorder}</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Status Worklist</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Mendapatkan detail worklist beserta status real-time dari Orthanc (jika gambar sudah ada).
        </p>

        <div class="space-y-4">
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Field</x-atoms.table-heading>
                    <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                </x-slot:headings>
                @foreach ([
                    ['status', 'pending | worklist | sent | error'],
                    ['orthanc.series_count', 'Jumlah seri gambar di Orthanc (jika sudah ada)'],
                    ['orthanc.is_stable', 'true jika semua gambar sudah diterima Orthanc'],
                    ['orthanc.last_update', 'Waktu update terakhir di Orthanc'],
                ] as [$f, $d])
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">{{ $f }}</x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300 text-xs">{{ $d }}</x-atoms.table-cell>
                </x-molecules.table-row>
                @endforeach
            </x-organisms.table>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl {{ $appUrl }}/api/{{ $activeVersions['dicom'] }}/worklists/PR202602220001 \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span></x-atoms.code-block>
            </div>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response (200)</h4>
                <x-atoms.code-block language="json">{
  <span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
  <span class="text-blue-400">"data"</span>: {
    <span class="text-blue-400">"noorder"</span>: <span class="text-emerald-400">"PR202602220001"</span>,
    <span class="text-blue-400">"patient_id"</span>: <span class="text-emerald-400">"RM001234"</span>,
    <span class="text-blue-400">"modality"</span>: <span class="text-emerald-400">"CR"</span>,
    <span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"sent"</span>,
    <span class="text-blue-400">"imaging_study_ihs"</span>: <span class="text-emerald-400">"02d36312-8a0f-45ef-..."</span>,
    <span class="text-blue-400">"orthanc"</span>: {
      <span class="text-blue-400">"series_count"</span>: <span class="text-amber-400">2</span>,
      <span class="text-blue-400">"is_stable"</span>: <span class="text-amber-400">true</span>,
      <span class="text-blue-400">"last_update"</span>: <span class="text-emerald-400">"20260222T143000"</span>
    }
  }
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== HAPUS WORKLIST ==================== --}}
@if ($activeSection === 'dicom-delete')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="red" size="sm">DELETE</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">{{ $appUrl }}/api/{{ $activeVersions['dicom'] }}/worklists/{noorder}</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Hapus Worklist</h3>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Menghapus record worklist dari database lokal. <strong>Tidak</strong> menghapus data dari Orthanc.
            Gunakan ini jika order dibatalkan di SIMRS dan tidak perlu dilanjutkan ke PACS.
        </p>

        <div class="space-y-4">
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Request</h4>
                <x-atoms.code-block language="bash">curl -X DELETE {{ $appUrl }}/api/{{ $activeVersions['dicom'] }}/worklists/PR202602220001 \
-H <span class="text-emerald-400">"Authorization: Bearer {token}"</span></x-atoms.code-block>
            </div>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response (200)</h4>
                <x-atoms.code-block language="json">{
  <span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
  <span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Worklist berhasil dihapus."</span>
}</x-atoms.code-block>
            </div>

            <div class="p-3 border rounded-xl bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800">
                <div class="flex gap-2">
                    <flux:icon name="exclamation-triangle" class="flex-shrink-0 w-4 h-4 mt-0.5 text-amber-600 dark:text-amber-400" />
                    <p class="text-xs text-amber-800 dark:text-amber-200">
                        Endpoint ini hanya menghapus record di database lokal (<code class="px-1 py-0.5 rounded bg-amber-100 dark:bg-amber-900/50">dicom_studies</code>).
                        Jika gambar sudah masuk ke Orthanc, hapus secara terpisah melalui antarmuka Orthanc.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ==================== WEBHOOK: SATU SEHAT DICOM ROUTER ==================== --}}
@if ($activeSection === 'dicom-webhook-satusehat')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">{{ $appUrl }}/api/webhooks/satusehat/dicom</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Webhook — Satu Sehat DICOM Router</h3>

        <div class="mb-4 p-3 border rounded-xl bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
            <div class="flex gap-3">
                <flux:icon name="information-circle" class="flex-shrink-0 w-5 h-5 text-blue-600 dark:text-blue-400" />
                <div class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                    <p class="font-medium">Endpoint ini dipanggil oleh DICOM Router Kemenkes / Platform Satu Sehat</p>
                    <p class="text-xs">
                        <strong>Tidak memerlukan autentikasi Bearer</strong> — endpoint ini menerima callback dari server Satu Sehat
                        setelah gambar DICOM berhasil dikirim ke IHS. Pastikan IP DICOM Router sudah di-whitelist di firewall.
                    </p>
                </div>
            </div>
        </div>

        <p class="mb-4 text-sm text-zinc-600 dark:text-primary-dark-300">
            Diterima setelah DICOM Router Kemenkes berhasil/gagal memproses gambar. Saat sukses, sistem otomatis:
        </p>
        <ul class="mb-4 ml-4 space-y-1 list-disc text-sm text-zinc-600 dark:text-primary-dark-300">
            <li>Menyimpan <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">ImagingStudy IHS ID</code> ke record worklist dan tabel <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">satu_sehat_imaging_studies</code></li>
            <li>Memicu pengiriman resource <strong>Observation</strong> dan <strong>DiagnosticReport</strong> ke Satu Sehat secara otomatis</li>
            <li>Mencatat hasil ke <code class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-xs">SatuSehatBundleLog</code> untuk audit trail</li>
        </ul>

        <div class="space-y-4">
            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Payload (dikirim oleh DICOM Router Kemenkes)</h4>
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Field</x-atoms.table-heading>
                        <x-atoms.table-heading>Tipe</x-atoms.table-heading>
                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    </x-slot:headings>
                    @foreach ([
                        ['status', 'boolean', 'true jika DICOM berhasil dikirim ke IHS'],
                        ['message', 'string', 'Pesan dari DICOM Router'],
                        ['stage', 'string', 'Tahap proses, contoh: dicom_sent'],
                        ['error', 'array', 'Daftar error jika ada'],
                        ['data.imagingStudyId', 'string', 'IHS ID ImagingStudy yang diterbitkan oleh Satu Sehat'],
                        ['data.accessionNumber', 'string', 'Nomor order radiologi'],
                        ['data.studyInstanceUID', 'string', 'DICOM Study Instance UID'],
                    ] as [$f, $t, $d])
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">{{ $f }}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-500 dark:text-primary-dark-400 text-xs">{{ $t }}</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300 text-xs">{{ $d }}</x-atoms.table-cell>
                    </x-molecules.table-row>
                    @endforeach
                </x-organisms.table>
            </div>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Payload (Sukses)</h4>
                <x-atoms.code-block language="json">{
  <span class="text-blue-400">"status"</span>: <span class="text-amber-400">true</span>,
  <span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"DICOM berhasil dikirim"</span>,
  <span class="text-blue-400">"stage"</span>: <span class="text-emerald-400">"dicom_sent"</span>,
  <span class="text-blue-400">"error"</span>: [],
  <span class="text-blue-400">"data"</span>: {
    <span class="text-blue-400">"imagingStudyId"</span>: <span class="text-emerald-400">"02d36312-8a0f-45ef-bd88-eb3c954a6a3f"</span>,
    <span class="text-blue-400">"accessionNumber"</span>: <span class="text-emerald-400">"PR202602220001"</span>,
    <span class="text-blue-400">"studyInstanceUID"</span>: <span class="text-emerald-400">"2.25.697744798.2508253374"</span>
  }
}</x-atoms.code-block>
            </div>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response dari Terapi (200)</h4>
                <x-atoms.code-block language="json">{
  <span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
  <span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Webhook received and processed successfully"</span>,
  <span class="text-blue-400">"items"</span>: {
    <span class="text-blue-400">"imaging_study"</span>: { <span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"success"</span>, <span class="text-blue-400">"ihs_number"</span>: <span class="text-emerald-400">"02d36312-..."</span> },
    <span class="text-blue-400">"observation"</span>: { <span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"success"</span>, <span class="text-blue-400">"count"</span>: <span class="text-amber-400">1</span> },
    <span class="text-blue-400">"diagnostic_report"</span>: { <span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"success"</span>, <span class="text-blue-400">"count"</span>: <span class="text-amber-400">1</span> }
  }
}</x-atoms.code-block>
            </div>
        </div>
    </div>
@endif

{{-- ==================== WEBHOOK: ORTHANC-SYNC ==================== --}}
@if ($activeSection === 'dicom-webhook-orthanc')
    <div class="p-6 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <flux:badge color="green" size="sm">POST</flux:badge>
            <code class="text-sm font-mono text-zinc-700 dark:text-primary-dark-300">{{ $appUrl }}/api/webhooks/orthanc/worklist</code>
        </div>
        <h3 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Webhook — Orthanc-Sync</h3>

        <div class="mb-4 p-3 border rounded-xl bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
            <div class="flex gap-3">
                <flux:icon name="information-circle" class="flex-shrink-0 w-5 h-5 text-blue-600 dark:text-blue-400" />
                <div class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                    <p class="font-medium">Endpoint ini dipanggil oleh Orthanc-Sync (layanan Django)</p>
                    <p class="text-xs">
                        <strong>Tidak memerlukan autentikasi Bearer.</strong> Digunakan untuk melaporkan apakah
                        gambar DICOM dari modalitas berhasil masuk ke Orthanc sebelum diteruskan ke Satu Sehat.
                        Status worklist diperbarui dari <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50">pending</code>
                        menjadi <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50">worklist</code> atau <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50">failed</code>.
                    </p>
                </div>
            </div>
        </div>

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
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">accession_number</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Nomor order yang dilaporkan</x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">status</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="red" size="sm">Ya</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300"><code class="text-xs">Berhasil</code> → status worklist jadi <code class="text-xs">worklist</code>. Selain itu → <code class="text-xs">failed</code></x-atoms.table-cell>
                    </x-molecules.table-row>
                    <x-molecules.table-row>
                        <x-atoms.table-cell class="font-mono text-xs text-primary-600 dark:text-primary-400">message</x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-600 dark:text-primary-dark-400">string</x-atoms.table-cell>
                        <x-atoms.table-cell><flux:badge color="zinc" size="sm">Tidak</flux:badge></x-atoms.table-cell>
                        <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">Pesan error (jika gagal)</x-atoms.table-cell>
                    </x-molecules.table-row>
                </x-organisms.table>
            </div>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Payload (Berhasil)</h4>
                <x-atoms.code-block language="json">{
  <span class="text-blue-400">"accession_number"</span>: <span class="text-emerald-400">"PR202602220001"</span>,
  <span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"Berhasil"</span>
}</x-atoms.code-block>
            </div>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Contoh Payload (Gagal)</h4>
                <x-atoms.code-block language="json">{
  <span class="text-blue-400">"accession_number"</span>: <span class="text-emerald-400">"PR202602220001"</span>,
  <span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"Gagal"</span>,
  <span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Koneksi ke modalitas terputus"</span>
}</x-atoms.code-block>
            </div>

            <div>
                <h4 class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Response (200)</h4>
                <x-atoms.code-block language="json">{
  <span class="text-blue-400">"success"</span>: <span class="text-amber-400">true</span>,
  <span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"Status updated"</span>
}</x-atoms.code-block>
            </div>

            {{-- Alur status --}}
            <div class="p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4 class="mb-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Alur Status Worklist</h4>
                <div class="flex items-center gap-2 flex-wrap text-xs">
                    @foreach ([
                        ['pending', 'zinc', 'Order dikirim dari SIMRS'],
                        ['worklist', 'blue', 'Orthanc-Sync melaporkan gambar masuk'],
                        ['sent', 'emerald', 'DICOM Router Kemenkes berhasil kirim ke IHS'],
                        ['failed', 'red', 'Salah satu tahap gagal'],
                    ] as [$s, $c, $d])
                    <div class="flex items-center gap-1">
                        <flux:badge color="{{ $c }}" size="sm">{{ $s }}</flux:badge>
                        <span class="text-zinc-400 dark:text-primary-dark-500">— {{ $d }}</span>
                    </div>
                    @if (!$loop->last)
                        <flux:icon name="arrow-right" class="w-3 h-3 text-zinc-300 dark:text-primary-dark-600" />
                    @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
