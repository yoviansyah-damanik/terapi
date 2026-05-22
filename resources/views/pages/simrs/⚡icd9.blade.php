<?php

use App\Models\Simrs\Icd9;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Master Data — ICD-9')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public int $perPage = 25;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        try {
            $items = Icd9::query()
                ->when(
                    $this->search,
                    fn($q) => $q
                        ->where('kode', 'like', "%{$this->search}%")
                        ->orWhere('deskripsi_panjang', 'like', "%{$this->search}%")
                        ->orWhere('deskripsi_pendek', 'like', "%{$this->search}%"),
                )
                ->orderBy('kode')
                ->paginate($this->perPage);

            return [
                'items' => $items,
                'total' => Icd9::count(),
            ];
        } catch (\Throwable $e) {
            return ['simrsError' => $e->getMessage(), 'items' => collect(), 'total' => 0];
        }
    }
};
?>

<div>
    <x-ui.page-header title="ICD-9" subtitle="Data ICD-9 / ICD-9-CM dari SIMRS" />

    {{-- Stats --}}
    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <x-organisms.stat-card title="Total Kode ICD-9" :value="number_format($total)" icon="document-text" color="zinc" />
    </div>

    @if (isset($simrsError))
        <x-ui.empty-state icon="exclamation-triangle" title="Koneksi SIMRS Gagal" :description="$simrsError" />
    @else
        <x-organisms.data-panel>
            <x-slot:filter>
                <div class="flex-1 min-w-48">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari kode atau nama penyakit..."
                        icon="magnifying-glass" />
                </div>
                <flux:select wire:model.live="perPage" class="w-36">
                    <flux:select.option value="25">25 / halaman</flux:select.option>
                    <flux:select.option value="50">50 / halaman</flux:select.option>
                    <flux:select.option value="100">100 / halaman</flux:select.option>
                </flux:select>
            </x-slot:filter>

            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="w-10">#</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-32">Kode</x-atoms.table-heading>
                    <x-atoms.table-heading>Deskripsi Pendek</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden lg:table-cell">Deskripsi Panjang</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($items as $item)
                    <x-molecules.table-row wire:key="icd9-{{ $item->kode }}">
                        <x-atoms.table-cell>
                            <span class="text-zinc-400">{{ $items->firstItem() + $loop->index }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell nowrap>
                            <flux:badge size="sm" color="zinc" class="font-mono font-bold" inset="top bottom">
                                {{ $item->kode }}
                            </flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            {{ $item->deskripsi_pendek }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden lg:table-cell text-zinc-500 dark:text-primary-dark-400">
                            {{ $item->deskripsi_panjang }}
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="4">
                            <x-ui.empty-state icon="document-magnifying-glass" title="Tidak ada data"
                                description="Tidak ada kode ICD-9 yang sesuai pencarian" />
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
