@props([
    'title' => '',
    'profil' => [],
    'bulan' => '',
    'tahun' => '',
    'subtitle' => '',
])

<div class="mb-6" data-area-print>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-primary-dark-100">{{ $title }}</h1>
            @if ($subtitle)
                <p class="text-sm text-zinc-500 dark:text-primary-dark-400">{{ $subtitle }}</p>
            @endif
        </div>
        <div
            class="text-sm font-semibold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 px-3 py-1.5 rounded-lg">
            Periode: {{ $bulan ? "$bulan $tahun" : "Tahun $tahun" }}
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 p-4 bg-zinc-50 dark:bg-primary-dark-800 rounded-lg border border-zinc-200 dark:border-primary-dark-700 text-sm"
        id="report-header-info">
        <div>
            <span class="font-semibold text-zinc-600 dark:text-primary-dark-400">Nama RS:</span>
            <span class="text-zinc-900 dark:text-primary-dark-100 ml-1">{{ $profil['nama_instansi'] ?? '-' }}</span>
        </div>
        <div>
            <span class="font-semibold text-zinc-600 dark:text-primary-dark-400">Alamat:</span>
            <span class="text-zinc-900 dark:text-primary-dark-100 ml-1">{{ $profil['alamat_instansi'] ?? '-' }}</span>
        </div>
        <div>
            <span class="font-semibold text-zinc-600 dark:text-primary-dark-400">Kab/Kota:</span>
            <span class="text-zinc-900 dark:text-primary-dark-100 ml-1">{{ $profil['kabupaten'] ?? '-' }}</span>
        </div>
        <div>
            <span class="font-semibold text-zinc-600 dark:text-primary-dark-400">Provinsi:</span>
            <span class="text-zinc-900 dark:text-primary-dark-100 ml-1">{{ $profil['propinsi'] ?? '-' }}</span>
        </div>
    </div>
</div>
