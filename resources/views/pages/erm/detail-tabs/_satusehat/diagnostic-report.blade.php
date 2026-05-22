@php
    $drLabIds = $periksaLabsPk
        ->map(
            fn($l) => "'" .
                $this->reg->no_rawat .
                '-DR_LAB_' .
                $l->kd_jenis_prw .
                '-' .
                ($l->tgl_periksa ? \Carbon\Carbon::parse($l->tgl_periksa)->format('Ymd') : '') .
                '-' .
                str_replace(':', '', $l->jam ?? '') .
                "'",
        )
        ->implode(',');
    $drRadItems = collect();
    foreach ($permintaanRadiologis as $pr) {
        foreach ($pr->periksa_rad as $r) {
            $r->noorder = $pr->noorder;
            $drRadItems->push($r);
        }
    }
    $drRadIds = $drRadItems
        ->map(
            fn($r) => "'" .
                $this->reg->no_rawat .
                '-DR_RAD_' .
                $r->noorder .
                '-' .
                ($r->tgl_periksa ? \Carbon\Carbon::parse($r->tgl_periksa)->format('Ymd') : '') .
                '-' .
                str_replace(':', '', $r->jam ?? '') .
                "'",
        )
        ->implode(',');

    $drUsgItems = collect();
    foreach ($usgResults as $key => $cfg) {
        foreach ($cfg['data'] as $item) {
            $item->usg_type = $key;
            $item->usg_label = $cfg['label'];
            $drUsgItems->push($item);
        }
    }
    $drUsgIds = $drUsgItems
        ->map(function ($item) {
            $tgl      = \Carbon\Carbon::parse($item->tanggal)->format('Ymd');
            $jam      = str_replace(':', '', $item->jam ?? \Carbon\Carbon::parse($item->tanggal)->format('His'));
            $noOrder  = $item->noorder ?? strtoupper($item->usg_type);
            return "'" . $this->reg->no_rawat . '-DR_USG_' . $noOrder . '-' . $tgl . '-' . $jam . "'";
        })
        ->implode(',');
@endphp

<div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-zinc-100 dark:border-primary-dark-700">
    <div class="flex gap-1 p-1 bg-zinc-100 rounded-lg dark:bg-primary-dark-900/50 shrink-0">
        <button @click="drSubTab = 'lab'"
            :class="drSubTab === 'lab' ?
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
        @if ($drRadItems->isNotEmpty())
            <button @click="drSubTab = 'rad'"
                :class="drSubTab === 'rad' ?
                    'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-700 dark:text-zinc-100' :
                    'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400'"
                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-all">
                <flux:icon name="photo" class="w-3.5 h-3.5" />
                Radiologi
                <flux:badge color="zinc" size="sm">{{ $drRadItems->count() }}
                </flux:badge>
            </button>
        @endif
        @if ($drUsgItems->isNotEmpty())
            <button @click="drSubTab = 'usg'"
                :class="drSubTab === 'usg' ?
                    'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-700 dark:text-zinc-100' :
                    'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400'"
                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-all">
                <flux:icon name="signal" class="w-3.5 h-3.5" />
                USG
                <flux:badge color="zinc" size="sm">{{ $drUsgItems->count() }}
                </flux:badge>
            </button>
        @endif
    </div>
    <div x-show="drSubTab === 'lab'">
        <x-atoms.button wire:click="sendSsLabDiagnosticReports" wire:loading.attr="disabled" icon="paper-airplane"
            size="sm">
            <span wire:loading.remove wire:target="sendSsLabDiagnosticReports">Kirim Diag. Report Lab</span>
            <span wire:loading wire:target="sendSsLabDiagnosticReports">Mengirim...</span>
        </x-atoms.button>
    </div>
    <div x-show="drSubTab === 'rad'" x-cloak>
        <x-atoms.button wire:click="sendSsRadDiagnosticReports" wire:loading.attr="disabled" icon="paper-airplane"
            size="sm">
            <span wire:loading.remove wire:target="sendSsRadDiagnosticReports">Kirim Diag. Report Rad</span>
            <span wire:loading wire:target="sendSsRadDiagnosticReports">Mengirim...</span>
        </x-atoms.button>
    </div>
    <div x-show="drSubTab === 'usg'" x-cloak>
        <x-atoms.button wire:click="sendSsUsgDiagnosticReports" wire:loading.attr="disabled" icon="paper-airplane"
            size="sm">
            <span wire:loading.remove wire:target="sendSsUsgDiagnosticReports">Kirim Diag. Report USG</span>
            <span wire:loading wire:target="sendSsUsgDiagnosticReports">Mengirim...</span>
        </x-atoms.button>
    </div>
</div>
<div x-show="drSubTab === 'lab'">
    @php
        // Pre-load saran/kesan lab untuk semua baris (hindari N+1)
        $saranKesanLabs = \App\Models\Simrs\SaranKesanLab::where('no_rawat', $this->reg->no_rawat)
            ->get()
            ->keyBy(fn($s) => $s->tgl_periksa?->format('Y-m-d') . '|' . $s->jam);
    @endphp
    @if ($periksaLabsPk->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th class="{{ $thClass }} w-16 text-center">
                            <input type="checkbox"
                                x-on:change="$el.checked ? $wire.set('ssSelectedDiagnosticReports', [{{ $drLabIds }}]) : $wire.set('ssSelectedDiagnosticReports', [])"
                                class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                        </th>
                        <th class="{{ $thClass }}">Kode</th>
                        <th class="{{ $thClass }}">Nama Pemeriksaan</th>
                        <th class="{{ $thClass }}">Waktu</th>
                        <th class="{{ $thClass }}">Hasil Lab</th>
                        <th class="{{ $thClass }}">Prasyarat</th>
                        <th class="{{ $thClass }}">Status Sinkronisasi</th>
                        <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                    @foreach ($periksaLabsPk as $l)
                        @php
                            $tglLab = $l->tgl_periksa ? \Carbon\Carbon::parse($l->tgl_periksa)->format('Ymd') : '';
                            $jamLab = str_replace(':', '', $l->jam ?? '');
                            $idStr = "{$this->reg->no_rawat}-DR_LAB_{$l->kd_jenis_prw}-{$tglLab}-{$jamLab}";
                            $syncedData = $ssDiagnosticReports->where('local_id', $idStr)->first();
                            $srIdStr = str_replace('DR_LAB_', 'SR_LAB_', $idStr);
                            $specIdStr = str_replace('DR_LAB_', 'SPEC_LAB_', $idStr);
                            $hasSR = $ssServiceRequests->where('local_id', $srIdStr)->isNotEmpty();
                            $hasSpec = $ssSpecimens->where('local_id', $specIdStr)->isNotEmpty();
                            // Format actual: {no_rawat}-OBS_LAB_{kd_jenis_prw}_{id_template}-{tgl}-{jam}
                            // Cukup cek prefix OBS_LAB_{kd}_ karena setiap template menghasilkan local_id berbeda
                            $hasObsLab = $ssObservations->contains(
                                fn($obs) => str_contains($obs->local_id, "OBS_LAB_{$l->kd_jenis_prw}_"),
                            );
                            $skKey =
                                ($l->tgl_periksa ? \Carbon\Carbon::parse($l->tgl_periksa)->format('Y-m-d') : '') .
                                '|' .
                                $l->jam;
                            $saranKesan = $saranKesanLabs->get($skKey);
                            $hasilLabOk =
                                $saranKesan && (trim($saranKesan->saran ?? '') || trim($saranKesan->kesan ?? ''));
                        @endphp
                        <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-2 text-center">
                                @if ($syncedData)
                                    <flux:icon name="check-circle" variant="solid"
                                        class="w-5 h-5 text-green-500 mx-auto" />
                                @elseif (!$hasSR || !$hasSpec || !$hasObsLab || !$hasilLabOk)
                                    <flux:icon name="lock-closed"
                                        class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 mx-auto"
                                        title="Prasyarat belum terpenuhi" />
                                @else
                                    <input type="checkbox" wire:model="ssSelectedDiagnosticReports"
                                        value="{{ $idStr }}"
                                        class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                                @endif
                            </td>
                            <td class="{{ $tdMono }}">{{ $l->kd_jenis_prw }}</td>
                            <td class="{{ $tdText }}">
                                {{ $l->jenisPerawatan?->nm_perawatan ?? '-' }}</td>
                            <td class="{{ $tdMuted }} text-xs">
                                {{ $l->tgl_periksa ? \Carbon\Carbon::parse($l->tgl_periksa)->format('d/m/Y') : '-' }}
                                {{ $l->jam }}
                            </td>
                            <td class="{{ $tdMuted }}">
                                @if ($hasilLabOk)
                                    <div class="flex flex-col gap-0.5">
                                        @if ($saranKesan->kesan)
                                            <span
                                                class="text-xs text-zinc-700 dark:text-primary-dark-200 line-clamp-1">{{ $saranKesan->kesan }}</span>
                                        @endif
                                        @if ($saranKesan->saran)
                                            <span
                                                class="text-xs text-zinc-500 dark:text-primary-dark-400 line-clamp-1">{{ $saranKesan->saran }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                        <flux:icon name="exclamation-triangle" class="w-3.5 h-3.5 shrink-0" />
                                        Hasil belum diisi
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex flex-col gap-1">
                                    <flux:badge :color="$hasSR ? 'emerald' : 'red'" size="sm" :icon="$hasSR ? 'check-circle' : 'x-circle'">
                                        {{ $hasSR ? 'Serv. Request' : 'Belum ada SR' }}
                                    </flux:badge>
                                    <flux:badge :color="$hasSpec ? 'emerald' : 'red'" size="sm" :icon="$hasSpec ? 'check-circle' : 'x-circle'">
                                        {{ $hasSpec ? 'Specimen' : 'Belum ada Specimen' }}
                                    </flux:badge>
                                    <flux:badge :color="$hasObsLab ? 'emerald' : 'red'" size="sm" :icon="$hasObsLab ? 'check-circle' : 'x-circle'">
                                        {{ $hasObsLab ? 'Observation' : 'Belum ada Obs.' }}
                                    </flux:badge>
                                    <flux:badge :color="$hasilLabOk ? 'emerald' : 'amber'" size="sm" :icon="$hasilLabOk ? 'check-circle' : 'exclamation-triangle'">
                                        {{ $hasilLabOk ? 'Hasil Lab' : 'Hasil belum diisi' }}
                                    </flux:badge>
                                </div>
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
                                        title="Lihat detail">
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
            <flux:icon name="document-chart-bar" class="w-8 h-8 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data Lab
                untuk
                kunjungan ini.</p>
        </div>
    @endif
</div>

{{-- Diagnostic Report Radiologi --}}
<div x-show="drSubTab === 'rad'" x-cloak>
    @php
        // Pre-load hasil radiologi untuk semua baris (hindari N+1)
        $hasilRadiologis = \App\Models\Simrs\HasilRadiologi::where('no_rawat', $this->reg->no_rawat)
            ->get()
            ->keyBy(fn($h) => $h->tgl_periksa?->format('Y-m-d') . '|' . $h->jam);
    @endphp
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked ? $wire.set('ssSelectedDiagnosticReports', [{{ $drRadIds }}]) : $wire.set('ssSelectedDiagnosticReports', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">No. Order</th>
                    <th class="{{ $thClass }}">Kode</th>
                    <th class="{{ $thClass }}">Nama Pemeriksaan</th>
                    <th class="{{ $thClass }}">Waktu</th>
                    <th class="{{ $thClass }}">Hasil Radiologi</th>
                    <th class="{{ $thClass }}">Prasyarat</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($drRadItems as $r)
                    @php
                        $tglRad = $r->tgl_periksa ? \Carbon\Carbon::parse($r->tgl_periksa)->format('Ymd') : '';
                        $jamRad = str_replace(':', '', $r->jam ?? '');
                        $idStr = "{$this->reg->no_rawat}-DR_RAD_{$r->noorder}-{$tglRad}-{$jamRad}";
                        $syncedData = $ssDiagnosticReports->where('local_id', $idStr)->first();
                        $hasSR = $ssServiceRequests->contains(
                            fn($sr) => str_contains($sr->local_id, "SR_RAD_{$r->noorder}"),
                        );
                        $hasIS = $ssImagingStudies->contains(
                            fn($is) => str_contains($is->local_id, "IMG_RAD_{$r->noorder}"),
                        );
                        $hasObsRad = $ssObservations
                            ->where('local_id', str_replace('-DR_RAD_', '-OBS_RAD_', $idStr))
                            ->isNotEmpty();
                        $radKey =
                            ($r->tgl_periksa ? \Carbon\Carbon::parse($r->tgl_periksa)->format('Y-m-d') : '') .
                            '|' .
                            $r->jam;
                        $hasilRad = $hasilRadiologis->get($radKey);
                        $hasilOk = $hasilRad && trim(strip_tags($hasilRad->hasil ?? '')) !== '';
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedData)
                                <flux:icon name="check-circle" variant="solid"
                                    class="w-5 h-5 text-green-500 mx-auto" />
                            @elseif (!$hasSR || !$hasIS || !$hasObsRad || !$hasilOk)
                                <flux:icon name="lock-closed"
                                    class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 mx-auto"
                                    title="Prasyarat belum terpenuhi" />
                            @else
                                <input type="checkbox" wire:model="ssSelectedDiagnosticReports"
                                    value="{{ $idStr }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </td>
                        <td class="{{ $tdMono }}">
                            {{ $r->noorder ?? '-' }}
                            @php
                                $dicomStudy = $dicomStudies->get($r->noorder);
                            @endphp
                            @if ($dicomStudy)
                                <div class="mt-1 flex items-center gap-1">
                                    <flux:badge
                                        color="{{ match ($dicomStudy->status) {
                                            'sent' => 'green',
                                            'received' => 'blue',
                                            'error' => 'red',
                                            default => 'zinc',
                                        } }}"
                                        size="sm">
                                        {{ $dicomStudy->status }}
                                    </flux:badge>
                                </div>
                            @endif
                        </td>
                        <td class="{{ $tdMono }}">{{ $r->kd_jenis_prw }}</td>
                        <td class="{{ $tdText }}">
                            {{ $r->jenisPerawatan?->nm_perawatan ?? '-' }}</td>
                        <td class="{{ $tdMuted }} text-xs">
                            {{ $r->tgl_periksa ? \Carbon\Carbon::parse($r->tgl_periksa)->format('d/m/Y') : '-' }}
                            {{ $r->jam }}
                        </td>
                        <td class="{{ $tdMuted }}">
                            @if ($hasilOk)
                                <span class="text-xs text-zinc-700 dark:text-primary-dark-200 line-clamp-2">
                                    {{ \Illuminate\Support\Str::limit(strip_tags($hasilRad->hasil), 100) }}
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                    <flux:icon name="exclamation-triangle" class="w-3.5 h-3.5 shrink-0" />
                                    Hasil belum diisi
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex flex-col gap-1">
                                @if ($hasSR)
                                    <flux:badge color="emerald" size="sm" icon="check-circle">Serv. Request
                                    </flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="x-circle">Belum ada SR
                                    </flux:badge>
                                @endif
                                @if ($hasIS)
                                    <flux:badge color="emerald" size="sm" icon="check-circle">Imaging Study
                                    </flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="x-circle">Belum ada Study
                                    </flux:badge>
                                @endif
                                @if ($hasObsRad)
                                    <flux:badge color="emerald" size="sm" icon="check-circle">Observation
                                    </flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="x-circle">Belum ada Obs.
                                    </flux:badge>
                                @endif
                            </div>
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
                                    title="Lihat detail">
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

{{-- Diagnostic Report USG --}}
<div x-show="drSubTab === 'usg'" x-cloak>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked ? $wire.set('ssSelectedDiagnosticReports', [{{ $drUsgIds }}]) : $wire.set('ssSelectedDiagnosticReports', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">No. Order</th>
                    <th class="{{ $thClass }}">Tipe USG</th>
                    <th class="{{ $thClass }}">Waktu</th>
                    <th class="{{ $thClass }}">Hasil/Kesimpulan</th>
                    <th class="{{ $thClass }}">Prasyarat</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($drUsgItems as $item)
                    @php
                        $tglUsg      = \Carbon\Carbon::parse($item->tanggal)->format('Ymd');
                        $jamUsg      = str_replace(':', '', $item->jam ?? \Carbon\Carbon::parse($item->tanggal)->format('His'));
                        $usgNoOrder  = $item->noorder ?? strtoupper($item->usg_type);
                        $idStr       = "{$this->reg->no_rawat}-DR_USG_{$usgNoOrder}-{$tglUsg}-{$jamUsg}";
                        $syncedData  = $ssDiagnosticReports->where('local_id', $idStr)->first();
                        $hasSR       = $ssServiceRequests->contains(
                            fn($sr) => str_contains($sr->local_id, "SR_USG_{$usgNoOrder}"),
                        );
                        $hasIS       = $ssImagingStudies->where(
                            'local_id', str_replace('-DR_USG_', '-IMG_USG_', $idStr)
                        )->isNotEmpty();
                        $hasObsUsg = $ssObservations
                            ->where('local_id', str_replace('-DR_USG_', '-OBS_USG_', $idStr))
                            ->isNotEmpty();
                        $hasilOk = trim($item->kesimpulan ?? '') !== '';
                    @endphp
                    @php
                        $dicomStudy = $dicomStudies->get($item->noorder);
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedData)
                                <flux:icon name="check-circle" variant="solid"
                                    class="w-5 h-5 text-green-500 mx-auto" />
                            @elseif (!$hasSR || !$hasIS || !$hasObsUsg || !$hasilOk)
                                <flux:icon name="lock-closed"
                                    class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 mx-auto"
                                    title="Prasyarat belum terpenuhi" />
                            @else
                                <input type="checkbox" wire:model="ssSelectedDiagnosticReports"
                                    value="{{ $idStr }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </td>
                        <td class="{{ $tdMono }}">
                            {{ $item->noorder ?? '-' }}
                            @if ($dicomStudy)
                                <div class="mt-1 flex items-center gap-1">
                                    <flux:badge
                                        color="{{ match ($dicomStudy->status) {
                                            'sent' => 'green',
                                            'received' => 'blue',
                                            'error' => 'red',
                                            default => 'zinc',
                                        } }}"
                                        size="sm">
                                        {{ $dicomStudy->status }}
                                    </flux:badge>
                                </div>
                            @endif
                        </td>
                        <td class="{{ $tdText }}">{{ $item->usg_label }}</td>
                        <td class="{{ $tdMuted }} text-xs">
                            {{ \Carbon\Carbon::parse($item->tanggal)->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="{{ $tdMuted }}">
                            @if ($hasilOk)
                                <span class="text-xs text-zinc-700 dark:text-primary-dark-200 line-clamp-2">
                                    {{ $item->kesimpulan }}
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                    <flux:icon name="exclamation-triangle" class="w-3.5 h-3.5 shrink-0" />
                                    Hasil belum diisi
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex flex-col gap-1">
                                @if ($hasSR)
                                    <flux:badge color="emerald" size="sm" icon="check-circle">Serv. Request
                                    </flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="x-circle">Belum ada SR
                                    </flux:badge>
                                @endif
                                @if ($hasIS)
                                    <flux:badge color="emerald" size="sm" icon="check-circle">Imaging Study
                                    </flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="x-circle">Belum ada Study
                                    </flux:badge>
                                @endif
                                @if ($hasObsUsg)
                                    <flux:badge color="emerald" size="sm" icon="check-circle">Observation
                                    </flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="x-circle">Belum ada Obs.
                                    </flux:badge>
                                @endif
                            </div>
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
                                    title="Lihat detail">
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
