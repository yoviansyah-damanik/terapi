<?php

use App\Jobs\ImportFhirDictionaryJob;
use App\Models\FhirDictionary;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Log;

new #[Layout('layouts::app', ['title' => 'Master Kamus FHIR'])] class extends Component {
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $filterSource = '';
    public string $filterType = '';

    // Modal Form State
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $source = 'internal';
    public string $type = '';
    public string $customType = '';
    public string $system_code = '';
    public string $system_term = '';
    public ?string $system_defenition = '';
    public string $system_display = '';

    // Deletion State
    public bool $showDeleteModal = false;
    public ?int $selectedIdForDeletion = null;
    public string $selectedCodeForDeletion = '';

    // File Upload State
    public $csvFile;
    public bool $showImportModal = false;

    // Data Import
    public string $importSource = 'internal';
    public string $importPath = ''; // diisi oleh previewImport() dari csvFile->getRealPath()
    public bool $importing = false;
    public array $importTypes = [];
    public array $conflictTypes = [];
    public array $conflictCounts = [];
    public int $importTotalRows = 0;
    public bool $previewDone = false;
    public bool $replaceExisting = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    public function updatingFilterSource(): void
    {
        $this->resetPage();
    }
    public function updatingFilterType(): void
    {
        $this->resetPage();
    }
    public function updatingImportSource(): void
    {
        // Re-run conflict check jika file sudah diupload saat source diubah
        if ($this->previewDone && $this->csvFile) {
            $this->conflictTypes = [];
            $this->conflictCounts = [];
            foreach ($this->importTypes as $t) {
                $count = \App\Models\FhirDictionary::where('source', $this->importSource)->where('type', $t)->count();
                if ($count > 0) {
                    $this->conflictTypes[] = $t;
                    $this->conflictCounts[$t] = $count;
                }
            }
        }
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'source', 'type', 'customType', 'system_code', 'system_term', 'system_defenition', 'system_display');
        $this->source = $this->filterSource ?: 'internal';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $dictionary = FhirDictionary::findOrFail($id);
        $this->editingId = $dictionary->id;
        $this->source = $dictionary->source;
        $this->type = $dictionary->type;
        $this->customType = '';
        $this->system_code = $dictionary->system_code;
        $this->system_term = $dictionary->system_term;
        $this->system_defenition = $dictionary->system_defenition;
        $this->system_display = $dictionary->system_display ?? '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $finalType = $this->type === '__custom__' ? trim($this->customType) : $this->type;

        $this->validate([
            'source' => 'required|string|in:internal,hl7,kemkes,ucum,other',
            'type' => 'required|string',
            'customType' => 'required_if:type,__custom__|nullable|string|max:100',
            'system_code' => 'required|string|max:100',
            'system_term' => 'required|string|max:255',
            'system_display' => 'nullable|string|max:255',
        ]);

        if (!$finalType) {
            $this->addError('type', 'Tipe wajib dipilih atau diisi.');
            return;
        }

        $duplicate = FhirDictionary::where('source', $this->source)->where('type', $finalType)->where('system_code', $this->system_code)->when($this->editingId, fn($q) => $q->where('id', '!=', $this->editingId))->exists();

        if ($duplicate) {
            $this->addError('system_code', 'Kode sudah digunakan pada sumber dan tipe ini.');
            return;
        }

        $data = [
            'source' => $this->source,
            'type' => $finalType,
            'system_code' => $this->system_code,
            'system_term' => $this->system_term,
            'system_defenition' => $this->system_defenition ?: null,
            'system_display' => $this->system_display ?: null,
        ];

        if ($this->editingId) {
            FhirDictionary::where('id', $this->editingId)->update($data);
            $this->dispatch('toast', type: 'success', message: 'Data kamus berhasil diperbarui.');
        } else {
            FhirDictionary::create($data);
            $this->dispatch('toast', type: 'success', message: 'Data kamus berhasil ditambahkan.');
        }

        $this->showModal = false;
    }

    public function confirmDelete(int $id): void
    {
        $item = FhirDictionary::findOrFail($id);
        $this->selectedIdForDeletion = $item->id;
        $this->selectedCodeForDeletion = $item->system_code;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if ($this->selectedIdForDeletion) {
            FhirDictionary::where('id', $this->selectedIdForDeletion)->delete();
            $this->dispatch('toast', type: 'success', message: 'Data kamus berhasil dihapus.');
            $this->showDeleteModal = false;
            $this->reset('selectedIdForDeletion', 'selectedCodeForDeletion');
        }
    }

    // --- Advanced Import Logic (Ref: ICD-10) ---

    public function openImportModal(): void
    {
        $this->resetImportState();
        $this->importSource = $this->filterSource ?: 'internal';
        $this->showImportModal = true;
    }

    public function resetImportState(): void
    {
        $this->csvFile = null;
        $this->importPath = '';
        $this->importTypes = $this->conflictTypes = $this->conflictCounts = [];
        $this->importTotalRows = 0;
        $this->importing = $this->previewDone = $this->replaceExisting = false;
        $this->resetErrorBag();
    }

    public function previewImport(): void
    {
        $this->resetErrorBag();
        $this->importTypes = $this->conflictTypes = $this->conflictCounts = [];
        $this->previewDone = false;

        if (!$this->csvFile || !file_exists($this->csvFile->getRealPath())) {
            $this->addError('csvFile', 'File tidak ditemukan atau sudah kadaluarsa.');
            return;
        }

        $handle = fopen($this->csvFile->getRealPath(), 'r');
        if (!$handle) {
            $this->addError('csvFile', 'Gagal membuka file.');
            return;
        }

        $headers = array_map(fn($h) => strtolower(trim($h)), fgetcsv($handle, 0, ',') ?: []);
        if (!in_array('type', $headers) || (!in_array('system_code', $headers) && !in_array('code', $headers))) {
            fclose($handle);
            $this->addError('csvFile', 'Kolom wajib: "type" dan "system_code" atau "code".');
            return;
        }

        $typesInFile = [];
        $rowCount = 0;
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($headers) !== count($row)) {
                continue;
            }
            $data = array_combine($headers, array_map('trim', $row));
            $t = $data['type'] ?? '';
            if ($t) {
                $typesInFile[$t] = true;
            }
            $rowCount++;
        }
        fclose($handle);

        // Simpan path untuk digunakan saat import
        $this->importPath = $this->csvFile->getRealPath();
        $this->importTotalRows = $rowCount;
        $this->importTypes = array_keys($typesInFile);

        foreach ($this->importTypes as $t) {
            $count = FhirDictionary::where('source', $this->importSource)->where('type', $t)->count();
            if ($count > 0) {
                $this->conflictTypes[] = $t;
                $this->conflictCounts[$t] = $count;
            }
        }
        $this->previewDone = true;
    }

    public function import(): void
    {
        if (empty($this->importPath) || !file_exists($this->importPath)) {
            $this->addError('importPath', 'File tidak ditemukan.');
            return;
        }

        $this->importing = true;
        try {
            $typesToReplace = $this->replaceExisting ? $this->conflictTypes : [];
            ImportFhirDictionaryJob::dispatch($this->importPath, $this->importSource, $typesToReplace);

            $this->resetImportState();
            $this->showImportModal = false;
            $this->dispatch('toast', type: 'success', message: 'Proses import ' . $this->importSource . ' berjalan di background.');
        } catch (\Exception $e) {
            Log::error('Import failed: ' . $e->getMessage());
            $this->importing = false;
            $this->dispatch('toast', type: 'danger', message: 'Gagal memulai import.');
        }
    }

    public function with(): array
    {
        $items = FhirDictionary::query()
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('system_code', 'like', $this->search . '%')
                        ->orWhere('system_term', 'like', '%' . $this->search . '%')
                        ->orWhere('system_defenition', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterSource, fn($q) => $q->where('source', $this->filterSource))
            ->when($this->filterType, fn($q) => $q->where('type', $this->filterType))
            ->orderBy('source')
            ->orderBy('type')
            ->orderBy('system_code')
            ->paginate(25);

        return [
            'items' => $items,
            'existingTypes' => FhirDictionary::getDistinctTypes($this->filterSource ?: null),
            'sources' => FhirDictionary::getDistinctSources(),
        ];
    }
};
?>

<div>
    {{-- Header Section: Reference to ICD-10 simplicity --}}
    <x-ui.page-header title="Source Terminology — Kamus FHIR"
        subtitle="Master data referensi FHIR yang dapat dikelola dan diimpor dari file CSV">
        <x-slot:actions>
            <x-atoms.button wire:click="openImportModal" icon="arrow-up-tray" variant="ghost" size="sm">
                Import CSV
            </x-atoms.button>
            <x-atoms.button wire:click="create" icon="plus" variant="primary" size="sm">
                Tambah Data
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Single Column Layout --}}
    <x-organisms.data-panel title="Data Kamus FHIR" subtitle="Manajemen referensi data terminologi FHIR terpadu">
        <x-slot:filter>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-48">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari kode atau deskripsi..."
                        icon="magnifying-glass" size="sm" />
                </div>
                <div class="min-w-40">
                    <flux:select wire:model.live="filterSource" size="sm">
                        <option value="">Semua Sumber</option>
                        @foreach ($sources as $src)
                            <option value="{{ $src }}">{{ strtoupper($src) }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="min-w-40">
                    <flux:select wire:model.live="filterType" size="sm">
                        <option value="">Semua Tipe</option>
                        @foreach ($existingTypes as $et)
                            <option value="{{ $et }}">{{ $et }}</option>
                        @endforeach
                    </flux:select>
                </div>
                @if ($search || $filterSource || $filterType)
                    <x-atoms.button wire:click="$set('search', ''); $set('filterSource', ''); $set('filterType', '')"
                        variant="ghost" size="sm" icon="x-mark">
                        Reset
                    </x-atoms.button>
                @endif
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-24">Source</x-atoms.table-heading>
                <x-atoms.table-heading class="w-48">Tipe</x-atoms.table-heading>
                <x-atoms.table-heading class="w-48">System Code</x-atoms.table-heading>
                <x-atoms.table-heading>Term & Deskripsi</x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-24">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $item)
                <x-molecules.table-row wire:key="dict-{{ $item->id }}">
                    <x-atoms.table-cell>
                        <flux:badge variant="outline"
                            :color="match($item->source) { 'hl7' => 'sky', 'kemkes' => 'emerald', 'ucum' => 'amber', 'internal' => 'blue', default => 'zinc' }"
                            class="uppercase px-2 py-0">
                            {{ $item->source }}
                        </flux:badge>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <span
                            class="text-primary-400 bg-primary-50 dark:bg-primary-dark-900/50 px-2 py-0.5 rounded border border-primary-100 dark:border-primary-dark-800">{{ $item->type }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <span
                            class="font-mono font-black text-primary-600 dark:text-primary-400">{{ $item->system_code }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <div class="flex flex-col gap-0.5">
                            <span
                                class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $item->system_term }}</span>
                            @if ($item->system_defenition)
                                <p class="text-xs text-zinc-400 dark:text-primary-dark-500 line-clamp-1">
                                    {{ $item->system_defenition }}</p>
                            @endif
                            @if ($item->system_display)
                                <p class="text-xs italic text-zinc-400 dark:text-primary-dark-500 line-clamp-1">
                                    {{ $item->system_display }}</p>
                            @endif
                        </div>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="center" action>
                        <x-atoms.button wire:click="edit({{ $item->id }})" variant="ghost" icon="pencil-square"
                            tooltip="Ubah" size="sm"
                            class="hover:bg-primary-50 text-zinc-400 hover:text-primary-600" />
                        <x-atoms.button wire:click="confirmDelete({{ $item->id }})" variant="ghost" icon="trash"
                            tooltip="Hapus" size="sm" class="hover:bg-red-50 text-zinc-400 hover:text-red-500" />
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="5" class="py-12 text-center">
                        <div class="flex flex-col items-center gap-2 text-zinc-400">
                            <flux:icon name="circle-stack" class="size-10" />
                            <p class="text-sm">Belum ada data kamus FHIR ditemukan.</p>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        <x-slot:footer>
            @php
                $hasPages = $items->hasPages();
                $pageNumbers = [];
                if ($hasPages) {
                    $lastPage = $items->lastPage();
                    $current = $items->currentPage();
                    if ($lastPage <= 7) {
                        $pageNumbers = range(1, $lastPage);
                    } elseif ($current <= 4) {
                        $pageNumbers = [...range(1, 5), null, $lastPage];
                    } elseif ($current >= $lastPage - 3) {
                        $pageNumbers = [1, null, ...range($lastPage - 4, $lastPage)];
                    } else {
                        $pageNumbers = [1, null, $current - 1, $current, $current + 1, null, $lastPage];
                    }
                }
            @endphp
            <x-molecules.pagination :page="$items->currentPage()" :total-page="$items->lastPage()" :total="$items->total()" :page-numbers="$pageNumbers"
                on-prev="previousPage" on-next="nextPage" on-goto="gotoPage" />
        </x-slot:footer>
    </x-organisms.data-panel>

    {{-- Modal Konfirmasi Hapus --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="sm" title="Hapus Data?">
        <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
            Apakah Anda yakin ingin menghapus referensi <strong>{{ $selectedCodeForDeletion }}</strong>?
        </p>

        <x-slot:footer>
            <div class="flex justify-end gap-2 w-full">
                <x-atoms.button variant="ghost" wire:click="$set('showDeleteModal', false)">Batal</x-atoms.button>
                <x-atoms.button wire:click="delete" variant="danger">Hapus Sekarang</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Form Modal --}}
    <x-organisms.modal wire:model="showModal" maxWidth="lg"
        title="{{ $editingId ? 'Edit Referensi' : 'Tambah Referensi' }}">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <flux:label class="text-xs font-semibold text-zinc-500">Sumber</flux:label>
                    <flux:select wire:model="source" size="sm" class="mt-1">
                        @foreach ($sources as $src)
                            <option value="{{ $src }}">{{ strtoupper($src) }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:label class="text-xs font-semibold text-zinc-500">Tipe</flux:label>
                    <flux:select wire:model.live="type" size="sm" class="mt-1">
                        <option value="">-- Pilih Tipe --</option>
                        @foreach ($existingTypes as $et)
                            <option value="{{ $et }}">{{ $et }}</option>
                        @endforeach
                        <option value="__custom__">+ Tambah Baru...</option>
                    </flux:select>
                </div>
            </div>

            @if ($type === '__custom__')
                <flux:input wire:model="customType" placeholder="Nama Tipe Baru..." label="Kategori Baru" icon="plus"
                    size="sm" />
            @endif

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <flux:label class="text-xs font-semibold text-zinc-500">System Code</flux:label>
                    <flux:input wire:model="system_code" placeholder="Misal: TAB" size="sm" class="mt-1" />
                    @error('system_code')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <flux:label class="text-xs font-semibold text-zinc-500">System Term</flux:label>
                    <flux:input wire:model="system_term" placeholder="Display term..." size="sm"
                        class="mt-1" />
                    @error('system_term')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <flux:label class="text-xs font-semibold text-zinc-500">Definisi</flux:label>
                <flux:textarea wire:model="system_defenition" rows="2"
                    placeholder="Detail informasi referensi (opsional)" size="sm" class="mt-1" />
            </div>

            <div>
                <flux:label class="text-xs font-semibold text-zinc-500">Display</flux:label>
                <flux:input wire:model="system_display" rows="2" placeholder="http://example.com/CodeSystem/*"
                    size="sm" class="mt-1" />
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2 w-full">
                    <x-atoms.button variant="ghost" wire:click="$set('showModal', false)">Batal</x-atoms.button>
                    <x-atoms.button type="submit" variant="primary" class="px-6">Simpan</x-atoms.button>
                </div>
            </x-slot:footer>
        </form>
    </x-organisms.modal>

    {{-- Advanced Import Modal --}}
    <x-organisms.modal wire:model="showImportModal" maxWidth="xl" title="Import Kamus FHIR Terpadu">
        <div class="space-y-5">
            {{-- Step 1: Sumber --}}
            <div>
                <flux:label class="text-[10px] font-black uppercase tracking-wider text-zinc-500">Sumber Data Tujuan
                </flux:label>
                <flux:select wire:model.live="importSource" class="mt-1">
                    @foreach ($sources as $src)
                        <option value="{{ $src }}">{{ strtoupper($src) }}</option>
                    @endforeach
                </flux:select>
            </div>

            {{-- Step 2: Upload --}}
            <div>
                <flux:label class="text-[10px] font-black uppercase tracking-wider text-zinc-500 mb-1.5 block">Berkas
                    CSV</flux:label>
                <x-atoms.document-dropzone wireModel="csvFile" accept=".csv" maxSizeMb="20"
                    label="Seret & lepas atau klik untuk memilih berkas CSV">
                    {{-- Preview setelah upload selesai --}}
                    @if ($previewDone)
                        <div class="mt-4 space-y-3 animate-in fade-in slide-in-from-bottom-2 duration-300">
                            <div class="grid grid-cols-2 gap-3">
                                <div
                                    class="p-3 bg-zinc-50 dark:bg-primary-dark-900/50 rounded-xl border border-zinc-100 dark:border-primary-dark-800 text-center">
                                    <p class="text-xl font-black text-zinc-800 dark:text-zinc-100">
                                        {{ number_format($importTotalRows) }}</p>
                                    <p class="text-[10px] uppercase font-bold text-zinc-400 tracking-widest mt-0.5">
                                        Total Baris</p>
                                </div>
                                <div
                                    class="p-3 bg-zinc-50 dark:bg-primary-dark-900/50 rounded-xl border border-zinc-100 dark:border-primary-dark-800 text-center">
                                    <p class="text-xl font-black text-zinc-800 dark:text-zinc-100">
                                        {{ count($importTypes) }}</p>
                                    <p class="text-[10px] uppercase font-bold text-zinc-400 tracking-widest mt-0.5">
                                        Kategori Tipe</p>
                                </div>
                            </div>

                            @if (!empty($conflictTypes))
                                <div
                                    class="p-4 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 rounded-xl space-y-3">
                                    <div class="flex items-start gap-2">
                                        <flux:icon name="exclamation-triangle"
                                            class="size-4 shrink-0 text-amber-600 mt-0.5" />
                                        <div class="space-y-1.5">
                                            <p class="text-[11px] font-black text-amber-800 dark:text-amber-400">
                                                Kategori sudah ada di database</p>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($conflictTypes as $t)
                                                    <span
                                                        class="px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-[10px] font-bold border border-amber-200 dark:border-amber-800">
                                                        {{ $t }} ({{ number_format($conflictCounts[$t]) }})
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <label
                                        class="flex items-center gap-3 cursor-pointer p-2.5 bg-white/70 dark:bg-white/5 rounded-lg border border-amber-200 dark:border-amber-800/50">
                                        <input type="checkbox" wire:model="replaceExisting"
                                            class="size-4 rounded border-amber-300 text-amber-600">
                                        <span class="text-[11px] font-bold text-amber-800 dark:text-amber-400">Timpa &
                                            ganti data lama pada kategori yang sama</span>
                                    </label>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div wire:loading wire:target="previewImport"
                        class="mt-3 flex items-center gap-2 text-xs text-zinc-500">
                        <svg class="animate-spin size-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Menganalisis isi file...
                    </div>
                </x-atoms.document-dropzone>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2 w-full">
                    <x-atoms.button wire:click="$set('showImportModal', false)" variant="ghost">Batal</x-atoms.button>
                    @if ($previewDone)
                        <x-atoms.button wire:click="import" variant="primary" :loading="$importing" :disabled="empty($importPath)"
                            class="px-8">
                            {{ $replaceExisting ? 'Ganti & Import' : 'Import Sekarang' }}
                        </x-atoms.button>
                    @endif
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>
</div>
