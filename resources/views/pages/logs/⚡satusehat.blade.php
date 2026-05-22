<?php

use App\Models\SatuSehat\SatuSehatBundle;
use App\Models\SatuSehat\SatuSehatBundleLog;
use App\Models\SatuSehat\SatuSehatLog;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Log Satu Sehat')] class extends Component {
    use WithPagination;

    #[Url(as: 'tab')]
    public string $activeTab = 'api';

    // Tab: API Log
    #[Url]
    public string $search = '';
    #[Url]
    public string $filterResource = '';
    #[Url]
    public string $filterAction = '';
    #[Url]
    public string $filterStatus = '';
    #[Url]
    public string $filterDate = '';
    #[Url]
    public int $perPage = 25;

    public bool $showDetailModal = false;
    public ?SatuSehatLog $selectedLog = null;

    // Tab: Bundle Job
    #[Url(as: 'bundle_search')]
    public string $bundleSearch = '';
    #[Url(as: 'bundle_status')]
    public string $bundleStatus = '';
    #[Url(as: 'bundle_date')]
    public string $bundleDate = '';
    #[Url(as: 'bundle_per_page')]
    public int $bundlePerPage = 25;

    // Tab: Bundle Log (audit trail)
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

    public function updatedActiveTab(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterResource(): void
    {
        $this->resetPage();
    }
    public function updatedFilterAction(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }
    public function updatedFilterDate(): void
    {
        $this->resetPage();
    }
    public function updatedBundleSearch(): void
    {
        $this->resetPage();
    }
    public function updatedBundleStatus(): void
    {
        $this->resetPage();
    }
    public function updatedBundleDate(): void
    {
        $this->resetPage();
    }
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

    public function showDetail(string $id): void
    {
        $this->selectedLog = SatuSehatLog::with('user')->find($id);
        $this->showDetailModal = true;
    }

    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->selectedLog = null;
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
        $query = SatuSehatLog::query()
            ->with('user')
            ->search($this->search)
            ->when($this->filterResource, fn($q) => $q->forResource($this->filterResource))
            ->when($this->filterAction, fn($q) => $q->forAction($this->filterAction))
            ->when($this->filterStatus === 'success', fn($q) => $q->successful())
            ->when($this->filterStatus === 'failed', fn($q) => $q->failed())
            ->when($this->filterDate === 'today', fn($q) => $q->today())
            ->when($this->filterDate === 'week', fn($q) => $q->thisWeek())
            ->when($this->filterDate === 'month', fn($q) => $q->thisMonth())
            ->latest();

        $bundleQuery = SatuSehatBundle::query()
            ->with(['triggeredBy', 'items' => fn($q) => $q->orderBy('created_at')])
            ->when($this->bundleSearch, fn($q) => $q->where('no_rawat', 'like', "%{$this->bundleSearch}%"))
            ->when($this->bundleStatus, fn($q) => $q->where('status', $this->bundleStatus))
            ->when($this->bundleDate === 'today', fn($q) => $q->whereDate('created_at', today()))
            ->when($this->bundleDate === 'week', fn($q) => $q->where('created_at', '>=', now()->startOfWeek()))
            ->when($this->bundleDate === 'month', fn($q) => $q->where('created_at', '>=', now()->startOfMonth()))
            ->latest();

        $blQuery = SatuSehatBundleLog::query()
            ->with('bundle')
            ->when($this->blSearch, fn($q) => $q->whereHas('bundle', fn($b) => $b->where('no_rawat', 'like', "%{$this->blSearch}%")))
            ->when($this->blResource, fn($q) => $q->where('resource_type', $this->blResource))
            ->when($this->blStatus, fn($q) => $q->where('status', $this->blStatus))
            ->when($this->blDate === 'today', fn($q) => $q->whereDate('created_at', today()))
            ->when($this->blDate === 'week', fn($q) => $q->where('created_at', '>=', now()->startOfWeek()))
            ->when($this->blDate === 'month', fn($q) => $q->where('created_at', '>=', now()->startOfMonth()))
            ->latest();

        return [
            'logs' => $query->paginate($this->perPage),
            'resourceTypes' => SatuSehatLog::getResourceTypes(),
            'actions' => SatuSehatLog::getActions(),
            'bundles' => $bundleQuery->paginate($this->bundlePerPage, ['*'], 'bundlePage'),
            'bundleStats' => [
                'total' => SatuSehatBundle::count(),
                'completed' => SatuSehatBundle::byStatus(SatuSehatBundle::STATUS_COMPLETED)->count(),
                'partial' => SatuSehatBundle::byStatus(SatuSehatBundle::STATUS_PARTIAL)->count(),
                'failed' => SatuSehatBundle::byStatus(SatuSehatBundle::STATUS_FAILED)->count(),
            ],
            'bundleLogs' => $blQuery->paginate($this->blPerPage, ['*'], 'blPage'),
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
    {{-- Header --}}
    <x-ui.page-header title="Log Satu Sehat" subtitle="Riwayat akses API dan pengiriman bundle Satu Sehat">
        <x-slot:actions>
            <x-atoms.button variant="ghost" icon="arrow-path" wire:click="$refresh">Refresh</x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Tab Navigation --}}
    <div
        class="mb-5 overflow-hidden bg-white border rounded-2xl dark:bg-primary-dark-800/60 border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="p-3">
            <div class="flex gap-1 p-1 rounded-xl bg-zinc-100 dark:bg-primary-dark-900/60">
                <button wire:click="$set('activeTab', 'api')"
                    class="flex flex-1 items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all duration-200
                        {{ $activeTab === 'api'
                            ? 'bg-white dark:bg-primary-dark-700 text-primary-700 dark:text-primary-300 shadow-sm ring-1 ring-zinc-200/70 dark:ring-primary-dark-600/50'
                            : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                    <flux:icon name="document-text" class="w-4 h-4" />
                    API Log
                </button>
                <button wire:click="$set('activeTab', 'bundle')"
                    class="flex flex-1 items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all duration-200
                        {{ $activeTab === 'bundle'
                            ? 'bg-white dark:bg-primary-dark-700 text-primary-700 dark:text-primary-300 shadow-sm ring-1 ring-zinc-200/70 dark:ring-primary-dark-600/50'
                            : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                    <flux:icon name="paper-airplane" class="w-4 h-4" />
                    Bundle Job
                    @if ($bundleStats['partial'] + $bundleStats['failed'] > 0)
                        <flux:badge color="red" size="sm">{{ $bundleStats['partial'] + $bundleStats['failed'] }}
                        </flux:badge>
                    @endif
                </button>
                <button wire:click="$set('activeTab', 'bundle-log')"
                    class="flex flex-1 items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all duration-200
                        {{ $activeTab === 'bundle-log'
                            ? 'bg-white dark:bg-primary-dark-700 text-primary-700 dark:text-primary-300 shadow-sm ring-1 ring-zinc-200/70 dark:ring-primary-dark-600/50'
                            : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                    <flux:icon name="queue-list" class="w-4 h-4" />
                    Bundle Log
                    @if ($blStats['failed'] > 0)
                        <flux:badge color="red" size="sm">{{ number_format($blStats['failed']) }}</flux:badge>
                    @endif
                </button>
            </div>
        </div>
    </div>

    {{-- Tab: API Log --}}
    @if ($activeTab === 'api')
        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-5">
            <x-organisms.stat-card title="Total Request"
                value="{{ number_format(App\Models\SatuSehat\SatuSehatLog::count()) }}" icon="document-text"
                color="zinc" />
            <x-organisms.stat-card title="Sukses"
                value="{{ number_format(App\Models\SatuSehat\SatuSehatLog::successful()->count()) }}"
                icon="check-circle" color="emerald" />
            <x-organisms.stat-card title="Gagal"
                value="{{ number_format(App\Models\SatuSehat\SatuSehatLog::failed()->count()) }}" icon="x-circle"
                color="red" />
            <x-organisms.stat-card title="Hari Ini"
                value="{{ number_format(App\Models\SatuSehat\SatuSehatLog::today()->count()) }}" icon="calendar"
                color="blue" />
            <x-organisms.stat-card title="Avg Response"
                value="{{ number_format(App\Models\SatuSehat\SatuSehatLog::avg('response_time') ?? 0, 0) }} ms"
                icon="clock" color="amber" />
        </div>

        {{-- Data Panel --}}
        <x-organisms.data-panel title="Log Sinkronisasi SatuSehat" subtitle="Riwayat sinkronisasi data ke IHS">
            <x-slot:filter>
                <div class="flex items-center gap-3">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari endpoint, IHS, NIK, error..."
                        icon="magnifying-glass" clearable class="flex-1" />
                    <flux:select wire:model.live="filterResource" class="w-44">
                        <flux:select.option value="">Semua Resource</flux:select.option>
                        @foreach ($resourceTypes as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="filterAction" class="w-44">
                        <flux:select.option value="">Semua Aksi</flux:select.option>
                        @foreach ($actions as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="filterStatus" class="w-44">
                        <flux:select.option value="">Semua Status</flux:select.option>
                        <flux:select.option value="success">Sukses</flux:select.option>
                        <flux:select.option value="failed">Gagal</flux:select.option>
                    </flux:select>
                    <flux:select wire:model.live="filterDate" class="w-44">
                        <flux:select.option value="">Semua Waktu</flux:select.option>
                        <flux:select.option value="today">Hari Ini</flux:select.option>
                        <flux:select.option value="week">Minggu Ini</flux:select.option>
                        <flux:select.option value="month">Bulan Ini</flux:select.option>
                    </flux:select>
                </div>
            </x-slot:filter>

            <div class="overflow-x-auto">
                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Waktu</x-atoms.table-heading>
                        <x-atoms.table-heading>Resource</x-atoms.table-heading>
                        <x-atoms.table-heading>Path</x-atoms.table-heading>
                        <x-atoms.table-heading align="center" class="hidden lg:table-cell">Response
                            Time</x-atoms.table-heading>
                        <x-atoms.table-heading class="hidden lg:table-cell">User</x-atoms.table-heading>
                        <x-atoms.table-heading align="center">Status</x-atoms.table-heading>
                        <x-atoms.table-heading align="center">Detail</x-atoms.table-heading>
                    </x-slot:headings>

                    @forelse ($logs as $log)
                        <x-molecules.table-row :key="$log->id">
                            <x-atoms.table-cell :nowrap="true">
                                <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300">
                                    {{ $log->created_at->format('d/m/Y') }}
                                </p>
                                <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500">
                                    {{ $log->created_at->format('H:i:s') }}
                                </p>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell :nowrap="true">
                                <flux:badge color="zinc" size="sm">
                                    {{ $log->resource_type }}
                                </flux:badge>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell :nowrap="true">
                                <div class="flex items-center gap-2">
                                    <x-atoms.method-badge :method="$log->method" />
                                    <div
                                        class="text-xs w-96 truncate font-mono text-zinc-500 dark:text-primary-dark-400">
                                        {{ $log->endpoint }}</div>
                                </div>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="center" :nowrap="true" class="hidden lg:table-cell">
                                <span
                                    class="text-xs font-mono {{ $log->response_time > 1000 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-600 dark:text-primary-dark-400' }}">
                                    {{ number_format($log->response_time ?? 0, 0) }} ms
                                </span>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell :nowrap="true" class="hidden lg:table-cell">
                                @if ($log->user)
                                    <div class="text-xs text-zinc-600 dark:text-primary-dark-300">
                                        {{ $log->user->name }}
                                    </div>
                                @else
                                    <span class="text-xs text-zinc-400">System</span>
                                @endif
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="center" :nowrap="true">
                                <x-atoms.status-badge :status="$log->response_status ?? 'failed'" />
                            </x-atoms.table-cell>
                            <x-atoms.table-cell align="center" :nowrap="true" :action="true">
                                <x-atoms.button variant="ghost" size="sm" icon="eye"
                                    wire:click="showDetail('{{ $log->id }}')" tooltip="Lihat Detail" />
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @empty
                        <x-molecules.table-row>
                            <x-atoms.table-cell colspan="8" class="py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="document-text"
                                        class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada log
                                        ditemukan</p>
                                </div>
                            </x-atoms.table-cell>
                        </x-molecules.table-row>
                    @endforelse

                    @if ($logs->hasPages())
                        <x-slot:footer>
                            <div class="px-4 py-4">
                                {{ $logs->links() }}
                            </div>
                        </x-slot:footer>
                    @endif
                </x-organisms.table>
            </div>
        </x-organisms.data-panel>
    @endif

    {{-- Tab: Bundle Job --}}
    @if ($activeTab === 'bundle')
        <div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-4">
            <x-organisms.stat-card title="Total Bundle" value="{{ number_format($bundleStats['total']) }}"
                icon="paper-airplane" color="zinc" />
            <x-organisms.stat-card title="Selesai" value="{{ number_format($bundleStats['completed']) }}"
                icon="check-circle" color="emerald" />
            <x-organisms.stat-card title="Sebagian Gagal" value="{{ number_format($bundleStats['partial']) }}"
                icon="exclamation-triangle" color="amber" />
            <x-organisms.stat-card title="Gagal" value="{{ number_format($bundleStats['failed']) }}"
                icon="x-circle" color="red" />
        </div>

        {{-- Filter --}}
        <div
            class="mb-4 flex items-center gap-3 bg-white dark:bg-primary-dark-800 p-4 rounded-2xl border border-zinc-200 dark:border-primary-dark-700 shadow-sm">
            <flux:input wire:model.live.debounce.300ms="bundleSearch" placeholder="Cari no. rawat..."
                icon="magnifying-glass" clearable class="flux-1" />
            <flux:select wire:model.live="bundleStatus" class="w-44">
                <flux:select.option value="">Semua Status</flux:select.option>
                <flux:select.option value="queued">Antrian</flux:select.option>
                <flux:select.option value="running">Berjalan</flux:select.option>
                <flux:select.option value="completed">Selesai</flux:select.option>
                <flux:select.option value="partial">Sebagian Gagal</flux:select.option>
                <flux:select.option value="failed">Gagal</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="bundleDate" class="w-44">
                <flux:select.option value="">Semua Waktu</flux:select.option>
                <flux:select.option value="today">Hari Ini</flux:select.option>
                <flux:select.option value="week">Minggu Ini</flux:select.option>
                <flux:select.option value="month">Bulan Ini</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="bundlePerPage" class="w-40 ml-auto">
                <flux:select.option value="10">10 / halaman</flux:select.option>
                <flux:select.option value="25">25 / halaman</flux:select.option>
                <flux:select.option value="50">50 / halaman</flux:select.option>
            </flux:select>
        </div>

        {{-- Polling aktif hanya saat ada bundle yang sedang running --}}
        @if ($bundles->contains(fn($b) => in_array($b->status, ['running', 'queued'])))
            <div wire:poll.3000ms="$refresh" class="hidden"></div>
        @endif

        {{-- Log Feed --}}
        <div class="space-y-3">
            @forelse ($bundles as $bundle)
                @php
                    $hasErrors = $bundle->total_errors > 0;
                    $borderColor = match ($bundle->status) {
                        'completed' => 'border-emerald-200 dark:border-emerald-800/40',
                        'partial' => 'border-amber-200 dark:border-amber-800/40',
                        'failed' => 'border-red-200 dark:border-red-800/40',
                        'running' => 'border-blue-200 dark:border-blue-800/40',
                        default => 'border-zinc-200 dark:border-primary-dark-700',
                    };
                    $headerBg = match ($bundle->status) {
                        'completed' => 'bg-emerald-50/60 dark:bg-emerald-900/10',
                        'partial' => 'bg-amber-50/60 dark:bg-amber-900/10',
                        'failed' => 'bg-red-50/60 dark:bg-red-900/10',
                        'running' => 'bg-blue-50/60 dark:bg-blue-900/10',
                        default => 'bg-zinc-50 dark:bg-primary-dark-800/60',
                    };
                @endphp
                <div wire:key="{{ $bundle->id }}" x-data="{ open: {{ $hasErrors || in_array($bundle->status, ['running', 'queued', 'failed']) ? 'true' : 'false' }} }"
                    class="rounded-xl border overflow-hidden {{ $borderColor }}">

                    {{-- Bundle Header --}}
                    <button @click="open = !open"
                        class="w-full flex items-center gap-3 px-4 py-3 text-left {{ $headerBg }} transition-colors">
                        <flux:icon name="chevron-down"
                            class="w-4 h-4 text-zinc-400 shrink-0 transition-transform duration-200"
                            ::class="open ? 'rotate-180' : ''" />
                        <span
                            class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400 shrink-0">{{ $bundle->no_rawat }}</span>
                        <flux:badge color="{{ $bundle->status_color }}" size="sm">{{ $bundle->status_label }}
                        </flux:badge>
                        <span class="text-xs text-zinc-500 dark:text-primary-dark-400 shrink-0">
                            {{ $bundle->created_at->format('d M Y, H:i:s') }}
                        </span>
                        <div class="flex items-center gap-3 ml-auto text-xs shrink-0">
                            <span
                                class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ $bundle->total_sent }}
                                terkirim</span>
                            @if ($bundle->total_errors > 0)
                                <span class="text-red-500 dark:text-red-400 font-semibold">{{ $bundle->total_errors }}
                                    error</span>
                            @endif
                            @if ($bundle->duration)
                                <span
                                    class="font-mono text-zinc-400 dark:text-primary-dark-500">{{ $bundle->duration }}</span>
                            @endif
                            <span
                                class="text-zinc-400 dark:text-primary-dark-500 hidden sm:inline">{{ $bundle->triggeredBy?->name ?? 'System' }}</span>
                        </div>
                    </button>

                    {{-- Log Console --}}
                    <div x-show="open" x-cloak
                        class="bg-zinc-950 dark:bg-black p-4 font-mono text-xs space-y-0.5 overflow-auto max-h-[500px]">

                        {{-- Header comment --}}
                        <div class="text-zinc-500 mb-2">
                            # Bundle {{ $bundle->no_rawat }}
                            · started
                            {{ $logStart = $bundle->started_at?->format('Y-m-d H:i:s') ?? $bundle->created_at->format('Y-m-d H:i:s') }}
                            · triggered by {{ $bundle->triggeredBy?->name ?? 'System' }}
                        </div>

                        @foreach ($bundle->items as $item)
                            @php
                                $tag = match ($item->status) {
                                    'success' => ' OK ',
                                    'failed' => 'FAIL',
                                    'warning' => 'WARN',
                                    'skipped' => 'SKIP',
                                    'sending' => 'WAIT',
                                    default => 'LOG ',
                                };
                                $tagClass = match ($item->status) {
                                    'success' => 'text-emerald-400',
                                    'failed' => 'text-red-400',
                                    'warning' => 'text-amber-400',
                                    'skipped' => 'text-zinc-500',
                                    'sending' => 'text-blue-400',
                                    default => 'text-zinc-400',
                                };
                                $rowClass = match ($item->status) {
                                    'success' => 'text-zinc-300',
                                    'failed' => 'text-red-300',
                                    'warning' => 'text-amber-200',
                                    'skipped' => 'text-zinc-500',
                                    default => 'text-zinc-400',
                                };
                                $itemTime = $item->created_at->format('H:i:s');
                            @endphp
                            <div class="flex items-start gap-2 leading-5">
                                <span class="text-zinc-600 shrink-0 w-20">{{ $itemTime }}</span>
                                <span
                                    class="font-bold {{ $tagClass }} shrink-0 w-10">[{{ $tag }}]</span>
                                <span class="{{ $rowClass }} flex-1">
                                    {{ str_pad($item->resource_type, 20) }}
                                    @if ($item->local_id)
                                        <span class="text-zinc-500 text-[10px]">#{{ $item->local_id }}</span>
                                    @endif
                                    <span class="text-zinc-500">→</span>
                                    @if ($item->ihs_id)
                                        <span class="text-emerald-500/80">{{ $item->ihs_id }}</span>
                                    @elseif($item->error_message)
                                        <span class="text-red-500/70 italic">{{ $item->error_message }}</span>
                                    @else
                                        <span class="text-zinc-600">Processed</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach

                        {{-- Summary --}}
                        <div class="text-zinc-600 pt-2 mt-2 border-t border-zinc-800">
                            # Summary: {{ $bundle->total_sent }} resources sent · {{ $bundle->total_errors }} errors
                            · {{ $bundle->duration ?? 'N/A' }} · {{ $bundle->status_label }}
                            @if ($bundle->encounter_finished)
                                · Encounter Finished
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div
                    class="flex flex-col items-center py-16 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200 dark:border-primary-dark-700">
                    <flux:icon name="paper-airplane" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                    <p class="mt-3 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada bundle job ditemukan</p>
                </div>
            @endforelse
        </div>

        @if ($bundles->hasPages())
            <div class="mt-4">{{ $bundles->links() }}</div>
        @endif
    @endif

    {{-- Tab: Bundle Log (audit trail) --}}
    @if ($activeTab === 'bundle-log')
        {{-- Stats --}}
        <div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-4">
            <x-organisms.stat-card title="Total Aksi" value="{{ number_format($blStats['total']) }}"
                icon="queue-list" color="zinc" />
            <x-organisms.stat-card title="Terkirim" value="{{ number_format($blStats['success']) }}"
                icon="check-circle" color="emerald" />
            <x-organisms.stat-card title="Gagal" value="{{ number_format($blStats['failed']) }}" icon="x-circle"
                color="red" />
            <x-organisms.stat-card title="Dilewati" value="{{ number_format($blStats['skipped']) }}"
                icon="minus-circle" color="zinc" />
        </div>

        {{-- Filter --}}
        <div
            class="mb-4 flex flex-wrap items-center gap-3 bg-white dark:bg-primary-dark-800 p-4 rounded-2xl border border-zinc-200 dark:border-primary-dark-700 shadow-sm">
            <flux:input wire:model.live.debounce.300ms="blSearch" placeholder="Cari no. rawat..."
                icon="magnifying-glass" clearable class="flex-1 min-w-40" />
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
        <x-organisms.data-panel title="Riwayat Aksi Bundle"
            subtitle="Audit trail setiap aksi pengiriman per-resource">
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
    @endif

    {{-- Detail Modal (API Log) --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="4xl" title="">
        @if ($selectedLog)
            <div class="space-y-6">
                {{-- Header --}}
                <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div
                        class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-lg {{ $selectedLog->is_success ? 'bg-green-100 dark:bg-green-900/50' : 'bg-red-100 dark:bg-red-900/50' }}">
                        <flux:icon name="{{ $selectedLog->is_success ? 'check-circle' : 'x-circle' }}"
                            class="w-6 h-6 {{ $selectedLog->is_success ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ $selectedLog->resource_type }} - {{ $selectedLog->action_label }}
                        </h2>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <x-atoms.method-badge :method="$selectedLog->method" />
                            <x-atoms.status-badge :status="$selectedLog->response_status ?? 'failed'" />
                            <span class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                {{ $selectedLog->created_at->format('d M Y H:i:s') }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Info Grid --}}
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">Endpoint
                        </h4>
                        <p class="mt-1 font-mono text-sm break-all text-zinc-900 dark:text-primary-dark-100">
                            {{ $selectedLog->endpoint }}
                        </p>
                    </div>
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">Response
                            Time</h4>
                        <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ number_format($selectedLog->response_time ?? 0, 2) }} <span
                                class="text-sm font-normal">ms</span>
                        </p>
                    </div>
                    @if ($selectedLog->ihs_number)
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">IHS
                                Number
                            </h4>
                            <p class="mt-1 font-mono text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedLog->ihs_number }}
                            </p>
                        </div>
                    @endif
                    @if ($selectedLog->patient_nik)
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                                Patient NIK
                            </h4>
                            <p class="mt-1 font-mono text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedLog->patient_nik }}
                            </p>
                        </div>
                    @endif
                    @if ($selectedLog->user)
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">User
                            </h4>
                            <p class="mt-1 text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedLog->user->name }}
                            </p>
                        </div>
                    @endif
                    @if ($selectedLog->ip_address)
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">IP
                                Address
                            </h4>
                            <p class="mt-1 font-mono text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedLog->ip_address }}
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Error Message --}}
                @if ($selectedLog->error_message)
                    <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                        <h4 class="text-xs font-semibold uppercase text-red-600 dark:text-red-400">Error Message</h4>
                        <p class="mt-1 text-sm text-red-800 dark:text-red-200">
                            {{ $selectedLog->error_message }}
                        </p>
                    </div>
                @endif

                {{-- Request/Response --}}
                @if ($selectedLog->request_params || $selectedLog->request_body)
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                            Request</h4>
                        <x-atoms.code-block language="json"
                            maxHeight="max-h-64">{{ json_encode($selectedLog->request_params ?? $selectedLog->request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</x-atoms.code-block>
                    </div>
                @endif
                @if ($selectedLog->response_body)
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                            Response</h4>
                        <x-atoms.code-block language="json"
                            maxHeight="max-h-64">{{ json_encode($selectedLog->response_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</x-atoms.code-block>
                    </div>
                @endif

                {{-- Actions --}}
                <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                    <x-atoms.button variant="ghost" wire:click="closeDetail">
                        Tutup
                    </x-atoms.button>
                </div>
            </div>
        @endif
    </x-organisms.modal>

    {{-- Modal: Bundle Log Detail --}}
    <x-organisms.modal wire:model="showBundleLogModal" maxWidth="4xl" title="">
        @if ($selectedBundleLog)
            <div class="space-y-5">
                {{-- Header --}}
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

                {{-- Info Grid --}}
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
                            <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">Local
                                ID</p>
                            <p class="mt-1 font-mono text-sm text-zinc-700 dark:text-primary-dark-200">
                                {{ $selectedBundleLog->local_id }}
                            </p>
                        </div>
                    @endif
                    @if ($selectedBundleLog->ihs_id)
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">IHS ID
                            </p>
                            <p class="mt-1 font-mono text-sm text-emerald-600 dark:text-emerald-400">
                                {{ $selectedBundleLog->ihs_id }}
                            </p>
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
