<?php

use App\Models\SatuSehat\SatuSehatEncounter;
use App\Models\Simrs\RegPeriksa;
use App\Models\Simrs\AlergiPasien;
use App\Models\Simrs\CatatanAdimeGizi;
use App\Models\Simrs\DetailPemberianObat;
use App\Models\Simrs\DiagnosaPasien;
use App\Models\Simrs\Dokter;
use App\Models\Simrs\PemeriksaanRalan;
use App\Models\Simrs\PemeriksaanRanap;
use App\Models\Simrs\LaporanOperasi;
use App\Models\Simrs\PermintaanLab;
use App\Models\Simrs\PermintaanLabMb;
use App\Models\Simrs\PermintaanLabPa;
use App\Models\Simrs\PermintaanRadiologi;
use App\Models\Simrs\ProsedurPasien;
use App\Models\Simrs\ResepPulang;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Detail eRM')] class extends Component {
    public string $activeTab = 'admission';

    public string $noRawat;

    private function getNilaiRujukan($template, ?string $jk, ?int $umurTahun): ?string
    {
        if (!$template) {
            return null;
        }
        $isAnak = $umurTahun !== null && $umurTahun < 18;
        if ($jk === 'L') {
            return $isAnak ? $template->nilai_rujukan_la : $template->nilai_rujukan_ld;
        }
        return $isAnak ? $template->nilai_rujukan_pa : $template->nilai_rujukan_pd;
    }

    public function getUsgTypeConfigs(): array
    {
        return \App\Services\UsgService::getUsgTypeConfigs();
    }

    public function with(): array
    {
        $reg = $ssEncounter = null;
        $reg = RegPeriksa::with(['pasien', 'dokter', 'poliklinik', 'penjab', 'bridgingSep', 'resumePasien', 'resumePasienRanap'])->find($this->noRawat);

        if ($reg) {
            $ssEncounter = SatuSehatEncounter::where('local_id', $reg->no_rawat)->first();

            $noRawat = $reg->no_rawat;

            $reg->loadMissing(['pasien', 'resumePasien', 'resumePasienRanap']);
            if ($reg->status_lanjut === 'Ranap') {
                $reg->loadMissing([
                    'kamarInap' => fn($q) => $q
                        ->with(['kamar.bangsal'])
                        ->orderBy('tgl_masuk')
                        ->orderBy('jam_masuk'),
                    'dpjpRanap.dokter',
                ]);
            }

            $pasienJk = $reg->pasien?->jk;
            $pasienUmur = $reg->pasien?->tgl_lahir?->age;

            $diagnosas = DiagnosaPasien::where('no_rawat', $noRawat)->with('penyakit')->orderBy('prioritas')->get();
            $prosedurs = ProsedurPasien::where('no_rawat', $noRawat)->with('icd9')->orderBy('prioritas')->get();

            $pemeriksaans = $reg->status_lanjut === 'Ralan' ? PemeriksaanRalan::where('no_rawat', $noRawat)->with('petugas')->orderByDesc('tgl_perawatan')->orderByDesc('jam_rawat')->get() : PemeriksaanRanap::where('no_rawat', $noRawat)->with('petugas')->orderByDesc('tgl_perawatan')->orderByDesc('jam_rawat')->get();

            $catatanGizis = CatatanAdimeGizi::where('no_rawat', $noRawat)->with('petugas')->orderByDesc('tanggal')->get();

            $obats = DetailPemberianObat::where('no_rawat', $noRawat)
                ->with(['dataBarang', 'bangsal', 'aturanPakai', 'dataBatch'])
                ->orderBy('tgl_perawatan')
                ->orderBy('jam')
                ->get();

            $resepPulangs =
                $reg->status_lanjut === 'Ranap'
                    ? ResepPulang::where('no_rawat', $noRawat)
                        ->with(['dataBarang', 'dataBatch'])
                        ->orderBy('tanggal')
                        ->orderBy('jam')
                        ->get()
                    : collect();

            // Lab PK — root: PermintaanLab, relasi: periksaLab → detailPeriksaLab + templateLaboratoriums + kesanSaran
            $permintaanLabsPk = PermintaanLab::where('no_rawat', $noRawat)
                ->with(['dokterPerujuk', 'kesanSaran', 'periksaLab.jenisPerawatan', 'periksaLab.dokter', 'periksaLab.detailPeriksaLab', 'periksaLab.templateLaboratoriums'])
                ->orderBy('tgl_permintaan')
                ->orderBy('jam_permintaan')
                ->get();

            $periksaLabsPk = $permintaanLabsPk->flatMap(fn($p) => $p->periksaLab)->values();
            $saranKesanLabs = $permintaanLabsPk->map(fn($p) => $p->kesanSaran)->filter()->values();
            $detailLabsPk = $periksaLabsPk->mapWithKeys(
                fn($lab) => [
                    $lab->kd_jenis_prw . '|' . $lab->tgl_periksa?->format('Y-m-d') . '|' . $lab->jam => $lab->detailPeriksaLab->keyBy('id_template'),
                ],
            );
            $templatesPk = $periksaLabsPk->flatMap(fn($lab) => $lab->templateLaboratoriums)->unique('id_template')->groupBy('kd_jenis_prw');

            // Lab MB — root: PermintaanLabMb, relasi: periksaLab → detailPeriksaLab + templateLaboratoriums
            $permintaanLabsMb = PermintaanLabMb::where('no_rawat', $noRawat)
                ->with(['dokterPerujuk', 'periksaLab.jenisPerawatan', 'periksaLab.dokter', 'periksaLab.detailPeriksaLab', 'periksaLab.templateLaboratoriums'])
                ->orderBy('tgl_permintaan')
                ->orderBy('jam_permintaan')
                ->get();

            $periksaLabsMb = $permintaanLabsMb->flatMap(fn($p) => $p->periksaLab)->values();
            $detailLabsMb = $periksaLabsMb->mapWithKeys(
                fn($lab) => [
                    $lab->kd_jenis_prw . '|' . $lab->tgl_periksa?->format('Y-m-d') . '|' . $lab->jam => $lab->detailPeriksaLab->keyBy('id_template'),
                ],
            );
            $templatesMb = $periksaLabsMb->flatMap(fn($lab) => $lab->templateLaboratoriums)->unique('id_template')->groupBy('kd_jenis_prw');

            // Lab PA — root: PermintaanLabPa, relasi: detailPeriksaLabPa → jenisPerawatan
            $permintaanLabsPa = PermintaanLabPa::where('no_rawat', $noRawat)
                ->with(['dokterPerujuk', 'detailPeriksaLabPa.jenisPerawatan'])
                ->orderBy('tgl_permintaan')
                ->orderBy('jam_permintaan')
                ->get();

            $detailPeriksaLabsPa = $permintaanLabsPa->flatMap(fn($p) => $p->detailPeriksaLabPa)->values();

            // Radiologi
            $permintaanRadiologis = PermintaanRadiologi::where('no_rawat', $noRawat)
                ->with(['dokterPerujuk', 'allPeriksaRad.jenisPerawatan', 'allPeriksaRad.dokter', 'allHasilRadiologi', 'allGambarRadiologi', 'allBhpRadiologi.dataBarang'])
                ->orderBy('tgl_permintaan')
                ->orderBy('jam_permintaan')
                ->get();

            $periksaRadiologis = $permintaanRadiologis->flatMap(fn($pr) => $pr->periksa_rad);
            $hasilRadiologis = $permintaanRadiologis->map(fn($pr) => $pr->hasilRadiologi)->filter()->values();
            $gambarRadiologis = $permintaanRadiologis->flatMap(fn($pr) => $pr->gambarRadiologi);
            $bhpRadiologis = $permintaanRadiologis->flatMap(fn($pr) => $pr->bhpRadiologi);

            $conn = DB::connection('simrs');

            // USG — batch Dokter query ke satu round trip
            $usgConfigs = $this->getUsgTypeConfigs();
            $usgRawData = [];
            $allKdDokters = collect();

            foreach ($usgConfigs as $usgKey => $usgConfig) {
                $data = $usgConfig['model']::where('no_rawat', $noRawat)->orderBy('tanggal')->get();
                if ($data->isNotEmpty()) {
                    $usgRawData[$usgKey] = $data;
                    $allKdDokters = $allKdDokters->merge($data->pluck('kd_dokter')->filter());
                }
            }

            $allDokters = $allKdDokters->isNotEmpty() ? Dokter::whereIn('kd_dokter', $allKdDokters->unique())->pluck('nm_dokter', 'kd_dokter') : collect();

            $usgResults = [];
            foreach ($usgRawData as $usgKey => $data) {
                $usgConfig = $usgConfigs[$usgKey];
                $gambar = $usgConfig['gambar_model']::where('no_rawat', $noRawat)->whereIn('noorder', $data->pluck('noorder'))->get()->pluck('photo_url');
                $usgResults[$usgKey] = [
                    'label' => $usgConfig['label'],
                    'fields' => $usgConfig['fields'],
                    'data' => $data,
                    'dokters' => $allDokters,
                    'gambar' => $gambar,
                ];
            }

            // Alergi
            $alergiPasiens = AlergiPasien::where('no_rawat_ref', $noRawat)
                ->with(['alergi', 'reaksi', 'tingkatKeparahan', 'kritisitas', 'pegawai'])
                ->orderBy('tanggal')
                ->orderBy('jam')
                ->get();

            // Tindakan
            $tindakanJalanDr = $tindakanJalanPr = $tindakanJalanDrPr = collect();
            $tindakanInapDr = $tindakanInapPr = $tindakanInapDrPr = collect();

            $queryTindakan = function (string $table, string $ref, bool $hasDr, bool $hasPr) use ($conn, $noRawat) {
                $q = $conn
                    ->table("$table as t")
                    ->join("$ref as ref", 't.kd_jenis_prw', '=', 'ref.kd_jenis_prw')
                    ->select('t.kd_jenis_prw', 't.tgl_perawatan', 't.jam_rawat', 't.biaya_rawat', 'ref.nm_perawatan');
                if ($hasDr) {
                    $q->addSelect('t.kd_dokter')->leftJoin('dokter as d', 't.kd_dokter', '=', 'd.kd_dokter')->addSelect('d.nm_dokter');
                }
                if ($hasPr) {
                    $q->addSelect('t.nip');
                }
                return $q->where('t.no_rawat', $noRawat)->orderBy('t.tgl_perawatan')->orderBy('t.jam_rawat')->get();
            };

            if ($reg->status_lanjut === 'Ralan') {
                $tindakanJalanDr = $queryTindakan('rawat_jl_dr', 'jns_perawatan', true, false);
                $tindakanJalanPr = $queryTindakan('rawat_jl_pr', 'jns_perawatan', false, true);
                $tindakanJalanDrPr = $queryTindakan('rawat_jl_drpr', 'jns_perawatan', true, true);
            } else {
                $tindakanInapDr = $queryTindakan('rawat_inap_dr', 'jns_perawatan_inap', true, false);
                $tindakanInapPr = $queryTindakan('rawat_inap_pr', 'jns_perawatan_inap', false, true);
                $tindakanInapDrPr = $queryTindakan('rawat_inap_drpr', 'jns_perawatan_inap', true, true);
            }

            $laporanOperasis = LaporanOperasi::where('no_rawat', $noRawat)->orderBy('tanggal')->get();

            return compact('reg', 'ssEncounter', 'pasienJk', 'pasienUmur', 'diagnosas', 'prosedurs', 'pemeriksaans', 'catatanGizis', 'obats', 'resepPulangs', 'permintaanLabsPk', 'periksaLabsPk', 'detailLabsPk', 'templatesPk', 'saranKesanLabs', 'permintaanLabsMb', 'periksaLabsMb', 'detailLabsMb', 'templatesMb', 'permintaanLabsPa', 'detailPeriksaLabsPa', 'permintaanRadiologis', 'periksaRadiologis', 'hasilRadiologis', 'gambarRadiologis', 'bhpRadiologis', 'alergiPasiens', 'usgResults', 'tindakanJalanDr', 'tindakanJalanPr', 'tindakanJalanDrPr', 'tindakanInapDr', 'tindakanInapPr', 'tindakanInapDrPr', 'laporanOperasis');
        }
        return compact('reg');
    }
};
?>

<div x-data="{
    activeTab: new URLSearchParams(window.location.search).get('activeTab') || '{{ $activeTab }}'
}" x-init="$watch('activeTab', value => {
    const url = new URL(window.location.href);
    url.searchParams.set('activeTab', value);
    if (value !== 'bpjs') {
        url.searchParams.delete('bpjsTab');
        url.searchParams.delete('ermSubTab');
    }
    if (value !== 'satusehat') {
        url.searchParams.delete('ssFhirTab');
    }
    window.history.replaceState(null, '', url);
})">
    @if ($reg)
        {{-- Header --}}
        <x-ui.page-header title="Detail eRM"
            subtitle="{{ $reg->status_lanjut === 'Ranap' ? 'Rawat Inap' : ($reg->poliklinik ? 'Rawat Jalan — ' . $reg->poliklinik->nm_poli : 'Rawat Jalan') }}"
            backUrl="{{ url()->previous() }}" />

        {{-- Info Pasien: 4 Card --}}
        @php
            $firstRoom = $reg->kamarInap?->first();
            $lastRoom = $reg->kamarInap?->last();
        @endphp
        <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">

            {{-- Card 1: Data Pasien --}}
            <button @click="$flux.modal('pasien').show()"
                class="p-4 text-left bg-white rounded-xl border border-zinc-200/80 shadow-sm transition-all hover:shadow-md hover:bg-zinc-50/80 dark:bg-primary-dark-800 dark:border-primary-dark-700/60 dark:hover:bg-primary-dark-700/70 group">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-100 dark:bg-sky-900/30 mt-0.5">
                        <flux:icon name="user" class="h-4 w-4 text-sky-600 dark:text-sky-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                            Data Pasien</p>
                        <p class="mt-0.5 text-sm font-semibold truncate text-zinc-900 dark:text-primary-dark-100">
                            {{ $reg->pasien?->nm_pasien ?? '-' }}
                        </p>
                        <p class="text-xs font-mono text-zinc-500 dark:text-primary-dark-400">RM:
                            {{ $reg->no_rkm_medis }}
                        </p>
                    </div>
                    <flux:icon name="chevron-right"
                        class="h-3.5 w-3.5 shrink-0 text-zinc-300 dark:text-primary-dark-600 group-hover:text-zinc-400 transition-colors mt-1" />
                </div>
            </button>

            {{-- Card 2: Data Registrasi --}}
            <button @click="$flux.modal('registrasi').show()"
                class="p-4 text-left bg-white rounded-xl border border-zinc-200/80 shadow-sm transition-all hover:shadow-md hover:bg-zinc-50/80 dark:bg-primary-dark-800 dark:border-primary-dark-700/60 dark:hover:bg-primary-dark-700/70 group">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900/30 mt-0.5">
                        <flux:icon name="clipboard-document-list"
                            class="h-4 w-4 text-violet-600 dark:text-violet-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                            Data Registrasi</p>
                        <p
                            class="mt-0.5 font-mono text-sm font-semibold text-primary-600 dark:text-primary-400 truncate">
                            {{ $reg->no_rawat }}
                        </p>
                        @if ($reg->status_lanjut === 'Ranap')
                            <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                {{ $firstRoom?->tgl_masuk?->format('d/m/Y') ?? '-' }} &ndash;
                                {{ $lastRoom?->tgl_keluar?->format('d/m/Y') ?? 'Masih Dirawat' }}
                            </p>
                        @else
                            <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                {{ $reg->tgl_registrasi?->format('d M Y') }}
                                {{ $reg->jam_reg }}
                            </p>
                        @endif
                    </div>
                    <flux:icon name="chevron-right"
                        class="h-3.5 w-3.5 shrink-0 text-zinc-300 dark:text-primary-dark-600 group-hover:text-zinc-400 transition-colors mt-1" />
                </div>
            </button>

            {{-- Card 3: DPJP --}}
            <button @click="$flux.modal('dpjp').show()"
                class="p-4 text-left bg-white rounded-xl border border-zinc-200/80 shadow-sm transition-all hover:shadow-md hover:bg-zinc-50/80 dark:bg-primary-dark-800 dark:border-primary-dark-700/60 dark:hover:bg-primary-dark-700/70 group">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30 mt-0.5">
                        <flux:icon name="user-circle" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                            DPJP</p>
                        <p class="mt-0.5 text-sm font-semibold truncate text-zinc-900 dark:text-primary-dark-100">
                            {{ $reg->dokter?->nm_dokter ?? '-' }}
                        </p>
                        @if ($reg->status_lanjut === 'Ranap' && ($reg->dpjpRanap?->count() ?? 0) > 0)
                            <p class="text-xs text-zinc-500 dark:text-primary-dark-400">+{{ $reg->dpjpRanap->count() }}
                                DPJP Ranap</p>
                        @else
                            <p class="text-xs font-mono text-zinc-500 dark:text-primary-dark-400">
                                {{ $reg->dokter?->kd_dokter ?? '-' }}
                            </p>
                        @endif
                    </div>
                    <flux:icon name="chevron-right"
                        class="h-3.5 w-3.5 shrink-0 text-zinc-300 dark:text-primary-dark-600 group-hover:text-zinc-400 transition-colors mt-1" />
                </div>
            </button>

            {{-- Card 4: Status Bayar --}}
            <div
                class="p-4 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30 mt-0.5">
                        <flux:icon name="banknotes" class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                            Status Bayar</p>
                        <p class="mt-0.5 text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                            {{ $reg->status_bayar ?? '-' }}
                        </p>
                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                            {{ $reg->penjab?->png_jawab ?? '-' }}
                        </p>
                    </div>
                </div>
            </div>

        </div>

        {{-- Modal: Data Pasien Lengkap --}}
        <x-organisms.modal name="pasien" maxWidth="lg" title="Data Pasien Lengkap">

            <div class="mt-4 overflow-y-auto max-h-[65vh]">
                @if ($reg->pasien)
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div class="col-span-2">
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Nama Pasien</dt>
                            <dd class="font-semibold text-zinc-900 dark:text-primary-dark-100">
                                {{ $reg->pasien->nm_pasien }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">No. Rekam Medis</dt>
                            <dd class="font-mono font-medium text-zinc-900 dark:text-primary-dark-100">
                                {{ $reg->no_rkm_medis }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">NIK</dt>
                            <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                {{ $reg->pasien->no_ktp ?: '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Tgl. Lahir</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $reg->pasien->tgl_lahir?->format('d F Y') ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Jenis Kelamin / Umur</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $reg->pasien->gender_label }}{{ $reg->pasien->age ? ' / ' . $reg->pasien->age : '' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Gol. Darah</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $reg->pasien->gol_darah ?: '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Status Pernikahan</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $reg->pasien->stts_nikah ?: '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">No. Peserta BPJS</dt>
                            <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                                {{ $reg->pasien->no_peserta ?: '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">No. Telepon</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $reg->pasien->no_tlp ?: '-' }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Alamat</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $reg->pasien->alamat ?: '-' }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-zinc-400 italic">Data pasien tidak ditemukan.</p>
                @endif
            </div>

        </x-organisms.modal>

        {{-- Modal: Data Registrasi Lengkap --}}
        <x-organisms.modal name="registrasi" maxWidth="lg" title="Data Registrasi Lengkap">

            <div class="mt-4 overflow-y-auto max-h-[65vh]">
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">No. Rawat</dt>
                        <dd class="font-mono font-medium text-primary-600 dark:text-primary-400">
                            {{ $reg->no_rawat }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Jenis Bayar</dt>
                        <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $reg->penjab?->png_jawab ?? '-' }}</dd>
                    </div>
                    @if ($reg->status_lanjut === 'Ranap')
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Tanggal Masuk</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $firstRoom?->tgl_masuk?->format('d F Y') ?? '-' }}
                                {{ $firstRoom?->jam_masuk ? ' ' . $firstRoom->jam_masuk : '' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Tanggal Keluar</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $lastRoom?->tgl_keluar?->format('d F Y') ?? 'Masih Dirawat' }}
                                {{ $lastRoom?->tgl_keluar && $lastRoom->jam_keluar ? ' ' . $lastRoom->jam_keluar : '' }}
                            </dd>
                        </div>
                        @if ($reg->kamarInap?->isNotEmpty())
                            <div>
                                <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Kamar Terakhir</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $lastRoom?->kd_kamar ?? '-' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Bangsal</dt>
                                <dd class="text-zinc-900 dark:text-primary-dark-100">
                                    {{ $lastRoom?->kamar?->bangsal?->nm_bangsal ?? '-' }}
                                </dd>
                            </div>
                        @endif
                    @else
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Tanggal Registrasi</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $reg->tgl_registrasi?->format('d F Y') ?? '-' }} {{ $reg->jam_reg }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Poliklinik</dt>
                            <dd class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $reg->poliklinik?->nm_poli ?? '-' }}
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Status Periksa</dt>
                        <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $reg->status_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Status Daftar</dt>
                        <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $reg->stts_daftar ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Status Bayar</dt>
                        <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $reg->status_bayar ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">Penanggung Jawab</dt>
                        <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $reg->p_jawab ?? '-' }}</dd>
                    </div>
                    @if ($reg->bridgingSep)
                        <div class="col-span-2">
                            <dt class="text-xs text-zinc-400 dark:text-primary-dark-500">No. SEP</dt>
                            <dd class="font-mono font-medium text-zinc-900 dark:text-primary-dark-100">
                                {{ $reg->bridgingSep->no_sep }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

        </x-organisms.modal>

        {{-- Modal: Data DPJP --}}
        <x-organisms.modal name="dpjp" maxWidth="lg" title="Data DPJP">

            <div class="mt-4 overflow-y-auto max-h-[65vh]">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                    <thead class="bg-zinc-50 dark:bg-primary-dark-900 sticky top-0">
                        <tr>
                            <th
                                class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Kode Dokter</th>
                            <th
                                class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Nama Dokter</th>
                            <th
                                class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Keterangan</th>
                        </tr>
                    </thead>
                    <tbody
                        class="bg-white divide-y divide-zinc-100 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                        {{-- Dokter Pemeriksa (dari reg_periksa) --}}
                        @if ($reg->dokter)
                            <tr>
                                <td class="px-4 py-3 font-mono text-sm text-zinc-700 dark:text-primary-dark-300">
                                    {{ $reg->dokter->kd_dokter }}
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                    {{ $reg->dokter->nm_dokter }}
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge color="sky" size="sm">
                                        {{ $reg->status_lanjut === 'Ranap' ? 'Dokter Pengirim' : 'Dokter Pemeriksa' }}
                                    </flux:badge>
                                </td>
                            </tr>
                        @endif
                        {{-- DPJP Ranap (hanya jika Ranap) --}}
                        @if ($reg->status_lanjut === 'Ranap')
                            @forelse ($reg->dpjpRanap ?? [] as $dpjp)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-sm text-zinc-700 dark:text-primary-dark-300">
                                        {{ $dpjp->dokter?->kd_dokter ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                        {{ $dpjp->dokter?->nm_dokter ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <flux:badge color="amber" size="sm">DPJP Ranap</flux:badge>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-6 text-sm text-center text-zinc-400">Tidak ada
                                        DPJP Ranap tercatat.</td>
                                </tr>
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </div>

        </x-organisms.modal>

        {{-- Outer Tab Navigasi --}}
        <x-molecules.tabs>
            {{-- Tab: Data Kunjungan --}}
            <x-atoms.tab-item :active="false"
                x-bind:class="activeTab === 'admission' ? '!border-primary-500 !text-primary-600 dark:!text-primary-400' : ''"
                @click="activeTab = 'admission'">
                Data Kunjungan
            </x-atoms.tab-item>

            {{-- Tab: Status BPJS Kesehatan --}}
            <x-atoms.tab-item :active="false"
                x-bind:class="activeTab === 'bpjs' ? '!border-primary-500 !text-primary-600 dark:!text-primary-400' : ''"
                @click="activeTab = 'bpjs'" class="flex items-center gap-2">
                Status BPJS Kesehatan
                @if ($reg->bridgingSep)
                    <flux:badge color="green" size="sm">Sudah SEP</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">Belum SEP</flux:badge>
                @endif
            </x-atoms.tab-item>

            {{-- Tab: Status Satu Sehat --}}
            <x-atoms.tab-item :active="false"
                x-bind:class="activeTab === 'satusehat' ? '!border-primary-500 !text-primary-600 dark:!text-primary-400' : ''"
                @click="activeTab = 'satusehat'" class="flex items-center gap-2">
                Status Satu Sehat
                @if ($ssEncounter?->status === 'finished')
                    <flux:badge color="green" size="sm">Selesai</flux:badge>
                @elseif ($ssEncounter?->status === 'in-progress')
                    <flux:badge color="blue" size="sm">Dalam Proses</flux:badge>
                @elseif ($ssEncounter !== null)
                    <flux:badge color="yellow" size="sm">{{ $ssEncounter->status }}</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">Belum</flux:badge>
                @endif
            </x-atoms.tab-item>
        </x-molecules.tabs>

        {{-- Tab Active Content --}}
        <div x-show="activeTab === 'admission'" x-cloak>
            @include('pages.erm.detail-tabs.admission')
        </div>
        <div x-show="activeTab === 'bpjs'" x-cloak>
            <livewire:pages::erm.detail-tabs.bpjs key="tab-bpjs" :$reg :totalTindakanERM="$tindakanJalanDr->count() +
                $tindakanJalanPr->count() +
                $tindakanJalanDrPr->count() +
                $tindakanInapDr->count() +
                $tindakanInapPr->count() +
                $tindakanInapDrPr->count()" :totalObatERM="$obats->count() + $resepPulangs->count()"
                :totalLabERM="$periksaLabsPk->count() + $periksaLabsMb->count()" :totalRadERM="$periksaRadiologis->count()" :totalVsERM="$pemeriksaans->count()" :totalDiagnosaERM="$diagnosas->count()" :totalProsedurERM="$prosedurs->count()" lazy />
        </div>
        <div x-show="activeTab === 'satusehat'" x-cloak>
            <livewire:pages::erm.detail-tabs.satusehat key="tab-satusehat" :$reg :$ssEncounter lazy />
        </div>
    @else
        {{-- Halaman kosong jika tidak ada no_rawat --}}
        <div class="flex flex-col items-center justify-center py-24">
            <flux:icon name="document-magnifying-glass" class="w-16 h-16 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-3 text-sm text-zinc-500 dark:text-primary-dark-400">Pilih kunjungan dari halaman daftar eRM.
            </p>
            <div class="flex gap-2 mt-4">
                <x-atoms.button href="{{ route('erm.rawat-jalan') }}" navigate size="sm" variant="ghost">
                    Rawat Jalan
                </x-atoms.button>
                <x-atoms.button href="{{ route('erm.igd') }}" navigate size="sm" variant="ghost">
                    IGD
                </x-atoms.button>
                <x-atoms.button href="{{ route('erm.rawat-inap') }}" navigate size="sm" variant="ghost">
                    Rawat Inap
                </x-atoms.button>
            </div>
        </div>
    @endif
</div>
