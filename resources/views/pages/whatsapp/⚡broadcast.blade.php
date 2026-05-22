<?php

use App\Helpers\ConfigurationHelper;
use App\Jobs\ProcessWhatsappBroadcastJob;
use App\Jobs\ProcessGowaBroadcastJob;
use App\Models\WaGateway\Waha\WahaBroadcast;
use App\Models\WaGateway\Waha\WahaBroadcastRecipient;
use App\Models\WaGateway\Gowa\GowaBroadcast;
use App\Models\WaGateway\Gowa\GowaBroadcastRecipient;
use App\Models\WaGateway\WhatsappContact;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Broadcast WhatsApp')] class extends Component {
    use WithPagination, WithFileUploads;

    public string $activeGateway = 'waha';

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

    // Field khusus GOWA
    public string $latitude = '';
    public string $longitude = '';
    public string $contactName = '';
    public string $contactPhone = '';
    public string $linkUrl = '';
    public string $pollQuestion = '';
    public array $pollOptions = ['', ''];
    public int $pollMaxAnswer = 1;

    // Detail modal
    public bool $showDetailModal = false;
    public ?string $detailBroadcastId = null;

    // Form modal
    public bool $showFormModal = false;

    // Kontak picker untuk broadcast
    public bool $showContactPicker = false;
    public string $contactPickerSearch = '';
    public string $contactPickerGroup = '';
    public array $contactPickerSelected = [];

    public function mount(): void
    {
        $this->activeGateway = ConfigurationHelper::get('whatsapp.active_gateway', 'waha');
    }

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

    public function addPollOption(): void
    {
        if (count($this->pollOptions) < 12) {
            $this->pollOptions[] = '';
        }
    }

    public function removePollOption(int $index): void
    {
        if (count($this->pollOptions) > 2) {
            array_splice($this->pollOptions, $index, 1);
            $this->pollOptions = array_values($this->pollOptions);
        }
    }

    public function openForm(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function createBroadcast(): void
    {
        $isWaha = $this->activeGateway === 'waha';

        $rules = [
            'name' => 'required|string|max:255',
            'phones' => 'required|string',
        ];

        $messages = [
            'name.required' => 'Nama broadcast harus diisi',
            'phones.required' => 'Nomor penerima harus diisi',
            'message.required' => 'Pesan harus diisi',
            'file.required' => 'File harus diunggah',
            'file.max' => 'Ukuran file maksimal 10MB',
        ];

        if ($isWaha) {
            $rules['type'] = 'required|in:text,image,file';
        } else {
            $rules['type'] = 'required|in:text,image,file,video,audio,location,contact,link,poll';
        }

        // Validasi per tipe
        match ($this->type) {
            'text' => ($rules['message'] = 'required|string'),
            'image' => ($rules = array_merge($rules, ['file' => 'required|image|max:10240'])),
            'file' => ($rules = array_merge($rules, ['file' => 'required|file|max:10240'])),
            'video' => ($rules = array_merge($rules, ['file' => 'required|file|max:10240|mimes:mp4,avi,mov,mkv,3gp'])),
            'audio' => ($rules = array_merge($rules, ['file' => 'required|file|max:10240|mimes:mp3,wav,ogg,m4a,aac'])),
            'location' => ($rules = array_merge($rules, [
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
            ])),
            'contact' => ($rules = array_merge($rules, [
                'contactName' => 'required|string|max:255',
                'contactPhone' => 'required|string|min:10|max:15',
            ])),
            'link' => ($rules = array_merge($rules, ['linkUrl' => 'required|url|max:2048'])),
            'poll' => ($rules = array_merge($rules, [
                'pollQuestion' => 'required|string|max:255',
                'pollOptions' => 'required|array|min:2|max:12',
                'pollOptions.*' => 'required|string|max:100',
                'pollMaxAnswer' => 'required|integer|min:1',
            ])),
            default => null,
        };

        $this->validate($rules, $messages);

        // Parse nomor telepon
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

        // File upload
        if ($this->file && in_array($this->type, ['image', 'file', 'video', 'audio'])) {
            $folder = $isWaha ? 'whatsapp/broadcast' : 'gowa/broadcast';
            $path = $this->file->store($folder, 'public');
            $data['file_path'] = $path;
            $data['file_name'] = $this->file->getClientOriginalName();
        }

        // Metadata GOWA
        if (!$isWaha) {
            match ($this->type) {
                'location' => ($data['metadata'] = ['latitude' => $this->latitude, 'longitude' => $this->longitude]),
                'contact' => ($data['metadata'] = ['contact_name' => $this->contactName, 'contact_phone' => $this->contactPhone]),
                'link' => ($data['metadata'] = ['link' => $this->linkUrl]),
                'poll' => ($data['metadata'] = [
                    'question' => $this->pollQuestion,
                    'options' => array_filter($this->pollOptions),
                    'max_answer' => $this->pollMaxAnswer,
                ]),
                default => null,
            };
        }

        $broadcastModel = $isWaha ? WahaBroadcast::class : GowaBroadcast::class;
        $recipientModel = $isWaha ? WahaBroadcastRecipient::class : GowaBroadcastRecipient::class;

        $broadcast = $broadcastModel::create($data);

        foreach ($phoneList as $phone) {
            $recipientModel::create([
                'broadcast_id' => $broadcast->id,
                'phone' => $phone,
                'status' => 'pending',
            ]);
        }

        $this->showFormModal = false;
        $this->resetForm();
        $this->toastSuccess("Broadcast \"{$broadcast->name}\" berhasil dibuat dengan {$phoneList->count()} penerima");
    }

    public function startBroadcast(string $id): void
    {
        $isWaha = $this->activeGateway === 'waha';
        $model = $isWaha ? WahaBroadcast::class : GowaBroadcast::class;
        $broadcast = $model::findOrFail($id);

        if ($broadcast->status !== 'draft') {
            $this->toastError('Broadcast hanya bisa dimulai dari status draft');
            return;
        }

        if ($isWaha) {
            ProcessWhatsappBroadcastJob::dispatch($broadcast);
        } else {
            ProcessGowaBroadcastJob::dispatch($broadcast);
        }

        $this->toastSuccess('Broadcast mulai diproses');
    }

    public function cancelBroadcast(string $id): void
    {
        $model = $this->activeGateway === 'waha' ? WahaBroadcast::class : GowaBroadcast::class;
        $broadcast = $model::findOrFail($id);

        if (!in_array($broadcast->status, ['draft', 'processing'])) {
            $this->toastError('Broadcast tidak bisa dibatalkan');
            return;
        }

        $broadcast->update(['status' => 'cancelled']);
        $this->toastSuccess('Broadcast berhasil dibatalkan');
    }

    public function viewDetail(string $id): void
    {
        $this->detailBroadcastId = $id;
        $this->showDetailModal = true;
    }

    public function deleteBroadcast(string $id): void
    {
        $model = $this->activeGateway === 'waha' ? WahaBroadcast::class : GowaBroadcast::class;
        $broadcast = $model::findOrFail($id);

        if ($broadcast->status !== 'draft') {
            $this->toastError('Hanya broadcast berstatus draft yang bisa dihapus');
            return;
        }

        $broadcast->delete();
        $this->toastSuccess('Broadcast berhasil dihapus');
    }

    // ========== KONTAK PICKER ==========

    public function openContactPicker(): void
    {
        $this->contactPickerSearch = '';
        $this->contactPickerGroup = '';
        $this->contactPickerSelected = [];
        $this->showContactPicker = true;
    }

    public function toggleContactSelect(string $id): void
    {
        if (in_array($id, $this->contactPickerSelected)) {
            $this->contactPickerSelected = array_values(array_diff($this->contactPickerSelected, [$id]));
        } else {
            $this->contactPickerSelected[] = $id;
        }
    }

    public function selectAllContacts(): void
    {
        $contacts = $this->getPickerContacts();
        $ids = $contacts->pluck('id')->toArray();

        if (count($this->contactPickerSelected) === count($ids)) {
            $this->contactPickerSelected = [];
        } else {
            $this->contactPickerSelected = $ids;
        }
    }

    public function importFromContacts(): void
    {
        if (empty($this->contactPickerSelected)) {
            $this->toastError('Pilih minimal satu kontak');
            return;
        }

        $contacts = WhatsappContact::whereIn('id', $this->contactPickerSelected)->get();
        $phones = $contacts->pluck('phone')->toArray();

        $existingPhones = array_filter(explode("\n", $this->phones));
        $existingPhones = array_map('trim', $existingPhones);

        $merged = array_unique(array_merge($existingPhones, $phones));
        $merged = array_filter($merged);

        $this->phones = implode("\n", $merged);
        $this->showContactPicker = false;
        $this->toastSuccess(count($phones) . ' nomor ditambahkan dari kontak');
    }

    private function getPickerContacts()
    {
        return WhatsappContact::query()->search($this->contactPickerSearch)->group($this->contactPickerGroup)->orderBy('name')->limit(100)->get();
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->message = '';
        $this->type = 'text';
        $this->file = null;
        $this->phones = '';
        $this->latitude = '';
        $this->longitude = '';
        $this->contactName = '';
        $this->contactPhone = '';
        $this->linkUrl = '';
        $this->pollQuestion = '';
        $this->pollOptions = ['', ''];
        $this->pollMaxAnswer = 1;
        $this->resetValidation();
    }

    public function with(): array
    {
        $isWaha = $this->activeGateway === 'waha';
        $model = $isWaha ? WahaBroadcast::class : GowaBroadcast::class;

        $query = $model
            ::query()
            ->with('creator')
            ->withCount(['recipients as pending_count' => fn($q) => $q->where('status', 'pending')])
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->orderByDesc('created_at');

        $detailBroadcast = null;
        $detailRecipients = null;
        if ($this->detailBroadcastId) {
            $detailBroadcast = $model::with('creator')->find($this->detailBroadcastId);
            $detailRecipients = $detailBroadcast?->recipients()->orderBy('status')->paginate(20, pageName: 'recipientPage');
        }

        $pickerContacts = $this->showContactPicker ? $this->getPickerContacts() : collect();
        $contactGroups = WhatsappContact::select('group')->whereNotNull('group')->distinct()->pluck('group');

        return [
            'broadcasts' => $query->paginate(15),
            'detailBroadcast' => $detailBroadcast,
            'detailRecipients' => $detailRecipients,
            'isWaha' => $isWaha,
            'pickerContacts' => $pickerContacts,
            'contactGroups' => $contactGroups,
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Broadcast WhatsApp" subtitle="Kirim pesan massal ke banyak nomor">
        <x-slot name="actions">
            <flux:badge color="{{ $activeGateway === 'waha' ? 'blue' : 'emerald' }}" size="lg">
                {{ strtoupper($activeGateway) }}
            </flux:badge>
            <a href="{{ route('whatsapp.configuration') }}" wire:navigate
                class="text-xs text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400 dark:hover:text-primary-dark-200 underline">
                Ganti Gateway
            </a>
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
                                    :color="match($bc->type) {
                                                                                                                                                                                        'text' => 'zinc', 'image' => 'blue', 'file' => 'purple',
                                                                                                                                                                                        'video' => 'indigo', 'audio' => 'amber', 'location' => 'emerald',
                                                                                                                                                                                        'contact' => 'cyan', 'link' => 'sky', 'poll' => 'fuchsia',
                                                                                                                                                                                        default => 'zinc'
                                                                                                                                                                                    }"
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
                                                                                                                                                                                        'draft' => 'zinc', 'processing' => 'yellow', 'completed' => 'green', 'cancelled' => 'red', default => 'zinc'
                                                                                                                                                                                    }"
                                    size="sm">
                                    {{ match ($bc->status) {'draft' => 'Draft','processing' => 'Diproses','completed' => 'Selesai','cancelled' => 'Dibatalkan',default => $bc->status} }}
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
                        @if ($activeGateway === 'gowa')
                            <flux:select.option value="video">Video</flux:select.option>
                            <flux:select.option value="audio">Audio</flux:select.option>
                            <flux:select.option value="location">Lokasi</flux:select.option>
                            <flux:select.option value="contact">Kontak</flux:select.option>
                            <flux:select.option value="link">Link</flux:select.option>
                            <flux:select.option value="poll">Poll</flux:select.option>
                        @endif
                    </flux:select>
                </flux:field>

                {{-- Text / Caption --}}
                @if (in_array($type, ['text', 'image']) || ($activeGateway === 'gowa' && in_array($type, ['video', 'link'])))
                    <flux:field>
                        <flux:label>{{ $type === 'text' ? 'Pesan' : 'Caption' }}</flux:label>
                        <flux:textarea wire:model="message" rows="4"
                            placeholder="{{ $type === 'text' ? 'Ketik pesan broadcast...' : 'Caption (opsional)' }}" />
                        @error('message')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>
                @endif

                {{-- File Upload --}}
                @if (in_array($type, ['image', 'file']) || ($activeGateway === 'gowa' && in_array($type, ['video', 'audio'])))
                    <flux:field>
                        <flux:label>
                            {{ match ($type) {'image' => 'Upload Gambar','video' => 'Upload Video','audio' => 'Upload Audio',default => 'Upload File'} }}
                        </flux:label>
                        <input type="file" wire:model="file"
                            class="block w-full text-sm text-zinc-500 dark:text-primary-dark-400
                                file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700
                                dark:file:bg-primary-900/30 dark:file:text-primary-300
                                hover:file:bg-primary-100 dark:hover:file:bg-primary-900/50"
                            @if ($type === 'image') accept="image/*"
                            @elseif ($type === 'video') accept="video/*"
                            @elseif ($type === 'audio') accept="audio/*" @endif />
                        @error('file')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-zinc-400">Maksimal 10MB</p>
                    </flux:field>
                @endif

                {{-- GOWA: Location --}}
                @if ($activeGateway === 'gowa' && $type === 'location')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Latitude</flux:label>
                            <flux:input type="number" wire:model="latitude" step="any"
                                placeholder="-6.200000" />
                            @error('latitude')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>
                        <flux:field>
                            <flux:label>Longitude</flux:label>
                            <flux:input type="number" wire:model="longitude" step="any"
                                placeholder="106.816666" />
                            @error('longitude')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>
                    </div>
                @endif

                {{-- GOWA: Contact --}}
                @if ($activeGateway === 'gowa' && $type === 'contact')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Nama Kontak</flux:label>
                            <flux:input wire:model="contactName" placeholder="Nama kontak" />
                            @error('contactName')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>
                        <flux:field>
                            <flux:label>Nomor Kontak</flux:label>
                            <flux:input wire:model="contactPhone" placeholder="08xxxxxxxxxx" />
                            @error('contactPhone')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>
                    </div>
                @endif

                {{-- GOWA: Link --}}
                @if ($activeGateway === 'gowa' && $type === 'link')
                    <flux:field>
                        <flux:label>URL</flux:label>
                        <flux:input wire:model="linkUrl" placeholder="https://example.com" />
                        @error('linkUrl')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>
                @endif

                {{-- GOWA: Poll --}}
                @if ($activeGateway === 'gowa' && $type === 'poll')
                    <flux:field>
                        <flux:label>Pertanyaan Poll</flux:label>
                        <flux:input wire:model="pollQuestion" placeholder="Apa warna favorit Anda?" />
                        @error('pollQuestion')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>

                    <div class="space-y-2">
                        <flux:label>Opsi Jawaban</flux:label>
                        @foreach ($pollOptions as $index => $option)
                            <div class="flex items-center gap-2">
                                <flux:input wire:model="pollOptions.{{ $index }}"
                                    placeholder="Opsi {{ $index + 1 }}" />
                                @if (count($pollOptions) > 2)
                                    <x-atoms.button variant="ghost" size="sm" icon="x-mark"
                                        wire:click="removePollOption({{ $index }})" />
                                @endif
                            </div>
                            @error("pollOptions.{$index}")
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        @endforeach
                        @if (count($pollOptions) < 12)
                            <x-atoms.button variant="ghost" size="sm" icon="plus"
                                wire:click="addPollOption">
                                Tambah Opsi</x-atoms.button>
                        @endif
                    </div>

                    <flux:field>
                        <flux:label>Maks. Jawaban</flux:label>
                        <flux:input type="number" wire:model="pollMaxAnswer" min="1"
                            max="{{ count($pollOptions) }}" />
                        @error('pollMaxAnswer')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>
                @endif

                <flux:field>
                    <div class="flex items-center justify-between mb-1">
                        <flux:label>Nomor Penerima</flux:label>
                        <x-atoms.button variant="ghost" size="sm" icon="user-group"
                            wire:click="openContactPicker">
                            Impor dari Kontak
                        </x-atoms.button>
                    </div>
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
                <x-atoms.button variant="ghost" wire:click="$set('showFormModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="check" wire:click="createBroadcast"
                    wire:loading.attr="disabled" wire:target="createBroadcast">
                    <span wire:loading.remove wire:target="createBroadcast">Simpan Broadcast</span>
                    <span wire:loading wire:target="createBroadcast">Menyimpan...</span>
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Kontak Picker Modal --}}
    <x-organisms.modal wire:model="showContactPicker" maxWidth="2xl" title="">
        <div class="space-y-4">
            <flux:heading size="lg">Pilih Kontak</flux:heading>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <flux:input wire:model.live.debounce.300ms="contactPickerSearch" placeholder="Cari nama atau nomor..."
                    icon="magnifying-glass" />
                <flux:select wire:model.live="contactPickerGroup">
                    <flux:select.option value="">Semua Grup</flux:select.option>
                    @foreach ($contactGroups as $g)
                        <flux:select.option value="{{ $g }}">{{ $g }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div
                class="overflow-x-auto border rounded-lg border-zinc-200 dark:border-primary-dark-700 max-h-80 overflow-y-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                    <thead class="bg-zinc-50 dark:bg-primary-dark-900 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left w-10">
                                <input type="checkbox" wire:click="selectAllContacts" @checked(count($contactPickerSelected) > 0 && count($contactPickerSelected) === $pickerContacts->count())
                                    class="rounded border-zinc-300 dark:border-primary-dark-600" />
                            </th>
                            <th
                                class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Nama</th>
                            <th
                                class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Nomor</th>
                            <th
                                class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                Grup</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-primary-dark-700">
                        @forelse ($pickerContacts as $c)
                            <tr wire:key="picker-{{ $c->id }}"
                                class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50 cursor-pointer"
                                wire:click="toggleContactSelect('{{ $c->id }}')">
                                <td class="px-4 py-2">
                                    <input type="checkbox" @checked(in_array($c->id, $contactPickerSelected))
                                        class="rounded border-zinc-300 dark:border-primary-dark-600" />
                                </td>
                                <td class="px-4 py-2 text-sm text-zinc-900 dark:text-primary-dark-100">
                                    {{ $c->name }}
                                </td>
                                <td class="px-4 py-2 text-sm font-mono text-zinc-700 dark:text-primary-dark-300">
                                    {{ $c->phone }}</td>
                                <td class="px-4 py-2">
                                    @if ($c->group)
                                        <flux:badge color="zinc" size="sm">{{ $c->group }}</flux:badge>
                                    @else
                                        <span class="text-sm text-zinc-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-zinc-400">Tidak ada
                                    kontak ditemukan</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between">
                <span class="text-sm text-zinc-500 dark:text-primary-dark-400">
                    {{ count($contactPickerSelected) }} dipilih
                </span>
                <div class="flex gap-3">
                    <x-atoms.button variant="ghost"
                        wire:click="$set('showContactPicker', false)">Batal</x-atoms.button>
                    <x-atoms.button variant="primary" icon="plus" wire:click="importFromContacts"
                        :disabled="empty($contactPickerSelected)">
                        Tambahkan ({{ count($contactPickerSelected) }})
                    </x-atoms.button>
                </div>
            </div>
        </div>
    
    </x-organisms.modal>

    {{-- Detail Modal --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="3xl" title="">
        @if ($detailBroadcast)
            <div class="space-y-6">
                <flux:heading size="lg">Detail Broadcast: {{ $detailBroadcast->name }}</flux:heading>

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
                                {{ match ($detailBroadcast->status) {'draft' => 'Draft','processing' => 'Diproses','completed' => 'Selesai','cancelled' => 'Dibatalkan',default => $detailBroadcast->status} }}
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
                                            {{ $r->sent_at?->format('d/m/Y H:i:s') ?? '-' }}</td>
                                        <td class="px-4 py-2 text-xs text-red-500">{{ $r->error_message ?? '-' }}</td>
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
                        <div class="mt-3">{{ $detailRecipients->links() }}</div>
                    @endif
                </div>

                <div class="flex justify-end">
                    <x-atoms.button variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</x-atoms.button>
                </div>
            </div>
        @endif
    
    </x-organisms.modal>
</div>
