@php
    $srLabIds = $periksaLabsPk
        ->map(
            fn($l) => "'" .
                $this->reg->no_rawat .
                '-SR_LAB_' .
                $l->kd_jenis_prw .
                '-' .
                ($l->tgl_periksa ? \Carbon\Carbon::parse($l->tgl_periksa)->format('Ymd') : '') .
                '-' .
                str_replace(':', '', $l->jam ?? '') .
                "'",
        )
        ->implode(',');
    $srRadIds = $permintaanRadiologis
        ->map(function ($pr) {
            $tgl = $pr->tgl_permintaan ? \Carbon\Carbon::parse($pr->tgl_permintaan)->format('Ymd') : '';
            $jam = str_replace(':', '', $pr->jam_permintaan ?? '');
            return "'" . $this->reg->no_rawat . '-SR_RAD_' . $pr->noorder . '-' . $tgl . '-' . $jam . "'";
        })
        ->implode(',');
    // Ambil waktu_permintaan dari permintaan_usg, diindex by noorder
    $permintaanUsgByOrder = \App\Models\Simrs\Usg\PermintaanUsg::on('simrs')
        ->where('no_rawat', $this->reg->no_rawat)
        ->get()
        ->keyBy('noorder');

    $usgSrItems = collect();
    foreach ($usgResults as $key => $cfg) {
        foreach ($cfg['data'] as $item) {
            $noorder = $item->noorder ?? null;
            $permintaan = $noorder ? $permintaanUsgByOrder->get($noorder) : null;
            $waktu = $permintaan?->waktu_permintaan ?? \Carbon\Carbon::parse($item->tanggal);
            $usgSrItems->push(
                (object) [
                    'usg_type' => $key,
                    'usg_label' => $cfg['label'],
                    'noorder' => $noorder,
                    'tglFmt' => $waktu->format('Ymd'),
                    'jamFmt' => str_replace(':', '', $waktu->format('H:i:s')),
                    'display' => $waktu->format('d/m/Y H:i'),
                ],
            );
        }
    }
    $srUsgIds = $usgSrItems
        ->map(
            fn($u) => "'" .
                $this->reg->no_rawat .
                '-SR_USG_' .
                strtoupper($u->usg_type) .
                '-' .
                $u->tglFmt .
                '-' .
                $u->jamFmt .
                "'",
        )
        ->implode(',');
@endphp
<div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-zinc-100 dark:border-primary-dark-700">
    <div class="flex gap-1 p-1 bg-zinc-100 rounded-lg dark:bg-primary-dark-900/50 shrink-0">
        <button @click="srSubTab = 'lab'"
            :class="srSubTab === 'lab' ?
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
        @if ($permintaanRadiologis->isNotEmpty())
            <button @click="srSubTab = 'rad'"
                :class="srSubTab === 'rad' ?
                    'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-700 dark:text-zinc-100' :
                    'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400'"
                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-all">
                <flux:icon name="photo" class="w-3.5 h-3.5" />
                Radiologi
                <flux:badge color="zinc" size="sm">{{ $permintaanRadiologis->count() }}</flux:badge>
            </button>
        @endif
        @if ($usgSrItems->isNotEmpty())
            <button @click="srSubTab = 'usg'"
                :class="srSubTab === 'usg' ?
                    'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-700 dark:text-zinc-100' :
                    'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400'"
                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-all">
                <flux:icon name="signal" class="w-3.5 h-3.5" />
                USG
                <flux:badge color="zinc" size="sm">{{ $usgSrItems->count() }}</flux:badge>
            </button>
        @endif
    </div>
    <div x-show="srSubTab === 'lab'">
        <x-atoms.button wire:click="sendSsLabServiceRequests" wire:loading.attr="disabled" icon="paper-airplane"
            size="sm" :disabled="!$prereq_location_lab">
            <span wire:loading.remove wire:target="sendSsLabServiceRequests">Kirim SR Lab</span>
            <span wire:loading wire:target="sendSsLabServiceRequests">Mengirim...</span>
        </x-atoms.button>
    </div>
    <div x-show="srSubTab === 'rad'" x-cloak>
        <x-atoms.button wire:click="sendSsRadServiceRequests" wire:loading.attr="disabled" icon="paper-airplane"
            size="sm" :disabled="!$prereq_location_rad">
            <span wire:loading.remove wire:target="sendSsRadServiceRequests">Kirim SR Rad</span>
            <span wire:loading wire:target="sendSsRadServiceRequests">Mengirim...</span>
        </x-atoms.button>
    </div>
    <div x-show="srSubTab === 'usg'" x-cloak>
        <x-atoms.button wire:click="sendSsUsgServiceRequests" wire:loading.attr="disabled" icon="paper-airplane"
            size="sm">
            <span wire:loading.remove wire:target="sendSsUsgServiceRequests">Kirim SR USG</span>
            <span wire:loading wire:target="sendSsUsgServiceRequests">Mengirim...</span>
        </x-atoms.button>
    </div>
</div>
<div x-show="srSubTab === 'lab'">
    @if (!$prereq_location_lab && $periksaLabsPk->isNotEmpty())
        <div class="flex items-center gap-2.5 px-4 py-3 bg-amber-50 dark:bg-amber-900/10 border-b border-amber-100 dark:border-amber-800/30">
            <flux:icon name="lock-closed" class="w-4 h-4 text-amber-500 shrink-0" />
            <p class="text-xs text-amber-700 dark:text-amber-400">
                <strong>Location Lab belum dikonfigurasi:</strong> Daftarkan Location dengan type <span class="font-semibold">lab</span> di menu Satu Sehat → FHIR Resource → Location sebelum dapat mengirim Service Request Lab.
            </p>
        </div>
    @endif
    @if ($periksaLabsPk->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th class="{{ $thClass }} w-16 text-center">
                            <input type="checkbox"
                                x-on:change="$el.checked ? $wire.set('ssSelectedServiceRequests', [{{ $srLabIds }}]) : $wire.set('ssSelectedServiceRequests', [])"
                                class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                        </th>
                        <th class="{{ $thClass }}">Kode</th>
                        <th class="{{ $thClass }}">Tindakan</th>
                        <th class="{{ $thClass }}">Waktu</th>
                        <th class="{{ $thClass }}">Status Sinkronisasi</th>
                        <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                    @foreach ($periksaLabsPk as $l)
                        @php
                            $tglLab = $l->tgl_periksa ? \Carbon\Carbon::parse($l->tgl_periksa)->format('Ymd') : '';
                            $jamLab = str_replace(':', '', $l->jam ?? '');
                            $idStr = "{$this->reg->no_rawat}-SR_LAB_{$l->kd_jenis_prw}-{$tglLab}-{$jamLab}";
                            $syncedData = $ssServiceRequests->where('local_id', $idStr)->first();
                        @endphp
                        <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-2 text-center">
                                @if ($syncedData)
                                    <flux:icon name="check-circle" variant="solid"
                                        class="w-5 h-5 text-green-500 mx-auto" />
                                @else
                                    <input type="checkbox" wire:model="ssSelectedServiceRequests"
                                        value="{{ $idStr }}"
                                        class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                                @endif
                            </td>
                            <td class="{{ $tdMono }}">{{ $l->kd_jenis_prw }}</td>
                            <td class="{{ $tdText }}">
                                {{ $l->jenisPerawatan?->nm_perawatan ?? '-' }}</td>
                            <td class="{{ $tdMuted }} text-xs">
                                {{ $l->tgl_periksa ? \Carbon\Carbon::parse($l->tgl_periksa)->format('d/m/Y') : '-' }}
                                {{ $l->jam }}</td>
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
            <flux:icon name="magnifying-glass" class="w-8 h-8 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data
                permintaan
                Lab untuk kunjungan ini.</p>
        </div>
    @endif
</div>

{{-- Service Request Radiologi --}}
<div x-show="srSubTab === 'rad'" x-cloak>
    @if (!$prereq_location_rad && $permintaanRadiologis->isNotEmpty())
        <div class="flex items-center gap-2.5 px-4 py-3 bg-amber-50 dark:bg-amber-900/10 border-b border-amber-100 dark:border-amber-800/30">
            <flux:icon name="lock-closed" class="w-4 h-4 text-amber-500 shrink-0" />
            <p class="text-xs text-amber-700 dark:text-amber-400">
                <strong>Location Radiologi belum dikonfigurasi:</strong> Daftarkan Location dengan type <span class="font-semibold">rad</span> di menu Satu Sehat → FHIR Resource → Location sebelum dapat mengirim Service Request Radiologi.
            </p>
        </div>
    @endif
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked ? $wire.set('ssSelectedServiceRequests', [{{ $srRadIds }}]) : $wire.set('ssSelectedServiceRequests', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">No. Order</th>
                    <th class="{{ $thClass }}">Kode</th>
                    <th class="{{ $thClass }}">Tindakan</th>
                    <th class="{{ $thClass }}">Tgl. Permintaan</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($permintaanRadiologis as $pr)
                    @php
                        $tglRad = $pr->tgl_permintaan ? \Carbon\Carbon::parse($pr->tgl_permintaan)->format('Ymd') : '';
                        $jamRad = str_replace(':', '', $pr->jam_permintaan ?? '');
                        $idStr = $this->reg->no_rawat . '-SR_RAD_' . $pr->noorder . '-' . $tglRad . '-' . $jamRad;
                        $syncedData = $ssServiceRequests->where('local_id', $idStr)->first();
                        $periksaRad = $pr->periksa_rad->first();
                        $localCode = $periksaRad?->kd_jenis_prw;
                        $nmPerawatan = $periksaRad?->jenisPerawatan?->nm_perawatan ?? ($pr->informasi_tambahan ?? '-');
                        $dicomStudy = $dicomStudies->get($pr->noorder);
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedData)
                                <flux:icon name="check-circle" variant="solid"
                                    class="w-5 h-5 text-green-500 mx-auto" />
                            @elseif ($localCode)
                                <input type="checkbox" wire:model="ssSelectedServiceRequests"
                                    value="{{ $idStr }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @else
                                <flux:icon name="clock" class="w-4 h-4 text-amber-400 mx-auto"
                                    title="Pemeriksaan belum dilakukan" />
                            @endif
                        </td>
                        <td class="{{ $tdMono }}">
                            {{ $pr->noorder }}
                            @if ($dicomStudy)
                                <div class="mt-1 flex items-center gap-1">
                                    <flux:badge
                                        color="{{ $dicomStudy->status === 'sent' ? 'green' : ($dicomStudy->status === 'received' ? 'blue' : 'zinc') }}"
                                        size="sm">{{ $dicomStudy->status }}</flux:badge>
                                </div>
                            @endif
                        </td>
                        <td class="{{ $tdMono }}">{{ $localCode ?? '-' }}</td>
                        <td class="{{ $tdText }}">{{ $nmPerawatan }}</td>
                        <td class="{{ $tdMuted }} text-xs">
                            {{ $pr->tgl_permintaan?->format('d/m/Y') }} {{ $pr->jam_permintaan }}</td>
                        <td class="{{ $tdMuted }}">
                            @if ($syncedData)
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                    <span class="text-[10px] font-mono">{{ $syncedData->ihs_number }}</span>
                                    <span
                                        class="text-[10px]">{{ $syncedData->synced_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            @elseif (!$localCode)
                                <span class="text-amber-500 text-xs">Menunggu pemeriksaan</span>
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

{{-- Service Request USG --}}
<div x-show="srSubTab === 'usg'" x-cloak>
    @if ($usgSrItems->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th class="{{ $thClass }} w-16 text-center">
                            <input type="checkbox"
                                x-on:change="$el.checked ? $wire.set('ssSelectedServiceRequests', [{{ $srUsgIds }}]) : $wire.set('ssSelectedServiceRequests', [])"
                                class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                        </th>
                        <th class="{{ $thClass }}">No. Order</th>
                        <th class="{{ $thClass }}">Tipe USG</th>
                        <th class="{{ $thClass }}">Waktu Pemeriksaan</th>
                        <th class="{{ $thClass }}">Status Sinkronisasi</th>
                        <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                    @foreach ($usgSrItems as $usg)
                        @php
                            $idStr =
                                $this->reg->no_rawat .
                                '-SR_USG_' .
                                $usg->noorder .
                                '-' .
                                $usg->tglFmt .
                                '-' .
                                $usg->jamFmt;
                            $syncedData = $ssServiceRequests->where('local_id', $idStr)->first();
                        @endphp
                        <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-2 text-center">
                                @if ($syncedData)
                                    <flux:icon name="check-circle" variant="solid"
                                        class="w-5 h-5 text-green-500 mx-auto" />
                                @else
                                    <input type="checkbox" wire:model="ssSelectedServiceRequests"
                                        value="{{ $idStr }}"
                                        class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                                @endif
                            </td>
                            <td class="{{ $tdMono }}">{{ $usg->noorder ?? '-' }}</td>
                            <td class="{{ $tdText }}">{{ $usg->usg_label }}</td>
                            <td class="{{ $tdMuted }} text-xs">{{ $usg->display }}</td>
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
            <flux:icon name="signal" class="w-8 h-8 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data USG untuk kunjungan ini.
            </p>
        </div>
    @endif
</div>
