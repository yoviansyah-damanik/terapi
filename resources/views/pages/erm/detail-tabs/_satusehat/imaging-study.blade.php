<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Imaging Study
        <flux:badge color="{{ $ssImagingStudies->count() > 0 ? 'primary' : 'zinc' }}" size="sm">
            {{ $ssImagingStudies->count() }}</flux:badge>
    </p>
    <x-atoms.button wire:click="sendSsImagingStudies" wire:loading.attr="disabled" :disabled="$isBundleProcessing"
        @class(['opacity-50 cursor-not-allowed' => $isBundleProcessing]) icon="arrow-path-rounded-square" size="sm">
        <span wire:loading.remove wire:target="sendSsImagingStudies">Transfer DICOM & Sinkronisasi</span>
        <span wire:loading wire:target="sendSsImagingStudies">Memproses...</span>
    </x-atoms.button>
</div>

@if ($permintaanRadiologis->isNotEmpty())
    <div class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
        @foreach ($permintaanRadiologis as $pr)
            @php
                $periksaRad = $pr->periksa_rad->first();
                $tglIS = $periksaRad?->tgl_periksa ?? $pr->tgl_permintaan;
                $jamIS = $periksaRad?->jam ?? $pr->jam_permintaan;
                $idStr =
                    $this->reg->no_rawat .
                    '-IMG_RAD_' .
                    $pr->noorder .
                    '-' .
                    ($tglIS ? $tglIS->format('Ymd') : '') .
                    '-' .
                    str_replace(':', '', $jamIS ?? '000000');
                $syncedData = $ssImagingStudies->firstWhere('local_id', $idStr);
                $hasilRad = $pr->hasilRadiologi;
                $gambarRads = $pr->allGambarRadiologi->filter(
                    fn($g) => $periksaRad &&
                        $g->tgl_periksa?->format('Y-m-d') === $periksaRad->tgl_periksa?->format('Y-m-d') &&
                        $g->jam === $periksaRad->jam,
                );
                $dicomStudy = $dicomStudies->get($pr->noorder);
                $routerResps = $dicomRouterResponses->get($pr->noorder, collect());
                $latestResp = $routerResps->first();
                $hasSR = $ssServiceRequests->contains(
                    fn($sr) => $sr->note === 'RAD' && str_contains($sr->local_id, $pr->noorder),
                );
                $dicomReceived =
                    $dicomStudy &&
                    $dicomStudy->status == 'received' &&
                    $dicomStudy->series_count > 0 &&
                    $dicomStudy->instance_count > 0;
                $canSendImgStudy = $hasSR && $dicomReceived;
                $dicomStatusColor = $dicomStudy
                    ? match ($dicomStudy->status) {
                        'sent' => 'green',
                        'received' => 'blue',
                        'worklist' => 'zinc',
                        'error' => 'red',
                        default => 'amber',
                    }
                    : 'zinc';
            @endphp

            <div class="p-4 space-y-3">
                {{-- Header order --}}
                <div class="flex items-start justify-between gap-3">
                    <div class="flex flex-col gap-0.5">
                        <div class="flex items-center gap-2">
                            <span
                                class="{{ $tdMono }} text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $pr->noorder }}</span>

                            @if ($periksaRad)
                                <flux:badge color="zinc" size="sm">{{ $periksaRad->kd_jenis_prw }}</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm">Menunggu pemeriksaan</flux:badge>
                            @endif
                        </div>
                        <span class="text-xs text-zinc-500 dark:text-primary-dark-400">
                            Tgl. Permintaan: {{ $pr->tgl_permintaan?->format('d/m/Y') }} {{ $pr->jam_permintaan }}
                            @if ($pr->dokterPerujuk)
                                &bull; Dokter: {{ $pr->dokterPerujuk->nm_dokter }}
                            @endif
                        </span>
                        @if ($periksaRad)
                            <span class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300">
                                {{ $periksaRad->jenisPerawatan?->nm_perawatan ?? '-' }}
                            </span>
                            <span class="text-xs text-zinc-400">
                                Tgl. Periksa: {{ $periksaRad->tgl_periksa?->format('d/m/Y') }} {{ $periksaRad->jam }}
                            </span>
                        @endif
                    </div>

                    {{-- Checkbox atau status FHIR --}}
                    <div class="shrink-0 flex flex-col items-end gap-1">
                        @if ($syncedData)
                            <div class="flex items-center gap-1 text-green-600 dark:text-green-400">
                                <flux:icon name="check-circle" variant="solid" class="w-4 h-4" />
                                <span class="text-xs font-semibold">Tersinkronisasi IHS</span>
                            </div>
                            <span class="text-[10px] font-mono text-zinc-400">{{ $syncedData->ihs_number }}</span>
                            <span
                                class="text-[10px] text-zinc-400">{{ $syncedData->synced_at?->format('d/m/Y H:i') }}</span>
                            <button type="button" wire:click="openSsDetail('{{ $syncedData->ihs_number }}')"
                                class="mt-1 flex items-center gap-1 text-xs text-sky-600 hover:text-sky-700 dark:text-sky-400"
                                title="Lihat detail FHIR">
                                <flux:icon name="eye" class="w-3.5 h-3.5" />
                                Detail
                            </button>
                        @else
                            @if ($canSendImgStudy)
                                <input type="checkbox" wire:model="ssSelectedImagingStudies"
                                    value="{{ $idStr }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @else
                                <flux:icon name="lock-closed" class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600"
                                    title="Prasyarat belum terpenuhi" />
                            @endif
                            <div class="flex flex-col gap-1 mt-1 items-end">
                                @if ($hasSR)
                                    <flux:badge color="emerald" size="sm" icon="check-circle">Serv. Request
                                    </flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="x-circle">Belum ada SR</flux:badge>
                                @endif
                                @if ($dicomStudy && $dicomStudy->status === 'received')
                                    <flux:badge color="emerald" size="sm" icon="check-circle">DICOM Received
                                    </flux:badge>
                                @elseif ($dicomStudy)
                                    <flux:badge color="amber" size="sm">DICOM: {{ $dicomStudy->status }}
                                    </flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="x-circle">Belum ada DICOM
                                    </flux:badge>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Detail DICOM Order (jika ada dicom_study) --}}
                @if ($dicomStudy)
                    <div
                        class="rounded-lg border border-blue-100 dark:border-blue-900/40 bg-blue-50/50 dark:bg-blue-950/20 p-3 space-y-2">
                        <div class="flex items-center gap-1.5 mb-1">
                            <flux:icon name="photo" class="w-3.5 h-3.5 text-blue-500" />
                            <span class="text-xs font-semibold text-blue-700 dark:text-blue-400">DICOM Order</span>
                            <flux:badge color="{{ $dicomStatusColor }}" size="sm">{{ $dicomStudy->status }}
                            </flux:badge>
                        </div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                            @if ($dicomStudy->study_instance_uid)
                                <div>
                                    <span class="text-zinc-400">Study Instance UID</span>
                                    <p class="font-mono text-[10px] text-zinc-600 dark:text-primary-dark-300 break-all">
                                        {{ $dicomStudy->study_instance_uid }}</p>
                                </div>
                            @endif
                            @if ($dicomStudy->orthanc_study_id)
                                <div>
                                    <span class="text-zinc-400">Orthanc Study ID</span>
                                    <p class="font-mono text-[10px] text-zinc-600 dark:text-primary-dark-300">
                                        {{ $dicomStudy->orthanc_study_id }}</p>
                                </div>
                            @endif
                            @if ($dicomStudy->modality)
                                <div>
                                    <span class="text-zinc-400">Modality</span>
                                    <p class="font-semibold text-zinc-700 dark:text-primary-dark-200">
                                        {{ $dicomStudy->modality }}</p>
                                </div>
                            @endif
                            @if ($dicomStudy->ae_title)
                                <div>
                                    <span class="text-zinc-400">AE Title</span>
                                    <p class="font-mono text-zinc-700 dark:text-primary-dark-200">
                                        {{ $dicomStudy->ae_title }}</p>
                                </div>
                            @endif
                            @if ($dicomStudy->series_count || $dicomStudy->instance_count)
                                <div>
                                    <span class="text-zinc-400">Series / Instance</span>
                                    <p class="text-zinc-700 dark:text-primary-dark-200">{{ $dicomStudy->series_count }}
                                        series, {{ $dicomStudy->instance_count }} instance</p>
                                </div>
                            @endif
                            @if ($dicomStudy->sent_to_router_at)
                                <div>
                                    <span class="text-zinc-400">Dikirim ke Router</span>
                                    <p class="text-zinc-700 dark:text-primary-dark-200">
                                        {{ $dicomStudy->sent_to_router_at->format('d/m/Y H:i') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- DICOM Router Responses --}}
                @if ($latestResp)
                    <div class="space-y-1.5">
                        <p
                            class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400 flex items-center gap-1">
                            <flux:icon name="arrow-path" class="w-3.5 h-3.5" />
                            Webhook Router (Terbaru)
                        </p>
                        <div @class([
                            'rounded-lg border p-3 space-y-1.5 text-xs',
                            'border-green-200 bg-green-50/50 dark:border-green-900/40 dark:bg-green-950/20' =>
                                $latestResp->status,
                            'border-red-200 bg-red-50/50 dark:border-red-900/40 dark:bg-red-950/20' => !$latestResp->status,
                        ])>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    @if ($latestResp->status)
                                        <flux:icon name="check-circle" variant="solid"
                                            class="w-3.5 h-3.5 text-green-500" />
                                        <span
                                            class="font-semibold text-green-700 dark:text-green-400">{{ $latestResp->message ?? 'Berhasil' }}</span>
                                    @else
                                        <flux:icon name="x-circle" variant="solid" class="w-3.5 h-3.5 text-red-500" />
                                        <span
                                            class="font-semibold text-red-700 dark:text-red-400">{{ $latestResp->message ?? 'Gagal' }}</span>
                                    @endif
                                    @if ($latestResp->stage)
                                        <flux:badge color="zinc" size="sm">{{ $latestResp->stage }}</flux:badge>
                                    @endif
                                </div>
                                <span
                                    class="text-zinc-400 text-[10px]">{{ $latestResp->created_at->format('d/m/Y H:i:s') }}</span>
                            </div>
                            @if ($latestResp->imaging_study_ihs)
                                <div class="flex gap-2">
                                    <span class="text-zinc-400 shrink-0">ImagingStudy IHS</span>
                                    <span
                                        class="font-mono text-[10px] text-zinc-600 dark:text-primary-dark-300">{{ $latestResp->imaging_study_ihs }}</span>
                                </div>
                            @endif
                            @if ($latestResp->study_instance_uid)
                                <div class="flex gap-2">
                                    <span class="text-zinc-400 shrink-0">Study UID</span>
                                    <span
                                        class="font-mono text-[10px] text-zinc-600 dark:text-primary-dark-300 break-all">{{ $latestResp->study_instance_uid }}</span>
                                </div>
                            @endif
                            @if (!empty($latestResp->errors))
                                <div class="text-red-600 dark:text-red-400 font-mono text-[10px]">
                                    {{ is_string($latestResp->errors) ? $latestResp->errors : json_encode($latestResp->errors) }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Gambar Radiologi (jika ada dari Orthanc) --}}
                @if ($gambarRads->isNotEmpty())
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400">Gambar
                            ({{ $gambarRads->count() }})</p>
                        @foreach ($gambarRads as $gambar)
                            <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-primary-dark-400">
                                <flux:icon name="film" class="w-3.5 h-3.5 shrink-0" />
                                <span class="font-mono text-[10px] break-all">{{ $gambar->lokasi_gambar }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        {{-- Section USG --}}
        @foreach ($usgResults as $usgKey => $usg)
            @foreach ($usg['data'] as $usgItem)
                @php
                    $tglUSG = \Carbon\Carbon::parse($usgItem->tanggal);
                    $jamUSG = $tglUSG->format('H:i:s');
                    $usgNoOrder = $usgItem->noorder;
                    $idStr =
                        $this->reg->no_rawat .
                        '-IMG_USG_' .
                        $usgNoOrder .
                        '-' .
                        $tglUSG->format('Ymd') .
                        '-' .
                        str_replace(':', '', $jamUSG);
                    $syncedData = $ssImagingStudies->firstWhere('local_id', $idStr);
                    $hasSR = $ssServiceRequests->contains(
                        fn($sr) => $sr->note === 'USG' && str_contains($sr->local_id, $usgItem->noorder ?? 'NULL'),
                    );
                    $dicomStudy = $dicomStudies->get($usgItem->noorder);
                    $routerResps = $dicomRouterResponses->get($usgItem->noorder, collect());
                    $latestResp = $routerResps->first();
                    $dicomReceived =
                        $dicomStudy &&
                        $dicomStudy->status === 'received' &&
                        $dicomStudy->series_count > 0 &&
                        $dicomStudy->instance_count > 0;
                    $canSendImgStudy = $hasSR && $dicomReceived;
                    $dicomStatusColor = $dicomStudy
                        ? match ($dicomStudy->status) {
                            'sent' => 'green',
                            'received' => 'blue',
                            'worklist' => 'zinc',
                            'error' => 'red',
                            default => 'amber',
                        }
                        : 'zinc';
                @endphp
                <div class="p-4 space-y-3 border-t border-zinc-100 dark:border-primary-dark-700 bg-zinc-50/30">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex flex-col gap-0.5">
                            <div class="flex items-center gap-2">
                                <flux:icon name="signal" class="w-4 h-4 text-zinc-400" />
                                <span
                                    class="{{ $tdMono }} text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">
                                    {{ $usgItem->noorder ?? '-' }}
                                </span>
                                <span class="text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">USG
                                    {{ strtoupper($usgKey) }}</span>
                                <flux:badge color="zinc" size="sm">{{ $usg['label'] }}</flux:badge>
                            </div>
                            <span class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                Tgl. Pemeriksaan: {{ $tglUSG->format('d/m/Y') }} {{ $jamUSG }}
                            </span>
                        </div>

                        <div class="shrink-0 flex flex-col items-end gap-1">
                            @if ($syncedData)
                                <div class="flex items-center gap-1 text-green-600 dark:text-green-400">
                                    <flux:icon name="check-circle" variant="solid" class="w-4 h-4" />
                                    <span class="text-xs font-semibold">Tersinkronisasi IHS</span>
                                </div>
                                <span class="text-[10px] font-mono text-zinc-400">{{ $syncedData->ihs_number }}</span>
                                <span
                                    class="text-[10px] text-zinc-400">{{ $syncedData->synced_at?->format('d/m/Y H:i') }}</span>
                            @else
                                @if ($canSendImgStudy)
                                    <input type="checkbox" wire:model="ssSelectedImagingStudies"
                                        value="{{ $idStr }}"
                                        class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                                @else
                                    <flux:icon name="lock-closed"
                                        class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600"
                                        title="Prasyarat belum terpenuhi" />
                                @endif
                                <div class="flex flex-col gap-1 mt-1 items-end">
                                    @if ($hasSR)
                                        <flux:badge color="emerald" size="sm" icon="check-circle">Serv. Request
                                        </flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm" icon="x-circle">Belum ada SR
                                        </flux:badge>
                                    @endif
                                    @if ($dicomStudy && $dicomStudy->status === 'received')
                                        <flux:badge color="emerald" size="sm" icon="check-circle">DICOM Received
                                        </flux:badge>
                                    @elseif ($dicomStudy)
                                        <flux:badge color="amber" size="sm">DICOM: {{ $dicomStudy->status }}
                                        </flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm" icon="x-circle">Belum ada DICOM
                                        </flux:badge>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Detail DICOM Order (jika ada dicom_study) --}}
                    @if ($dicomStudy)
                        <div
                            class="rounded-lg border border-blue-100 dark:border-blue-900/40 bg-blue-50/50 dark:bg-blue-950/20 p-3 space-y-2">
                            <div class="flex items-center gap-1.5 mb-1">
                                <flux:icon name="photo" class="w-3.5 h-3.5 text-blue-500" />
                                <span class="text-xs font-semibold text-blue-700 dark:text-blue-400">DICOM Order</span>
                                <flux:badge color="{{ $dicomStatusColor }}" size="sm">{{ $dicomStudy->status }}
                                </flux:badge>
                            </div>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                @if ($dicomStudy->study_instance_uid)
                                    <div>
                                        <span class="text-zinc-400">Study Instance UID</span>
                                        <p
                                            class="font-mono text-[10px] text-zinc-600 dark:text-primary-dark-300 break-all">
                                            {{ $dicomStudy->study_instance_uid }}</p>
                                    </div>
                                @endif
                                @if ($dicomStudy->orthanc_study_id)
                                    <div>
                                        <span class="text-zinc-400">Orthanc Study ID</span>
                                        <p class="font-mono text-[10px] text-zinc-600 dark:text-primary-dark-300">
                                            {{ $dicomStudy->orthanc_study_id }}</p>
                                    </div>
                                @endif
                                @if ($dicomStudy->modality)
                                    <div>
                                        <span class="text-zinc-400">Modality</span>
                                        <p class="font-semibold text-zinc-700 dark:text-primary-dark-200">
                                            {{ $dicomStudy->modality }}</p>
                                    </div>
                                @endif
                                @if ($dicomStudy->ae_title)
                                    <div>
                                        <span class="text-zinc-400">AE Title</span>
                                        <p class="font-mono text-zinc-700 dark:text-primary-dark-200">
                                            {{ $dicomStudy->ae_title }}</p>
                                    </div>
                                @endif
                                @if ($dicomStudy->series_count || $dicomStudy->instance_count)
                                    <div>
                                        <span class="text-zinc-400">Series / Instance</span>
                                        <p class="text-zinc-700 dark:text-primary-dark-200">
                                            {{ $dicomStudy->series_count }}
                                            series, {{ $dicomStudy->instance_count }} instance</p>
                                    </div>
                                @endif
                                @if ($dicomStudy->sent_to_router_at)
                                    <div>
                                        <span class="text-zinc-400">Dikirim ke Router</span>
                                        <p class="text-zinc-700 dark:text-primary-dark-200">
                                            {{ $dicomStudy->sent_to_router_at->format('d/m/Y H:i') }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- DICOM Router Responses --}}
                    @if ($latestResp)
                        <div class="space-y-1.5">
                            <p
                                class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400 flex items-center gap-1">
                                <flux:icon name="arrow-path" class="w-3.5 h-3.5" />
                                Webhook Router (Terbaru)
                            </p>
                            <div @class([
                                'rounded-lg border p-3 space-y-1.5 text-xs',
                                'border-green-200 bg-green-50/50 dark:border-green-900/40 dark:bg-green-950/20' =>
                                    $latestResp->status,
                                'border-red-200 bg-red-50/50 dark:border-red-900/40 dark:bg-red-950/20' => !$latestResp->status,
                            ])>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        @if ($latestResp->status)
                                            <flux:icon name="check-circle" variant="solid"
                                                class="w-3.5 h-3.5 text-green-500" />
                                            <span
                                                class="font-semibold text-green-700 dark:text-green-400">{{ $latestResp->message ?? 'Berhasil' }}</span>
                                        @else
                                            <flux:icon name="x-circle" variant="solid"
                                                class="w-3.5 h-3.5 text-red-500" />
                                            <span
                                                class="font-semibold text-red-700 dark:text-red-400">{{ $latestResp->message ?? 'Gagal' }}</span>
                                        @endif
                                        @if ($latestResp->stage)
                                            <flux:badge color="zinc" size="sm">{{ $latestResp->stage }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                    <span
                                        class="text-zinc-400 text-[10px]">{{ $latestResp->created_at->format('d/m/Y H:i:s') }}</span>
                                </div>
                                @if ($latestResp->imaging_study_ihs)
                                    <div class="flex gap-2">
                                        <span class="text-zinc-400 shrink-0">ImagingStudy IHS</span>
                                        <span
                                            class="font-mono text-[10px] text-zinc-600 dark:text-primary-dark-300">{{ $latestResp->imaging_study_ihs }}</span>
                                    </div>
                                @endif
                                @if ($latestResp->study_instance_uid)
                                    <div class="flex gap-2">
                                        <span class="text-zinc-400 shrink-0">Study UID</span>
                                        <span
                                            class="font-mono text-[10px] text-zinc-600 dark:text-primary-dark-300 break-all">{{ $latestResp->study_instance_uid }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($usg['images']->isNotEmpty())
                        <div class="space-y-1 pt-1">
                            <p class="text-[10px] font-semibold text-zinc-400 dark:text-primary-dark-500">Gambar USG
                                ({{ $usg['images']->count() }})
                            </p>
                            @foreach ($usg['images'] as $imgUrl)
                                <a href="{{ $imgUrl }}" target="_blank"
                                    class="flex items-center gap-2 text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                    <flux:icon name="film" class="w-3.5 h-3.5 shrink-0" />
                                    <span class="font-mono text-[10px] break-all truncate">{{ $imgUrl }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        @endforeach
    </div>
@else
    @if (empty($usgResults))
        <div class="flex flex-col items-center py-10">
            <flux:icon name="photo" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data Gambar Radiologi atau USG
                untuk
                kunjungan ini.</p>
        </div>
    @else
        <div class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
            {{-- Loop USG saja jika Radiologi kosong --}}
            @foreach ($usgResults as $usgKey => $usg)
                @foreach ($usg['data'] as $usgItem)
                    @php
                        $tglUSG = \Carbon\Carbon::parse($usgItem->tanggal);
                        $jamUSG = $tglUSG->format('H:i:s');
                        $usgNoOrder = $usgItem->noorder;

                        $idStr =
                            $this->reg->no_rawat .
                            '-IMG_USG_' .
                            $usgNoOrder .
                            '-' .
                            $tglUSG->format('Ymd') .
                            '-' .
                            str_replace(':', '', $jamUSG);
                        $syncedData = $ssImagingStudies->firstWhere('local_id', $idStr);
                        $hasSR = $ssServiceRequests->contains(
                            fn($sr) => $sr->note === 'USG' && str_contains($sr->local_id, $usgItem->noorder ?? 'NULL'),
                        );
                        $dicomStudy = $dicomStudies->get($usgItem->noorder);
                        $routerResps = $dicomRouterResponses->get($usgItem->noorder, collect());
                        $latestResp = $routerResps->first();
                        $dicomReceived =
                            $dicomStudy &&
                            $dicomStudy->status === 'received' &&
                            $dicomStudy->series_count > 0 &&
                            $dicomStudy->instance_count > 0;
                        $canSendImgStudy = $hasSR && $dicomReceived;
                        $dicomStatusColor = $dicomStudy
                            ? match ($dicomStudy->status) {
                                'sent' => 'green',
                                'received' => 'blue',
                                'worklist' => 'zinc',
                                'error' => 'red',
                                default => 'amber',
                            }
                            : 'zinc';
                    @endphp
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center gap-2">
                                    <flux:icon name="signal" class="w-4 h-4 text-zinc-400" />
                                    <span
                                        class="{{ $tdMono }} text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">
                                        {{ $usgItem->noorder ?? '-' }}
                                    </span>
                                    <span class="text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">USG
                                        {{ strtoupper($usgKey) }}</span>
                                    <flux:badge color="zinc" size="sm">{{ $usg['label'] }}</flux:badge>
                                </div>
                                <span class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                    Tgl. Pemeriksaan: {{ $tglUSG->format('d/m/Y') }} {{ $jamUSG }}
                                </span>
                            </div>

                            <div class="shrink-0 flex flex-col items-end gap-1">
                                @if ($syncedData)
                                    <div class="flex items-center gap-1 text-green-600 dark:text-green-400">
                                        <flux:icon name="check-circle" variant="solid" class="w-4 h-4" />
                                        <span class="text-xs font-semibold">Tersinkronisasi IHS</span>
                                    </div>
                                    <span
                                        class="text-[10px] font-mono text-zinc-400">{{ $syncedData->ihs_number }}</span>
                                    <span
                                        class="text-[10px] text-zinc-400">{{ $syncedData->synced_at?->format('d/m/Y H:i') }}</span>
                                @else
                                    @if ($canSendImgStudy)
                                        <input type="checkbox" wire:model="ssSelectedImagingStudies"
                                            value="{{ $idStr }}"
                                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                                    @else
                                        <flux:icon name="lock-closed"
                                            class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600"
                                            title="Prasyarat belum terpenuhi" />
                                    @endif
                                    <div class="flex flex-col gap-1 mt-1 items-end">
                                        @if ($hasSR)
                                            <flux:badge color="emerald" size="sm" icon="check-circle">Serv.
                                                Request</flux:badge>
                                        @else
                                            <flux:badge color="red" size="sm" icon="x-circle">Belum ada SR
                                            </flux:badge>
                                        @endif
                                        @if ($dicomStudy && $dicomStudy->status === 'received')
                                            <flux:badge color="emerald" size="sm" icon="check-circle">DICOM
                                                Received</flux:badge>
                                        @elseif ($dicomStudy)
                                            <flux:badge color="amber" size="sm">DICOM:
                                                {{ $dicomStudy->status }}</flux:badge>
                                        @else
                                            <flux:badge color="red" size="sm" icon="x-circle">Belum ada DICOM
                                            </flux:badge>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Detail DICOM Order (jika ada dicom_study) --}}
                        @if ($dicomStudy)
                            <div
                                class="rounded-lg border border-blue-100 dark:border-blue-900/40 bg-blue-50/50 dark:bg-blue-950/20 p-3 space-y-2">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <flux:icon name="photo" class="w-3.5 h-3.5 text-blue-500" />
                                    <span class="text-xs font-semibold text-blue-700 dark:text-blue-400">DICOM
                                        Order</span>
                                    <flux:badge color="{{ $dicomStatusColor }}" size="sm">
                                        {{ $dicomStudy->status }}
                                    </flux:badge>
                                </div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                    @if ($dicomStudy->study_instance_uid)
                                        <div>
                                            <span class="text-zinc-400">Study Instance UID</span>
                                            <p
                                                class="font-mono text-[10px] text-zinc-600 dark:text-primary-dark-300 break-all">
                                                {{ $dicomStudy->study_instance_uid }}</p>
                                        </div>
                                    @endif
                                    @if ($dicomStudy->orthanc_study_id)
                                        <div>
                                            <span class="text-zinc-400">Orthanc Study ID</span>
                                            <p class="font-mono text-[10px] text-zinc-600 dark:text-primary-dark-300">
                                                {{ $dicomStudy->orthanc_study_id }}</p>
                                        </div>
                                    @endif
                                    @if ($dicomStudy->modality)
                                        <div>
                                            <span class="text-zinc-400">Modality</span>
                                            <p class="font-semibold text-zinc-700 dark:text-primary-dark-200">
                                                {{ $dicomStudy->modality }}</p>
                                        </div>
                                    @endif
                                    @if ($dicomStudy->ae_title)
                                        <div>
                                            <span class="text-zinc-400">AE Title</span>
                                            <p class="font-mono text-zinc-700 dark:text-primary-dark-200">
                                                {{ $dicomStudy->ae_title }}</p>
                                        </div>
                                    @endif
                                    @if ($dicomStudy->series_count || $dicomStudy->instance_count)
                                        <div>
                                            <span class="text-zinc-400">Series / Instance</span>
                                            <p class="text-zinc-700 dark:text-primary-dark-200">
                                                {{ $dicomStudy->series_count }}
                                                series, {{ $dicomStudy->instance_count }} instance</p>
                                        </div>
                                    @endif
                                    @if ($dicomStudy->sent_to_router_at)
                                        <div>
                                            <span class="text-zinc-400">Dikirim ke Router</span>
                                            <p class="text-zinc-700 dark:text-primary-dark-200">
                                                {{ $dicomStudy->sent_to_router_at->format('d/m/Y H:i') }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- DICOM Router Responses --}}
                        @if ($latestResp)
                            <div class="space-y-1.5">
                                <p
                                    class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400 flex items-center gap-1">
                                    <flux:icon name="arrow-path" class="w-3.5 h-3.5" />
                                    Webhook Router (Terbaru)
                                </p>
                                <div @class([
                                    'rounded-lg border p-3 space-y-1.5 text-xs',
                                    'border-green-200 bg-green-50/50 dark:border-green-900/40 dark:bg-green-950/20' =>
                                        $latestResp->status,
                                    'border-red-200 bg-red-50/50 dark:border-red-900/40 dark:bg-red-950/20' => !$latestResp->status,
                                ])>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            @if ($latestResp->status)
                                                <flux:icon name="check-circle" variant="solid"
                                                    class="w-3.5 h-3.5 text-green-500" />
                                                <span
                                                    class="font-semibold text-green-700 dark:text-green-400">{{ $latestResp->message ?? 'Berhasil' }}</span>
                                            @else
                                                <flux:icon name="x-circle" variant="solid"
                                                    class="w-3.5 h-3.5 text-red-500" />
                                                <span
                                                    class="font-semibold text-red-700 dark:text-red-400">{{ $latestResp->message ?? 'Gagal' }}</span>
                                            @endif
                                            @if ($latestResp->stage)
                                                <flux:badge color="zinc" size="sm">{{ $latestResp->stage }}
                                                </flux:badge>
                                            @endif
                                        </div>
                                        <span
                                            class="text-zinc-400 text-[10px]">{{ $latestResp->created_at->format('d/m/Y H:i:s') }}</span>
                                    </div>
                                    @if ($latestResp->imaging_study_ihs)
                                        <div class="flex gap-2">
                                            <span class="text-zinc-400 shrink-0">ImagingStudy IHS</span>
                                            <span
                                                class="font-mono text-[10px] text-zinc-600 dark:text-primary-dark-300">{{ $latestResp->imaging_study_ihs }}</span>
                                        </div>
                                    @endif
                                    @if ($latestResp->study_instance_uid)
                                        <div class="flex gap-2">
                                            <span class="text-zinc-400 shrink-0">Study UID</span>
                                            <span
                                                class="font-mono text-[10px] text-zinc-600 dark:text-primary-dark-300 break-all">{{ $latestResp->study_instance_uid }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($usg['images']->isNotEmpty())
                            <div class="space-y-1 pt-1">
                                <p class="text-[10px] font-semibold text-zinc-400 dark:text-primary-dark-500">Gambar
                                    USG
                                    ({{ $usg['images']->count() }})
                                </p>
                                @foreach ($usg['images'] as $imgUrl)
                                    <a href="{{ $imgUrl }}" target="_blank"
                                        class="flex items-center gap-2 text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                        <flux:icon name="film" class="w-3.5 h-3.5 shrink-0" />
                                        <span
                                            class="font-mono text-[10px] break-all truncate">{{ $imgUrl }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>
    @endif
@endif
