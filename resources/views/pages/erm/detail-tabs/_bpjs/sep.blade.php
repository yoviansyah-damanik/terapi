@php
    $jnsRawatMap = ['0' => 'Rawat Jalan', '1' => 'Rawat Inap', '2' => 'IGD'];
    $kunjunganMap = ['0' => 'Normal', '1' => 'Program Rujuk Balik (PRB)', '2' => 'Kontrol Khusus'];
    $isPrb = ($sep->tujuankunjungan ?? '') === '1';
    $jnsRawat = $jnsRawatMap[$sep->jnspelayanan ?? ''] ?? ($sep->jnspelayanan ?? '-');
    $jnsKunjungan = $kunjunganMap[$sep->tujuankunjungan ?? ''] ?? ($sep->tujuankunjungan ?? '-');
    $dokter = $sep->nmdpdjp ?? ($sep->nmdpjplayanan ?? '-');
@endphp

<div class="border border-zinc-200 dark:border-primary-dark-700 rounded-xl overflow-hidden text-sm select-none">

    {{-- Header BPJS --}}
    <div class="bg-[#009B4D] px-4 py-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shrink-0">
                <img class="w-full" src="{{ Vite::image('bpjs.png') }}" />
            </div>
            <div class="text-white min-w-0">
                <p class="text-xs font-bold tracking-wide uppercase">Surat Eligibilitas Peserta (SEP)</p>
                <p class="text-[11px] opacity-75 truncate">
                    {{ config('hospital.name', 'Fasilitas Kesehatan Tingkat Lanjutan') }}</p>
            </div>
        </div>
        <div class="text-right text-white shrink-0">
            <p class="font-mono font-bold text-xs">{{ $sep->no_sep }}</p>
            <p class="text-[11px] opacity-75">{{ $sep->tglsep?->format('d/m/Y') ?? '-' }}</p>
        </div>
    </div>

    {{-- PRB Banner --}}
    @if ($isPrb)
        <div
            class="bg-emerald-50 dark:bg-emerald-900/20 border-b border-emerald-200 dark:border-emerald-800/40 px-4 py-1.5 flex items-center justify-center gap-2">
            <flux:icon name="arrow-path" class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
            <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">Program Rujuk Balik (PRB)</span>
        </div>
    @endif

    {{-- Body: 2 kolom --}}
    <div
        class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-zinc-100 dark:divide-primary-dark-700 bg-white dark:bg-primary-dark-800">

        {{-- Kolom Kiri: Data Peserta & Pelayanan --}}
        <div class="p-4 space-y-3">

            {{-- Data Peserta --}}
            <div>
                <p
                    class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500 mb-2">
                    Data
                    Peserta</p>
                <dl class="space-y-1.5">
                    <div class="flex gap-2">
                        <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-24 shrink-0">No. Kartu</dt>
                        <dd class="font-mono text-[11px] font-semibold text-zinc-800 dark:text-primary-dark-100">
                            {{ $sep->no_kartu ?? '-' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-24 shrink-0">Nama Peserta</dt>
                        <dd class="text-[11px] font-semibold text-zinc-800 dark:text-primary-dark-100">
                            {{ $sep->nama_pasien ?? '-' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-24 shrink-0">Tgl. Lahir</dt>
                        <dd class="text-[11px] text-zinc-700 dark:text-primary-dark-300">
                            {{ $sep->tanggal_lahir ? \Carbon\Carbon::parse($sep->tanggal_lahir)->format('d/m/Y') : '-' }}
                        </dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-24 shrink-0">No. Telepon</dt>
                        <dd class="font-mono text-[11px] text-zinc-700 dark:text-primary-dark-300">
                            {{ $sep->notelep ?? '-' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <hr class="border-zinc-100 dark:border-primary-dark-700">

            {{-- Data Pelayanan --}}
            <div>
                <p
                    class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500 mb-2">
                    Pelayanan</p>
                <dl class="space-y-1.5">
                    <div class="flex gap-2">
                        <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-24 shrink-0">Sub/Spesialis
                        </dt>
                        <dd class="text-[11px] text-zinc-700 dark:text-primary-dark-300">{{ $sep->nmpolitujuan ?? '-' }}
                        </dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-24 shrink-0">Dokter</dt>
                        <dd class="text-[11px] text-zinc-700 dark:text-primary-dark-300">{{ $dokter }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-24 shrink-0">Faskes Perujuk
                        </dt>
                        <dd class="text-[11px] text-zinc-700 dark:text-primary-dark-300">
                            {{ $sep->nmppkrujukan ?? '-' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-24 shrink-0">Diagnosa Awal
                        </dt>
                        <dd class="text-[11px] text-zinc-700 dark:text-primary-dark-300">
                            @if ($sep->diagawal)
                                <span
                                    class="font-mono font-semibold text-primary-600 dark:text-primary-400">{{ $sep->diagawal }}</span>
                                @if ($sep->nmdiagnosaawal)
                                    <span class="ml-1">— {{ $sep->nmdiagnosaawal }}</span>
                                @endif
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                    @if ($sep->catatan)
                        <div class="flex gap-2">
                            <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-24 shrink-0">Catatan</dt>
                            <dd class="text-[11px] text-zinc-700 dark:text-primary-dark-300">{{ $sep->catatan }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Kolom Kanan: Registrasi & Klasifikasi --}}
        <div class="p-4">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500 mb-2">
                Registrasi & Klasifikasi</p>
            <dl class="space-y-1.5">
                <div class="flex gap-2">
                    <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-28 shrink-0">No. Rawat</dt>
                    <dd class="font-mono text-[11px] font-semibold text-zinc-800 dark:text-primary-dark-100">
                        {{ $sep->no_rawat ?? '-' }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-28 shrink-0">No. RM</dt>
                    <dd class="font-mono text-[11px] text-zinc-700 dark:text-primary-dark-300">{{ $sep->nomr ?? '-' }}
                    </dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-28 shrink-0">Peserta</dt>
                    <dd class="text-[11px] text-zinc-700 dark:text-primary-dark-300">{{ $sep->peserta ?? '-' }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-28 shrink-0">Jns. Rawat</dt>
                    <dd class="text-[11px] text-zinc-700 dark:text-primary-dark-300">{{ $jnsRawat }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-28 shrink-0">Jns. Kunjungan</dt>
                    <dd class="text-[11px] text-zinc-700 dark:text-primary-dark-300">{{ $jnsKunjungan }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-28 shrink-0">Poli Tujuan</dt>
                    <dd class="text-[11px] text-zinc-700 dark:text-primary-dark-300">{{ $sep->nmpolitujuan ?? '-' }}
                    </dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-28 shrink-0">Kls. Hak</dt>
                    <dd class="text-[11px] text-zinc-700 dark:text-primary-dark-300">{{ $sep->klsrawat ?? '-' }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-28 shrink-0">Kls. Rawat</dt>
                    <dd class="text-[11px] text-zinc-700 dark:text-primary-dark-300">
                        {{ $sep->klsnaik ?: $sep->klsrawat ?? '-' }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-28 shrink-0">Penjamin</dt>
                    <dd class="text-[11px] text-zinc-700 dark:text-primary-dark-300">{{ $sep->pembiayaan ?? '-' }}</dd>
                </div>
                @if ($sep->noskdp)
                    <div class="flex gap-2">
                        <dt class="text-[11px] text-zinc-400 dark:text-primary-dark-500 w-28 shrink-0">No. SKP</dt>
                        <dd class="font-mono text-[11px] text-zinc-700 dark:text-primary-dark-300">{{ $sep->noskdp }}
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    {{-- Footer: Tanda Tangan Pasien --}}
    <div
        class="border-t border-zinc-100 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-900/30 px-4 py-3 flex items-end justify-between gap-4">
        <div class="text-[11px] text-zinc-400 dark:text-primary-dark-500 space-y-0.5">
            @if ($sep->tglsep)
                <p>{{ config('hospital.city', '') }}, {{ $sep->tglsep->isoFormat('D MMMM Y') }}</p>
            @endif
            @if ($sep->backdate)
                <p class="text-amber-500">Backdate: {{ $sep->backdate }}</p>
            @endif
            @if ($sep->suplesi && $sep->no_sep_suplesi)
                <p>Suplesi SEP: <span class="font-mono">{{ $sep->no_sep_suplesi }}</span></p>
            @endif
        </div>
        <div class="text-center shrink-0">
            <div
                class="w-20 h-14 border border-dashed border-zinc-300 dark:border-primary-dark-600 rounded-lg flex items-center justify-center mb-1.5">
                <flux:icon name="qr-code" class="w-8 h-8 text-zinc-300 dark:text-primary-dark-600" />
            </div>
            <p class="text-[10px] text-zinc-400 dark:text-primary-dark-500">Pasien / Keluarga Pasien</p>
            <p class="text-[11px] font-semibold text-zinc-600 dark:text-primary-dark-400 mt-0.5">
                {{ $sep->nama_pasien ?? '' }}
            </p>
        </div>
    </div>
</div>
