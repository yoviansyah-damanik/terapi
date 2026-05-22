<?php

use App\Models\Simrs\JnsPerawatan;
use App\Models\Simrs\JnsPerawatanInap;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Master Data — Tindakan')] class extends Component {
    use WithPagination;

    /**
     * Nilai activeTab: jalan, jalan_dr, jalan_pr, jalan_drpr,
     *                   inap,  inap_dr,  inap_pr,  inap_drpr
     */
    #[Url(as: 'tab')]
    public string $activeTab = 'jalan';

    #[Url]
    public string $search = '';

    #[Url]
    public int $perPage = 25;

    public function updatedActiveTab(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Map activeTab → tabel transaksi SIMRS; null = tampilkan semua dari master. */
    private const TX_TABLE_MAP = [
        'jalan_dr' => 'rawat_jl_dr',
        'jalan_pr' => 'rawat_jl_pr',
        'jalan_drpr' => 'rawat_jl_drpr',
        'inap_dr' => 'rawat_inap_dr',
        'inap_pr' => 'rawat_inap_pr',
        'inap_drpr' => 'rawat_inap_drpr',
    ];

    /**
     * Ambil DISTINCT kd_jenis_prw dari tabel transaksi, di-cache 10 menit.
     * Menghindari JOIN+GROUP BY+COUNT(*) mahal pada tabel transaksi besar.
     */
    private function getCachedCodes(string $txTable): array
    {
        return Cache::remember("proc_tx_codes_{$txTable}", 600, fn() => DB::connection('simrs')->table($txTable)->distinct()->pluck('kd_jenis_prw')->all());
    }

    public function with(): array
    {
        try {
            $txTable = self::TX_TABLE_MAP[$this->activeTab] ?? null;
            $isInap = str_starts_with($this->activeTab, 'inap');
            $masterModel = $isInap ? JnsPerawatanInap::class : JnsPerawatan::class;

            $query = $masterModel::query()->when($txTable, fn($q) => $q->whereIn('kd_jenis_prw', $this->getCachedCodes($txTable)))->when($this->search, fn($q) => $q->where('kd_jenis_prw', 'like', "%{$this->search}%")->orWhere('nm_perawatan', 'like', "%{$this->search}%"))->orderBy('nm_perawatan');

            return ['items' => $query->paginate($this->perPage)];
        } catch (\Throwable $e) {
            return ['simrsError' => $e->getMessage(), 'items' => collect()];
        }
    }
};
?>

<div>
    <x-ui.page-header title="Tindakan" subtitle="Data jenis tindakan / perawatan dari SIMRS" />

    @if (isset($simrsError))
        <x-ui.empty-state icon="exclamation-triangle" title="Koneksi SIMRS Gagal" :description="$simrsError" />
    @else
        @php
            $primaryTab = str_starts_with($activeTab, 'inap') ? 'inap' : 'jalan';
            $tabSuffix  = str_contains($activeTab, '_') ? '_' . Str::after($activeTab, '_') : '';
        @endphp

        {{-- Level 1: Rawat Jalan / Rawat Inap --}}
        <x-molecules.tabs class="mb-0">
            <x-atoms.tab-item
                wire:click="$set('activeTab', '{{ 'jalan' . $tabSuffix }}')"
                :active="$primaryTab === 'jalan'"
                icon="building-office">
                Rawat Jalan
            </x-atoms.tab-item>
            <x-atoms.tab-item
                wire:click="$set('activeTab', '{{ 'inap' . $tabSuffix }}')"
                :active="$primaryTab === 'inap'"
                icon="building-office-2">
                Rawat Inap
            </x-atoms.tab-item>
        </x-molecules.tabs>

        @php
            $subTabs = [
                ''      => 'Semua',
                '_dr'   => 'Dokter',
                '_pr'   => 'Perawat',
                '_drpr' => 'Dokter & Perawat',
            ];
        @endphp

        <x-organisms.data-panel>
            <x-slot:filter>
                {{-- Baris 1: search + count + perPage --}}
                <div class="flex flex-1 items-center gap-3">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="Cari kode atau nama tindakan..." clearable class="flex-1 min-w-48" />
                    <flux:badge color="zinc" size="sm" class="hidden sm:flex whitespace-nowrap shrink-0">
                        {{ $items instanceof \Illuminate\Pagination\LengthAwarePaginator ? number_format($items->total()) : 0 }} tindakan
                    </flux:badge>
                    <flux:select wire:model.live="perPage" class="w-32 shrink-0">
                        <flux:select.option value="25">25 / hal</flux:select.option>
                        <flux:select.option value="50">50 / hal</flux:select.option>
                        <flux:select.option value="100">100 / hal</flux:select.option>
                    </flux:select>
                </div>
                {{-- Baris 2: sub-filter pelaksana (w-full memaksa baris baru) --}}
                <div class="flex w-full flex-wrap items-center gap-1.5">
                    @foreach ($subTabs as $suffix => $label)
                        @php $tabValue = "{$primaryTab}{$suffix}"; @endphp
                        <x-atoms.button
                            wire:click="$set('activeTab', '{{ $tabValue }}')"
                            variant="{{ $activeTab === $tabValue ? 'primary' : 'outline' }}"
                            size="sm">
                            {{ $label }}
                        </x-atoms.button>
                    @endforeach
                </div>
            </x-slot:filter>

            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="w-10">#</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-36">Kode</x-atoms.table-heading>
                    <x-atoms.table-heading>Nama Tindakan</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($items as $item)
                    <x-molecules.table-row wire:key="prw-{{ $activeTab }}-{{ $item->kd_jenis_prw }}">
                        <x-atoms.table-cell class="text-zinc-400">
                            {{ ($items instanceof \Illuminate\Pagination\LengthAwarePaginator ? $items->firstItem() : 0) + $loop->index }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell nowrap>
                            <flux:badge size="sm" color="zinc" class="font-mono font-bold" inset="top bottom">
                                {{ $item->kd_jenis_prw }}
                            </flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <span class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">
                                {{ $item->nm_perawatan }}
                            </span>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="3">
                            <x-ui.empty-state icon="clipboard-document-list" title="Tidak ada data tindakan"
                                description="Coba ganti filter atau kata kunci pencarian" />
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
</div>
