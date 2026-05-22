<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Konektivitas')] class extends Component {
    #[Url]
    public string $tab = 'bpjs';

    public array $modules = [];

    private const MODULES = [
        'bpjs' => [
            'label' => 'BPJS Kesehatan',
            'icon' => 'shield-check',
            'description' => 'VClaim, eRM, Antrian, Apotek, iCare',
        ],
        'satusehat' => [
            'label' => 'Satu Sehat',
            'icon' => 'heart',
            'description' => 'Kemenkes FHIR Integration',
        ],
        'rsonline' => [
            'label' => 'RS Online',
            'icon' => 'building-office',
            'description' => 'Pelaporan RS Online Kemenkes',
        ],
        'snowstorm' => [
            'label' => 'Snowstorm',
            'icon' => 'magnifying-glass',
            'description' => 'Snomed CT Server',
        ],
        'ai' => [
            'label' => 'AI Provider',
            'icon' => 'cpu-chip',
            'description' => 'Konfigurasi Provider AI',
        ],
        'tte' => [
            'label' => 'TTE (BSrE)',
            'icon' => 'shield-check',
            'description' => 'Tanda Tangan Elektronik',
        ],
        'wa' => [
            'label' => 'WhatsApp Gateway',
            'icon' => 'device-phone-mobile',
            'description' => 'Push Notifikasi',
        ],
        'dicom' => [
            'label' => 'DICOM (PACS)',
            'icon' => 'photo',
            'description' => 'Radiologi Server',
        ],
        'orthanc-sync' => [
            'label' => 'Orthanc Sync',
            'icon' => 'arrow-path',
            'description' => 'Sinkronisasi Worklist',
        ],
    ];

    public function mount(): void
    {
        $this->modules = self::MODULES;

        // Pastikan tab valid
        if (!array_key_exists($this->tab, $this->modules)) {
            $this->tab = array_key_first($this->modules);
        }
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
    }
}; ?>

<div>
    <x-ui.page-header title="Konektivitas & Integrasi"
        subtitle="Pengaturan sentral untuk integrasi layanan internal dan eksternal pihak ketiga" />

    <div class="flex flex-col lg:flex-row gap-6">

        {{-- Sidebar Navigasi --}}
        <aside class="w-full lg:w-64 shrink-0">
            <div
                class="sticky top-4 bg-white dark:bg-primary-dark-800 lg:rounded-2xl lg:border border-zinc-200 dark:border-primary-dark-700 lg:shadow-sm overflow-hidden flex flex-col gap-1 lg:block">

                <div class="hidden lg:block px-4 pt-4 pb-2">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500">
                        Modul Integrasi</p>
                </div>

                {{-- Horizontal Layout for Mobile / Tablet --}}
                <div class="flex lg:hidden flex-wrap gap-2 mb-2">
                    @foreach ($modules as $moduleKey => $module)
                        <x-atoms.button icon="{{ $module['icon'] }}" wire:click="selectTab('{{ $moduleKey }}')"
                            :variant="$tab === $moduleKey ? 'primary' : 'ghost'" size="sm">
                            {{ $module['label'] }}
                        </x-atoms.button>
                    @endforeach
                </div>

                {{-- Vertical Layout for Desktop --}}
                <nav class="hidden lg:flex flex-col px-2 pb-2 space-y-0.5">
                    @foreach ($modules as $moduleKey => $module)
                        @php $isActive = $tab === $moduleKey; @endphp

                        <x-atoms.button icon="{{ $module['icon'] }}" variant="ghost"
                            wire:click="selectTab('{{ $moduleKey }}')"
                            class="group flex items-center w-full gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                                {{ $isActive
                                    ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300'
                                    : 'text-zinc-600 dark:text-primary-dark-400 hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40 hover:text-zinc-800 dark:hover:text-primary-dark-200' }}">
                            <div class="flex-1 text-left flex flex-col">
                                <span>{{ $module['label'] }}</span>
                                <span
                                    class="text-[10px] font-normal {{ $isActive ? 'text-primary-500 dark:text-primary-400' : 'text-zinc-400 dark:text-primary-dark-500 group-hover:text-zinc-500' }}">{{ $module['description'] }}</span>
                            </div>
                            @if ($isActive)
                                <div class="w-1.5 h-1.5 rounded-full bg-primary-500 dark:bg-primary-400 shrink-0"></div>
                            @endif
                        </x-atoms.button>
                    @endforeach
                </nav>

            </div>
        </aside>

        {{-- Konten Dinamis --}}
        <div class="flex-1 min-w-0">
            @if ($tab === 'bpjs')
                <livewire:pages::configuration.connectivity-partials.tab-bpjs wire:key="tab-bpjs" />
            @elseif ($tab === 'satusehat')
                <livewire:pages::configuration.connectivity-partials.tab-satusehat wire:key="tab-satusehat" />
            @elseif ($tab === 'rsonline')
                <livewire:pages::configuration.connectivity-partials.tab-rsonline wire:key="tab-rsonline" />
            @elseif ($tab === 'snowstorm')
                <livewire:pages::configuration.connectivity-partials.tab-snowstorm wire:key="tab-snowstorm" />
            @elseif ($tab === 'ai')
                <livewire:pages::configuration.connectivity-partials.tab-ai wire:key="tab-ai" />
            @elseif ($tab === 'tte')
                <livewire:pages::configuration.connectivity-partials.tab-tte wire:key="tab-tte" />
            @elseif ($tab === 'wa')
                <livewire:pages::configuration.connectivity-partials.tab-wa-gateway wire:key="tab-wa" />
            @elseif ($tab === 'dicom')
                <livewire:pages::configuration.connectivity-partials.tab-dicom wire:key="tab-dicom" />
            @elseif ($tab === 'orthanc-sync')
                <livewire:pages::configuration.connectivity-partials.tab-orthanc-sync wire:key="tab-orthanc-sync" />
            @endif
        </div>

    </div>
</div>
