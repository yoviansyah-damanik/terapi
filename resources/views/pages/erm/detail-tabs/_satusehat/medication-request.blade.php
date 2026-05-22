<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Medication Request
        <flux:badge color="{{ $ssMedications->count() > 0 ? 'primary' : 'zinc' }}" size="sm">
            {{ $ssMedications->count() }}</flux:badge>
    </p>
    <x-atoms.button wire:click="sendSsMedicationRequests" wire:loading.attr="disabled" icon="paper-airplane" size="sm"
        :disabled="!$prereq_location_apotek">
        <span wire:loading.remove wire:target="sendSsMedicationRequests">Kirim Med. Request</span>
        <span wire:loading wire:target="sendSsMedicationRequests">Mengirim...</span>
    </x-atoms.button>
</div>
@if (!$prereq_location_apotek && $medList->isNotEmpty())
    <div class="flex items-center gap-2.5 px-4 py-3 bg-amber-50 dark:bg-amber-900/10 border-b border-amber-100 dark:border-amber-800/30">
        <flux:icon name="lock-closed" class="w-4 h-4 text-amber-500 shrink-0" />
        <p class="text-xs text-amber-700 dark:text-amber-400">
            <strong>Location Apotek belum dikonfigurasi:</strong> Daftarkan Location dengan type <span class="font-semibold">apotek</span> di menu Satu Sehat → FHIR Resource → Location sebelum dapat mengirim Medication Request.
        </p>
    </div>
@endif

@if ($medList->isNotEmpty())
    @php
        $medKfaMap  = \App\Models\Mapping\MedicationMap::whereIn('local_code', $medList->pluck('kode_brng'))
            ->get()->keyBy('local_code');
        $kfaCodes   = $medKfaMap->pluck('kfa_code')->filter()->unique()->values()->toArray();
        $ssIhsMap   = \App\Models\SatuSehat\SatuSehatMedication::whereIn('kfa_code', $kfaCodes)
            ->pluck('ihs_number', 'kfa_code');
    @endphp
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked ? $wire.set('ssSelectedMedications', {{ $medList->pluck('idStr')->toJson() }}) : $wire.set('ssSelectedMedications', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">Nama Obat</th>
                    <th class="{{ $thClass }}">Jumlah</th>
                    <th class="{{ $thClass }}">Waktu</th>
                    <th class="{{ $thClass }}">Prasyarat</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($medList as $med)
                    @php
                        $syncedData = $ssMedications->where('local_id', $med->idStr)->first();
                        $kfaMap     = $medKfaMap->get($med->kode_brng);
                        $isMapped   = $kfaMap && $kfaMap->kfa_code;
                        $ihsNumber  = $isMapped ? ($ssIhsMap->get($kfaMap->kfa_code) ?? null) : null;
                        $hasIhs     = (bool) $ihsNumber;
                        $isReady    = $isMapped && $hasIhs;
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedData)
                                <flux:icon name="check-circle" variant="solid" class="w-5 h-5 text-green-500 mx-auto" />
                            @elseif ($isReady)
                                <input type="checkbox" wire:model="ssSelectedMedications" value="{{ $med->idStr }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @else
                                <flux:icon name="lock-closed"
                                    class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 mx-auto"
                                    title="{{ !$isMapped ? 'Obat belum di-mapping ke KFA' : 'IHS Medication belum terbit di Satu Sehat' }}" />
                            @endif
                        </td>
                        <td class="{{ $tdText }}">
                            <div class="font-medium">{{ $med->nama_brng }}</div>
                            <div class="text-[11px] font-mono text-zinc-400">{{ $med->kode_brng }}</div>
                        </td>
                        <td class="{{ $tdText }}">{{ $med->jml }}</td>
                        <td class="{{ $tdMuted }} text-xs">{{ $med->tgl }} {{ $med->jam }}</td>
                        <td class="px-4 py-2">
                            @if (!$isMapped)
                                <flux:badge color="red" size="sm" icon="x-circle">Belum di-mapping</flux:badge>
                            @elseif (!$hasIhs)
                                <div class="flex flex-col gap-0.5">
                                    <flux:badge color="amber" size="sm" icon="clock">IHS belum terbit</flux:badge>
                                    <span class="text-[10px] font-mono text-zinc-400">{{ $kfaMap->kfa_code }}</span>
                                </div>
                            @else
                                <div class="flex flex-col gap-0.5">
                                    <flux:badge color="emerald" size="sm" icon="check-circle">Siap Kirim</flux:badge>
                                    <span class="text-[10px] font-mono text-zinc-400">{{ $ihsNumber }}</span>
                                </div>
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
                                <span class="text-zinc-400">Belum dikirim</span>
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
        <flux:icon name="beaker" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data Pemberian
            Obat
            untuk kunjungan ini.</p>
    </div>
@endif
