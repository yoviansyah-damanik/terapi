<?php

use App\Helpers\ConfigurationHelper;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component {
    public string $ssAuthUrl = '';
    public string $ssBaseUrl = '';
    public string $ssFhirUrl = '';
    public string $ssConsentUrl = '';
    public string $ssClientId = '';
    public string $ssClientSecret = '';
    public string $ssOrgId = '';
    public string $ssKodePpk = '';
    public string $ssNamaPpk = '';

    public function mount(): void
    {
        $this->ssAuthUrl = ConfigurationHelper::get('satusehat.auth_url', '');
        $this->ssBaseUrl = ConfigurationHelper::get('satusehat.base_url', '');
        $this->ssFhirUrl = ConfigurationHelper::get('satusehat.fhir_url', '');
        $this->ssConsentUrl = ConfigurationHelper::get('satusehat.consent_url', '');
        $this->ssClientId = ConfigurationHelper::get('satusehat.client_id', '');
        $this->ssClientSecret = ConfigurationHelper::get('satusehat.client_secret', '');
        $this->ssOrgId = ConfigurationHelper::get('satusehat.organization_id', '');
        $this->ssKodePpk = ConfigurationHelper::get('satusehat.kode_ppk_kemenkes', '');
        $this->ssNamaPpk = ConfigurationHelper::get('satusehat.nama_ppk_kemenkes', '');
    }

    public function saveSatuSehat(): void
    {
        ConfigurationHelper::set('satusehat.auth_url', $this->ssAuthUrl);
        ConfigurationHelper::set('satusehat.base_url', $this->ssBaseUrl);
        ConfigurationHelper::set('satusehat.fhir_url', $this->ssFhirUrl);
        ConfigurationHelper::set('satusehat.consent_url', $this->ssConsentUrl);
        ConfigurationHelper::set('satusehat.organization_id', $this->ssOrgId);
        ConfigurationHelper::set('satusehat.kode_ppk_kemenkes', $this->ssKodePpk);
        ConfigurationHelper::set('satusehat.nama_ppk_kemenkes', $this->ssNamaPpk);
        ConfigurationHelper::set('satusehat.client_id', $this->ssClientId, encrypted: true);
        ConfigurationHelper::set('satusehat.client_secret', $this->ssClientSecret, encrypted: true);

        // Invalidasi token lama agar re-auth dengan credential baru
        Cache::forget(config('satusehat.cache.key', 'satusehat_access_token'));

        $this->dispatch('toast', type: 'success', message: 'Konfigurasi Satu Sehat berhasil disimpan.');
    }
}; ?>

<x-ui.section-card title="Satu Sehat FHIR API"
    subtitle="Kredensial OAuth2 dan endpoint untuk integrasi Satu Sehat Kemenkes. Client ID & Secret disimpan terenkripsi.">
    <form id="form-satusehat" wire:submit="saveSatuSehat" class="space-y-6">
        <div>
            <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 mb-3">
                Endpoint</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:label>Auth URL</flux:label>
                    <flux:input wire:model="ssAuthUrl"
                        placeholder="https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1" />
                </div>
                <div>
                    <flux:label>Base URL</flux:label>
                    <flux:input wire:model="ssBaseUrl"
                        placeholder="https://api-satusehat-stg.dto.kemkes.go.id" />
                </div>
                <div>
                    <flux:label>FHIR URL</flux:label>
                    <flux:input wire:model="ssFhirUrl"
                        placeholder="https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1" />
                </div>
                <div>
                    <flux:label>Consent URL</flux:label>
                    <flux:input wire:model="ssConsentUrl"
                        placeholder="https://api-satusehat-stg.dto.kemkes.go.id/consent/v1" />
                </div>
            </div>
        </div>
        <hr class="border-zinc-200 dark:border-primary-dark-700">
        <div>
            <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 mb-3">
                Kredensial</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:label>Client ID <span class="text-xs text-zinc-400 font-normal">(terenkripsi)</span>
                    </flux:label>
                    <flux:input wire:model="ssClientId" placeholder="..." />
                </div>
                <div>
                    <flux:label>Client Secret <span class="text-xs text-zinc-400 font-normal">(terenkripsi)</span></flux:label>
                    <flux:input type="password" wire:model="ssClientSecret" placeholder="••••••••" />
                </div>
                <div>
                    <flux:label>Organization ID</flux:label>
                    <flux:input wire:model="ssOrgId" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                        class="font-mono" />
                </div>
            </div>
        </div>
        <hr class="border-zinc-200 dark:border-primary-dark-700">
        <div>
            <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 mb-3">
                Kode PPK Kemenkes</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:label>Kode PPK</flux:label>
                    <flux:input wire:model="ssKodePpk" placeholder="1277015" class="font-mono" />
                </div>
                <div>
                    <flux:label>Nama PPK</flux:label>
                    <flux:input wire:model="ssNamaPpk" placeholder="Nama Rumah Sakit" />
                </div>
            </div>
        </div>
    </form>
    <x-slot:footer>
        <div class="flex items-center gap-3">
            <x-atoms.button form="form-satusehat" type="submit" variant="primary" icon="check">Simpan
            </x-atoms.button>
            <span wire:loading wire:target="saveSatuSehat"
                class="text-sm text-zinc-500 dark:text-primary-dark-400">Menyimpan...</span>
            <livewire:components.connection-result :url="rtrim($ssAuthUrl, '/') . '/accesstoken?grant_type=client_credentials'" method="POST"
                :body="['client_id' => $ssClientId, 'client_secret' => $ssClientSecret]" :as-form="true"
                name="connection-ss-auth" title="Tes Koneksi — SatuSehat OAuth2" label="Tes Auth" variant="ghost"
                icon="key" />
            <livewire:components.connection-result :url="rtrim($ssFhirUrl, '/') . '/metadata'" method="GET" :reachable="true"
                name="connection-ss-fhir" title="Tes Koneksi — SatuSehat FHIR (metadata)" label="Tes FHIR"
                variant="ghost" icon="globe-alt" />
        </div>
    </x-slot:footer>
</x-ui.section-card>
