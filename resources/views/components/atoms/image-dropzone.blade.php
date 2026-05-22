@props([
    'wireModel'   => null,    // nama wire:model (string, misal "images")
    'accept'      => 'image/jpeg,image/png',
    'maxSizeMb'   => 10,
    'maxFiles'    => 20,
    'label'       => 'Klik atau drag & drop gambar di sini',
    'description' => null,
    'inputId'     => null,
])

@php
    $uid         = $inputId ?? ('dropzone-' . uniqid());
    $description = $description ?? "JPG / PNG, maks. {$maxSizeMb} MB per file";
    $wireAttr    = $wireModel ? "wire:model=\"{$wireModel}\"" : '';
@endphp

<div
    x-data="{
        isDragging: false,
        previews: [],
        fileCount: 0,

        async handleFiles(fileList) {
            const files = Array.from(fileList);
            this.fileCount = files.length;
            this.previews = [];
            for (const f of files) {
                const url = await new Promise(res => {
                    const r = new FileReader();
                    r.onload = e => res(e.target.result);
                    r.readAsDataURL(f);
                });
                this.previews.push({ url, name: f.name, size: (f.size / 1024).toFixed(0) });
            }
        },

        removePreview(index) {
            this.previews.splice(index, 1);
            this.fileCount = this.previews.length;
        },

        formatSize(kb) {
            return kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : kb + ' KB';
        },
    }"
    x-on:dragover.prevent="isDragging = true"
    x-on:dragleave.prevent="isDragging = false"
    x-on:drop.prevent="
        isDragging = false;
        handleFiles($event.dataTransfer.files);
    "
    {{ $attributes->class(['space-y-3']) }}
>
    {{-- Drop area --}}
    <label for="{{ $uid }}"
        :class="isDragging
            ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 scale-[1.01]'
            : 'border-zinc-300 dark:border-primary-dark-600 hover:border-blue-400 dark:hover:border-blue-500 hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50'"
        class="flex flex-col items-center justify-center w-full min-h-[160px] p-6 cursor-pointer
               border-2 border-dashed rounded-xl transition-all duration-200">

        <div class="flex flex-col items-center gap-2 pointer-events-none text-center" x-show="previews.length === 0">
            <div class="w-12 h-12 rounded-full bg-zinc-100 dark:bg-primary-dark-700 flex items-center justify-center">
                <flux:icon name="cloud-arrow-up" class="size-6 text-zinc-400 dark:text-primary-dark-400" />
            </div>
            <div>
                <p class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $label }}</p>
                <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">{{ $description }}</p>
            </div>
        </div>

        {{-- Saat ada file --}}
        <div x-show="previews.length > 0" class="pointer-events-none text-center">
            <p class="text-sm font-medium text-blue-600 dark:text-blue-400">
                <span x-text="previews.length"></span> gambar dipilih
                — <span class="underline">klik untuk ganti</span>
            </p>
        </div>

        {{-- Overlay saat dragging --}}
        <div x-show="isDragging"
            class="absolute inset-0 flex items-center justify-center rounded-xl bg-blue-50/80 dark:bg-blue-900/30 pointer-events-none">
            <p class="text-blue-600 dark:text-blue-300 font-semibold text-sm">Lepaskan untuk upload</p>
        </div>

        <input
            id="{{ $uid }}"
            type="file"
            multiple
            accept="{{ $accept }}"
            class="sr-only"
            {!! $wireAttr !!}
            x-on:change="handleFiles($el.files)"
        />
    </label>

    {{-- Grid preview --}}
    <div x-show="previews.length > 0" class="grid grid-cols-3 gap-2" x-cloak>
        <template x-for="(item, i) in previews" :key="i">
            <div class="relative group rounded-lg overflow-hidden border border-zinc-200 dark:border-primary-dark-700 aspect-square bg-zinc-50 dark:bg-primary-dark-800">
                <img :src="item.url" :alt="item.name"
                    class="w-full h-full object-cover" />

                {{-- Overlay info --}}
                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-1 p-2">
                    <p class="text-white text-xs font-medium text-center leading-tight line-clamp-2" x-text="item.name"></p>
                    <p class="text-white/70 text-[10px]" x-text="formatSize(item.size)"></p>
                </div>

                {{-- Nomor urut --}}
                <div class="absolute top-1.5 left-1.5 w-5 h-5 rounded-full bg-black/60 flex items-center justify-center">
                    <span class="text-[10px] text-white font-bold" x-text="i + 1"></span>
                </div>
            </div>
        </template>
    </div>

    {{-- Loading state saat Livewire upload --}}
    <div wire:loading wire:target="{{ $wireModel }}"
        class="flex items-center gap-2 text-sm text-zinc-500 dark:text-primary-dark-400">
        <svg class="animate-spin w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
        Mengunggah...
    </div>

    @error($wireModel ?? 'images')
        <p class="text-xs text-red-500">{{ $message }}</p>
    @enderror
    @error(($wireModel ?? 'images') . '.*')
        <p class="text-xs text-red-500">{{ $message }}</p>
    @enderror
</div>
