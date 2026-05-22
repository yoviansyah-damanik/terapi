<?php

use Livewire\Component;
use Livewire\Attributes\{Layout, Title, Url};
use App\Helpers\SirsHelper;
use App\Services\Sirs\SirsVisitService;

new #[Layout('layouts::app')] #[Title('RL 3.4 - Pengunjung')] class extends Component {
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
        $service = new SirsVisitService();
        return [
            'data' => $service->getRL34($this->tahun, $this->bulan),
            'profil' => SirsHelper::getProfilRS(),
            'namaBulan' => SirsHelper::getNamaBulan($this->bulan),
        ];
    }
};
?>

<div>
    <x-sirs.report-header title="RL 3.4 - Rekapitulasi Pengunjung Rumah Sakit" subtitle="Data bulanan" :profil="$profil"
        :bulan="$namaBulan" :tahun="$tahun" />
    <x-sirs.period-filter :tahun="$tahun" :bulan="$bulan" />

    <div class="overflow-x-auto mt-4 rounded-lg border border-zinc-200 dark:border-primary-dark-700" data-area-print>
        <table id="report-table" class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700 text-xs">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th
                        class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Jenis Pelayanan</th>
                    <th
                        class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Status</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Laki-laki</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Perempuan</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400">
                        Total</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                {{-- Rawat Jalan --}}
                @php
                    $rajalData = collect($data['rajal'])->groupBy('status');
                    $grandTotal = 0;
                @endphp
                @foreach (['baru' => 'Pasien Baru', 'lama' => 'Pasien Lama'] as $status => $label)
                    @php
                        $items = $rajalData->get($status, collect());
                        $l = $items->where('jk', 'L')->sum('jumlah');
                        $p = $items->where('jk', 'P')->sum('jumlah');
                        $grandTotal += $l + $p;
                    @endphp
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td
                            class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            Rawat Jalan</td>
                        <td
                            class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $label }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ number_format($l) }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ number_format($p) }}</td>
                        <td class="px-4 py-3 text-center font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ number_format($l + $p) }}</td>
                    </tr>
                @endforeach

                {{-- Rawat Inap --}}
                @php
                    $ranapL = collect($data['ranap'])->where('jk', 'L')->sum('jumlah');
                    $ranapP = collect($data['ranap'])->where('jk', 'P')->sum('jumlah');
                    $grandTotal += $ranapL + $ranapP;
                @endphp
                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                    <td
                        class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        Rawat Inap</td>
                    <td
                        class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        Pasien Masuk</td>
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ number_format($ranapL) }}</td>
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ number_format($ranapP) }}</td>
                    <td class="px-4 py-3 text-center font-bold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($ranapL + $ranapP) }}</td>
                </tr>

                {{-- IGD --}}
                @php
                    $igdL = $data['igd']['L']->jumlah ?? 0;
                    $igdP = $data['igd']['P']->jumlah ?? 0;
                    $grandTotal += $igdL + $igdP;
                @endphp
                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                    <td
                        class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        IGD</td>
                    <td
                        class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        Kunjungan</td>
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ number_format($igdL) }}</td>
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ number_format($igdP) }}</td>
                    <td class="px-4 py-3 text-center font-bold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($igdL + $igdP) }}</td>
                </tr>

                <tr class="bg-primary-50 dark:bg-primary-900/20 font-bold">
                    <td colspan="4"
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        TOTAL
                        PENGUNJUNG</td>
                    <td class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($grandTotal) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <x-sirs.export-buttons rl="3.4" />
</div>
