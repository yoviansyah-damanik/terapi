<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Medication Dispense
        <flux:badge color="{{ $ssMedicationDispenses->count() > 0 ? 'primary' : 'zinc' }}" size="sm">
            {{ $ssMedicationDispenses->count() }}</flux:badge>
    </p>
    <x-atoms.button wire:click="sendSsMedicationDispenses" wire:loading.attr="disabled" icon="paper-airplane" size="sm"
        :disabled="!$prereq_med_req || !$prereq_location_apotek">
        <span wire:loading.remove wire:target="sendSsMedicationDispenses">Kirim Med. Dispense</span>
        <span wire:loading wire:target="sendSsMedicationDispenses">Mengirim...</span>
    </x-atoms.button>
</div>
@if (!$prereq_med_req)
    <div class="flex items-center gap-2.5 px-4 py-3 bg-amber-50 dark:bg-amber-900/10 border-b border-amber-100 dark:border-amber-800/30">
        <flux:icon name="lock-closed" class="w-4 h-4 text-amber-500 shrink-0" />
        <p class="text-xs text-amber-700 dark:text-amber-400">
            <strong>Prasyarat belum terpenuhi:</strong> Kirim <span class="font-semibold">Medication Request</span>
            terlebih dahulu sebelum dapat mengirim Medication Dispense.
        </p>
    </div>
@endif
@if (!$prereq_location_apotek)
    <div class="flex items-center gap-2.5 px-4 py-3 bg-amber-50 dark:bg-amber-900/10 border-b border-amber-100 dark:border-amber-800/30">
        <flux:icon name="lock-closed" class="w-4 h-4 text-amber-500 shrink-0" />
        <p class="text-xs text-amber-700 dark:text-amber-400">
            <strong>Location Apotek belum dikonfigurasi:</strong> Daftarkan Location dengan type <span class="font-semibold">apotek</span> di menu Satu Sehat → FHIR Resource → Location sebelum dapat mengirim Medication Dispense.
        </p>
    </div>
@endif
@php
    $medListDisp = collect();
    foreach ($obats as $o) {
        $idStr =
            $this->reg->no_rawat .
            '-MED_DISP_' .
            $o->kode_brng .
            '-' .
            ($o->tgl_perawatan ? \Carbon\Carbon::parse($o->tgl_perawatan)->format('Ymd') : '') .
            '-' .
            str_replace(':', '', $o->jam ?? '');
        $medListDisp->push(
            (object) [
                'idStr' => $idStr,
                'kode_brng' => $o->kode_brng,
                'nama_brng' => $o->dataBarang?->nama_brng,
                'tgl' => $o->tgl_perawatan ? \Carbon\Carbon::parse($o->tgl_perawatan)->format('d/m/Y') : '',
                'jam' => $o->jam,
                'jml' => $o->jml,
            ],
        );
    }
    foreach ($resepPulangs as $r) {
        $idStr =
            $this->reg->no_rawat .
            '-MED_DISP_' .
            $r->kode_brng .
            '-' .
            ($r->tanggal ? \Carbon\Carbon::parse($r->tanggal)->format('Ymd') : '') .
            '-' .
            str_replace(':', '', $r->jam ?? '');
        $medListDisp->push(
            (object) [
                'idStr' => $idStr,
                'kode_brng' => $r->kode_brng,
                'nama_brng' => $r->dataBarang?->nama_brng,
                'tgl' => $r->tanggal ? \Carbon\Carbon::parse($r->tanggal)->format('d/m/Y') : '',
                'jam' => $r->jam,
                'jml' => $r->jml_barang,
            ],
        );
    }
@endphp
@if ($medListDisp->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        @if ($prereq_med_req)
                            <input type="checkbox"
                                x-on:change="$el.checked ? $wire.set('ssSelectedDispenses', {{ $medListDisp->pluck('idStr')->toJson() }}) : $wire.set('ssSelectedDispenses', [])"
                                class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                        @endif
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
                @foreach ($medListDisp as $med)
                    @php
                        $syncedData = $ssMedicationDispenses->where('local_id', $med->idStr)->first();
                        $reqLocalId = str_replace('-MED_DISP_', '-MED_REQ_', $med->idStr);
                        $reqSent = $ssMedications->where('local_id', $reqLocalId)->isNotEmpty();
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedData)
                                <flux:icon name="check-circle" variant="solid" class="w-5 h-5 text-green-500 mx-auto" />
                            @elseif ($reqSent)
                                <input type="checkbox" wire:model="ssSelectedDispenses" value="{{ $med->idStr }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @else
                                <flux:icon name="lock-closed"
                                    class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 mx-auto"
                                    title="MedicationRequest untuk item ini belum dikirim" />
                            @endif
                        </td>
                        <td class="{{ $tdText }}">
                            <div class="font-medium">{{ $med->nama_brng }}</div>
                            <div class="text-[11px] font-mono text-zinc-400">{{ $med->kode_brng }}
                            </div>
                        </td>
                        <td class="{{ $tdText }}">{{ $med->jml }}</td>
                        <td class="{{ $tdMuted }} text-xs">{{ $med->tgl }}
                            {{ $med->jam }}</td>
                        <td class="px-4 py-2">
                            @if ($reqSent)
                                <flux:badge color="emerald" size="sm" icon="check-circle">Med. Request</flux:badge>
                            @else
                                <flux:badge color="red" size="sm" icon="x-circle">Belum ada Request</flux:badge>
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
        <flux:icon name="archive-box" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data Pemberian
            Obat
            untuk kunjungan ini.</p>
    </div>
@endif
