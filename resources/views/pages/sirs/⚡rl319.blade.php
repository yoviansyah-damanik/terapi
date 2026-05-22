<?php

use Livewire\Component;
use Livewire\Attributes\{Layout, Title, Url};
use App\Helpers\SirsHelper;
use App\Services\Sirs\SirsVisitService;

new #[Layout('layouts::app')] #[Title('RL 3.19 - Cara Bayar')] class extends Component {
    #[Url]
    public int $tahun = 0;

    public function mount(): void
    {
        $this->tahun = $this->tahun ?: now()->year;
    }

    public function with(): array
    {
        $service = new SirsVisitService();
        return [
            'data' => $service->getRL319($this->tahun),
            'profil' => SirsHelper::getProfilRS(),
        ];
    }
};
?>

<div>
    <x-sirs.report-header title="RL 3.19 - Cara Bayar Pasien" subtitle="Rekapitulasi tahunan berdasarkan penjamin biaya"
        :profil="$profil" bulan="Tahun" :tahun="$tahun" />
    <x-sirs.period-filter :tahun="$tahun" :bulan="1" :showBulan="false" />

    <div class="overflow-x-auto mt-4 rounded-lg border border-zinc-200 dark:border-primary-dark-700" data-area-print>
        <table id="report-table" class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700 text-xs">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th rowspan="2"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700 w-8">
                        No</th>
                    <th rowspan="2"
                        class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Cara Bayar</th>
                    <th colspan="2"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        Rawat Inap</th>
                    <th colspan="4"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-zinc-200 dark:border-primary-dark-700">
                        Rawat Jalan</th>
                </tr>
                <tr>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Ps. Keluar</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Lama Rawat</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Lab</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Radiologi</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Lainnya</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400">
                        Total Rajal</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                @php
                    $no = 1;
                    $labels = [
                        'JKN' => 'Jaminan Kesehatan Nasional (JKN)',
                        'JAMKESDA' => 'Jamkesda',
                        'PEMDA_LAIN' => 'Pemda Lainnya',
                        'SWASTA' => 'Asuransi Swasta',
                        'KERINGANAN' => 'Keringanan',
                        'GRATIS' => 'Gratis',
                        'SENDIRI' => 'Membayar Sendiri',
                    ];
                    $totals = [
                        'ranap_keluar' => 0,
                        'ranap_lama' => 0,
                        'ralan_lab' => 0,
                        'ralan_rad' => 0,
                        'ralan_lain' => 0,
                        'ralan_total' => 0,
                    ];
                @endphp
                @foreach ($data as $kat => $row)
                    @php
                        foreach ($totals as $k => &$v) {
                            $v += $row[$k];
                        }
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $no++ }}</td>
                        <td
                            class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $labels[$kat] ?? $kat }}
                        </td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ number_format($row['ranap_keluar']) }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ number_format($row['ranap_lama']) }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ number_format($row['ralan_lab']) }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ number_format($row['ralan_rad']) }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ number_format($row['ralan_lain']) }}</td>
                        <td class="px-4 py-3 text-center font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ number_format($row['ralan_total']) }}</td>
                    </tr>
                @endforeach

                <tr class="bg-primary-50 dark:bg-primary-900/20 font-bold">
                    <td colspan="2"
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        TOTAL
                    </td>
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ number_format($totals['ranap_keluar']) }}</td>
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ number_format($totals['ranap_lama']) }}</td>
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ number_format($totals['ralan_lab']) }}</td>
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ number_format($totals['ralan_rad']) }}</td>
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ number_format($totals['ralan_lain']) }}</td>
                    <td class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($totals['ralan_total']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <x-sirs.export-buttons rl="3.19" />
</div>
