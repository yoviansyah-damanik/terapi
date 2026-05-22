<?php

use App\Helpers\SirsHelper;
use App\Services\Sirs\SirsClinicalService;
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('RL 3.14 - Pelayanan Khusus')] class extends Component {
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
            'data' => $service->getRL314($this->tahun, $this->bulan),
            'profil' => SirsHelper::getProfilRS(),
            'namaBulan' => SirsHelper::getNamaBulan($this->bulan),
        ];
    }
};
?>

<div>
    <x-sirs.report-header title="RL 3.14 - Pelayanan Khusus" subtitle="Data bulanan" :profil="$profil" :bulan="$namaBulan"
        :tahun="$tahun" />
    <x-sirs.period-filter :tahun="$tahun" :bulan="$bulan" />

    <div class="overflow-x-auto mt-4 rounded-lg border border-zinc-200 dark:border-primary-dark-700" data-area-print>
        <table id="report-table" class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700 text-xs">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700 w-8">
                        No</th>
                    <th
                        class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Jenis Pelayanan</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400">
                        Jumlah</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        1</td>
                    <td
                        class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        Hemodialisa</td>
                    <td class="px-4 py-3 text-center font-bold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($data['hemodialisa']) }}</td>
                </tr>
                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        2</td>
                    <td
                        class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        Operasi</td>
                    <td class="px-4 py-3 text-center font-bold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($data['operasi']) }}</td>
                </tr>

                <tr class="bg-primary-50 dark:bg-primary-900/20 font-bold">
                    <td colspan="2"
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        TOTAL
                    </td>
                    <td class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($data['hemodialisa'] + $data['operasi']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <x-sirs.export-buttons rl="3.14" />
</div>
