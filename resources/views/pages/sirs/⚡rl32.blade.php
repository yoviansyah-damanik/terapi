<?php

use Livewire\Component;
use Livewire\Attributes\{Layout, Title, Url};
use App\Helpers\SirsHelper;
use App\Services\Sirs\SirsIndicatorService;

new #[Layout('layouts::app')] #[Title('RL 3.2 - Rawat Inap')] class extends Component {
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
            'data' => $service->getRL32($this->tahun, $this->bulan),
            'profil' => SirsHelper::getProfilRS(),
            'namaBulan' => SirsHelper::getNamaBulan($this->bulan),
        ];
    }
};
?>

<div>
    <x-sirs.report-header title="RL 3.2 - Rekapitulasi Kegiatan Pelayanan Rawat Inap" subtitle="Formulir Bulanan"
        :profil="$profil" :bulan="$namaBulan" :tahun="$tahun" />
    <x-sirs.period-filter :tahun="$tahun" :bulan="$bulan" />

    <div class="overflow-x-auto mt-4 rounded-lg border border-zinc-200 dark:border-primary-dark-700" data-area-print>
        <table id="report-table" class="min-w-[1600px] divide-y divide-zinc-200 dark:divide-primary-dark-700 text-[10px]">
            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                <tr>
                    <th rowspan="3"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700 w-8">
                        No</th>
                    <th rowspan="3"
                        class="px-2 py-2 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700 min-w-[140px]">
                        Jenis Pelayanan</th>
                    <th rowspan="3"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Ps. Awal</th>
                    <th rowspan="3"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Ps. Masuk</th>
                    <th rowspan="3"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Pindahan</th>
                    <th rowspan="3"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Dipindah</th>
                    <th rowspan="3"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Keluar Hidup</th>
                    <th colspan="4"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        Keluar Mati</th>
                    <th rowspan="3"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Lama Rawat</th>
                    <th rowspan="3"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        Ps. Akhir</th>
                    <th rowspan="3"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        HP Total</th>
                    <th colspan="6"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        Hari Perawatan per Kelas</th>
                    <th rowspan="3"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400">
                        TT</th>
                </tr>
                <tr>
                    <th colspan="2"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        Laki-laki</th>
                    <th colspan="2"
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        Perempuan</th>
                    <th
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        VVIP</th>
                    <th
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        VIP</th>
                    <th
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        I</th>
                    <th
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        II</th>
                    <th
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        III</th>
                    <th
                        class="px-2 py-2 text-center text-xs font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-b border-r border-zinc-200 dark:border-primary-dark-700">
                        Khusus</th>
                </tr>
                <tr>
                    <th
                        class="px-2 py-2 text-center text-[10px] font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        &lt;48j</th>
                    <th
                        class="px-2 py-2 text-center text-[10px] font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        &ge;48j</th>
                    <th
                        class="px-2 py-2 text-center text-[10px] font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        &lt;48j</th>
                    <th
                        class="px-2 py-2 text-center text-[10px] font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                        &ge;48j</th>
                    <th
                        class="px-2 py-2 text-center text-[10px] font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                    </th>
                    <th
                        class="px-2 py-2 text-center text-[10px] font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                    </th>
                    <th
                        class="px-2 py-2 text-center text-[10px] font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                    </th>
                    <th
                        class="px-2 py-2 text-center text-[10px] font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                    </th>
                    <th
                        class="px-2 py-2 text-center text-[10px] font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                    </th>
                    <th
                        class="px-2 py-2 text-center text-[10px] font-medium text-zinc-500 uppercase tracking-wider dark:text-primary-dark-400 border-r border-zinc-200 dark:border-primary-dark-700">
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                @foreach ($data as $key => $row)
                    @if ($key <= 35 || $key == 99)
                        <tr
                            class="{{ $key === 99 ? 'bg-primary-50 dark:bg-primary-900/20 font-semibold' : 'hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50' }}">
                            <td
                                class="px-2 py-1 text-center whitespace-nowrap text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                                {{ $key === 99 ? '' : $key }}</td>
                            <td
                                class="px-2 py-1 whitespace-nowrap text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                                {{ $row['nama'] }}</td>
                            @foreach (['pasien_awal', 'pasien_masuk', 'pasien_pindahan', 'pasien_dipindahkan', 'pasien_keluar_hidup', 'pasien_laki_mati_kurang48', 'pasien_laki_mati_lebih48', 'pasien_perempuan_mati_kurang48', 'pasien_perempuan_mati_lebih48', 'jumlah_lama_dirawat', 'pasien_akhir', 'jumlah_hari_perawatan', 'hari_perawatan_vvip', 'hari_perawatan_vip', 'hari_perawatan_i', 'hari_perawatan_ii', 'hari_perawatan_iii', 'hari_perawatan_khusus', 'tempat_tidur'] as $field)
                                <td
                                    class="px-2 py-1 text-center whitespace-nowrap text-zinc-900 dark:text-primary-dark-100 border-r border-zinc-200 dark:border-primary-dark-700">
                                    {{ $row[$field] > 0 ? number_format($row[$field]) : '-' }}</td>
                            @endforeach
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <x-sirs.export-buttons rl="3.2" />
</div>
