<div
    class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
    @if ($prosedurs->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            No</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Kode ICD9</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Nama Prosedur</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Status</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Prioritas</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                    @foreach ($prosedurs as $index => $proc)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-3 text-sm text-zinc-500 dark:text-primary-dark-400">
                                {{ $index + 1 }}</td>
                            <td class="px-4 py-3 whitespace-nowrap"><span
                                    class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400">{{ $proc->kode }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $proc->icd9?->deskripsi_panjang ?? ($proc->icd9?->deskripsi_pendek ?? '-') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <flux:badge :color="$proc->status === 'Ralan' ? 'sky' : 'amber'" size="sm">
                                    {{ $proc->status === 'Ralan' ? 'Rawat Jalan' : ($proc->status === 'Ranap' ? 'Rawat Inap' : $proc->status) }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                {{ $proc->prioritas ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="flex flex-col items-center py-12">
            <flux:icon name="wrench-screwdriver" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data prosedur</p>
        </div>
    @endif
</div>
