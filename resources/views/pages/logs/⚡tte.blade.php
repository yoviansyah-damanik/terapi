<?php

use App\Models\Api\ApiLog;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Log TTE')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterMethod = '';

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $filterDate = '';

    public ?string $viewLogId = null;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterMethod(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }
    public function updatingFilterDate(): void { $this->resetPage(); }

    public function openDetail(string $id): void
    {
        $this->viewLogId = $id;
        $this->dispatch('open-tte-log-detail');
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterMethod = '';
        $this->filterStatus = '';
        $this->filterDate = '';
        $this->resetPage();
    }

    public function with(): array
    {
        $query = ApiLog::forScope('tte')
            ->when($this->search, fn($q) => $q->search($this->search))
            ->when($this->filterMethod, fn($q) => $q->forMethod($this->filterMethod))
            ->when($this->filterStatus, fn($q) => $q->forStatus($this->filterStatus))
            ->when($this->filterDate, fn($q) => $q->whereDate('created_at', $this->filterDate))
            ->orderByDesc('created_at');

        $stats = ApiLog::forScope('tte')
            ->selectRaw('COUNT(*) as total, SUM(response_status BETWEEN 200 AND 299) as success, SUM(response_status NOT BETWEEN 200 AND 299) as failed, SUM(DATE(created_at) = CURDATE()) as today')
            ->first();

        return [
            'logs' => $query->paginate(25),
            'stats' => $stats,
            'viewLog' => $this->viewLogId ? ApiLog::find($this->viewLogId) : null,
        ];
    }
};
?>

<div @open-tte-log-detail.window="$flux.modal('tte-log-detail').show()">
    <x-ui.page-header title="Log TTE" subtitle="Riwayat aktivitas Tanda Tangan Elektronik via API">
        <x-slot:actions>
            <x-atoms.button variant="ghost" icon="arrow-path" wire:click="$refresh">Refresh</x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-4">
        <x-organisms.stat-card title="Total" value="{{ number_format($stats->total ?? 0) }}" icon="queue-list" color="zinc" />
        <x-organisms.stat-card title="Sukses" value="{{ number_format($stats->success ?? 0) }}" icon="check-circle" color="emerald" />
        <x-organisms.stat-card title="Gagal" value="{{ number_format($stats->failed ?? 0) }}" icon="x-circle" color="red" />
        <x-organisms.stat-card title="Hari Ini" value="{{ number_format($stats->today ?? 0) }}" icon="calendar-days" color="blue" />
    </div>

    {{-- Data Panel --}}
    <x-organisms.data-panel title="Log Aktivitas TTE" subtitle="Riwayat request API Tanda Tangan Elektronik">
        <x-slot:filter>
            <div class="flex flex-wrap gap-3">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari path, user API, IP…"
                    icon="magnifying-glass" clearable class="flex-1 min-w-48" />
                <flux:select wire:model.live="filterMethod" class="w-36">
                    <flux:select.option value="">Semua Method</flux:select.option>
                    <flux:select.option value="GET">GET</flux:select.option>
                    <flux:select.option value="POST">POST</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="filterStatus" class="w-40">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="2xx">2xx Sukses</flux:select.option>
                    <flux:select.option value="4xx">4xx Gagal</flux:select.option>
                    <flux:select.option value="5xx">5xx Error</flux:select.option>
                </flux:select>
                <flux:input wire:model.live="filterDate" type="date" class="w-44!" />
                @if ($search || $filterMethod || $filterStatus || $filterDate)
                    <x-atoms.button wire:click="resetFilters" variant="ghost" icon="x-mark" size="sm">Reset</x-atoms.button>
                @endif
            </div>
        </x-slot:filter>

        <div class="overflow-x-auto">
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="w-36">Waktu</x-atoms.table-heading>
                    <x-atoms.table-heading>Path</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden md:table-cell">User API</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden lg:table-cell w-32">IP</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden md:table-cell" align="center">Resp. Time</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-24" align="center">Status</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-16" align="center">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($logs as $log)
                    <x-molecules.table-row :key="$log->id"
                        class="{{ ($log->response_status ?? 0) >= 400 ? 'bg-red-50/30 dark:bg-red-950/10' : '' }}">
                        <x-atoms.table-cell :nowrap="true">
                            <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300">
                                {{ $log->created_at?->format('d/m/Y') }}</p>
                            <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500">
                                {{ $log->created_at?->format('H:i:s') }}</p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="max-w-xs">
                            <div class="flex items-center gap-2">
                                <x-atoms.method-badge :method="$log->method" />
                                <span class="font-mono text-xs text-zinc-700 dark:text-primary-dark-300 truncate block"
                                    title="{{ $log->path }}">{{ $log->path }}</span>
                            </div>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden md:table-cell">
                            <span class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $log->api_user_name ?? '—' }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden lg:table-cell">
                            <span class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400">{{ $log->ip_address ?? '—' }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" class="hidden md:table-cell">
                            @if ($log->response_time_ms)
                                @php
                                    $ms = $log->response_time_ms;
                                    $msColor = $ms > 5000
                                        ? 'text-red-600 dark:text-red-400'
                                        : ($ms > 2000 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400');
                                @endphp
                                <span class="font-mono text-xs {{ $msColor }}">{{ number_format($ms) }} ms</span>
                            @else
                                <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" :nowrap="true">
                            <x-atoms.status-badge :status="$log->response_status" />
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :action="true" align="center">
                            <x-atoms.button variant="ghost" size="sm" icon="eye" tooltip="Lihat Detail"
                                wire:click="openDetail('{{ $log->id }}')" />
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="7" class="py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                    <flux:icon name="queue-list" class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                </div>
                                <p class="text-sm text-zinc-400 dark:text-primary-dark-500">
                                    @if ($search || $filterMethod || $filterStatus || $filterDate)
                                        Tidak ada log yang sesuai filter.
                                    @else
                                        Belum ada log TTE.
                                    @endif
                                </p>
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse

                @if ($logs->hasPages())
                    <x-slot:footer>
                        <div class="px-5 py-4">{{ $logs->links() }}</div>
                    </x-slot:footer>
                @endif
            </x-organisms.table>
        </div>
    </x-organisms.data-panel>

    {{-- Modal Detail --}}
    <x-organisms.modal name="tte-log-detail" @close="$wire.set('viewLogId', null)" maxWidth="3xl" title="">
        @if ($viewLog)
            @php
                $rawTabs = [
                    'response' => [
                        'label' => 'Response',
                        'data' => is_string($viewLog->response_body)
                            ? json_decode($viewLog->response_body, true) ?? $viewLog->response_body
                            : $viewLog->response_body,
                    ],
                    'request' => [
                        'label' => 'Request',
                        'data' => is_string($viewLog->request_body)
                            ? json_decode($viewLog->request_body, true) ?? $viewLog->request_body
                            : $viewLog->request_body,
                    ],
                ];
                $tabs = array_filter($rawTabs, fn($t) => !empty($t['data']));
                $tabOptions = array_map(fn($t) => $t['label'], $tabs);
            @endphp
            <div class="space-y-4" x-data="{ tab: '{{ array_key_first($tabs) ?? 'response' }}' }">
                {{-- Header --}}
                <div class="flex items-start gap-3 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <x-atoms.status-badge :status="$viewLog->response_status" />
                            <x-atoms.method-badge :method="$viewLog->method" />
                            <span class="font-mono text-sm font-semibold text-zinc-700 dark:text-primary-dark-300 truncate">
                                {{ $viewLog->path }}</span>
                        </div>
                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                            {{ $viewLog->created_at?->format('d/m/Y H:i:s') }}</p>
                    </div>
                </div>

                {{-- Info Grid --}}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @php
                        $infoRows = array_filter([
                            ['label' => 'User API', 'value' => $viewLog->api_user_name],
                            ['label' => 'IP Address', 'value' => $viewLog->ip_address],
                            ['label' => 'Query String', 'value' => $viewLog->query_string],
                            ['label' => 'Response Time', 'value' => $viewLog->response_time_ms ? number_format($viewLog->response_time_ms) . ' ms' : null],
                        ], fn($r) => !is_null($r['value']));
                    @endphp
                    @foreach ($infoRows as $row)
                        <div class="flex flex-col gap-0.5 p-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">{{ $row['label'] }}</span>
                            <span class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300 font-mono break-all">{{ $row['value'] }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Payload Tabs --}}
                @if (count($tabOptions) > 1)
                    <x-molecules.tabs-2 model="tab" :options="$tabOptions" />
                @endif

                @foreach ($tabs as $tabKey => $tab)
                    <div x-show="tab === '{{ $tabKey }}'">
                        <x-atoms.code-block language="json" maxHeight="max-h-64">{{ json_encode($tab['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </div>
                @endforeach

                @if (empty($tabs))
                    <p class="text-sm text-center text-zinc-400 dark:text-primary-dark-500 py-6">Tidak ada payload untuk log ini.</p>
                @endif

                <div class="flex justify-end pt-2">
                    <x-atoms.button size="sm" variant="ghost" icon="x-mark"
                        x-on:click="$flux.modal('tte-log-detail').close()">Tutup</x-atoms.button>
                </div>
            </div>
        @endif
    </x-organisms.modal>
</div>
