<?php

use App\Models\SatuSehat\SatuSehatBundleLog;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

new #[Lazy] class extends Component {
    use WithPagination;

    #[Url(as: 'bl_search')]
    public string $blSearch = '';
    #[Url(as: 'bl_resource')]
    public string $blResource = '';
    #[Url(as: 'bl_status')]
    public string $blStatus = '';
    #[Url(as: 'bl_date')]
    public string $blDate = '';
    #[Url(as: 'bl_per_page')]
    public int $blPerPage = 25;

    public bool $showBundleLogModal = false;
    public ?SatuSehatBundleLog $selectedBundleLog = null;

    public function updatedBlSearch(): void
    {
        $this->resetPage();
    }
    public function updatedBlResource(): void
    {
        $this->resetPage();
    }
    public function updatedBlStatus(): void
    {
        $this->resetPage();
    }
    public function updatedBlDate(): void
    {
        $this->resetPage();
    }

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('pages::logs._satusehat._skeleton-tab');
    }

    public function showBundleLogDetail(string $id): void
    {
        $this->selectedBundleLog = SatuSehatBundleLog::with('bundle')->find($id);
        $this->showBundleLogModal = true;
    }

    public function closeBundleLogDetail(): void
    {
        $this->showBundleLogModal = false;
        $this->selectedBundleLog = null;
    }

    public function with(): array
    {
        $query = SatuSehatBundleLog::query()
            ->with('bundle')
            ->when($this->blSearch, fn($q) => $q->whereHas('bundle', fn($b) => $b->where('no_rawat', 'like', "%{$this->blSearch}%")))
            ->when($this->blResource, fn($q) => $q->where('resource_type', $this->blResource))
            ->when($this->blStatus, fn($q) => $q->where('status', $this->blStatus))
            ->when($this->blDate === 'today', fn($q) => $q->whereDate('created_at', today()))
            ->when($this->blDate === 'week', fn($q) => $q->where('created_at', '>=', now()->startOfWeek()))
            ->when($this->blDate === 'month', fn($q) => $q->where('created_at', '>=', now()->startOfMonth()))
            ->latest();

        return [
            'bundleLogs' => $query->paginate($this->blPerPage, ['*'], 'blPage'),
            'blResourceTypes' => SatuSehatBundleLog::query()->distinct()->orderBy('resource_type')->pluck('resource_type'),
            'blStats' => [
                'total' => SatuSehatBundleLog::count(),
                'success' => SatuSehatBundleLog::where('status', 'success')->count(),
                'failed' => SatuSehatBundleLog::where('status', 'failed')->count(),
                'skipped' => SatuSehatBundleLog::where('status', 'skipped')->count(),
            ],
        ];
    }
};
?>

<div>
    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-4">
        <x-organisms.stat-card title="Total Aksi" value="{{ number_format($blStats['total']) }}" icon="queue-list"
            color="zinc" />
        <x-organisms.stat-card title="Terkirim" value="{{ number_format($blStats['success']) }}" icon="check-circle"
            color="emerald" />
        <x-organisms.stat-card title="Gagal" value="{{ number_format($blStats['failed']) }}" icon="x-circle"
            color="red" />
        <x-organisms.stat-card title="Dilewati" value="{{ number_format($blStats['skipped']) }}" icon="minus-circle"
            color="zinc" />
    </div>

    {{-- Filter --}}
    <div
        class="mb-4 flex flex-wrap items-center gap-3 bg-white dark:bg-primary-dark-800 p-4 rounded-2xl border border-zinc-200 dark:border-primary-dark-700 shadow-sm">
        <flux:input wire:model.live.debounce.300ms="blSearch" placeholder="Cari no. rawat..." icon="magnifying-glass"
            clearable class="flex-1 min-w-40" />
        <flux:select wire:model.live="blResource" class="w-48">
            <flux:select.option value="">Semua Resource</flux:select.option>
            @foreach ($blResourceTypes as $rt)
                <flux:select.option value="{{ $rt }}">{{ $rt }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="blStatus" class="w-44">
            <flux:select.option value="">Semua Status</flux:select.option>
            <flux:select.option value="success">Terkirim</flux:select.option>
            <flux:select.option value="failed">Gagal</flux:select.option>
            <flux:select.option value="skipped">Dilewati</flux:select.option>
            <flux:select.option value="warning">Peringatan</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="blDate" class="w-44">
            <flux:select.option value="">Semua Waktu</flux:select.option>
            <flux:select.option value="today">Hari Ini</flux:select.option>
            <flux:select.option value="week">Minggu Ini</flux:select.option>
            <flux:select.option value="month">Bulan Ini</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="blPerPage" class="w-40 ml-auto">
            <flux:select.option value="25">25 / halaman</flux:select.option>
            <flux:select.option value="50">50 / halaman</flux:select.option>
            <flux:select.option value="100">100 / halaman</flux:select.option>
        </flux:select>
    </div>

    {{-- Tabel --}}
    <x-organisms.data-panel title="Riwayat Aksi Bundle" subtitle="Audit trail setiap aksi pengiriman per-resource">
        <div class="overflow-x-auto">
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Waktu</x-atoms.table-heading>
                    <x-atoms.table-heading>No. Rawat</x-atoms.table-heading>
                    <x-atoms.table-heading>Resource</x-atoms.table-heading>
                    <x-atoms.table-heading>Local ID</x-atoms.table-heading>
                    <x-atoms.table-heading>IHS ID</x-atoms.table-heading>
                    <x-atoms.table-heading>Pesan</x-atoms.table-heading>
                    <x-atoms.table-heading align="center">Status</x-atoms.table-heading>
                    <x-atoms.table-heading align="center">Detail</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($bundleLogs as $bl)
                    <x-molecules.table-row :key="$bl->id">
                        <x-atoms.table-cell :nowrap="true">
                            <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300">
                                {{ $bl->created_at->format('d/m/Y') }}
                            </p>
                            <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500">
                                {{ $bl->created_at->format('H:i:s') }}
                            </p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :nowrap="true">
                            <span class="font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">
                                {{ $bl->bundle?->no_rawat ?? '-' }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :nowrap="true">
                            <flux:badge color="zinc" size="sm">{{ $bl->resource_type }}</flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :nowrap="true">
                            <span class="font-mono text-[11px] text-zinc-500 dark:text-primary-dark-400">
                                {{ $bl->local_id ?? '-' }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :nowrap="true">
                            @if ($bl->ihs_id)
                                <span class="font-mono text-[11px] text-emerald-600 dark:text-emerald-400">
                                    {{ $bl->ihs_id }}
                                </span>
                            @else
                                <span class="text-xs text-zinc-400">-</span>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            @if ($bl->error_message)
                                <span class="text-xs text-red-500 dark:text-red-400 line-clamp-1 max-w-xs">
                                    {{ $bl->error_message }}
                                </span>
                            @elseif ($bl->status === 'skipped')
                                <span class="text-xs text-zinc-400 italic">Sudah tersinkronisasi</span>
                            @elseif ($bl->status === 'success')
                                <span class="text-xs text-emerald-500">OK</span>
                            @else
                                <span class="text-xs text-zinc-400">-</span>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" :nowrap="true">
                            <flux:badge color="{{ $bl->status_color }}" size="sm">{{ $bl->status_label }}
                            </flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" :nowrap="true" :action="true">
                            <x-atoms.button variant="ghost" size="sm" icon="eye"
                                wire:click="showBundleLogDetail('{{ $bl->id }}')"
                                tooltip="Lihat Payload dan Response" />
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center">
                                <flux:icon name="queue-list"
                                    class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Belum ada riwayat
                                    bundle log</p>
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse

                @if ($bundleLogs->hasPages())
                    <x-slot:footer>
                        <div class="px-4 py-4">
                            {{ $bundleLogs->links() }}
                        </div>
                    </x-slot:footer>
                @endif
            </x-organisms.table>
        </div>
    </x-organisms.data-panel>


    <x-organisms.modal wire:model="showBundleLogModal" maxWidth="4xl" title="">
        @if ($selectedBundleLog)
            <div class="space-y-5">
                <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div
                        class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-lg
                        {{ $selectedBundleLog->status === 'success' ? 'bg-emerald-100 dark:bg-emerald-900/40' : ($selectedBundleLog->status === 'failed' ? 'bg-red-100 dark:bg-red-900/40' : 'bg-zinc-100 dark:bg-primary-dark-700') }}">
                        <flux:icon
                            name="{{ $selectedBundleLog->status === 'success' ? 'check-circle' : ($selectedBundleLog->status === 'failed' ? 'x-circle' : 'minus-circle') }}"
                            class="w-6 h-6 {{ $selectedBundleLog->status === 'success' ? 'text-emerald-600 dark:text-emerald-400' : ($selectedBundleLog->status === 'failed' ? 'text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-primary-dark-400') }}" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ $selectedBundleLog->resource_type }}
                        </h2>
                        <div class="flex flex-wrap items-center gap-2 mt-1.5">
                            <flux:badge color="{{ $selectedBundleLog->status_color }}" size="sm">
                                {{ $selectedBundleLog->status_label }}
                            </flux:badge>
                            <span class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                {{ $selectedBundleLog->created_at->format('d M Y H:i:s') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">No. Rawat
                        </p>
                        <p class="mt-1 font-mono text-sm font-bold text-primary-600 dark:text-primary-400">
                            {{ $selectedBundleLog->bundle?->no_rawat ?? '-' }}
                        </p>
                    </div>
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">Bundle ID
                        </p>
                        <p class="mt-1 font-mono text-[11px] break-all text-zinc-600 dark:text-primary-dark-300">
                            {{ $selectedBundleLog->bundle_id }}
                        </p>
                    </div>
                    @if ($selectedBundleLog->local_id)
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">Local ID
                            </p>
                            <p class="mt-1 font-mono text-sm text-zinc-700 dark:text-primary-dark-200">
                                {{ $selectedBundleLog->local_id }}</p>
                        </div>
                    @endif
                    @if ($selectedBundleLog->ihs_id)
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">IHS ID
                            </p>
                            <p class="mt-1 font-mono text-sm text-emerald-600 dark:text-emerald-400">
                                {{ $selectedBundleLog->ihs_id }}</p>
                        </div>
                    @endif
                </div>

                @if ($selectedBundleLog->error_message)
                    <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                        <p class="text-xs font-semibold uppercase text-red-600 dark:text-red-400">Error</p>
                        <p class="mt-1 text-sm text-red-800 dark:text-red-200">{{ $selectedBundleLog->error_message }}
                        </p>
                    </div>
                @endif
                @if ($selectedBundleLog->payload)
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                            Payload (Request)</p>
                        <x-atoms.code-block language="json"
                            maxHeight="max-h-64">{{ json_encode($selectedBundleLog->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</x-atoms.code-block>
                    </div>
                @endif
                @if ($selectedBundleLog->response)
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                            Response</p>
                        <x-atoms.code-block language="json"
                            maxHeight="max-h-64">{{ json_encode($selectedBundleLog->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</x-atoms.code-block>
                    </div>
                @endif

                <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                    <x-atoms.button variant="ghost" wire:click="closeBundleLogDetail">Tutup</x-atoms.button>
                </div>
            </div>
        @endif
    </x-organisms.modal>
</div>
