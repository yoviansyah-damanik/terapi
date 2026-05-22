@php
    $spLabIds = $periksaLabsPk
        ->map(
            fn($l) => "'" .
                $this->reg->no_rawat .
                '-SPEC_LAB_' .
                $l->kd_jenis_prw .
                '-' .
                ($l->tgl_periksa ? \Carbon\Carbon::parse($l->tgl_periksa)->format('Ymd') : '') .
                '-' .
                str_replace(':', '', $l->jam ?? '') .
                "'",
        )
        ->implode(',');
    $spRadItems = collect();
    foreach ($permintaanRadiologis as $pr) {
        foreach ($pr->periksa_rad as $r) {
            $spRadItems->push(
                (object) [
                    'noorder' => $pr->noorder,
                    'kd_jenis_prw' => $r->kd_jenis_prw,
                    'jenisPerawatan' => $r->jenisPerawatan,
                    'tgl_periksa' => $r->tgl_periksa,
                    'jam' => $r->jam,
                ],
            );
        }
    }
    $spRadIds = $spRadItems
        ->map(function ($r) {
            $tgl = $r->tgl_periksa ? \Carbon\Carbon::parse($r->tgl_periksa)->format('Ymd') : '';
            $jam = str_replace(':', '', $r->jam ?? '');
            return "'" . $this->reg->no_rawat . '-SPEC_RAD_' . $r->noorder . '-' . $tgl . '-' . $jam . "'";
        })
        ->implode(',');
@endphp
<div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-zinc-100 dark:border-primary-dark-700">
    <div class="flex gap-1 p-1 bg-zinc-100 rounded-lg dark:bg-primary-dark-900/50 shrink-0">
        <button @click="spSubTab = 'lab'"
            :class="spSubTab === 'lab' ?
                'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-700 dark:text-zinc-100' :
                'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400'"
            class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-all">
            <flux:icon name="beaker" class="w-3.5 h-3.5" />
            Laboratorium
            @if ($periksaLabsPk->isNotEmpty())
                <flux:badge color="zinc" size="sm">{{ $periksaLabsPk->count() }}
                </flux:badge>
            @endif
        </button>
        {{-- Tab Specimen Radiologi disembunyikan --}}
    </div>
    <div x-show="spSubTab === 'lab'">
        <x-atoms.button wire:click="sendSsLabSpecimens" wire:loading.attr="disabled" icon="paper-airplane"
            size="sm">
            <span wire:loading.remove wire:target="sendSsLabSpecimens">Kirim Specimen Lab</span>
            <span wire:loading wire:target="sendSsLabSpecimens">Mengirim...</span>
        </x-atoms.button>
    </div>
</div>
<div x-show="spSubTab === 'lab'">
    @if ($periksaLabsPk->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th class="{{ $thClass }} w-16 text-center">
                            <input type="checkbox"
                                x-on:change="$el.checked ? $wire.set('ssSelectedSpecimens', [{{ $spLabIds }}]) : $wire.set('ssSelectedSpecimens', [])"
                                class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                        </th>
                        <th class="{{ $thClass }}">Kode</th>
                        <th class="{{ $thClass }}">Nama Pemeriksaan</th>
                        <th class="{{ $thClass }}">Waktu Pengambilan</th>
                        <th class="{{ $thClass }}">Prasyarat</th>
                        <th class="{{ $thClass }}">Status Sinkronisasi</th>
                        <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                    @foreach ($periksaLabsPk as $l)
                        @php
                            $idStr =
                                $this->reg->no_rawat .
                                '-SPEC_LAB_' .
                                $l->kd_jenis_prw .
                                '-' .
                                ($l->tgl_periksa ? \Carbon\Carbon::parse($l->tgl_periksa)->format('Ymd') : '') .
                                '-' .
                                str_replace(':', '', $l->jam ?? '');
                            $srIdStr = str_replace('SPEC_LAB_', 'SR_LAB_', $idStr);
                            $hasSR = $ssServiceRequests->where('local_id', $srIdStr)->isNotEmpty();
                            $syncedData = $ssSpecimens->where('local_id', $idStr)->first();
                        @endphp
                        <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-2 text-center">
                                @if ($syncedData)
                                    <flux:icon name="check-circle" variant="solid"
                                        class="w-5 h-5 text-green-500 mx-auto" />
                                @elseif (!$hasSR)
                                    <flux:icon name="lock-closed"
                                        class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 mx-auto"
                                        title="Prasyarat belum terpenuhi" />
                                @else
                                    <input type="checkbox" wire:model="ssSelectedSpecimens" value="{{ $idStr }}"
                                        class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                                @endif
                            </td>
                            <td class="{{ $tdMono }}">{{ $l->kd_jenis_prw }}</td>
                            <td class="{{ $tdText }}">
                                {{ $l->jenisPerawatan?->nm_perawatan ?? '-' }}</td>
                            <td class="{{ $tdMuted }} text-xs">
                                {{ $l->tgl_periksa ? \Carbon\Carbon::parse($l->tgl_periksa)->format('d/m/Y') : '-' }}
                                {{ $l->jam }}</td>
                            <td class="px-4 py-2">
                                @if ($hasSR)
                                    <flux:badge color="emerald" size="sm" icon="check-circle">Serv. Request
                                    </flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="x-circle">Belum ada SR</flux:badge>
                                @endif
                            </td>
                            <td class="{{ $tdMuted }}">
                                @if ($syncedData)
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                        <span class="text-[10px] font-mono">{{ $syncedData->ihs_number }}</span>
                                        <span
                                            class="text-[10px]">{{ $syncedData->synced_at?->format('d/m/Y H:i') }}</span>
                                    </div>
                                @else
                                    <span class="text-zinc-400">Belum didaftarkan</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if ($syncedData)
                                    <button type="button" wire:click="openSsDetail('{{ $syncedData->ihs_number }}')"
                                        class="p-1.5 rounded-lg text-zinc-400 hover:text-sky-600 hover:bg-sky-50 dark:text-primary-dark-500 dark:hover:text-sky-400 dark:hover:bg-sky-900/20 transition-colors"
                                        title="Lihat detail sinkronisasi">
                                        <flux:icon name="eye" class="w-4 h-4" />
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="flex flex-col items-center py-8">
            <flux:icon name="eye-dropper" class="w-8 h-8 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data Lab
                untuk
                kunjungan ini.</p>
        </div>
    @endif
</div>

{{-- Specimen Radiologi --}}
<div x-show="spSubTab === 'rad'" x-cloak>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked ? $wire.set('ssSelectedSpecimens', [{{ $spRadIds }}]) : $wire.set('ssSelectedSpecimens', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">Kode</th>
                    <th class="{{ $thClass }}">Nama Pemeriksaan</th>
                    <th class="{{ $thClass }}">Waktu Pengambilan</th>
                    <th class="{{ $thClass }}">Prasyarat</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($spRadItems as $r)
                    @php
                        $tglR = $r->tgl_periksa ? \Carbon\Carbon::parse($r->tgl_periksa)->format('Ymd') : '';
                        $jamR = str_replace(':', '', $r->jam ?? '');
                        $idStr = $this->reg->no_rawat . '-SPEC_RAD_' . $r->noorder . '-' . $tglR . '-' . $jamR;
                        $syncedData = $ssSpecimens->where('local_id', $idStr)->first();
                        $hasSR = $ssServiceRequests->contains(
                            fn($sr) => str_contains($sr->local_id, "SR_RAD_{$r->noorder}"),
                        );
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedData)
                                <flux:icon name="check-circle" variant="solid"
                                    class="w-5 h-5 text-green-500 mx-auto" />
                            @elseif (!$hasSR)
                                <flux:icon name="lock-closed"
                                    class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 mx-auto"
                                    title="Prasyarat belum terpenuhi" />
                            @else
                                <input type="checkbox" wire:model="ssSelectedSpecimens" value="{{ $idStr }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </td>
                        <td class="{{ $tdMono }}">{{ $r->kd_jenis_prw }}</td>
                        <td class="{{ $tdText }}">
                            {{ $r->jenisPerawatan?->nm_perawatan ?? '-' }}</td>
                        <td class="{{ $tdMuted }} text-xs">
                            {{ $r->tgl_periksa ? \Carbon\Carbon::parse($r->tgl_periksa)->format('d/m/Y') : '-' }}
                            {{ $r->jam }}</td>
                        <td class="px-4 py-2">
                            @if ($hasSR)
                                <flux:badge color="emerald" size="sm" icon="check-circle">Serv. Request
                                </flux:badge>
                            @else
                                <flux:badge color="red" size="sm" icon="x-circle">Belum ada SR</flux:badge>
                            @endif
                        </td>
                        <td class="{{ $tdMuted }}">
                            @if ($syncedData)
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                    <span class="text-[10px] font-mono">{{ $syncedData->ihs_number }}</span>
                                    <span
                                        class="text-[10px]">{{ $syncedData->synced_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            @else
                                <span class="text-zinc-400">Belum didaftarkan</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-center">
                            @if ($syncedData)
                                <button type="button" wire:click="openSsDetail('{{ $syncedData->ihs_number }}')"
                                    class="p-1.5 rounded-lg text-zinc-400 hover:text-sky-600 hover:bg-sky-50 dark:text-primary-dark-500 dark:hover:text-sky-400 dark:hover:bg-sky-900/20 transition-colors"
                                    title="Lihat detail sinkronisasi">
                                    <flux:icon name="eye" class="w-4 h-4" />
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
