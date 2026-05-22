<?php

use Livewire\Component;
use Livewire\Attributes\{Layout, Title};

new #[Layout('layouts::app')] #[Title('SIRS Online')] class extends Component {
    public function with(): array
    {
        $formulir = [
            'bulanan' => [
                ['kode' => 'RL 3.1', 'judul' => 'Indikator Pelayanan', 'route' => 'sirs.rl31', 'desc' => 'BOR, ALOS, BTO, TOI, NDR, GDR'],
                ['kode' => 'RL 3.2', 'judul' => 'Rawat Inap', 'route' => 'sirs.rl32', 'desc' => 'Rekapitulasi kegiatan pelayanan rawat inap'],
                ['kode' => 'RL 3.3', 'judul' => 'Rawat Darurat', 'route' => 'sirs.rl33', 'desc' => 'Pelayanan rawat darurat/IGD'],
                ['kode' => 'RL 3.4', 'judul' => 'Pengunjung', 'route' => 'sirs.rl34', 'desc' => 'Rekapitulasi pengunjung rumah sakit'],
                ['kode' => 'RL 3.5', 'judul' => 'Kunjungan Rajal', 'route' => 'sirs.rl35', 'desc' => 'Kunjungan rawat jalan'],
                ['kode' => 'RL 3.6', 'judul' => 'Kebidanan', 'route' => 'sirs.rl36', 'desc' => 'Pelayanan kebidanan'],
                ['kode' => 'RL 3.7', 'judul' => 'Neonatal', 'route' => 'sirs.rl37', 'desc' => 'Neonatal/Bayi/Balita'],
                ['kode' => 'RL 3.8', 'judul' => 'Laboratorium', 'route' => 'sirs.rl38', 'desc' => 'Pemeriksaan laboratorium'],
                ['kode' => 'RL 3.9', 'judul' => 'Radiologi', 'route' => 'sirs.rl39', 'desc' => 'Pemeriksaan radiologi'],
                ['kode' => 'RL 3.10', 'judul' => 'Rujukan', 'route' => 'sirs.rl310', 'desc' => 'Rujukan masuk & keluar'],
                ['kode' => 'RL 3.12', 'judul' => 'Pembedahan', 'route' => 'sirs.rl312', 'desc' => 'Pelayanan pembedahan'],
                ['kode' => 'RL 3.14', 'judul' => 'Pelayanan Khusus', 'route' => 'sirs.rl314', 'desc' => 'Pelayanan khusus'],
                ['kode' => 'RL 3.19', 'judul' => 'Cara Bayar', 'route' => 'sirs.rl319', 'desc' => 'Cara bayar pasien'],
            ],
            'tahunan' => [['kode' => 'RL 3.11', 'judul' => 'Gigi & Mulut', 'route' => 'sirs.rl311', 'desc' => 'Pelayanan gigi dan mulut'], ['kode' => 'RL 3.13', 'judul' => 'Rehab Medik', 'route' => 'sirs.rl313', 'desc' => 'Rehabilitasi medik'], ['kode' => 'RL 3.15', 'judul' => 'Kesehatan Jiwa', 'route' => 'sirs.rl315', 'desc' => 'Pelayanan kesehatan jiwa'], ['kode' => 'RL 3.16', 'judul' => 'Keluarga Berencana', 'route' => 'sirs.rl316', 'desc' => 'Pelayanan KB'], ['kode' => 'RL 3.17', 'judul' => 'Farmasi Pengadaan', 'route' => 'sirs.rl317', 'desc' => 'Pengadaan obat'], ['kode' => 'RL 3.18', 'judul' => 'Farmasi Resep', 'route' => 'sirs.rl318', 'desc' => 'Resep obat']],
            'penyakit' => [['kode' => 'RL 4.1', 'judul' => 'Morbiditas Ranap', 'route' => 'sirs.rl41', 'desc' => 'Penyakit rawat inap per kelompok umur'], ['kode' => 'RL 4.2', 'judul' => '10 Besar Ranap', 'route' => 'sirs.rl42', 'desc' => '10 besar penyakit rawat inap'], ['kode' => 'RL 4.3', 'judul' => '10 Besar Kematian', 'route' => 'sirs.rl43', 'desc' => '10 besar penyebab kematian'], ['kode' => 'RL 5.1', 'judul' => 'Morbiditas Rajal', 'route' => 'sirs.rl51', 'desc' => 'Penyakit rawat jalan per kelompok umur'], ['kode' => 'RL 5.2', 'judul' => '10 Besar Rajal', 'route' => 'sirs.rl52', 'desc' => '10 besar penyakit rawat jalan kasus baru'], ['kode' => 'RL 5.3', 'judul' => '10 Besar Kunjungan', 'route' => 'sirs.rl53', 'desc' => '10 besar kunjungan rawat jalan']],
        ];

        return compact('formulir');
    }
};
?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-primary-dark-100">SIRS Online</h1>
        <p class="text-sm text-zinc-500 dark:text-primary-dark-400">Sistem Informasi Rumah Sakit - Laporan RL (Rekam
            Laporan)</p>
    </div>

    {{-- RL 3 Bulanan --}}
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-zinc-800 dark:text-primary-dark-200 mb-3 flex items-center gap-2">
            <flux:icon.calendar-days class="w-5 h-5 text-primary-600" />
            RL 3 - Laporan Bulanan
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            @foreach ($formulir['bulanan'] as $item)
                <a wire:navigate href="{{ route($item['route']) }}"
                    class="block p-4 bg-white dark:bg-primary-dark-800 rounded-lg border border-zinc-200 dark:border-primary-dark-700 hover:border-primary-400 dark:hover:border-primary-600 hover:shadow-md transition-all group">
                    <div class="flex items-start justify-between">
                        <span
                            class="text-xs font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 px-2 py-0.5 rounded">{{ $item['kode'] }}</span>
                        <flux:icon.arrow-right
                            class="w-4 h-4 text-zinc-400 group-hover:text-primary-600 transition-colors" />
                    </div>
                    <h3 class="font-semibold text-zinc-900 dark:text-primary-dark-100 mt-2">{{ $item['judul'] }}</h3>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-1">{{ $item['desc'] }}</p>
                </a>
            @endforeach
        </div>
    </div>

    {{-- RL 3 Tahunan --}}
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-zinc-800 dark:text-primary-dark-200 mb-3 flex items-center gap-2">
            <flux:icon.chart-bar class="w-5 h-5 text-amber-600" />
            RL 3 - Laporan Tahunan
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            @foreach ($formulir['tahunan'] as $item)
                <a wire:navigate href="{{ route($item['route']) }}"
                    class="block p-4 bg-white dark:bg-primary-dark-800 rounded-lg border border-zinc-200 dark:border-primary-dark-700 hover:border-amber-400 dark:hover:border-amber-600 hover:shadow-md transition-all group">
                    <div class="flex items-start justify-between">
                        <span
                            class="text-xs font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-2 py-0.5 rounded">{{ $item['kode'] }}</span>
                        <flux:icon.arrow-right
                            class="w-4 h-4 text-zinc-400 group-hover:text-amber-600 transition-colors" />
                    </div>
                    <h3 class="font-semibold text-zinc-900 dark:text-primary-dark-100 mt-2">{{ $item['judul'] }}</h3>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-1">{{ $item['desc'] }}</p>
                </a>
            @endforeach
        </div>
    </div>

    {{-- RL 4-5 Penyakit --}}
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-zinc-800 dark:text-primary-dark-200 mb-3 flex items-center gap-2">
            <flux:icon.clipboard-document-list class="w-5 h-5 text-emerald-600" />
            RL 4-5 - Laporan Penyakit
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            @foreach ($formulir['penyakit'] as $item)
                <a wire:navigate href="{{ route($item['route']) }}"
                    class="block p-4 bg-white dark:bg-primary-dark-800 rounded-lg border border-zinc-200 dark:border-primary-dark-700 hover:border-emerald-400 dark:hover:border-emerald-600 hover:shadow-md transition-all group">
                    <div class="flex items-start justify-between">
                        <span
                            class="text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 rounded">{{ $item['kode'] }}</span>
                        <flux:icon.arrow-right
                            class="w-4 h-4 text-zinc-400 group-hover:text-emerald-600 transition-colors" />
                    </div>
                    <h3 class="font-semibold text-zinc-900 dark:text-primary-dark-100 mt-2">{{ $item['judul'] }}</h3>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-1">{{ $item['desc'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
</div>
