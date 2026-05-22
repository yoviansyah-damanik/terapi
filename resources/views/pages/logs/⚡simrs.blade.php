<?php

use App\Models\Api\ApiSimrsLog;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Log SIMRS')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterLevel = '';

    #[Url]
    public string $filterCategory = '';

    #[Url]
    public string $filterModule = '';

    #[Url]
    public string $filterDate = '';

    public ?string $viewLogId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    public function updatingFilterLevel(): void
    {
        $this->resetPage();
    }
    public function updatingFilterCategory(): void
    {
        $this->resetPage();
    }
    public function updatingFilterModule(): void
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
        $this->filterLevel = '';
        $this->filterCategory = '';
        $this->filterModule = '';
        $this->filterDate = '';
        $this->resetPage();
    }

    public function with(): array
    {
        $query = ApiSimrsLog::orderByDesc('created_at');

        if ($this->search) {
            $query->search($this->search);
        }

        if ($this->filterLevel) {
            $query->forLevel($this->filterLevel);
        }

        if ($this->filterCategory) {
            $query->forCategory($this->filterCategory);
        }

        if ($this->filterModule) {
            $query->forModule($this->filterModule);
        }

        if ($this->filterDate) {
            $query->whereDate('created_at', $this->filterDate);
        }

        $stats = ApiSimrsLog::selectRaw(
            "
            COUNT(*) as total,
            SUM(level = 'error') as errors,
            SUM(level = 'warning') as warnings,
            SUM(level = 'info') as infos,
            SUM(level = 'debug') as debugs,
            SUM(DATE(created_at) = CURDATE()) as today
        ",
        )->first();

        $categories = ApiSimrsLog::distinct()->orderBy('category')->pluck('category')->filter();
        $modules = ApiSimrsLog::distinct()->orderBy('module')->pluck('module')->filter();

        $viewLog = $this->viewLogId ? ApiSimrsLog::find($this->viewLogId) : null;

        return [
            'logs' => $query->paginate(25),
            'stats' => $stats,
            'categories' => $categories,
            'modules' => $modules,
            'viewLog' => $viewLog,
        ];
    }
}; ?>

<div @open-log-detail.window="$flux.modal('simrs-log-detail').show()">
    <x-ui.page-header title="Log SIMRS" subtitle="Riwayat error dan aktivitas yang dikirim dari aplikasi SIMRS">
        <x-slot:actions>
            <x-atoms.button variant="ghost" icon="arrow-path" wire:click="$refresh">Refresh</x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-3 lg:grid-cols-6">
        @php
            $statCards = [
                ['label' => 'Total', 'value' => $stats->total ?? 0, 'color' => 'zinc', 'icon' => 'queue-list'],
                ['label' => 'Error', 'value' => $stats->errors ?? 0, 'color' => 'red', 'icon' => 'x-circle'],
                [
                    'label' => 'Warning',
                    'value' => $stats->warnings ?? 0,
                    'color' => 'amber',
                    'icon' => 'exclamation-triangle',
                ],
                ['label' => 'Info', 'value' => $stats->infos ?? 0, 'color' => 'blue', 'icon' => 'information-circle'],
                ['label' => 'Debug', 'value' => $stats->debugs ?? 0, 'color' => 'violet', 'icon' => 'bug-ant'],
                ['label' => 'Hari Ini', 'value' => $stats->today ?? 0, 'color' => 'emerald', 'icon' => 'calendar-days'],
            ];
        @endphp
        @foreach ($statCards as $card)
            <x-organisms.stat-card title="{{ current(explode(' ', trim($card['label']))) }}"
                value="{{ number_format($card['value']) }}" icon="{{ $card['icon'] }}" color="{{ $card['color'] }}"
                subtitle="{{ $card['label'] }}" />
        @endforeach
    </div>

    {{-- Data Panel --}}
    <x-organisms.data-panel title="Log Sistem SIMRS" subtitle="Total: {{ number_format($logs->total()) }} entri">
        <x-slot:filter>
            <div class="flex items-center gap-3">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari pesan, exception, user SIMRS…"
                    icon="magnifying-glass" clearable class="flex-1" />
                <flux:select wire:model.live="filterLevel" class="w-36">
                    <flux:select.option value="">Semua Level</flux:select.option>
                    <flux:select.option value="error">Error</flux:select.option>
                    <flux:select.option value="warning">Warning</flux:select.option>
                    <flux:select.option value="info">Info</flux:select.option>
                    <flux:select.option value="debug">Debug</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="filterCategory" class="w-52">
                    <flux:select.option value="">Semua Kategori</flux:select.option>
                    @foreach ($categories as $cat)
                        <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterModule" class="w-44">
                    <flux:select.option value="">Semua Modul</flux:select.option>
                    @foreach ($modules as $mod)
                        <flux:select.option value="{{ $mod }}">{{ str_replace('_', ' ', ucfirst($mod)) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model.live="filterDate" type="date" class="!w-44" />
                @if ($search || $filterLevel || $filterCategory || $filterModule || $filterDate)
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
                    <x-atoms.table-heading class="w-24" align="center">Level</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-36 hidden lg:table-cell">Error ID</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-48">Kategori</x-atoms.table-heading>
                    <x-atoms.table-heading>Pesan</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-28 hidden xl:table-cell">User SIMRS</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-32 hidden 2xl:table-cell">IP / Host</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-16" align="center">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($logs as $log)
                    <x-molecules.table-row :key="$log->id"
                        class="{{ $log->level === 'error' ? 'bg-red-50/30 dark:bg-red-950/10' : '' }}">
                        <x-atoms.table-cell :nowrap="true">
                            <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300">
                                {{ $log->created_at?->format('d/m/Y') }}
                            </p>
                            <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500">
                                {{ $log->created_at?->format('H:i:s') }}
                            </p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" :nowrap="true">
                            <x-atoms.status-badge :status="$log->level" />
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden lg:table-cell">
                            @if ($log->error_id)
                                <span
                                    class="font-mono text-[11px] text-zinc-500 dark:text-primary-dark-400 truncate block max-w-[8rem]"
                                    title="{{ $log->error_id }}">{{ $log->error_id }}</span>
                            @else
                                <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <span class="font-mono text-xs text-zinc-700 dark:text-primary-dark-300">
                                {{ $log->category ?? '—' }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="max-w-xs">
                            @if ($log->module)
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300">
                                    {{ str_replace('_', ' ', ucfirst($log->module)) }}
                                </span>
                            @else
                                <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                            @endif
                            <p class="text-xs text-zinc-700 dark:text-primary-dark-300 truncate"
                                title="{{ $log->message }}">{{ $log->message }}</p>
                            @if ($log->exception_class)
                                <p
                                    class="text-[11px] text-zinc-400 dark:text-primary-dark-500 font-mono truncate mt-0.5">
                                    {{ $log->exception_class }}
                                </p>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden xl:table-cell">
                            <span class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                {{ $log->simrs_user ?? '—' }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden 2xl:table-cell">
                            <p class="font-mono text-[11px] text-zinc-500 dark:text-primary-dark-400">
                                {{ $log->ip_address ?? '—' }}
                            </p>
                            @if ($log->host_name)
                                <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500 truncate">
                                    {{ $log->host_name }}
                                </p>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :action="true" align="center">
                            <x-atoms.button variant="ghost" size="sm" icon="eye" tooltip="Lihat Detail"
                                wire:click="openDetail('{{ $log->id }}')" />
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="9" class="py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div
                                    class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                    <flux:icon name="queue-list"
                                        class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                </div>
                                <p class="text-sm text-zinc-400 dark:text-primary-dark-500">
                                    @if ($search || $filterLevel || $filterCategory || $filterModule || $filterDate)
                                        Tidak ada log yang sesuai filter.
                                    @else
                                        Belum ada log dari SIMRS.
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
    <x-organisms.modal name="simrs-log-detail" @close="$wire.set('viewLogId', null)" maxWidth="4xl" title="">
        @if ($viewLog)
            <div class="space-y-4" x-data="{ tab: 'info' }">
                {{-- Header --}}
                <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center flex-wrap gap-2 mb-2">
                            <x-atoms.status-badge :status="$viewLog->level" />
                            @if ($viewLog->category)
                                <span class="font-mono text-xs font-semibold text-red-600 dark:text-red-400">
                                    {{ $viewLog->category }}
                                </span>
                            @endif
                            @if ($viewLog->module)
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300">
                                    {{ str_replace('_', ' ', ucfirst($viewLog->module)) }}
                                </span>
                            @endif
                        </div>
                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-200 break-words">
                            {{ $viewLog->message }}
                        </p>
                        @if ($viewLog->exception_class)
                            <p class="mt-1 font-mono text-xs text-zinc-500 dark:text-primary-dark-400">
                                {{ $viewLog->exception_class }}
                            </p>
                        @endif
                        <p class="mt-1.5 text-xs text-zinc-400 dark:text-primary-dark-500">
                            {{ $viewLog->created_at?->format('d/m/Y H:i:s') }}
                        </p>
                    </div>
                </div>

                {{-- Tab selector --}}
                @php
                    $hasTabs = $viewLog->stack_trace || $viewLog->context || $viewLog->db_host;
                    $tabOptions = ['info' => 'Info'];
                    if ($viewLog->stack_trace) {
                        $tabOptions['trace'] = 'Stack Trace';
                    }
                    if ($viewLog->context) {
                        $tabOptions['context'] = 'Context';
                    }
                @endphp
                @if ($hasTabs)
                    <x-molecules.tabs-2 model="tab" :options="$tabOptions" />
                @endif

                {{-- Tab: Info --}}
                <div x-show="tab === 'info'">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @php
                            $infoRows = array_filter(
                                [
                                    ['label' => 'Error ID', 'value' => $viewLog->error_id],
                                    ['label' => 'Versi Aplikasi', 'value' => $viewLog->app_version],
                                    ['label' => 'Host / Server', 'value' => $viewLog->host_name],
                                    ['label' => 'IP Address', 'value' => $viewLog->ip_address],
                                    ['label' => 'User SIMRS', 'value' => $viewLog->simrs_user],
                                    ['label' => 'Role SIMRS', 'value' => $viewLog->simrs_user_role],
                                    ['label' => 'DB Host', 'value' => $viewLog->db_host],
                                    ['label' => 'DB Name', 'value' => $viewLog->db_name],
                                ],
                                fn($r) => !is_null($r['value']),
                            );

                            $dbStatusRow = !is_null($viewLog->db_connected)
                                ? [
                                    'label' => 'Koneksi DB',
                                    'value' => $viewLog->db_connected ? '✓ Terhubung' : '✗ Terputus',
                                    'color' => $viewLog->db_connected
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-red-600 dark:text-red-400',
                                ]
                                : null;
                        @endphp

                        @foreach ($infoRows as $row)
                            <div class="flex flex-col gap-0.5 p-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                                <span
                                    class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                    {{ $row['label'] }}
                                </span>
                                <span
                                    class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300 font-mono break-all">
                                    {{ $row['value'] }}
                                </span>
                            </div>
                        @endforeach

                        @if ($dbStatusRow)
                            <div class="flex flex-col gap-0.5 p-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                                <span
                                    class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                    {{ $dbStatusRow['label'] }}
                                </span>
                                <span class="text-sm font-semibold {{ $dbStatusRow['color'] }}">
                                    {{ $dbStatusRow['value'] }}
                                </span>
                            </div>
                        @endif

                        @if ($viewLog->db_response_time_ms !== null)
                            <div class="flex flex-col gap-0.5 p-3 rounded-xl bg-zinc-50 dark:bg-primary-dark-900/50">
                                <span
                                    class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                    Response Time DB
                                </span>
                                <span class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300 font-mono">
                                    {{ number_format($viewLog->db_response_time_ms) }} ms
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end mt-4">
                        <x-atoms.button size="sm" variant="ghost"
                            x-on:click="$flux.modal('simrs-log-detail').close()">
                            Tutup
                        </x-atoms.button>
                    </div>
                </div>

                {{-- Tab: Stack Trace --}}
                @if ($viewLog->stack_trace)
                    <div x-show="tab === 'trace'" x-data="{ copied: false }">
                        <div
                            class="overflow-auto max-h-96 rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-zinc-950">
                            <pre x-ref="stack_trace" class="p-4 text-[11px] leading-relaxed font-mono text-red-300 whitespace-pre-wrap break-all">{{ $viewLog->stack_trace }}</pre>
                        </div>
                        <div class="flex items-center justify-between mt-3">
                            <x-atoms.button size="sm" icon="clipboard-document"
                                x-on:click="
                                            const text = $refs.stack_trace.textContent;
                                            if (navigator.clipboard) { navigator.clipboard.writeText(text); }
                                            else {
                                                const el = document.createElement('textarea');
                                                el.value = text; document.body.appendChild(el);
                                                el.select(); document.execCommand('copy');
                                                document.body.removeChild(el);
                                            }
                                            copied = true; setTimeout(() => copied = false, 2000);
                                        ">
                                <span x-text="copied ? 'Tersalin!' : 'Salin'">Salin</span>
                            </x-atoms.button>
                            <x-atoms.button size="sm" icon="x-mark" variant="ghost"
                                x-on:click="$flux.modal('simrs-log-detail').close()">Tutup</x-atoms.button>
                        </div>
                    </div>
                @endif

                {{-- Tab: Context --}}
                @if ($viewLog->context)
                    <div x-show="tab === 'context'">
                        <x-atoms.code-block language="json"
                            maxHeight="max-h-96">{{ json_encode($viewLog->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                        <div class="flex justify-end mt-3">
                            <x-atoms.button size="sm" icon="x-mark" variant="ghost"
                                x-on:click="$flux.modal('simrs-log-detail').close()">Tutup</x-atoms.button>
                        </div>
                    </div>
                @endif
            </div>
        @endif

    </x-organisms.modal>
</div>
