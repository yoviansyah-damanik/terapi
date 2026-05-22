<?php

use App\Models\SatuSehat\SatusehatRuleNumber;
use App\Jobs\ImportSatusehatRuleNumberJob;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Log;

new #[Layout('layouts::app', ['title' => 'Satu Sehat Rule Numbers'])] class extends Component {
    use WithPagination, WithFileUploads;

    public string $search = '';

    // File Upload State
    public $csvFile;
    public bool $showImportModal = false;

    // Data Import
    public string $importPath = '';
    public bool $importing = false;
    public int $importTotalRows = 0;
    public bool $previewDone = false;
    public bool $replaceExisting = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openImportModal(): void
    {
        $this->resetImportState();
        $this->showImportModal = true;
    }

    public function resetImportState(): void
    {
        $this->csvFile = null;
        $this->importPath = '';
        $this->importTotalRows = 0;
        $this->importing = $this->previewDone = $this->replaceExisting = false;
        $this->resetErrorBag();
    }

    public function previewImport(): void
    {
        $this->resetErrorBag();
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

        // Detect delimiter
        $firstLine = fgets($handle);
        $delimiter = strpos($firstLine, ';') !== false ? ';' : ',';
        rewind($handle);

        $headers = array_map(fn($h) => strtolower(trim($h)), fgetcsv($handle, 0, $delimiter) ?: []);

        $rowCount = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowCount++;
        }
        fclose($handle);

        $this->importPath = $this->csvFile->getRealPath();
        $this->importTotalRows = $rowCount;
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
            ImportSatusehatRuleNumberJob::dispatch($this->importPath, $this->replaceExisting);

            $this->resetImportState();
            $this->showImportModal = false;
            $this->dispatch('toast', type: 'success', message: 'Proses import berjalan di background.');
        } catch (\Exception $e) {
            Log::error('Import failed: ' . $e->getMessage());
            $this->importing = false;
            $this->dispatch('toast', type: 'danger', message: 'Gagal memulai import.');
        }
    }

    public function with(): array
    {
        $items = SatusehatRuleNumber::query()
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('rule_no', 'like', '%' . $this->search . '%')
                        ->orWhere('path', 'like', '%' . $this->search . '%')
                        ->orWhere('terminology_used', 'like', '%' . $this->search . '%')
                        ->orWhere('error_description', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('rule_no')
            ->paginate(25);

        return [
            'items' => $items,
        ];
    }
};
?>

<div>
    <x-ui.page-header title="Satu Sehat — Kamus Rule Number"
        subtitle="Master data validasi rule Satu Sehat yang dapat diimpor dari file CSV">
        <x-slot:actions>
            <x-atoms.button wire:click="openImportModal" icon="arrow-up-tray" variant="primary" size="sm">
                Import CSV
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-organisms.data-panel title="Data Rule Number" subtitle="Manajemen referensi rule Satu Sehat">
        <x-slot:filter>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-48">
                    <flux:input wire:model.live.debounce.300ms="search"
                        placeholder="Cari Rule No, Path, atau Deskripsi..." icon="magnifying-glass" size="sm" />
                </div>
                @if ($search)
                    <x-atoms.button wire:click="$set('search', '')" variant="ghost" size="sm" icon="x-mark">
                        Reset
                    </x-atoms.button>
                @endif
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-32">Rule No</x-atoms.table-heading>
                <x-atoms.table-heading class="w-48">Path</x-atoms.table-heading>
                <x-atoms.table-heading class="w-48">Terminology</x-atoms.table-heading>
                <x-atoms.table-heading>Deskripsi Error</x-atoms.table-heading>
                <x-atoms.table-heading class="w-32">Versi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $item)
                <x-molecules.table-row wire:key="rule-{{ $item->id }}">
                    <x-atoms.table-cell>
                        <span
                            class="font-mono font-black text-primary-600 dark:text-primary-400">{{ $item->rule_no }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <span
                            class="text-sm font-mono text-zinc-600 dark:text-primary-dark-300 break-all">{{ $item->path }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <span
                            class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $item->terminology_used }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <p class="text-sm text-zinc-700 dark:text-primary-dark-200 line-clamp-2"
                            title="{{ $item->error_description }}">
                            {{ $item->error_description }}
                        </p>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <div class="flex flex-col gap-0.5 text-xs text-zinc-500 dark:text-primary-dark-400">
                            <span>{{ $item->version ?: '-' }}</span>
                            <span>{{ $item->rule_last_update ?: '-' }}</span>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="5" class="py-12 text-center">
                        <div class="flex flex-col items-center gap-2 text-zinc-400">
                            <flux:icon name="circle-stack" class="size-10" />
                            <p class="text-sm">Belum ada data rule number ditemukan.</p>
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

    {{-- Import Modal --}}
    <x-organisms.modal wire:model="showImportModal" maxWidth="xl" title="Import Kamus Rule Number">
        <div class="space-y-5">
            <div>
                <flux:label class="text-[10px] font-black uppercase tracking-wider text-zinc-500 mb-1.5 block">Berkas
                    CSV</flux:label>
                <x-atoms.document-dropzone wireModel="csvFile" accept=".csv" maxSizeMb="20"
                    label="Seret & lepas atau klik untuk memilih berkas CSV">
                    {{-- Preview setelah upload selesai --}}
                    @if ($previewDone)
                        <div class="mt-4 space-y-3 animate-in fade-in slide-in-from-bottom-2 duration-300">
                            <div class="grid grid-cols-1 gap-3">
                                <div
                                    class="p-3 bg-zinc-50 dark:bg-primary-dark-900/50 rounded-xl border border-zinc-100 dark:border-primary-dark-800 text-center">
                                    <p class="text-xl font-black text-zinc-800 dark:text-zinc-100">
                                        {{ number_format($importTotalRows) }}</p>
                                    <p class="text-[10px] uppercase font-bold text-zinc-400 tracking-widest mt-0.5">
                                        Total Baris</p>
                                </div>
                            </div>

                            <div
                                class="p-4 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 rounded-xl space-y-3">
                                <label
                                    class="flex items-center gap-3 cursor-pointer p-2.5 bg-white/70 dark:bg-white/5 rounded-lg border border-amber-200 dark:border-amber-800/50">
                                    <input type="checkbox" wire:model="replaceExisting"
                                        class="size-4 rounded border-amber-300 text-amber-600">
                                    <span class="text-[11px] font-bold text-amber-800 dark:text-amber-400">Timpa & ganti
                                        semua data (Truncate)</span>
                                </label>
                            </div>
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
                            {{ $replaceExisting ? 'Ganti Keseluruhan & Import' : 'Import Sekarang' }}
                        </x-atoms.button>
                    @endif
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>
</div>
