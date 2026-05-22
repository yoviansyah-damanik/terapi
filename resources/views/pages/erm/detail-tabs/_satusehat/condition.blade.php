<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Condition
        <flux:badge color="{{ $ssConditions->count() > 0 ? 'primary' : 'zinc' }}" size="sm">
            {{ $ssConditions->count() }}</flux:badge>
    </p>
    <x-atoms.button wire:click="sendSsConditions" wire:loading.attr="disabled" icon="paper-airplane" size="sm">
        <span wire:loading.remove wire:target="sendSsConditions">Kirim Diagnosa</span>
        <span wire:loading wire:target="sendSsConditions">Mengirim...</span>
    </x-atoms.button>
</div>
@if ($diagnosas->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th class="{{ $thClass }} w-16 text-center">
                        <input type="checkbox"
                            x-on:change="$el.checked ? $wire.set('ssSelectedConditions', [{{ $diagnosas->map(fn($d) => "'" . $this->reg->no_rawat . '-CON_' . $d->kd_penyakit . '-' . $this->reg->tgl_registrasi->format('Ymd') . '-' . str_replace(':', '', $this->reg->jam_reg ?? '000000') . "'" )->implode(',') }}]) : $wire.set('ssSelectedConditions', [])"
                            class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                    </th>
                    <th class="{{ $thClass }}">Kode ICD</th>
                    <th class="{{ $thClass }}">Nama Diagnosa</th>
                    <th class="{{ $thClass }}">Status Sinkronisasi</th>
                    <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                @foreach ($diagnosas as $diag)
                    @php
                        $code = $diag->kd_penyakit;
                        $localId = $this->reg->no_rawat . '-CON_' . $code . '-' . $this->reg->tgl_registrasi->format('Ymd') . '-' . str_replace(':', '', $this->reg->jam_reg ?? '000000');
                        $syncedData = $ssConditions->where('local_id', $localId)->first();
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td class="px-4 py-2 text-center">
                            @if ($syncedData)
                                <flux:icon name="check-circle" variant="solid" class="w-5 h-5 text-green-500 mx-auto" />
                            @else
                                <input type="checkbox" wire:model="ssSelectedConditions" value="{{ $localId }}"
                                    class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-primary-dark-800 focus:ring-2 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                            @endif
                        </td>
                        <td class="{{ $tdMono }}">{{ $code }}</td>
                        <td class="{{ $tdText }}">
                            {{ $diag->penyakit?->nm_penyakit ?? '-' }}
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
        <flux:icon name="clipboard-document-list" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data Condition
            (Diagnosa) untuk kunjungan ini.</p>
    </div>
@endif
