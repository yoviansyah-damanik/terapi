<?php

use App\Helpers\QrCodeHelper;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Generator QR Code')] class extends Component {
    public string $body = '';
    public ?string $previewBase64 = null;

    public function generate(): void
    {
        $this->validate(['body' => 'required|string']);

        try {
            $result = QrCodeHelper::generate($this->body, 'png');
            $this->previewBase64 = base64_encode($result->getString());
        } catch (\Throwable $e) {
            $this->addError('body', 'Gagal generate QR Code: ' . $e->getMessage());
        }
    }

    public function resetPreview(): void
    {
        $this->previewBase64 = null;
    }
};
?>

<div>
    <x-ui.page-header title="Generator QR Code" subtitle="Buat QR Code dari teks, URL, atau data lainnya" />

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        {{-- Input Panel --}}
        <div class="lg:col-span-3 flex flex-col gap-4">
            <div
                class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200 dark:border-primary-dark-700 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700">
                    <p class="text-xs font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500">
                        Konten</p>
                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">Teks, URL, atau data apapun yang
                        ingin
                        di-encode</p>
                </div>
                <div class="p-5">
                    <flux:textarea wire:model="body" placeholder="https://example.com atau teks bebas…" rows="7"
                        class="font-mono text-sm" wire:dirty.class="ring-1 ring-primary-500" />
                    @error('body')
                        <p class="mt-2 text-xs text-red-500 flex items-center gap-1.5">
                            <flux:icon name="exclamation-circle" class="w-3.5 h-3.5 shrink-0" />
                            {{ $message }}
                        </p>
                    @enderror
                </div>
                <div class="px-5 pb-5">
                    <x-atoms.button wire:click="generate" variant="primary" icon="qr-code" class="w-full">
                        <span wire:loading.remove wire:target="generate">Generate QR Code</span>
                        <span wire:loading wire:target="generate">Memproses…</span>
                    </x-atoms.button>
                </div>
            </div>

            {{-- Hint --}}
            <div
                class="flex items-start gap-2.5 px-4 py-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-800/50 border border-zinc-200 dark:border-primary-dark-700">
                <flux:icon name="information-circle"
                    class="w-4 h-4 text-zinc-400 dark:text-primary-dark-500 shrink-0 mt-0.5" />
                <p class="text-xs text-zinc-500 dark:text-primary-dark-400 leading-relaxed">
                    Tampilan QR Code (warna, ukuran, logo, dan koreksi error) mengikuti
                    <a href="{{ route('configuration.qrcode') }}"
                        class="font-medium text-primary-600 dark:text-primary-400 hover:underline">pengaturan QR
                        Code</a>.
                </p>
            </div>
        </div>

        {{-- Preview Panel --}}
        <div class="lg:col-span-2">
            <div
                class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200 dark:border-primary-dark-700 shadow-sm overflow-hidden h-full flex flex-col">
                <div
                    class="px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500">
                            Preview
                        </p>
                    </div>
                    @if ($previewBase64)
                        <x-atoms.button variant="ghost" size="sm" icon="x-mark" wire:click="resetPreview" />
                    @endif
                </div>

                <div class="flex-1 flex flex-col items-center justify-center p-6">
                    @if ($previewBase64)
                        <img src="data:image/png;base64,{{ $previewBase64 }}" alt="QR Code"
                            class="w-full max-w-xs rounded-xl border border-zinc-200 dark:border-primary-dark-700 shadow-sm" />
                        <div class="mt-5 w-full max-w-xs">
                            <a href="data:image/png;base64,{{ $previewBase64 }}" download="qrcode.png"
                                class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium transition-colors">
                                <flux:icon name="arrow-down-tray" class="w-4 h-4" />
                                Unduh PNG
                            </a>
                        </div>
                    @else
                        <div class="flex flex-col items-center gap-4 py-8 text-center">
                            <div
                                class="w-20 h-20 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700/50 flex items-center justify-center">
                                <flux:icon name="qr-code" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-zinc-500 dark:text-primary-dark-400">Belum ada
                                    preview</p>
                                <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-1">Isi konten lalu klik
                                    <strong>Generate</strong>
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>
