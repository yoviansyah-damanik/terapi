<?php

use Livewire\Component;
use Livewire\Attributes\{Layout, Title, Url};
use App\Helpers\SirsHelper;
use App\Services\Sirs\SirsMorbidityService;

new #[Layout('layouts::app')] #[Title('RL 4.2 - 10 Besar Ranap')] class extends Component {
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
        $service = new SirsMorbidityService();
        $result = $service->getRL42($this->tahun, $this->bulan);
        return [
            'data' => $result['data'],
            'kematian' => $result['kematian'],
            'profil' => SirsHelper::getProfilRS(),
            'namaBulan' => SirsHelper::getNamaBulan($this->bulan),
        ];
    }
};
?>

<div>
    <x-sirs.report-header title="RL 4.2 - 10 Besar Penyakit Rawat Inap"
        subtitle="Berdasarkan diagnosa utama pasien keluar" :profil="$profil" :bulan="$namaBulan" :tahun="$tahun" />
    <x-sirs.period-filter :tahun="$tahun" :bulan="$bulan" />

    <div class="overflow-x-auto mt-4 rounded-lg border border-zinc-200 dark:border-primary-dark-700" data-area-print>
        <table id="report-table" class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700 text-xs">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th rowspan="2"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700 w-8">
                        No</th>
                    <th rowspan="2"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Kode ICD</th>
                    <th rowspan="2"
                        class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Nama Penyakit</th>
                    <th colspan="3"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        Pasien Keluar</th>
                    <th colspan="3"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-zinc-200 dark:border-primary-dark-700">
                        Kematian</th>
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
                        Total</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        L</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        P</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400">
                        Total</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                @php
                    $no = 1;
                    $totL = 0;
                    $totP = 0;
                    $totAll = 0;
                    $totMatiL = 0;
                    $totMatiP = 0;
                    $totMati = 0;
                @endphp
                @forelse ($data as $kode => $row)
                    @php
                        $matiL = $kematian[$kode]['l'] ?? 0;
                        $matiP = $kematian[$kode]['p'] ?? 0;
                        $matiT = $kematian[$kode]['total'] ?? 0;
                        $totL += $row['l'];
                        $totP += $row['p'];
                        $totAll += $row['total'];
                        $totMatiL += $matiL;
                        $totMatiP += $matiP;
                        $totMati += $matiT;
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $no++ }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700 font-mono">
                            {{ $kode }}</td>
                        <td
                            class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['nama'] }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['l'] }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['p'] }}</td>
                        <td
                            class="px-4 py-3 text-center font-bold text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['total'] }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $matiL ?: '-' }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $matiP ?: '-' }}</td>
                        <td
                            class="px-4 py-3 text-center font-bold {{ $matiT > 0 ? 'text-red-600' : 'text-zinc-900 dark:text-primary-dark-100' }}">
                            {{ $matiT ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-zinc-500 dark:text-primary-dark-400">Tidak
                            ada
                            data untuk periode ini</td>
                    </tr>
                @endforelse

                @if (count($data) > 0)
                    <tr class="bg-primary-50 dark:bg-primary-900/20 font-bold">
                        <td colspan="3"
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            TOTAL</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $totL }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $totP }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $totAll }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $totMatiL }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $totMatiP }}</td>
                        <td class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100">
                            {{ $totMati }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <x-sirs.export-buttons rl="4.2" />
</div>
