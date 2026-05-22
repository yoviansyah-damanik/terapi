<?php

use App\Models\Mapping\GeneralMap;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Mapping General ke Snomed CT')] class extends Component {
    use WithPagination;

    #[Url(as: 'tab')]
    public $activeTab = 'status_pulang'; // status_pulang, status_perkawinan, jenis_kelamin

    public $search = '';

    // Mapping Modal state
    public $showModal = false;
    public $isEdit = false;
    public $local_code;
    public $local_term;
    public $snomed_code;
    public $snomed_display;
    public $snomed_term; // Added
    public $system_display = 'http://snomed.info/sct';

    // Search Snomed Modal state
    public $showSnomedSearchModal = false;
    public $snomedSearch = '';
    public $snomedResults = [];

    // Edit tracking
    public $editId;

    public function updatedActiveTab()
    {
        $this->resetPage();
        $this->search = '';
        $this->reset(['showModal', 'showSnomedSearchModal']);
    }

    public function with()
    {
        $mappings = GeneralMap::where('category', $this->activeTab)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('local_code', 'like', '%' . $this->search . '%')
                        ->orWhere('local_term', 'like', '%' . $this->search . '%')
                        ->orWhere('system_code', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('local_code')
            ->paginate(10);

        return [
            'mappings' => $mappings,
        ];
    }

    public function create()
    {
        $this->reset(['local_code', 'local_term', 'snomed_code', 'snomed_display', 'snomed_term', 'editId', 'snomedSearch', 'snomedResults']);
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $mapping = GeneralMap::findOrFail($id);
        $this->editId = $id;
        $this->local_code = $mapping->local_code;
        $this->local_term = $mapping->local_term;
        $this->snomed_code = $mapping->system_code;
        $this->snomed_term = $mapping->system_term;
        $this->snomed_display = $mapping->system_term ?? $mapping->system_code;

        $this->isEdit = true;
        $this->showModal = true;
    }

    // ...

    public function selectSnomed($conceptId, $pt)
    {
        $this->snomed_code = $conceptId;
        $this->snomed_term = $pt; // Save term
        $this->snomed_display = "$conceptId - $pt";
        $this->showSnomedSearchModal = false;
    }

    public function openSnomedSearchModal()
    {
        $this->reset(['snomedSearch', 'snomedResults']);
        $this->showSnomedSearchModal = true;
    }

    public function updatedSnomedSearch()
    {
        if (strlen($this->snomedSearch) < 3) {
            $this->snomedResults = [];
            return;
        }

        $service = new \App\Services\Snomed\SnowstormService();
        // Adjust semantic tag based on category
        $semanticTag = match ($this->activeTab) {
            'jenis_kelamin' => 'finding',
            'status_perkawinan' => 'finding',
            'status_pulang' => 'procedure',
            default => 'finding',
        };

        if ($this->activeTab === 'status_pulang') {
            $semanticTag = null;
        }

        $response = $service->search($this->snomedSearch, semanticTag: $semanticTag);
        $this->snomedResults = $response['items'] ?? [];
    }

    protected function rules()
    {
        return [
            'local_code' => 'required|string|max:50',
            'local_term' => 'nullable|string|max:255',
            'snomed_code' => 'required|string|max:20',
            'system_display' => 'required|url',
        ];
    }

    public function store()
    {
        $this->validate();

        if ($this->isEdit) {
            GeneralMap::where('id', $this->editId)->update([
                'category' => $this->activeTab,
                'local_code' => $this->local_code,
                'local_term' => $this->local_term,
                'system_code' => $this->snomed_code,
                'system_term' => $this->snomed_term,
                'system_display' => $this->system_display,
            ]);

            $this->toastSuccess('Mapping berhasil diperbarui', 'Sukses');
        } else {
            // Check duplicate
            $exists = GeneralMap::where('category', $this->activeTab)->where('local_code', $this->local_code)->exists();

            if ($exists) {
                $this->addError('local_code', 'Mapping ini sudah ada.');
                return;
            }

            GeneralMap::create([
                'category' => $this->activeTab,
                'local_code' => $this->local_code,
                'local_term' => $this->local_term,
                'system_code' => $this->snomed_code,
                'system_term' => $this->snomed_term,
                'system_display' => $this->system_display,
            ]);
            $this->toastSuccess('Mapping berhasil disimpan', 'Sukses');
        }

        $this->showModal = false;
    }

    public function delete($id)
    {
        GeneralMap::findOrFail($id)->delete();
        $this->toastSuccess('Mapping berhasil dihapus', 'Sukses');
    }
}; ?>

<div>
    <x-ui.page-header title="Mapping General Snomed CT"
        subtitle="Hubungkan data demografi dan status lainnya dengan konsep Snomed CT">
        <x-slot name="actions">
            <x-atoms.button wire:click="create" variant="primary" icon="plus">Tambah Mapping</x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    <div class="mb-4">
        {{-- Tabs --}}
        <x-molecules.tabs>
            <x-atoms.tab-item wire:click="$set('activeTab', 'status_pulang')">Status Pulang</x-atoms.tab-item>
            <x-atoms.tab-item wire:click="$set('activeTab', 'status_perkawinan')">Status Perkawinan</x-atoms.tab-item>
            <x-atoms.tab-item wire:click="$set('activeTab', 'jenis_kelamin')">Jenis Kelamin</x-atoms.tab-item>

        </x-molecules.tabs>
    </div>

    <div class="flex flex-col gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="Cari kode lokal, deskripsi, atau kode snomed..." />

        <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                    <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                        <tr>
                            <th
                                class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Kode Lokal</th>
                            <th
                                class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Deskripsi Lokal</th>
                            <th
                                class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Kode Snomed</th>
                            <th
                                class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Istilah Snomed</th>
                            <th
                                class="px-4 py-3 text-xs font-medium tracking-wider text-center uppercase text-zinc-500 dark:text-primary-dark-400">
                                Aksi</th>
                        </tr>
                    </thead>
                    <tbody
                        class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                        @forelse ($mappings as $mapping)
                            <tr :key="$mapping->id" class="group hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span
                                        class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400">{{ $mapping->local_code }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                    {{ $mapping->local_term ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span
                                        class="font-mono text-sm text-zinc-600 dark:text-primary-dark-400">{{ $mapping->system_code }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-300">
                                    {{ $mapping->system_term ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <div
                                        class="flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
                                        <x-atoms.button wire:click="edit({{ $mapping->id }})" size="sm"
                                            icon="pencil-square" variant="ghost" tooltip="Edit" />

                                        <x-atoms.button wire:confirm="Hapus mapping ini?"
                                            wire:click="delete({{ $mapping->id }})" size="sm" icon="trash"
                                            variant="ghost" tooltip="Hapus" class="text-red-500" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <flux:icon name="document-text"
                                            class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Belum ada data
                                            mapping.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($mappings->hasPages())
                <div class="px-4 py-4 border-t border-zinc-200 dark:border-primary-dark-700">
                    {{ $mappings->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Mapping Modal --}}
    <x-organisms.modal wire:model="showModal" maxWidth="sm" title="">
        <div class="space-y-6">
            <div>
                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                    {{ $isEdit ? 'Edit' : 'Tambah' }} Mapping
                </h3>
                <p class="text-xs text-zinc-400 uppercase mt-1">Kategori: {{ str_replace('_', ' ', $activeTab) }}</p>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="local_code" label="Kode Lokal" placeholder="Contoh: L / P / PLG" />
                <flux:input wire:model="local_term" label="Deskripsi Lokal"
                    placeholder="Contoh: Laki-laki / Pulang Sehat" />

                <div class="flex gap-2">
                    <flux:input wire:model="snomed_code" label="Kode Snomed" placeholder="Pilih kode Snomed..." readonly
                        class="flex-1" />
                    <div class="flex items-end">
                        <x-atoms.button wire:click="openSearchModal" variant="primary" icon="magnifying-glass"
                            size="sm">
                            Cari</x-atoms.button>
                    </div>
                </div>

                @endif
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="store" variant="primary">Simpan</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Snomed CT Search Modal --}}
    <x-organisms.modal wire:model="showSearchModal" maxWidth="md" title="">
        <div class="space-y-6">
            <div>
                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">Cari Konsep Snomed CT</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Cari konsep yang sesuai</p>
            </div>

            <div class="space-y-4">
                <flux:input wire:model.live.debounce.500ms="snomedSearch" label="Kata Kunci" icon="magnifying-glass"
                    placeholder="Ketik minimal 3 karakter..." />

                <div
                    class="overflow-y-auto bg-white border divide-y rounded-md shadow max-h-96 border-zinc-200 dark:bg-primary-dark-900 dark:border-primary-dark-700 dark:divide-primary-dark-700">
                    @foreach ($snomedResults as $result)
                        <div class="p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-primary-dark-800"
                            wire:click="selectConcept('{{ $result['conceptId'] ?? ($result['id'] ?? '') }}', '{{ addslashes($result['pt']['term'] ?? ($result['fsn']['term'] ?? '')) }}')">
                            <div class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                {{ $result['pt']['term'] ?? ($result['fsn']['term'] ?? 'No Description') }}
                            </div>
                            <div class="flex items-center gap-2 mt-1">
                                <span
                                    class="font-mono text-xs text-primary-600 dark:text-primary-400">{{ $result['conceptId'] ?? ($result['id'] ?? '-') }}</span>
                                @if (isset($result['fsn']['term']))
                                    <span class="text-xs text-zinc-500">• {{ $result['fsn']['term'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    @if (count($snomedResults) === 0)
                        @if (strlen($snomedSearch) >= 3)
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <flux:icon name="document-text"
                                    class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Data tidak ditemukan.
                                </p>
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <flux:icon name="magnifying-glass"
                                    class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Ketik minimal 3
                                    karakter untuk
                                    mencari
                                </p>
                            </div>
                        @endif
                @endif
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button wire:click="$set('showSearchModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
