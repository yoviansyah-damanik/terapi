@php
    $tindakanGroups = [
        [
            'label' => 'Tindakan Dokter Rawat Jalan',
            'items' => $tindakanJalanDr,
            'hasDr' => true,
            'hasPr' => false,
            'color' => 'sky',
        ],
        [
            'label' => 'Tindakan Perawat Rawat Jalan',
            'items' => $tindakanJalanPr,
            'hasDr' => false,
            'hasPr' => true,
            'color' => 'teal',
        ],
        [
            'label' => 'Tindakan Dokter & Perawat Rawat Jalan',
            'items' => $tindakanJalanDrPr,
            'hasDr' => true,
            'hasPr' => true,
            'color' => 'indigo',
        ],
        [
            'label' => 'Tindakan Dokter Rawat Inap',
            'items' => $tindakanInapDr,
            'hasDr' => true,
            'hasPr' => false,
            'color' => 'amber',
        ],
        [
            'label' => 'Tindakan Perawat Rawat Inap',
            'items' => $tindakanInapPr,
            'hasDr' => false,
            'hasPr' => true,
            'color' => 'orange',
        ],
        [
            'label' => 'Tindakan Dokter & Perawat Rawat Inap',
            'items' => $tindakanInapDrPr,
            'hasDr' => true,
            'hasPr' => true,
            'color' => 'rose',
        ],
    ];
    $hasTindakan = collect($tindakanGroups)->contains(fn($g) => $g['items']->isNotEmpty());
@endphp
@if ($hasTindakan)
    <div class="space-y-4">
        @foreach ($tindakanGroups as $group)
            @if ($group['items']->isNotEmpty())
                <div
                    class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                    <div
                        class="px-4 py-3 border-b bg-{{ $group['color'] }}-50 dark:bg-{{ $group['color'] }}-900/20 border-{{ $group['color'] }}-200 dark:border-{{ $group['color'] }}-700">
                        <h4
                            class="flex items-center gap-2 text-sm font-semibold text-{{ $group['color'] }}-900 dark:text-{{ $group['color'] }}-100">
                            <flux:icon name="hand-raised" class="w-4 h-4" />
                            {{ $group['label'] }}
                            <flux:badge color="zinc" size="sm">{{ $group['items']->count() }}
                            </flux:badge>
                        </h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Tanggal & Jam</th>
                                    <th
                                        class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Kode</th>
                                    <th
                                        class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Nama Tindakan</th>
                                    @if ($group['hasDr'])
                                        <th
                                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                            Dokter</th>
                                    @endif
                                    @if ($group['hasPr'])
                                        <th
                                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                            NIP Perawat</th>
                                    @endif
                                    <th
                                        class="px-4 py-3 text-xs font-medium tracking-wider text-right uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Biaya</th>
                                </tr>
                            </thead>
                            <tbody
                                class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                                @foreach ($group['items'] as $item)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                        <td
                                            class="px-4 py-3 whitespace-nowrap text-sm text-zinc-500 dark:text-primary-dark-400">
                                            {{ $item->tgl_perawatan ?? '-' }}
                                            @if ($item->jam_rawat)
                                                <span class="text-xs text-zinc-400">
                                                    {{ $item->jam_rawat }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap"><span
                                                class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400">{{ $item->kd_jenis_prw }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-primary-dark-100">
                                            {{ $item->nm_perawatan ?? '-' }}</td>
                                        @if ($group['hasDr'])
                                            <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                                {{ $item->nm_dokter ?? ($item->kd_dokter ?? '-') }}
                                            </td>
                                        @endif
                                        @if ($group['hasPr'])
                                            <td
                                                class="px-4 py-3 text-sm font-mono text-zinc-600 dark:text-primary-dark-400">
                                                {{ $item->nip ?? '-' }}</td>
                                        @endif
                                        <td
                                            class="px-4 py-3 text-sm text-right whitespace-nowrap text-zinc-700 dark:text-primary-dark-300">
                                            @if ($item->biaya_rawat)
                                                Rp {{ number_format($item->biaya_rawat, 0, ',', '.') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
@else
    <div
        class="flex flex-col items-center py-12 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
        <flux:icon name="hand-raised" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data tindakan</p>
    </div>
@endif
