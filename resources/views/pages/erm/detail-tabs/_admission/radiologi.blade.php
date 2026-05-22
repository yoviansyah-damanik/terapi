<div class="space-y-6">
    @if ($permintaanRadiologis->count() > 0)
        <div
            class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
            <div
                class="px-4 py-3 border-b bg-zinc-50 dark:bg-primary-dark-900 border-zinc-200 dark:border-primary-dark-700">
                <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">Permintaan
                    Radiologi
                </h4>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                    <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                        <tr>
                            <th
                                class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                No Order</th>
                            <th
                                class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Tgl Permintaan</th>
                            <th
                                class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Dokter Perujuk</th>
                            <th
                                class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase md:table-cell text-zinc-500 dark:text-primary-dark-400">
                                Diagnosa Klinis</th>
                            <th
                                class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Status</th>
                        </tr>
                    </thead>
                    <tbody
                        class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                        @foreach ($permintaanRadiologis as $pr)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                <td class="px-4 py-3"><span
                                        class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400">{{ $pr->noorder }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-zinc-900 dark:text-primary-dark-100">
                                        {{ $pr->tgl_permintaan?->format('d/m/Y') }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                        {{ $pr->jam_permintaan }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                    {{ $pr->dokterPerujuk?->nm_dokter ?? '-' }}</td>
                                <td
                                    class="hidden px-4 py-3 text-sm md:table-cell text-zinc-700 dark:text-primary-dark-300">
                                    {{ $pr->diagnosa_klinis ?: '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <flux:badge :color="$pr->status === 'sudah' ? 'green' : 'yellow'" size="sm">
                                        {{ ucfirst($pr->status ?? '-') }}
                                    </flux:badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @php
        $hasPeriksaRadiologi = $permintaanRadiologis->contains(fn($pr) => $pr->periksa_rad->isNotEmpty());
        $totalBiayaRadiologi = $permintaanRadiologis->sum(fn($pr) => $pr->periksa_rad->sum('biaya'));
    @endphp

    @if ($hasPeriksaRadiologi)
        @foreach ($permintaanRadiologis as $pr)
            @if ($pr->periksa_rad->isNotEmpty())
                <div
                    class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60 mt-4">
                    <div
                        class="px-4 py-3 border-b bg-zinc-50 dark:bg-primary-dark-900 border-zinc-200 dark:border-primary-dark-700">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                                    Pemeriksaan No Order: {{ $pr->noorder }}
                                </h4>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                    {{ $pr->tgl_permintaan?->format('d/m/Y') }}
                                    {{ $pr->jam_permintaan }}
                                    @if ($pr->dokterPerujuk)
                                        &middot; Perujuk: {{ $pr->dokterPerujuk->nm_dokter }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge :color="$pr->status === 'sudah' ? 'green' : 'yellow'" size="sm">
                                    {{ ucfirst($pr->status ?? '-') }}</flux:badge>
                                <span class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Rp
                                    {{ number_format($pr->periksa_rad->sum('biaya'), 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="px-4 py-2 bg-zinc-50 dark:bg-primary-dark-800 border-b border-zinc-100 dark:border-primary-dark-700">
                        <div class="text-xs font-semibold text-zinc-600 dark:text-primary-dark-300 mb-2">
                            Daftar Tindakan Radiologi:</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            @foreach ($pr->periksa_rad as $rad)
                                <div
                                    class="flex items-start gap-2 bg-white dark:bg-primary-dark-900 p-2 rounded border border-zinc-200 dark:border-primary-dark-700">
                                    <flux:icon name="check-circle" class="w-4 h-4 text-primary-500 shrink-0 mt-0.5" />
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                            {{ $rad->jenisPerawatan?->nm_perawatan ?? $rad->kd_jenis_prw }}
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                            Dr
                                            PJ: {{ $rad->dokter?->nm_dokter ?? '-' }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="p-4 space-y-4">
                        @if ($pr->hasil_radiologi)
                            <div>
                                <span
                                    class="text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">Hasil
                                    Pemeriksaan</span>
                                <div
                                    class="p-3 mt-2 text-sm rounded bg-zinc-50 dark:bg-primary-dark-900 text-zinc-900 dark:text-primary-dark-100">
                                    {!! nl2br(e($pr->hasil_radiologi->hasil)) !!}
                                </div>
                            </div>
                        @endif

                        @if ($pr->gambar_radiologi->isNotEmpty())
                            <div>
                                <span
                                    class="text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">Gambar</span>
                                <div class="grid grid-cols-2 gap-3 mt-2 sm:grid-cols-3 md:grid-cols-4">
                                    @foreach ($pr->gambar_radiologi as $gambar)
                                        <a href="{{ config('app.simrs_webapps_url') . '/radiologi/' . $gambar->lokasi_gambar }}"
                                            target="_blank"
                                            class="block overflow-hidden rounded-xl border border-zinc-200/80 dark:border-primary-dark-700/60 hover:ring-2 hover:ring-primary-500">
                                            <img src="{{ config('app.simrs_webapps_url') . '/radiologi/' . $gambar->lokasi_gambar }}"
                                                alt="Radiologi" class="object-cover w-full h-32" loading="lazy">
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($pr->bhp_radiologi->isNotEmpty())
                            <div>
                                <span
                                    class="text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">Bahan
                                    Habis Pakai</span>
                                <div class="mt-2 overflow-x-auto">
                                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                                        <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                                            <tr>
                                                <th
                                                    class="px-3 py-2 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                                    Nama Barang</th>
                                                <th
                                                    class="px-3 py-2 text-xs font-medium tracking-wider text-right uppercase text-zinc-500 dark:text-primary-dark-400">
                                                    Jumlah</th>
                                                <th
                                                    class="hidden px-3 py-2 text-xs font-medium tracking-wider text-right uppercase sm:table-cell text-zinc-500 dark:text-primary-dark-400">
                                                    Harga</th>
                                                <th
                                                    class="px-3 py-2 text-xs font-medium tracking-wider text-right uppercase text-zinc-500 dark:text-primary-dark-400">
                                                    Total</th>
                                            </tr>
                                        </thead>
                                        <tbody
                                            class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                                            @foreach ($pr->bhp_radiologi as $bhp)
                                                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                                    <td
                                                        class="px-3 py-2 text-sm text-zinc-900 dark:text-primary-dark-100">
                                                        {{ $bhp->dataBarang?->nama_brng ?? $bhp->kode_brng }}
                                                    </td>
                                                    <td
                                                        class="px-3 py-2 text-sm text-right whitespace-nowrap text-zinc-900 dark:text-primary-dark-100">
                                                        {{ rtrim(rtrim(number_format($bhp->jumlah, 2, ',', '.'), '0'), ',') }}
                                                    </td>
                                                    <td
                                                        class="hidden px-3 py-2 text-sm text-right whitespace-nowrap sm:table-cell text-zinc-700 dark:text-primary-dark-300">
                                                        {{ number_format($bhp->harga, 0, ',', '.') }}
                                                    </td>
                                                    <td
                                                        class="px-3 py-2 text-sm font-medium text-right whitespace-nowrap text-zinc-900 dark:text-primary-dark-100">
                                                        {{ number_format($bhp->total, 0, ',', '.') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="bg-zinc-50 dark:bg-primary-dark-900">
                                            <tr>
                                                <td colspan="3"
                                                    class="px-3 py-2 text-xs font-semibold text-right text-zinc-900 dark:text-primary-dark-100">
                                                    Total BHP</td>
                                                <td
                                                    class="px-3 py-2 text-sm font-bold text-right whitespace-nowrap text-zinc-900 dark:text-primary-dark-100">
                                                    {{ number_format($pr->bhp_radiologi->sum('total'), 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @endforeach
        <div
            class="p-4 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60 mt-4">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">Total
                    Biaya
                    Radiologi</span>
                <span class="text-sm font-bold text-zinc-900 dark:text-primary-dark-100">Rp
                    {{ number_format($totalBiayaRadiologi, 0, ',', '.') }}</span>
            </div>
        </div>
    @else
        @if ($permintaanRadiologis->count() === 0)
            <div
                class="flex flex-col items-center py-12 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60 mt-4">
                <flux:icon name="camera" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data
                    radiologi
                </p>
            </div>
        @endif
    @endif
</div>
