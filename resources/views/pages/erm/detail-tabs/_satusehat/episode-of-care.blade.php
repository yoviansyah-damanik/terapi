{{-- Header --}}
<div class="flex items-center gap-3 px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <div class="flex-1">
        <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Episode of Care
        </p>
        <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">
            Program perawatan jangka panjang berdasarkan mapping ICD-10
        </p>
    </div>
    <a href="{{ route('local.episode-of-care.index') }}" wire:navigate
        class="text-xs text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1">
        <flux:icon name="map" class="w-3.5 h-3.5" />
        Kelola Mapping
    </a>
</div>

<div class="p-4 space-y-5">

    {{-- Sudah Terkirim --}}
    @if ($ssEpisodeOfCares->isNotEmpty())
        <div>
            <p class="mb-2 text-[10px] font-bold tracking-widest uppercase text-zinc-400 dark:text-primary-dark-500">
                Terkirim ({{ $ssEpisodeOfCares->count() }})
            </p>
            <div class="space-y-2">
                @foreach ($ssEpisodeOfCares as $eoc)
                    <div
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-900/30">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <flux:badge size="sm" color="zinc" class="font-mono font-bold">
                                    {{ $eoc->type_code }}
                                </flux:badge>
                                @php
                                    $eocStatusColor = match ($eoc->status) {
                                        'active' => 'emerald',
                                        'finished' => 'zinc',
                                        'onhold' => 'amber',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge size="sm" :color="$eocStatusColor">
                                    {{ $eoc->status }}
                                </flux:badge>
                            </div>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-1 font-mono">
                                {{ $eoc->type_display }}
                                @if ($eoc->period_start)
                                    · {{ $eoc->period_start->format('d/m/Y') }}
                                @endif
                            </p>
                        </div>
                        @if ($eoc->status === 'active')
                            <x-atoms.button size="sm" variant="ghost" icon="check-badge"
                                wire:click="updateSsEpisodeOfCareStatus('{{ $eoc->local_id }}', 'finished')"
                                tooltip="Selesaikan episode" />
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Belum Terkirim (terdeteksi) --}}
    @if ($ssDetectedEpisodes->isNotEmpty())
        <div>
            <p class="mb-2 text-[10px] font-bold tracking-widest uppercase text-zinc-400 dark:text-primary-dark-500">
                Terdeteksi, Belum Terkirim ({{ $ssDetectedEpisodes->count() }})
            </p>
            <div class="space-y-2">
                @foreach ($ssDetectedEpisodes as $ep)
                    <div
                        class="flex items-start gap-3 px-3 py-2.5 rounded-xl border
                                        {{ !$ep['prereq']['passed'] ? 'border-red-200 dark:border-red-800/50 bg-red-50/50 dark:bg-red-900/10' : ($ep['prereq']['warning'] ? 'border-amber-200 dark:border-amber-800/50 bg-amber-50/50 dark:bg-amber-900/10' : 'border-zinc-200 dark:border-primary-dark-700') }}">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <flux:badge size="sm" color="violet" class="font-mono font-bold">
                                    {{ $ep['eoc_code'] }}
                                </flux:badge>
                                @foreach (array_slice($ep['icd10_codes'], 0, 4) as $icd)
                                    <flux:badge size="sm" color="zinc" class="font-mono text-[10px]">
                                        {{ $icd }}
                                    </flux:badge>
                                @endforeach
                                @if (count($ep['icd10_codes']) > 4)
                                    <span class="text-[10px] text-zinc-400">+{{ count($ep['icd10_codes']) - 4 }}</span>
                                @endif
                            </div>
                            @if ($ep['first_date'])
                                <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">
                                    Sejak
                                    {{ \Carbon\Carbon::parse($ep['first_date'])->format('d/m/Y') }}
                                </p>
                            @endif
                            @if (!$ep['prereq']['passed'] || $ep['prereq']['warning'])
                                <p
                                    class="text-xs mt-1 {{ !$ep['prereq']['passed'] ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400' }}">
                                    {{ $ep['prereq']['message'] }}
                                </p>
                            @endif
                        </div>
                        @if ($ep['prereq']['passed'])
                            <x-atoms.button size="sm" variant="ghost" icon="paper-airplane"
                                wire:click="sendSsEpisodeOfCare('{{ $ep['eoc_code'] }}', '{{ $ep['first_date'] }}')"
                                :tooltip="$ep['prereq']['warning'] ? 'Kirim (ada peringatan)' : 'Kirim ke SatuSehat'" />
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Empty state --}}
    @if ($ssEpisodeOfCares->isEmpty() && $ssDetectedEpisodes->isEmpty())
        <div class="py-8 text-center">
            <flux:icon name="document-magnifying-glass"
                class="w-10 h-10 mx-auto text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm font-medium text-zinc-500 dark:text-primary-dark-400">
                Tidak ada episode terdeteksi
            </p>
            <p class="mt-1 text-xs text-zinc-400 dark:text-primary-dark-500">
                Pastikan diagnosa pasien sudah di-mapping di halaman Episode of Care.
            </p>
        </div>
    @endif

</div>
