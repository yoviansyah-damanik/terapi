

<div x-data="{ activeTab: 'diagnosa', labTab: 'pk', obatTab: 'medis' }">
    @php
        $isVaksin = fn($item) => str_starts_with(strtolower($item->dataBarang?->nama_brng ?? ''), 'vaksin');
        $obatMedis = $obats->filter(fn($o) => !$isVaksin($o))->values();
        $vaksin = $obats->filter($isVaksin)->values();
        $resepPulangMedis = $resepPulangs->filter(fn($r) => !$isVaksin($r))->values();
        $resepPulangVaksin = $resepPulangs->filter($isVaksin)->values();
        $totalObat = $obatMedis->count() + $resepPulangMedis->count();
        $totalVaksin = $vaksin->count() + $resepPulangVaksin->count();
        $totalLab = $periksaLabsPk->count() + $periksaLabsMb->count() + $detailPeriksaLabsPa->count();
        $totalRadiologi = $periksaRadiologis->count();
        $totalUsg = collect($usgResults)->sum(fn($u) => $u['data']->count());
        $totalTindakan =
            $tindakanJalanDr->count() +
            $tindakanJalanPr->count() +
            $tindakanJalanDrPr->count() +
            $tindakanInapDr->count() +
            $tindakanInapPr->count() +
            $tindakanInapDrPr->count();
        $totalOperasi = $laporanOperasis->count();
    @endphp

    {{-- Data Sub-tab Navigation --}}
    @php
        $dataTabs = [
            ['diagnosa', 'clipboard-document-list', 'Diagnosa', $diagnosas->count(), 'Diagnosa ICD-10 pasien'],
            ['prosedur', 'wrench-screwdriver', 'Prosedur', $prosedurs->count(), 'Tindakan/prosedur bedah'],
            ['tindakan', 'hand-raised', 'Tindakan', $totalTindakan, 'Tindakan medis tercatat'],
            [
                'pemeriksaan',
                'clipboard-document-check',
                'Pemeriksaan',
                $pemeriksaans->count(),
                'Tanda-tanda vital/fisik',
            ],
            ['obat', 'beaker', 'Obat', $totalObat, 'Medikasi & peresepan'],
            ['vaksin', 'shield-check', 'Vaksin', $totalVaksin, 'Data vaksinasi pasien'],
            ['lab', 'chart-bar', 'Laboratorium', $totalLab, 'Pemeriksaan Patologi/Klinik'],
            ['radiologi', 'camera', 'Radiologi', $totalRadiologi, 'Pemeriksaan radiologi'],
            ['usg', 'signal', 'USG', $totalUsg, 'Hasil pemeriksaan USG'],
            ['operasi', 'scissors', 'Operasi', $totalOperasi, 'Laporan operasi'],
            ['alergi', 'exclamation-triangle', 'Alergi', $alergiPasiens->count(), 'Peringatan alergi pasien'],
            ['resume', 'document-text', 'Resume', null, 'Resume medis akhir'],
        ];
    @endphp
    <div class="flex flex-col lg:flex-row gap-6 mt-4">

        {{-- Sidebar Navigasi --}}
        <aside class="w-full lg:w-64 shrink-0">
            <div
                class="bg-white lg:sticky lg:top-8 relative dark:bg-primary-dark-800 lg:rounded-2xl lg:border border-zinc-200 dark:border-primary-dark-700 lg:shadow-sm overflow-hidden flex flex-col gap-1 lg:block">

                <div
                    class="hidden lg:block px-4 pt-4 pb-2 border-b border-zinc-100 dark:border-primary-dark-700/50 mb-2">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500">
                        Data Kunjungan</p>
                </div>

                {{-- Horizontal Layout for Mobile / Tablet --}}
                <div class="flex lg:hidden gap-1 p-1 overflow-x-auto bg-zinc-100 rounded-xl dark:bg-primary-dark-900/50">
                    @foreach ($dataTabs as [$key, $icon, $label, $count, $desc])
                        <button @click="activeTab = '{{ $key }}'"
                            :class="activeTab === '{{ $key }}'
                                ?
                                'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-800 dark:text-primary-dark-100' :
                                'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400 dark:hover:text-primary-dark-200'"
                            class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-all whitespace-nowrap border dark:border-primary-dark-700/50"
                            :class="activeTab === '{{ $key }}' ? 'border-zinc-200 dark:border-primary-dark-600' :
                                'border-transparent'">
                            <flux:icon name="{{ $icon }}" class="h-4 w-4" />
                            {{ $label }}
                            @if ($count !== null && $count > 0)
                                <flux:badge color="primary" size="sm">{{ $count }}</flux:badge>
                            @elseif ($count !== null)
                                <flux:badge color="zinc" size="sm">{{ $count }}</flux:badge>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- Vertical Layout for Desktop --}}
                <nav class="hidden lg:flex flex-col px-2 pb-2 space-y-0.5">
                    @foreach ($dataTabs as [$key, $icon, $label, $count, $desc])
                        <button @click="activeTab = '{{ $key }}'"
                            :class="activeTab === '{{ $key }}'
                                ?
                                'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300' :
                                'text-zinc-600 dark:text-primary-dark-400 hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40 hover:text-zinc-800 dark:hover:text-primary-dark-200'"
                            class="group flex items-center w-full gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors text-left relative">
                            <flux:icon name="{{ $icon }}" class="h-5 w-5 shrink-0" />
                            <div class="flex-1 flex flex-col min-w-0 pr-8">
                                <span class="truncate">{{ $label }}</span>
                                <span
                                    :class="activeTab === '{{ $key }}' ? 'text-primary-500 dark:text-primary-400' :
                                        'text-zinc-400 dark:text-primary-dark-500 group-hover:text-zinc-500'"
                                    class="text-[10px] font-normal truncate">{{ $desc }}</span>
                            </div>
                            @if ($count !== null && $count > 0)
                                <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <flux:badge color="primary" size="sm">{{ $count }}</flux:badge>
                                </div>
                            @elseif ($count !== null)
                                <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <flux:badge color="zinc" size="sm">{{ $count }}</flux:badge>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </nav>

            </div>
        </aside>

        {{-- Konten Dinamis --}}
        <div class="flex-1 min-w-0">

            {{-- Data Tab: Diagnosa --}}
            <div x-show="activeTab === 'diagnosa'" x-cloak>
                @include('pages.erm.detail-tabs._admission.diagnosa')
            </div>

            {{-- Data Tab: Prosedur --}}
            <div x-show="activeTab === 'prosedur'" x-cloak>
                @include('pages.erm.detail-tabs._admission.prosedur')
            </div>

            {{-- Data Tab: Tindakan --}}
            <div x-show="activeTab === 'tindakan'" x-cloak>
                @include('pages.erm.detail-tabs._admission.tindakan')
            </div>

            {{-- Data Tab: Pemeriksaan --}}
            <div x-show="activeTab === 'pemeriksaan'" x-cloak>
                @include('pages.erm.detail-tabs._admission.pemeriksaan')
            </div>

            {{-- Data Tab: Obat --}}
            <div x-show="activeTab === 'obat'" x-cloak>
                @include('pages.erm.detail-tabs._admission.obat')
            </div>

            {{-- Data Tab: Vaksin --}}
            <div x-show="activeTab === 'vaksin'" x-cloak>
                @include('pages.erm.detail-tabs._admission.vaksin')
            </div>

            {{-- Data Tab: Laboratorium --}}
            <div x-show="activeTab === 'lab'" x-cloak>
                @include('pages.erm.detail-tabs._admission.laboratorium')
            </div>

            {{-- Data Tab: Radiologi --}}
            <div x-show="activeTab === 'radiologi'" x-cloak>
                @include('pages.erm.detail-tabs._admission.radiologi')
            </div>
            
            {{-- Data Tab: USG --}}
            <div x-show="activeTab === 'usg'" x-cloak>
                @include('pages.erm.detail-tabs._admission.usg')
            </div>

            {{-- Data Tab: Alergi --}}
            <div x-show="activeTab === 'alergi'" x-cloak>
                @include('pages.erm.detail-tabs._admission.alergi')
            </div>

            {{-- Data Tab: Operasi --}}
            <div x-show="activeTab === 'operasi'" class="space-y-4" x-cloak>
                @forelse ($laporanOperasis as $op)
                    <div
                        class="overflow-hidden bg-white rounded-2xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                        <div class="px-4 py-3 bg-zinc-50/80 border-b border-zinc-200/80 dark:bg-primary-dark-900/40 dark:border-primary-dark-700/60 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <flux:icon name="scissors" class="w-4 h-4 text-zinc-400" />
                                <span class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">Laporan Operasi</span>
                                <span class="text-xs text-zinc-400 font-mono">— {{ $op->tanggal }}</span>
                            </div>
                            <span class="text-xs text-zinc-400">Selesai: {{ $op->selesaioperasi }}</span>
                        </div>
                        <div class="p-4 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/20 border border-zinc-100 dark:border-primary-dark-700/50">
                                    <label class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Diagnosa Pre-Op</label>
                                    <p class="mt-1 text-sm text-zinc-800 dark:text-primary-dark-100">{{ $op->diagnosa_preop ?: '-' }}</p>
                                </div>
                                <div class="p-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/20 border border-zinc-100 dark:border-primary-dark-700/50">
                                    <label class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Diagnosa Post-Op</label>
                                    <p class="mt-1 text-sm text-zinc-800 dark:text-primary-dark-100">{{ $op->diagnosa_postop ?: '-' }}</p>
                                </div>
                            </div>
                            @if($op->jaringan_dieksekusi)
                            <div>
                                <label class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Jaringan Dieksekusi</label>
                                <p class="mt-1 text-sm text-zinc-700 dark:text-primary-dark-300">{{ $op->jaringan_dieksekusi }}</p>
                            </div>
                            @endif
                            <div>
                                <label class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Isi Laporan</label>
                                <div class="mt-2 p-3 rounded-xl bg-zinc-50/50 dark:bg-primary-dark-900/10 border border-zinc-100 dark:border-primary-dark-700/30 text-sm text-zinc-700 dark:text-primary-dark-300 whitespace-pre-wrap leading-relaxed">
                                    {!! nl2br(e($op->laporan_operasi)) !!}
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 text-zinc-400">
                        <flux:icon name="scissors" class="w-12 h-12 mb-2 opacity-20" />
                        <p class="text-sm italic">Tidak ada laporan operasi untuk kunjungan ini.</p>
                    </div>
                @endforelse
            </div>

            {{-- Data Tab: Resume --}}
            <div x-show="activeTab === 'resume'" x-cloak>
                @include('pages.erm.detail-tabs._admission.resume')
            </div>
        </div>
    </div>
</div>
