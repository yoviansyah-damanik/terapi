<?php

use Livewire\Component;
use Livewire\Attributes\{Layout, Title, Url};
use App\Helpers\SirsHelper;
use App\Services\Sirs\SirsVisitService;

new #[Layout('layouts::app')] #[Title('RL 3.3 - Rawat Darurat')] class extends Component {
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
            'data' => $service->getRL33($this->tahun, $this->bulan),
            'profil' => SirsHelper::getProfilRS(),
            'namaBulan' => SirsHelper::getNamaBulan($this->bulan),
        ];
    }
};
?>

<div>
    <x-sirs.report-header title="RL 3.3 - Pelayanan Rawat Darurat (IGD)" subtitle="Rekapitulasi bulanan" :profil="$profil"
        :bulan="$namaBulan" :tahun="$tahun" />
    <x-sirs.period-filter :tahun="$tahun" :bulan="$bulan" />

    <div class="overflow-x-auto mt-4 rounded-lg border border-zinc-200 dark:border-primary-dark-700" data-area-print>
        <table id="report-table" class="min-w-full text-xs divide-y divide-zinc-200 dark:divide-primary-dark-700">
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
                @php
                    $kunjL = $data['kunjungan']['L']->jumlah ?? 0;
                    $kunjP = $data['kunjungan']['P']->jumlah ?? 0;
                    $ditL = $data['diterima']['L']->jumlah ?? 0;
                    $ditP = $data['diterima']['P']->jumlah ?? 0;
                    $rujL = $data['dirujuk']['L']->jumlah ?? 0;
                    $rujP = $data['dirujuk']['P']->jumlah ?? 0;
                @endphp
                @foreach ([
        'Total Kunjungan IGD' => [$kunjL, $kunjP],
        'Diterima Rawat Inap' => [$ditL, $ditP],
        'Dirujuk ke RS Lain' => [$rujL, $rujP],
    ] as $label => [$l, $p])
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td
                            class="px-4 py-3 font-medium text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
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

                {{-- Meninggal --}}
                @foreach ($data['meninggal'] as $row)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td
                            class="px-4 py-3 font-medium text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            Meninggal ({{ $row['waktu'] === 'kurang48' ? '<48 jam' : '>=48 jam' }})
                        </td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['jk'] === 'L' ? $row['jumlah'] : '-' }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $row['jk'] === 'P' ? $row['jumlah'] : '-' }}</td>
                        <td class="px-4 py-3 text-center font-bold text-red-600 dark:text-red-400">
                            {{ $row['jumlah'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-sirs.export-buttons rl="3.3" />
</div>
