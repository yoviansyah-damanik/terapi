@if ($antreanTasks->isNotEmpty())
    <div class="p-5 space-y-4">
        {{-- Tabel Task ID --}}
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-zinc-100 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th
                            class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Task</th>
                        <th
                            class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Keterangan</th>
                        <th
                            class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                    @php
                        $taskLabels = [
                            1 => 'Ambil Antrean Admisi',
                            2 => 'Panggil Antrean Admisi',
                            3 => 'Selesai Admisi/Mulai Antrean Poli',
                            4 => 'Panggil Antrean Poli',
                            5 => 'Selesai Layanan Poli/Mulai Antrean Farmasi',
                            6 => 'Panggil Antrean Farmasi',
                            7 => 'Selesai Layanan Farmasi',
                        ];
                    @endphp
                    @foreach ($taskLabels as $tid => $tlabel)
                        @if (isset($antreanTasks[$tid]))
                            <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                <td class="px-4 py-2 font-medium text-purple-600 dark:text-purple-400">
                                    Task {{ $tid }}</td>
                                <td class="px-4 py-2 text-zinc-700 dark:text-primary-dark-300">
                                    {{ $tlabel }}</td>
                                <td class="px-4 py-2 font-mono text-zinc-900 dark:text-primary-dark-100">
                                    {{ $antreanTasks[$tid]->waktu }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Kalkulasi Waktu --}}
        @if (count($antreanMetrics) > 0)
            <div>
                <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                    Kalkulasi Waktu</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($antreanMetrics as $metric)
                        <div class="p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20">
                            <p class="text-xs text-purple-600 dark:text-purple-400">
                                {{ $metric['label'] }}</p>
                            <p class="mt-1 text-sm font-semibold text-purple-900 dark:text-purple-100">
                                {{ $metric['display'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@else
    <div
        class="flex flex-col items-center py-10 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
        <flux:icon name="queue-list" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data antrean
            Mobile
            JKN
            untuk kunjungan ini.</p>
    </div>
@endif
