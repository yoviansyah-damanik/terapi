<?php

use Livewire\Component;
use Livewire\Attributes\{Layout, Title, Url};
use App\Helpers\SirsHelper;
use App\Services\Sirs\SirsPharmacyService;

new #[Layout('layouts::app')] #[Title('RL 3.18 - Farmasi Resep')] class extends Component {
    #[Url]
    public int $tahun = 0;

    public function mount(): void
    {
        $this->tahun = $this->tahun ?: now()->year;
    }

    public function with(): array
    {
        $service = new SirsPharmacyService();
        return [
            'data' => $service->getRL318($this->tahun),
            'profil' => SirsHelper::getProfilRS(),
        ];
    }
};
?>

<div>
    <x-sirs.report-header title="RL 3.18 - Farmasi (Resep)" subtitle="Data tahunan" :profil="$profil" :tahun="$tahun" />
    <x-sirs.period-filter :tahun="$tahun" :showBulan="false" />

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
                        Jumlah Resep</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        1</td>
                    <td
                        class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        Rawat Jalan</td>
                    <td class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($data['ralan']) }}</td>
                </tr>
                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                    <td
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        2</td>
                    <td
                        class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        Rawat Inap</td>
                    <td class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($data['ranap']) }}</td>
                </tr>

                <tr class="bg-primary-50 dark:bg-primary-900/20 font-bold">
                    <td colspan="2"
                        class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                        TOTAL
                    </td>
                    <td class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100">
                        {{ number_format($data['total']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <x-sirs.export-buttons rl="3.18" />
</div>
