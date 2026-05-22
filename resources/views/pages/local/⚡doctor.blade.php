<?php

use App\Models\Simrs\Dokter;
use App\Models\Mapping\DoctorMap;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;

/** Halaman mapping dokter ke SNOMED CT via snomed-search. */
new #[Layout('layouts::app')] #[Title('Mapping Dokter → SNOMED CT')] class extends Component {
    use WithPagination;

    public string $search = '';

    // State modal snomed-search
    public bool $showSnomedSearchModal = false;
    public ?string $selectedDoctorCode = null;

    // State konfirmasi hapus
    public bool $showDeleteModal = false;
    public ?string $deleteCode = null;
    public ?string $deleteName = null;

    public string $snomedInitialSearch = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        // 1. Query dokter aktif dari SIMRS
        $doctors = Dokter::where('status', '1')->when($this->search, fn($q) => $q->where('nm_dokter', 'like', "%{$this->search}%")->orWhere('kd_dokter', 'like', "%{$this->search}%"))->orderBy('nm_dokter')->paginate(25);

        // 2. Load mapping dari DB lokal (terpisah, hindari cross-DB join)
        $codes = $doctors->pluck('kd_dokter')->toArray();
        $mappings = DoctorMap::whereIn('doctor_code', $codes)->get()->keyBy('doctor_code');

        // 3. Gabungkan via collection
        $doctors->getCollection()->transform(function ($doctor) use ($mappings) {
            $map = $mappings->get($doctor->kd_dokter);
            $doctor->snomed_code = $map?->system_code;
            $doctor->snomed_term = $map?->system_term;
            return $doctor;
        });

        return ['items' => $doctors];
    }

    /** Buka modal cari SNOMED CT untuk dokter tertentu */
    public function selectSnomed(string $doctorCode): void
    {
        $this->selectedDoctorCode = $doctorCode;
        $this->snomedInitialSearch = DoctorMap::where('doctor_code', $doctorCode)->value('system_term') ?? '';
        $this->showSnomedSearchModal = true;
    }

    /** Tangani pilihan dari komponen snomed-search */
    #[On('snomed-selected')]
    public function snomedSelected(string $system_code, string $system_term, string $system_display, string $category): void
    {
        if (!$this->selectedDoctorCode) {
            return;
        }

        DoctorMap::where('doctor_code', $this->selectedDoctorCode)->delete();

        DoctorMap::create([
            'doctor_code' => $this->selectedDoctorCode,
            'system_code' => $system_code,
            'system_term' => $system_term,
            'system_display' => 'http://snomed.info/sct',
        ]);

        $this->showSnomedSearchModal = false;
        $this->reset(['selectedDoctorCode']);
        $this->toastSuccess('Mapping SNOMED CT berhasil disimpan', 'Sukses');
    }

    /** Konfirmasi hapus mapping */
    public function confirmDelete(string $doctorCode, string $doctorName): void
    {
        $this->deleteCode = $doctorCode;
        $this->deleteName = $doctorName;
        $this->showDeleteModal = true;
    }

    public function deleteMapping(): void
    {
        if (!$this->deleteCode) {
            return;
        }
        DoctorMap::where('doctor_code', $this->deleteCode)->delete();
        $this->showDeleteModal = false;
        $this->reset(['deleteCode', 'deleteName']);
        $this->toastSuccess('Mapping berhasil dihapus', 'Sukses');
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->reset(['deleteCode', 'deleteName']);
    }
};
?>

<div>
    <x-ui.page-header title="Mapping Dokter → SNOMED CT" subtitle="Hubungkan dokter dengan kode spesialistik SNOMED CT" />

    @php
        $mappedCount = collect($items->items())->filter(fn($i) => $i->snomed_code)->count();
    @endphp

    {{-- Toolbar --}}
    <div class="flex items-center gap-3 mb-4">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Cari nama atau kode dokter..." clearable />
        </div>
        <div
            class="hidden sm:flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-medium bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 whitespace-nowrap shadow-sm">
            <span class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 dark:bg-emerald-500"></span>
                {{ $mappedCount }} ter-mapping
            </span>
            <span class="text-zinc-300 dark:text-primary-dark-600">/</span>
            <span class="text-zinc-500 dark:text-primary-dark-400">{{ $items->count() }} di halaman ini</span>
        </div>
    </div>

    {{-- Tabel --}}
    <div
        class="overflow-hidden bg-white border rounded-2xl dark:bg-primary-dark-800 border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr
                        class="border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/70 dark:bg-primary-dark-900/40">
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Nama Dokter</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500 w-32">
                            Kode</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Mapping SNOMED CT</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-40">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                    @forelse ($items as $item)
                        <tr :key="$item->kd_dokter"
                            class="group transition-colors hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/20">
                            <td class="px-5 py-4">
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $item->nm_dokter }}
                                </p>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span
                                    class="inline-block font-mono text-xs font-bold px-2 py-1 rounded-md
                                    bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300
                                    ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                                    {{ $item->kd_dokter }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                @if ($item->snomed_code)
                                    <div class="flex items-start gap-2.5">
                                        <span
                                            class="mt-1 w-2 h-2 rounded-full bg-emerald-400 dark:bg-emerald-500 shrink-0 ring-2 ring-emerald-100 dark:ring-emerald-900/50"></span>
                                        <div class="min-w-0">
                                            <p
                                                class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                                {{ $item->snomed_code }}</p>
                                            <p
                                                class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug line-clamp-2">
                                                {{ $item->snomed_term }}</p>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 text-zinc-400 dark:text-primary-dark-500">
                                        <span class="w-2 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-600"></span>
                                        <span class="text-xs italic">Belum di-mapping</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    <x-atoms.button wire:click="selectSnomed('{{ $item->kd_dokter }}')" size="sm"
                                        variant="{{ $item->snomed_code ? 'ghost' : 'primary' }}"
                                        icon="{{ $item->snomed_code ? 'pencil-square' : 'plus' }}">
                                        {{ $item->snomed_code ? 'Ubah' : 'Petakan' }}
                                    </x-atoms.button>
                                    @if ($item->snomed_code)
                                        <x-atoms.button
                                            wire:click="confirmDelete('{{ $item->kd_dokter }}', '{{ addslashes($item->nm_dokter) }}')"
                                            size="sm" icon="trash" variant="ghost"
                                            class="text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 opacity-0 group-hover:opacity-100 transition-opacity duration-150" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div
                                        class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                        <flux:icon name="user-circle"
                                            class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak
                                            ada data
                                            dokter</p>
                                        <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">Coba ubah
                                            kata kunci
                                            pencarian</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div
                class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                {{ $items->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Pencarian SNOMED CT --}}
    <x-organisms.modal wire:model="showSnomedSearchModal" maxWidth="4xl" title="Pilih Kode SNOMED CT">
        <div class="space-y-4">
            <div>
                
                <flux:text class="mt-0.5">Klik baris untuk memilih kode spesialistik dokter.</flux:text>
            </div>

            <livewire:components.snomed-search defaultTag="occupation" :initialSearch="$snomedInitialSearch" :key="'snomed-doctor-' . ($selectedDoctorCode ?? 'new')" />
        </div>

        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button wire:click="$set('showSnomedSearchModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Konfirmasi Hapus --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="sm" title="Hapus Mapping">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-900/30 shrink-0">
                    <flux:icon name="exclamation-triangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    
                    <flux:text class="mt-0.5">Tindakan ini tidak dapat dibatalkan.</flux:text>
                </div>
            </div>
            <div
                class="p-4 space-y-2 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-10 shrink-0">Kode</span>
                    <span
                        class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $deleteCode }}</span>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-xs font-medium text-zinc-400 w-10 shrink-0 mt-0.5">Nama</span>
                    <span class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $deleteName }}</span>
                </div>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="cancelDelete" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteMapping" variant="danger">Hapus Mapping</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
