<?php

use App\Helpers\ConfigurationHelper;
use Livewire\Component;

new class extends Component {
    public string $dicomProvider = 'orthanc';
    public string $dicomUrl = '';
    public string $dicomUsername = '';
    public string $dicomPassword = '';
    public string $dicomViewerUrl = '';

    // Default Router
    public string $routerDefaultAe = '';
    public string $routerDefaultHost = '';
    public string $routerDefaultPort = '';
    public string $ssAeTitle = '';
    public string $ssHost = '';
    public string $ssPort = '11112';

    public bool $showSsEchoModal = false;

    public function mount(): void
    {
        $this->dicomProvider = ConfigurationHelper::get('dicom.provider', 'orthanc');
        $this->dicomUrl = ConfigurationHelper::get('dicom.orthanc.url') ?? ConfigurationHelper::get('dicom.url', 'http://192.168.1.10:8042');
        $this->dicomUsername = ConfigurationHelper::get('dicom.orthanc.username') ?? ConfigurationHelper::get('dicom.username', 'orthanc');
        $this->dicomPassword = ConfigurationHelper::get('dicom.orthanc.password') ?? ConfigurationHelper::get('dicom.password', 'orthanc');
        $this->dicomViewerUrl = ConfigurationHelper::get('dicom.orthanc.viewer_url') ?? ConfigurationHelper::get('dicom.viewer_url', 'http://192.168.1.10:8042/web-viewer/app/viewer.html');

        $this->ssAeTitle = ConfigurationHelper::get('dicom.satusehat.ae_title', '');
        $this->ssHost = ConfigurationHelper::get('dicom.satusehat.host', '');
        $this->ssPort = ConfigurationHelper::get('dicom.satusehat.port', '11112');

        $this->routerDefaultAe = ConfigurationHelper::get('dicom.router.default_ae', '');
        $this->routerDefaultHost = ConfigurationHelper::get('dicom.router.default_host', '');
        $this->routerDefaultPort = (int) ConfigurationHelper::get('dicom.router.default_port', 104);
    }

    public function saveDicom(): void
    {
        $cleanUrl = rtrim($this->dicomUrl, '/');

        ConfigurationHelper::set('dicom.provider', $this->dicomProvider);
        ConfigurationHelper::set('dicom.orthanc.url', $cleanUrl);
        ConfigurationHelper::set('dicom.orthanc.username', $this->dicomUsername);
        ConfigurationHelper::set('dicom.orthanc.password', $this->dicomPassword, true);
        ConfigurationHelper::set('dicom.orthanc.viewer_url', $this->dicomViewerUrl);

        ConfigurationHelper::set('dicom.satusehat.ae_title', strtoupper($this->ssAeTitle));
        ConfigurationHelper::set('dicom.satusehat.host', $this->ssHost);
        ConfigurationHelper::set('dicom.satusehat.port', $this->ssPort);

        ConfigurationHelper::set('dicom.router.default_ae', strtoupper($this->routerDefaultAe));
        ConfigurationHelper::set('dicom.router.default_host', $this->routerDefaultHost);
        ConfigurationHelper::set('dicom.router.default_port', (string) $this->routerDefaultPort);
$this->dispatch('toast', type: 'success', message: 'Konfigurasi DICOM berhasil disimpan.');
    }

    public function testSsConnection(): void
    {
        $this->validate([
            'ssAeTitle' => 'required',
            'ssHost' => 'required',
            'ssPort' => 'required',
        ]);
        $this->showSsEchoModal = true;
    }

    public function fillFromRouter(string $target, string $routerId): void
    {
        $router = \App\Models\Dicom\DicomRouter::find($routerId);
        if (!$router) {
            return;
        }

        if ($target === 'satusehat') {
            $this->ssAeTitle = $router->ae_title;
            $this->ssHost = $router->host;
            $this->ssPort = (string) $router->port;
        } elseif ($target === 'default') {
            $this->routerDefaultAe = $router->ae_title;
            $this->routerDefaultHost = $router->host;
            $this->routerDefaultPort = (string) $router->port;
        }
    }

    /** Auth header untuk komponen tes koneksi */
    public function getBuiltHeader(): array
    {
        $headers = [];
        if (!empty($this->dicomUsername) || !empty($this->dicomPassword)) {
            $base = base64_encode($this->dicomUsername . ':' . $this->dicomPassword);
            $headers['Authorization'] = 'Basic ' . $base;
        }
        return $headers;
    }
}; ?>

<div class="space-y-4">
    <x-ui.section-card title="Pengaturan DICOM ORTHANC"
        subtitle="Atur koneksi ke server PACS (Orthanc atau dcm4chee) untuk menarik Metadata via DICOMweb.">
        <form id="form-dicom" wire:submit="saveDicom" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <flux:select wire:model.live="dicomProvider" label="Provider PACS">
                        <flux:select.option value="orthanc">Orthanc</flux:select.option>
                        <flux:select.option value="dcm4chee">dcm4chee</flux:select.option>
                    </flux:select>
                </div>

                <div class="sm:col-span-2">
                    <flux:label>Base URL Server PACS (DICOM API)</flux:label>
                    <flux:input wire:model="dicomUrl"
                        placeholder="Contoh: http://192.168.1.10:8042 atau http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs" />
                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-1">Gunakan URL DICOMweb REST API.</p>
                </div>

                <div>
                    <flux:label>Username Auth (Opsional)</flux:label>
                    <flux:input wire:model="dicomUsername" placeholder="orthanc" />
                </div>
                <div>
                    <flux:label>Password Auth (Opsional)</flux:label>
                    <flux:input type="password" wire:model="dicomPassword" placeholder="Sandi Server PACS" />
                </div>

                <div class="sm:col-span-2 mt-4 pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100 mb-4">
                        Pengaturan Web Viewer
                    </h3>
                    <flux:label>URL Web Viewer Gambar (OHIF / Orthanc Viewer)</flux:label>
                    <flux:input wire:model="dicomViewerUrl"
                        placeholder="Contoh: http://192.168.1.10:8042/web-viewer/app/viewer.html" />
                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-1">
                        Tautan ini digunakan sebagai dasar untuk membuka gambar radiologi. Nanti kita akan cantumkan
                        <code class="font-mono">?study=...</code> di belakangnya.
                    </p>
                </div>

                <div class="sm:col-span-2 mt-4 pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                            DICOM SATU SEHAT
                        </h3>
                        <div x-data="{ open: false }" class="relative">
                            <x-atoms.button size="xs" icon="chevron-down" iconTrailing variant="ghost"
                                @click="open = !open" @click.outside="open = false">
                                Pilih Router
                            </x-atoms.button>
                            <div x-show="open" x-cloak x-transition
                                class="absolute right-0 top-full mt-1 w-64 bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 shadow-lg z-50 overflow-hidden">
                                <div
                                    class="divide-y divide-zinc-100 dark:divide-primary-dark-700 max-h-60 overflow-y-auto">
                                    @foreach (\App\Models\Dicom\DicomRouter::active()->get() as $r)
                                        <button type="button" @click="open = false"
                                            wire:click="fillFromRouter('satusehat', '{{ $r->id }}')"
                                            class="w-full text-left px-4 py-2 hover:bg-zinc-50 dark:hover:bg-primary-dark-700 transition">
                                            <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-200">
                                                {{ $r->name }}</p>
                                            <p class="text-xs text-zinc-500 font-mono mt-0.5">{{ $r->ae_title }}
                                                &middot; {{ $r->host }}:{{ $r->port }}</p>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <flux:label>AE Title Satu Sehat</flux:label>
                            <flux:input wire:model="ssAeTitle" placeholder="SATUSEHAT" style="text-transform:uppercase"
                                maxlength="16" />
                        </div>
                        <div>
                            <flux:label>Host / IP Address</flux:label>
                            <flux:input wire:model="ssHost" placeholder="10.10.10.1" />
                        </div>
                        <div>
                            <flux:label>Port</flux:label>
                            <flux:input type="number" wire:model="ssPort" placeholder="11112" min="1"
                                max="65535" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-atoms.button size="sm" icon="signal" variant="outline" wire:click="testSsConnection">
                            Tes Koneksi (C-ECHO)
                        </x-atoms.button>
                    </div>
                </div>

                <div class="sm:col-span-2 mt-4 pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex flex-col">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                                Default DICOM Router
                            </h3>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                                Router DICOM utama yang digunakan sebagai tujuan default pengiriman studi via C-STORE.
                            </p>
                        </div>
                        <div x-data="{ open: false }" class="relative">
                            <x-atoms.button size="xs" icon="chevron-down" iconTrailing variant="ghost"
                                @click="open = !open" @click.outside="open = false">
                                Pilih Router
                            </x-atoms.button>
                            <div x-show="open" x-cloak x-transition
                                class="absolute right-0 top-full mt-1 w-64 bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 shadow-lg z-50 overflow-hidden">
                                <div
                                    class="divide-y divide-zinc-100 dark:divide-primary-dark-700 max-h-60 overflow-y-auto">
                                    @foreach (\App\Models\Dicom\DicomRouter::active()->get() as $r)
                                        <button type="button" @click="open = false"
                                            wire:click="fillFromRouter('default', '{{ $r->id }}')"
                                            class="w-full text-left px-4 py-2 hover:bg-zinc-50 dark:hover:bg-primary-dark-700 transition">
                                            <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-200">
                                                {{ $r->name }}</p>
                                            <p class="text-xs text-zinc-500 font-mono mt-0.5">{{ $r->ae_title }}
                                                &middot; {{ $r->host }}:{{ $r->port }}</p>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <flux:label>AE Title Router</flux:label>
                            <flux:input wire:model="routerDefaultAe" placeholder="ROUTER_AE"
                                style="text-transform:uppercase" maxlength="16" />
                        </div>
                        <div>
                            <flux:label>Host / IP Address</flux:label>
                            <flux:input wire:model="routerDefaultHost" placeholder="192.168.1.20" />
                        </div>
                        <div>
                            <flux:label>Port</flux:label>
                            <flux:input type="number" wire:model="routerDefaultPort" placeholder="104" min="1"
                                max="65535" />
                        </div>
                    </div>
                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-2">
                        Konfigurasi ini digunakan sebagai preset di halaman
                        <a href="{{ route('dicom.modality') }}" wire:navigate class="underline">DICOM Router</a>.
                        Anda tetap bisa menambah router lain di sana.
                    </p>
                    <div class="mt-4">
                        @php
                            $testUrl = rtrim($this->dicomUrl, '/');
                            $testUrl .= $this->dicomProvider === 'orthanc' ? '/system' : '/studies?limit=1';
                        @endphp
                        <livewire:components.connection-result size="sm" icon="signal" variant="outline"
                            :url="$testUrl" method="GET" name="connection-dicom"
                            title="Tes Koneksi PACS ({{ strtoupper($this->dicomProvider) }})" :headers="$this->getBuiltHeader()" />
                    </div>
                </div>
            </div>
        </form>

        <x-slot:footer>
            <div class="flex items-center gap-3">
                <x-atoms.button form="form-dicom" type="submit" variant="primary"
                    icon="check">Simpan</x-atoms.button>
                <span wire:loading wire:target="saveDicom"
                    class="text-sm text-zinc-500 dark:text-primary-dark-400">Menyimpan...</span>
            </div>
        </x-slot:footer>
    </x-ui.section-card>

    <x-organisms.modal wire:model="showSsEchoModal" name="modal-echo-ss" title="Diagnostik DICOM Satu Sehat"
        maxWidth="lg">
        @if ($showSsEchoModal)
            <livewire:components.dicom-checker wire:key="echo-ss-{{ md5($ssAeTitle . $ssHost . $ssPort) }}"
                ae="{{ strtoupper($ssAeTitle) }}" host="{{ $ssHost }}" port="{{ $ssPort }}"
                manufacturer="STORESCP" lazy />
        @endif
        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button variant="ghost" wire:click="$set('showSsEchoModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
