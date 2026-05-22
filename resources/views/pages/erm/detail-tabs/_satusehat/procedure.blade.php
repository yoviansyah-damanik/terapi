<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">ICD 9
        <flux:badge color="{{ $ssProcedures->count() > 0 ? 'primary' : 'zinc' }}" size="sm">
            {{ $ssProcedures->count() }}</flux:badge>
    </p>
    <x-atoms.button wire:click="sendSsProcedures" wire:loading.attr="disabled" icon="paper-airplane" size="sm">
        <span wire:loading.remove wire:target="sendSsProcedures">Kirim Prosedur</span>
        <span wire:loading wire:target="sendSsProcedures">Mengirim...</span>
    </x-atoms.button>
</div>
@if ($prosedurs->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked ? $wire.set('ssSelectedProcedures', [{{ $prosedurs->map(fn($p) => "'" . $this->reg->no_rawat . '-PRO_' . $p->kode . '-' . $this->reg->tgl_registrasi->format('Ymd') . '-' . str_replace(':', '', $this->reg->jam_reg ?? '000000') . "'")->implode(',') }}]) : $wire.set('ssSelectedProcedures', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">Kode ICD-9</th>
                    <th class="{{ $thClass }}">Deskripsi</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($prosedurs as $prosed)
                    @php
                        $code = $prosed->kode;
                        $localId =
                            $this->reg->no_rawat .
                            '-PRO_' .
                            $code .
                            '-' .
                            $this->reg->tgl_registrasi->format('Ymd') .
                            '-' .
                            str_replace(':', '', $this->reg->jam_reg ?? '000000');
                        $syncedData = $ssProcedures->where('local_id', $localId)->first();
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedData)
                                <flux:icon name="check-circle" variant="solid" class="w-5 h-5 text-green-500 mx-auto" />
                            @else
                                <input type="checkbox" wire:model="ssSelectedProcedures" value="{{ $localId }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </td>
                        <td class="{{ $tdMono }}">{{ $code }}</td>
                        <td class="{{ $tdText }}">
                            {{ $prosed->icd9?->deskripsi_panjang ?? ($prosed->icd9?->deskripsi_pendek ?? '-') }}
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
        <flux:icon name="hand-raised" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data ICD 9
            untuk
            kunjungan ini.</p>
    </div>
@endif

{{-- Tindakan SIMRS --}}
@if ($allTindakan->isNotEmpty())
    @php
        $tndSourceTable = $reg->status_lanjut === 'Ralan' ? 'jalan' : 'inap';
        $tndMapAll = \App\Models\Mapping\ProcedureMap::where('source_table', $tndSourceTable)
            ->whereIn('procedure_code', $allTindakan->pluck('kd_jenis_prw')->unique()->values())
            ->get()
            ->keyBy('procedure_code');

        // Hanya ID yang sudah ter-mapping SNOMED + Category yang boleh masuk select-all
        $tndSelectAllIds = $allTindakan
            ->filter(function ($t) use ($tndMapAll) {
                $m = $tndMapAll->get($t->kd_jenis_prw);
                return $m && $m->system_code && $m->category_code;
            })
            ->map(function ($t) {
                $tglFmt = $t->tgl_perawatan ? \Carbon\Carbon::parse($t->tgl_perawatan)->format('Ymd') : '';
                $jamFmt = str_replace(':', '', $t->jam_rawat ?? '000000');
                return "'" .
                    $this->reg->no_rawat .
                    '-TND_' .
                    $t->_suffix .
                    '_' .
                    $t->kd_jenis_prw .
                    '-' .
                    $tglFmt .
                    '-' .
                    $jamFmt .
                    "'";
            })
            ->implode(',');

        $tndReadyCount = $allTindakan
            ->filter(fn($t) => ($m = $tndMapAll->get($t->kd_jenis_prw)) && $m->system_code && $m->category_code)
            ->count();
    @endphp

    <div class="border-t border-zinc-100 dark:border-primary-dark-700">
        {{-- Sub-header --}}
        <div class="flex items-center justify-between px-4 py-3 bg-zinc-50/80 dark:bg-primary-dark-900/40">
            <div class="flex items-center gap-2.5">
                <flux:icon name="scissors" class="w-4 h-4 text-zinc-400 dark:text-primary-dark-500" />
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-primary-dark-300">
                    Tindakan SIMRS
                </span>
                <flux:badge color="zinc" size="sm">{{ $allTindakan->count() }}</flux:badge>
                @if ($tndReadyCount > 0)
                    <flux:badge color="emerald" size="sm">{{ $tndReadyCount }} siap kirim</flux:badge>
                @endif
            </div>
            @if ($tndReadyCount < $allTindakan->count())
                <span class="text-[11px] text-amber-600 dark:text-amber-400 flex items-center gap-1">
                    <flux:icon name="exclamation-triangle" class="w-3.5 h-3.5" />
                    {{ $allTindakan->count() - $tndReadyCount }} belum ter-mapping SNOMED
                </span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th class="{{ $thClass }} w-14 text-center">
                            @if ($tndSelectAllIds)
                                <input type="checkbox"
                                    x-on:change="$el.checked ? $wire.set('ssSelectedProcedures', [{{ $tndSelectAllIds }}]) : $wire.set('ssSelectedProcedures', [])"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </th>
                        <th class="{{ $thClass }}">Kode</th>
                        <th class="{{ $thClass }}">Nama Tindakan</th>
                        <th class="{{ $thClass }}">Sumber</th>
                        <th class="{{ $thClass }}">Prasyarat</th>
                        <th class="{{ $thClass }}">Waktu</th>
                        <th class="{{ $thClass }}">Status Sinkronisasi</th>
                        <th class="{{ $thClass }} w-14 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                    @foreach ($allTindakan as $tnd)
                        @php
                            $tglFmt = $tnd->tgl_perawatan
                                ? \Carbon\Carbon::parse($tnd->tgl_perawatan)->format('Ymd')
                                : '';
                            $jamFmt = str_replace(':', '', $tnd->jam_rawat ?? '000000');
                            $localId =
                                $this->reg->no_rawat .
                                '-TND_' .
                                $tnd->_suffix .
                                '_' .
                                $tnd->kd_jenis_prw .
                                '-' .
                                $tglFmt .
                                '-' .
                                $jamFmt;
                            $syncedData = $ssProcedures->where('local_id', $localId)->first();
                            $tndMap = $tndMapAll->get($tnd->kd_jenis_prw);
                            $hasSnomed = $tndMap && $tndMap->system_code;
                            $hasCat = $tndMap && $tndMap->category_code;
                            $isReady = $hasSnomed && $hasCat;

                            $sourceLabel = match ($tnd->_suffix) {
                                'DR' => 'Dokter',
                                'PR' => 'Perawat',
                                'DRPR' => 'Dokter & Perawat',
                                default => $tnd->_suffix,
                            };
                            $sourceColor = match ($tnd->_suffix) {
                                'DR' => 'sky',
                                'PR' => 'violet',
                                default => 'teal',
                            };
                        @endphp
                        <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            {{-- Checkbox --}}
                            <td class="px-4 py-2.5 text-center">
                                @if ($syncedData)
                                    <flux:icon name="check-circle" variant="solid"
                                        class="w-5 h-5 text-green-500 mx-auto" />
                                @elseif ($isReady)
                                    <input type="checkbox" wire:model="ssSelectedProcedures"
                                        value="{{ $localId }}"
                                        class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                                @else
                                    <flux:icon name="lock-closed"
                                        class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 mx-auto"
                                        title="Belum ter-mapping SNOMED CT" />
                                @endif
                            </td>

                            {{-- Kode --}}
                            <td class="{{ $tdMono }}">{{ $tnd->kd_jenis_prw }}</td>

                            {{-- Nama --}}
                            <td class="{{ $tdText }}">
                                <div class="font-medium">{{ $tnd->nm_perawatan ?? '-' }}</div>
                                @if ($hasSnomed)
                                    <div class="text-[10px] font-mono text-zinc-400 dark:text-primary-dark-500 mt-0.5">
                                        {{ $tndMap->system_code }} — {{ $tndMap->system_term }}
                                    </div>
                                @endif
                            </td>

                            {{-- Sumber --}}
                            <td class="px-4 py-2.5">
                                <flux:badge color="{{ $sourceColor }}" size="sm">{{ $sourceLabel }}
                                </flux:badge>
                            </td>

                            {{-- Prasyarat --}}
                            <td class="px-4 py-2.5">
                                @if (!$hasSnomed)
                                    <flux:badge color="red" size="sm" icon="x-circle">Belum di-mapping
                                    </flux:badge>
                                @elseif (!$hasCat)
                                    <div class="flex flex-col gap-1">
                                        <flux:badge color="emerald" size="sm" icon="check-circle">SNOMED Mapped
                                        </flux:badge>
                                        <flux:badge color="amber" size="sm" icon="clock">Tanpa Kategori
                                        </flux:badge>
                                    </div>
                                @else
                                    <div class="flex flex-col gap-1">
                                        <flux:badge color="emerald" size="sm" icon="check-circle">SNOMED Mapped
                                        </flux:badge>
                                        <flux:badge color="sky" size="sm" icon="tag">
                                            {{ $tndMap->category_term }}</flux:badge>
                                    </div>
                                @endif
                            </td>

                            {{-- Waktu --}}
                            <td class="{{ $tdMuted }} text-xs">
                                {{ $tnd->tgl_perawatan ? \Carbon\Carbon::parse($tnd->tgl_perawatan)->format('d/m/Y') : '-' }}
                                <span class="block font-mono">{{ $tnd->jam_rawat }}</span>
                            </td>

                            {{-- Status Sinkronisasi --}}
                            <td class="{{ $tdMuted }}">
                                @if ($syncedData)
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                        <span class="text-[10px] font-mono">{{ $syncedData->ihs_number }}</span>
                                        <span
                                            class="text-[10px]">{{ $syncedData->synced_at?->format('d/m/Y H:i') }}</span>
                                    </div>
                                @elseif ($isReady)
                                    <span class="text-zinc-400">Belum dikirim</span>
                                @else
                                    <span class="text-red-400 text-xs italic">Mapping diperlukan</span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="px-4 py-2.5 text-center">
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
@endif
