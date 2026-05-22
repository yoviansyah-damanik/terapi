<div x-data="{
    ermSubTab: new URLSearchParams(location.search).get('ermSubTab') || 'bundle'
}" x-init="
    $watch('ermSubTab', val => {
        if (activeTab === 'bpjs' && bpjsTab === 'erm') {
            let url = new URL(window.location.href);
            url.searchParams.set('ermSubTab', val);
            window.history.replaceState({}, '', url);
        }
    });
    $watch('activeTab', val => {
        if (val === 'bpjs' && bpjsTab === 'erm') {
            let url = new URL(window.location.href);
            url.searchParams.set('ermSubTab', ermSubTab);
            window.history.replaceState({}, '', url);
        }
    });
    $watch('bpjsTab', val => {
        if (activeTab === 'bpjs') {
            let url = new URL(window.location.href);
            if (val === 'erm') {
                url.searchParams.set('ermSubTab', ermSubTab);
            } else {
                url.searchParams.delete('ermSubTab');
            }
            window.history.replaceState({}, '', url);
        }
    });
" class="p-5 flex flex-col gap-4">
    {{-- Inner Tabs Nav --}}
    <div class="flex gap-1 p-1 overflow-x-auto bg-zinc-100 rounded-xl dark:bg-primary-dark-900/50 w-max">
        <button @click="ermSubTab = 'bundle'"
            :class="ermSubTab === 'bundle'
                ?
                'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-800 dark:text-primary-dark-100 border border-zinc-200 dark:border-primary-dark-600' :
                'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400 border border-transparent'"
            class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-all">
            <flux:icon name="document-text" class="h-4 w-4" />
            Resource Bundle
        </button>
        <button @click="ermSubTab = 'history'"
            :class="ermSubTab === 'history'
                ?
                'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-800 dark:text-primary-dark-100 border border-zinc-200 dark:border-primary-dark-600' :
                'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400 border border-transparent'"
            class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-all">
            <flux:icon name="clock" class="h-4 w-4" />
            Riwayat Pengiriman
        </button>
    </div>

    {{-- Bundle Content --}}
    <div x-show="ermSubTab === 'bundle'" x-cloak class="space-y-4">
        {{-- Pesan validasi --}}
        @foreach ($validationMessages as $msg)
            <div
                class="flex items-start gap-2 p-3 rounded-lg text-sm {{ $msg['type'] === 'error' ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400' : 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400' }}">
                <flux:icon name="{{ $msg['type'] === 'error' ? 'x-circle' : 'exclamation-triangle' }}"
                    class="w-4 h-4 mt-0.5 shrink-0" />
                <div><span class="font-semibold">{{ $msg['section'] }}</span>: {{ $msg['text'] }}</div>
            </div>
        @endforeach

        {{-- Daftar Resource Bundle --}}
        @php
            use App\Services\Bpjs\Erm\ErmModuleRegistry;

            // Counts are provided from the component directly

            $bundleResources = [
                // --- Wajib ---
                [
                    'label' => 'Composition',
                    'desc' => 'Resume & ringkasan dokumen',
                    'count' => 1,
                    'required' => true,
                    'enabled' => true,
                ],
                [
                    'label' => 'Patient',
                    'desc' => $this->reg->pasien?->nm_pasien ?? '-',
                    'count' => $this->reg->pasien ? 1 : 0,
                    'required' => true,
                    'enabled' => true,
                ],
                [
                    'label' => 'Practitioner',
                    'desc' => $this->reg->dokter?->nm_dokter ?? '-',
                    'count' => $this->reg->dokter ? 1 : 0,
                    'required' => true,
                    'enabled' => true,
                ],
                [
                    'label' => 'Organization — RS',
                    'desc' => 'Rumah Sakit (identifier: RS)',
                    'count' => 1,
                    'required' => true,
                    'enabled' => true,
                ],
                [
                    'label' => 'Organization — Unit',
                    'desc' =>
                        $this->reg->status_lanjut === 'Ranap'
                            ? 'Rawat Inap (identifier: RI)'
                            : (\Illuminate\Support\Str::contains(strtolower($this->reg->poliklinik->nm_poli ?? ''), 'darurat') || \Illuminate\Support\Str::contains(strtolower($this->reg->poliklinik->nm_poli ?? ''), 'gawat') ? 'IGD (identifier: IGD)' : 'Rawat Jalan (identifier: RJ)'),
                    'count' => 1,
                    'required' => true,
                    'enabled' => true,
                ],
                [
                    'label' => 'Encounter',
                    'desc' => 'No. SEP: ' . ($this->reg->bridgingSep?->no_sep ?? '-'),
                    'count' => $this->reg->bridgingSep ? 1 : 0,
                    'required' => true,
                    'enabled' => true,
                ],
                [
                    'label' => 'Condition',
                    'desc' => 'Diagnosa ICD-10',
                    'count' => $totalDiagnosaERM,
                    'required' => true,
                    'enabled' => true,
                ],
                // --- Opsional (Organization per modul) ---
                [
                    'label' => 'Organization — Farmasi',
                    'desc' => 'Diperlukan jika ada obat (identifier: FAR)',
                    'count' => $totalObatERM > 0 ? 1 : 0,
                    'required' => false,
                    'enabled' => ErmModuleRegistry::isEnabled('medication'),
                ],
                [
                    'label' => 'Organization — Lab',
                    'desc' => 'Diperlukan jika ada pemeriksaan lab (identifier: LAB)',
                    'count' => $totalLabERM > 0 ? 1 : 0,
                    'required' => false,
                    'enabled' => ErmModuleRegistry::isEnabled('lab'),
                ],
                [
                    'label' => 'Organization — Radiologi',
                    'desc' => 'Diperlukan jika ada pemeriksaan rad (identifier: RAD)',
                    'count' => $totalRadERM > 0 ? 1 : 0,
                    'required' => false,
                    'enabled' => ErmModuleRegistry::isEnabled('radiologi'),
                ],
                // --- Opsional (resource klinis) ---
                [
                    'label' => 'Procedure',
                    'desc' => 'ICD-9 (' . $totalProsedurERM . ') + Tindakan (' . $totalTindakanERM . ')',
                    'count' => $totalProsedurERM + $totalTindakanERM,
                    'required' => false,
                    'enabled' => ErmModuleRegistry::isEnabled('procedure'),
                ],
                [
                    'label' => 'MedicationRequest',
                    'desc' => 'Resep & pemberian obat',
                    'count' => $totalObatERM,
                    'required' => false,
                    'enabled' => ErmModuleRegistry::isEnabled('medication'),
                ],
                [
                    'label' => 'DiagnosticReport Lab',
                    'desc' => 'Hasil pemeriksaan laboratorium',
                    'count' => $totalLabERM,
                    'required' => false,
                    'enabled' => ErmModuleRegistry::isEnabled('lab'),
                ],
                [
                    'label' => 'DiagnosticReport Radiologi',
                    'desc' => 'Hasil pemeriksaan radiologi',
                    'count' => $totalRadERM,
                    'required' => false,
                    'enabled' => ErmModuleRegistry::isEnabled('radiologi'),
                ],
                [
                    'label' => 'DiagnosticReport Vital Sign',
                    'desc' => 'Tanda-tanda vital',
                    'count' => $totalVsERM,
                    'required' => false,
                    'enabled' => ErmModuleRegistry::isEnabled('vital_sign'),
                ],
            ];
        @endphp

        <div>
            <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden divide-y divide-zinc-100 dark:divide-primary-dark-700">
            @php $codings = $this->getCodings(); @endphp
            @foreach ($bundleResources as $i => $resource)
                <div class="flex items-start gap-3 px-4 py-2.5 {{ !$resource['enabled'] ? 'opacity-50' : '' }}">
                    <span
                        class="text-xs font-mono text-zinc-400 dark:text-primary-dark-500 w-5 shrink-0 text-right mt-0.5">{{ $i + 1 }}</span>
                    <div class="flex-1 min-w-0">
                        <span
                            class="text-sm font-medium text-zinc-800 dark:text-primary-dark-200">{{ $resource['label'] }}</span>
                        <span
                            class="text-xs text-zinc-400 dark:text-primary-dark-500 ml-2">{{ $resource['desc'] }}</span>
                        
                        {{-- Tampilkan Codings jika ada --}}
                        @if(!empty($codings[$resource['label']]))
                            <div class="mt-2 space-y-1.5 mb-1 pr-4">
                                @foreach($codings[$resource['label']] as $code)
                                    <div class="flex items-center gap-2 text-[11px] bg-zinc-50 dark:bg-primary-dark-800/50 p-1.5 rounded-md border border-zinc-100 dark:border-primary-dark-700">
                                        <span class="font-mono text-zinc-500 dark:text-primary-dark-400 shrink-0 w-24 truncate" title="{{ $code['system'] }}">{{ basename($code['system']) === 'kfa' || basename($code['system']) === 'icd-10' ? basename($code['system']) : $code['system'] }}</span>
                                        <span class="font-bold text-primary-600 dark:text-primary-400">{{ $code['code'] }}</span>
                                        <span class="text-zinc-600 dark:text-primary-dark-300 truncate" title="{{ $code['display'] }}">{{ $code['display'] }}</span>
                                        <span class="ml-auto text-[10px] text-zinc-400 italic shrink-0">{{ $code['type'] ?? '' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 shrink-0 mt-0.5">
                        @if (!$resource['enabled'])
                            <flux:badge color="zinc" size="sm">Nonaktif</flux:badge>
                        @elseif ($resource['count'] > 0)
                            <flux:badge color="green" size="sm" icon="check-circle">
                                {{ $resource['count'] }} item</flux:badge>
                        @elseif ($resource['required'])
                            <flux:badge color="red" size="sm" icon="x-circle">Tidak ada</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">Kosong</flux:badge>
                        @endif
                    </div>
                </div>
            @endforeach
            </div>
        </div>
    </div>

    {{-- History Content --}}
    <div x-show="ermSubTab === 'history'" x-cloak class="-mx-5 -mb-5">
        @include('pages.erm.detail-tabs._bpjs.erm-history')
    </div>
</div>
