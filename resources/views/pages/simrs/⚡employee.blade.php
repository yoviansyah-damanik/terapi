<?php

use App\Models\Simrs\Dokter;
use App\Models\Simrs\Pegawai;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Master Data — Pegawai')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'dokter';

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public int $perPage = 25;

    private const BIDANG_KEPERAWATAN = ['Keperawatan', 'Kebidanan'];
    private const BIDANG_PENUNJANG = ['Penunjang Medis'];
    private const BIDANG_MEDIS = ['Medis'];

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        $this->search = '';
        $this->filterStatus = '';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        try {
            [$items, $stats] = match ($this->tab) {
                'keperawatan' => $this->queryPegawai(self::BIDANG_KEPERAWATAN, whereIn: true),
                'penunjang' => $this->queryPegawai(self::BIDANG_PENUNJANG, whereIn: true),
                'non-medis' => $this->queryNonMedis(),
                default => $this->queryDokter(),
            };

            return compact('items', 'stats');
        } catch (\Throwable $e) {
            return ['simrsError' => $e->getMessage(), 'items' => collect(), 'stats' => []];
        }
    }

    private function queryDokter(): array
    {
        $query = Dokter::query()
            ->when($this->search, fn($q) => $q->search($this->search))
            ->when($this->filterStatus !== '', fn($q) => $q->where('status', $this->filterStatus))
            ->orderBy('nm_dokter');

        $items = $query->paginate($this->perPage);
        $stats = [
            'total' => Dokter::count(),
            'aktif' => Dokter::active()->count(),
        ];

        return [$items, $stats];
    }

    private function queryPegawai(array $bidang, bool $whereIn = true): array
    {
        $base = Pegawai::whereIn('bidang', $bidang);

        $query = Pegawai::whereIn('bidang', $bidang)
            ->when($this->search, fn($q) => $q->search($this->search))
            ->when($this->filterStatus !== '', fn($q) => $q->where('stts_aktif', $this->filterStatus))
            ->orderBy('nama');

        $items = $query->paginate($this->perPage);
        $stats = [
            'total' => $base->count(),
            'aktif' => (clone $base)->where('stts_aktif', 'AKTIF')->count(),
        ];

        return [$items, $stats];
    }

    private function queryNonMedis(): array
    {
        $excluded = array_merge(self::BIDANG_MEDIS, self::BIDANG_KEPERAWATAN, self::BIDANG_PENUNJANG);
        $base = Pegawai::whereNotIn('bidang', $excluded);

        $query = Pegawai::whereNotIn('bidang', $excluded)
            ->when($this->search, fn($q) => $q->search($this->search))
            ->when($this->filterStatus !== '', fn($q) => $q->where('stts_aktif', $this->filterStatus))
            ->orderBy('nama');

        $items = $query->paginate($this->perPage);
        $stats = [
            'total' => $base->count(),
            'aktif' => (clone $base)->where('stts_aktif', 'AKTIF')->count(),
        ];

        return [$items, $stats];
    }
};
?>

<div>
    <x-ui.page-header title="Pegawai" subtitle="Data pegawai dari SIMRS" />

    @if (isset($simrsError))
        <x-ui.empty-state icon="exclamation-triangle" title="Koneksi SIMRS Gagal" :description="$simrsError" />
    @else
        {{-- Stats --}}
        <div class="mb-5 grid grid-cols-2 gap-3">
            <x-organisms.stat-card title="Total" :value="number_format($stats['total'] ?? 0)" icon="users" color="zinc" />
            <x-organisms.stat-card title="Aktif" :value="number_format($stats['aktif'] ?? 0)" icon="check-circle" color="emerald" />
        </div>

        {{-- Tab navigation --}}
        <x-molecules.tabs>
            @foreach ([
        'dokter' => 'Dokter',
        'keperawatan' => 'Keperawatan / Kebidanan',
        'penunjang' => 'Penunjang Medis',
        'non-medis' => 'Non Medis',
    ] as $key => $label)
                <x-atoms.tab-item wire:click="switchTab('{{ $key }}')" :active="$tab === $key">
                    {{ $label }}
                </x-atoms.tab-item>
            @endforeach
        </x-molecules.tabs>

        <x-organisms.data-panel>
            <x-slot:filter>
                <div class="flex gap-3">
                    <flux:input class='flex-1' wire:model.live.debounce.300ms="search"
                        placeholder="{{ $tab === 'dokter' ? 'Cari kode atau nama dokter...' : 'Cari ID, NIK, atau nama...' }}"
                        icon="magnifying-glass" />
                    @if ($tab !== 'dokter')
                        <flux:select wire:model.live="filterStatus" class="!w-40">
                            <flux:select.option value="">Semua Status</flux:select.option>
                            <flux:select.option value="AKTIF">Aktif</flux:select.option>
                            <flux:select.option value="CUTI">Cuti</flux:select.option>
                            <flux:select.option value="KELUAR">Keluar</flux:select.option>
                            <flux:select.option value="PENSIUN">Pensiun</flux:select.option>
                        </flux:select>
                    @else
                        <flux:select wire:model.live="filterStatus" class="!w-40">
                            <flux:select.option value="">Semua Status</flux:select.option>
                            <flux:select.option value="1">Aktif</flux:select.option>
                            <flux:select.option value="0">Non-Aktif</flux:select.option>
                        </flux:select>
                    @endif
                    <flux:select wire:model.live="perPage" class="!w-36">
                        <flux:select.option value="25">25 / halaman</flux:select.option>
                        <flux:select.option value="50">50 / halaman</flux:select.option>
                        <flux:select.option value="100">100 / halaman</flux:select.option>
                    </flux:select>
                </div>
            </x-slot:filter>

            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="w-10">#</x-atoms.table-heading>
                    @if ($tab === 'dokter')
                        <x-atoms.table-heading class="w-32">Kode</x-atoms.table-heading>
                        <x-atoms.table-heading>Nama Dokter</x-atoms.table-heading>
                        <x-atoms.table-heading class="hidden md:table-cell">Spesialis</x-atoms.table-heading>
                    @else
                        <x-atoms.table-heading class="w-40">ID / NIK</x-atoms.table-heading>
                        <x-atoms.table-heading>Nama</x-atoms.table-heading>
                        <x-atoms.table-heading class="hidden md:table-cell">Bidang</x-atoms.table-heading>
                        <x-atoms.table-heading class="hidden lg:table-cell">Jabatan</x-atoms.table-heading>
                    @endif
                    <x-atoms.table-heading align="center" class="w-28">Status</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($items as $item)
                    <x-molecules.table-row wire:key="emp-{{ $item->getKey() }}">
                        <x-atoms.table-cell>
                            <span class="text-zinc-400">{{ $items->firstItem() + $loop->index }}</span>
                        </x-atoms.table-cell>

                        @if ($tab === 'dokter')
                            <x-atoms.table-cell nowrap>
                                <span class="font-mono text-sm font-medium text-zinc-700 dark:text-primary-dark-300">
                                    {{ $item->kd_dokter }}
                                </span>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell>
                                <span class="font-medium">{{ $item->nm_dokter }}</span>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="hidden md:table-cell text-zinc-500 dark:text-primary-dark-400">
                                {{ $item->kd_sps ?? '-' }}
                            </x-atoms.table-cell>
                        @else
                            <x-atoms.table-cell nowrap>
                                <div class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">
                                    {{ $item->id }}
                                </div>
                                @if ($item->nik)
                                    <div class="text-xs text-zinc-400">NIK: {{ $item->nik }}</div>
                                @endif
                            </x-atoms.table-cell>
                            <x-atoms.table-cell>
                                <span class="font-medium">{{ $item->nama }}</span>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="hidden md:table-cell text-zinc-500 dark:text-primary-dark-400">
                                {{ $item->bidang ?? '-' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="hidden lg:table-cell text-zinc-500 dark:text-primary-dark-400">
                                {{ $item->jbtn ?? '-' }}
                            </x-atoms.table-cell>
                        @endif

                        <x-atoms.table-cell align="center">
                            @if ($tab === 'dokter')
                                <flux:badge :color="$item->status == '1' ? 'green' : 'zinc'" size="sm">
                                    {{ $item->status == '1' ? 'Aktif' : 'Non-Aktif' }}
                                </flux:badge>
                            @else
                                <flux:badge
                                    :color="$item->stts_aktif === 'AKTIF' ? 'green' : ($item->stts_aktif === 'CUTI' ? 'yellow' : 'zinc')"
                                    size="sm">
                                    {{ $item->status_aktif_label }}
                                </flux:badge>
                            @endif
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="6">
                            <x-ui.empty-state icon="users" title="Tidak ada data"
                                description="Tidak ada pegawai yang sesuai filter" />
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse
            </x-organisms.table>

            <x-slot:footer>
                @if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator && $items->hasPages())
                    {{ $items->links() }}
                @endif
            </x-slot:footer>
        </x-organisms.data-panel>
    @endif
</div>
