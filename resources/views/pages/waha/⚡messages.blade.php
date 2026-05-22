<?php

use App\Helpers\ConfigurationHelper;
use App\Jobs\SendWahaMessageJob;
use App\Models\WaGateway\Waha\WahaMessage;
use App\Services\WahaService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Pesan WhatsApp')] class extends Component {
    use WithPagination, WithFileUploads;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    // Form kirim pesan
    public string $phone = '';
    public string $message = '';
    public string $type = 'text';
    public $file;

    // Detail modal
    public bool $showDetailModal = false;
    public ?WahaMessage $detailMessage = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->file = null;
        $this->resetValidation('file');
    }

    /**
     * Kirim pesan via queue
     */
    public function sendMessage(): void
    {
        $this->validateMessage();

        try {
            $waMessage = $this->createMessage();
            $delay = (int) ConfigurationHelper::get('whatsapp.delay', '3');

            SendWahaMessageJob::dispatch($waMessage)->delay(now()->addSeconds($delay));

            $this->toastSuccess('Pesan dijadwalkan untuk dikirim');
            $this->resetForm();
        } catch (\Exception $e) {
            $this->toastError('Gagal: ' . $e->getMessage());
        }
    }

    /**
     * Kirim pesan langsung tanpa queue
     */
    public function sendDirect(): void
    {
        $this->validateMessage();

        try {
            $waMessage = $this->createMessage();
            $service = app(WahaService::class);

            $result = match ($waMessage->type) {
                'image' => $service->sendImage($waMessage->phone, storage_path('app/public/' . $waMessage->file_path), $waMessage->message),
                'file' => $service->sendFile($waMessage->phone, storage_path('app/public/' . $waMessage->file_path), $waMessage->file_name),
                default => $service->sendText($waMessage->phone, $waMessage->message),
            };

            if ($result['success']) {
                $waMessage->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
                $this->toastSuccess('Pesan berhasil dikirim');
            } else {
                $errorMessage = $result['message'] ?? ($result['error'] ?? 'Gagal mengirim pesan');
                $waMessage->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                ]);
                $this->toastError('Gagal mengirim: ' . $errorMessage);
            }

            $this->resetForm();
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Kirim ulang pesan yang gagal
     */
    public function resendMessage(string $id): void
    {
        try {
            $waMessage = WahaMessage::findOrFail($id);
            $waMessage->update([
                'status' => 'pending',
                'error_message' => null,
            ]);

            $delay = (int) ConfigurationHelper::get('whatsapp.delay', '3');
            SendWahaMessageJob::dispatch($waMessage)->delay(now()->addSeconds($delay));

            $this->toastSuccess('Pesan dijadwalkan untuk dikirim ulang');
        } catch (\Exception $e) {
            $this->toastError('Gagal: ' . $e->getMessage());
        }
    }

    /**
     * Lihat detail pesan
     */
    public function viewDetail(string $id): void
    {
        $this->detailMessage = WahaMessage::with('sentByUser')->findOrFail($id);
        $this->showDetailModal = true;
    }

    private function validateMessage(): void
    {
        $rules = [
            'phone' => 'required|string|min:10|max:15',
            'type' => 'required|in:text,image,file',
        ];

        $messages = [
            'phone.required' => 'Nomor tujuan harus diisi',
            'phone.min' => 'Nomor tujuan minimal 10 karakter',
            'message.required' => 'Pesan harus diisi',
            'file.required' => 'File harus diunggah',
            'file.max' => 'Ukuran file maksimal 10MB',
            'file.mimes' => 'Format file tidak didukung',
        ];

        if ($this->type === 'text') {
            $rules['message'] = 'required|string';
        } elseif ($this->type === 'image') {
            $rules['file'] = 'required|image|max:10240';
        } elseif ($this->type === 'file') {
            $rules['file'] = 'required|file|max:10240';
        }

        $this->validate($rules, $messages);
    }

    private function createMessage(): WahaMessage
    {
        $data = [
            'phone' => $this->phone,
            'message' => $this->message ?: null,
            'type' => $this->type,
            'status' => 'pending',
            'sent_by' => auth()->id(),
        ];

        if ($this->file && in_array($this->type, ['image', 'file'])) {
            $path = $this->file->store('whatsapp', 'public');
            $data['file_path'] = $path;
            $data['file_name'] = $this->file->getClientOriginalName();
        }

        return WahaMessage::create($data);
    }

    private function resetForm(): void
    {
        $this->phone = '';
        $this->message = '';
        $this->type = 'text';
        $this->file = null;
        $this->resetValidation();
    }

    public function with(): array
    {
        $query = WahaMessage::query()
            ->with('sentByUser')
            ->when(
                $this->search,
                fn($q) => $q->where(function ($q) {
                    $q->where('phone', 'like', "%{$this->search}%")->orWhere('message', 'like', "%{$this->search}%");
                }),
            )
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->orderByDesc('created_at');

        return [
            'messages' => $query->paginate(15),
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Pesan WhatsApp" subtitle="Kirim dan kelola pesan WhatsApp" />

    {{-- Form Kirim Pesan --}}
    <div
        class="p-6 mb-6 bg-white border rounded-lg dark:bg-primary-dark-800 border-zinc-200 dark:border-primary-dark-700">
        <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">Kirim Pesan</h2>

        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Nomor Tujuan</flux:label>
                    <flux:input wire:model="phone" placeholder="08xxxxxxxxxx atau 628xxxxxxxxxx" />
                    @error('phone')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Tipe Pesan</flux:label>
                    <flux:select wire:model.live="type">
                        <flux:select.option value="text">Teks</flux:select.option>
                        <flux:select.option value="image">Gambar</flux:select.option>
                        <flux:select.option value="file">File</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            @if ($type === 'text' || $type === 'image')
                <flux:field>
                    <flux:label>{{ $type === 'image' ? 'Caption' : 'Pesan' }}</flux:label>
                    <flux:textarea wire:model="message" rows="3"
                        placeholder="{{ $type === 'image' ? 'Caption gambar (opsional)' : 'Ketik pesan...' }}" />
                    @error('message')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>
            @endif

            @if ($type === 'image' || $type === 'file')
                <flux:field>
                    <flux:label>{{ $type === 'image' ? 'Upload Gambar' : 'Upload File' }}</flux:label>
                    <input type="file" wire:model="file"
                        class="block w-full text-sm text-zinc-500 dark:text-primary-dark-400
                            file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                            file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700
                            dark:file:bg-primary-900/30 dark:file:text-primary-300
                            hover:file:bg-primary-100 dark:hover:file:bg-primary-900/50"
                        {{ $type === 'image' ? 'accept=image/*' : '' }} />
                    @error('file')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-zinc-400">Maksimal 10MB</p>
                </flux:field>
            @endif

            <div class="flex gap-2 pt-2">
                <x-atoms.button variant="primary" icon="paper-airplane" wire:click="sendMessage"
                    wire:loading.attr="disabled" wire:target="sendMessage,sendDirect">
                    <span wire:loading.remove wire:target="sendMessage">Kirim (Queue)</span>
                    <span wire:loading wire:target="sendMessage">Mengirim...</span>
                </x-atoms.button>
                <x-atoms.button variant="filled" icon="bolt" wire:click="sendDirect" wire:loading.attr="disabled"
                    wire:target="sendMessage,sendDirect">
                    <span wire:loading.remove wire:target="sendDirect">Kirim Langsung</span>
                    <span wire:loading wire:target="sendDirect">Mengirim...</span>
                </x-atoms.button>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="p-4 mb-6 bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nomor atau pesan..."
                    icon="magnifying-glass" />
            </div>
            <flux:select wire:model.live="filterStatus">
                <flux:select.option value="">Semua Status</flux:select.option>
                <flux:select.option value="pending">Pending</flux:select.option>
                <flux:select.option value="sent">Terkirim</flux:select.option>
                <flux:select.option value="failed">Gagal</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Tabel Riwayat --}}
    <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Nomor Tujuan
                        </th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Tipe
                        </th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Pesan
                        </th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Status
                        </th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Pengirim
                        </th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Waktu
                        </th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-right uppercase text-zinc-500 dark:text-primary-dark-400">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                    @forelse ($messages as $msg)
                        <tr :key="$msg->id" class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                    {{ $msg->phone }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge
                                    :color="match($msg->type) { 'text' => 'zinc', 'image' => 'blue', 'file' => 'purple', default => 'zinc' }"
                                    size="sm">
                                    {{ ucfirst($msg->type) }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-zinc-700 dark:text-primary-dark-300">
                                    {{ Str::limit($msg->message, 50) ?: $msg->file_name ?? '-' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge
                                    :color="match($msg->status) { 'sent' => 'green', 'failed' => 'red', default => 'yellow' }"
                                    size="sm">
                                    {{ match ($msg->status) {'sent' => 'Terkirim','failed' => 'Gagal',default => 'Pending'} }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap text-zinc-500 dark:text-primary-dark-400">
                                {{ $msg->sentByUser?->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap text-zinc-500 dark:text-primary-dark-400">
                                {{ $msg->sent_at?->format('d/m/Y H:i') ?? $msg->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <x-atoms.button variant="ghost" size="sm" icon="eye"
                                        wire:click="viewDetail('{{ $msg->id }}')" title="Detail" />
                                    @if ($msg->status === 'failed')
                                        <x-atoms.button variant="ghost" size="sm" icon="arrow-path"
                                            wire:click="resendMessage('{{ $msg->id }}')" title="Kirim Ulang" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="chat-bubble-left-right"
                                        class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                                        Belum ada pesan
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($messages->hasPages())
            <div class="px-6 py-4 border-t border-zinc-200 dark:border-primary-dark-700">
                {{ $messages->links() }}
            </div>
        @endif
    </div>

    {{-- Detail Modal --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="lg" title="">
        @if ($detailMessage)
            <div class="space-y-6">
                <flux:heading size="lg">Detail Pesan</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Nomor Tujuan</span>
                        <span
                            class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ $detailMessage->phone }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Tipe</span>
                        <flux:badge
                            :color="match($detailMessage->type) { 'text' => 'zinc', 'image' => 'blue', 'file' => 'purple', default => 'zinc' }"
                            size="sm">
                            {{ ucfirst($detailMessage->type) }}
                        </flux:badge>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Status</span>
                        <flux:badge
                            :color="match($detailMessage->status) { 'sent' => 'green', 'failed' => 'red', default => 'yellow' }"
                            size="sm">
                            {{ match ($detailMessage->status) {'sent' => 'Terkirim','failed' => 'Gagal',default => 'Pending'} }}
                        </flux:badge>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Pengirim</span>
                        <span
                            class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ $detailMessage->sentByUser?->name ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Dibuat</span>
                        <span
                            class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ $detailMessage->created_at->format('d/m/Y H:i:s') }}</span>
                    </div>
                    @if ($detailMessage->sent_at)
                        <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                            <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Terkirim</span>
                            <span
                                class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ $detailMessage->sent_at->format('d/m/Y H:i:s') }}</span>
                        </div>
                    @endif

                    @if ($detailMessage->message)
                        <div class="py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                            <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Pesan</span>
                            <p class="mt-1 text-sm text-zinc-900 dark:text-primary-dark-100 whitespace-pre-wrap">
                                {{ $detailMessage->message }}</p>
                        </div>
                    @endif

                    @if ($detailMessage->file_name)
                        <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                            <span class="text-sm text-zinc-500 dark:text-primary-dark-400">File</span>
                            <span
                                class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ $detailMessage->file_name }}</span>
                        </div>
                    @endif

                    @if ($detailMessage->error_message)
                        <div class="py-2">
                            <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Error</span>
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $detailMessage->error_message }}
                            </p>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-3">
                    @if ($detailMessage->status === 'failed')
                        <x-atoms.button variant="primary" size="sm" icon="arrow-path"
                            wire:click="resendMessage('{{ $detailMessage->id }}')"
                            x-on:click="$wire.set('showDetailModal', false)">
                            Kirim Ulang
                        </x-atoms.button>
                    @endif
                    <x-atoms.button variant="ghost" wire:click="$set('showDetailModal', false)">
                        Tutup
                    </x-atoms.button>
                </div>
            </div>
        @endif
    
    </x-organisms.modal>
</div>
