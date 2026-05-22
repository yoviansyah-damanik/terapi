<?php

use App\Models\Api\ApiLog;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Log DICOM')] class extends Component {
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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    public function updatingFilterMethod(): void
    {
        $this->resetPage();
    }
    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }
    public function updatingFilterDate(): void
    {
        $this->resetPage();
    }

    public function openDetail(string $id): void
    {
        $this->viewLogId = $id;
        $this->dispatch('open-log-detail');
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
        $query = ApiLog::where(function ($q) {
            $q->where('scope', 'dicom')->orWhere('path', 'like', '%dicom%');
        })->orderByDesc('created_at');

        if ($this->search) {
            $query->search($this->search);
        }

        if ($this->filterMethod) {
            $query->forMethod($this->filterMethod);
        }

        if ($this->filterStatus) {
            $query->forStatus($this->filterStatus);
        }

        if ($this->filterDate) {
            $query->whereDate('created_at', $this->filterDate);
        }

        $stats = ApiLog::where(function ($q) {
            $q->where('scope', 'dicom')->orWhere('path', 'like', '%dicom%');
        })
            ->selectRaw(
                "
                COUNT(*) as total,
                SUM(response_status >= 500) as errors,
                SUM(response_status >= 400 AND response_status < 500) as warnings,
                SUM(response_status >= 200 AND response_status < 300) as success,
                SUM(DATE(created_at) = CURDATE()) as today
            ",
            )
            ->first();

        $viewLog = $this->viewLogId ? ApiLog::find($this->viewLogId) : null;

        return [
            'logs' => $query->paginate(25),
            'stats' => $stats,
            'viewLog' => $viewLog,
        ];
    }
}; ?>

<div @open-log-detail.window="$flux.modal('dicom-log-detail').show()">
    <x-ui.page-header title="Log DICOM" subtitle="Riwayat request API dan webhook terkait integrasi DICOM">
        <x-slot:actions>
            <x-atoms.button variant="ghost" icon="arrow-path" wire:click="$refresh">Refresh</x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-3 lg:grid-cols-5">
        @php
            $statCards = [
                ['label' => 'Total', 'value' => $stats->total ?? 0, 'color' => 'zinc', 'icon' => 'queue-list'],
                ['label' => 'Error (5xx)', 'value' => $stats->errors ?? 0, 'color' => 'red', 'icon' => 'x-circle'],
                [
                    'label' => 'Warning (4xx)',
                    'value' => $stats->warnings ?? 0,
                    'color' => 'amber',
                    'icon' => 'exclamation-triangle',
                ],
                [
                    'label' => 'Success (2xx)',
                    'value' => $stats->success ?? 0,
                    'color' => 'emerald',
                    'icon' => 'check-circle',
                ],
                ['label' => 'Hari Ini', 'value' => $stats->today ?? 0, 'color' => 'blue', 'icon' => 'calendar-days'],
            ];
        @endphp
        @foreach ($statCards as $card)
            <x-organisms.stat-card title="{{ current(explode(' ', trim($card['label']))) }}"
                value="{{ number_format($card['value']) }}" icon="{{ $card['icon'] }}" color="{{ $card['color'] }}"
                subtitle="{{ $card['label'] }}" />
        @endforeach
    </div>

    {{-- Data Panel --}}
    <x-organisms.data-panel title="Riwayat API DICOM" subtitle="Total: {{ number_format($logs->total()) }} entri">
        <x-slot:filter>
            <div class="flex items-center gap-3">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari path, IP, user..."
                    icon="magnifying-glass" clearable class="flex-1" />
                <flux:select wire:model.live="filterMethod" class="w-36">
                    <flux:select.option value="">Semua Method</flux:select.option>
                    <flux:select.option value="GET">GET</flux:select.option>
                    <flux:select.option value="POST">POST</flux:select.option>
                    <flux:select.option value="PUT">PUT</flux:select.option>
                    <flux:select.option value="DELETE">DELETE</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="filterStatus" class="w-40">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="2xx">Success (2xx)</flux:select.option>
                    <flux:select.option value="4xx">Client Error (4xx)</flux:select.option>
                    <flux:select.option value="5xx">Server Error (5xx)</flux:select.option>
                </flux:select>
                <flux:input wire:model.live="filterDate" type="date" class="!w-44" />
                @if ($search || $filterMethod || $filterStatus || $filterDate)
                    <x-atoms.button wire:click="resetFilters" variant="ghost" icon="x-mark">
                        Reset
                    </x-atoms.button>
                @endif
            </div>
        </x-slot:filter>

        <div class="overflow-x-auto">
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="w-36">Waktu</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-24" align="center">Method</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-24" align="center">Status</x-atoms.table-heading>
                    <x-atoms.table-heading>Path Endpoint</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-36 hidden md:table-cell">User API</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-32 hidden lg:table-cell">IP Address</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-24 hidden xl:table-cell" align="right">Waktu
                        (ms)</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-16" align="center">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($logs as $log)
                    <x-molecules.table-row :key="$log->id"
                        class="{{ $log->response_status >= 500 ? 'bg-red-50/30 dark:bg-red-950/10' : ($log->response_status >= 400 ? 'bg-amber-50/30 dark:bg-amber-950/10' : '') }}">
                        <x-atoms.table-cell :nowrap="true">
                            <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300">
                                {{ $log->created_at?->format('d/m/Y') }}
                            </p>
                            <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500">
                                {{ $log->created_at?->format('H:i:s') }}
                            </p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" :nowrap="true">
                            <span
                                class="font-mono text-xs font-bold {{ $log->method === 'POST' ? 'text-amber-600 dark:text-amber-400' : ($log->method === 'GET' ? 'text-emerald-600 dark:text-emerald-400' : 'text-blue-600 dark:text-blue-400') }}">
                                {{ $log->method }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" :nowrap="true">
                            <x-atoms.status-badge :status="$log->response_status" :color="$log->status_color" />
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <p class="font-mono text-xs text-zinc-700 dark:text-primary-dark-300 truncate max-w-sm"
                                title="{{ $log->path }}">
                                {{ $log->path }}
                            </p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden md:table-cell">
                            <span class="text-xs text-zinc-600 dark:text-primary-dark-400">
                                {{ $log->api_user_name ?? 'Webhook / Guest' }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden lg:table-cell">
                            <span class="font-mono text-[11px] text-zinc-500 dark:text-primary-dark-400">
                                {{ $log->ip_address ?? '—' }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden xl:table-cell" align="right">
                            <span class="font-mono text-[11px] text-zinc-500 dark:text-primary-dark-400">
                                {{ number_format($log->response_time_ms) }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :action="true" align="center">
                            <x-atoms.button variant="ghost" size="sm" icon="eye" tooltip="Lihat Detail"
                                wire:click="openDetail('{{ $log->id }}')" />
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="8" class="py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div
                                    class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                    <flux:icon name="queue-list"
                                        class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                </div>
                                <p class="text-sm text-zinc-400 dark:text-primary-dark-500">
                                    @if ($search || $filterMethod || $filterStatus || $filterDate)
                                        Tidak ada log DICOM yang sesuai filter.
                                    @else
                                        Belum ada log request API DICOM.
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

    {{-- Modal Detail Log --}}
    <x-organisms.modal name="dicom-log-detail" @close="$wire.set('viewLogId', null)" maxWidth="5xl" title="">
        @if ($viewLog)
            <div class="space-y-4" x-data="{ tab: 'info' }">
                {{-- Header --}}
                <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-2">
                            <span
                                class="font-mono text-sm font-bold {{ $viewLog->method === 'POST' ? 'text-amber-600 dark:text-amber-400' : ($viewLog->method === 'GET' ? 'text-emerald-600 dark:text-emerald-400' : 'text-blue-600 dark:text-blue-400') }}">
                                {{ $viewLog->method }}
                            </span>
                            <span class="font-mono text-sm text-zinc-700 dark:text-primary-dark-300 break-all">
                                {{ $viewLog->path }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-atoms.status-badge :status="$viewLog->response_status" :color="$viewLog->status_color" />
                            <span class="text-xs text-zinc-400 dark:text-primary-dark-500">
                                {{ $viewLog->created_at?->format('d M Y, H:i:s') }}
                            </span>
                            <span class="text-xs text-zinc-400 dark:text-primary-dark-500">
                                &bull; {{ number_format($viewLog->response_time_ms) }} ms
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Tab selector --}}
                <x-molecules.tabs-2 model="tab" :options="['info' => 'Informasi', 'request' => 'Request', 'response' => 'Response']" />

                {{-- Tab: Info --}}
                <div x-show="tab === 'info'" class="space-y-4">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @php
                            $infoRows = [
                                ['label' => 'User API', 'value' => $viewLog->api_user_name ?? 'Webhook / Guest'],
                                ['label' => 'IP Address', 'value' => $viewLog->ip_address ?? '—'],
                                ['label' => 'Scope', 'value' => $viewLog->scope ?? '—'],
                                ['label' => 'User Agent', 'value' => $viewLog->user_agent ?? '—'],
                            ];
                        @endphp

                        @foreach ($infoRows as $row)
                            <div class="flex flex-col gap-0.5 p-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                                <span
                                    class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                    {{ $row['label'] }}
                                </span>
                                <span class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300 break-words">
                                    {{ $row['value'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    @if ($viewLog->query_string)
                        <div class="p-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                            <span
                                class="block text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500 mb-1">
                                Query String
                            </span>
                            <span class="font-mono text-sm text-zinc-700 dark:text-primary-dark-300 break-words">
                                ?{{ $viewLog->query_string }}
                            </span>
                        </div>
                    @endif
                </div>

                {{-- Tab: Request --}}
                <div x-show="tab === 'request'" class="space-y-4" x-data="{ copied: false }">
                    @if (!empty($viewLog->request_headers))
                        <div>
                            <span
                                class="block text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500 mb-2">Request
                                Headers</span>
                            <x-atoms.code-block language="json"
                                maxHeight="max-h-48">{{ json_encode($viewLog->request_headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</x-atoms.code-block>
                        </div>
                    @endif

                    <div>
                        <span
                            class="block text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500 mb-2">Request
                            Body</span>
                        <x-atoms.code-block language="json"
                            maxHeight="max-h-96">{{ is_array($viewLog->request_body) ? json_encode($viewLog->request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ($viewLog->request_body ?: 'No Request Body') }}</x-atoms.code-block>
                    </div>
                </div>

                {{-- Tab: Response --}}
                <div x-show="tab === 'response'" class="space-y-4">
                    <div>
                        <span
                            class="block text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500 mb-2">Response
                            Body</span>
                        <x-atoms.code-block language="json"
                            maxHeight="max-h-96">{{ is_array($viewLog->response_body) ? json_encode($viewLog->response_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ($viewLog->response_body ?: 'No Response Body') }}</x-atoms.code-block>
                    </div>
                </div>

                <div class="flex justify-end mt-4">
                    <x-atoms.button size="sm" variant="ghost"
                        x-on:click="$flux.modal('dicom-log-detail').close()">
                        Tutup
                    </x-atoms.button>
                </div>
            </div>
        @endif

    </x-organisms.modal>
</div>
