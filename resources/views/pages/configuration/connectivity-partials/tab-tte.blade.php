<?php

use App\Helpers\ConfigurationHelper;
use App\Helpers\QrCodeHelper;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $tteBaseUrl = '';
    public string $tteUsername = '';
    public string $ttePassword = '';
    public string $tteNik = '';
    public string $ttePassphrase = '';

    public string $contentTemplate = '';
    public float $widthMm = 25;
    public float $heightMm = 25;
    public ?string $qrPreview = null;

    // Tampilan QR TTE
    public string $tteErrorCorrection = 'H';
    public string $tteForegroundColor = '#000000';
    public string $tteBackgroundColor = '#FFFFFF';
    public string $tteRoundBlockMode = 'margin';

    // Logo di tengah QR TTE
    public bool $tteLogoEnabled = false;
    public $tteLogoFile = null;
    public int $tteLogoSize = 60;
    public ?string $tteCurrentLogoPath = null;

    public function mount(): void
    {
        $this->tteBaseUrl = ConfigurationHelper::get('tte.base_url', '');
        $this->tteUsername = ConfigurationHelper::get('tte.username', '');
        $this->ttePassword = ConfigurationHelper::get('tte.password', '');
        $this->tteNik = ConfigurationHelper::get('tte.default_nik', '');
        $this->ttePassphrase = ConfigurationHelper::get('tte.default_passphrase', '');
        $this->loadSettings();
        $this->generatePreview();
    }

    /** Simpan kredensial TTE ke database */
    public function saveCredentials(): void
    {
        $this->validate(
            [
                'tteBaseUrl' => 'required|url',
                'tteUsername' => 'required|string',
                'ttePassword' => 'required|string',
                'tteNik' => 'nullable|string|max:16',
                'ttePassphrase' => 'nullable|string',
            ],
            [
                'tteBaseUrl.required' => 'Base URL wajib diisi.',
                'tteBaseUrl.url' => 'Base URL harus berupa URL yang valid.',
                'tteUsername.required' => 'Username wajib diisi.',
                'ttePassword.required' => 'Password wajib diisi.',
            ],
        );

        ConfigurationHelper::set('tte.base_url', rtrim($this->tteBaseUrl, '/'));
        ConfigurationHelper::set('tte.username', $this->tteUsername, encrypted: true);
        ConfigurationHelper::set('tte.password', $this->ttePassword, encrypted: true);
        ConfigurationHelper::set('tte.default_nik', $this->tteNik);
        ConfigurationHelper::set('tte.default_passphrase', $this->ttePassphrase, encrypted: true);

        $this->toastSuccess('Kredensial TTE berhasil disimpan.');
    }

    private function loadSettings(): void
    {
        $this->contentTemplate = ConfigurationHelper::get('tte.qr.content_template', '{app_name} - Ditandatangani secara elektronik pada {signed_at}');
        $this->widthMm = (float) ConfigurationHelper::get('tte.qr.width_mm', '25');
        $this->heightMm = (float) ConfigurationHelper::get('tte.qr.height_mm', '25');
        $this->tteErrorCorrection = ConfigurationHelper::get('tte.qr.error_correction', 'H');
        $this->tteForegroundColor = ConfigurationHelper::get('tte.qr.foreground_color', '#000000');
        $this->tteBackgroundColor = ConfigurationHelper::get('tte.qr.background_color', '#FFFFFF');
        $this->tteRoundBlockMode = ConfigurationHelper::get('tte.qr.round_block_mode', 'margin');
        $this->tteLogoEnabled = ConfigurationHelper::get('tte.qr.logo_enabled', '0') === '1';
        $this->tteCurrentLogoPath = ConfigurationHelper::get('tte.qr.logo_path');
        $this->tteLogoSize = (int) ConfigurationHelper::get('tte.qr.logo_size', '60');
    }

    /**
     * Generate preview QR berdasarkan template dengan data sample dan logo TTE
     */
    public function generatePreview(): void
    {
        try {
            $sampleData = str_replace(['{app_name}', '{signer_name}', '{signed_at}', '{document_number}'], [config('app.name', 'Suratin'), 'John Doe', now()->format('d/m/Y H:i'), 'DOC-2024-001'], $this->contentTemplate);

            $result = QrCodeHelper::generate($sampleData, 'png', [
                'error_correction' => $this->tteErrorCorrection,
                'foreground_color' => $this->tteForegroundColor,
                'background_color' => $this->tteBackgroundColor,
                'round_block_mode' => $this->tteRoundBlockMode,
                'logo_enabled' => $this->tteLogoEnabled,
                'logo_path' => $this->tteCurrentLogoPath,
                'logo_size' => $this->tteLogoSize,
            ]);
            $this->qrPreview = $result->getDataUri();
        } catch (\Throwable $e) {
            $this->qrPreview = null;
            $this->toastError('Gagal generate preview: ' . $e->getMessage());
        }
    }

    /**
     * Simpan pengaturan TTE ke database
     */
    public function saveSettings(): void
    {
        $this->validate([
            'contentTemplate' => 'required|string|max:500',
            'widthMm' => 'required|numeric|min:5|max:100',
            'heightMm' => 'required|numeric|min:5|max:100',
            'tteErrorCorrection' => 'required|in:L,M,Q,H',
            'tteForegroundColor' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'tteBackgroundColor' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'tteRoundBlockMode' => 'required|in:margin,shrink,none',
            'tteLogoFile' => 'nullable|image|max:2048',
            'tteLogoSize' => 'required|integer|min:20|max:200',
        ]);

        ConfigurationHelper::set('tte.qr.content_template', $this->contentTemplate);
        ConfigurationHelper::set('tte.qr.width_mm', (string) $this->widthMm);
        ConfigurationHelper::set('tte.qr.height_mm', (string) $this->heightMm);
        ConfigurationHelper::set('tte.qr.error_correction', $this->tteErrorCorrection);
        ConfigurationHelper::set('tte.qr.foreground_color', $this->tteForegroundColor);
        ConfigurationHelper::set('tte.qr.background_color', $this->tteBackgroundColor);
        ConfigurationHelper::set('tte.qr.round_block_mode', $this->tteRoundBlockMode);
        ConfigurationHelper::set('tte.qr.logo_enabled', $this->tteLogoEnabled ? '1' : '0');
        ConfigurationHelper::set('tte.qr.logo_size', (string) $this->tteLogoSize);

        if ($this->tteLogoFile) {
            if ($this->tteCurrentLogoPath && Storage::disk('public')->exists($this->tteCurrentLogoPath)) {
                Storage::disk('public')->delete($this->tteCurrentLogoPath);
            }
            $path = $this->tteLogoFile->store('tte', 'public');
            ConfigurationHelper::set('tte.qr.logo_path', $path);
            $this->tteCurrentLogoPath = $path;
            $this->tteLogoFile = null;
        }

        $this->generatePreview();
        $this->toastSuccess('Konfigurasi TTE berhasil disimpan');
    }

    /** Hapus logo TTE */
    public function removeTteLogo(): void
    {
        if ($this->tteCurrentLogoPath && Storage::disk('public')->exists($this->tteCurrentLogoPath)) {
            Storage::disk('public')->delete($this->tteCurrentLogoPath);
        }
        ConfigurationHelper::set('tte.qr.logo_path', null);
        ConfigurationHelper::set('tte.qr.logo_enabled', '0');
        $this->tteCurrentLogoPath = null;
        $this->tteLogoEnabled = false;
        $this->generatePreview();
        $this->toastSuccess('Logo TTE berhasil dihapus');
    }

    public function with(): array
    {
        $widthPt = round($this->widthMm * 2.8346, 2);
        $heightPt = round($this->heightMm * 2.8346, 2);

        return [
            'widthPt' => $widthPt,
            'heightPt' => $heightPt,
        ];
    }
};
?>

<div>
    {{-- Header --}}
    
    {{-- Kredensial --}}
    <x-ui.section-card title="Kredensial" class="mb-6">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <flux:field>
                <flux:label>Base URL</flux:label>
                <flux:input wire:model="tteBaseUrl" type="url" placeholder="https://tte.example.com" />
                @error('tteBaseUrl')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>
            <flux:field>
                <flux:label>Username</flux:label>
                <flux:input wire:model="tteUsername" placeholder="Username TTE" />
                @error('tteUsername')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>
            <flux:field>
                <flux:label>Password</flux:label>
                <flux:input type="password" wire:model="ttePassword" placeholder="Password TTE" />
                @error('ttePassword')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>
        </div>
        <hr class="my-5 border-zinc-200 dark:border-primary-dark-700">
        <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-3">Akun default untuk simulasi TTE. Disimpan
            terenkripsi
            dan diisi otomatis di halaman Simulasi.</p>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <flux:field>
                <flux:label>NIK <span class="text-xs text-zinc-400 font-normal">(default akun)</span></flux:label>
                <flux:input wire:model="tteNik" placeholder="1234567890123456" class="font-mono" maxlength="16" />
                @error('tteNik')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>
            <flux:field>
                <flux:label>Passphrase <span class="text-xs text-zinc-400 font-normal">(default akun,
                        terenkripsi)</span></flux:label>
                <flux:input type="password" wire:model="ttePassphrase" placeholder="Passphrase akun TTE" />
                @error('ttePassphrase')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>
        </div>
        <x-slot:footer>
            <div class="flex items-center gap-3">
                <x-atoms.button wire:click="saveCredentials" variant="primary" icon="check">Simpan</x-atoms.button>
                <span wire:loading wire:target="saveCredentials"
                    class="text-sm text-zinc-500 dark:text-primary-dark-400">Menyimpan...</span>
                <livewire:components.connection-result :url="rtrim($tteBaseUrl, '/') . '/api/v2/user/check/status'" method="POST" :headers="['Authorization' => 'Basic ' . base64_encode($tteUsername . ':' . $ttePassword)]"
                    :body="['nik' => '0000000000000000']" name="connection-tte" title="Tes Koneksi — TTE" label="Tes Koneksi"
                    variant="ghost" icon="signal" />
            </div>
        </x-slot:footer>
    </x-ui.section-card>

    {{-- Informasi Konfigurasi Env --}}
    <x-ui.section-card title="Informasi Konfigurasi" class="mb-6">
        <div class="space-y-3">
            <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                <span class="text-sm text-zinc-500 dark:text-primary-dark-400">TTE_BASE_URL</span>
                <span
                    class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ config('services.tte.base_url') ?: '-' }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                <span class="text-sm text-zinc-500 dark:text-primary-dark-400">TTE_USERNAME</span>
                <span
                    class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ config('services.tte.username') ?: '-' }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                <span class="text-sm text-zinc-500 dark:text-primary-dark-400">TTE_PASSWORD</span>
                <span
                    class="text-sm font-mono text-zinc-900 dark:text-primary-dark-100">{{ config('services.tte.password') ? '********' : '-' }}</span>
            </div>
        </div>
    </x-ui.section-card>

    {{-- Konfigurasi QR TTE --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Form Pengaturan --}}
        <div class="lg:col-span-2">
            <x-ui.section-card title="Pengaturan QR TTE">
                <form wire:submit="saveSettings" class="space-y-5">
                    {{-- Template Konten QR --}}
                    <flux:field>
                        <flux:label>Template Konten QR</flux:label>
                        <flux:textarea wire:model="contentTemplate" rows="3"
                            placeholder="{app_name} - Ditandatangani secara elektronik pada {signed_at}" />
                        @error('contentTemplate')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                        <p class="mt-1.5 text-xs text-zinc-400">
                            Placeholder tersedia:
                            <code
                                class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300">{app_name}</code>
                            <code
                                class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300">{signer_name}</code>
                            <code
                                class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300">{signed_at}</code>
                            <code
                                class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300">{document_number}</code>
                        </p>
                    </flux:field>

                    {{-- Ukuran Default QR --}}
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Lebar Default (mm)</flux:label>
                            <flux:input type="number" wire:model.live.debounce.300ms="widthMm" step="0.1"
                                min="5" max="100" />
                            @error('widthMm')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                            <p class="mt-1 text-xs text-zinc-400">= {{ $widthPt }} pt</p>
                        </flux:field>

                        <flux:field>
                            <flux:label>Tinggi Default (mm)</flux:label>
                            <flux:input type="number" wire:model.live.debounce.300ms="heightMm" step="0.1"
                                min="5" max="100" />
                            @error('heightMm')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                            <p class="mt-1 text-xs text-zinc-400">= {{ $heightPt }} pt</p>
                        </flux:field>
                    </div>

                    {{-- Tampilan QR --}}
                    <div class="pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                        <h3 class="mb-4 text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Tampilan QR</h3>

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            {{-- Level Koreksi Error --}}
                            <flux:field>
                                <flux:label>Level Koreksi Error</flux:label>
                                <flux:select wire:model.live="tteErrorCorrection">
                                    <flux:select.option value="L">L — Low (7%)</flux:select.option>
                                    <flux:select.option value="M">M — Medium (15%)</flux:select.option>
                                    <flux:select.option value="Q">Q — Quartile (25%)</flux:select.option>
                                    <flux:select.option value="H">H — High (30%)</flux:select.option>
                                </flux:select>
                                @error('tteErrorCorrection')
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror
                                <p class="mt-1 text-xs text-zinc-400">Gunakan H jika memakai logo di tengah</p>
                            </flux:field>

                            {{-- Bentuk Blok --}}
                            <flux:field>
                                <flux:label>Bentuk Blok</flux:label>
                                <flux:select wire:model.live="tteRoundBlockMode">
                                    <flux:select.option value="margin">Bulat (dengan margin)</flux:select.option>
                                    <flux:select.option value="shrink">Bulat (tanpa margin)</flux:select.option>
                                    <flux:select.option value="none">Kotak</flux:select.option>
                                </flux:select>
                                @error('tteRoundBlockMode')
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </flux:field>

                            {{-- Warna QR --}}
                            <flux:field>
                                <flux:label>Warna QR (Foreground)</flux:label>
                                <div class="flex items-center gap-3">
                                    <input type="color" wire:model.live="tteForegroundColor"
                                        class="w-10 h-10 rounded border border-zinc-300 dark:border-primary-dark-600 cursor-pointer shrink-0" />
                                    <flux:input wire:model.live="tteForegroundColor" placeholder="#000000"
                                        class="flex-1 font-mono" />
                                </div>
                                @error('tteForegroundColor')
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </flux:field>

                            {{-- Warna Background --}}
                            <flux:field>
                                <flux:label>Warna Background</flux:label>
                                <div class="flex items-center gap-3">
                                    <input type="color" wire:model.live="tteBackgroundColor"
                                        class="w-10 h-10 rounded border border-zinc-300 dark:border-primary-dark-600 cursor-pointer shrink-0" />
                                    <flux:input wire:model.live="tteBackgroundColor" placeholder="#FFFFFF"
                                        class="flex-1 font-mono" />
                                </div>
                                @error('tteBackgroundColor')
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </flux:field>
                        </div>
                    </div>

                    {{-- Logo di Tengah QR --}}
                    <div class="pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                        <div class="flex items-center gap-3 mb-4">
                            <flux:switch wire:model.live="tteLogoEnabled" />
                            <div>
                                <flux:label>Logo di Tengah QR</flux:label>
                                <p class="text-xs text-zinc-400 mt-0.5">Tampilkan logo institusi di tengah QR TTE</p>
                            </div>
                        </div>

                        @if ($tteLogoEnabled)
                            <div class="space-y-4 pl-1">
                                {{-- Logo aktif saat ini --}}
                                @if ($tteCurrentLogoPath)
                                    <div
                                        class="flex items-center gap-3 p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700">
                                        <img src="{{ Storage::url($tteCurrentLogoPath) }}" alt="Logo TTE"
                                            class="w-12 h-12 object-contain rounded border border-zinc-200 dark:border-primary-dark-600 bg-white" />
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">
                                                Logo aktif
                                            </p>
                                            <p class="text-xs text-zinc-400 truncate">
                                                {{ basename($tteCurrentLogoPath) }}</p>
                                        </div>
                                        <x-atoms.button variant="ghost" size="sm" icon="trash"
                                            class="text-red-500 hover:text-red-600 shrink-0"
                                            wire:click="removeTteLogo"
                                            wire:confirm="Yakin ingin menghapus logo TTE?" />
                                    </div>
                                @endif

                                {{-- Upload logo baru --}}
                                <flux:field>
                                    <flux:label>{{ $tteCurrentLogoPath ? 'Ganti Logo' : 'Upload Logo' }}</flux:label>
                                    <input type="file" wire:model="tteLogoFile" accept="image/*"
                                        class="block w-full text-sm text-zinc-500 dark:text-primary-dark-400
                                            file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                                            file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700
                                            dark:file:bg-primary-900/30 dark:file:text-primary-400
                                            hover:file:bg-primary-100 dark:hover:file:bg-primary-900/50
                                            file:cursor-pointer" />
                                    <p class="mt-1 text-xs text-zinc-400">PNG/JPG/SVG, maks 2MB. Gunakan PNG transparan
                                        untuk hasil terbaik.</p>
                                    @error('tteLogoFile')
                                        <flux:error>{{ $message }}</flux:error>
                                    @enderror
                                </flux:field>

                                {{-- Ukuran logo --}}
                                <flux:field>
                                    <flux:label>Ukuran Logo (px)</flux:label>
                                    <flux:input type="number" wire:model="tteLogoSize" min="20"
                                        max="200" class="w-40" />
                                    @error('tteLogoSize')
                                        <flux:error>{{ $message }}</flux:error>
                                    @enderror
                                    <p class="mt-1 text-xs text-zinc-400">Lebar logo dalam pixel. Disarankan 50–80px
                                        agar tidak menutupi QR.</p>
                                </flux:field>

                                @if (!$tteCurrentLogoPath)
                                    <p class="text-xs text-amber-600 dark:text-amber-400">
                                        Logo belum diupload. Upload logo lalu simpan konfigurasi.
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div
                        class="p-3 text-sm rounded-lg bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-300">
                        <strong>Info:</strong> Ukuran (mm) dikonversi ke point saat digunakan di PDF (1mm = 2.8346pt).
                        Pengaturan di sini bersifat independen dari konfigurasi QR global.
                    </div>

                    <div class="flex gap-2 justify-end pt-2">
                        <x-atoms.button variant="subtle" icon="eye" wire:click.prevent="generatePreview">
                            Refresh Preview
                        </x-atoms.button>
                        <x-atoms.button type="submit" variant="primary" icon="check">
                            Simpan Konfigurasi
                        </x-atoms.button>
                    </div>
                </form>
            </x-ui.section-card>
        </div>

        {{-- Preview QR --}}
        <div class="lg:col-span-1">
            <x-ui.section-card title="Preview QR" class="sticky top-20">
                <div class="flex flex-col items-center gap-3">
                    @if ($qrPreview)
                        <img src="{{ $qrPreview }}" alt="Preview QR TTE"
                            class="max-w-full rounded border border-zinc-200 dark:border-primary-dark-700" />
                        <p class="text-xs text-center text-zinc-400">Preview dengan data sample</p>
                    @else
                        <div
                            class="flex items-center justify-center w-full h-48 rounded bg-zinc-100 dark:bg-primary-dark-700">
                            <p class="text-sm text-zinc-400">Preview tidak tersedia</p>
                        </div>
                    @endif
                </div>

                {{-- Ringkasan setting --}}
                <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-primary-dark-700 space-y-3">
                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Ringkasan Pengaturan
                    </h3>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-zinc-500 dark:text-primary-dark-400">Ukuran</span>
                            <span class="font-medium text-zinc-900 dark:text-primary-dark-100">{{ $widthMm }} ×
                                {{ $heightMm }} mm</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-zinc-500 dark:text-primary-dark-400">Koreksi Error</span>
                            <span
                                class="font-medium font-mono text-zinc-900 dark:text-primary-dark-100">{{ $tteErrorCorrection }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-zinc-500 dark:text-primary-dark-400">Bentuk</span>
                            <span class="font-medium text-zinc-900 dark:text-primary-dark-100">
                                {{ match ($tteRoundBlockMode) {'shrink' => 'Bulat (tanpa margin)','none' => 'Kotak',default => 'Bulat (margin)'} }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-zinc-500 dark:text-primary-dark-400">Warna</span>
                            <div class="flex items-center gap-1.5">
                                <span
                                    class="w-4 h-4 rounded-sm border border-zinc-300 dark:border-primary-dark-600 shrink-0"
                                    style="background:{{ $tteForegroundColor }}"></span>
                                <span class="text-xs text-zinc-400">→</span>
                                <span
                                    class="w-4 h-4 rounded-sm border border-zinc-300 dark:border-primary-dark-600 shrink-0"
                                    style="background:{{ $tteBackgroundColor }}"></span>
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-zinc-500 dark:text-primary-dark-400">Logo</span>
                            <span class="font-medium text-zinc-900 dark:text-primary-dark-100">
                                {{ $tteLogoEnabled && $tteCurrentLogoPath ? 'Aktif (' . $tteLogoSize . 'px)' : 'Nonaktif' }}
                            </span>
                        </div>
                    </div>
                </div>
            </x-ui.section-card>
        </div>
    </div>
</div>
