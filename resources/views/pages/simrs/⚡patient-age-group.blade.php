<?php

use App\Jobs\HitungKelompokUmurJob;
use App\Models\Simrs\KelompokUmur;
use App\Models\Simrs\KelompokUmurPasien;
use App\Models\Simrs\Pasien;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Pasien & Kelompok Umur')] class extends Component {
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $filterKelompok = '';
    #[Url] public string $filterHitung = '';
    #[Url] public int $perPage = 25;

    public bool $showMasterModal = false;

    public string $formKode = '';
    public string $formNama = '';
    public string $formUmurMin = '';
    public string $formUmurMax = '';
    public string $formUrut = '';
    public ?string $editingKode = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterKelompok(): void { $this->resetPage(); }
    public function updatedFilterHitung(): void { $this->resetPage(); }

    // ── Master Kelompok Umur CRUD ─────────────────────────────────────────────

    public function openMasterModal(): void
    {
        $this->resetKelompokUmurForm();
        $this->showMasterModal = true;
    }

    public function editKelompokUmur(string $kode): void
    {
        $item = KelompokUmur::find($kode);
        if (!$item) return;
        $this->editingKode  = $kode;
        $this->formKode     = $item->kode;
        $this->formNama     = $item->nama;
        $this->formUmurMin  = (string) $item->umur_min;
        $this->formUmurMax  = $item->umur_max !== null ? (string) $item->umur_max : '';
        $this->formUrut     = (string) $item->urut;
    }

    public function saveKelompokUmur(): void
    {
        $this->validate([
            'formKode'    => 'required|string|max:5',
            'formNama'    => 'required|string|max:100',
            'formUmurMin' => 'required|integer|min:0',
            'formUmurMax' => 'nullable|integer|min:0',
            'formUrut'    => 'required|integer|min:1',
        ], [
            'formKode.required'    => 'Kode wajib diisi',
            'formKode.max'         => 'Kode maksimal 5 karakter',
            'formNama.required'    => 'Nama wajib diisi',
            'formUmurMin.required' => 'Umur minimum wajib diisi',
            'formUrut.required'    => 'Urut wajib diisi',
        ]);

        $data = [
            'nama'      => $this->formNama,
            'umur_min'  => (int) $this->formUmurMin,
            'umur_max'  => $this->formUmurMax !== '' ? (int) $this->formUmurMax : null,
            'urut'      => (int) $this->formUrut,
        ];

        if ($this->editingKode) {
            KelompokUmur::where('kode', $this->editingKode)->update($data);
            $this->toastSuccess("Kelompok umur '{$this->formNama}' berhasil diperbarui.");
        } else {
            if (KelompokUmur::where('kode', $this->formKode)->exists()) {
                $this->addError('formKode', "Kode '{$this->formKode}' sudah digunakan.");
                return;
            }
            KelompokUmur::create(array_merge(['kode' => strtoupper($this->formKode)], $data));
            $this->toastSuccess("Kelompok umur '{$this->formNama}' berhasil ditambahkan.");
        }

        $this->resetKelompokUmurForm();
    }

    public function deleteKelompokUmur(string $kode): void
    {
        $used = KelompokUmurPasien::where('kode_kelompok_umur', $kode)->exists();
        if ($used) {
            $this->toastError("Kelompok umur '{$kode}' sedang digunakan oleh data pasien.");
            return;
        }
        KelompokUmur::where('kode', $kode)->delete();
        $this->toastSuccess("Kelompok umur '{$kode}' berhasil dihapus.");
        if ($this->editingKode === $kode) {
            $this->resetKelompokUmurForm();
        }
    }

    public function resetKelompokUmurForm(): void
    {
        $this->editingKode = null;
        $this->formKode    = '';
        $this->formNama    = '';
        $this->formUmurMin = '';
        $this->formUmurMax = '';
        $this->formUrut    = '';
        $this->resetValidation();
    }

    // ── Perhitungan Kelompok Umur ─────────────────────────────────────────────

    public function hitungKelompokUmur(string $noRkmMedis): void
    {
        $pasien = Pasien::find($noRkmMedis);
        if (!$pasien || !$pasien->tgl_lahir) {
            $this->toastError('Data tanggal lahir pasien tidak tersedia.');
            return;
        }

        $umurHari  = Carbon::parse($pasien->tgl_lahir)->diffInDays(today());
        $kelompok  = KelompokUmur::findByDays($umurHari);

        if (!$kelompok) {
            $this->toastError('Tidak ada kelompok umur yang sesuai. Periksa master kelompok umur.');
            return;
        }

        KelompokUmurPasien::updateOrCreate(
            ['no_rkm_medis' => $noRkmMedis],
            [
                'kode_kelompok_umur' => $kelompok->kode,
                'umur_hari'          => $umurHari,
                'tanggal_hitung'     => today(),
            ]
        );

        $this->toastSuccess("Kelompok umur {$pasien->nm_pasien} → {$kelompok->nama}.");
    }

    public function hitungSemuaPasien(): void
    {
        HitungKelompokUmurJob::dispatch();
        $this->toastSuccess('Proses hitung kelompok umur seluruh pasien dijadwalkan di background.');
    }

    // ── Data ─────────────────────────────────────────────────────────────────

    private function buildQuery()
    {
        return Pasien::query()
            ->leftJoin('kelompok_umur_pasien as kup', 'pasien.no_rkm_medis', '=', 'kup.no_rkm_medis')
            ->leftJoin('kelompok_umur as ku', 'kup.kode_kelompok_umur', '=', 'ku.kode')
            ->select([
                'pasien.no_rkm_medis',
                'pasien.nm_pasien',
                'pasien.jk',
                'pasien.tgl_lahir',
                'pasien.no_ktp',
                'kup.kode_kelompok_umur',
                'kup.umur_hari',
                'kup.tanggal_hitung',
                'ku.nama as nama_kelompok_umur',
                'ku.urut as urut_kelompok_umur',
            ])
            ->when($this->search, fn($q) => $q->where(fn($sq) =>
                $sq->where('pasien.nm_pasien', 'like', "%{$this->search}%")
                   ->orWhere('pasien.no_rkm_medis', 'like', "%{$this->search}%")
                   ->orWhere('pasien.no_ktp', 'like', "%{$this->search}%")
            ))
            ->when($this->filterKelompok, fn($q) => $q->where('kup.kode_kelompok_umur', $this->filterKelompok))
            ->when($this->filterHitung === 'sudah', fn($q) => $q->whereNotNull('kup.kode_kelompok_umur'))
            ->when($this->filterHitung === 'belum', fn($q) => $q->whereNull('kup.kode_kelompok_umur'))
            ->orderBy('ku.urut')
            ->orderBy('pasien.nm_pasien');
    }

    public function with(): array
    {
        $kelompoks   = Cache::remember('simrs_kelompok_umur', 3600, fn() => KelompokUmur::orderBy('urut')->get());
        $totalPasien = Cache::remember('simrs_total_pasien', 300, fn() => Pasien::count());
        $totalSudah  = Cache::remember('simrs_kelompok_umur_pasien_count', 300, fn() => KelompokUmurPasien::count());
        $totalBelum  = max(0, $totalPasien - $totalSudah);

        return [
            'patients'    => $this->buildQuery()->paginate($this->perPage),
            'kelompoks'   => $kelompoks,
            'totalPasien' => $totalPasien,
            'totalSudah'  => $totalSudah,
            'totalBelum'  => $totalBelum,
        ];
    }
};
?>

<div>
    <x-ui.page-header title="Pasien & Kelompok Umur"
        subtitle="Pengelompokan pasien berdasarkan rentang usia sesuai master kelompok umur">
        <x-slot:actions>
            <x-atoms.button variant="outline" icon="table-cells" wire:click="openMasterModal">
                Master Kelompok Umur
            </x-atoms.button>
            <x-atoms.button variant="primary" icon="calculator" wire:click="hitungSemuaPasien">
                Hitung Semua Pasien
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-organisms.stat-card title="Total Pasien"  :value="number_format($totalPasien)" icon="users"          color="zinc" />
        <x-organisms.stat-card title="Sudah Dihitung" :value="number_format($totalSudah)"  icon="check-circle"   color="emerald"
            :subtitle="number_format(round($totalPasien > 0 ? ($totalSudah / $totalPasien) * 100 : 0)) . '% dari total'" />
        <x-organisms.stat-card title="Belum Dihitung" :value="number_format($totalBelum)"  icon="clock"          color="amber" />
    </div>

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <div class="flex-1 min-w-[200px]">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari no. RM, nama, NIK..." clearable />
                </div>
                <flux:select wire:model.live="filterKelompok" class="sm:w-52">
                    <flux:select.option value="">Semua Kelompok Umur</flux:select.option>
                    @foreach ($kelompoks as $k)
                        <flux:select.option value="{{ $k->kode }}">{{ $k->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterHitung" class="sm:w-44">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="sudah">Sudah Dihitung</flux:select.option>
                    <flux:select.option value="belum">Belum Dihitung</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="perPage" class="w-20">
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                </flux:select>
            </div>
        </x-slot:filter>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        @foreach (['No. RM', 'Nama Pasien', 'JK', 'Tgl Lahir', 'Umur (Hari)', 'Kelompok Umur', 'Tgl Hitung', 'Aksi'] as $h)
                            <th class="px-4 py-3 text-xs font-semibold tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400 whitespace-nowrap">
                                {{ $h }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-zinc-100 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                    @forelse ($patients as $p)
                        @php
                            $umurHariCalc = $p->tgl_lahir ? Carbon::parse($p->tgl_lahir)->diffInDays(today()) : null;
                            $isOutdated   = $p->tanggal_hitung && Carbon::parse($p->tanggal_hitung)->lt(today());
                        @endphp
                        <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-200 whitespace-nowrap">
                                {{ $p->no_rkm_medis }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ $p->nm_pasien }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" color="{{ $p->jk === 'L' ? 'blue' : 'pink' }}">
                                    {{ $p->jk === 'L' ? 'L' : 'P' }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-600 dark:text-primary-dark-300 whitespace-nowrap">
                                {{ $p->tgl_lahir ? Carbon::parse($p->tgl_lahir)->format('d/m/Y') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right text-zinc-700 dark:text-primary-dark-200 whitespace-nowrap">
                                {{ $umurHariCalc !== null ? number_format($umurHariCalc) : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($p->kode_kelompok_umur)
                                    <div class="flex items-center gap-2">
                                        <flux:badge size="sm" color="{{ $isOutdated ? 'amber' : 'emerald' }}">
                                            {{ $p->kode_kelompok_umur }}
                                        </flux:badge>
                                        <span class="text-xs text-zinc-600 dark:text-primary-dark-300">{{ $p->nama_kelompok_umur }}</span>
                                    </div>
                                @else
                                    <span class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum dihitung</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-400 dark:text-primary-dark-500 whitespace-nowrap">
                                @if ($p->tanggal_hitung)
                                    <span class="{{ $isOutdated ? 'text-amber-500 dark:text-amber-400' : '' }}">
                                        {{ Carbon::parse($p->tanggal_hitung)->format('d/m/Y') }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <button type="button" wire:click="hitungKelompokUmur('{{ $p->no_rkm_medis }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="hitungKelompokUmur('{{ $p->no_rkm_medis }}')"
                                    class="p-1.5 rounded-lg text-zinc-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:text-primary-400 dark:hover:bg-primary-900/20 transition-colors"
                                    title="Hitung ulang kelompok umur">
                                    <flux:icon name="arrow-path"
                                        class="w-4 h-4 {{ $isOutdated ? 'text-amber-400' : '' }}" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center">
                                <flux:icon name="users" class="w-10 h-10 mx-auto text-zinc-300 dark:text-primary-dark-600" />
                                <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data pasien ditemukan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-zinc-100 dark:border-primary-dark-700">
            {{ $patients->links() }}
        </div>
    </x-organisms.data-panel>

    {{-- Modal Master Kelompok Umur --}}
    <x-organisms.modal wire:model="showMasterModal" maxWidth="2xl" title="">
        <div class="space-y-5">
            <div class="flex items-center gap-3 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-primary-100 dark:bg-primary-900/40 shrink-0">
                    <flux:icon name="table-cells" class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-primary-dark-100">Master Kelompok Umur</h2>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Rentang umur dalam satuan hari</p>
                </div>
            </div>

            {{-- Form Input --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-900/40 space-y-3">
                <p class="text-xs font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500">
                    {{ $editingKode ? 'Edit: ' . $editingKode : 'Input Kelompok Umur' }}
                </p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div>
                        <flux:label class="text-xs">Kode (maks 5)</flux:label>
                        <flux:input wire:model="formKode" placeholder="NEO" class="mt-1"
                            :readonly="(bool)$editingKode" maxlength="5" />
                        @error('formKode') <p class="mt-0.5 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="col-span-2">
                        <flux:label class="text-xs">Nama</flux:label>
                        <flux:input wire:model="formNama" placeholder="Neonatus" class="mt-1" />
                        @error('formNama') <p class="mt-0.5 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:label class="text-xs">Umur Min (hari)</flux:label>
                        <flux:input wire:model="formUmurMin" type="number" min="0" placeholder="0" class="mt-1" />
                        @error('formUmurMin') <p class="mt-0.5 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:label class="text-xs">Umur Max (hari, kosong=∞)</flux:label>
                        <flux:input wire:model="formUmurMax" type="number" min="0" placeholder="∞" class="mt-1" />
                        @error('formUmurMax') <p class="mt-0.5 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="w-24">
                    <flux:label class="text-xs">Urut</flux:label>
                    <flux:input wire:model="formUrut" type="number" min="1" placeholder="1" class="mt-1" />
                    @error('formUrut') <p class="mt-0.5 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap gap-2 pt-1">
                    <x-atoms.button wire:click="saveKelompokUmur" variant="primary" size="sm" icon="check">
                        {{ $editingKode ? 'Perbarui' : 'Simpan' }}
                    </x-atoms.button>
                    @if ($editingKode)
                        <x-atoms.button wire:click="deleteKelompokUmur('{{ $editingKode }}')"
                            wire:confirm="Hapus kelompok umur '{{ $editingKode }}'?" variant="danger" size="sm" icon="trash">
                            Hapus
                        </x-atoms.button>
                    @endif
                    <x-atoms.button wire:click="resetKelompokUmurForm" variant="ghost" size="sm">Batal</x-atoms.button>
                </div>
            </div>

            {{-- Tabel Kelompok Umur --}}
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                    <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                        <tr>
                            @foreach (['Kode', 'Nama', 'Umur Min', 'Umur Max', 'Urut', ''] as $h)
                                <th class="px-3 py-2 text-xs font-semibold tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                    {{ $h }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-zinc-100 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                        @forelse ($kelompoks as $k)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50 {{ $editingKode === $k->kode ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}">
                                <td class="px-3 py-2 font-mono text-xs font-bold text-zinc-800 dark:text-primary-dark-100">{{ $k->kode }}</td>
                                <td class="px-3 py-2 text-sm text-zinc-700 dark:text-primary-dark-200">{{ $k->nama }}</td>
                                <td class="px-3 py-2 text-xs text-right text-zinc-600 dark:text-primary-dark-300">{{ number_format($k->umur_min) }}</td>
                                <td class="px-3 py-2 text-xs text-right text-zinc-600 dark:text-primary-dark-300">
                                    {{ $k->umur_max !== null ? number_format($k->umur_max) : '∞' }}
                                </td>
                                <td class="px-3 py-2 text-xs text-center text-zinc-500 dark:text-primary-dark-400">{{ $k->urut }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" wire:click="editKelompokUmur('{{ $k->kode }}')"
                                        class="p-1 rounded text-zinc-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:text-primary-400 dark:hover:bg-primary-900/20 transition-colors"
                                        title="Edit">
                                        <flux:icon name="pencil-square" class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-xs text-zinc-400 dark:text-primary-dark-500">
                                    Belum ada data kelompok umur.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            
        <x-slot:footer>
            <div class="flex justify-end pt-2">
                <x-atoms.button wire:click="$set('showMasterModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>
</div>
