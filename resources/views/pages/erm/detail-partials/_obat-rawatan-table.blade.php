@php $thClass = 'px-4 py-3 text-xs font-medium tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400'; @endphp
<div class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
    <div class="px-4 py-3 border-b bg-zinc-50 dark:bg-primary-dark-900 border-zinc-200 dark:border-primary-dark-700">
        <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">{{ $title }}</h4>
    </div>
    @if ($items->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th class="{{ $thClass }} text-left w-10">No</th>
                        <th class="{{ $thClass }} text-left">Tanggal</th>
                        <th class="{{ $thClass }} text-left">Nama</th>
                        <th class="{{ $thClass }} text-right">Jumlah</th>
                        <th class="{{ $thClass }} text-right hidden md:table-cell">Biaya</th>
                        <th class="{{ $thClass }} text-right">Total</th>
                        <th class="{{ $thClass }} text-left hidden sm:table-cell">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                    @foreach ($items as $index => $obat)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-3 text-sm text-zinc-500 dark:text-primary-dark-400">{{ $index + 1 }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm text-zinc-900 dark:text-primary-dark-100">{{ $obat->tgl_perawatan?->format('d/m/Y') }}</div>
                                <div class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $obat->jam }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                    {{ $obat->dataBarang?->nama_brng ?? $obat->kode_brng }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $obat->kode_brng }}</p>
                                @if ($obat->no_batch)
                                    @php $batch = $obat->dataBatch; @endphp
                                    <div class="mt-1 flex flex-wrap gap-x-3 text-[10px] text-zinc-400 dark:text-primary-dark-500">
                                        <span>Batch: <span class="font-medium text-zinc-500 dark:text-primary-dark-400">{{ $obat->no_batch }}</span></span>
                                        @if ($batch?->tgl_kadaluarsa)
                                            <span>Exp:
                                                <span class="font-medium {{ $batch->tgl_kadaluarsa->isPast() ? 'text-red-500 dark:text-red-400' : 'text-zinc-500 dark:text-primary-dark-400' }}">
                                                    {{ $batch->tgl_kadaluarsa->format('d/m/Y') }}
                                                </span>
                                            </span>
                                        @endif
                                        @if ($batch?->no_faktur)
                                            <span>Faktur: <span class="font-medium text-zinc-500 dark:text-primary-dark-400">{{ $batch->no_faktur }}</span></span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right whitespace-nowrap text-zinc-900 dark:text-primary-dark-100">
                                {{ rtrim(rtrim(number_format($obat->jml, 2, ',', '.'), '0'), ',') }}
                            </td>
                            <td class="hidden px-4 py-3 text-sm text-right whitespace-nowrap md:table-cell text-zinc-700 dark:text-primary-dark-300">
                                {{ number_format($obat->biaya_obat, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-right whitespace-nowrap text-zinc-900 dark:text-primary-dark-100">
                                {{ number_format($obat->total, 0, ',', '.') }}
                            </td>
                            <td class="hidden px-4 py-3 whitespace-nowrap sm:table-cell">
                                <flux:badge :color="$obat->status === 'Ralan' ? 'sky' : 'amber'" size="sm">{{ $obat->status }}</flux:badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-sm font-semibold text-right text-zinc-900 dark:text-primary-dark-100">
                            Total {{ $title }}
                        </td>
                        <td class="px-4 py-3 text-sm font-bold text-right whitespace-nowrap text-zinc-900 dark:text-primary-dark-100">
                            {{ number_format($items->sum('total'), 0, ',', '.') }}
                        </td>
                        <td class="hidden sm:table-cell"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @else
        <div class="flex flex-col items-center py-12">
            <flux:icon name="{{ $icon }}" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">{{ $emptyLabel }}</p>
        </div>
    @endif
</div>
