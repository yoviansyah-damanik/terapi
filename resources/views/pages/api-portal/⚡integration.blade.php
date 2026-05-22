<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Integrasi API')] class extends Component {
    #[Url]
    public string $tab = 'tte';

    #[Url]
    public string $activeSection = 'overview';

    public array $sidebarSections = [];

    private const MODULES = [
        'tte' => [
            'label' => 'TTE (BSrE)',
            'icon' => 'shield-check',
            'sections' => [
                'overview' => ['label' => 'Overview', 'icon' => 'book-open'],
                'auth' => ['label' => 'Autentikasi', 'icon' => 'lock-closed'],
                'sign' => ['label' => 'Sign PDF', 'icon' => 'pencil-square'],
                'sign-totp' => ['label' => 'Request Sign TOTP', 'icon' => 'key'],
                'verify' => ['label' => 'Verify PDF', 'icon' => 'shield-check'],
                'user' => ['label' => 'Manajemen User', 'icon' => 'users'],
                'seal' => ['label' => 'Segel Elektronik', 'icon' => 'document-check'],
                'status-user' => ['label' => 'Status User', 'icon' => 'information-circle'],
                'download' => ['label' => 'Unduh Dokumen', 'icon' => 'arrow-down-tray'],
            ],
        ],
        'bpjs' => [
            'label' => 'BPJS Kesehatan',
            'icon' => 'heart',
            'sections' => [
                'overview' => ['label' => 'Overview & Auth', 'icon' => 'book-open'],
                'vclaim' => ['label' => 'VClaim', 'icon' => 'document-text'],
                'antrian-rs' => ['label' => 'Antrian RS', 'icon' => 'queue-list'],
                'erm' => ['label' => 'eRM BPJS', 'icon' => 'clipboard-document-check'],
                'apotek' => ['label' => 'Apotek Online', 'icon' => 'beaker'],
            ],
        ],
        'satusehat' => [
            'label' => 'Satu Sehat',
            'icon' => 'globe-alt',
            'sections' => [
                'overview' => ['label' => 'Overview & Auth', 'icon' => 'book-open'],
                'fhir-resources' => ['label' => 'FHIR Resources', 'icon' => 'squares-2x2'],
                'patient' => ['label' => 'Patient', 'icon' => 'user'],
                'encounter' => ['label' => 'Encounter', 'icon' => 'clipboard-document'],
                'erm' => ['label' => 'Bundle eRM', 'icon' => 'paper-airplane'],
            ],
        ],
    ];

    public function mount(): void
    {
        $this->sidebarSections = self::MODULES[$this->tab]['sections'] ?? [];
        if (!array_key_exists($this->activeSection, $this->sidebarSections)) {
            $this->activeSection = array_key_first($this->sidebarSections) ?: '';
        }
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
        $this->sidebarSections = self::MODULES[$tab]['sections'] ?? [];
        $this->activeSection = array_key_first($this->sidebarSections) ?: '';
    }

    public function with(): array
    {
        return [
            'tteBaseUrl' => config('services.tte.base_url') ?: 'https://{IP-Server-ESign-Client}',
            'modules' => self::MODULES,
        ];
    }
};
?>

<div>
    <x-ui.page-header title="Integrasi API" subtitle="Dokumentasi penggunaan API untuk integrasi sistem" />

    <div class="flex gap-6">

        <x-organisms.sidebar-nav
            :modules="$modules"
            :tab="$tab"
            :sections="$sidebarSections"
            :activeSection="$activeSection"
            select-method="selectTab"
        />

        {{-- Konten --}}
        <div class="flex-1 min-w-0 space-y-6">
            @if ($tab === 'tte')
                @include('api-portal.integration-partials._tab-tte')
            @endif
            @if ($tab === 'bpjs')
                @include('api-portal.integration-partials._tab-bpjs')
            @endif
            @if ($tab === 'satusehat')
                @include('api-portal.integration-partials._tab-satusehat')
            @endif
        </div>

    </div>
</div>
