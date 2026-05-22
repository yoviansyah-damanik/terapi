<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Clinical Impression
        <flux:badge color="{{ $ssClinicalImpressions->count() > 0 ? 'primary' : 'zinc' }}" size="sm">
            {{ $ssClinicalImpressions->count() }}</flux:badge>
    </p>
    <x-atoms.button wire:click="sendSsClinicalImpressions" wire:loading.attr="disabled" icon="paper-airplane"
        size="sm">
        <span wire:loading.remove wire:target="sendSsClinicalImpressions">Kirim Clin.
            Impression</span>
        <span wire:loading wire:target="sendSsClinicalImpressions">Mengirim...</span>
    </x-atoms.button>
</div>
@php
    $impresionList = collect();
    foreach ($pemeriksaans as $p) {
        $tglR = $p->tgl_perawatan ? $p->tgl_perawatan->format('Ymd') : '';
        $jamR = str_replace(':', '', $p->jam_rawat ?? '000000');
        $idStr = "{$this->reg->no_rawat}-CLINICAL_IMPRESSION-{$tglR}-{$jamR}";
        $desc = collect([$p->keluhan, $p->penilaian, $p->tindak_lanjut])
            ->filter()
            ->implode('; ');
        if (!empty($desc)) {
            $impresionList->push(
                (object) [
                    'idStr' => $idStr,
                    'deskripsi' => \Illuminate\Support\Str::limit($desc, 120),
                    'waktu' => ($p->tgl_perawatan?->format('d/m/Y') ?? '') . ' ' . ($p->jam_rawat ?? ''),
                ],
            );
        }
    }
@endphp
@if ($impresionList->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">Pilih</th>
                    <th class="{{ $thClass }}">Evaluasi / Penilaian Medis</th>
                    <th class="{{ $thClass }}">Waktu Periksa</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($impresionList as $imp)
                    @php
                        $syncedData = $ssClinicalImpressions->where('local_id', $imp->idStr)->first();
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center align-top">
                            @if ($syncedData)
                                <flux:icon name="check-circle" variant="solid"
                                    class="w-5 h-5 text-green-500 mx-auto mt-1" />
                            @else
                                <input type="checkbox" wire:model="ssSelectedClinicalImpressions"
                                    value="{{ $imp->idStr }}"
                                    class="w-4 h-4 mt-1 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </td>
                        <td class="{{ $tdText }} align-top">
                            <div class="max-w-md whitespace-normal leading-relaxed text-xs">
                                {{ $imp->deskripsi }}</div>
                        </td>
                        <td class="{{ $tdMuted }} text-xs align-top whitespace-nowrap">
                            {{ $imp->waktu }}</td>
                        <td class="{{ $tdMuted }} align-top">
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
        <flux:icon name="eye" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data Clinical
            Impression untuk kunjungan ini.</p>
    </div>
@endif
