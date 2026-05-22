<?php

use App\Services\Snomed\SnowstormService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Flux\Flux;

new #[Layout('layouts::app')] #[Title('Master Snomed CT')] class extends Component {
    use WithPagination;

    // Tab management
    public string $activeTab = 'search';

    // Semantic Tags tab
    public string $semanticSearch = '';

    // Modal state
    public ?int $editingTagId = null;
    public string $semancticTagName = '';
    public string $semanticTagDescription = '';

    public function openModal(?int $id = null): void
    {
        $this->resetValidation();
        $this->editingTagId = $id;

        if ($id) {
            $tag = \App\Models\Terminology\SnomedSemanticTag::find($id);
            $this->semancticTagName = $tag->tag;
            $this->semanticTagDescription = $tag->description;
        } else {
            $this->semancticTagName = '';
            $this->semanticTagDescription = '';
        }

        // Dispatch event browser setelah morphdom selesai memperbarui konten modal.
        // Ini mencegah conflict antara morphdom dan Alpine saat membuka modal.
        $this->dispatch('open-tag-modal');
    }

    public function closeModal(): void
    {
        $this->editingTagId = null;
        $this->semancticTagName = '';
        $this->semanticTagDescription = '';
        Flux::modal('tagModal')->close();
    }

    public function saveSemanticTag(): void
    {
        $this->validate([
            'semancticTagName' => 'required|string|unique:snomed_semantic_tags,tag,' . $this->editingTagId,
            'semanticTagDescription' => 'required|string',
        ]);

        if ($this->editingTagId) {
            $tag = \App\Models\Terminology\SnomedSemanticTag::find($this->editingTagId);
            $tag->update([
                'tag' => $this->semancticTagName,
                'description' => $this->semanticTagDescription,
            ]);
            $this->toastSuccess('Semantic tag updated successfully');
        } else {
            \App\Models\Terminology\SnomedSemanticTag::create([
                'tag' => $this->semancticTagName,
                'description' => $this->semanticTagDescription,
            ]);
            $this->toastSuccess('Semantic tag created successfully');
        }

        $this->closeModal();
    }

    public function deleteSemanticTag(int $id): void
    {
        $tag = \App\Models\Terminology\SnomedSemanticTag::find($id);
        if ($tag) {
            $tag->delete();
            $this->toastSuccess('Semantic tag deleted successfully');
        }
    }

    /** Pindah ke tab Search dan set filter semantic tag sekaligus */
    public function goToSearch(string $tag): void
    {
        $this->activeTab = 'search';
        $this->selectedSemanticTag = $tag;
        $this->resetPage();
    }

    // Search tab
    public string $searchTerm = '';
    public array $searchItems = []; // Stores the raw items
    public ?string $selectedSemanticTag = null;
    public bool $searching = false;
    public string $searchMode = 'STANDARD';

    // Search pagination
    public int $limit = 50;
    public int $searchTotal = 0;

    public function updatedLimit()
    {
        $this->resetPage();
        $this->searchSnomed();
    }

    public function searchSnomed(): void
    {
        if (empty($this->searchTerm)) {
            return;
        }

        $this->searching = true;

        // Calculate offset based on current page
        $page = $this->getPage();
        $offset = ($page - 1) * $this->limit;

        try {
            $service = new SnowstormService();
            $response = $service->search($this->searchTerm, $this->limit, $offset, $this->searchMode, null, $this->selectedSemanticTag);

            $this->searchItems = $response['items'] ?? [];
            $this->searchTotal = $response['total'] ?? 0;
        } catch (\Exception $e) {
            $this->toastError('Search failed: ' . $e->getMessage());
        }

        $this->searching = false;
    }

    // Trigger search when page changes
    public function updatedPage($page)
    {
        $this->searchSnomed();
    }

    // Reset pagination when search term or filters change
    public function updatedSearchTerm()
    {
        $this->resetPage();
    }
    public function updatedSelectedSemanticTag()
    {
        $this->resetPage();
    }
    public function updatedSelectedConceptIds()
    {
        $this->resetPage();
    }
    public function updatedSearchMode()
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $semanticTags = \App\Models\Terminology\SnomedSemanticTag::where('active', true)
            ->when($this->semanticSearch, function ($query) {
                $query->where('tag', 'like', '%' . $this->semanticSearch . '%')->orWhere('description', 'like', '%' . $this->semanticSearch . '%');
            })
            ->orderBy('tag')
            ->paginate(10, ['*'], 'tagsPage');

        return [
            'searchResults' => new \Illuminate\Pagination\LengthAwarePaginator($this->searchItems, $this->searchTotal, $this->limit, $this->getPage(), ['path' => \Illuminate\Support\Facades\Request::url()]),
            'semanticTags' => $semanticTags,
            'searchFilterTags' => \App\Models\Terminology\SnomedSemanticTag::where('active', true)->orderBy('tag')->get(),
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="Snomed CT" subtitle="Kelola pengaturan Snowstorm API dan eksplorasi SNOMED CT concepts" />

    {{-- Tabs --}}
    <x-molecules.tabs>
        <x-atoms.tab-item wire:click="$set('activeTab', 'search')">Pencarian Snomed CT</x-atoms.tab-item>
        <x-atoms.tab-item wire:click="$set('activeTab', 'semantic')">Semantic Tags</x-atoms.tab-item>
    
    </x-molecules.tabs>

    @if ($activeTab === 'semantic')
        <div class="flex flex-col gap-4" key="semantic">
            <x-organisms.data-panel title="Tag Semantik" subtitle="Kelola daftar Tag Semantik untuk filter pencarian SNOMED CT">
                <x-slot:actions>
                    <x-atoms.button wire:click="openModal" variant="primary" icon="plus">
                        Tambah Tag
                    </x-atoms.button>
                </x-slot:actions>

                <x-slot:filter>
                    <flux:input wire:model.live.debounce.300ms="semanticSearch" icon="magnifying-glass"
                        placeholder="Cari tag semantik..." />
                </x-slot:filter>

                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Tag</x-atoms.table-heading>
                        <x-atoms.table-heading>Deskripsi</x-atoms.table-heading>
                        <x-atoms.table-heading align="right">Aksi</x-atoms.table-heading>
                    </x-slot:headings>

                    @forelse ($semanticTags as $tag)
                        <x-molecules.table-row>
                            <x-atoms.table-cell>
                                <flux:badge color="zinc" size="sm">{{ $tag->tag }}</flux:badge>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">
                                {{ $tag->description }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="right">
                                <div class="flex justify-end gap-2">
                                    <x-atoms.button wire:click="goToSearch('{{ $tag->tag }}')"
                                        variant="ghost" size="sm" icon="magnifying-glass"
                                        title="Cari tag ini">
                                    </x-atoms.button>
                                    <x-atoms.button wire:click="openModal({{ $tag->id }})" variant="ghost"
                                        size="sm" icon="pencil-square" title="Edit">
                                    </x-atoms.button>
                                    <x-atoms.button wire:confirm="Apakah Anda yakin ingin menghapus tag ini?"
                                        wire:click="deleteSemanticTag({{ $tag->id }})" variant="ghost"
                                        size="sm" icon="trash"
                                        class="text-red-500 hover:text-red-600">
                                    </x-atoms.button>
                                </div>
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @empty
                        <x-molecules.table-row>
                            <x-atoms.table-cell colspan="3" class="px-4 py-6 text-center text-zinc-500">
                                Tidak ada tag semantik ditemukan.
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @endforelse
                </x-organisms.table>
                
                @if ($semanticTags->hasPages())
                    <x-slot:footer>
                        <div class="px-4 py-4 w-full">
                            {{ $semanticTags->links() }}
                        </div>
                    </x-slot:footer>
                @endif
            </x-organisms.data-panel>
        </div>
    @endif

    <x-organisms.modal name="tagModal" maxWidth="md" title="{{ $editingTagId ? 'Edit Tag Semantik' : 'Tambah Tag Semantik' }}" description="Kelola tag semantik untuk filter pencarian SNOMED CT.">
        <form wire:submit="saveSemanticTag" class="space-y-4">
            <flux:field>
                <flux:label>Nama Tag</flux:label>
                <flux:input wire:model="semancticTagName" placeholder="cth. disorder" />
                <flux:error name="semancticTagName" />
            </flux:field>

            <flux:field>
                <flux:label>Deskripsi</flux:label>
                <flux:input wire:model="semanticTagDescription" placeholder="cth. Kelainan" />
                <flux:error name="semanticTagDescription" />
            </flux:field>

            <x-slot:footer>
                <div class="flex justify-end gap-2 w-full pt-1">
                    <x-atoms.button type="button" wire:click="closeModal" variant="ghost">Batal
                    </x-atoms.button>
                    <x-atoms.button type="submit" variant="primary">
                        {{ $editingTagId ? 'Perbarui' : 'Buat' }}
                    </x-atoms.button>
                </div>
            </x-slot:footer>
        </form>
    </x-organisms.modal>

    @if ($activeTab === 'search')
        <div class="flex flex-col gap-4" key="search">
            <x-organisms.data-panel>
                <x-slot:filter>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                        <div class="md:col-span-4">
                            <flux:label>Kata Kunci</flux:label>
                            <flux:input wire:model="searchTerm" wire:keydown.enter="searchSnomed" icon="magnifying-glass"
                                placeholder="Cari konsep SNOMED CT..." />
                        </div>
                        <div class="md:col-span-2">
                            <flux:label>Mode Pencarian</flux:label>
                            <flux:select wire:model="searchMode">
                                <flux:select.option value="STANDARD">Standar</flux:select.option>
                                <flux:select.option value="REGEX">Regex</flux:select.option>
                                <flux:select.option value="WHOLE_WORD">Kata Utuh</flux:select.option>
                            </flux:select>
                        </div>
                        <div class="md:col-span-3">
                            <flux:label>Tag Semantik</flux:label>
                            <flux:select wire:model="selectedSemanticTag">
                                <flux:select.option value="">Semua Tag</flux:select.option>
                                @foreach ($searchFilterTags as $tag)
                                    <flux:select.option value="{{ $tag->tag }}">{{ $tag->description }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="md:col-span-2">
                            <flux:label>Per Halaman</flux:label>
                            <flux:select wire:model.live="limit">
                                <flux:select.option value="25">25</flux:select.option>
                                <flux:select.option value="50">50</flux:select.option>
                                <flux:select.option value="100">100</flux:select.option>
                            </flux:select>
                        </div>
                        <div class="flex h-full items-end md:col-span-1">
                            <x-atoms.button class="w-full" wire:click="searchSnomed" variant="primary" icon="magnifying-glass">
                                Cari
                            </x-atoms.button>
                        </div>
                    </div>
                </x-slot:filter>

                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>ID Konsep</x-atoms.table-heading>
                        <x-atoms.table-heading>FSN</x-atoms.table-heading>
                        <x-atoms.table-heading>Istilah Pilihan</x-atoms.table-heading>
                        <x-atoms.table-heading>Tag Semantik</x-atoms.table-heading>
                        <x-atoms.table-heading align="center">Status</x-atoms.table-heading>
                    </x-slot:headings>

                    @forelse($searchResults as $result)
                        <x-molecules.table-row>
                            <x-atoms.table-cell>
                                <span class="font-mono font-medium text-primary-600 dark:text-primary-400">{{ $result['conceptId'] }}</span>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">
                                {{ $result['fsn']['term'] ?? '-' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">
                                {{ $result['pt']['term'] ?? '-' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell>
                                @php
                                    $fsn = $result['fsn']['term'] ?? '';
                                    preg_match('/\(([^)]+)\)$/', $fsn, $matches);
                                    $tag = $matches[1] ?? '-';
                                @endphp
                                <flux:badge color="zinc" size="sm">{{ $tag }}</flux:badge>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="center">
                                <flux:badge :color="$result['active'] ? 'green' : 'red'" size="sm">
                                    {{ $result['active'] ? 'Aktif' : 'Tidak Aktif' }}
                                </flux:badge>
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @empty
                        <x-molecules.table-row>
                            <x-atoms.table-cell colspan="5" class="py-12 text-center">
                                <div class="flex flex-col items-center">
                                    @if ($searching)
                                        <flux:icon name="arrow-path"
                                            class="w-12 h-12 text-primary-600 dark:text-primary-400 animate-spin" />
                                        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                                            Sedang mencari...</p>
                                    @else
                                        <flux:icon name="magnifying-glass"
                                            class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada hasil</p>
                                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500">Masukkan kata kunci dan klik "Cari"</p>
                                    @endif
                                </div>
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @endforelse
                </x-organisms.table>

                @if ($searchResults instanceof \Illuminate\Pagination\LengthAwarePaginator && $searchResults->hasPages())
                    <x-slot:footer>
                        <div class="px-4 py-4 w-full border-t border-zinc-200 dark:border-primary-dark-700">
                            {{ $searchResults->links() }}
                        </div>
                    </x-slot:footer>
                @endif
            </x-organisms.data-panel>
        </div>
    @endif
</div>
@script
    <script>
        window.addEventListener('open-tag-modal', () => {
            document.dispatchEvent(new CustomEvent('modal-show', {
                detail: {
                    name: 'tagModal'
                }
            }));
        });
    </script>
@endscript
