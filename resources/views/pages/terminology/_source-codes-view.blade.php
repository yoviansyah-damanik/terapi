<div>
    <x-ui.page-header :title="'Source Terminology — ' . $typeLabel" subtitle="Master data terminologi yang dapat diimpor dari file CSV">
        <x-slot:actions>
            <x-atoms.button wire:click="openImportModal" icon="arrow-up-tray" variant="primary" size="sm">
                Import CSV
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-organisms.data-panel title="Data {{ $typeLabel }}" subtitle="Manajemen referensi data dari file CSV">
        <x-slot:filter>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-48">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari kode atau deskripsi..."
                        icon="magnifying-glass" size="sm" />
                </div>
                <div class="min-w-40">
                    <flux:select wire:model.live="filterVersion" size="sm">
                        <option value="">Semua Versi</option>
                        @foreach ($versions as $v)
                            <option value="{{ $v }}">{{ $v }}</option>
                        @endforeach
                    </flux:select>
                </div>
                @if ($search || $filterVersion)
                    <x-atoms.button wire:click="$set('search', ''); $set('filterVersion', '')" variant="ghost" size="sm" icon="x-mark">
                        Reset
                    </x-atoms.button>
                @endif
                @if ($filterVersion)
                    <x-atoms.button wire:click="deleteVersion('{{ $filterVersion }}')"
                        wire:confirm="Hapus semua data versi {{ $filterVersion }} untuk {{ $typeLabel }}?" icon="trash"
                        variant="ghost" size="sm" class="text-red-500 hover:text-red-700">
                        Hapus Versi
                    </x-atoms.button>
                @endif
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-40">Kode</x-atoms.table-heading>
                <x-atoms.table-heading>Deskripsi</x-atoms.table-heading>
                <x-atoms.table-heading class="w-32">Versi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($data as $row)
                <x-molecules.table-row :key="$row->id">
                    <x-atoms.table-cell>
                        <span class="font-mono text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ $row->code }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <span class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $row->display }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <flux:badge color="zinc" size="sm">{{ $row->version }}</flux:badge>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="3" class="px-4 py-12 text-center">
                        <div class="flex flex-col items-center gap-2 text-zinc-400 dark:text-primary-dark-600">
                            <flux:icon name="circle-stack" class="w-10 h-10" />
                            <p class="text-sm">Belum ada data {{ $typeLabel }}</p>
                            <x-atoms.button wire:click="openImportModal" icon="arrow-up-tray" variant="ghost" size="sm">
                                Import CSV
                            </x-atoms.button>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        <x-slot:footer>
            @php
                $hasPages = $data->hasPages();
                $pageNumbers = [];
                if ($hasPages) {
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
    <x-organisms.modal wire:model="showImportModal" maxWidth="xl" title="Import CSV — {{ $typeLabel }}">
        <div class="space-y-4" x-data="{
                <p class="mt-1 text-sm text-zinc-500 dark:text-primary-dark-400">
                    Format CSV: kolom
                    <code
                        class="font-mono text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 py-0.5 rounded">code</code>,
                    <code
                        class="font-mono text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 py-0.5 rounded">display</code>,
                    <code
                        class="font-mono text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1 py-0.5 rounded">version</code>
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

            {{-- Setelah Upload — Preview --}}
            <div x-show="uploaded" class="space-y-4">
                {{-- Info File --}}
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

                {{-- Loading preview --}}
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

                {{-- Hasil Preview --}}
                @if ($previewDone)
                    <div wire:loading.remove wire:target="previewImport">
                        {{-- Ringkasan --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 bg-zinc-50 dark:bg-primary-dark-900/50 rounded-lg text-center">
                                <p class="text-xl font-bold text-zinc-900 dark:text-primary-dark-100">
                                    {{ number_format($importTotalRows) }}</p>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Total Baris</p>
                            </div>
                            <div class="p-3 bg-zinc-50 dark:bg-primary-dark-900/50 rounded-lg text-center">
                                <p class="text-xl font-bold text-zinc-900 dark:text-primary-dark-100">
                                    {{ count($importVersions) }}</p>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Versi Terdeteksi</p>
                            </div>
                        </div>

                        {{-- Daftar Versi --}}
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

                        {{-- Peringatan Konflik --}}
                        @if ($showConflictWarning)
                            <div
                                class="mt-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <flux:icon name="exclamation-triangle"
                                        class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                                            Versi sudah ada di database!
                                        </p>
                                        <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                                            Versi berikut sudah ada untuk <strong>{{ $typeLabel }}</strong>:
                                        </p>
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
                                            Melanjutkan import akan menghapus data versi di atas terlebih dahulu.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Error validasi --}}
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
                <div class="flex justify-end gap-2 w-full">
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
