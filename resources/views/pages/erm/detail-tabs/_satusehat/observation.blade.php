@php
    $labDetails = \App\Models\Simrs\DetailPeriksaLab::with(['jenisPerawatan', 'template'])
        ->where('no_rawat', $reg->no_rawat)
        ->get();
    $radDetails = \App\Models\Simrs\PeriksaRadiologi::where('periksa_radiologi.no_rawat', $reg->no_rawat)
        ->leftJoin('permintaan_radiologi', function ($join) {
            $join
                ->on('periksa_radiologi.no_rawat', '=', 'permintaan_radiologi.no_rawat')
                ->on('periksa_radiologi.tgl_periksa', '=', 'permintaan_radiologi.tgl_hasil')
                ->on('periksa_radiologi.jam', '=', 'permintaan_radiologi.jam_hasil');
        })
        ->select('periksa_radiologi.*', 'permintaan_radiologi.noorder')
        ->with(['jenisPerawatan'])
        ->get();
@endphp

<div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-zinc-100 dark:border-primary-dark-700">
    <div class="flex gap-1 p-1 bg-zinc-100 rounded-lg dark:bg-primary-dark-900/50 shrink-0">
        <button @click="obsSubTab = 'vital'"
            :class="obsSubTab === 'vital' ?
                'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-700 dark:text-zinc-100' :
                'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400'"
            class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-all">
            <flux:icon name="heart" class="w-3.5 h-3.5" />
            Tanda Vital
            @if ($ssObservations->count() > 0)
                <flux:badge color="primary" size="sm">{{ $ssObservations->count() }}
                </flux:badge>
            @endif
        </button>
        @if ($labDetails->isNotEmpty())
            <button @click="obsSubTab = 'lab'"
                :class="obsSubTab === 'lab' ?
                    'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-700 dark:text-zinc-100' :
                    'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400'"
                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-all">
                <flux:icon name="beaker" class="w-3.5 h-3.5" />
                Laboratorium
                <flux:badge color="zinc" size="sm">{{ $labDetails->count() }}</flux:badge>
            </button>
        @endif
        @if ($radDetails->isNotEmpty())
            <button @click="obsSubTab = 'rad'"
                :class="obsSubTab === 'rad' ?
                    'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-700 dark:text-zinc-100' :
                    'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400'"
                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-all">
                <flux:icon name="photo" class="w-3.5 h-3.5" />
                Radiologi
                <flux:badge color="zinc" size="sm">{{ $radDetails->count() }}</flux:badge>
            </button>
        @endif
        @php $usgObsTotal = collect($usgResults)->sum(fn($u) => count($u['data'])); @endphp
        @if ($usgObsTotal > 0)
            <button @click="obsSubTab = 'usg'"
                :class="obsSubTab === 'usg' ?
                    'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-700 dark:text-zinc-100' :
                    'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400'"
                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-all">
                <flux:icon name="signal" class="w-3.5 h-3.5" />
                USG
                <flux:badge color="zinc" size="sm">{{ $usgObsTotal }}</flux:badge>
            </button>
        @endif
    </div>
    <div class="shrink-0">
        <div x-show="obsSubTab === 'vital'">
            <x-atoms.button wire:click="sendSsObservations" wire:loading.attr="disabled" icon="paper-airplane"
                size="sm">
                <span wire:loading.remove wire:target="sendSsObservations">Kirim Tanda Vital</span>
                <span wire:loading wire:target="sendSsObservations">Mengirim...</span>
            </x-atoms.button>
        </div>
        <div x-show="obsSubTab === 'lab'" x-cloak>
            <x-atoms.button wire:click="sendSsLabObservations" wire:loading.attr="disabled" icon="paper-airplane"
                size="sm" :disabled="!$prereq_location_lab">
                <span wire:loading.remove wire:target="sendSsLabObservations">Kirim Obs. Lab</span>
                <span wire:loading wire:target="sendSsLabObservations">Mengirim...</span>
            </x-atoms.button>
        </div>
        <div x-show="obsSubTab === 'rad'" x-cloak>
            <x-atoms.button wire:click="sendSsRadObservations" wire:loading.attr="disabled" icon="paper-airplane"
                size="sm" :disabled="!$prereq_location_rad">
                <span wire:loading.remove wire:target="sendSsRadObservations">Kirim Obs. Rad</span>
                <span wire:loading wire:target="sendSsRadObservations">Mengirim...</span>
            </x-atoms.button>
        </div>
        <div x-show="obsSubTab === 'usg'" x-cloak>
            <x-atoms.button wire:click="sendSsUsgObservations" wire:loading.attr="disabled" icon="paper-airplane"
                size="sm">
                <span wire:loading.remove wire:target="sendSsUsgObservations">Kirim Obs. USG</span>
                <span wire:loading wire:target="sendSsUsgObservations">Mengirim...</span>
            </x-atoms.button>
        </div>
    </div>
</div>
{{-- Tanda Vital --}}
<div x-show="obsSubTab === 'vital'">
    @if ($pemeriksaans->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th class="{{ $thClass }} w-16 text-center">
                            <input type="checkbox"
                                x-on:change="$el.checked ? $wire.set('ssSelectedObservations', [{{ $pemeriksaans->flatMap(fn($p) => collect(['temperature','heart_rate','respiratory_rate','systolic','diastolic','oxygen_saturation','height','weight'])->map(fn($t) => "'" . $this->reg->no_rawat . '-OBS_' . strtoupper($t) . '-' . $p->tgl_perawatan?->format('Ymd') . '-' . str_replace(':', '', $p->jam_rawat ?? '000000') . "'" ))->implode(',') }}]) : $wire.set('ssSelectedObservations', [])"
                                class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                        </th>
                        <th class="{{ $thClass }}">Waktu Periksa</th>
                        <th class="{{ $thClass }}">Data Observasi Tersedia</th>
                        <th class="{{ $thClass }}">Status Sinkronisasi</th>
                        <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                    @foreach ($pemeriksaans as $periksa)
                        @php
                            $baseIdStr =
                                $periksa->tgl_perawatan?->format('Y-m-d') . '|' . ($periksa->jam_rawat ?? '00:00:00');
                            $dtStr = $periksa->tgl_perawatan?->format('Y-m-d') . ' ' . $periksa->jam_rawat;

                            $vitals = [];
                            if (!empty($periksa->suhu_tubuh) && $periksa->suhu_tubuh !== '-') {
                                $vitals['temperature'] = [
                                    'label' => 'Body temperature',
                                    'value' => $periksa->suhu_tubuh . ' °C',
                                    'code' => '8310-5',
                                ];
                            }
                            if (!empty($periksa->nadi) && $periksa->nadi !== '-') {
                                $vitals['heart_rate'] = [
                                    'label' => 'Heart rate',
                                    'value' => $periksa->nadi . ' /min',
                                    'code' => '8867-4',
                                ];
                            }
                            if (!empty($periksa->respirasi) && $periksa->respirasi !== '-') {
                                $vitals['respiratory_rate'] = [
                                    'label' => 'Respiratory rate',
                                    'value' => $periksa->respirasi . ' /min',
                                    'code' => '9279-1',
                                ];
                            }

                            if (!empty($periksa->tensi) && $periksa->tensi !== '-') {
                                $parts = explode('/', $periksa->tensi);
                                if (count($parts) === 2) {
                                    $vitals['systolic'] = [
                                        'label' => 'Systolic blood pressure',
                                        'value' => trim($parts[0]) . ' mmHg',
                                        'code' => '8480-6',
                                    ];
                                    $vitals['diastolic'] = [
                                        'label' => 'Diastolic blood pressure',
                                        'value' => trim($parts[1]) . ' mmHg',
                                        'code' => '8462-4',
                                    ];
                                }
                            }

                            if (!empty($periksa->spo2) && $periksa->spo2 !== '-') {
                                $vitals['oxygen_saturation'] = [
                                    'label' => 'Oxygen saturation in Arterial blood',
                                    'value' => $periksa->spo2 . ' %',
                                    'code' => '2708-6',
                                ];
                            }
                            if (!empty($periksa->tinggi) && $periksa->tinggi !== '-') {
                                $vitals['height'] = [
                                    'label' => 'Body height',
                                    'value' => $periksa->tinggi . ' cm',
                                    'code' => '8302-2',
                                ];
                            }
                            if (!empty($periksa->berat) && $periksa->berat !== '-') {
                                $vitals['weight'] = [
                                    'label' => 'Body weight',
                                    'value' => $periksa->berat . ' kg',
                                    'code' => '29463-7',
                                ];
                            }
                        @endphp

                        @foreach ($vitals as $type => $vital)
                            @php
                                $localId = $this->reg->no_rawat . '-OBS_' . strtoupper($type) . '-' . $periksa->tgl_perawatan?->format('Ymd') . '-' . str_replace(':', '', $periksa->jam_rawat ?? '000000');
                                $syncedData = $ssObservations->where('local_id', $localId)->first();
                                $hasSynced = !empty($syncedData);
                                $firstSynced = $syncedData;
                            @endphp
                            <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                <td class="px-4 py-2 text-center">
                                    @if ($hasSynced)
                                        <flux:icon name="check-circle" variant="solid"
                                            class="w-5 h-5 text-green-500 mx-auto" />
                                    @else
                                        <input type="checkbox" wire:model="ssSelectedObservations"
                                            value="{{ $localId }}"
                                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                                    @endif
                                </td>
                                <td class="{{ $tdText }}">
                                    {{ $periksa->tgl_perawatan?->format('d/m/Y') }}
                                    {{ $periksa->jam_rawat }}
                                </td>
                                <td class="{{ $tdText }}">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="font-medium text-zinc-900 dark:text-zinc-100">{{ $vital['label'] }}</span>
                                        <span
                                            class="text-sm px-2 py-0.5 rounded bg-zinc-100 text-zinc-700 border border-zinc-200 dark:bg-primary-dark-800 dark:text-zinc-300 dark:border-primary-dark-600">{{ $vital['value'] }}</span>
                                    </div>
                                </td>
                                <td class="{{ $tdMuted }}">
                                    @if ($hasSynced)
                                        <div class="flex flex-col gap-0.5">
                                            <span
                                                class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                            <span class="text-[10px] font-mono">{{ $firstSynced->ihs_number }}</span>
                                            <span
                                                class="text-[10px]">{{ $firstSynced->synced_at?->format('d/m/Y H:i') }}</span>
                                        </div>
                                    @else
                                        <span class="text-zinc-400 dark:text-primary-dark-500">Belum
                                            didaftarkan</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center">
                                    @if ($hasSynced)
                                        <button type="button"
                                            wire:click="openSsDetail('{{ $firstSynced->ihs_number }}')"
                                            class="p-1.5 rounded-lg text-zinc-400 hover:text-sky-600 hover:bg-sky-50 dark:text-primary-dark-500 dark:hover:text-sky-400 dark:hover:bg-sky-900/20 transition-colors"
                                            title="Lihat detail sinkronisasi">
                                            <flux:icon name="eye" class="w-4 h-4" />
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="flex flex-col items-center py-8">
            <flux:icon name="chart-bar" class="w-8 h-8 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data
                Observation
                (Tanda Vital) untuk kunjungan ini.</p>
        </div>
    @endif
</div>

{{-- Lab Observations --}}
<div x-show="obsSubTab === 'lab'" x-cloak>
    @if (!$prereq_location_lab)
        <div class="flex items-center gap-2.5 px-4 py-3 bg-amber-50 dark:bg-amber-900/10 border-b border-amber-100 dark:border-amber-800/30">
            <flux:icon name="lock-closed" class="w-4 h-4 text-amber-500 shrink-0" />
            <p class="text-xs text-amber-700 dark:text-amber-400">
                <strong>Location Lab belum dikonfigurasi:</strong> Daftarkan Location dengan type <span class="font-semibold">lab</span> di menu Satu Sehat → FHIR Resource → Location sebelum dapat mengirim Observation Lab.
            </p>
        </div>
    @endif
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked ? $wire.set('ssSelectedLabObservations', [{{ $labDetails->map(function ($d) use ($reg) {return '\'' . $reg->no_rawat . '-OBS_LAB_' . $d->kd_jenis_prw . '_' . ($d->id_template ?? 'item') . '-' . ($d->tgl_periksa ? \Carbon\Carbon::parse($d->tgl_periksa)->format('Ymd') : '') . '-' . str_replace(':', '', $d->jam ?? '') . '\'';})->implode(',') }}]) : $wire.set('ssSelectedLabObservations', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">Waktu Periksa</th>
                    <th class="{{ $thClass }}">Pemeriksaan Lab</th>
                    <th class="{{ $thClass }}">Hasil</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($labDetails as $detail)
                    @php
                        $localCode = $detail->kd_jenis_prw;
                        $templateId = $detail->id_template ?? 'item';
                        $idStr =
                            $reg->no_rawat .
                            '-OBS_LAB_' .
                            $localCode .
                            '_' .
                            $templateId .
                            '-' .
                            ($detail->tgl_periksa ? \Carbon\Carbon::parse($detail->tgl_periksa)->format('Ymd') : '') .
                            '-' .
                            str_replace(':', '', $detail->jam ?? '');

                        $specimenIdStr =
                            $reg->no_rawat .
                            '-SPEC_LAB_' .
                            $localCode .
                            '-' .
                            ($detail->tgl_periksa ? \Carbon\Carbon::parse($detail->tgl_periksa)->format('Ymd') : '') .
                            '-' .
                            str_replace(':', '', $detail->jam ?? '');

                        $syncedObs = $ssObservations->where('local_id', $idStr)->first();
                        $hasSynced = !empty($syncedObs);
                        $hasSpecimen = $ssSpecimens->where('local_id', $specimenIdStr)->isNotEmpty();

                        $display = $detail->template?->Pemeriksaan ?? ($detail->jenisPerawatan?->nm_perawatan ?? '-');
                        $hasil = trim($detail->nilai ?? '-');
                        $rujukan = trim($detail->nilai_rujukan ?? ($detail->template?->nilai_rujukan_ld ?? ''));
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($hasSynced)
                                <flux:icon name="check-circle" variant="solid"
                                    class="w-5 h-5 text-green-500 mx-auto" />
                            @elseif (!$hasSpecimen)
                                <flux:icon name="lock-closed"
                                    class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 mx-auto" />
                            @else
                                <input type="checkbox" wire:model="ssSelectedLabObservations"
                                    value="{{ $idStr }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </td>
                        <td class="{{ $tdText }}">
                            {{ $detail->tgl_periksa ? \Carbon\Carbon::parse($detail->tgl_periksa)->format('d/m/Y') : '-' }}
                            {{ $detail->jam }}
                        </td>
                        <td class="{{ $tdText }}">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $display }}</span>
                        </td>
                        <td class="{{ $tdText }}">
                            <div class="flex items-center gap-2">
                                <span
                                    class="text-sm px-2 py-0.5 rounded bg-zinc-100 text-zinc-700 border border-zinc-200 dark:bg-primary-dark-800 dark:text-zinc-300 dark:border-primary-dark-600">{{ $hasil }}</span>
                                @if ($rujukan)
                                    <span class="text-[11px] text-zinc-400"> (N:
                                        {{ $rujukan }})</span>
                                @endif
                            </div>
                        </td>
                        <td class="{{ $tdMuted }}">
                            @if ($hasSynced)
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                    <span class="text-[10px] font-mono">{{ $syncedObs->ihs_number }}</span>
                                    <span class="text-[10px]">{{ $syncedObs->synced_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            @elseif (!$hasSpecimen)
                                <div class="flex items-center gap-1.5">
                                    <flux:icon name="exclamation-triangle"
                                        class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                                    <span class="text-amber-600 dark:text-amber-400">Perlu
                                        Specimen</span>
                                </div>
                            @else
                                <span class="text-zinc-400 dark:text-primary-dark-500">Belum
                                    didaftarkan</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-center">
                            @if ($hasSynced)
                                <button type="button" wire:click="openSsDetail('{{ $syncedObs->ihs_number }}')"
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

{{-- Rad Observations --}}
<div x-show="obsSubTab === 'rad'" x-cloak>
    @if (!$prereq_location_rad)
        <div class="flex items-center gap-2.5 px-4 py-3 bg-amber-50 dark:bg-amber-900/10 border-b border-amber-100 dark:border-amber-800/30">
            <flux:icon name="lock-closed" class="w-4 h-4 text-amber-500 shrink-0" />
            <p class="text-xs text-amber-700 dark:text-amber-400">
                <strong>Location Radiologi belum dikonfigurasi:</strong> Daftarkan Location dengan type <span class="font-semibold">rad</span> di menu Satu Sehat → FHIR Resource → Location sebelum dapat mengirim Observation Radiologi.
            </p>
        </div>
    @endif
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked ? $wire.set('ssSelectedRadObservations', [{{ $radDetails->map(function ($r) use ($reg) {return '\'' . $reg->no_rawat . '-OBS_RAD_' . $r->noorder . '-' . ($r->tgl_periksa ? \Carbon\Carbon::parse($r->tgl_periksa)->format('Ymd') : '') . '-' . str_replace(':', '', $r->jam ?? '') . '\'';})->implode(',') }}]) : $wire.set('ssSelectedRadObservations', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">Waktu Periksa</th>
                    <th class="{{ $thClass }}">Pemeriksaan Radiologi</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($radDetails as $rad)
                    @php
                        $localCode = $rad->kd_jenis_prw;
                        $tglR = $rad->tgl_periksa ? \Carbon\Carbon::parse($rad->tgl_periksa)->format('Ymd') : '';
                        $jamR = str_replace(':', '', $rad->jam ?? '');
                        $idStr = $reg->no_rawat . '-OBS_RAD_' . $rad->noorder . '-' . $tglR . '-' . $jamR;
                        $imgIdStr = $reg->no_rawat . '-IMG_RAD_' . $rad->noorder . '-' . $tglR . '-' . $jamR;
                        $specimenIdStr = $reg->no_rawat . '-SPEC_RAD_' . $rad->noorder . '-' . $tglR . '-' . $jamR;

                        $syncedObs = $ssObservations->where('local_id', $idStr)->first();
                        $hasSynced = !empty($syncedObs);
                        $hasImagingStudy = $ssImagingStudies->where('local_id', $imgIdStr)->isNotEmpty();
                        $hasSpecimen = $ssSpecimens->where('local_id', $specimenIdStr)->isNotEmpty();
                        $canSend = $hasImagingStudy && $hasSpecimen;

                        $display = $rad->jenisPerawatan?->nm_perawatan ?? '-';
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($hasSynced)
                                <flux:icon name="check-circle" variant="solid"
                                    class="w-5 h-5 text-green-500 mx-auto" />
                            @elseif (!$canSend)
                                <flux:icon name="lock-closed"
                                    class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 mx-auto" />
                            @else
                                <input type="checkbox" wire:model="ssSelectedRadObservations"
                                    value="{{ $idStr }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </td>
                        <td class="{{ $tdText }}">
                            {{ $rad->tgl_periksa ? \Carbon\Carbon::parse($rad->tgl_periksa)->format('d/m/Y') : '-' }}
                            {{ $rad->jam }}
                        </td>
                        <td class="{{ $tdText }}">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $display }}</span>
                        </td>
                        <td class="{{ $tdMuted }}">
                            @if ($hasSynced)
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                    <span class="text-[10px] font-mono">{{ $syncedObs->ihs_number }}</span>
                                    <span class="text-[10px]">{{ $syncedObs->synced_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            @elseif (!$hasImagingStudy)
                                <div class="flex items-center gap-1.5">
                                    <flux:icon name="exclamation-triangle"
                                        class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                                    <span class="text-amber-600 dark:text-amber-400">Perlu Imaging
                                        Study</span>
                                </div>
                            @elseif (!$hasSpecimen)
                                <div class="flex items-center gap-1.5">
                                    <flux:icon name="exclamation-triangle"
                                        class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                                    <span class="text-amber-600 dark:text-amber-400">Perlu
                                        Specimen</span>
                                </div>
                            @else
                                <span class="text-zinc-400 dark:text-primary-dark-500">Belum
                                    didaftarkan</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-center">
                            @if ($hasSynced)
                                <button type="button" wire:click="openSsDetail('{{ $syncedObs->ihs_number }}')"
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

{{-- USG Observations --}}
<div x-show="obsSubTab === 'usg'" x-cloak>
    @php
        $usgObsItems = collect();
        foreach ($usgResults as $key => $cfg) {
            foreach ($cfg['data'] as $item) {
                $tglUSG = \Carbon\Carbon::parse($item->tanggal);
                $jamUSG = $item->jam ?? $tglUSG->format('H:i:s');
                $usgObsItems->push((object) [
                    'usg_type'  => $key,
                    'usg_label' => $cfg['label'],
                    'noorder'   => $item->noorder ?? strtoupper($key),
                    'tglFmt'    => $tglUSG->format('Ymd'),
                    'jamFmt'    => str_replace(':', '', $jamUSG),
                    'display'   => $tglUSG->format('d/m/Y') . ' ' . $jamUSG,
                ]);
            }
        }
    @endphp
    @if ($usgObsItems->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th class="{{ $thClass }} w-16 text-center">
                            <input type="checkbox"
                                x-on:change="$el.checked ? $wire.set('ssSelectedUsgObservations', {{ $usgObsItems->map(fn($u) => $reg->no_rawat . '-OBS_USG_' . $u->noorder . '-' . $u->tglFmt . '-' . $u->jamFmt)->toJson() }}) : $wire.set('ssSelectedUsgObservations', [])"
                                class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                        </th>
                        <th class="{{ $thClass }}">Waktu Periksa</th>
                        <th class="{{ $thClass }}">Tipe USG</th>
                        <th class="{{ $thClass }}">Status Sinkronisasi</th>
                        <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                    @foreach ($usgObsItems as $usg)
                        @php
                            $idStr           = "{$reg->no_rawat}-OBS_USG_{$usg->noorder}-{$usg->tglFmt}-{$usg->jamFmt}";
                            $imgIdStr        = "{$reg->no_rawat}-IMG_USG_{$usg->noorder}-{$usg->tglFmt}-{$usg->jamFmt}";
                            $syncedObs       = $ssObservations->where('local_id', $idStr)->first();
                            $hasSynced       = !empty($syncedObs);
                            $hasImagingStudy = $ssImagingStudies->where('local_id', $imgIdStr)->isNotEmpty();
                        @endphp
                        <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-2 text-center">
                                @if ($hasSynced)
                                    <flux:icon name="check-circle" variant="solid"
                                        class="w-5 h-5 text-green-500 mx-auto" />
                                @elseif (!$hasImagingStudy)
                                    <flux:icon name="lock-closed"
                                        class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 mx-auto"
                                        title="ImagingStudy belum dikirim" />
                                @else
                                    <input type="checkbox" wire:model="ssSelectedUsgObservations"
                                        value="{{ $idStr }}"
                                        class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                                @endif
                            </td>
                            <td class="{{ $tdText }}">{{ $usg->display }}</td>
                            <td class="{{ $tdText }}">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $usg->usg_label }}</span>
                            </td>
                            <td class="{{ $tdMuted }}">
                                @if ($hasSynced)
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                        <span class="text-[10px] font-mono">{{ $syncedObs->ihs_number }}</span>
                                        <span class="text-[10px]">{{ $syncedObs->synced_at?->format('d/m/Y H:i') }}</span>
                                    </div>
                                @elseif (!$hasImagingStudy)
                                    <div class="flex items-center gap-1.5">
                                        <flux:icon name="exclamation-triangle"
                                            class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                                        <span class="text-amber-600 dark:text-amber-400">Perlu Imaging Study</span>
                                    </div>
                                @else
                                    <span class="text-zinc-400 dark:text-primary-dark-500">Belum didaftarkan</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if ($hasSynced)
                                    <button type="button" wire:click="openSsDetail('{{ $syncedObs->ihs_number }}')"
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
            <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data USG untuk kunjungan ini.</p>
        </div>
    @endif
</div>
