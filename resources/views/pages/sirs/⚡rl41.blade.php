<?php

use Livewire\Component;
use Livewire\Attributes\{Layout, Title, Url};
use App\Helpers\SirsHelper;
use App\Services\Sirs\SirsMorbidityService;

new #[Layout('layouts::app')] #[Title('RL 4.1 - Morbiditas Ranap')] class extends Component {
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
        $result = $service->getRL41($this->tahun, $this->bulan);
        return [
            'data' => $result['data'],
            'kematian' => $result['kematian'],
            'labels' => $result['labels'],
            'profil' => SirsHelper::getProfilRS(),
            'namaBulan' => SirsHelper::getNamaBulan($this->bulan),
        ];
    }
};
?>

<div>
    <x-sirs.report-header title="RL 4.1 - Morbiditas Pasien Rawat Inap"
        subtitle="Berdasarkan kelompok umur dan jenis kelamin" :profil="$profil" :bulan="$namaBulan" :tahun="$tahun" />
    <x-sirs.period-filter :tahun="$tahun" :bulan="$bulan" />

    <div class="overflow-x-auto mt-4 rounded-lg border border-zinc-200 dark:border-primary-dark-700" data-area-print>
        <table id="report-table" class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700 text-xs"
            style="min-width: 2400px;">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th rowspan="3"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700 w-8 sticky left-0 bg-zinc-50 dark:bg-primary-dark-900 z-10">
                        No</th>
                    <th rowspan="3"
                        class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700 sticky left-16 bg-zinc-50 dark:bg-primary-dark-900 z-10 min-w-[60px]">
                        Kode
                    </th>
                    <th rowspan="3"
                        class="px-4 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700 sticky left-[124px] bg-zinc-50 dark:bg-primary-dark-900 z-10 min-w-[180px]">
                        Nama Penyakit</th>
                    @foreach ($labels as $label)
                        <th colspan="2"
                            class="px-2 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $label }}
                        </th>
                    @endforeach
                    <th colspan="2"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        Total</th>
                    <th rowspan="3"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Jml</th>
                    <th colspan="3"
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-zinc-200 dark:border-primary-dark-700">
                        Kematian</th>
                </tr>
                <tr>
                    @for ($i = 0; $i < count($labels); $i++)
                        <th
                            class="px-2 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                            L</th>
                        <th
                            class="px-2 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                            P</th>
                    @endfor
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
                    <th
                        class="px-4 py-3 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400">
                        Jml</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                @if (count($data) === 0)
                    <tr>
                        <td colspan="{{ 3 + count($labels) * 2 + 6 }}"
                            class="px-4 py-8 text-center text-zinc-500 dark:text-primary-dark-400">Tidak ada data</td>
                    </tr>
                @endif

                @php $no = 1; @endphp
                @foreach ($data as $kode => $item)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700 sticky left-0 bg-white dark:bg-primary-dark-800 z-10">
                            {{ $no++ }}</td>
                        <td
                            class="px-4 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700 sticky left-16 bg-white dark:bg-primary-dark-800 z-10 font-mono">
                            {{ $kode }}</td>
                        <td
                            class="px-4 py-3 text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700 sticky left-[124px] bg-white dark:bg-primary-dark-800 z-10">
                            {{ Str::limit($item['nama'], 30) }}</td>
                        @foreach ($labels as $label)
                            @php $d = $item['detail'][$label]; @endphp
                            <td
                                class="px-2 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                                {{ $d['l'] ?: '' }}</td>
                            <td
                                class="px-2 py-3 text-center text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                                {{ $d['p'] ?: '' }}</td>
                        @endforeach
                        <td
                            class="px-4 py-3 text-center font-bold text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $item['total_l'] }}</td>
                        <td
                            class="px-4 py-3 text-center font-bold text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $item['total_p'] }}</td>
                        <td
                            class="px-4 py-3 text-center font-bold text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $item['total'] }}</td>
                        @php $mati = $kematian[$kode] ?? ['l' => 0, 'p' => 0, 'total' => 0]; @endphp
                        <td
                            class="px-4 py-3 text-center text-red-600 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $mati['l'] ?: '' }}</td>
                        <td
                            class="px-4 py-3 text-center text-red-600 border-r border-zinc-200 dark:border-primary-dark-700">
                            {{ $mati['p'] ?: '' }}</td>
                        <td class="px-4 py-3 text-center text-red-600 font-bold">
                            {{ $mati['total'] ?: '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-xs text-zinc-500 mt-2">Total {{ count($data) }} kode penyakit</p>

    <x-sirs.export-buttons rl="4.1" />
</div>
