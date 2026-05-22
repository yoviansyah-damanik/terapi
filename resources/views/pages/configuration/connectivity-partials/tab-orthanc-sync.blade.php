<?php

use App\Helpers\ConfigurationHelper;
use Livewire\Component;

new class extends Component {
    public string $syncUrl = '';
    public string $syncApiKey = '';

    public function mount(): void
    {
        $this->syncUrl = ConfigurationHelper::get('orthanc.sync_url', 'http://127.0.0.1:9123');
        $this->syncApiKey = ConfigurationHelper::get('orthanc.sync_api_key', '');
    }

    public function save(): void
    {
        ConfigurationHelper::set('orthanc.sync_url', rtrim($this->syncUrl, '/'));
        ConfigurationHelper::set('orthanc.sync_api_key', $this->syncApiKey, true);

        $this->dispatch('toast', type: 'success', message: 'Konfigurasi Orthanc Sync berhasil disimpan.');
    }
}; ?>

<div class="space-y-4">
    <x-ui.section-card title="Konfigurasi Orthanc Sync"
        subtitle="Pengaturan jembatan sinkronisasi worklist antara SIMRS dan PACS Orthanc.">
        <form id="form-sync" wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <flux:label>Base URL Orthanc Sync (Django App)</flux:label>
                    <flux:input wire:model="syncUrl" placeholder="http://127.0.0.1:9123" />
                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-1">
                        URL tempat aplikasi jembatan Orthanc-Sync berjalan.
                    </p>
                </div>

                <div>
                    <flux:label>X-API-Key</flux:label>
                    <flux:input type="password" wire:model="syncApiKey" placeholder="Masukkan API Key" />
                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-1">
                        Kunci otentikasi untuk mengakses API Orthanc-Sync. Pastikan key ini sudah terdaftar di dashboard
                        Orthanc Sync.
                    </p>
                </div>

                <div class="pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                    <div class="flex flex-col gap-1 mb-4">
                        <flux:label>Webhook Callback URL</flux:label>
                        <div class="flex items-center gap-2">
                            <code
                                class="flex-1 px-3 py-2 bg-zinc-100 dark:bg-primary-dark-900 rounded-lg text-xs font-mono text-zinc-600 dark:text-primary-dark-400 break-all">
                                {{ url('/api/webhooks/orthanc/worklist') }}
                            </code>
                            <x-atoms.button size="xs" variant="ghost" icon="clipboard"
                                onclick="navigator.clipboard.writeText('{{ url('/api/webhooks/orthanc/worklist') }}'); $dispatch('toast', {type: 'success', message: 'URL disalin'})">
                                Salin
                            </x-atoms.button>
                        </div>
                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                            Gunakan URL ini di dashboard <strong>Orthanc Sync</strong> sebagai default callback URL.
                            URL ini digunakan untuk menerima pembaruan status (Berhasil/Gagal) dari jembatan
                            sinkronisasi secara otomatis.
                        </p>
                    </div>

                    <livewire:components.connection-result size="sm" icon="signal" variant="outline"
                        :url="rtrim($this->syncUrl, '/') . '/api/status'" method="GET" name="connection-sync" title="Tes Koneksi Orthanc Sync"
                        :headers="['X-API-Key' => $this->syncApiKey]" />
                </div>
            </div>
        </form>

        <x-slot:footer>
            <div class="flex items-center gap-3">
                <x-atoms.button form="form-sync" type="submit" variant="primary" icon="check">
                    Simpan Perubahan
                </x-atoms.button>
                <span wire:loading wire:target="save" class="text-sm text-zinc-500 dark:text-primary-dark-400">
                    Menyimpan...
                </span>
            </div>
        </x-slot:footer>
    </x-ui.section-card>
</div>
