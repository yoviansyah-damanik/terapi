<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Allergy Intolerance
        <flux:badge color="{{ $ssAllergyIntolerances->count() > 0 ? 'primary' : 'zinc' }}" size="sm">
            {{ $ssAllergyIntolerances->count() }}</flux:badge>
    </p>
    <x-atoms.button wire:click="sendSsAllergyIntolerances" wire:loading.attr="disabled" icon="paper-airplane"
        size="sm">
        <span wire:loading.remove wire:target="sendSsAllergyIntolerances">Kirim Alergi</span>
        <span wire:loading wire:target="sendSsAllergyIntolerances">Mengirim...</span>
    </x-atoms.button>
</div>
@php
    $allergyList = collect();
    if (isset($alergiPasiens)) {
        $allergyMapsCache = \App\Models\Mapping\AllergyMap::getCached();
        $reactionMapsCache = \App\Models\Mapping\AllergyReactionMap::getCached();
        foreach ($alergiPasiens as $a) {
            $idStr =
                $this->reg->no_rawat . '-AI_' . $a->id_alergi . '-' . $a->tanggal->format('Ymd') . '-' . str_replace(':', '', $a->jam ?? '000000');
            $allergyList->push(
                (object) [
                    'idStr' => $idStr,
                    'alergi' => $a->alergi?->nama_alergi ?? '-',
                    'reaksi' => $a->reaksi?->nama_reaksi ?? '-',
                    'keparahan' => $a->tingkatKeparahan?->keparahan ?? '-',
                    'waktu' => ($a->tanggal ? $a->tanggal->format('d/m/Y') : '') . ' ' . $a->jam,
                    'hasAllergyMap' => $allergyMapsCache->has($a->id_alergi),
                    'hasReactionMap' => $a->id_reaksi ? $reactionMapsCache->has($a->id_reaksi) : true,
                ],
            );
        }
    }
    $mappableIds = $allergyList->where('hasAllergyMap', true)->pluck('idStr')->toJson();
@endphp
@if ($allergyList->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked ? $wire.set('ssSelectedAllergyIntolerances', {{ $mappableIds }}) : $wire.set('ssSelectedAllergyIntolerances', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">Alergen</th>
                    <th class="{{ $thClass }}">Reaksi</th>
                    <th class="{{ $thClass }}">Tingkat Keparahan</th>
                    <th class="{{ $thClass }}">Waktu Tercatat</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($allergyList as $al)
                    @php
                        $syncedData = $ssAllergyIntolerances->where('local_id', $al->idStr)->first();
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedData)
                                <flux:icon name="check-circle" variant="solid" class="w-5 h-5 text-green-500 mx-auto" />
                            @elseif (!$al->hasAllergyMap)
                                <flux:icon name="exclamation-triangle" variant="solid"
                                    class="w-5 h-5 text-amber-400 mx-auto"
                                    title="Alergi belum dipetakan ke SNOMED CT" />
                            @else
                                <input type="checkbox" wire:model="ssSelectedAllergyIntolerances"
                                    value="{{ $al->idStr }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </td>
                        <td class="{{ $tdText }}">
                            {{ $al->alergi }}
                            @if (!$al->hasReactionMap && $al->reaksi !== '-')
                                <flux:badge color="amber" size="sm" class="ml-1">
                                    Reaksi
                                    belum dipetakan</flux:badge>
                            @endif
                        </td>
                        <td class="{{ $tdText }}">{{ $al->reaksi }}</td>
                        <td class="{{ $tdText }}">{{ $al->keparahan }}</td>
                        <td class="{{ $tdMuted }} text-xs">{{ $al->waktu }}</td>
                        <td class="{{ $tdMuted }}">
                            @if ($syncedData)
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                    <span class="text-[10px] font-mono">{{ $syncedData->ihs_number }}</span>
                                    <span class="text-[10px]">{{ $syncedData->synced_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            @elseif (!$al->hasAllergyMap)
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-amber-500 dark:text-amber-400 font-medium text-xs">Pemetaan
                                        diperlukan</span>
                                    <a href="{{ route('local.allergy.allergy') }}"
                                        class="text-[10px] text-primary-600 dark:text-primary-400 hover:underline">
                                        Petakan di Local Terminology →
                                    </a>
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
        <flux:icon name="shield-exclamation" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data Allergy
            Intolerance untuk kunjungan ini.</p>
    </div>
@endif
