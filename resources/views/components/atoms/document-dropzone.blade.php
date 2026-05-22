@props([
    'wireModel'  => 'file',       // nama properti Livewire
    'accept'     => '.csv',
    'maxSizeMb'  => 20,
    'label'      => 'Klik atau seret & lepas dokumen',
    'hint'       => null,
    'inputId'    => null,
])

@php
    $uid  = $inputId ?? ('doc-drop-' . uniqid());
    $hint = $hint ?? strtoupper(ltrim($accept, '.')) . ', maks. ' . $maxSizeMb . ' MB';
@endphp

<div
    x-data="{
        isDragging: false,
        uploading: false,
        uploaded: false,
        progress: 0,
        fileName: '',
        fileSize: 0,
        error: '',

        fmt(bytes) {
            if (!bytes) return '0 B';
            const k = 1024, s = ['B','KB','MB'], i = Math.min(Math.floor(Math.log(bytes)/Math.log(k)), 2);
            return (bytes / Math.pow(k, i)).toFixed(1) + ' ' + s[i];
        },

        handleFile(file) {
            if (!file) return;
            this.error   = '';
            this.fileName = file.name;
            this.fileSize = file.size;
            this.uploading  = true;
            this.uploaded   = false;
            this.progress   = 0;

            @this.upload('{{ $wireModel }}', file, (uploadedName) => {
                this.uploading = false;
                this.uploaded  = true;
                this.progress  = 100;
                if (typeof $wire.previewImport !== 'undefined') {
                    $wire.previewImport();
                }
            }, () => {
                this.uploading = false;
                this.error = 'Upload gagal. Coba lagi.';
            }, (e) => {
                this.progress = e.detail.progress;
            });
        },

        reset() {
            this.uploaded  = false;
            this.uploading = false;
            this.progress  = 0;
            this.fileName  = '';
            this.fileSize  = 0;
            this.error     = '';
        }
    }"
    x-on:dragover.prevent="isDragging = true"
    x-on:dragleave.prevent="isDragging = false"
    x-on:drop.prevent="isDragging = false; handleFile($event.dataTransfer.files[0])"
    {{ $attributes->class(['relative']) }}
>
    {{-- Drop Area (sebelum upload) --}}
    <label for="{{ $uid }}"
        :class="{
            'border-primary-400 bg-primary-50/50 dark:bg-primary-900/20 scale-[1.01]': isDragging,
            'border-zinc-200 dark:border-primary-dark-700 hover:border-primary-300 dark:hover:border-primary-700': !isDragging && !uploaded,
            'border-emerald-300 bg-emerald-50/50 dark:border-emerald-800 dark:bg-emerald-900/10': uploaded,
        }"
        class="flex gap-4 items-center w-full p-4 cursor-pointer border-2 border-dashed rounded-xl transition-all duration-200"
        x-show="!uploading"
    >
        {{-- Ikon Dokumen --}}
        <div
            :class="uploaded ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-800/40 dark:text-emerald-400' : 'bg-zinc-100 text-zinc-400 dark:bg-primary-dark-700 dark:text-primary-dark-400'"
            class="flex-shrink-0 flex items-center justify-center size-12 rounded-xl transition-colors"
        >
            <flux:icon x-show="!uploaded" name="document-arrow-up" class="size-6" />
            <flux:icon x-show="uploaded" name="document-check" class="size-6" />
        </div>

        {{-- Teks Info --}}
        <div class="flex-1 min-w-0">
            <p x-show="!uploaded" class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $label }}</p>
            <p x-show="!uploaded" class="text-[11px] text-zinc-400 dark:text-primary-dark-500 mt-0.5">{{ $hint }}</p>

            <p x-show="uploaded" class="text-sm font-bold text-emerald-700 dark:text-emerald-400 truncate" x-text="fileName"></p>
            <p x-show="uploaded" class="text-[11px] text-emerald-600 dark:text-emerald-500 mt-0.5" x-text="fmt(fileSize)"></p>
        </div>

        {{-- Tombol Ganti / Pilih --}}
        <div class="flex-shrink-0">
            <span
                x-show="!uploaded"
                class="text-[11px] font-bold px-3 py-1.5 rounded-lg border border-primary-200 bg-primary-50 text-primary-600 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-400 transition-colors"
            >Pilih File</span>
            <button type="button"
                x-show="uploaded"
                x-on:click.prevent.stop="reset(); $wire.resetImportState()"
                class="flex items-center justify-center size-7 rounded-lg text-zinc-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20 transition-colors"
            >
                <flux:icon name="x-mark" class="size-4" />
            </button>
        </div>

        <input type="file" id="{{ $uid }}" accept="{{ $accept }}" class="sr-only"
            x-on:change="handleFile($el.files[0])" />
    </label>

    {{-- Progress State (saat uploading) --}}
    <div x-show="uploading" class="p-4 border-2 border-dashed border-primary-300 dark:border-primary-700 rounded-xl bg-primary-50/50 dark:bg-primary-900/10 space-y-3">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 size-10 rounded-xl bg-primary-100 dark:bg-primary-800/40 flex items-center justify-center">
                <svg class="animate-spin size-5 text-primary-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-primary-700 dark:text-primary-300 truncate" x-text="fileName"></p>
                <p class="text-[10px] text-primary-500 dark:text-primary-400">Mengunggah <span x-text="progress"></span>%</p>
            </div>
        </div>
        <div class="w-full bg-primary-100 dark:bg-primary-900/30 rounded-full h-1.5 overflow-hidden">
            <div class="h-full bg-primary-500 rounded-full transition-all duration-300" :style="'width:' + progress + '%'"></div>
        </div>
    </div>

    {{-- Error State --}}
    <p x-show="error" x-text="error" class="mt-2 text-xs text-red-500 font-medium"></p>

    {{-- Slot untuk konten tambahan setelah upload --}}
    {{ $slot }}
</div>
