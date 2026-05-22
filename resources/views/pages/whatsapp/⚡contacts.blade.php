<?php

use App\Models\WaGateway\WhatsappContact;
use App\Models\Patient;
use App\Models\Pegawai;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Kontak WhatsApp')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterGroup = '';

    // Form CRUD
    public bool $showFormModal = false;
    public ?string $editId = null;
    public string $formName = '';
    public string $formPhone = '';
    public string $formGroup = '';
    public string $formNotes = '';

    // Import modal
    public bool $showImportModal = false;
    public string $importSource = 'patients';
    public string $importSearch = '';
    public array $importSelected = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterGroup(): void
    {
        $this->resetPage();
    }

    public function updatedImportSource(): void
    {
        $this->importSelected = [];
        $this->importSearch = '';
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEdit(string $id): void
    {
        $contact = WhatsappContact::findOrFail($id);
        $this->editId = $contact->id;
        $this->formName = $contact->name;
        $this->formPhone = $contact->phone;
        $this->formGroup = $contact->group ?? '';
        $this->formNotes = $contact->notes ?? '';
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->validate(
            [
                'formName' => 'required|string|max:255',
                'formPhone' => 'required|string|min:10|max:15',
                'formGroup' => 'nullable|string|max:100',
                'formNotes' => 'nullable|string|max:500',
            ],
            [
                'formName.required' => 'Nama wajib diisi',
                'formPhone.required' => 'Nomor telepon wajib diisi',
                'formPhone.min' => 'Nomor telepon minimal 10 karakter',
            ],
        );

        $data = [
            'name' => $this->formName,
            'phone' => $this->formPhone,
            'group' => $this->formGroup ?: null,
            'notes' => $this->formNotes ?: null,
        ];

        if ($this->editId) {
            WhatsappContact::findOrFail($this->editId)->update($data);
            $this->toastSuccess('Kontak berhasil diperbarui');
        } else {
            WhatsappContact::create($data);
            $this->toastSuccess('Kontak berhasil ditambahkan');
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function delete(string $id): void
    {
        WhatsappContact::findOrFail($id)->delete();
        $this->toastSuccess('Kontak berhasil dihapus');
    }

    // ========== IMPORT ==========

    public function openImport(): void
    {
        $this->importSource = 'patients';
        $this->importSearch = '';
        $this->importSelected = [];
        $this->showImportModal = true;
    }

    public function toggleImportSelect(string $id): void
    {
        if (in_array($id, $this->importSelected)) {
            $this->importSelected = array_values(array_diff($this->importSelected, [$id]));
        } else {
            $this->importSelected[] = $id;
        }
    }

    public function selectAllImport(): void
    {
        $records = $this->getImportRecords();
        $ids = $records->pluck($this->importSource === 'patients' ? 'medical_record_number' : 'employee_id')->toArray();

        if (count($this->importSelected) === count($ids)) {
            $this->importSelected = [];
        } else {
            $this->importSelected = $ids;
        }
    }

    public function doImport(): void
    {
        if (empty($this->importSelected)) {
            $this->toastError('Pilih minimal satu data untuk diimpor');
            return;
        }

        $count = 0;
        $group = $this->importSource === 'patients' ? 'Pasien' : 'Pegawai';

        if ($this->importSource === 'patients') {
            $records = Patient::whereIn('medical_record_number', $this->importSelected)->whereNotNull('phone')->where('phone', '!=', '')->get();

            foreach ($records as $record) {
                WhatsappContact::updateOrCreate(['phone' => $record->phone], ['name' => $record->name, 'group' => $group]);
                $count++;
            }
        } else {
            $records = Pegawai::whereIn('employee_id', $this->importSelected)->whereNotNull('nik')->where('nik', '!=', '')->get();

            foreach ($records as $record) {
                WhatsappContact::updateOrCreate(['phone' => $record->nik], ['name' => $record->name, 'group' => $group]);
                $count++;
            }
        }

        $this->showImportModal = false;
        $this->importSelected = [];
        $this->toastSuccess("{$count} kontak berhasil diimpor sebagai \"{$group}\"");
    }

    private function getImportRecords()
    {
        if ($this->importSource === 'patients') {
            return Patient::query()
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->when(
                    $this->importSearch,
                    fn($q) => $q->where(function ($q) {
                        $q->where('name', 'like', "%{$this->importSearch}%")
                            ->orWhere('phone', 'like', "%{$this->importSearch}%")
                            ->orWhere('medical_record_number', 'like', "%{$this->importSearch}%");
                    }),
                )
                ->orderBy('name')
                ->limit(50)
                ->get();
        }

        return Pegawai::query()
            ->where('active_status', 'AKTIF')
            ->when(
                $this->importSearch,
                fn($q) => $q->where(function ($q) {
                    $q->where('name', 'like', "%{$this->importSearch}%")->orWhere('employee_id', 'like', "%{$this->importSearch}%");
                }),
            )
            ->orderBy('name')
            ->limit(50)
            ->get();
    }

    private function resetForm(): void
    {
        $this->editId = null;
        $this->formName = '';
        $this->formPhone = '';
        $this->formGroup = '';
        $this->formNotes = '';
        $this->resetValidation();
    }

    public function with(): array
    {
        $query = WhatsappContact::query()->search($this->search)->group($this->filterGroup)->orderBy('name');

        $groups = WhatsappContact::select('group')->whereNotNull('group')->distinct()->pluck('group');

        return [
            'contacts' => $query->paginate(20),
            'groups' => $groups,
            'importRecords' => $this->showImportModal ? $this->getImportRecords() : collect(),
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Kontak WhatsApp" subtitle="Kelola daftar kontak untuk pengiriman pesan">
        <x-slot name="actions">
            <x-atoms.button variant="ghost" icon="arrow-down-tray" wire:click="openImport">
                Impor
            </x-atoms.button>
            <x-atoms.button variant="primary" icon="plus" wire:click="openCreate">
                Tambah Kontak
            </x-atoms.button>
        </x-slot>
    </x-ui.page-header>

    {{-- Filter --}}
    <div class="p-4 mb-6 bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama atau nomor..."
                    icon="magnifying-glass" />
            </div>
            <flux:select wire:model.live="filterGroup">
                <flux:select.option value="">Semua Grup</flux:select.option>
                @foreach ($groups as $g)
                    <flux:select.option value="{{ $g }}">{{ $g }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Tabel Kontak --}}
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
                            Nomor</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Grup</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Catatan</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-right uppercase text-zinc-500 dark:text-primary-dark-400">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                    @forelse ($contacts as $contact)
                        <tr :key="$contact->id" class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-6 py-4">
                                <span
                                    class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ $contact->name }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="text-sm text-zinc-700 dark:text-primary-dark-300 font-mono">{{ $contact->phone }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($contact->group)
                                    <flux:badge color="zinc" size="sm">{{ $contact->group }}</flux:badge>
                                @else
                                    <span class="text-sm text-zinc-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="text-sm text-zinc-500 dark:text-primary-dark-400">{{ Str::limit($contact->notes, 40) ?: '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <x-atoms.button variant="ghost" size="sm" icon="pencil-square"
                                        wire:click="openEdit('{{ $contact->id }}')" title="Edit" />
                                    <x-atoms.button variant="ghost" size="sm" icon="trash"
                                        wire:click="delete('{{ $contact->id }}')"
                                        wire:confirm="Yakin hapus kontak ini?" title="Hapus" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="user-group"
                                        class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Belum ada kontak
                                    </p>
                                    <x-atoms.button variant="ghost" size="sm" class="mt-2"
                                        wire:click="openCreate">
                                        Tambah Kontak Pertama</x-atoms.button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($contacts->hasPages())
            <div class="px-6 py-4 border-t border-zinc-200 dark:border-primary-dark-700">
                {{ $contacts->links() }}
            </div>
        @endif
    </div>

    {{-- Form Modal (Tambah/Edit) --}}
    <x-organisms.modal wire:model="showFormModal" maxWidth="lg" title="">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editId ? 'Edit Kontak' : 'Tambah Kontak' }}</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Nama</flux:label>
                    <flux:input wire:model="formName" placeholder="Nama kontak" />
                    @error('formName')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Nomor Telepon</flux:label>
                    <flux:input wire:model="formPhone" placeholder="08xxxxxxxxxx" />
                    @error('formPhone')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Grup</flux:label>
                    <flux:input wire:model="formGroup" placeholder="Contoh: Pasien, Pegawai, Dokter" />
                    @error('formGroup')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Catatan</flux:label>
                    <flux:textarea wire:model="formNotes" rows="2" placeholder="Catatan (opsional)" />
                    @error('formNotes')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>
            </div>

            
        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showFormModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="check" wire:click="save" wire:loading.attr="disabled"
                    wire:target="save">
                    <span wire:loading.remove wire:target="save">Simpan</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Import Modal --}}
    <x-organisms.modal wire:model="showImportModal" maxWidth="2xl" title="">
        <div class="space-y-6">
            <flux:heading size="lg">Impor Kontak</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Sumber Data</flux:label>
                    <flux:select wire:model.live="importSource">
                        <flux:select.option value="patients">Data Pasien</flux:select.option>
                        <flux:select.option value="employees">Data Pegawai</flux:select.option>
                    </flux:select>
                </flux:field>

                @if ($importSource === 'employees')
                    <div
                        class="p-3 text-sm rounded-lg bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300">
                        Data pegawai tidak memiliki kolom nomor telepon. NIK pegawai akan digunakan sebagai penanda.
                    </div>
                @endif

                <flux:input wire:model.live.debounce.300ms="importSearch"
                    placeholder="Cari {{ $importSource === 'patients' ? 'nama/no. RM/nomor' : 'nama/NIP' }}..."
                    icon="magnifying-glass" />

                {{-- Tabel Preview --}}
                <div
                    class="overflow-x-auto border rounded-lg border-zinc-200 dark:border-primary-dark-700 max-h-80 overflow-y-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                        <thead class="bg-zinc-50 dark:bg-primary-dark-900 sticky top-0">
                            <tr>
                                <th class="px-4 py-2 text-left w-10">
                                    <input type="checkbox" wire:click="selectAllImport" @checked(count($importSelected) > 0 && count($importSelected) === $importRecords->count())
                                        class="rounded border-zinc-300 dark:border-primary-dark-600" />
                                </th>
                                <th
                                    class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                    Nama</th>
                                <th
                                    class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                    {{ $importSource === 'patients' ? 'Nomor Telepon' : 'NIP' }}
                                </th>
                                @if ($importSource === 'patients')
                                    <th
                                        class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        No. RM</th>
                                @else
                                    <th
                                        class="px-4 py-2 text-xs font-medium text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                                        Jabatan</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-primary-dark-700">
                            @forelse ($importRecords as $rec)
                                @php
                                    $recId =
                                        $importSource === 'patients' ? $rec->medical_record_number : $rec->employee_id;
                                @endphp
                                <tr wire:key="import-{{ $recId }}"
                                    class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50 cursor-pointer"
                                    wire:click="toggleImportSelect('{{ $recId }}')">
                                    <td class="px-4 py-2">
                                        <input type="checkbox" @checked(in_array($recId, $importSelected))
                                            class="rounded border-zinc-300 dark:border-primary-dark-600" />
                                    </td>
                                    <td class="px-4 py-2 text-sm text-zinc-900 dark:text-primary-dark-100">
                                        {{ $rec->name }}
                                    </td>
                                    <td class="px-4 py-2 text-sm font-mono text-zinc-700 dark:text-primary-dark-300">
                                        {{ $importSource === 'patients' ? $rec->phone : $rec->employee_id }}
                                    </td>
                                    @if ($importSource === 'patients')
                                        <td class="px-4 py-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                                            {{ $rec->medical_record_number }}</td>
                                    @else
                                        <td class="px-4 py-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                                            {{ $rec->position_name ?? ($rec->position ?? '-') }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-zinc-400">
                                        {{ $importSource === 'patients' ? 'Tidak ada pasien dengan nomor telepon' : 'Tidak ada pegawai aktif ditemukan' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($importRecords->count() >= 50)
                    <p class="text-xs text-zinc-400">Menampilkan 50 data pertama. Gunakan pencarian untuk menyaring.
                    </p>
                @endif
            </div>

            <div class="flex items-center justify-between">
                <span class="text-sm text-zinc-500 dark:text-primary-dark-400">
                    {{ count($importSelected) }} dipilih
                </span>
                <div class="flex gap-3">
                    <x-atoms.button variant="ghost" wire:click="$set('showImportModal', false)">Batal</x-atoms.button>
                    <x-atoms.button variant="primary" icon="arrow-down-tray" wire:click="doImport"
                        wire:loading.attr="disabled" wire:target="doImport" :disabled="empty($importSelected)">
                        <span wire:loading.remove wire:target="doImport">Impor ({{ count($importSelected) }})</span>
                        <span wire:loading wire:target="doImport">Mengimpor...</span>
                    </x-atoms.button>
                </div>
            </div>
        </div>
    
    </x-organisms.modal>
</div>
