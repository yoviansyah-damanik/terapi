<?php

use App\Helpers\ConfigurationHelper;
use Livewire\Component;

new class extends Component {
    public string $rsonlineBaseUrl = '';
    public string $rsonlineRsId = '';
    public string $rsonlinePassword = '';

    public function mount(): void
    {
        $this->rsonlineBaseUrl = ConfigurationHelper::get('rsonline.base_url', '');
        $this->rsonlineRsId = ConfigurationHelper::get('rsonline.rs_id', '');
        $this->rsonlinePassword = ConfigurationHelper::get('rsonline.password', '');
    }

    public function saveRsonline(): void
    {
        $this->validate(
            [
                'rsonlineBaseUrl' => 'required|url',
                'rsonlineRsId' => 'required|string',
                'rsonlinePassword' => 'required|string',
            ],
            [
                'rsonlineBaseUrl.required' => 'Base URL wajib diisi.',
                'rsonlineBaseUrl.url' => 'Base URL harus berupa URL yang valid.',
                'rsonlineRsId.required' => 'RS ID wajib diisi.',
                'rsonlinePassword.required' => 'Password wajib diisi.',
            ],
        );

        ConfigurationHelper::set('rsonline.base_url', rtrim($this->rsonlineBaseUrl, '/'));
        ConfigurationHelper::set('rsonline.rs_id', $this->rsonlineRsId);
        ConfigurationHelper::set('rsonline.password', $this->rsonlinePassword);

        $this->dispatch('toast', type: 'success', message: 'Konfigurasi RS Online berhasil disimpan.');
    }
}; ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    {{-- Form Konfigurasi --}}
    <div class="lg:col-span-2">
        <x-ui.section-card title="Kredensial API"
            subtitle="Dikirim sebagai header HTTP pada setiap request ke RS Online Kemenkes.">
            <div class="space-y-4">
                <div>
                    <flux:label>Base URL</flux:label>
                    <flux:input wire:model="rsonlineBaseUrl" type="url"
                        placeholder="https://rs-online.kemkes.go.id" />
                    @error('rsonlineBaseUrl')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <flux:label>RS ID <span class="text-xs font-normal text-zinc-400">(X-rs-id)</span>
                    </flux:label>
                    <flux:input wire:model="rsonlineRsId" placeholder="Kode RS Online dari Kemenkes" />
                    @error('rsonlineRsId')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div x-data="{ show: false }">
                    <flux:label>Password <span class="text-xs font-normal text-zinc-400">(X-pass, dikirim
                            sebagai MD5)</span></flux:label>
                    <div class="flex gap-2">
                        <flux:input wire:model="rsonlinePassword" x-bind:type="show ? 'text' : 'password'"
                            placeholder="Password akan di-hash MD5 saat dikirim" class="flex-1" />
                        <x-atoms.button type="button" variant="ghost" size="sm" x-on:click="show = !show">
                            <flux:icon x-show="!show" name="eye" class="w-4 h-4" />
                            <flux:icon x-show="show" name="eye-slash" class="w-4 h-4" />
                        </x-atoms.button>
                    </div>
                    @error('rsonlinePassword')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <x-slot:footer>
                <div class="flex items-center gap-3">
                    <x-atoms.button wire:click="saveRsonline" variant="primary" icon="check">Simpan
                    </x-atoms.button>
                    <livewire:components.connection-result
                        :url="rtrim($rsonlineBaseUrl, '/') . '/fo/index.php/Referensi/status_rawat'" method="GET"
                        :headers="[
                            'X-rs-id' => $rsonlineRsId,
                            'X-pass' => $rsonlinePassword ? md5($rsonlinePassword) : '',
                        ]" :volatile-headers="['X-Timestamp' => 'time']" name="connection-rsonline"
                        title="Tes Koneksi — RS Online" />
                    <span wire:loading wire:target="saveRsonline"
                        class="text-sm text-zinc-500 dark:text-primary-dark-400">Menyimpan...</span>
                </div>
            </x-slot:footer>
        </x-ui.section-card>
    </div>

    {{-- Info & Navigasi --}}
    <div class="space-y-4">
        <x-ui.section-card title="Mekanisme Autentikasi">
            <div class="space-y-2 text-xs text-zinc-600 dark:text-primary-dark-400">
                <div class="flex gap-2">
                    <code
                        class="font-mono bg-zinc-100 dark:bg-primary-dark-700 px-1.5 py-0.5 rounded shrink-0">X-rs-id</code>
                    <span>ID rumah sakit di RS Online</span>
                </div>
                <div class="flex gap-2">
                    <code
                        class="font-mono bg-zinc-100 dark:bg-primary-dark-700 px-1.5 py-0.5 rounded shrink-0">X-pass</code>
                    <span>Password yang di-hash MD5</span>
                </div>
                <div class="flex gap-2">
                    <code
                        class="font-mono bg-zinc-100 dark:bg-primary-dark-700 px-1.5 py-0.5 rounded shrink-0">X-Timestamp</code>
                    <span>Unix timestamp (validitas 10 menit)</span>
                </div>
            </div>
        </x-ui.section-card>

        <x-ui.section-card title="Halaman RS Online">
            <div class="space-y-1.5">
                <a wire:navigate href="{{ route('rsonline.referensi') }}"
                    class="flex items-center gap-2 text-sm text-zinc-600 dark:text-primary-dark-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                    <flux:icon name="book-open" class="w-4 h-4" />
                    Master Referensi
                </a>
                <a wire:navigate href="{{ route('rsonline.pasien') }}"
                    class="flex items-center gap-2 text-sm text-zinc-600 dark:text-primary-dark-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                    <flux:icon name="users" class="w-4 h-4" />
                    Data Pasien
                </a>
                <a wire:navigate href="{{ route('rsonline.fasyankes') }}"
                    class="flex items-center gap-2 text-sm text-zinc-600 dark:text-primary-dark-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                    <flux:icon name="building-office-2" class="w-4 h-4" />
                    Data Fasyankes
                </a>
            </div>
        </x-ui.section-card>
    </div>
</div>
