<?php

use App\Helpers\ConfigurationHelper;
use App\Helpers\QrCodeHelper;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Pengaturan QR Code')] class extends Component {
    use WithFileUploads;

    // Generator QR defaults
    public string $foregroundColor = '#000000';
    public string $backgroundColor = '#FFFFFF';
    public int $size = 300;
    public int $margin = 10;
    public string $errorCorrection = 'M';
    public bool $logoEnabled = false;
    public $logoFile = null;
    public int $logoSize = 60;
    public ?string $currentLogoPath = null;

    public ?string $qrPreview = null;

    public function mount(): void
    {
        $this->foregroundColor = ConfigurationHelper::get('qrcode.foreground_color', '#000000');
        $this->backgroundColor = ConfigurationHelper::get('qrcode.background_color', '#FFFFFF');
        $this->size = (int) ConfigurationHelper::get('qrcode.size', '300');
        $this->margin = (int) ConfigurationHelper::get('qrcode.margin', '10');
        $this->errorCorrection = ConfigurationHelper::get('qrcode.error_correction', 'M');
        $this->logoEnabled = ConfigurationHelper::get('qrcode.logo_enabled', '0') === '1';
        $this->logoSize = (int) ConfigurationHelper::get('qrcode.logo_size', '60');
        $this->currentLogoPath = ConfigurationHelper::get('qrcode.logo_path');
        $this->generatePreview();
    }

    public function generatePreview(): void
    {
        try {
            $result = QrCodeHelper::generate('https://example.com', 'png', [
                'size' => $this->size,
                'margin' => $this->margin,
                'foreground_color' => $this->foregroundColor,
                'background_color' => $this->backgroundColor,
                'error_correction' => $this->errorCorrection,
                'logo_enabled' => $this->logoEnabled,
                'logo_path' => $this->currentLogoPath,
                'logo_size' => $this->logoSize,
            ]);
            $this->qrPreview = $result->getDataUri();
        } catch (\Throwable) {
            $this->qrPreview = null;
        }
    }

    public function saveSettings(): void
    {
        $this->validate([
            'foregroundColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'backgroundColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'size' => 'required|integer|min:50|max:2000',
            'margin' => 'required|integer|min:0|max:100',
            'errorCorrection' => 'required|in:L,M,Q,H',
            'logoFile' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'logoSize' => 'required|integer|min:20|max:200',
        ]);

        ConfigurationHelper::set('qrcode.foreground_color', $this->foregroundColor);
        ConfigurationHelper::set('qrcode.background_color', $this->backgroundColor);
        ConfigurationHelper::set('qrcode.size', (string) $this->size);
        ConfigurationHelper::set('qrcode.margin', (string) $this->margin);
        ConfigurationHelper::set('qrcode.error_correction', $this->errorCorrection);
        ConfigurationHelper::set('qrcode.logo_enabled', $this->logoEnabled ? '1' : '0');
        ConfigurationHelper::set('qrcode.logo_size', (string) $this->logoSize);

        if ($this->logoFile) {
            if ($this->currentLogoPath && Storage::disk('public')->exists($this->currentLogoPath)) {
                Storage::disk('public')->delete($this->currentLogoPath);
            }
            $path = $this->logoFile->store('qrcode', 'public');
            ConfigurationHelper::set('qrcode.logo_path', $path);
            $this->currentLogoPath = $path;
            $this->logoFile = null;
        }

        $this->generatePreview();
        $this->toastSuccess('Pengaturan QR Code berhasil disimpan.');
    }

    public function removeLogo(): void
    {
        if ($this->currentLogoPath && Storage::disk('public')->exists($this->currentLogoPath)) {
            Storage::disk('public')->delete($this->currentLogoPath);
        }
        ConfigurationHelper::set('qrcode.logo_path', null);
        ConfigurationHelper::set('qrcode.logo_enabled', '0');
        $this->currentLogoPath = null;
        $this->logoEnabled = false;
        $this->generatePreview();
        $this->toastSuccess('Logo QR Code berhasil dihapus.');
    }
};
?>

<div>
    <x-ui.page-header title="Pengaturan QR Code" subtitle="Konfigurasi default generator QR Code">
        <x-slot:actions>
            <div class="flex items-center gap-3">
                <x-atoms.button wire:click="saveSettings" variant="primary" icon="check">Simpan
                    Perubahan</x-atoms.button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Form --}}
        <div class="lg:col-span-2">
            <x-ui.section-card title="Default Generator QR">
                <div class="space-y-5">

                    {{-- Warna --}}
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Warna Modul</flux:label>
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model.live.debounce.300ms="foregroundColor"
                                    class="h-9 w-12 rounded border border-zinc-200 dark:border-primary-dark-700 cursor-pointer bg-transparent" />
                                <flux:input wire:model.live.debounce.300ms="foregroundColor"
                                    class="font-mono text-sm flex-1" />
                            </div>
                            @error('foregroundColor')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Latar Belakang</flux:label>
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model.live.debounce.300ms="backgroundColor"
                                    class="h-9 w-12 rounded border border-zinc-200 dark:border-primary-dark-700 cursor-pointer bg-transparent" />
                                <flux:input wire:model.live.debounce.300ms="backgroundColor"
                                    class="font-mono text-sm flex-1" />
                            </div>
                            @error('backgroundColor')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>
                    </div>

                    {{-- Ukuran & Margin --}}
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Ukuran Default (px)</flux:label>
                            <flux:input type="number" wire:model.live.debounce.300ms="size" min="50"
                                max="2000" />
                            @error('size')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Margin Default (px)</flux:label>
                            <flux:input type="number" wire:model.live.debounce.300ms="margin" min="0"
                                max="100" />
                            @error('margin')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>
                    </div>

                    {{-- Error Correction --}}
                    <flux:field>
                        <flux:label>Koreksi Error Default</flux:label>
                        <flux:select wire:model.live="errorCorrection">
                            <flux:select.option value="L">L — Low (7%)</flux:select.option>
                            <flux:select.option value="M">M — Medium (15%)</flux:select.option>
                            <flux:select.option value="Q">Q — Quartile (25%)</flux:select.option>
                            <flux:select.option value="H">H — High (30%)</flux:select.option>
                        </flux:select>
                        @error('errorCorrection')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>

                    <hr class="border-zinc-200 dark:border-primary-dark-700">

                    {{-- Logo --}}
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Logo di Tengah
                                QR</p>
                            <flux:switch wire:model.live="logoEnabled" />
                        </div>

                        @if ($logoEnabled)
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>Ukuran Logo (px)</flux:label>
                                    <flux:input type="number" wire:model.live.debounce.300ms="logoSize" min="20"
                                        max="200" />
                                    @error('logoSize')
                                        <flux:error>{{ $message }}</flux:error>
                                    @enderror
                                </flux:field>

                                <div>
                                    <flux:field>
                                        <flux:label>Ganti Logo</flux:label>
                                        <flux:input type="file" wire:model="logoFile" accept=".png,.jpg,.jpeg" />
                                        @error('logoFile')
                                            <flux:error>{{ $message }}</flux:error>
                                        @enderror
                                    </flux:field>
                                    <p class="mt-1 text-[11px] text-zinc-400 dark:text-primary-dark-500">PNG atau
                                        JPG, maks.
                                        2 MB</p>
                                </div>
                            </div>

                            @if ($currentLogoPath)
                                <div
                                    class="flex items-center gap-3 p-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                                    <img src="{{ Storage::disk('public')->url($currentLogoPath) }}" alt="Logo"
                                        class="h-10 w-10 rounded object-contain border border-zinc-200 dark:border-primary-dark-700" />
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300 truncate">
                                            {{ basename($currentLogoPath) }}
                                        </p>
                                        <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500">Logo aktif
                                            saat ini
                                        </p>
                                    </div>
                                    <x-atoms.button variant="ghost" size="sm" icon="trash"
                                        wire:click="removeLogo" wire:confirm="Hapus logo ini?"
                                        class="text-red-500 hover:text-red-600" />
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </x-ui.section-card>
        </div>

        {{-- Preview --}}
        <div>
            <x-ui.section-card title="Preview">
                <div class="flex flex-col items-center gap-4">
                    @if ($qrPreview)
                        <img src="{{ $qrPreview }}" alt="QR Preview"
                            class="max-w-full rounded-xl border border-zinc-200 dark:border-primary-dark-700 shadow" />
                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500 text-center">
                            {{ $size }}×{{ $size }}px · Margin {{ $margin }}px · Error
                            {{ $errorCorrection }}
                        </p>
                    @else
                        <div class="flex flex-col items-center gap-3 py-10">
                            <div
                                class="flex items-center justify-center w-16 h-16 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="qr-code" class="w-8 h-8 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Preview tidak tersedia</p>
                        </div>
                    @endif
                    <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500 text-center">
                        Preview menggunakan data contoh <span class="font-mono">https://example.com</span>
                    </p>
                </div>
            </x-ui.section-card>
        </div>

    </div>
</div>
