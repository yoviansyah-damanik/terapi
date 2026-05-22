<?php

use App\Models\Simrs\KategoriPenyakit;
use App\Models\Simrs\Penyakit;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Master Data — ICD-10')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $filterKategori = '';

    #[Url]
    public int $perPage = 25;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }
    public function updatedFilterKategori(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        try {
            $items = Penyakit::with('kategori')
                ->when(
                    $this->search,
                    fn($q) => $q
                        ->where('kd_penyakit', 'like', "%{$this->search}%")
                        ->orWhere('nm_penyakit', 'like', "%{$this->search}%")
                        ->orWhereHas('kategori', fn($k) => $k->where('nm_kategori', 'like', "%{$this->search}%")),
                )
                ->when($this->filterStatus !== '', fn($q) => $q->where('status', $this->filterStatus))
                ->when($this->filterKategori !== '', fn($q) => $q->where('kd_ktg', $this->filterKategori))
                ->orderBy('kd_penyakit')
                ->paginate($this->perPage);

            $kategoris = KategoriPenyakit::orderBy('nm_kategori')->get();

            return [
                'items' => $items,
                'kategoris' => $kategoris,
                'total' => Penyakit::count(),
                'menular' => Penyakit::where('status', 'Menular')->count(),
            ];
        } catch (\Throwable $e) {
            return ['simrsError' => $e->getMessage(), 'items' => collect(), 'kategoris' => collect(), 'total' => 0, 'menular' => 0];
        }
    }
};
?>

<div>
    <x-ui.page-header title="ICD-10" subtitle="Data ICD-10 (diagnosa penyakit) dari SIMRS" />

    {{-- Stats --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <x-organisms.stat-card title="Total Kode" :value="number_format($total)" icon="document-text" color="zinc" />
        <x-organisms.stat-card title="Menular" :value="number_format($menular)" icon="exclamation-triangle" color="red" />
        <x-organisms.stat-card title="Tidak Menular" :value="number_format($total - $menular)" icon="check-circle" color="zinc" />
    </div>

    @if (isset($simrsError))
        <x-ui.empty-state icon="exclamation-triangle" title="Koneksi SIMRS Gagal" :description="$simrsError" />
    @else
        <x-organisms.data-panel>
            <x-slot:filter>
                <div class="flex-1 min-w-48">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari kode, nama, atau kategori..."
                        icon="magnifying-glass" />
                </div>
                <flux:select wire:model.live="filterKategori" class="w-48">
                    <flux:select.option value="">Semua Kategori</flux:select.option>
                    @foreach ($kategoris as $ktg)
                        <flux:select.option value="{{ $ktg->kd_ktg }}">{{ $ktg->nm_kategori }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterStatus" class="w-44">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="Menular">Menular</flux:select.option>
                    <flux:select.option value="Tidak Menular">Tidak Menular</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="perPage" class="w-36">
                    <flux:select.option value="25">25 / halaman</flux:select.option>
                    <flux:select.option value="50">50 / halaman</flux:select.option>
                    <flux:select.option value="100">100 / halaman</flux:select.option>
                </flux:select>
            </x-slot:filter>

            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="w-10">#</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-28">Kode</x-atoms.table-heading>
                    <x-atoms.table-heading>Nama Penyakit</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden lg:table-cell">Kategori</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden xl:table-cell">Ciri Umum</x-atoms.table-heading>
                    <x-atoms.table-heading align="center" class="w-32">Status</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($items as $item)
                    <x-molecules.table-row wire:key="icd10-{{ $item->kd_penyakit }}">
                        <x-atoms.table-cell>
                            <span class="text-zinc-400">{{ $items->firstItem() + $loop->index }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell nowrap>
                            <flux:badge size="sm" color="zinc" class="font-mono font-bold" inset="top bottom">
                                {{ $item->kd_penyakit }}
                            </flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            {{ $item->nm_penyakit }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden lg:table-cell text-zinc-600 dark:text-primary-dark-400">
                            {{ $item->kategori?->nm_kategori ?? '-' }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden xl:table-cell max-w-xs truncate text-zinc-500 dark:text-primary-dark-400"
                            title="{{ $item->kategori?->ciri_umum }}">
                            {{ $item->kategori?->ciri_umum ? \Illuminate\Support\Str::limit($item->kategori->ciri_umum, 60) : '-' }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center">
                            <flux:badge :color="$item->status === 'Menular' ? 'red' : 'zinc'" size="sm">
                                {{ $item->status ?? '-' }}
                            </flux:badge>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="6">
                            <x-ui.empty-state icon="document-magnifying-glass" title="Tidak ada data"
                                description="Tidak ada kode ICD-10 yang sesuai pencarian" />
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
