<div class="space-y-6">
    @if ($reg->status_lanjut === 'Ralan' && $reg->resumePasien)
        <div
            class="p-5 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
            <h3 class="flex items-center gap-2 mb-4 text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                <flux:icon name="document-text" class="w-4 h-4" /> Resume Pasien (Rawat Jalan)
            </h3>
            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 text-sm sm:grid-cols-2">
                @foreach ([
        'diagnosa_utama' => 'Diagnosa Utama',
        'diagnosa_sekunder' => 'Diagnosa Sekunder',
        'prosedur_utama' => 'Prosedur Utama',
        'keluhan_utama' => 'Keluhan Utama',
        'jalannya_penyakit' => 'Jalannya Penyakit',
        'pemeriksaan_fisik' => 'Pemeriksaan Fisik',
        'kondisi_pulang' => 'Kondisi Pulang',
        'saran_pulang' => 'Saran Pulang',
        'obat_pulang' => 'Obat Pulang',
    ] as $field => $label)
                    <div>
                        <dt class="mb-1 text-xs font-semibold text-zinc-500 dark:text-primary-dark-400">
                            {{ $label }}</dt>
                        <dd class="text-zinc-900 dark:text-primary-dark-100">
                            {{ $reg->resumePasien->$field ?? '-' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @elseif ($reg->status_lanjut === 'Ranap' && $reg->resumePasienRanap)
        <div
            class="p-5 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
            <h3 class="flex items-center gap-2 mb-4 text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                <flux:icon name="document-text" class="w-4 h-4" /> Resume Pasien (Rawat Inap)
            </h3>
            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 text-sm sm:grid-cols-2">
                @foreach ([
        'diagnosa_utama' => 'Diagnosa Utama',
        'diagnosa_sekunder' => 'Diagnosa Sekunder',
        'prosedur_utama' => 'Prosedur Utama',
        'keluhan_utama' => 'Keluhan Utama',
        'jalannya_penyakit' => 'Jalannya Penyakit',
        'pemeriksaan_fisik' => 'Pemeriksaan Fisik',
        'hasil_penunjang_medis' => 'Hasil Penunjang',
        'kondisi_pulang' => 'Kondisi Pulang',
        'instruksi_pulang' => 'Saran Pulang',
        'obat_pulang' => 'Obat Pulang',
    ] as $field => $label)
                    <div>
                        <dt class="mb-1 text-xs font-semibold text-zinc-500 dark:text-primary-dark-400">
                            {{ $label }}</dt>
                        <dd class="text-zinc-900 dark:text-primary-dark-100">
                            {{ $reg->resumePasienRanap->$field ?? '-' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @else
        <div class="flex flex-col items-center py-12">
            <flux:icon name="document-text" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data resume
                pasien</p>
        </div>
    @endif
</div>
