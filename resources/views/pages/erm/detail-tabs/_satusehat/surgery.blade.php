<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Surgical Procedure (Operasi)
        <flux:badge color="{{ $ssProcedures->filter(fn($p) => str_contains($p->local_id, '-SURGERY-'))->count() > 0 ? 'primary' : 'zinc' }}" size="sm">
            {{ $ssProcedures->filter(fn($p) => str_contains($p->local_id, '-SURGERY-'))->count() }}</flux:badge>
    </p>
    <x-atoms.button wire:click="sendSsSurgeries" wire:loading.attr="disabled" icon="paper-airplane" size="sm" :disabled="empty($ssSelectedSurgeries)">
        <span wire:loading.remove wire:target="sendSsSurgeries">Kirim Operasi</span>
        <span wire:loading wire:target="sendSsSurgeries">Mengirim...</span>
    </x-atoms.button>
</div>
@if ($operasis->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked ? $wire.set('ssSelectedSurgeries', [{{ $operasis->map(function($op) {
                                $tglOp = $op->tgl_operasi instanceof \Carbon\Carbon ? $op->tgl_operasi : \Carbon\Carbon::parse($op->tgl_operasi);
                                return "'" . $this->reg->no_rawat . '-SURGERY-' . $op->kode_paket . '-' . $tglOp->format('YmdHis') . "'";
                            })->implode(',') }}]) : $wire.set('ssSelectedSurgeries', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">Tanggal</th>
                    <th class="{{ $thClass }}">Paket Operasi</th>
                    <th class="{{ $thClass }}">Kategori / Anasthesi</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($operasis as $op)
                    @php
                        $tglOp = $op->tgl_operasi instanceof \Carbon\Carbon ? $op->tgl_operasi : \Carbon\Carbon::parse($op->tgl_operasi);
                        $localId = $this->reg->no_rawat . '-SURGERY-' . $op->kode_paket . '-' . $tglOp->format('YmdHis');
                        $syncedData = $ssProcedures->where('local_id', $localId)->first();
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedData)
                                <flux:icon name="check-circle" variant="solid"
                                    class="w-5 h-5 text-green-500 mx-auto" />
                            @else
                                <input type="checkbox" wire:model.live="ssSelectedSurgeries" value="{{ $localId }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </td>
                        <td class="{{ $tdMuted }} whitespace-nowrap">
                            {{ $tglOp->format('d/m/Y') }}<br>
                            <span class="text-[10px]">{{ $op->jam_mulai }} - {{ $op->jam_selesai }}</span>
                        </td>
                        <td class="{{ $tdText }}">
                            <div class="font-medium">{{ $op->paket?->nm_perawatan ?? 'Paket ' . $op->kode_paket }}</div>
                            <div class="text-[10px] text-zinc-400 font-mono">#{{ $op->kode_paket }}</div>
                        </td>
                        <td class="{{ $tdMuted }}">
                            <div class="flex flex-col">
                                <span>{{ $op->kategori }}</span>
                                <span class="text-[10px]">{{ $op->jenis_anasthesi }}</span>
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
        <flux:icon name="scissors" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data tindakan operasi untuk kunjungan ini.</p>
    </div>
@endif
