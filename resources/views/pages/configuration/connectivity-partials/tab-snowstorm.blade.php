<?php

use App\Helpers\ConfigurationHelper;
use Livewire\Component;

new class extends Component {
    public string $snowstormUrl = '';
    public string $snowstormBranch = 'MAIN';
    public string $snowstormSystemDisplay = 'http://snomed.info/sct';

    public function mount(): void
    {
        $this->snowstormUrl = ConfigurationHelper::get('snowstorm.url', '');
        $this->snowstormBranch = ConfigurationHelper::get('snowstorm.branch', 'MAIN');
        $this->snowstormSystemDisplay = ConfigurationHelper::get('snowstorm.system_display', 'http://snomed.info/sct');
    }

    public function saveSnowstorm(): void
    {
        ConfigurationHelper::set('snowstorm.url', $this->snowstormUrl);
        ConfigurationHelper::set('snowstorm.branch', $this->snowstormBranch);
        ConfigurationHelper::set('snowstorm.system_display', $this->snowstormSystemDisplay);

        $this->dispatch('toast', type: 'success', message: 'Konfigurasi Snowstorm berhasil disimpan.');
    }
}; ?>

<div>
    
    <x-ui.section-card title="Snowstorm — SNOMED CT Server"
        subtitle="Terminology server untuk pencarian konsep SNOMED CT.">
        <form id="form-snowstorm" wire:submit="saveSnowstorm" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:label>Base URL</flux:label>
                    <flux:input wire:model="snowstormUrl" placeholder="http://localhost:9876" />
                </div>
                <div>
                    <flux:label>Branch</flux:label>
                    <flux:input wire:model="snowstormBranch" placeholder="MAIN" />
                </div>
                <div class="sm:col-span-2">
                    <flux:label>System Display URL</flux:label>
                    <flux:input wire:model="snowstormSystemDisplay" placeholder="http://snomed.info/sct" />
                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-1">Digunakan sebagai <code
                            class="font-mono">system</code> pada FHIR coding SNOMED CT.</p>
                </div>
            </div>
        </form>
        <x-slot:footer>
            <div class="flex items-center gap-3">
                <x-atoms.button form="form-snowstorm" type="submit" variant="primary" icon="check">Simpan</x-atoms.button>
                <span wire:loading wire:target="saveSnowstorm"
                    class="text-sm text-zinc-500 dark:text-primary-dark-400">Menyimpan...</span>
                <livewire:components.connection-result :url="rtrim($snowstormUrl, '/') . '/version'" method="GET" name="connection-snowstorm"
                    title="Tes Koneksi — Snowstorm" />
            </div>
        </x-slot:footer>
    </x-ui.section-card>
</div>
