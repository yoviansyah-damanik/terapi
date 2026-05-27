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

    public function updated(string $property): void
    {
        $triggers = ['foregroundColor', 'backgroundColor', 'size', 'margin', 'errorCorrection', 'logoEnabled', 'logoSize'];
        if (in_array($property, $triggers)) {
            $this->generatePreview();
        }
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
    <x-ui.page-header title="Pengaturan QR Code" subtitle="Konfigurasi tampilan default generator QR Code">
        <x-slot:actions>
            <x-atoms.button wire:click="saveSettings" variant="primary" icon="check">Simpan Perubahan</x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Form Settings --}}
        <div class="space-y-4 lg:col-span-2">

            {{-- Tampilan --}}
            <x-ui.section-card>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <flux:icon name="swatch" class="h-4 w-4 text-zinc-400" />
                        <span>Tampilan</span>
                    </div>
                </x-slot:title>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Warna Modul (Foreground)</flux:label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model.live.debounce.300ms="foregroundColor"
                                class="h-9 w-12 shrink-0 cursor-pointer rounded-lg border border-zinc-200 bg-transparent p-0.5 dark:border-primary-dark-700" />
                            <flux:input wire:model.live.debounce.300ms="foregroundColor" class="font-mono text-sm" />
                        </div>
                        @error('foregroundColor')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>Warna Latar Belakang</flux:label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model.live.debounce.300ms="backgroundColor"
                                class="h-9 w-12 shrink-0 cursor-pointer rounded-lg border border-zinc-200 bg-transparent p-0.5 dark:border-primary-dark-700" />
                            <flux:input wire:model.live.debounce.300ms="backgroundColor" class="font-mono text-sm" />
                        </div>
                        @error('backgroundColor')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>
                </div>
            </x-ui.section-card>

            {{-- Dimensi & Kualitas --}}
            <x-ui.section-card>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <flux:icon name="squares-2x2" class="h-4 w-4 text-zinc-400" />
                        <span>Dimensi & Kualitas</span>
                    </div>
                </x-slot:title>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <flux:field>
                        <flux:label>Ukuran (px)</flux:label>
                        <flux:input type="number" wire:model.live.debounce.500ms="size" min="50"
                            max="2000" />
                        @error('size')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>Margin (px)</flux:label>
                        <flux:input type="number" wire:model.live.debounce.500ms="margin" min="0"
                            max="100" />
                        @error('margin')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>Koreksi Error</flux:label>
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
                </div>
            </x-ui.section-card>

            {{-- Logo --}}
            <x-ui.section-card>
                <x-slot:title>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <flux:icon name="photo" class="h-4 w-4 text-zinc-400" />
                            <span>Logo di Tengah QR</span>
                        </div>
                        <flux:switch wire:model.live="logoEnabled" />
                    </div>
                </x-slot:title>

                @if ($logoEnabled)
                    <div class="space-y-4"
                        x-data="{
                            dragging: false,
                            localPreview: null,
                            localName: null,
                            handleDrop(e) {
                                this.dragging = false;
                                const file = e.dataTransfer.files[0];
                                if (!file) return;
                                const dt = new DataTransfer();
                                dt.items.add(file);
                                this.$refs.fileInput.files = dt.files;
                                this.$refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                                this.localPreview = URL.createObjectURL(file);
                                this.localName = file.name;
                            },
                            handleChange(e) {
                                const file = e.target.files[0];
                                if (!file) return;
                                this.localPreview = URL.createObjectURL(file);
                                this.localName = file.name;
                            }
                        }">

                        {{-- Ukuran logo --}}
                        <flux:field class="max-w-xs">
                            <flux:label>Ukuran Logo (px)</flux:label>
                            <flux:input type="number" wire:model.live.debounce.500ms="logoSize" min="20" max="200" />
                            @error('logoSize')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>

                        {{-- Drop zone --}}
                        <div
                            @dragover.prevent="dragging = true"
                            @dragleave.prevent="dragging = false"
                            @drop.prevent="handleDrop($event)"
                            @click="$refs.fileInput.click()"
                            :class="dragging
                                ? 'border-primary-400 bg-primary-50 dark:border-primary-500 dark:bg-primary-900/20'
                                : 'border-zinc-300 bg-zinc-50 dark:border-primary-dark-600 dark:bg-primary-dark-900/40 hover:border-zinc-400 dark:hover:border-primary-dark-500'"
                            class="relative flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-8 transition-colors select-none">

                            <template x-if="localPreview">
                                <img :src="localPreview" class="mb-2 h-16 w-16 rounded-lg object-contain" />
                            </template>
                            <template x-if="!localPreview">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-primary-dark-700">
                                    <flux:icon name="arrow-up-tray" class="h-6 w-6 text-zinc-400 dark:text-primary-dark-400" />
                                </div>
                            </template>

                            <div class="text-center">
                                <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-200"
                                    x-text="localName ?? 'Drag & drop atau klik untuk pilih file'"></p>
                                <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">PNG atau JPG, maks. 2 MB</p>
                            </div>

                            <input type="file" x-ref="fileInput" wire:model="logoFile"
                                @change="handleChange($event)"
                                accept=".png,.jpg,.jpeg" class="hidden" />
                        </div>
                        @error('logoFile')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror

                        {{-- Logo aktif --}}
                        @if ($currentLogoPath)
                            <div class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-primary-dark-700 dark:bg-primary-dark-900/50">
                                <img src="{{ asset("storage/{$currentLogoPath}") }}"
                                    alt="Logo aktif"
                                    class="h-12 w-12 shrink-0 rounded-lg border border-zinc-200 object-contain dark:border-primary-dark-700" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-medium text-zinc-700 dark:text-primary-dark-300">{{ basename($currentLogoPath) }}</p>
                                    <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500">Logo aktif saat ini</p>
                                </div>
                                <x-atoms.button variant="ghost" size="sm" icon="trash" wire:click="removeLogo"
                                    wire:confirm="Hapus logo ini?" class="text-red-500 hover:text-red-600" />
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500">Aktifkan toggle di atas untuk menyisipkan logo di tengah QR Code.</p>
                @endif
            </x-ui.section-card>

        </div>

        {{-- Live Preview (sticky) --}}
        <div class="lg:sticky lg:top-6 lg:self-start">
            <x-ui.section-card title="Live Preview">
                <div class="flex flex-col items-center gap-4">

                    {{-- QR image dengan loading overlay --}}
                    <div class="relative flex items-center justify-center">
                        @if ($qrPreview)
                            <img src="{{ $qrPreview }}" alt="QR Preview"
                                class="max-w-full rounded-2xl border border-zinc-200 shadow-sm dark:border-primary-dark-700"
                                wire:loading.class="opacity-30"
                                wire:target="foregroundColor,backgroundColor,size,margin,errorCorrection,logoEnabled,logoSize,generatePreview" />
                        @else
                            <div
                                class="flex h-48 w-48 flex-col items-center justify-center rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 dark:border-primary-dark-600 dark:bg-primary-dark-900/50">
                                <flux:icon name="qr-code" class="h-12 w-12 text-zinc-200 dark:text-primary-dark-600" />
                                <p class="mt-2 text-xs text-zinc-400 dark:text-primary-dark-500">Preview tidak
                                    tersedia</p>
                            </div>
                        @endif

                        {{-- Loading spinner overlay --}}
                        <div wire:loading
                            wire:target="foregroundColor,backgroundColor,size,margin,errorCorrection,logoEnabled,logoSize,generatePreview"
                            class="absolute inset-0 flex items-center justify-center">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-white/80 shadow dark:bg-primary-dark-800/80">
                                <flux:icon name="arrow-path"
                                    class="h-6 w-6 animate-spin text-zinc-500 dark:text-primary-dark-300" />
                            </div>
                        </div>
                    </div>

                    {{-- Metadata --}}
                    @if ($qrPreview)
                        <div class="w-full space-y-1.5 rounded-xl bg-zinc-50 p-3 dark:bg-primary-dark-900/50">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-400 dark:text-primary-dark-500">Ukuran</span>
                                <span
                                    class="font-mono font-medium text-zinc-700 dark:text-primary-dark-300">{{ $size }}×{{ $size }}
                                    px</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-400 dark:text-primary-dark-500">Margin</span>
                                <span
                                    class="font-mono font-medium text-zinc-700 dark:text-primary-dark-300">{{ $margin }}
                                    px</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-400 dark:text-primary-dark-500">Koreksi Error</span>
                                <span
                                    class="font-medium text-zinc-700 dark:text-primary-dark-300">{{ $errorCorrection }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-400 dark:text-primary-dark-500">Logo</span>
                                @if ($logoEnabled && $currentLogoPath)
                                    <flux:badge color="emerald" size="sm">Aktif · {{ $logoSize }}px
                                    </flux:badge>
                                @elseif ($logoEnabled)
                                    <flux:badge color="amber" size="sm">Belum ada logo</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Nonaktif</flux:badge>
                                @endif
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-400 dark:text-primary-dark-500">Foreground</span>
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="inline-block h-3 w-3 rounded-full border border-zinc-300 dark:border-primary-dark-600"
                                        style="background: {{ $foregroundColor }}"></span>
                                    <span
                                        class="font-mono font-medium text-zinc-700 dark:text-primary-dark-300">{{ strtoupper($foregroundColor) }}</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-zinc-400 dark:text-primary-dark-500">Background</span>
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="inline-block h-3 w-3 rounded-full border border-zinc-300 dark:border-primary-dark-600"
                                        style="background: {{ $backgroundColor }}"></span>
                                    <span
                                        class="font-mono font-medium text-zinc-700 dark:text-primary-dark-300">{{ strtoupper($backgroundColor) }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <p class="text-center text-[11px] text-zinc-400 dark:text-primary-dark-500">
                        Contoh data: <span class="font-mono">https://example.com</span>
                    </p>
                </div>
            </x-ui.section-card>
        </div>

    </div>
</div>
