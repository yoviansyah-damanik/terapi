<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('BPJS — eRM')] class extends Component {
    #[Url]
    public string $tab = 'sent';

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function with(): array
    {
        return [];
    }
};
?>

<div class="space-y-6 pb-12">
    <x-ui.page-header title="BPJS — eRM" subtitle="Rekam medis elektronik dan penjadwalan pengiriman ke BPJS Kesehatan">
        <x-slot:actions>
            @if ($tab === 'sent')
                <x-atoms.button variant="ghost" icon="arrow-path"
                    wire:click="$dispatch('refresh-erm-sent')">Refresh</x-atoms.button>
            @elseif ($tab === 'failed')
                <x-atoms.button variant="ghost" icon="arrow-path"
                    wire:click="$dispatch('refresh-erm-failed')">Refresh</x-atoms.button>
            @elseif ($tab === 'scheduler')
                <x-atoms.button variant="ghost" icon="arrow-path"
                    wire:click="$dispatch('refresh-erm-scheduler')">Refresh</x-atoms.button>
                <x-atoms.button variant="primary" icon="paper-airplane" wire:click="$dispatch('open-erm-bulk-modal')">
                    Kirim eRM
                </x-atoms.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-molecules.tabs>
        <x-atoms.tab-item wire:click="switchTab('sent')" :active="$tab === 'sent'">
            <flux:icon.document-check class="size-4 inline-block mr-1.5" />
            eRM Terkirim
        </x-atoms.tab-item>
        <x-atoms.tab-item wire:click="switchTab('failed')" :active="$tab === 'failed'">
            <flux:icon.exclamation-triangle class="size-4 inline-block mr-1.5" />
            eRM Gagal
        </x-atoms.tab-item>
        <x-atoms.tab-item wire:click="switchTab('scheduler')" :active="$tab === 'scheduler'">
            <flux:icon.calendar-days class="size-4 inline-block mr-1.5" />
            Penjadwalan
        </x-atoms.tab-item>
    </x-molecules.tabs>

    @if ($tab === 'sent')
        <livewire:pages::bpjs._erm.sent />
    @elseif ($tab === 'failed')
        <livewire:pages::bpjs._erm.failed />
    @elseif ($tab === 'scheduler')
        <livewire:pages::bpjs._erm.scheduler />
    @endif
</div>
