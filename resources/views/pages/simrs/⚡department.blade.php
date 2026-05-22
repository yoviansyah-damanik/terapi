<?php

use App\Models\Simrs\Departemen;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Master Data — Departemen')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public int $perPage = 25;

    // Properti Form
    public string $dep_id = '';
    public string $nama = '';

    // State Kontrol Modal
    public bool $showCreateEditModal = false;
    public bool $showDeleteModal = false;
    public bool $isEdit = false;

    // Target Penghapusan
    public string $deletingDepId = '';
    public string $deletingDepName = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->isEdit = false;
        $this->dep_id = '';
        $this->nama = '';
        $this->resetValidation();
        $this->showCreateEditModal = true;
    }

    public function openEdit(string $depId): void
    {
        $this->isEdit = true;
        $department = Departemen::findOrFail($depId);
        $this->dep_id = $department->dep_id;
        $this->nama = $department->nama;
        $this->resetValidation();
        $this->showCreateEditModal = true;
    }

    public function save(): void
    {
        // Validasi input data
        $rules = [
            'dep_id' => 'required|string|max:10',
            'nama' => 'required|string|max:100',
        ];

        $this->validate($rules);

        try {
            if ($this->isEdit) {
                // Perbarui nama departemen (ID tidak boleh diubah untuk integritas referensi database)
                Departemen::where('dep_id', $this->dep_id)->update([
                    'nama' => $this->nama,
                ]);
                $this->toastSuccess('Departemen berhasil diperbarui.');
            } else {
                // Periksa duplikasi ID sebelum menambahkan data baru
                $exists = Departemen::where('dep_id', $this->dep_id)->exists();
                if ($exists) {
                    $this->addError('dep_id', 'Kode Departemen sudah digunakan.');
                    return;
                }

                // Buat data departemen baru
                Departemen::create([
                    'dep_id' => $this->dep_id,
                    'nama' => $this->nama,
                ]);
                $this->toastSuccess('Departemen berhasil ditambahkan.');
            }
        } catch (\Exception $e) {
            $this->toastError('Gagal menyimpan departemen: ' . $e->getMessage());
            return;
        }

        $this->showCreateEditModal = false;
    }

    public function confirmDelete(string $depId, string $nama): void
    {
        $this->deletingDepId = $depId;
        $this->deletingDepName = $nama;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (empty($this->deletingDepId)) {
            return;
        }

        try {
            // Hapus departemen berdasarkan dep_id
            Departemen::where('dep_id', $this->deletingDepId)->delete();
            $this->toastSuccess("Departemen \"{$this->deletingDepName}\" berhasil dihapus.");
        } catch (\Exception $e) {
            // Tangkap exception jika gagal akibat foreign key constraint
            $this->toastError("Gagal menghapus departemen: Data sedang digunakan oleh tabel lain.");
        }

        $this->showDeleteModal = false;
        $this->deletingDepId = '';
        $this->deletingDepName = '';
    }

    public function with(): array
    {
        try {
            $query = Departemen::query()
                ->when($this->search, function ($q) {
                    $q->where('dep_id', 'like', "%{$this->search}%")
                      ->orWhere('nama', 'like', "%{$this->search}%");
                })
                ->orderBy('nama');

            $items = $query->paginate($this->perPage);
            $total = Departemen::count();

            return compact('items', 'total');
        } catch (\Throwable $e) {
            return [
                'simrsError' => $e->getMessage(),
                'items' => collect(),
                'total' => 0,
            ];
        }
    }
};
?>

<div>
    <x-ui.page-header title="Departemen SIMRS" subtitle="Kelola data master departemen dan bagian pelayanan SIMRS">
        <x-slot:actions>
            <x-atoms.button wire:click="openCreate" icon="plus" variant="primary">Tambah Departemen</x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats --}}
    @unless (isset($simrsError))
        <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-organisms.stat-card title="Total Departemen" :value="number_format($total)" icon="building-office" color="zinc" />
        </div>
    @endunless

    @if (isset($simrsError))
        <x-ui.empty-state icon="exclamation-triangle" title="Koneksi SIMRS Gagal" :description="$simrsError" />
    @else
        <x-organisms.data-panel>
            <x-slot:filter>
                <div class="flex-1 min-w-48">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari kode atau nama departemen..." icon="magnifying-glass" clearable />
                </div>
                <flux:select wire:model.live="perPage" class="w-36">
                    <flux:select.option value="25">25 / halaman</flux:select.option>
                    <flux:select.option value="50">50 / halaman</flux:select.option>
                    <flux:select.option value="100">100 / halaman</flux:select.option>
                </flux:select>
            </x-slot:filter>

            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="w-10">#</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-32">Kode</x-atoms.table-heading>
                    <x-atoms.table-heading>Nama Departemen</x-atoms.table-heading>
                    <x-atoms.table-heading align="center" class="w-24">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($items as $item)
                    <x-molecules.table-row wire:key="dep-{{ $item->dep_id }}">
                        <x-atoms.table-cell>
                            <span class="text-zinc-400">{{ $items->firstItem() + $loop->index }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell nowrap>
                            <span class="font-mono text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">
                                {{ $item->dep_id }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            {{ $item->nama }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" :action="true">
                            <div class="flex items-center justify-center gap-1">
                                <x-atoms.button size="xs" variant="ghost" icon="pencil-square"
                                    wire:click="openEdit('{{ $item->dep_id }}')" />
                                <x-atoms.button size="xs" variant="ghost" icon="trash"
                                    wire:click="confirmDelete('{{ $item->dep_id }}', '{{ addslashes($item->nama) }}')"
                                    class="text-red-400 hover:text-red-600 dark:hover:text-red-400" />
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="4">
                            <x-ui.empty-state icon="building-office" title="Tidak ada data"
                                description="Tidak ada departemen yang sesuai pencarian" />
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse
            </x-organisms.table>

            <x-slot:footer>
                @if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator && $items->hasPages())
                    {{ $items->links() }}
                @endif
            </x-slot:footer>
        </x-organisms.data-panel>
    @endif

    {{-- Modal: Tambah / Edit Departemen --}}
    <x-organisms.modal wire:model="showCreateEditModal" maxWidth="md" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="building-office" class="size-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <flux:heading size="lg">{{ $isEdit ? 'Edit Departemen' : 'Tambah Departemen' }}</flux:heading>
                    <flux:subheading>{{ $isEdit ? 'Ubah nama dari departemen terpilih.' : 'Masukkan detail untuk membuat departemen baru.' }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Kode Departemen</flux:label>
                    <flux:input wire:model="dep_id" placeholder="Contoh: DIR, KEU, LAB" :readonly="$isEdit" />
                    @error('dep_id')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Nama Departemen</flux:label>
                    <flux:input wire:model="nama" placeholder="Contoh: Direksi, Keuangan, Laboratorium" />
                    @error('nama')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>
            </div>

            <x-slot:footer>
                <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                    <x-atoms.button wire:click="$set('showCreateEditModal', false)" variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="save" variant="primary" icon="check">Simpan</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>

    {{-- Modal: Konfirmasi Hapus --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="sm" title="">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="trash" class="size-5 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Departemen</flux:heading>
                    <flux:subheading>Tindakan ini tidak dapat dibatalkan.</flux:subheading>
                </div>
            </div>

            <p class="text-sm text-zinc-600 dark:text-primary-dark-400">
                Yakin ingin menghapus departemen <strong>{{ $deletingDepName }}</strong> (ID: {{ $deletingDepId }})?
                Tindakan ini akan gagal bila ada data lain (seperti Pegawai) yang masih mengacu pada departemen ini.
            </p>

            <x-slot:footer>
                <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4 dark:border-primary-dark-700">
                    <x-atoms.button wire:click="$set('showDeleteModal', false)" variant="ghost">Batal</x-atoms.button>
                    <x-atoms.button wire:click="delete" variant="danger" icon="trash">Hapus</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>
</div>
