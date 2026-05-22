<?php

use App\Helpers\SirsHelper;
use App\Services\Sirs\SirsOperativeService;
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('RL 3.13 - Rehab Medik')] class extends Component {
    #[Url]
    public int $tahun = 0;

    public function mount(): void
    {
        $this->tahun = $this->tahun ?: now()->year;
    }

    public function with(): array
    {
        $service = new SirsOperativeService();
        return [
            'data' => $service->getRL313($this->tahun),
            'profil' => SirsHelper::getProfilRS(),
        ];
    }
};
?>

<div>
    <x-sirs.report-header title="RL 3.13 - Rehabilitasi Medik" subtitle="Data tahunan" :profil="$profil"
        :tahun="$tahun" />
    <x-sirs.period-filter :tahun="$tahun" :showBulan="false" />

    <div class="overflow-x-auto mt-4 rounded-lg border border-zinc-200 dark:border-primary-dark-700" data-area-print>
        <table id="report-table" class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700 text-xs">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th
                        class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Uraian</th>
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
                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                    <td
                        class="px-4 py-3 font-medium text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        Kunjungan
                        Rehabilitasi Medik</td>
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ number_format($data['l']) }}</td>
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        {{ number_format($data['p']) }}</td>
                    <td class="px-4 py-3 text-center font-bold text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($data['total']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <x-sirs.export-buttons rl="3.13" />
</div>
