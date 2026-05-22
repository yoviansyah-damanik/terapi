<?php

use App\Jobs\ImportIcdMmJob;
use App\Models\Terminology\IcdMm;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app', ['title' => 'ICD-MM'])] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterVersion = '';
    public string $filterAnnex = '';
    public string $filterGroup = '';

    public bool $showImportModal = false;
    public string $importPath = '';
    public array $importVersions = [];
    public array $conflictVersions = [];
    public array $conflictCounts = [];
    public int $importTotalRows = 0;
    public bool $importing = false;
    public bool $showConflictWarning = false;
    public bool $previewDone = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    public function updatingFilterVersion(): void
    {
        $this->resetPage();
    }
    public function updatingFilterAnnex(): void
    {
        $this->filterGroup = '';
        $this->resetPage();
    }
    public function updatingFilterGroup(): void
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
        $this->importPath = '';
        $this->importVersions = $this->conflictVersions = $this->conflictCounts = [];
        $this->importTotalRows = 0;
        $this->importing = $this->showConflictWarning = $this->previewDone = false;
        $this->resetErrorBag();
    }

    public function setImportPath(string $path): void
    {
        $tempBase = realpath(storage_path('app/temp'));
        abort_if(!$tempBase || !str_starts_with(realpath($path) ?: '', $tempBase), 403, 'Path tidak valid.');
        $this->importPath = $path;
    }

    public function previewImport(): void
    {
        $this->resetErrorBag();
        $this->importVersions = $this->conflictVersions = $this->conflictCounts = [];
        $this->showConflictWarning = $this->previewDone = false;

        if (empty($this->importPath) || !file_exists($this->importPath)) {
            $this->addError('importPath', 'File tidak ditemukan di server.');
            return;
        }

        $handle = fopen($this->importPath, 'r');
        if (!$handle) {
            $this->addError('importPath', 'Gagal membuka file CSV.');
            return;
        }

        $rawHeaders = fgetcsv($handle, 0, ',');
        $headers = array_map(fn($h) => strtolower(trim($h)), $rawHeaders ?: []);

        // ICD-MM butuh kolom: level, code, display, version
        if (!in_array('code', $headers) || !in_array('version', $headers) || !in_array('level', $headers)) {
            fclose($handle);
            $this->addError('importPath', 'Format CSV tidak valid. Kolom yang dibutuhkan: LEVEL, CODE, DISPLAY, VERSION.');
            return;
        }

        $versions = [];
        $rowCount = 0;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($headers) !== count($row)) {
                continue;
            }
            $data = array_combine($headers, $row);
            $v = trim($data['version'] ?? '');
            $c = trim($data['code'] ?? '');
            if ($v && $c && !str_starts_with($c, 'GROUP-') && !str_contains($c, ' ')) {
                $versions[$v] = true;
                $rowCount++;
            }
        }

        fclose($handle);
        $this->importTotalRows = $rowCount;
        $this->importVersions = array_keys($versions);

        foreach ($this->importVersions as $v) {
            $count = IcdMm::countByVersion($v);
            if ($count > 0) {
                $this->conflictVersions[] = $v;
                $this->conflictCounts[$v] = $count;
            }
        }

        $this->showConflictWarning = !empty($this->conflictVersions);
        $this->previewDone = true;
    }

    public function import(): void
    {
        if (!empty($this->conflictVersions)) {
            return;
        }
        $this->doDispatch([]);
    }

    public function forceImport(): void
    {
        $this->doDispatch($this->conflictVersions);
    }

    private function doDispatch(array $forceVersions): void
    {
        if (empty($this->importPath) || !file_exists($this->importPath)) {
            $this->addError('importPath', 'File tidak ditemukan.');
            return;
        }
        $this->importing = true;
        try {
            ImportIcdMmJob::dispatch($this->importPath, $forceVersions);
            $this->resetImportState();
            $this->showImportModal = false;
            $this->dispatch('toast', type: 'success', message: 'Import berjalan di background. Data akan segera tersedia.');
        } catch (\Exception $e) {
            $this->importing = false;
            $this->dispatch('toast', type: 'error', message: 'Gagal memulai import: ' . $e->getMessage());
        }
    }

    public function deleteVersion(string $version): void
    {
        IcdMm::where('version', $version)->delete();
        if ($this->filterVersion === $version) {
            $this->filterVersion = '';
        }
        $this->dispatch('toast', type: 'success', message: "Versi {$version} berhasil dihapus.");
    }

    public function with(): array
    {
        $data = IcdMm::when($this->search, fn($q) => $q->where(fn($s) => $s->where('code', 'like', $this->search . '%')->orWhere('display', 'like', '%' . $this->search . '%')))->when($this->filterVersion, fn($q) => $q->where('version', $this->filterVersion))->when($this->filterAnnex, fn($q) => $q->where('annex', $this->filterAnnex))->when($this->filterGroup, fn($q) => $q->where('group_code', $this->filterGroup))->orderBy('version')->orderBy('annex')->orderBy('group_code')->orderBy('code')->paginate(25);

        return [
            'data' => $data,
            'versions' => IcdMm::getVersions(),
            'annexes' => IcdMm::getAnnexes($this->filterVersion),
            'groups' => IcdMm::getGroups($this->filterVersion, $this->filterAnnex),
        ];
    }
};
?>

<div>
    <x-organisms.data-panel title="Source Terminology — ICD-MM" subtitle="International Classification of Diseases for Maternal Mortality">
        <x-slot:actions>
            <x-atoms.button wire:click="openImportModal" icon="arrow-up-tray" variant="primary">
                Import CSV
            </x-atoms.button>
        </x-slot:actions>

        <x-slot:filter>
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div class="flex-1 min-w-48">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari kode atau deskripsi..."
                        icon="magnifying-glass" size="sm" />
                </div>
        
                <div class="min-w-36">
                    <flux:select wire:model.live="filterVersion" size="sm">
                        <option value="">Semua Versi</option>
                        @foreach ($versions as $v)
                            <option value="{{ $v }}">{{ $v }}</option>
                        @endforeach
                    </flux:select>
                </div>
        
                <div class="min-w-28">
                    <flux:select wire:model.live="filterAnnex" size="sm">
                        <option value="">Semua Annex</option>
                        @foreach ($annexes as $annex)
                            <option value="{{ $annex }}">Annex {{ $annex }}</option>
                        @endforeach
                    </flux:select>
                </div>
        
                <div class="min-w-48">
                    <flux:select wire:model.live="filterGroup" size="sm">
                        <option value="">Semua Group</option>
                        @foreach ($groups as $g)
                            <option value="{{ $g->group_code }}">{{ $g->group_code }} — {{ Str::limit($g->group_display, 40) }}
                            </option>
                        @endforeach
                    </flux:select>
                </div>
        
                @if ($search || $filterVersion || $filterAnnex || $filterGroup)
                    <x-atoms.button
                        wire:click="$set('search', ''); $set('filterVersion', ''); $set('filterAnnex', ''); $set('filterGroup', '')"
                        variant="ghost" size="sm" icon="x-mark">
                        Reset
                    </x-atoms.button>
                @endif
        
                @if ($filterVersion)
                    <x-atoms.button wire:click="deleteVersion('{{ $filterVersion }}')"
                        wire:confirm="Hapus semua data versi {{ $filterVersion }} ICD-MM?" icon="trash" variant="ghost"
                        size="sm" class="text-red-500 hover:text-red-700">
                        Hapus Versi
                    </x-atoms.button>
                @endif
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-32">Kode</x-atoms.table-heading>
                <x-atoms.table-heading>Deskripsi</x-atoms.table-heading>
                <x-atoms.table-heading class="w-24">Annex</x-atoms.table-heading>
                <x-atoms.table-heading class="w-40">Group</x-atoms.table-heading>
                <x-atoms.table-heading class="w-28">Versi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($data as $row)
                <x-molecules.table-row :key="$row->id">
                    <x-atoms.table-cell>
                        <span class="font-mono font-medium text-zinc-900 dark:text-primary-dark-100">{{ $row->code }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">
                        {{ $row->display }}
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        @if ($row->annex)
                            <flux:badge color="blue" size="sm">Annex {{ $row->annex }}</flux:badge>
                        @else
                            <span class="text-zinc-400 dark:text-primary-dark-600 text-xs">—</span>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        @if ($row->group_code)
                            <span
                                class="font-mono font-medium text-zinc-700 dark:text-primary-dark-300">{{ $row->group_code }}</span>
                            @if ($row->group_display)
                                <br><span
                                    class="text-[11px] text-zinc-400 dark:text-primary-dark-500">{{ Str::limit($row->group_display, 35) }}</span>
                            @endif
                        @else
                            <span class="text-zinc-400 dark:text-primary-dark-600">—</span>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <flux:badge color="zinc" size="sm">{{ $row->version }}</flux:badge>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="5" class="py-12 text-center">
                        <div class="flex flex-col items-center gap-2 text-zinc-400 dark:text-primary-dark-600">
                            <flux:icon name="circle-stack" class="w-10 h-10" />
                            <div class="text-sm">Belum ada data ICD-MM</div>
                            <x-atoms.button wire:click="openImportModal" icon="arrow-up-tray" variant="ghost"
                                size="sm">
                                Import CSV
                            </x-atoms.button>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        <x-slot:footer>
            @php
                $pageNumbers = [];
                $lastPage  = $data->lastPage();
                $current   = $data->currentPage();
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
                :page="$data->currentPage()"
                :total-page="$data->lastPage()"
                :total="$data->total()"
                :page-numbers="$pageNumbers"
                on-prev="previousPage"
                on-next="nextPage"
                on-goto="gotoPage"
            />
        </x-slot:footer>
    </x-organisms.data-panel>

    {{-- ===================== Modal Import ===================== --}}
    <x-organisms.modal wire:model="showImportModal" maxWidth="md" title="Import CSV — ICD-MM">
        <div class="space-y-5" x-data="{
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
                const uploadId = Math.random().toString(36).slice(2) + Date.now();
                const totalChunks = Math.ceil(file.size / this.chunkSize);
                for (let i = 0; i < totalChunks; i++) {
                    const chunk = file.slice(i * this.chunkSize, (i + 1) * this.chunkSize);
                    const form = new FormData();
                    form.append('chunk', chunk);
                    form.append('upload_id', uploadId);
                    form.append('chunk_index', i);
                    form.append('total_chunks', totalChunks);
                    form.append('filename', file.name);
                    const res = await fetch('/upload/chunk', {
                        method: 'POST',
                        headers: { 'X-XSRF-TOKEN': this.xsrfToken() },
                        body: form,
                    });
                    if (!res.ok) {
                        this.error = 'Upload gagal pada chunk ' + i;
                        this.uploading = false;
                        return;
                    }
                    this.uploadedBytes = Math.min((i + 1) * this.chunkSize, file.size);
                    this.progress = Math.round((this.uploadedBytes / this.fileSize) * 100);
                    const json = await res.json();
                    if (json.done) {
                        this.uploading = false;
                        this.uploaded = true;
                        await $wire.setImportPath(json.path);
                        await $wire.previewImport();
                    }
                }
            }
        }">

            <div>
                <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                    Format CSV: kolom
                    <code
                        class="font-mono text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 py-0.5 rounded">LEVEL</code>,
                    <code
                        class="font-mono text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 py-0.5 rounded">CODE</code>,
                    <code
                        class="font-mono text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 py-0.5 rounded">DISPLAY</code>,
                    <code
                        class="font-mono text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 py-0.5 rounded">VERSION</code>
                </p>
            </div>

            {{-- Upload Area --}}
            <div x-show="!uploaded">
                <label
                    class="flex flex-col items-center justify-center w-full h-36 border-2 border-dashed rounded-lg cursor-pointer transition-colors"
                    :class="uploading
                        ?
                        'border-primary-300 dark:border-primary-700 bg-primary-50 dark:bg-primary-900/20' :
                        'border-zinc-300 dark:border-primary-dark-600 bg-zinc-50 dark:bg-primary-dark-900/30 hover:bg-zinc-100 dark:hover:bg-primary-dark-800'"
                    x-on:dragover.prevent x-on:drop.prevent="if(!uploading) doUpload($event.dataTransfer.files[0])">

                    <div class="flex flex-col items-center justify-center gap-2 text-center px-4" x-show="!uploading">
                        <flux:icon name="arrow-up-tray" class="w-8 h-8 text-zinc-400 dark:text-primary-dark-600" />
                        <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                            Drag &amp; drop atau <span class="text-primary-600 dark:text-primary-400 font-medium">pilih
                                file</span>
                        </p>
                        <p class="text-xs text-zinc-400 dark:text-primary-dark-600">CSV / TXT</p>
                    </div>

                    <div class="w-full px-6" x-show="uploading">
                        <p class="text-sm text-center text-zinc-700 dark:text-primary-dark-300 mb-3">
                            Mengunggah <span x-text="fileName" class="font-medium"></span>...
                        </p>
                        <div class="w-full bg-zinc-200 dark:bg-primary-dark-700 rounded-full h-2">
                            <div class="bg-primary-500 h-2 rounded-full transition-all duration-200"
                                :style="'width:' + progress + '%'"></div>
                        </div>
                        <p class="text-xs text-center text-zinc-500 dark:text-primary-dark-400 mt-2">
                            <span x-text="fmt(uploadedBytes)"></span> / <span x-text="fmt(fileSize)"></span>
                            (<span x-text="progress"></span>%)
                        </p>
                    </div>

                    <input type="file" class="hidden" accept=".csv,.txt"
                        x-on:change="doUpload($event.target.files[0])" :disabled="uploading" />
                </label>
                <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600 dark:text-red-400"></p>
            </div>

            {{-- Preview setelah upload --}}
            <div x-show="uploaded" class="space-y-4">
                <div
                    class="flex items-center gap-3 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <flux:icon name="document-check" class="w-5 h-5 text-green-600 dark:text-green-400 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-green-800 dark:text-green-300" x-text="fileName"></p>
                        <p class="text-xs text-green-600 dark:text-green-500" x-text="fmt(fileSize)"></p>
                    </div>
                    <x-atoms.button x-on:click="reset(); $wire.resetImportState()"
                        class="text-green-600 dark:text-green-400 hover:text-green-800">
                        <flux:icon name="x-mark" class="w-4 h-4" />
                    </x-atoms.button>
                </div>

                <div wire:loading wire:target="previewImport"
                    class="flex items-center gap-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4" />
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Menganalisis versi dalam file...
                </div>

                @if ($previewDone)
                    <div wire:loading.remove wire:target="previewImport">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 bg-zinc-50 dark:bg-primary-dark-900/50 rounded-lg text-center">
                                <p class="text-xl font-bold text-zinc-900 dark:text-primary-dark-100">
                                    {{ number_format($importTotalRows) }}</p>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Total Kode</p>
                            </div>
                            <div class="p-3 bg-zinc-50 dark:bg-primary-dark-900/50 rounded-lg text-center">
                                <p class="text-xl font-bold text-zinc-900 dark:text-primary-dark-100">
                                    {{ count($importVersions) }}</p>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Versi Terdeteksi</p>
                            </div>
                        </div>

                        @if (!empty($importVersions))
                            <div class="mt-3">
                                <p class="text-xs font-medium text-zinc-500 dark:text-primary-dark-400 mb-1.5">Versi
                                    dalam
                                    file:</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($importVersions as $v)
                                        <span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-mono
                                            {{ in_array($v, $conflictVersions)
                                                ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400'
                                                : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' }}">
                                            @if (in_array($v, $conflictVersions))
                                                <flux:icon name="exclamation-triangle" class="w-3 h-3" />
                                            @else
                                                <flux:icon name="check" class="w-3 h-3" />
                                            @endif
                                            {{ $v }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($showConflictWarning)
                            <div
                                class="mt-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <flux:icon name="exclamation-triangle"
                                        class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Versi sudah
                                            ada di database!</p>
                                        <ul class="mt-2 space-y-1">
                                            @foreach ($conflictVersions as $v)
                                                <li class="text-xs text-amber-700 dark:text-amber-400">
                                                    <span class="font-mono font-medium">{{ $v }}</span>
                                                    &mdash; {{ number_format($conflictCounts[$v]) }} kode ada di
                                                    database
                                                </li>
                                            @endforeach
                                        </ul>
                                        <p class="text-xs text-amber-700 dark:text-amber-400 mt-2 font-medium">
                                            Melanjutkan akan menghapus data versi di atas terlebih dahulu.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @error('importPath')
                            <div
                                class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-sm text-red-700 dark:text-red-400">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                @endif
            </div>

            {{-- Aksi --}}
            <x-slot:footer>
                <div class="flex justify-end gap-2 w-full pt-1">
                    <x-atoms.button wire:click="$set('showImportModal', false)" variant="ghost">Batal</x-atoms.button>
                    @if ($previewDone)
                        @if ($showConflictWarning)
                            <x-atoms.button wire:click="forceImport" variant="primary" wire:loading.attr="disabled"
                                wire:target="forceImport" class="!bg-amber-600 hover:!bg-amber-700">
                                <span wire:loading.remove wire:target="forceImport">Ganti &amp; Import</span>
                                <span wire:loading wire:target="forceImport">Memproses...</span>
                            </x-atoms.button>
                        @else
                            <x-atoms.button wire:click="import" variant="primary" wire:loading.attr="disabled"
                                wire:target="import">
                                <span wire:loading.remove wire:target="import">Import Sekarang</span>
                                <span wire:loading wire:target="import">Memproses...</span>
                            </x-atoms.button>
                        @endif
                    @endif
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>
</div>
