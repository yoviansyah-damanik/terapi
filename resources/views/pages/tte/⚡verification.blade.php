<?php

use App\Services\TteService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app', ['title' => 'Verifikasi TTE'])] class extends Component {
    use WithFileUploads;

    public $pdfFile = null;
    public string $password = '';
    public bool $isVerifying = false;
    public ?array $result = null;
    public ?string $errorMessage = null;

    /**
     * Verifikasi tanda tangan elektronik pada dokumen PDF
     */
    public function verify(): void
    {
        $this->validate(
            [
                'pdfFile' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            ],
            [
                'pdfFile.required' => 'File PDF wajib diunggah.',
                'pdfFile.mimes' => 'File harus berformat PDF.',
                'pdfFile.max' => 'Ukuran file maksimal 10 MB.',
            ],
        );

        $this->isVerifying = true;
        $this->result = null;
        $this->errorMessage = null;

        try {
            $base64 = base64_encode(file_get_contents($this->pdfFile->getRealPath()));
            $password = $this->password !== '' ? $this->password : null;

            $service = app(TteService::class);
            $response = $service->verifyPdf($base64, $password);

            if ($response['success']) {
                $this->result = $response['data'];
            } else {
                $this->errorMessage = $response['message'] ?? ($response['data']['error'] ?? 'Gagal memverifikasi dokumen.');
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Terjadi kesalahan: ' . $e->getMessage();
        } finally {
            $this->isVerifying = false;
        }
    }

    /**
     * Reset form ke keadaan awal
     */
    public function resetForm(): void
    {
        $this->reset(['pdfFile', 'password', 'result', 'errorMessage']);
    }
}; ?>

<div>
    <x-ui.page-header title="Verifikasi TTE" subtitle="Upload PDF dan lakukan verifikasi keabsahan TTE" />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Form Upload --}}
        <x-organisms.card title="Upload Dokumen">
            <form wire:submit="verify" class="space-y-4">
                <div>
                    <flux:label>File PDF <span class="text-red-500">*</span></flux:label>
                    <input type="file" wire:model="pdfFile" accept=".pdf"
                        class="mt-1 block w-full text-sm text-zinc-700 dark:text-primary-dark-300
                            file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                            file:text-sm file:font-medium
                            file:bg-zinc-100 file:text-zinc-700
                            dark:file:bg-primary-dark-700 dark:file:text-primary-dark-300
                            hover:file:bg-zinc-200 dark:hover:file:bg-primary-dark-600
                            cursor-pointer" />
                    <p class="mt-1 text-xs text-zinc-500">Maks. 10 MB, format PDF</p>
                    @error('pdfFile')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <flux:input wire:model="password" label="Password (opsional)" type="password"
                    placeholder="Kosongkan jika PDF tidak berpassword" />

                <div class="flex gap-2">
                    <x-atoms.button type="submit" variant="primary" icon="shield-check" wire:loading.attr="disabled"
                        wire:target="verify, pdfFile">
                        <span wire:loading.remove wire:target="verify">Verifikasi</span>
                        <span wire:loading wire:target="verify">Memverifikasi...</span>
                    </x-atoms.button>

                    <x-atoms.button type="button" wire:click="resetForm" variant="ghost" icon="arrow-path">
                        Reset
                    </x-atoms.button>
                </div>
            </form>
        </x-organisms.card>

        {{-- Hasil Verifikasi --}}
        <x-organisms.card title="Hasil Verifikasi">
            {{-- Loading --}}
            <div wire:loading wire:target="verify" class="flex items-center gap-2 text-zinc-500">
                <flux:icon.arrow-path class="size-5 animate-spin" />
                <span>Sedang memverifikasi dokumen...</span>
            </div>

            {{-- Error --}}
            @if ($errorMessage)
                <div class="rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4">
                    <div class="flex items-center gap-2">
                        <flux:icon.x-circle class="size-5 text-red-500 shrink-0" />
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $errorMessage }}</p>
                    </div>
                </div>
            @endif

            {{-- Result --}}
            @if ($result)
                <div class="space-y-4" wire:loading.remove wire:target="verify">
                    @php
                        $conclusion = $result['conclusion'] ?? ($result['verificationType'] ?? 'UNKNOWN');
                        $conclusionColors = [
                            'TOTAL_PASSED' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                            'TOTAL_FAILED' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                            'INDETERMINATE' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                        ];
                        $conclusionColor = $conclusionColors[$conclusion] ?? 'bg-zinc-100 text-zinc-800 dark:bg-primary-dark-700 dark:text-primary-dark-300';
                        $conclusionLabels = [
                            'TOTAL_PASSED' => 'Semua Tanda Tangan Valid',
                            'TOTAL_FAILED' => 'Tanda Tangan Tidak Valid',
                            'INDETERMINATE' => 'Tidak Dapat Ditentukan',
                        ];
                        $conclusionLabel = $conclusionLabels[$conclusion] ?? $conclusion;
                        $signatures = $result['signatures'] ?? ($result['signatureInformations'] ?? []);
                    @endphp

                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium {{ $conclusionColor }}">
                            @if ($conclusion === 'TOTAL_PASSED')
                                <flux:icon.check-circle class="size-4 mr-1.5" />
                            @elseif ($conclusion === 'TOTAL_FAILED')
                                <flux:icon.x-circle class="size-4 mr-1.5" />
                            @else
                                <flux:icon.exclamation-triangle class="size-4 mr-1.5" />
                            @endif
                            {{ $conclusionLabel }}
                        </span>
                    </div>

                    <p class="text-sm text-zinc-600 dark:text-primary-dark-400">
                        Ditemukan <strong>{{ count($signatures) }}</strong> tanda tangan pada dokumen.
                    </p>

                    {{-- Detail per tanda tangan --}}
                    @foreach ($signatures as $index => $sig)
                        @php
                            $signerName = $sig['signerName'] ?? ($sig['signer_name'] ?? ($sig['name'] ?? '-'));
                            $signDate = $sig['signDate'] ?? ($sig['sign_date'] ?? ($sig['signingTime'] ?? ($sig['date'] ?? '-')));
                            $location = $sig['location'] ?? '-';
                            $reason = $sig['reason'] ?? '-';
                            $integrityValid = $sig['integrityValid'] ?? ($sig['integrity'] ?? ($sig['signatureValid'] ?? null));
                            $certTrusted = $sig['certTrusted'] ?? ($sig['certificateTrusted'] ?? ($sig['trusted'] ?? null));
                        @endphp
                        <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
                            <div class="bg-zinc-50 dark:bg-primary-dark-900/40 px-4 py-2.5 border-b border-zinc-200 dark:border-primary-dark-700">
                                <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">
                                    Tanda Tangan #{{ $index + 1 }}
                                </p>
                            </div>
                            <div class="px-4 py-3 space-y-2">
                                <div class="grid grid-cols-[120px_1fr] gap-y-1.5 text-sm">
                                    <span class="text-zinc-500 dark:text-primary-dark-400">Nama</span>
                                    <span class="text-zinc-800 dark:text-primary-dark-200 font-medium">{{ $signerName }}</span>

                                    <span class="text-zinc-500 dark:text-primary-dark-400">Tanggal</span>
                                    <span class="text-zinc-800 dark:text-primary-dark-200">{{ $signDate }}</span>

                                    <span class="text-zinc-500 dark:text-primary-dark-400">Lokasi</span>
                                    <span class="text-zinc-800 dark:text-primary-dark-200">{{ $location }}</span>

                                    <span class="text-zinc-500 dark:text-primary-dark-400">Alasan</span>
                                    <span class="text-zinc-800 dark:text-primary-dark-200">{{ $reason }}</span>

                                    <span class="text-zinc-500 dark:text-primary-dark-400">Integritas</span>
                                    <span>
                                        @if ($integrityValid === true)
                                            <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                                                <flux:icon.check-circle class="size-4" /> Valid
                                            </span>
                                        @elseif ($integrityValid === false)
                                            <span class="inline-flex items-center gap-1 text-red-600 dark:text-red-400">
                                                <flux:icon.x-circle class="size-4" /> Tidak Valid
                                            </span>
                                        @else
                                            <span class="text-zinc-400">-</span>
                                        @endif
                                    </span>

                                    <span class="text-zinc-500 dark:text-primary-dark-400">Sertifikat</span>
                                    <span>
                                        @if ($certTrusted === true)
                                            <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                                                <flux:icon.check-circle class="size-4" /> Terpercaya
                                            </span>
                                        @elseif ($certTrusted === false)
                                            <span class="inline-flex items-center gap-1 text-red-600 dark:text-red-400">
                                                <flux:icon.x-circle class="size-4" /> Tidak Terpercaya
                                            </span>
                                        @else
                                            <span class="text-zinc-400">-</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    {{-- Raw response (collapsible) --}}
                    <details class="mt-2">
                        <summary class="text-xs text-zinc-500 dark:text-primary-dark-400 cursor-pointer hover:text-zinc-700 dark:hover:text-primary-dark-300 mb-2">
                            Lihat raw response
                        </summary>
                        <x-atoms.code-block language="json" maxHeight="max-h-64">{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </details>
                </div>
            @elseif (!$errorMessage)
                <div wire:loading.remove wire:target="verify">
                    <p class="text-sm text-zinc-400 dark:text-primary-dark-500">
                        Upload file PDF yang sudah ditandatangani secara elektronik, lalu klik tombol Verifikasi.
                    </p>
                </div>
            @endif
        </x-organisms.card>
    </div>
</div>
