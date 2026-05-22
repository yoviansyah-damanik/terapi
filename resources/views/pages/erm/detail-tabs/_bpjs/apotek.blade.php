@if ($apotekBpjs->isNotEmpty())
    <div class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
        @foreach ($apotekBpjs as $apotek)
            <div class="p-5">
                {{-- Header apotek --}}
                <div class="grid grid-cols-2 gap-x-6 gap-y-1 mb-4 sm:grid-cols-4 text-sm">
                    <div><span class="text-xs text-zinc-400">No. Apotek</span>
                        <p class="font-mono font-medium text-zinc-900 dark:text-primary-dark-100">
                            {{ $apotek->no_apotek }}</p>
                    </div>
                    <div><span class="text-xs text-zinc-400">No. Resep</span>
                        <p class="font-mono text-zinc-900 dark:text-primary-dark-100">
                            {{ $apotek->no_resep ?? '-' }}</p>
                    </div>
                    <div><span class="text-xs text-zinc-400">Tgl Resep</span>
                        <p class="text-zinc-900 dark:text-primary-dark-100">
                            {{ $apotek->tgl_resep ?? '-' }}
                        </p>
                    </div>
                    <div><span class="text-xs text-zinc-400">Tgl Pelayanan</span>
                        <p class="text-zinc-900 dark:text-primary-dark-100">
                            {{ $apotek->tgl_pelayanan ?? '-' }}</p>
                    </div>
                    <div><span class="text-xs text-zinc-400">DPJP</span>
                        <p class="text-zinc-900 dark:text-primary-dark-100">
                            {{ $apotek->nmdpjp ?? ($apotek->kodedpjp ?? '-') }}</p>
                    </div>
                    <div><span class="text-xs text-zinc-400">Poli</span>
                        <p class="text-zinc-900 dark:text-primary-dark-100">
                            {{ $apotek->nm_poli ?? '-' }}
                        </p>
                    </div>
                    <div><span class="text-xs text-zinc-400">Jenis Obat</span>
                        <p class="text-zinc-900 dark:text-primary-dark-100">
                            {{ $apotek->jenis_obat ?? '-' }}
                        </p>
                    </div>
                    <div><span class="text-xs text-zinc-400">Jenis Resep</span>
                        <p class="text-zinc-900 dark:text-primary-dark-100">
                            {{ $apotek->jenis_resep ?? '-' }}
                        </p>
                    </div>
                </div>
                {{-- Daftar obat --}}
                @if ($apotek->obats->isNotEmpty())
                    <div class="overflow-x-auto rounded border border-zinc-100 dark:border-primary-dark-700">
                        <table class="min-w-full text-sm divide-y divide-zinc-100 dark:divide-primary-dark-700">
                            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                                <tr>
                                    <th
                                        class="px-3 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Kode Obat</th>
                                    <th
                                        class="px-3 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Nama Obat</th>
                                    <th
                                        class="px-3 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Kode BPJS</th>
                                    <th
                                        class="px-3 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Nama Obat BPJS</th>
                                    <th
                                        class="px-3 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Signa</th>
                                    <th
                                        class="px-3 py-2 text-xs font-medium text-center uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Jml</th>
                                    <th
                                        class="px-3 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Sediaan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                                @foreach ($apotek->obats as $obat)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                        <td class="px-3 py-2 font-mono text-zinc-600 dark:text-primary-dark-400">
                                            {{ $obat->kd_obat ?? '-' }}</td>
                                        <td class="px-3 py-2 text-zinc-900 dark:text-primary-dark-100">
                                            {{ $obat->nm_obat ?? '-' }}</td>
                                        <td class="px-3 py-2 font-mono text-primary-600 dark:text-primary-400">
                                            {{ $obat->kd_obat_bpjs ?? '-' }}</td>
                                        <td class="px-3 py-2 text-zinc-900 dark:text-primary-dark-100">
                                            {{ $obat->nm_obat_bpjs ?? '-' }}</td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-primary-dark-400">
                                            {{ $obat->signa1 ?? '' }}{{ $obat->signa2 ? ' × ' . $obat->signa2 : '' }}
                                        </td>
                                        <td
                                            class="px-3 py-2 text-center font-medium text-zinc-900 dark:text-primary-dark-100">
                                            {{ $obat->jml_obat ?? '-' }}</td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-primary-dark-400">
                                            {{ $obat->sedia ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-xs text-zinc-400 italic">Tidak ada data obat pada resep ini.</p>
                @endif
            </div>
        @endforeach
    </div>
@else
    <div
        class="flex flex-col items-center py-10 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
        <flux:icon name="beaker" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data apotek online
            untuk
            kunjungan ini.</p>
    </div>
@endif
