<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Dokumentasi API')] class extends Component {
    #[Url]
    public string $tab = 'umum';

    #[Url]
    public string $activeSection = 'overview';

    public array $sidebarSections = [];

    private const MODULES = [
        'umum' => [
            'label' => 'Umum & Auth',
            'icon' => 'book-open',
            'sections' => [
                'overview' => ['label' => 'Overview', 'icon' => 'book-open'],
                'auth' => ['label' => 'Autentikasi', 'icon' => 'lock-closed'],
            ],
        ],
        'hospital' => [
            'label' => 'Informasi RS',
            'icon' => 'building-office-2',
            'sections' => [
                'hospital-info' => ['label' => 'Identitas RS', 'icon' => 'identification'],
                'hospital-service' => ['label' => 'Info Layanan Sistem', 'icon' => 'server'],
            ],
        ],
        'whatsapp' => [
            'label' => 'WhatsApp',
            'icon' => 'chat-bubble-left-right',
            'sections' => [
                'wa-send-text' => ['label' => 'Kirim Teks', 'icon' => 'chat-bubble-left'],
                'wa-send-image' => ['label' => 'Kirim Gambar', 'icon' => 'photo'],
                'wa-send-file' => ['label' => 'Kirim File', 'icon' => 'document'],
                'wa-status' => ['label' => 'Status & Cek Pesan', 'icon' => 'signal'],
            ],
        ],
        'gowa' => [
            'label' => 'GOWA',
            'icon' => 'chat-bubble-left-right',
            'sections' => [
                'gowa-send' => ['label' => 'Kirim Pesan', 'icon' => 'paper-airplane'],
                'gowa-media' => ['label' => 'Kirim Media', 'icon' => 'photo'],
                'gowa-other' => ['label' => 'Tipe Lainnya', 'icon' => 'squares-2x2'],
                'gowa-status' => ['label' => 'Status & Manajemen', 'icon' => 'signal'],
            ],
        ],
        'tte' => [
            'label' => 'TTE (BSrE)',
            'icon' => 'shield-check',
            'sections' => [
                'tte-status' => ['label' => 'Cek Koneksi Server', 'icon' => 'signal'],
                'tte-hits' => ['label' => 'Statistik Hit', 'icon' => 'chart-bar'],
                'sign' => ['label' => 'Sign PDF', 'icon' => 'pencil-square'],
                'sign-totp' => ['label' => 'Request Sign TOTP', 'icon' => 'key'],
                'verify' => ['label' => 'Verify PDF', 'icon' => 'shield-check'],
                'user' => ['label' => 'Manajemen User BSrE', 'icon' => 'users'],
                'seal' => ['label' => 'Segel Elektronik', 'icon' => 'document-check'],
            ],
        ],
        'simrs-log' => [
            'label' => 'Log SIMRS',
            'icon' => 'document-text',
            'sections' => [
                'simrs-log-store' => ['label' => 'Kirim Log', 'icon' => 'arrow-up-tray'],
                'simrs-log-batch' => ['label' => 'Kirim Batch', 'icon' => 'queue-list'],
                'simrs-log-list' => ['label' => 'Ambil Daftar Log', 'icon' => 'table-cells'],
            ],
        ],
        'simrs-update' => [
            'label' => 'Update SIMRS',
            'icon' => 'archive-box-arrow-down',
            'sections' => [
                'simrs-update-overview' => ['label' => 'Overview', 'icon' => 'server'],
                'simrs-update-check-version' => ['label' => 'Cek Versi', 'icon' => 'arrow-path'],
                'simrs-update-download' => ['label' => 'Unduh Update', 'icon' => 'arrow-down-tray'],
                'simrs-update-report' => ['label' => 'Laporan Update', 'icon' => 'clipboard-document-check'],
                'simrs-slide-list' => ['label' => 'Slides Launcher', 'icon' => 'photo'],
            ],
        ],
        'qrcode' => [
            'label' => 'QR Code',
            'icon' => 'qr-code',
            'sections' => [
                'qrcode-generate' => ['label' => 'Generate QR Code', 'icon' => 'qr-code'],
            ],
        ],
        'dicom' => [
            'label' => 'DICOM / PACS',
            'icon' => 'photo',
            'sections' => [
                'dicom-worklist-batch' => ['label' => 'Kirim Worklist (Batch)', 'icon' => 'queue-list'],
                'dicom-worklist' => ['label' => 'Kirim Worklist (Single)', 'icon' => 'document-arrow-up'],
                'dicom-status' => ['label' => 'Status Worklist', 'icon' => 'signal'],
                'dicom-delete' => ['label' => 'Hapus Worklist', 'icon' => 'trash'],
                'dicom-webhook-satusehat' => ['label' => 'Webhook Satu Sehat DICOM', 'icon' => 'arrow-down-tray'],
                'dicom-webhook-orthanc' => ['label' => 'Webhook Orthanc-Sync', 'icon' => 'arrow-down-tray'],
            ],
        ],
        'ai' => [
            'label' => 'AI Provider',
            'icon' => 'cpu-chip',
            'sections' => [
                'ai-prompt' => ['label' => 'Kirim Prompt', 'icon' => 'paper-airplane'],
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
            'appUrl' => rtrim(config('app.url', 'https://your-app.example.com'), '/'),
            'modules' => self::MODULES,
        ];
    }
};
?>

<div>
    <x-ui.page-header title="Dokumentasi API"
        subtitle="Panduan penggunaan API eksternal untuk integrasi dengan sistem lain (SIMRS, RME, dll)" />

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
            @if ($tab === 'umum')
                @include('api-portal.documentation-partials._tab-umum')
            @endif
            @if ($tab === 'hospital')
                @include('api-portal.documentation-partials._tab-hospital')
            @endif
            @if ($tab === 'whatsapp')
                @include('api-portal.documentation-partials._tab-whatsapp')
            @endif
            @if ($tab === 'gowa')
                @include('api-portal.documentation-partials._tab-gowa')
            @endif
            @if ($tab === 'tte')
                @include('api-portal.documentation-partials._tab-tte')
            @endif
            @if ($tab === 'simrs-log')
                @include('api-portal.documentation-partials._tab-simrs-log')
            @endif
            @if ($tab === 'simrs-update')
                @include('api-portal.documentation-partials._tab-simrs-update')
            @endif
            @if ($tab === 'qrcode')
                @include('api-portal.documentation-partials._tab-qrcode')
            @endif
            @if ($tab === 'dicom')
                @include('api-portal.documentation-partials._tab-dicom')
            @endif
            @if ($tab === 'ai')
                @include('api-portal.documentation-partials._tab-ai')
            @endif
        </div>

    </div>
</div>
