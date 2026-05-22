<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Dashboard')] class extends Component {
    public function with(): array
    {
        return [
                // Data ringkasan umum bisa ditambahkan di sini nanti
            ];
    }
};
?>

<div>
    <x-ui.page-header title="Dashboard Utama" subtitle="Selamat datang di sistem integrasi Terapi" />

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
        <div
            class="p-6 bg-white rounded-xl shadow-sm border border-zinc-100 dark:bg-primary-dark-800 dark:border-primary-dark-700">
            <h3 class="font-bold text-zinc-900 dark:text-primary-dark-100 mb-2">Satu Sehat</h3>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-400 mb-4">Integrasi rekam medis elektronik dengan
                platform
                Satu Sehat Kemenkes.</p>
            <x-atoms.button wire:navigate href="{{ route('satusehat.summary') }}" variant="primary" size="sm">Buka
                Dashboard SS</x-atoms.button>
        </div>

        <div
            class="p-6 bg-white rounded-xl shadow-sm border border-zinc-100 dark:bg-primary-dark-800 dark:border-primary-dark-700">
            <h3 class="font-bold text-zinc-900 dark:text-primary-dark-100 mb-2">BPJS Kesehatan</h3>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-400 mb-4">Pengiriman data eRM dan pengecekan status
                bridging
                BPJS.</p>
            {{-- <x-atoms.button wire:navigate href="{{ route('bpjs.erm') }}" variant="primary" size="sm">Buka eRM BPJS
            </x-atoms.button> --}}
        </div>

        <div
            class="p-6 bg-white rounded-xl shadow-sm border border-zinc-100 dark:bg-primary-dark-800 dark:border-primary-dark-700">
            <h3 class="font-bold text-zinc-900 dark:text-primary-dark-100 mb-2">Terminologi</h3>
            <p class="text-sm text-zinc-600 dark:text-primary-dark-400 mb-4">Manajemen kode standar SNOMED CT, LOINC,
                dan KFA.
            </p>
            <x-atoms.button wire:navigate href="{{ route('terminology.snomed') }}" variant="primary" size="sm">Buka
                Snomed
                CT</x-atoms.button>
        </div>
    </div>
</div>
