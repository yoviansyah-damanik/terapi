<div
    class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
    @if ($alergiPasiens->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            No</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Tanggal</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Nama Alergi</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase sm:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Tipe</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Reaksi</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase sm:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Kategori Reaksi</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Keparahan</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase md:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Kritisitas</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase lg:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Catatan</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase lg:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Petugas</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                    @foreach ($alergiPasiens as $index => $alergi)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-3 text-sm text-zinc-500 dark:text-primary-dark-400">
                                {{ $index + 1 }}</td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap text-zinc-900 dark:text-primary-dark-100">
                                {{ $alergi->tanggal ? \Carbon\Carbon::parse($alergi->tanggal)->format('d/m/Y') : '-' }}
                                @if ($alergi->jam)
                                    <div class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                        {{ $alergi->jam }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                {{ $alergi->alergi?->nama_alergi ?? '-' }}</td>
                            <td
                                class="hidden px-4 py-3 text-sm whitespace-nowrap sm:table-cell text-zinc-700 dark:text-primary-dark-300">
                                {{ $alergi->alergi?->tipe ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                {{ $alergi->reaksi?->nama_reaksi ?? '-' }}</td>
                            <td
                                class="hidden px-4 py-3 text-sm whitespace-nowrap sm:table-cell text-zinc-700 dark:text-primary-dark-300">
                                {{ $alergi->reaksi?->kategori ?? '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $keparahanNama = $alergi->tingkatKeparahan?->keparahan ?? null;
                                    $sevColor = match (strtolower($keparahanNama ?? '')) {
                                        'berat', 'severe' => 'red',
                                        'sedang', 'moderate' => 'amber',
                                        'ringan', 'mild' => 'green',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge :color="$sevColor" size="sm">
                                    {{ $keparahanNama ?? '-' }}
                                </flux:badge>
                            </td>
                            <td
                                class="hidden px-4 py-3 text-sm whitespace-nowrap md:table-cell text-zinc-700 dark:text-primary-dark-300">
                                {{ $alergi->kritisitas?->kritisitas ?? '-' }}</td>
                            <td class="hidden px-4 py-3 text-sm lg:table-cell text-zinc-700 dark:text-primary-dark-300">
                                {{ $alergi->catatan ?: '-' }}</td>
                            <td
                                class="hidden px-4 py-3 text-sm whitespace-nowrap lg:table-cell text-zinc-700 dark:text-primary-dark-300">
                                {{ $alergi->pegawai?->nama ?? ($alergi->nip ?? '-') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="flex flex-col items-center py-12">
            <flux:icon name="exclamation-triangle" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data alergi</p>
        </div>
    @endif
</div>
