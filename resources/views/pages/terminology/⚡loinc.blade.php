<?php

use App\Jobs\ImportLoincJob;
use App\Models\Terminology\Loinc;
use App\Services\TerminologyCacheService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Log;

new #[Layout('layouts::app')] #[Title('Master LOINC')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterProperty = '';
    public string $filterSystem = '';
    public string $filterClass = '';
    public string $importPath = '';
    public bool $showImportModal = false;
    public bool $importing = false;

    // Detail
    public array $selectedLoinc = [];
    public bool $showDetailModal = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    public function updatingFilterProperty(): void
    {
        $this->resetPage();
    }
    public function updatingFilterSystem(): void
    {
        $this->resetPage();
    }
    public function updatingFilterClass(): void
    {
        $this->resetPage();
    }

    public function showDetail(string $loincNum): void
    {
        $loinc = Loinc::find($loincNum);
        $this->selectedLoinc = $loinc ? $loinc->toArray() : [];
        $this->showDetailModal = true;
    }

    public function resetFilters(): void
    {
        $this->filterProperty = '';
        $this->filterSystem = '';
        $this->filterClass = '';
        $this->resetPage();
    }

    public function openImportModal(): void
    {
        $this->resetErrorBag();
        $this->importPath = '';
        $this->showImportModal = true;
    }

    /** Dipanggil Alpine setelah semua chunk terakit di server. */
    public function setImportPath(string $path): void
    {
        $tempBase = realpath(storage_path('app/temp'));
        abort_if(!$tempBase || !str_starts_with(realpath($path) ?: '', $tempBase), 403, 'Path tidak valid.');

        $this->importPath = $path;
    }

    public function import(): void
    {
        if (empty($this->importPath) || !file_exists($this->importPath)) {
            $this->addError('importPath', 'File belum terupload atau tidak ditemukan.');
            return;
        }

        $this->importing = true;

        try {
            ImportLoincJob::dispatch($this->importPath);

            $this->importPath = '';
            $this->showImportModal = false;
            $this->dispatch('notify', variant: 'success', title: 'Import Dimulai', message: 'Proses import berjalan di background.');
        } catch (\Exception $e) {
            Log::error('Import Dispatch Failed: ' . $e->getMessage());
            $this->dispatch('notify', variant: 'danger', title: 'Import Gagal', message: 'Gagal memulai proses import.');
        } finally {
            $this->importing = false;
        }
    }

    public function with()
    {
        $loincs = Loinc::query()
            ->when($this->search, function ($q) {
                $term = $this->search;
                $q->where(function ($sub) use ($term) {
                    // loinc_num: prefix/exact match — memanfaatkan primary key index
                    $sub->where('loinc_num', 'like', $term . '%')
                        // component & long_common_name: FULLTEXT index (jauh lebih cepat dari LIKE '%...%')
                        ->orWhereFullText(['component', 'long_common_name'], $term);
                });
                // $q->whereAny(['loinc_num', 'component', 'long_common_name'], 'like', "%{$term}%");
            })
            ->when($this->filterProperty, fn($q) => $q->where('property', $this->filterProperty))
            ->when($this->filterSystem, fn($q) => $q->where('system', $this->filterSystem))
            ->when($this->filterClass, fn($q) => $q->where('class', $this->filterClass))
            ->orderBy('loinc_num')
            ->paginate(20);

        // Daftar filter di-cache agar tidak melakukan full-table scan setiap render
        // Versi LOINC naik saat import selesai → cache otomatis stale
        $properties = TerminologyCacheService::rememberLoincFilter('property', fn() => Loinc::distinct()->orderBy('property')->pluck('property')->filter()->values()->toArray());
        $systems = TerminologyCacheService::rememberLoincFilter('system', fn() => Loinc::distinct()->orderBy('system')->pluck('system')->filter()->values()->toArray());
        $classes = TerminologyCacheService::rememberLoincFilter('class', fn() => Loinc::distinct()->orderBy('class')->pluck('class')->filter()->values()->toArray());

        return [
            'loincs' => $loincs,
            'properties' => $properties,
            'systems' => $systems,
            'classes' => $classes,
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="Master LOINC" subtitle="Kelola data LOINC (Logical Observation Identifiers Names and Codes)">
        <x-slot:actions>
            <x-atoms.button wire:click="openImportModal" icon="arrow-up-tray" variant="primary">Import CSV</x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-organisms.data-panel title="Data LOINC" subtitle="Daftar observasi dan identifikasi (LOINC)">
        <x-slot:filter>
            <div class="mb-4 space-y-3">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="Cari LOINC Num / Component / Long Common Name..." clearable />

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="flex-1">
                        <flux:select wire:model.live="filterProperty" placeholder="Semua Property" size="sm">
                            <flux:select.option value="">Semua Property</flux:select.option>
                            @foreach ($properties as $prop)
                                <flux:select.option value="{{ $prop }}">{{ $prop }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="flex-1">
                        <flux:select wire:model.live="filterSystem" placeholder="Semua System" size="sm">
                            <flux:select.option value="">Semua System</flux:select.option>
                            @foreach ($systems as $sys)
                                <flux:select.option value="{{ $sys }}">{{ $sys }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="flex-1">
                        <flux:select wire:model.live="filterClass" placeholder="Semua Class" size="sm">
                            <flux:select.option value="">Semua Class</flux:select.option>
                            @foreach ($classes as $cls)
                                <flux:select.option value="{{ $cls }}">{{ $cls }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    @if ($filterProperty || $filterSystem || $filterClass)
                        <x-atoms.button wire:click="resetFilters" icon="x-mark" variant="ghost" size="sm">
                            Reset
                        </x-atoms.button>
                    @endif
                </div>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading>LOINC Num</x-atoms.table-heading>
                <x-atoms.table-heading>Component</x-atoms.table-heading>
                <x-atoms.table-heading>Property</x-atoms.table-heading>
                <x-atoms.table-heading>System</x-atoms.table-heading>
                <x-atoms.table-heading>Class</x-atoms.table-heading>
                <x-atoms.table-heading>Long Common Name</x-atoms.table-heading>
                <x-atoms.table-heading align="right"><span class="sr-only">Aksi</span></x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($loincs as $loinc)
                <x-molecules.table-row>
                    <x-atoms.table-cell class="font-mono font-medium text-zinc-900 dark:text-primary-dark-100">
                        {{ $loinc->loinc_num }}
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-500 dark:text-primary-dark-400 max-w-xs">
                        {{ Str::limit($loinc->component, 35) }}
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-500 whitespace-nowrap dark:text-primary-dark-400">
                        {{ $loinc->property }}
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-500 whitespace-nowrap dark:text-primary-dark-400">
                        {{ $loinc->system }}
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-500 whitespace-nowrap dark:text-primary-dark-400">
                        {{ $loinc->class }}
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-500 dark:text-primary-dark-400 max-w-xs">
                        {{ Str::limit($loinc->long_common_name, 50) }}
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="right">
                        <x-atoms.button size="xs" variant="ghost" icon="eye"
                            wire:click="showDetail('{{ $loinc->loinc_num }}')">Detail</x-atoms.button>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="7" class="px-6 py-10 text-center text-zinc-500 dark:text-primary-dark-400">
                        <div class="flex flex-col items-center justify-center">
                            <flux:icon name="magnifying-glass" class="w-10 h-10 mb-2 text-zinc-300 dark:text-primary-dark-600" />
                            <p>Tidak ada data ditemukan</p>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>
        
        <x-slot:footer>
            @php
                $pageNumbers = [];
                $lastPage = $loincs->lastPage();
                $current  = $loincs->currentPage();
                if ($lastPage <= 7) {
                    $pageNumbers = range(1, $lastPage);
                } elseif ($current <= 4) {
                    $pageNumbers = [...range(1, 5), null, $lastPage];
                } elseif ($current >= $lastPage - 3) {
                    $pageNumbers = [1, null, ...range($lastPage - 4, $lastPage)];
                } else {
                    $pageNumbers = [1, null, $current - 1, $current, $current + 1, null, $lastPage];
                }
            @endphp
            <x-molecules.pagination
                :page="$loincs->currentPage()"
                :total-page="$loincs->lastPage()"
                :total="$loincs->total()"
                :page-numbers="$pageNumbers"
                on-prev="previousPage"
                on-next="nextPage"
                on-goto="gotoPage"
            />
        </x-slot:footer>
    </x-organisms.data-panel>

    {{-- Modal Detail LOINC --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="xl" title="Detail LOINC">
        @if (!empty($selectedLoinc))
            <div class="space-y-4" wire:key="loinc-detail-{{ $selectedLoinc['loinc_num'] }}">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-primary-dark-100">
                        {{ $selectedLoinc['long_common_name'] ?? $selectedLoinc['loinc_num'] }}
                    </h3>
                    <p class="mt-0.5 text-xs font-mono text-zinc-500 dark:text-primary-dark-400">
                        LOINC: {{ $selectedLoinc['loinc_num'] }}
                        @if (!empty($selectedLoinc['status']))
                            &nbsp;&middot;&nbsp;
                            <flux:badge size="sm"
                                color="{{ $selectedLoinc['status'] === 'ACTIVE' ? 'green' : 'zinc' }}"
                                class="inline-flex">
                                {{ $selectedLoinc['status'] }}
                            </flux:badge>
                        @endif
                    </p>
                </div>

                @php
                $fields = array_filter([
                    'LOINC Num' => $selectedLoinc['loinc_num'] ?? null,
                    'Component' => $selectedLoinc['component'] ?? null,
                    'Property' => $selectedLoinc['property'] ?? null,
                    'Time Aspect' => $selectedLoinc['time_aspct'] ?? null,
                    'System' => $selectedLoinc['system'] ?? null,
                    'Scale Type' => $selectedLoinc['scale_typ'] ?? null,
                    'Method Type' => $selectedLoinc['method_typ'] ?? null,
                    'Class' => $selectedLoinc['class'] ?? null,
                    'Class Type' => $selectedLoinc['classtype'] ?? null,
                    'Long Common Name' => $selectedLoinc['long_common_name'] ?? null,
                    'Short Name' => $selectedLoinc['shortname'] ?? null,
                    'Status' => $selectedLoinc['status'] ?? null,
                    'Versi Pertama Rilis' => $selectedLoinc['version_first_released'] ?? null,
                    'Versi Terakhir Ubah' => $selectedLoinc['version_last_changed'] ?? null,
                    'Copyright' => $selectedLoinc['external_copyright_notice'] ?? null,
                ]);
                @endphp

                <div
                    class="rounded-lg ring-1 ring-zinc-200 dark:ring-primary-dark-700 overflow-hidden divide-y divide-zinc-100 dark:divide-primary-dark-700/60">
                    @foreach ($fields as $label => $value)
                        <div class="flex gap-4 px-4 py-2.5 odd:bg-zinc-50 dark:odd:bg-primary-dark-900/30">
                            <span
                                class="text-xs font-medium text-zinc-400 dark:text-primary-dark-500 shrink-0 w-40">{{ $label }}</span>
                            <span
                                class="text-xs text-zinc-800 dark:text-primary-dark-200 break-words">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>

            </div>
        @endif

        <x-slot:footer>
            <div class="flex justify-end w-full">
                <x-atoms.button wire:click="$set('showDetailModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    <!-- Import Modal -->
    <x-organisms.modal wire:model="showImportModal" maxWidth="md" title="Import Data LOINC" description="Upload file CSV LOINC terbaru. Semua data lama akan dihapus dan digantikan dengan data baru.">
        <div class="space-y-6" x-data="{
            uploading: false,
            uploaded: false,
            error: '',
            fileName: '',
            fileSize: 0,
            uploadedBytes: 0,
            progress: 0,
            chunkSize: 1 * 1024 * 1024,
        
            fmt(bytes) {
                if (bytes === 0) return '0 B';
                if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
            },
        
            reset() {
                this.uploading = false;
                this.uploaded = false;
                this.error = '';
                this.fileName = '';
                this.fileSize = 0;
                this.uploadedBytes = 0;
                this.progress = 0;
            },
        
            xsrfToken() {
                const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
                return m ? decodeURIComponent(m[1]) : '';
            },
        
            async doUpload(file) {
                this.reset();
                this.fileName = file.name;
                this.fileSize = file.size;
                this.uploading = true;
        
                const totalChunks = Math.ceil(file.size / this.chunkSize);
                const uid = ([1e7] + -1e3 + -4e3 + -8e3 + -1e11).replace(/[018]/g, c =>
                    (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16));
        
                try {
                    for (let i = 0; i < totalChunks; i++) {
                        const start = i * this.chunkSize;
                        const end = Math.min(start + this.chunkSize, file.size);
                        const form = new FormData();
                        form.append('chunk', file.slice(start, end), file.name);
                        form.append('upload_id', uid);
                        form.append('chunk_index', i);
                        form.append('total_chunks', totalChunks);
                        form.append('filename', file.name);
        
                        const res = await fetch('/upload/chunk', {
                            method: 'POST',
                            headers: { 'X-XSRF-TOKEN': this.xsrfToken() },
                            body: form,
                        });
        
                        if (!res.ok) {
                            throw new Error(res.status === 413 ?
                                'Chunk masih terlalu besar (413). Hubungi administrator.' :
                                `Upload gagal pada bagian ${i + 1} (HTTP ${res.status}).`);
                        }
        
                        this.uploadedBytes = end;
                        this.progress = Math.round(end / file.size * 100);
        
                        const data = await res.json();
                        if (data.done) {
                            await $wire.setImportPath(data.path);
                            this.uploaded = true;
                        }
                    }
                } catch (err) {
                    this.error = err.message || 'Upload gagal. Silakan coba lagi.';
                    this.uploaded = false;
                } finally {
                    this.uploading = false;
                }
            }
        }">
            <div class="space-y-3">
                <div>
                    <label class="block mb-1 text-sm font-medium text-zinc-700 dark:text-primary-dark-300">File
                        CSV</label>
                    <input type="file" accept=".csv,.txt" x-on:change="doUpload($event.target.files[0])"
                        x-bind:disabled="uploading"
                        class="block w-full text-sm text-zinc-700 dark:text-primary-dark-300 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-medium file:bg-zinc-100 dark:file:bg-primary-dark-700 file:text-zinc-700 dark:file:text-primary-dark-300 hover:file:bg-zinc-200 dark:hover:file:bg-primary-dark-600 disabled:opacity-50 cursor-pointer">
                    <!-- Info ukuran file -->
                    <p x-show="fileSize > 0" class="mt-1 text-xs text-zinc-400 dark:text-primary-dark-500"
                        x-text="'Ukuran: ' + fmt(fileSize)"></p>
                </div>

                <!-- Progress bar -->
                <div x-show="uploading || (uploaded && !error)" class="space-y-1">
                    <div class="flex justify-between text-xs text-zinc-500 dark:text-primary-dark-400">
                        <span x-show="uploading">
                            Mengupload... <span x-text="fmt(uploadedBytes)"></span> / <span
                                x-text="fmt(fileSize)"></span>
                        </span>
                        <span x-show="uploaded && !uploading" class="text-green-600 dark:text-green-400">
                            Upload selesai — siap diimport
                        </span>
                        <span x-text="progress + '%'"></span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-700">
                        <div class="h-2 transition-all duration-300 rounded-full"
                            x-bind:class="uploaded ? 'bg-green-500' : 'bg-blue-500'"
                            x-bind:style="'width: ' + progress + '%'"></div>
                    </div>
                </div>

                <!-- Error -->
                <p x-show="error" x-text="error" class="text-sm text-red-500"></p>
                @error('importPath')
                <p class="text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2 w-full">
                    <x-atoms.button wire:click="$set('showImportModal', false)" variant="ghost" x-bind:disabled="uploading">
                        Batal
                    </x-atoms.button>
                    <x-atoms.button wire:click="import" variant="primary" :loading="$importing"
                        x-bind:disabled="uploading || !uploaded || error !== ''">
                        Start Import
                    </x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>
</div>