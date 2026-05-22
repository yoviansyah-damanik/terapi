<?php

use Livewire\Component;
use Livewire\Attributes\{Layout, Title, Url};
use App\Helpers\SirsHelper;
use App\Services\Sirs\SirsIndicatorService;

new #[Layout('layouts::app')] #[Title('RL 3.1 - Indikator Pelayanan')] class extends Component {
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
        $service = new SirsIndicatorService();
        return [
            'data' => $service->getRL31($this->tahun, $this->bulan),
            'profil' => SirsHelper::getProfilRS(),
            'namaBulan' => SirsHelper::getNamaBulan($this->bulan),
        ];
    }
};
?>

<div>
    <x-sirs.report-header title="RL 3.1 - Indikator Pelayanan Rawat Inap"
        subtitle="Sumber Data: RL 3.2 Rekapitulasi Kegiatan Pelayanan Rawat Inap" :profil="$profil" :bulan="$namaBulan"
        :tahun="$tahun" />
    <x-sirs.period-filter :tahun="$tahun" :bulan="$bulan" />

    {{-- Ringkasan --}}
    @if (isset($data[99]))
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mt-4" data-area-print>
            @foreach (['bor' => ['BOR', '%', '60-85%'], 'alos' => ['ALOS', ' hari', '6-9 hari'], 'bto' => ['BTO', '', '2-4x'], 'toi' => ['TOI', ' hari', '1-3 hari'], 'ndr' => ['NDR', '‰', '<25‰'], 'gdr' => ['GDR', '‰', '<45‰']] as $key => [$label, $suffix, $ideal])
                <div
                    class="p-3 bg-white dark:bg-primary-dark-800 rounded-lg border border-zinc-200 dark:border-primary-dark-700 text-center">
                    <div class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $label }} Rata-rata</div>
                    <div class="text-lg font-bold text-primary-600 dark:text-primary-400">
                        {{ number_format($data[99][$key], 2) }}{{ $suffix }}</div>
                    <div class="text-xs text-zinc-400">Ideal: {{ $ideal }}</div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Tabel --}}
    <div class="overflow-x-auto mt-4 rounded-lg border border-zinc-200 dark:border-primary-dark-700" data-area-print>
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700 text-xs">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th rowspan="2"
                        class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        No</th>
                    <th rowspan="2"
                        class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Jenis Pelayanan</th>
                    <th colspan="6"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400">
                        Indikator Pelayanan</th>
                </tr>
                <tr>
                    <th
                        class="px-4 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-t border-r border-zinc-200 dark:border-primary-dark-700">
                        BOR (%)</th>
                    <th
                        class="px-4 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-t border-r border-zinc-200 dark:border-primary-dark-700">
                        ALOS (hari)</th>
                    <th
                        class="px-4 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-t border-r border-zinc-200 dark:border-primary-dark-700">
                        BTO</th>
                    <th
                        class="px-4 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-t border-r border-zinc-200 dark:border-primary-dark-700">
                        TOI (hari)</th>
                    <th
                        class="px-4 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-t border-r border-zinc-200 dark:border-primary-dark-700">
                        NDR (‰)</th>
                    <th
                        class="px-4 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-t border-zinc-200 dark:border-primary-dark-700">
                        GDR (‰)</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                @foreach ($data as $key => $row)
                    <tr
                        class="{{ $key === 99 ? 'bg-primary-50 dark:bg-primary-900/20 font-semibold' : 'hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50' }}">
                        <td
                            class="px-4 py-3 text-center whitespace-nowrap text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $key === 99 ? '' : $key }}</td>
                        <td
                            class="px-4 py-3 whitespace-nowrap text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['nama'] }}</td>
                        <td
                            class="px-4 py-3 text-right whitespace-nowrap text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ number_format($row['bor'], 2) }}</td>
                        <td
                            class="px-4 py-3 text-right whitespace-nowrap text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ number_format($row['alos'], 2) }}</td>
                        <td
                            class="px-4 py-3 text-right whitespace-nowrap text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ number_format($row['bto'], 2) }}</td>
                        <td
                            class="px-4 py-3 text-right whitespace-nowrap text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ number_format($row['toi'], 2) }}</td>
                        <td
                            class="px-4 py-3 text-right whitespace-nowrap text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ number_format($row['ndr'], 2) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap text-zinc-900 dark:text-primary-dark-100">
                            {{ number_format($row['gdr'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-sirs.export-buttons rl="3.1" />
</div>
