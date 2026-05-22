<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Medication Administration
        <flux:badge color="{{ $ssMedicationAdministrations->count() > 0 ? 'primary' : 'zinc' }}" size="sm">
            {{ $ssMedicationAdministrations->count() }}</flux:badge>
    </p>
    <x-atoms.button wire:click="sendSsMedicationAdministrations" wire:loading.attr="disabled" icon="paper-airplane"
        size="sm" :disabled="!$prereq_med_req">
        <span wire:loading.remove wire:target="sendSsMedicationAdministrations">Kirim Med. Administration</span>
        <span wire:loading wire:target="sendSsMedicationAdministrations">Mengirim...</span>
    </x-atoms.button>
</div>
@if (!$prereq_med_req)
    <div class="flex items-center gap-2.5 px-4 py-3 bg-amber-50 dark:bg-amber-900/10 border-b border-amber-100 dark:border-amber-800/30">
        <flux:icon name="lock-closed" class="w-4 h-4 text-amber-500 shrink-0" />
        <p class="text-xs text-amber-700 dark:text-amber-400">
            <strong>Prasyarat belum terpenuhi:</strong> Kirim <span class="font-semibold">Medication Request</span> terlebih dahulu sebelum dapat mengirim Medication Administration.
        </p>
    </div>
@endif
@php
    $stmtList = collect();
    foreach ($obats as $o) {
        $kode = $o->kode_brng ?? null;
        if (!$kode) {
            continue;
        }
        $idStr =
            $this->reg->no_rawat .
            '-MED_ADMIN_' .
            $kode .
            '-' .
            ($o->tgl_perawatan ? $o->tgl_perawatan->format('Ymd') : '') .
            '-' .
            str_replace(':', '', $o->jam ?? '');
        $stmtList->push(
            (object) [
                'idStr' => $idStr,
                'kode' => $kode,
                'nama' => $o->dataBarang?->nama_brng ?? $kode,
                'aturan' => $o->aturanPakai?->aturan ?? '-',
                'jumlah' => $o->jml ? number_format($o->jml, 0) : '-',
                'waktu' => ($o->tgl_perawatan ? $o->tgl_perawatan->format('d/m/Y') : '') . ' ' . ($o->jam ?? ''),
                'sumber' => 'Pemberian',
            ],
        );
    }
    foreach ($resepPulangs as $rp) {
        $kode = $rp->kode_brng ?? null;
        if (!$kode) {
            continue;
        }
        $idStr =
            $this->reg->no_rawat .
            '-MED_ADMIN_' .
            $kode .
            '-' .
            ($rp->tanggal ? $rp->tanggal->format('Ymd') : '') .
            '-' .
            str_replace(':', '', $rp->jam ?? '');
        $stmtList->push(
            (object) [
                'idStr' => $idStr,
                'kode' => $kode,
                'nama' => $rp->dataBarang?->nama_brng ?? $kode,
                'aturan' => $rp->dosis ?? '-',
                'jumlah' => $rp->jml_barang ? number_format($rp->jml_barang, 0) : '-',
                'waktu' => ($rp->tanggal ? $rp->tanggal->format('d/m/Y') : '') . ' ' . ($rp->jam ?? ''),
                'sumber' => 'Resep Pulang',
            ],
        );
    }
@endphp
@if ($stmtList->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-12 text-center">
                        @if ($prereq_med_req)
                            <input type="checkbox"
                                x-on:change="$el.checked ? $wire.set('ssSelectedMedicationAdministrations', {{ $stmtList->pluck('idStr')->toJson() }}) : $wire.set('ssSelectedMedicationAdministrations', [])"
                                class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                        @endif
                    </th>
                    <th class="{{ $thClass }}">Nama Obat</th>
                    <th class="{{ $thClass }}">Jumlah</th>
                    <th class="{{ $thClass }}">Sumber</th>
                    <th class="{{ $thClass }}">Waktu</th>
                    <th class="{{ $thClass }}">Prasyarat</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($stmtList as $item)
                    @php
                        $syncedAdmin = $ssMedicationAdministrations->where('local_id', $item->idStr)->first();
                        $reqLocalId = str_replace('-MED_ADMIN_', '-MED_REQ_', $item->idStr);
                        $hasRequest = $ssMedications->where('local_id', $reqLocalId)->first();
                        $reqSent = \App\Models\SatuSehat\SatuSehatMedicationRequest::where(
                            'local_id',
                            $reqLocalId,
                        )->exists();
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedAdmin)
                                <flux:icon name="check-circle" variant="solid" class="w-5 h-5 text-green-500 mx-auto" />
                            @elseif (!$reqSent)
                                <flux:icon name="lock-closed"
                                    class="w-4 h-4 text-zinc-300 dark:text-primary-dark-600 mx-auto"
                                    title="MedicationRequest belum dikirim" />
                            @else
                                <input type="checkbox" wire:model="ssSelectedMedicationAdministrations"
                                    value="{{ $item->idStr }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </td>
                        <td class="{{ $tdText }}">
                            <div class="font-medium">{{ $item->nama }}</div>
                            <div class="text-[11px] font-mono text-zinc-400">{{ $item->kode }}
                            </div>
                        </td>
                        <td class="{{ $tdMuted }}">{{ $item->jumlah }}</td>
                        <td class="px-4 py-2">
                            <flux:badge size="sm"
                                color="{{ $item->sumber === 'Resep Pulang' ? 'lime' : 'blue' }}">
                                {{ $item->sumber }}
                            </flux:badge>
                        </td>
                        <td class="{{ $tdMuted }} text-xs">{{ $item->waktu }}</td>
                        <td class="px-4 py-2">
                            @if ($reqSent)
                                <flux:badge color="emerald" size="sm" icon="check-circle">
                                    Med.
                                    Request</flux:badge>
                            @else
                                <flux:badge color="red" size="sm" icon="x-circle">Belum ada Request</flux:badge>
                            @endif
                        </td>
                        <td class="{{ $tdMuted }}">
                            @if ($syncedAdmin)
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                    <span class="text-[10px] font-mono">{{ $syncedAdmin->ihs_number }}</span>
                                    <span
                                        class="text-[10px]">{{ $syncedAdmin->synced_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            @else
                                <span class="text-zinc-400">Belum didaftarkan</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-center">
                            @if ($syncedAdmin)
                                <button type="button" wire:click="openSsDetail('{{ $syncedAdmin->ihs_number }}')"
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
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">
            Tidak ada data pemberian obat untuk kunjungan ini.
        </p>
    </div>
@endif
