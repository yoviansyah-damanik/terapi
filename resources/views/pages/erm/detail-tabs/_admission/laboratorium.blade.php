{{-- Sub-tab Nav --}}
<div class="flex gap-0.5 p-1 mb-4 overflow-x-auto bg-zinc-100 rounded-xl dark:bg-primary-dark-900/50">
    <button @click="labTab = 'pk'"
        :class="labTab === 'pk'
            ?
            'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-800 dark:text-primary-dark-100' :
            'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400 dark:hover:text-primary-dark-200'"
        class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-all whitespace-nowrap">
        Patologi Klinik
        <flux:badge color="zinc" size="sm">{{ $periksaLabsPk->count() }}</flux:badge>
    </button>
    <button @click="labTab = 'pa'"
        :class="labTab === 'pa'
            ?
            'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-800 dark:text-primary-dark-100' :
            'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400 dark:hover:text-primary-dark-200'"
        class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-all whitespace-nowrap">
        Patologi Anatomi
        <flux:badge color="zinc" size="sm">{{ $detailPeriksaLabsPa->count() }}</flux:badge>
    </button>
    <button @click="labTab = 'mb'"
        :class="labTab === 'mb'
            ?
            'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-800 dark:text-primary-dark-100' :
            'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400 dark:hover:text-primary-dark-200'"
        class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-all whitespace-nowrap">
        Mikrobiologi
        <flux:badge color="zinc" size="sm">{{ $periksaLabsMb->count() }}</flux:badge>
    </button>
</div>

{{-- Lab PK --}}
<div x-show="labTab === 'pk'" x-cloak>
    <div class="space-y-6">
        @if ($permintaanLabsPk->count() > 0)
            <div
                class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                <div
                    class="px-4 py-3 border-b bg-zinc-50 dark:bg-primary-dark-900 border-zinc-200 dark:border-primary-dark-700">
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                        Permintaan Lab
                        PK</h4>
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
                            @foreach ($permintaanLabsPk as $pl)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                    <td class="px-4 py-3"><span
                                            class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400">{{ $pl->noorder }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="text-sm text-zinc-900 dark:text-primary-dark-100">
                                            {{ $pl->tgl_permintaan?->format('d/m/Y') }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                            {{ $pl->jam_permintaan }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                        {{ $pl->dokterPerujuk?->nm_dokter ?? '-' }}</td>
                                    <td
                                        class="hidden px-4 py-3 text-sm md:table-cell text-zinc-700 dark:text-primary-dark-300">
                                        {{ $pl->diagnosa_klinis ?: '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <flux:badge :color="$pl->status === 'sudah' ? 'green' : 'yellow'"
                                            size="sm">{{ ucfirst($pl->status ?? '-') }}
                                        </flux:badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($periksaLabsPk->count() > 0)
            @foreach ($periksaLabsPk as $lab)
                @php
                    $labTemplates = $templatesPk[$lab->kd_jenis_prw] ?? collect();
                    $key = $lab->kd_jenis_prw . '|' . $lab->tgl_periksa?->format('Y-m-d') . '|' . $lab->jam;
                    $details = $detailLabsPk[$key] ?? collect();
                @endphp
                <div
                    class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                    <div
                        class="px-4 py-3 border-b bg-zinc-50 dark:bg-primary-dark-900 border-zinc-200 dark:border-primary-dark-700">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                                    {{ $lab->jenisPerawatan?->nm_perawatan ?? $lab->kd_jenis_prw }}
                                </h4>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                    {{ $lab->tgl_periksa?->format('d/m/Y') }} {{ $lab->jam }}
                                    &middot; Dokter PJ: {{ $lab->dokter?->nm_dokter ?? '-' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge :color="$lab->status === 'Sudah' ? 'green' : 'yellow'" size="sm">
                                    {{ $lab->status }}</flux:badge>
                                <span class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Rp
                                    {{ number_format($lab->biaya, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                    @if ($labTemplates->count() > 0 || $details->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                                    <tr>
                                        <th
                                            class="px-4 py-2 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                            No</th>
                                        <th
                                            class="px-4 py-2 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                            Pemeriksaan</th>
                                        <th
                                            class="px-4 py-2 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                            Hasil</th>
                                        <th
                                            class="hidden px-4 py-2 text-xs font-medium tracking-wider text-left uppercase sm:table-cell text-zinc-500 dark:text-primary-dark-400">
                                            Satuan</th>
                                        <th
                                            class="hidden px-4 py-2 text-xs font-medium tracking-wider text-left uppercase md:table-cell text-zinc-500 dark:text-primary-dark-400">
                                            Nilai Rujukan</th>
                                        <th
                                            class="hidden px-4 py-2 text-xs font-medium tracking-wider text-left uppercase lg:table-cell text-zinc-500 dark:text-primary-dark-400">
                                            Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                                    @if ($labTemplates->count() > 0)
                                        @foreach ($labTemplates as $tmplIndex => $tmpl)
                                            @php
                                                $detail = $details[$tmpl->id_template] ?? null;
                                                $nilaiRujukan = $this->getNilaiRujukan($tmpl, $pasienJk, $pasienUmur);
                                            @endphp
                                            <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                                <td class="px-4 py-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                                                    {{ $tmplIndex + 1 }}</td>
                                                <td class="px-4 py-2 text-sm text-zinc-900 dark:text-primary-dark-100">
                                                    {{ $tmpl->Pemeriksaan }}</td>
                                                <td
                                                    class="px-4 py-2 text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                                    {{ $detail?->nilai ?? '-' }}</td>
                                                <td
                                                    class="hidden px-4 py-2 text-sm sm:table-cell text-zinc-700 dark:text-primary-dark-300">
                                                    {{ $tmpl->satuan ?: '-' }}</td>
                                                <td
                                                    class="hidden px-4 py-2 text-sm md:table-cell text-zinc-700 dark:text-primary-dark-300">
                                                    {{ $nilaiRujukan ?: '-' }}</td>
                                                <td
                                                    class="hidden px-4 py-2 text-sm lg:table-cell text-zinc-700 dark:text-primary-dark-300">
                                                    {{ $detail?->keterangan ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        @foreach ($details as $detail)
                                            <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                                <td class="px-4 py-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                                                    {{ $loop->iteration }}</td>
                                                <td class="px-4 py-2 text-sm text-zinc-900 dark:text-primary-dark-100">
                                                    {{ $detail->id_template }}</td>
                                                <td
                                                    class="px-4 py-2 text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                                    {{ $detail->nilai ?? '-' }}</td>
                                                <td
                                                    class="hidden px-4 py-2 text-sm sm:table-cell text-zinc-700 dark:text-primary-dark-300">
                                                    -</td>
                                                <td
                                                    class="hidden px-4 py-2 text-sm md:table-cell text-zinc-700 dark:text-primary-dark-300">
                                                    {{ $detail->nilai_rujukan ?? '-' }}</td>
                                                <td
                                                    class="hidden px-4 py-2 text-sm lg:table-cell text-zinc-700 dark:text-primary-dark-300">
                                                    {{ $detail->keterangan ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="px-4 py-6 text-center">
                            <p class="text-sm text-zinc-500 dark:text-primary-dark-400">Belum ada hasil
                                pemeriksaan</p>
                        </div>
                    @endif
                </div>
            @endforeach
            <div
                class="p-4 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">Total
                        Biaya
                        Lab PK</span>
                    <span class="text-sm font-bold text-zinc-900 dark:text-primary-dark-100">Rp
                        {{ number_format($periksaLabsPk->sum('biaya'), 0, ',', '.') }}</span>
                </div>
            </div>
        @else
            @if ($permintaanLabsPk->count() === 0)
                <div
                    class="flex flex-col items-center py-12 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                    <flux:icon name="chart-bar" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data lab
                        Patologi Klinik</p>
                </div>
            @endif
        @endif

        @if ($saranKesanLabs->count() > 0)
            <div
                class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                <div
                    class="px-4 py-3 border-b bg-zinc-50 dark:bg-primary-dark-900 border-zinc-200 dark:border-primary-dark-700">
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">Saran &
                        Kesan
                        Laboratorium</h4>
                </div>
                <div class="divide-y divide-zinc-200 dark:divide-primary-dark-700">
                    @foreach ($saranKesanLabs as $sk)
                        <div class="p-4">
                            <div class="flex items-center gap-2 mb-2 text-xs text-zinc-500 dark:text-primary-dark-400">
                                <flux:icon name="calendar" class="w-3 h-3" />
                                {{ $sk->tgl_periksa?->format('d/m/Y') }} {{ $sk->jam }}
                            </div>
                            @if ($sk->kesan)
                                <div class="mb-2"><span
                                        class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400">Kesan:</span>
                                    <p class="mt-1 text-sm text-zinc-900 dark:text-primary-dark-100">
                                        {{ $sk->kesan }}</p>
                                </div>
                            @endif
                            @if ($sk->saran)
                                <div><span
                                        class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400">Saran:</span>
                                    <p class="mt-1 text-sm text-zinc-900 dark:text-primary-dark-100">
                                        {{ $sk->saran }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Lab PA --}}
<div x-show="labTab === 'pa'" x-cloak>
    <div class="space-y-6">
        @if ($permintaanLabsPa->count() > 0)
            <div
                class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                <div
                    class="px-4 py-3 border-b bg-zinc-50 dark:bg-primary-dark-900 border-zinc-200 dark:border-primary-dark-700">
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                        Permintaan Lab
                        PA</h4>
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
                                    class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase lg:table-cell text-zinc-500 dark:text-primary-dark-400">
                                    Lokasi Jaringan</th>
                                <th
                                    class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                    Status</th>
                            </tr>
                        </thead>
                        <tbody
                            class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                            @foreach ($permintaanLabsPa as $pl)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                    <td class="px-4 py-3"><span
                                            class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400">{{ $pl->noorder }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="text-sm text-zinc-900 dark:text-primary-dark-100">
                                            {{ $pl->tgl_permintaan?->format('d/m/Y') }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                            {{ $pl->jam_permintaan }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                        {{ $pl->dokterPerujuk?->nm_dokter ?? '-' }}</td>
                                    <td
                                        class="hidden px-4 py-3 text-sm md:table-cell text-zinc-700 dark:text-primary-dark-300">
                                        {{ $pl->diagnosa_klinis ?: '-' }}</td>
                                    <td
                                        class="hidden px-4 py-3 text-sm lg:table-cell text-zinc-700 dark:text-primary-dark-300">
                                        {{ $pl->lokasi_jaringan ?: '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <flux:badge :color="$pl->status === 'sudah' ? 'green' : 'yellow'"
                                            size="sm">{{ ucfirst($pl->status ?? '-') }}
                                        </flux:badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($detailPeriksaLabsPa->count() > 0)
            @foreach ($detailPeriksaLabsPa as $pa)
                <div
                    class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                    <div
                        class="px-4 py-3 border-b bg-zinc-50 dark:bg-primary-dark-900 border-zinc-200 dark:border-primary-dark-700">
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                            {{ $pa->jenisPerawatan?->nm_perawatan ?? $pa->kd_jenis_prw }}</h4>
                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                            {{ $pa->tgl_periksa?->format('d/m/Y') }} {{ $pa->jam }}</p>
                    </div>
                    <div class="p-4 space-y-4">
                        @foreach (['diagnosa_klinik' => 'Diagnosa Klinik', 'makroskopik' => 'Makroskopik', 'mikroskopik' => 'Mikroskopik', 'kesimpulan' => 'Kesimpulan', 'kesan' => 'Kesan'] as $field => $label)
                            @if ($pa->$field)
                                <div>
                                    <span
                                        class="text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">{{ $label }}</span>
                                    <p class="mt-1 text-sm text-zinc-900 dark:text-primary-dark-100">
                                        {{ $pa->$field }}</p>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        @else
            @if ($permintaanLabsPa->count() === 0)
                <div
                    class="flex flex-col items-center py-12 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                    <flux:icon name="chart-bar" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data lab
                        Patologi Anatomi</p>
                </div>
            @endif
        @endif
    </div>
</div>

{{-- Lab MB --}}
<div x-show="labTab === 'mb'" x-cloak>
    <div class="space-y-6">
        @if ($permintaanLabsMb->count() > 0)
            <div
                class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                <div
                    class="px-4 py-3 border-b bg-zinc-50 dark:bg-primary-dark-900 border-zinc-200 dark:border-primary-dark-700">
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                        Permintaan Lab
                        MB</h4>
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
                            @foreach ($permintaanLabsMb as $pl)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                    <td class="px-4 py-3"><span
                                            class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400">{{ $pl->noorder }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="text-sm text-zinc-900 dark:text-primary-dark-100">
                                            {{ $pl->tgl_permintaan?->format('d/m/Y') }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                            {{ $pl->jam_permintaan }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                        {{ $pl->dokterPerujuk?->nm_dokter ?? '-' }}</td>
                                    <td
                                        class="hidden px-4 py-3 text-sm md:table-cell text-zinc-700 dark:text-primary-dark-300">
                                        {{ $pl->diagnosa_klinis ?: '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <flux:badge :color="$pl->status === 'sudah' ? 'green' : 'yellow'"
                                            size="sm">{{ ucfirst($pl->status ?? '-') }}
                                        </flux:badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($periksaLabsMb->count() > 0)
            @foreach ($periksaLabsMb as $lab)
                @php
                    $labTemplates = $templatesMb[$lab->kd_jenis_prw] ?? collect();
                    $key = $lab->kd_jenis_prw . '|' . $lab->tgl_periksa?->format('Y-m-d') . '|' . $lab->jam;
                    $details = $detailLabsMb[$key] ?? collect();
                @endphp
                <div
                    class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                    <div
                        class="px-4 py-3 border-b bg-zinc-50 dark:bg-primary-dark-900 border-zinc-200 dark:border-primary-dark-700">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h4 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                                    {{ $lab->jenisPerawatan?->nm_perawatan ?? $lab->kd_jenis_prw }}
                                </h4>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                    {{ $lab->tgl_periksa?->format('d/m/Y') }} {{ $lab->jam }}
                                    &middot; Dokter PJ: {{ $lab->dokter?->nm_dokter ?? '-' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge :color="$lab->status === 'Sudah' ? 'green' : 'yellow'" size="sm">
                                    {{ $lab->status }}</flux:badge>
                                <span class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Rp
                                    {{ number_format($lab->biaya, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                    @if ($labTemplates->count() > 0 || $details->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                                    <tr>
                                        <th
                                            class="px-4 py-2 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                            No</th>
                                        <th
                                            class="px-4 py-2 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                            Pemeriksaan</th>
                                        <th
                                            class="px-4 py-2 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                            Hasil</th>
                                        <th
                                            class="hidden px-4 py-2 text-xs font-medium tracking-wider text-left uppercase sm:table-cell text-zinc-500 dark:text-primary-dark-400">
                                            Satuan</th>
                                        <th
                                            class="hidden px-4 py-2 text-xs font-medium tracking-wider text-left uppercase md:table-cell text-zinc-500 dark:text-primary-dark-400">
                                            Nilai Rujukan</th>
                                        <th
                                            class="hidden px-4 py-2 text-xs font-medium tracking-wider text-left uppercase lg:table-cell text-zinc-500 dark:text-primary-dark-400">
                                            Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                                    @if ($labTemplates->count() > 0)
                                        @foreach ($labTemplates as $tmplIndex => $tmpl)
                                            @php
                                                $detail = $details[$tmpl->id_template] ?? null;
                                                $nilaiRujukan = $this->getNilaiRujukan($tmpl, $pasienJk, $pasienUmur);
                                            @endphp
                                            <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                                <td class="px-4 py-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                                                    {{ $tmplIndex + 1 }}</td>
                                                <td class="px-4 py-2 text-sm text-zinc-900 dark:text-primary-dark-100">
                                                    {{ $tmpl->Pemeriksaan }}</td>
                                                <td
                                                    class="px-4 py-2 text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                                    {{ $detail?->nilai ?? '-' }}</td>
                                                <td
                                                    class="hidden px-4 py-2 text-sm sm:table-cell text-zinc-700 dark:text-primary-dark-300">
                                                    {{ $tmpl->satuan ?: '-' }}</td>
                                                <td
                                                    class="hidden px-4 py-2 text-sm md:table-cell text-zinc-700 dark:text-primary-dark-300">
                                                    {{ $nilaiRujukan ?: '-' }}</td>
                                                <td
                                                    class="hidden px-4 py-2 text-sm lg:table-cell text-zinc-700 dark:text-primary-dark-300">
                                                    {{ $detail?->keterangan ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        @foreach ($details as $detail)
                                            <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                                <td class="px-4 py-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                                                    {{ $loop->iteration }}</td>
                                                <td class="px-4 py-2 text-sm text-zinc-900 dark:text-primary-dark-100">
                                                    {{ $detail->id_template }}</td>
                                                <td
                                                    class="px-4 py-2 text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                                    {{ $detail->nilai ?? '-' }}</td>
                                                <td
                                                    class="hidden px-4 py-2 text-sm sm:table-cell text-zinc-700 dark:text-primary-dark-300">
                                                    -</td>
                                                <td
                                                    class="hidden px-4 py-2 text-sm md:table-cell text-zinc-700 dark:text-primary-dark-300">
                                                    {{ $detail->nilai_rujukan ?? '-' }}</td>
                                                <td
                                                    class="hidden px-4 py-2 text-sm lg:table-cell text-zinc-700 dark:text-primary-dark-300">
                                                    {{ $detail->keterangan ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="px-4 py-6 text-center">
                            <p class="text-sm text-zinc-500 dark:text-primary-dark-400">Belum ada hasil
                                pemeriksaan</p>
                        </div>
                    @endif
                </div>
            @endforeach
            <div
                class="p-4 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">Total
                        Biaya
                        Lab MB</span>
                    <span class="text-sm font-bold text-zinc-900 dark:text-primary-dark-100">Rp
                        {{ number_format($periksaLabsMb->sum('biaya'), 0, ',', '.') }}</span>
                </div>
            </div>
        @else
            @if ($permintaanLabsMb->count() === 0)
                <div
                    class="flex flex-col items-center py-12 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                    <flux:icon name="chart-bar" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data lab
                        Mikrobiologi</p>
                </div>
            @endif
        @endif
    </div>
</div>
