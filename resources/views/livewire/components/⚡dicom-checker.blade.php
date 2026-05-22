<?php

use Livewire\Component;

new class extends Component {
    public string $ae;
    public string $host;
    public int $port;
    public string $manufacturer = 'STORESCP';

    public int $version = 1;

    public function refresh(): void
    {
        $this->version++;
    }
}; ?>

<div class="space-y-4">
    {{-- Header AE Info --}}
    <div
        class="bg-zinc-100 dark:bg-primary-dark-900/50 p-3 rounded-xl flex items-center justify-between border border-zinc-200 dark:border-primary-dark-700">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-white dark:bg-primary-dark-800 rounded-lg shadow-sm">
                <flux:icon name="computer-desktop" class="size-5 text-zinc-500" />
            </div>
            <div>
                <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $ae }}</h4>
                <p class="text-[10px] text-zinc-500 font-mono">{{ $host }}:{{ $port }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-atoms.button variant="ghost" size="sm" icon="arrow-path" wire:click="refresh"
                tooltip="Ulangi Diagnostik" />
            <flux:badge size="sm" variant="outline">{{ $manufacturer }}</flux:badge>
        </div>
    </div>

    {{-- Result Cards (Async via Lazy Sub-components) --}}
    {{-- Result Cards (Async via Lazy Sub-components) --}}
    <div class="grid grid-cols-1 gap-3" wire:key="diagnostic-v-{{ $version }}">
        <livewire:components.dicom-diagnostic-item wire:key="echo-v-{{ $version }}" type="echo" :$ae :$host :$port :$manufacturer lazy />
        <livewire:components.dicom-diagnostic-item wire:key="find-v-{{ $version }}" type="find" :$ae :$host :$port :$manufacturer lazy />
        <livewire:components.dicom-diagnostic-item wire:key="store-v-{{ $version }}" type="store" :$ae :$host :$port :$manufacturer lazy />
    </div>

    {{-- Info --}}
    <div
        class="p-3 bg-amber-50 dark:bg-amber-900/10 rounded-xl border border-amber-100 dark:border-amber-900/20 flex gap-2">
        <flux:icon name="information-circle" variant="mini" class="size-4 text-amber-600 shrink-0 mt-0.5" />
        <p class="text-[10px] text-amber-800 dark:text-amber-400 leading-relaxed">
            <strong>Diagnostik Asinkron:</strong> Setiap tes dijalankan secara paralel.
            C-FIND menggunakan C-FIND Study level (bukan MWL).
            C-STORE menggunakan studi nyata dari PACS jika ada, atau DICOM sintetis sementara jika PACS kosong — instance tes dihapus otomatis setelah pengujian.
        </p>
    </div>
</div>
