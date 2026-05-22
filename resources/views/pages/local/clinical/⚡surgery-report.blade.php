<?php

use App\Models\Simrs\LaporanOperasi;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Laporan Operasi')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public int $perPage = 25;

    public bool $showDetailModal = false;
    public ?LaporanOperasi $selectedReport = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function viewDetail(string $noRawat): void
    {
        $this->selectedReport = LaporanOperasi::with('regPeriksa.pasien')->where('no_rawat', $noRawat)->first();
        $this->showDetailModal = true;
    }

    public function with(): array
    {
        $items = LaporanOperasi::query()
            ->with(['regPeriksa.pasien'])
            ->when($this->search, function ($q) {
                $q->where('no_rawat', 'like', "%{$this->search}%")
                    ->orWhere('diagnosa_preop', 'like', "%{$this->search}%")
                    ->orWhere('diagnosa_postop', 'like', "%{$this->search}%");
            })
            ->orderBy('tanggal', 'desc')
            ->paginate($this->perPage);

        return [
            'items' => $items,
        ];
    }
}; ?>

<div>
    <x-ui.page-header title="Laporan Operasi" subtitle="Daftar laporan operasi dari SIMRS">
    </x-ui.page-header>

    <x-organisms.data-panel>
        <x-slot:filter>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari No. Rawat atau Diagnosa..." clearable />
                </div>
                <flux:select wire:model.live="perPage" class="w-40 shrink-0">
                    <flux:select.option value="25">25 / halaman</flux:select.option>
                    <flux:select.option value="50">50 / halaman</flux:select.option>
                    <flux:select.option value="100">100 / halaman</flux:select.option>
                </flux:select>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-40">No. Rawat</x-atoms.table-heading>
                <x-atoms.table-heading>Pasien</x-atoms.table-heading>
                <x-atoms.table-heading>Tanggal</x-atoms.table-heading>
                <x-atoms.table-heading>Diagnosa Pre-Op</x-atoms.table-heading>
                <x-atoms.table-heading>Diagnosa Post-Op</x-atoms.table-heading>
                <x-atoms.table-heading align="center" class="w-20">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($items as $item)
                <x-molecules.table-row wire:key="report-{{ $item->no_rawat }}">
                    <x-atoms.table-cell>
                        <span class="font-mono text-xs font-bold text-zinc-700">{{ $item->no_rawat }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <p class="text-sm font-medium text-zinc-800">{{ $item->regPeriksa?->pasien?->nm_pasien ?? '-' }}</p>
                        <p class="text-[10px] text-zinc-400 font-mono">{{ $item->regPeriksa?->no_rkm_medis ?? '-' }}</p>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <span class="text-xs text-zinc-600">{{ $item->tanggal }}</span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <p class="text-xs text-zinc-600 line-clamp-2" title="{{ $item->diagnosa_preop }}">{{ $item->diagnosa_preop }}</p>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <p class="text-xs text-zinc-600 line-clamp-2" title="{{ $item->diagnosa_postop }}">{{ $item->diagnosa_postop }}</p>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell :action="true" align="center">
                        <x-atoms.button wire:click="viewDetail('{{ $item->no_rawat }}')" size="sm" icon="eye" variant="ghost" tooltip="Lihat Detail" />
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-16 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon name="document-text" class="w-10 h-10 text-zinc-200" />
                            <p class="text-sm font-semibold text-zinc-500">Tidak ada data laporan operasi</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-organisms.table>

        @if ($items->hasPages())
            <x-slot:footer>{{ $items->links() }}</x-slot:footer>
        @endif
    </x-organisms.data-panel>

    {{-- Detail Modal --}}
    <x-organisms.modal wire:model="showDetailModal" title="Detail Laporan Operasi" maxWidth="4xl">
        @if ($selectedReport)
            <div class="space-y-6">
                <div class="grid grid-cols-2 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-200 dark:border-primary-dark-700">
                    <div>
                        <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Informasi Pasien</p>
                        <p class="text-sm font-bold text-zinc-800 dark:text-primary-dark-100 mt-1">{{ $selectedReport->regPeriksa?->pasien?->nm_pasien }}</p>
                        <p class="text-xs text-zinc-500 font-mono">{{ $selectedReport->regPeriksa?->no_rkm_medis }} — {{ $selectedReport->no_rawat }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Waktu Operasi</p>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100 mt-1">{{ $selectedReport->tanggal }}</p>
                        <p class="text-xs text-zinc-500">Selesai: {{ $selectedReport->selesaioperasi }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-bold text-zinc-400 uppercase">Diagnosa Pre-Op</label>
                            <p class="text-sm text-zinc-700 mt-1">{{ $selectedReport->diagnosa_preop }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-zinc-400 uppercase">Diagnosa Post-Op</label>
                            <p class="text-sm text-zinc-700 mt-1">{{ $selectedReport->diagnosa_postop }}</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-bold text-zinc-400 uppercase">Jaringan Dieksekusi</label>
                            <p class="text-sm text-zinc-700 mt-1">{{ $selectedReport->jaringan_dieksekusi }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-zinc-400 uppercase">Permintaan PA</label>
                            <p class="text-sm text-zinc-700 mt-1">{{ $selectedReport->permintaan_pa }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold text-zinc-400 uppercase">Laporan Operasi</label>
                    <div class="mt-2 p-4 rounded-xl bg-white dark:bg-primary-dark-800 border border-zinc-200 dark:border-primary-dark-700 text-sm text-zinc-800 leading-relaxed whitespace-pre-wrap">
                        {!! nl2br(e($selectedReport->laporan_operasi)) !!}
                    </div>
                </div>
            </div>
        @endif
        <x-slot:footer>
            <x-atoms.button wire:click="$set('showDetailModal', false)" variant="ghost">Tutup</x-atoms.button>
        </x-slot:footer>
    </x-organisms.modal>
</div>
