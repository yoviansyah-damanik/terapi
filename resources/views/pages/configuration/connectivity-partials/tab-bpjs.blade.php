<?php

use App\Helpers\ConfigurationHelper;
use App\Services\Bpjs\Erm\ErmModuleRegistry;
use Livewire\Component;

new class extends Component {
    public array $bpjs = [
        'vclaim' => ['base_url' => '', 'cons_id' => '', 'user_key' => '', 'secret_key' => ''],
        'antrian_online' => ['base_url' => '', 'cons_id' => '', 'user_key' => '', 'secret_key' => ''],
        'apotek_online' => ['base_url' => '', 'cons_id' => '', 'user_key' => '', 'secret_key' => ''],
        'erm' => ['base_url' => '', 'cons_id' => '', 'user_key' => '', 'secret_key' => ''],
        'icare' => ['base_url' => '', 'cons_id' => '', 'user_key' => '', 'secret_key' => ''],
        'aplicare' => ['base_url' => '', 'cons_id' => '', 'user_key' => '', 'secret_key' => ''],
        'antrian_rs' => ['base_url' => '', 'username' => '', 'password' => ''],
    ];
    public string $bpjsKodePpk = '';
    public string $bpjsNamaPpk = '';
    public string $bpjsKodePpkApotek = '';
    public string $bpjsNamaPpkApotek = '';

    public array $ermModules = [
        'procedure' => true,
        'medication' => true,
        'lab' => true,
        'radiologi' => true,
        'vital_sign' => true,
    ];

    public function mount(): void
    {
        foreach (['vclaim', 'antrian_online', 'apotek_online', 'erm', 'icare', 'aplicare'] as $module) {
            $this->bpjs[$module] = [
                'base_url' => ConfigurationHelper::get("bpjs.{$module}.base_url", ''),
                'cons_id' => ConfigurationHelper::get("bpjs.{$module}.cons_id", ''),
                'user_key' => ConfigurationHelper::get("bpjs.{$module}.user_key", ''),
                'secret_key' => ConfigurationHelper::get("bpjs.{$module}.secret_key", ''),
            ];
        }
        $this->bpjs['antrian_rs'] = [
            'base_url' => ConfigurationHelper::get('bpjs.antrian_rs.base_url', ''),
            'username' => ConfigurationHelper::get('bpjs.antrian_rs.username', ''),
            'password' => ConfigurationHelper::get('bpjs.antrian_rs.password', ''),
        ];
        $this->bpjsKodePpk = ConfigurationHelper::get('bpjs.kode_ppk', '');
        $this->bpjsNamaPpk = ConfigurationHelper::get('bpjs.nama_ppk', '');
        $this->bpjsKodePpkApotek = ConfigurationHelper::get('bpjs.kode_ppk_apotek', '');
        $this->bpjsNamaPpkApotek = ConfigurationHelper::get('bpjs.nama_ppk_apotek', '');

        foreach (array_keys($this->ermModules) as $key) {
            $this->ermModules[$key] = (bool) ConfigurationHelper::get("bpjs.erm.modules.{$key}", true);
        }
    }

    public function saveBpjs(): void
    {
        foreach (['vclaim', 'antrian_online', 'apotek_online', 'erm', 'icare', 'aplicare'] as $module) {
            ConfigurationHelper::set("bpjs.{$module}.base_url", $this->bpjs[$module]['base_url'] ?? '');
            ConfigurationHelper::set("bpjs.{$module}.cons_id", $this->bpjs[$module]['cons_id'] ?? '');
            ConfigurationHelper::set("bpjs.{$module}.user_key", $this->bpjs[$module]['user_key'] ?? '', encrypted: true);
            ConfigurationHelper::set("bpjs.{$module}.secret_key", $this->bpjs[$module]['secret_key'] ?? '', encrypted: true);
        }
        ConfigurationHelper::set('bpjs.antrian_rs.base_url', $this->bpjs['antrian_rs']['base_url'] ?? '');
        ConfigurationHelper::set('bpjs.antrian_rs.username', $this->bpjs['antrian_rs']['username'] ?? '', encrypted: true);
        ConfigurationHelper::set('bpjs.antrian_rs.password', $this->bpjs['antrian_rs']['password'] ?? '', encrypted: true);

        ConfigurationHelper::set('bpjs.kode_ppk', $this->bpjsKodePpk);
        ConfigurationHelper::set('bpjs.nama_ppk', $this->bpjsNamaPpk);
        ConfigurationHelper::set('bpjs.kode_ppk_apotek', $this->bpjsKodePpkApotek);
        ConfigurationHelper::set('bpjs.nama_ppk_apotek', $this->bpjsNamaPpkApotek);

        $this->dispatch('toast', type: 'success', message: 'Konfigurasi BPJS berhasil disimpan.');
    }

    public function saveErmModules(): void
    {
        foreach ($this->ermModules as $key => $value) {
            ConfigurationHelper::set("bpjs.erm.modules.{$key}", (bool) $value);
        }

        $this->dispatch('toast', type: 'success', message: 'Modul eRM berhasil disimpan.');
    }
}; ?>

<div class="space-y-4">
    <form wire:submit="saveBpjs" class="space-y-4">

        {{-- Kode PPK Global --}}
        <x-ui.section-card title="Kode PPK">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <flux:label>Kode PPK RS BPJS</flux:label>
                    <flux:input wire:model="bpjsKodePpk" placeholder="0220R002" class="font-mono" />
                </div>
                <div>
                    <flux:label>Nama PPK RS BPJS</flux:label>
                    <flux:input wire:model="bpjsNamaPpk" placeholder="Nama Rumah Sakit" />
                </div>
                <div>
                    <flux:label>Kode PPK Apotek BPJS</flux:label>
                    <flux:input wire:model="bpjsKodePpkApotek" placeholder="22419" class="font-mono" />
                </div>
                <div>
                    <flux:label>Nama PPK Apotek BPJS</flux:label>
                    <flux:input wire:model="bpjsNamaPpkApotek" placeholder="IF Nama RS" />
                </div>
            </div>
        </x-ui.section-card>

        {{-- Modul BPJS --}}
        <x-ui.section-card title="Modul BPJS Kesehatan">
            @foreach ([['vclaim', 'VClaim', 'Get Peserta', 'GET', '/Peserta/nokartu/0/tglSEP/2024-01-01'], ['antrian_online', 'Antrian Online', 'Referensi Poli', 'GET', '/ref/poli'], ['apotek_online', 'Apotek Online', 'Referensi DPHO', 'GET', '/referensi/dpho'], ['erm', 'eRM', 'Insert eRM', 'POST', '/eclaim/rekammedis/insert'], ['icare', 'ICare', 'Validasi ICare', 'POST', '/api/rs/validate'], ['aplicare', 'Aplicare', 'Lihat Kamar', 'GET', null]] as [$module, $label, $testLabel, $testMethod, $testPath])
                @php
                    $moduleBaseUrl = rtrim($bpjs[$module]['base_url'] ?? '', '/');
                    $testUrl = $module === 'aplicare'
                        ? $moduleBaseUrl . '/rest/bed/read/' . $bpjsKodePpk . '/1/1'
                        : $moduleBaseUrl . $testPath;
                @endphp
                <div @class([
                    'py-6',
                    'pt-0' => $loop->first,
                    'border-t border-zinc-200 dark:border-primary-dark-700' => !$loop->first,
                ])>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">
                                {{ $label }}
                            </h4>
                            <p class="text-xs text-zinc-400 mt-0.5">Uji: {{ $testLabel }}</p>
                        </div>
                        <livewire:components.connection-result :key="'connection-bpjs-' . $module" :url="$testUrl" :method="$testMethod"
                            :hmac-bpjs="[
                                'cons_id' => $bpjs[$module]['cons_id'],
                                'secret_key' => $bpjs[$module]['secret_key'],
                                'user_key' => $bpjs[$module]['user_key'],
                            ]" name="connection-bpjs-{{ str_replace('_', '-', $module) }}"
                            title="Tes Koneksi — {{ $label }} ({{ $testLabel }})" label="Tes Koneksi"
                            variant="ghost" icon="signal" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <flux:label>Base URL</flux:label>
                            <flux:input wire:model="bpjs.{{ $module }}.base_url"
                                placeholder="https://apijkn-dev.bpjs-kesehatan.go.id/..." />
                        </div>
                        <div>
                            <flux:label>Cons ID</flux:label>
                            <flux:input wire:model="bpjs.{{ $module }}.cons_id" placeholder="1165"
                                class="font-mono" />
                        </div>
                        <div>
                            <flux:label>User Key <span
                                    class="text-xs text-zinc-400 font-normal">(terenkripsi)</span></flux:label>
                            <flux:input type="password" wire:model="bpjs.{{ $module }}.user_key"
                                placeholder="••••••••••••••••••••••••••••••••" />
                        </div>
                        <div class="sm:col-span-2">
                            <flux:label>Secret Key <span
                                    class="text-xs text-zinc-400 font-normal">(terenkripsi)</span></flux:label>
                            <flux:input type="password" wire:model="bpjs.{{ $module }}.secret_key"
                                placeholder="••••••••••" />
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Antrian RS (JKN Mobile) --}}
            <div class="py-6 border-t border-zinc-200 dark:border-primary-dark-700">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">
                            Antrian RS <span class="font-normal text-zinc-400">(JKN Mobile)</span>
                        </h4>
                        <p class="text-xs text-zinc-400 mt-0.5">Uji: Get Token</p>
                    </div>
                    <livewire:components.connection-result key="connection-bpjs-antrian-rs"
                        :url="rtrim($bpjs['antrian_rs']['base_url'] ?? '', '/') . '/auth'" method="GET"
                        :headers="[
                            'x-username' => $bpjs['antrian_rs']['username'],
                            'x-password' => $bpjs['antrian_rs']['password'],
                        ]" name="connection-bpjs-antrian-rs"
                        title="Tes Koneksi — Antrian RS (Get Token)" label="Tes Koneksi" variant="ghost"
                        icon="signal" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <flux:label>Base URL</flux:label>
                        <flux:input wire:model="bpjs.antrian_rs.base_url"
                            placeholder="https://simrs.example.com:8888" />
                    </div>
                    <div>
                        <flux:label>Username <span class="text-xs text-zinc-400 font-normal">(terenkripsi)</span>
                        </flux:label>
                        <flux:input wire:model="bpjs.antrian_rs.username" placeholder="bpjs" />
                    </div>
                    <div>
                        <flux:label>Password <span class="text-xs text-zinc-400 font-normal">(terenkripsi)</span>
                        </flux:label>
                        <flux:input type="password" wire:model="bpjs.antrian_rs.password"
                            placeholder="••••••••" />
                    </div>
                </div>
            </div>
        </x-ui.section-card>

        <div class="flex items-center gap-3">
            <x-atoms.button type="submit" variant="primary" icon="check">Simpan</x-atoms.button>
            <span wire:loading wire:target="saveBpjs"
                class="text-sm text-zinc-500 dark:text-primary-dark-400">Menyimpan...</span>
        </div>
    </form>

    {{-- Panel Modul eRM --}}
    <x-ui.section-card title="Modul Pengiriman eRM BPJS"
        subtitle="Aktifkan atau nonaktifkan resource yang disertakan dalam bundle saat mengirim eRM. Resource yang dinonaktifkan tidak akan divalidasi maupun dikirim.">
        <div class="space-y-3">
            <flux:switch wire:model="ermModules.procedure" label="Procedure (ICD-9 + Tindakan)" />
            <flux:switch wire:model="ermModules.medication" label="Medication (Resep + Obat Rawatan)" />
            <flux:switch wire:model="ermModules.lab" label="Diagnostic Laboratorium" />
            <flux:switch wire:model="ermModules.radiologi" label="Diagnostic Radiologi" />
            <flux:switch wire:model="ermModules.vital_sign" label="Vital Sign" />
        </div>
        <x-slot:footer>
            <div class="flex items-center gap-3">
                <x-atoms.button wire:click="saveErmModules" variant="primary" icon="check">Simpan</x-atoms.button>
                <span wire:loading wire:target="saveErmModules"
                    class="text-sm text-zinc-500 dark:text-primary-dark-400">Menyimpan...</span>
            </div>
        </x-slot:footer>
    </x-ui.section-card>
</div>
