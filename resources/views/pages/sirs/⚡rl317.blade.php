<?php

use Livewire\Component;
use Livewire\Attributes\{Layout, Title, Url};
use App\Helpers\SirsHelper;
use App\Services\Sirs\SirsPharmacyService;

new #[Layout('layouts::app')] #[Title('RL 3.17 - Farmasi Pengadaan')] class extends Component {
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
            'data' => $service->getRL317($this->tahun),
            'profil' => SirsHelper::getProfilRS(),
        ];
    }
};
?>

<div>
    <x-sirs.report-header title="RL 3.17 - Farmasi (Pengadaan Obat)"
        subtitle="50 besar obat berdasarkan jumlah pengadaan tahunan" :profil="$profil" :tahun="$tahun" />
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
                        Nama Obat</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Satuan</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Kategori</th>
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400">
                        Jumlah Pengadaan</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                @forelse ($data as $index => $row)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $index + 1 }}</td>
                        <td
                            class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['nama_brng'] }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['kode_sat'] }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['kode_kategori'] }}</td>
                        <td class="px-4 py-3 text-center font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ number_format($row['jumlah_pengadaan']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500 dark:text-primary-dark-400">Tidak
                            ada
                            data</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-sirs.export-buttons rl="3.17" />
</div>
