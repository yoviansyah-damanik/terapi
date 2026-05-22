<?php

use App\Jobs\ProcessWhatsappBroadcastJob;
use App\Models\WaGateway\Waha\WahaBroadcast;
use App\Models\WaGateway\Waha\WahaBroadcastRecipient;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Broadcast WhatsApp')] class extends Component {
    use WithPagination, WithFileUploads;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    // Form broadcast
    public string $name = '';
    public string $message = '';
    public string $type = 'text';
    public $file;
    public string $phones = '';

    // Detail modal
    public bool $showDetailModal = false;
    public ?string $detailBroadcastId = null;

    // Form modal
    public bool $showFormModal = false;

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
     * Buka form modal untuk buat broadcast baru
     */
    public function openForm(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    /**
     * Buat broadcast baru
     */
    public function createBroadcast(): void
    {
        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|in:text,image,file',
            'phones' => 'required|string',
        ];

        $messages = [
            'name.required' => 'Nama broadcast harus diisi',
            'phones.required' => 'Nomor penerima harus diisi',
            'message.required' => 'Pesan harus diisi',
            'file.required' => 'File harus diunggah',
            'file.max' => 'Ukuran file maksimal 10MB',
        ];

        if ($this->type === 'text') {
            $rules['message'] = 'required|string';
        } elseif ($this->type === 'image') {
            $rules['file'] = 'required|image|max:10240';
        } elseif ($this->type === 'file') {
            $rules['file'] = 'required|file|max:10240';
        }

        $this->validate($rules, $messages);

        // Parse nomor telepon (1 per baris)
        $phoneList = collect(explode("\n", $this->phones))
            ->map(fn($p) => trim($p))
            ->filter(fn($p) => strlen($p) >= 10)
            ->unique()
            ->values();

        if ($phoneList->isEmpty()) {
            $this->addError('phones', 'Tidak ada nomor valid yang ditemukan');
            return;
        }

        $data = [
            'name' => $this->name,
            'message' => $this->message ?: null,
            'type' => $this->type,
            'total_recipients' => $phoneList->count(),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ];

        if ($this->file && in_array($this->type, ['image', 'file'])) {
            $path = $this->file->store('whatsapp/broadcast', 'public');
            $data['file_path'] = $path;
            $data['file_name'] = $this->file->getClientOriginalName();
        }

        $broadcast = WahaBroadcast::create($data);

        // Buat recipients
        foreach ($phoneList as $phone) {
            WahaBroadcastRecipient::create([
                'broadcast_id' => $broadcast->id,
                'phone' => $phone,
                'status' => 'pending',
            ]);
        }

        $this->showFormModal = false;
        $this->resetForm();
        $this->toastSuccess("Broadcast \"{$broadcast->name}\" berhasil dibuat dengan {$phoneList->count()} penerima");
    }

    /**
     * Mulai proses broadcast
     */
    public function startBroadcast(string $id): void
    {
        $broadcast = WahaBroadcast::findOrFail($id);

        if ($broadcast->status !== 'draft') {
            $this->toastError('Broadcast hanya bisa dimulai dari status draft');
            return;
        }

        ProcessWhatsappBroadcastJob::dispatch($broadcast);

        $this->toastSuccess('Broadcast mulai diproses');
    }

    /**
     * Batalkan broadcast yang sedang berjalan
     */
    public function cancelBroadcast(string $id): void
    {
        $broadcast = WahaBroadcast::findOrFail($id);

        if (!in_array($broadcast->status, ['draft', 'processing'])) {
            $this->toastError('Broadcast tidak bisa dibatalkan');
            return;
        }

        $broadcast->update(['status' => 'cancelled']);
        $this->toastSuccess('Broadcast berhasil dibatalkan');
    }

    /**
     * Lihat detail broadcast
     */
    public function viewDetail(string $id): void
    {
        $this->detailBroadcastId = $id;
        $this->showDetailModal = true;
    }

    /**
     * Hapus broadcast (hanya draft)
     */
    public function deleteBroadcast(string $id): void
    {
        $broadcast = WahaBroadcast::findOrFail($id);

        if ($broadcast->status !== 'draft') {
            $this->toastError('Hanya broadcast berstatus draft yang bisa dihapus');
            return;
        }

        $broadcast->delete();
        $this->toastSuccess('Broadcast berhasil dihapus');
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->message = '';
        $this->type = 'text';
        $this->file = null;
        $this->phones = '';
        $this->resetValidation();
    }

    public function with(): array
    {
        $query = WahaBroadcast::query()
            ->with('creator')
            ->withCount(['recipients as pending_count' => fn($q) => $q->where('status', 'pending')])
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->orderByDesc('created_at');

        $detailBroadcast = null;
        $detailRecipients = null;
        if ($this->detailBroadcastId) {
            $detailBroadcast = WahaBroadcast::with('creator')->find($this->detailBroadcastId);
            $detailRecipients = $detailBroadcast?->recipients()->orderBy('status')->paginate(20, pageName: 'recipientPage');
        }

        return [
            'broadcasts' => $query->paginate(15),
            'detailBroadcast' => $detailBroadcast,
            'detailRecipients' => $detailRecipients,
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Broadcast WhatsApp" subtitle="Kirim pesan massal ke banyak nomor">
        <x-slot name="actions">
            <x-atoms.button variant="primary" icon="plus" wire:click="openForm">
                Buat Broadcast
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    {{-- Filter --}}
    <div class="p-4 mb-6 bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama broadcast..."
                    icon="magnifying-glass" />
            </div>
            <flux:select wire:model.live="filterStatus">
                <flux:select.option value="">Semua Status</flux:select.option>
                <flux:select.option value="draft">Draft</flux:select.option>
                <flux:select.option value="processing">Diproses</flux:select.option>
                <flux:select.option value="completed">Selesai</flux:select.option>
                <flux:select.option value="cancelled">Dibatalkan</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Tabel Broadcast --}}
    <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Nama</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Tipe</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Penerima</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Progress</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Status</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Pembuat</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Waktu</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-right uppercase text-zinc-500 dark:text-primary-dark-400">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                    @forelse ($broadcasts as $bc)
                        <tr :key="$bc->id" class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-6 py-4">
                                <span
                                    class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ $bc->name }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge
                                    :color="match($bc->type) { 'text' => 'zinc', 'image' => 'blue', 'file' => 'purple', default => 'zinc' }"
                                    size="sm">
                                    {{ ucfirst($bc->type) }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 text-sm text-zinc-700 dark:text-primary-dark-300 whitespace-nowrap">
                                {{ $bc->total_recipients }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="w-24 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600">
                                        @php
                                            $progress =
                                                $bc->total_recipients > 0
                                                    ? (($bc->sent_count + $bc->failed_count) / $bc->total_recipients) *
                                                        100
                                                    : 0;
                                        @endphp
                                        <div class="h-2 rounded-full bg-emerald-500"
                                            style="width: {{ $progress }}%"></div>
                                    </div>
                                    <span class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                        {{ $bc->sent_count }}/{{ $bc->total_recipients }}
                                        @if ($bc->failed_count > 0)
                                            <span class="text-red-500">({{ $bc->failed_count }} gagal)</span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge
                                    :color="match($bc->status) {
                                                                                                                                                                                                                        'draft' => 'zinc',
                                                                                                                                                                                                                        'processing' => 'yellow',
                                                                                                                                                                                                                        'completed' => 'green',
                                                                                                                                                                                                                        'cancelled' => 'red',
                                                                                                                                                                                                                        default => 'zinc'
                                                                                                                                                                                                                    }"
                                    size="sm">
                                    {{ match ($bc->status) {
                                        'draft' => 'Draft',
                                        'processing' => 'Diproses',
                                        'completed' => 'Selesai',
                                        'cancelled' => 'Dibatalkan',
                                        default => $bc->status,
                                    } }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap text-zinc-500 dark:text-primary-dark-400">
                                {{ $bc->creator?->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap text-zinc-500 dark:text-primary-dark-400">
                                {{ $bc->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <x-atoms.button variant="ghost" size="sm" icon="eye"
                                        wire:click="viewDetail('{{ $bc->id }}')" title="Detail" />
                                    @if ($bc->status === 'draft')
                                        <x-atoms.button variant="ghost" size="sm" icon="play"
                                            wire:click="startBroadcast('{{ $bc->id }}')"
                                            wire:confirm="Mulai broadcast ini? Pesan akan dikirim ke {{ $bc->total_recipients }} penerima."
                                            title="Mulai" />
                                        <x-atoms.button variant="ghost" size="sm" icon="trash"
                                            wire:click="deleteBroadcast('{{ $bc->id }}')"
                                            wire:confirm="Yakin hapus broadcast ini?" title="Hapus" />
                                    @endif
                                    @if ($bc->status === 'processing')
                                        <x-atoms.button variant="ghost" size="sm" icon="x-mark"
                                            wire:click="cancelBroadcast('{{ $bc->id }}')"
                                            wire:confirm="Yakin batalkan broadcast ini?" title="Batalkan" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="megaphone"
                                        class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Belum ada broadcast
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($broadcasts->hasPages())
            <div class="px-6 py-4 border-t border-zinc-200 dark:border-primary-dark-700">
                {{ $broadcasts->links() }}
            </div>
        @endif
    </div>

    {{-- Form Modal --}}
    <x-organisms.modal wire:model="showFormModal" maxWidth="2xl" title="">
        <div class="space-y-6">
            <flux:heading size="lg">Buat Broadcast Baru</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Nama Broadcast</flux:label>
                    <flux:input wire:model="name" placeholder="Contoh: Pengumuman Jadwal Dokter" />
                    @error('name')
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

                @if ($type === 'text' || $type === 'image')
                    <flux:field>
                        <flux:label>{{ $type === 'image' ? 'Caption' : 'Pesan' }}</flux:label>
                        <flux:textarea wire:model="message" rows="4"
                            placeholder="{{ $type === 'image' ? 'Caption gambar (opsional)' : 'Ketik pesan broadcast...' }}" />
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

                <flux:field>
                    <flux:label>Nomor Penerima</flux:label>
                    <flux:textarea wire:model="phones" rows="6"
                        placeholder="Masukkan nomor telepon, satu per baris:&#10;08123456789&#10;08198765432&#10;628551234567" />
                    @error('phones')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                    <p class="mt-1 text-xs text-zinc-400">Satu nomor per baris. Minimal 10 digit.</p>
                </flux:field>
            </div>

            
        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showFormModal', false)">
                    Batal
                </x-atoms.button>
                <x-atoms.button variant="primary" icon="check" wire:click="createBroadcast"
                    wire:loading.attr="disabled" wire:target="createBroadcast">
                    <span wire:loading.remove wire:target="createBroadcast">Simpan Broadcast</span>
                    <span wire:loading wire:target="createBroadcast">Menyimpan...</span>
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Detail Modal --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="3xl" title="">
        @if ($detailBroadcast)
            <div class="space-y-6">
                <flux:heading size="lg">Detail Broadcast: {{ $detailBroadcast->name }}</flux:heading>

                {{-- Info Broadcast --}}
                <div class="grid grid-cols-2 gap-4 p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900">
                    <div>
                        <span class="text-xs text-zinc-500 dark:text-primary-dark-400">Tipe</span>
                        <p class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                            {{ ucfirst($detailBroadcast->type) }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-zinc-500 dark:text-primary-dark-400">Status</span>
                        <div class="mt-0.5">
                            <flux:badge
                                :color="match($detailBroadcast->status) {
                                                                                                                                                                                                'draft' => 'zinc', 'processing' => 'yellow', 'completed' => 'green', 'cancelled' => 'red', default => 'zinc'
                                                                                                                                                                                            }"
                                size="sm">
                                {{ match ($detailBroadcast->status) {
                                    'draft' => 'Draft',
                                    'processing' => 'Diproses',
                                    'completed' => 'Selesai',
                                    'cancelled' => 'Dibatalkan',
                                    default => $detailBroadcast->status,
                                } }}
                            </flux:badge>
                        </div>
                    </div>
                    <div>
                        <span class="text-xs text-zinc-500 dark:text-primary-dark-400">Progress</span>
                        <p class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                            {{ $detailBroadcast->sent_count }} terkirim / {{ $detailBroadcast->total_recipients }}
                            total
                            @if ($detailBroadcast->failed_count > 0)
                                <span class="text-red-500">({{ $detailBroadcast->failed_count }} gagal)</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <span class="text-xs text-zinc-500 dark:text-primary-dark-400">Pembuat</span>
                        <p class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                            {{ $detailBroadcast->creator?->name ?? '-' }}</p>
                    </div>
                    @if ($detailBroadcast->message)
                        <div class="col-span-2">
                            <span class="text-xs text-zinc-500 dark:text-primary-dark-400">Pesan</span>
                            <p class="text-sm text-zinc-900 dark:text-primary-dark-100 whitespace-pre-wrap mt-1">
                                {{ $detailBroadcast->message }}</p>
                        </div>
                    @endif
                </div>

                {{-- Tabel Penerima --}}
                <div>
                    <h3 class="mb-3 text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Daftar Penerima
                    </h3>
                    <div class="overflow-x-auto border rounded-lg border-zinc-200 dark:border-primary-dark-700">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                                <tr>
                                    <th
                                        class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Nomor</th>
                                    <th
                                        class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Status</th>
                                    <th
                                        class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Waktu Kirim</th>
                                    <th
                                        class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Error</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-primary-dark-700">
                                @forelse ($detailRecipients as $r)
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-zinc-900 dark:text-primary-dark-100">
                                            {{ $r->phone }}</td>
                                        <td class="px-4 py-2">
                                            <flux:badge
                                                :color="match($r->status) { 'sent' => 'green', 'failed' => 'red', default => 'yellow' }"
                                                size="sm">
                                                {{ match ($r->status) {'sent' => 'Terkirim','failed' => 'Gagal',default => 'Pending'} }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-4 py-2 text-xs text-zinc-500 dark:text-primary-dark-400">
                                            {{ $r->sent_at?->format('d/m/Y H:i:s') ?? '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-xs text-red-500">
                                            {{ $r->error_message ?? '-' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-4 text-center text-sm text-zinc-400">Tidak
                                            ada penerima</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($detailRecipients?->hasPages())
                        <div class="mt-3">
                            {{ $detailRecipients->links() }}
                        </div>
                    @endif
                </div>

                <div class="flex justify-end">
                    <x-atoms.button variant="ghost" wire:click="$set('showDetailModal', false)">
                        Tutup
                    </x-atoms.button>
                </div>
            </div>
        @endif
    
    </x-organisms.modal>
</div>
