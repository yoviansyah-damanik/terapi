<?php

use Livewire\Component;
use Livewire\Attributes\{Layout, Title, Url};
use App\Helpers\SirsHelper;
use App\Services\Sirs\SirsClinicalService;

new #[Layout('layouts::app')] #[Title('RL 3.7 - Neonatal')] class extends Component {
    #[Url]
    public int $tahun = 0;
    #[Url]
    public int $bulan = 0;

    public function mount(): void
    {
        $this->tahun = $this->tahun ?: now()->year;
        $this->bulan = $this->bulan ?: now()->month;
    }

    public function with(): array
    {
        $service = new SirsClinicalService();
        return [
            'data' => $service->getRL37($this->tahun, $this->bulan),
            'profil' => SirsHelper::getProfilRS(),
            'namaBulan' => SirsHelper::getNamaBulan($this->bulan),
        ];
    }
};
?>

<div>
    <x-sirs.report-header title="RL 3.7 - Pelayanan Neonatal / Bayi / Balita" subtitle="Data rawat inap bulanan"
        :profil="$profil" :bulan="$namaBulan" :tahun="$tahun" />
    <x-sirs.period-filter :tahun="$tahun" :bulan="$bulan" />

    <div class="overflow-x-auto mt-4 rounded-lg border border-zinc-200 dark:border-primary-dark-700" data-area-print>
        <table id="report-table" class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700 text-xs">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th rowspan="2"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700 w-8">
                        No</th>
                    <th rowspan="2"
                        class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Kategori</th>
                    <th colspan="2"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        Keluar Hidup</th>
                    <th colspan="2"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        Keluar Mati</th>
                    <th rowspan="2"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400">
                        Total</th>
                </tr>
                <tr>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        L</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        P</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        L</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        P</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                @php
                    $no = 1;
                    $totHL = 0;
                    $totHP = 0;
                    $totML = 0;
                    $totMP = 0;
                    $totAll = 0;
                    $labels = [
                        'neonatal' => 'Neonatal (0-28 hari)',
                        'bayi' => 'Bayi (29 hari - 1 tahun)',
                        'balita' => 'Balita (1-5 tahun)',
                    ];
                @endphp
                @foreach ($labels as $key => $label)
                    @php
                        $row = $data[$key];
                        $totHL += $row['hidup_l'];
                        $totHP += $row['hidup_p'];
                        $totML += $row['mati_l'];
                        $totMP += $row['mati_p'];
                        $totAll += $row['total'];
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $no++ }}</td>
                        <td
                            class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $label }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['hidup_l'] ?: '-' }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['hidup_p'] ?: '-' }}</td>
                        <td
                            class="px-4 py-3 text-center text-red-600 dark:text-red-400 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['mati_l'] ?: '-' }}</td>
                        <td
                            class="px-4 py-3 text-center text-red-600 dark:text-red-400 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['mati_p'] ?: '-' }}</td>
                        <td class="px-4 py-3 text-center font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ $row['total'] }}</td>
                    </tr>
                @endforeach

                <tr class="bg-primary-50 dark:bg-primary-900/20 font-bold">
                    <td colspan="2"
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        TOTAL
                    </td>
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ $totHL }}
                    </td>
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ $totHP }}
                    </td>
                    <td
                        class="px-4 py-3 text-center text-red-600 dark:text-red-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ $totML }}</td>
                    <td
                        class="px-4 py-3 text-center text-red-600 dark:text-red-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ $totMP }}</td>
                    <td class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100">{{ $totAll }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <x-sirs.export-buttons rl="3.7" />
</div>
