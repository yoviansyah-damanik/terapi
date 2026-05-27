<?php

use App\Models\Simrs\Poliklinik;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Master Data — Poliklinik / Unit')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

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

    public function with(): array
    {
        try {
            $query = Poliklinik::query()
                ->when($this->search, fn($q) => $q->search($this->search))
                ->when($this->filterStatus !== '', fn($q) => $q->where('status', $this->filterStatus))
                ->orderBy('nm_poli');

            $items = $query->paginate($this->perPage);
            $total = Poliklinik::count();
            $aktif = Poliklinik::where('status', '1')->count();

            return compact('items', 'total', 'aktif');
        } catch (\Throwable $e) {
            return ['simrsError' => $e->getMessage(), 'items' => collect(), 'total' => 0, 'aktif' => 0];
        }
    }
};
?>

<div>
    <x-ui.page-header title="Poliklinik / Unit" subtitle="Data poliklinik dan unit pelayanan dari SIMRS" />

    {{-- Stats --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <x-organisms.stat-card title="Total" :value="number_format($total)" icon="building-office-2" color="zinc" />
        <x-organisms.stat-card title="Aktif" :value="number_format($aktif)" icon="check-circle" color="emerald" />
        <x-organisms.stat-card title="Non-Aktif" :value="number_format($total - $aktif)" icon="x-circle" color="zinc" />
    </div>

    @if (isset($simrsError))
        <x-ui.empty-state icon="exclamation-triangle" title="Koneksi SIMRS Gagal" :description="$simrsError" />
    @else
        <x-organisms.data-panel>
            <x-slot:filter>
                <div class="flex gap-3">
                    <flux:input class="flex-1" wire:model.live.debounce.300ms="search"
                        placeholder="Cari kode atau nama poli..." icon="magnifying-glass" />
                    <flux:select wire:model.live="filterStatus" class="!w-40">
                        <flux:select.option value="">Semua Status</flux:select.option>
                        <flux:select.option value="1">Aktif</flux:select.option>
                        <flux:select.option value="0">Non-Aktif</flux:select.option>
                    </flux:select>
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
                    <x-atoms.table-heading class="w-32">Kode</x-atoms.table-heading>
                    <x-atoms.table-heading>Nama Poli / Unit</x-atoms.table-heading>
                    <x-atoms.table-heading align="center" class="w-28">Registrasi</x-atoms.table-heading>
                    <x-atoms.table-heading align="center" class="w-28">Status</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($items as $item)
                    <x-molecules.table-row wire:key="poli-{{ $item->kd_poli }}">
                        <x-atoms.table-cell>
                            <span class="text-zinc-400">{{ $items->firstItem() + $loop->index }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell nowrap>
                            <span class="font-mono text-sm font-medium text-zinc-700 dark:text-primary-dark-300">
                                {{ $item->kd_poli }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            {{ $item->nm_poli }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center">
                            <flux:badge :color="$item->registrasi == '1' ? 'green' : 'zinc'" size="sm">
                                {{ $item->registrasi == '1' ? 'Ya' : 'Tidak' }}
                            </flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center">
                            <flux:badge :color="$item->status == '1' ? 'green' : 'zinc'" size="sm">
                                {{ $item->status == '1' ? 'Aktif' : 'Non-Aktif' }}
                            </flux:badge>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="5">
                            <x-ui.empty-state icon="building-office-2" title="Tidak ada data"
                                description="Tidak ada poliklinik yang sesuai filter" />
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
