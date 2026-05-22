<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Immunization
        <flux:badge color="{{ $vaksin->count() > 0 ? 'primary' : 'zinc' }}" size="sm">
            {{ $vaksin->count() }}</flux:badge>
    </p>
    <x-atoms.button wire:click="sendSsVaksinFromObat" wire:loading.attr="disabled" icon="paper-airplane" size="sm">
        <span wire:loading.remove wire:target="sendSsVaksinFromObat">Kirim Vaksin</span>
        <span wire:loading wire:target="sendSsVaksinFromObat">Mengirim...</span>
    </x-atoms.button>
</div>
@php
    $vaksinKfaMaps = \App\Models\Mapping\MedicationMap::whereIn('local_code', $vaksin->pluck('kode_brng')->unique())
        ->whereNotNull('kfa_code')
        ->pluck('kfa_code', 'local_code');
    $vaksinObatList = $vaksin->map(function ($v) use ($vaksinKfaMaps) {
        $rawTanggal = $v->getRawOriginal('tgl_perawatan') ?? '';
        $medMap = $vaksinKfaMaps->has($v->kode_brng);
        return (object) [
            'idStr' => $this->reg->no_rawat . '-IMM_' . $v->kode_brng . '-' . str_replace('-', '', $rawTanggal) . '-' . str_replace(':', '', $v->jam ?? ''),
            'nama' => $v->dataBarang?->nama_brng ?? $v->kode_brng,
            'kode' => $v->kode_brng,
            'kfaCode' => $vaksinKfaMaps->get($v->kode_brng),
            'waktu' => ($v->tgl_perawatan?->format('d/m/Y') ?? '') . ' ' . $v->jam,
            'jumlah' => $v->jml,
            'noBatch' => $v->no_batch,
            'expDate' => $v->dataBatch?->tgl_kadaluarsa,
            'hasMedMap' => $medMap,
        ];
    });
    $vaksinObatMappableIds = $vaksinObatList->where('hasMedMap', true)->pluck('idStr')->toJson();
@endphp
@if ($vaksinObatList->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked ? $wire.set('ssSelectedVaksinObat', {{ $vaksinObatMappableIds }}) : $wire.set('ssSelectedVaksinObat', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">Nama Vaksin</th>
                    <th class="{{ $thClass }}">Waktu Pemberian</th>
                    <th class="{{ $thClass }}">Batch / Kadaluarsa</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($vaksinObatList as $vk)
                    @php $syncedData = $ssImmunizations->where('local_id', $vk->idStr)->first(); @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedData)
                                <flux:icon name="check-circle" variant="solid" class="w-5 h-5 text-green-500 mx-auto" />
                            @elseif (!$vk->hasMedMap)
                                <flux:icon name="exclamation-triangle" variant="solid"
                                    class="w-5 h-5 text-amber-400 mx-auto" title="Belum dipetakan ke KFA" />
                            @else
                                <input type="checkbox" wire:model="ssSelectedVaksinObat" value="{{ $vk->idStr }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </td>
                        <td class="{{ $tdText }}">
                            <p class="font-medium">{{ $vk->nama }}</p>
                            <p class="text-xs text-zinc-400">{{ $vk->kode }} &middot;
                                {{ rtrim(rtrim(number_format($vk->jumlah, 2, ',', '.'), '0'), ',') }}
                            </p>
                            @if ($vk->kfaCode)
                                <p class="text-[10px] font-mono text-primary-500 dark:text-primary-400">
                                    KFA: {{ $vk->kfaCode }}</p>
                            @else
                                <p class="text-[10px] text-amber-500 dark:text-amber-400">Belum
                                    dipetakan ke KFA &mdash; <a href="{{ route('local.medication.vaccine') }}"
                                        class="underline">Petakan</a></p>
                            @endif
                        </td>
                        <td class="{{ $tdMuted }} text-xs">{{ $vk->waktu }}</td>
                        <td class="{{ $tdMuted }} text-xs">
                            @if ($vk->noBatch)
                                <span class="block">Batch: <span
                                        class="font-medium text-zinc-700 dark:text-primary-dark-200">{{ $vk->noBatch }}</span></span>
                            @endif
                            @if ($vk->expDate)
                                <span
                                    class="block {{ $vk->expDate->isPast() ? 'text-red-500 dark:text-red-400 font-semibold' : '' }}">
                                    Exp: {{ $vk->expDate->format('d/m/Y') }}
                                </span>
                            @endif
                            @if (!$vk->noBatch && !$vk->expDate)
                                <span class="text-zinc-400">-</span>
                            @endif
                        </td>
                        <td class="{{ $tdMuted }}">
                            @if ($syncedData)
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                    <span class="text-[10px] font-mono">{{ $syncedData->ihs_number }}</span>
                                    <span class="text-[10px]">{{ $syncedData->synced_at?->format('d/m/Y H:i') }}</span>
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
    <div
        class="flex flex-col items-center py-10 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
        <flux:icon name="shield-check" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada vaksin pada
            kunjungan ini.</p>
    </div>
@endif
