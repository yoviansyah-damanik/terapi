<?php

use App\Models\Api\ApiLog;
use App\Models\Api\ApiUser;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Log API')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterScope = '';

    #[Url]
    public string $filterMethod = '';

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $filterDate = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterScope(): void
    {
        $this->resetPage();
    }
    public function updatedFilterMethod(): void
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

    public int $perPage = 25;

    public bool $showDetailModal = false;
    public ?string $selectedLogId = null;

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filterScope', 'filterMethod', 'filterStatus', 'filterDate']);
        $this->resetPage();
    }

    public function viewLog(string $id): void
    {
        $this->selectedLogId = $id;
        $this->showDetailModal = true;
    }

    public function with(): array
    {
        // ── Stats ─────────────────────────────────────────────────────────────
        $total = ApiLog::count();
        $success = ApiLog::whereBetween('response_status', [200, 299])->count();
        $errors4xx = ApiLog::whereBetween('response_status', [400, 499])->count();
        $errors5xx = ApiLog::whereBetween('response_status', [500, 599])->count();
        $today = ApiLog::whereDate('created_at', today())->count();
        $avgMs = ApiLog::avg('response_time_ms');

        // ── Hit Per User (dengan breakdown per scope) ──────────────────────────
        $scopeMap = ApiUser::scopesMap();
        $scopes = array_column(ApiUser::SCOPES, 'key');

        $logGrouped = ApiLog::select('api_user_id', 'scope', DB::raw('count(*) as hits'), DB::raw('sum(case when response_status between 200 and 299 then 1 else 0 end) as success_hits'), DB::raw('sum(case when response_status >= 400 then 1 else 0 end) as error_hits'))->groupBy('api_user_id', 'scope')->get()->groupBy('api_user_id');

        $todayGrouped = ApiLog::select('api_user_id', 'scope', DB::raw('count(*) as hits'))->whereDate('created_at', today())->groupBy('api_user_id', 'scope')->get()->groupBy('api_user_id');

        $perUser = ApiUser::orderBy('name')
            ->get()
            ->map(function ($u) use ($logGrouped, $todayGrouped, $scopes, $scopeMap) {
                $userLogs = $logGrouped->get($u->id, collect());
                $userToday = $todayGrouped->get($u->id, collect());

                $scopeRows = collect($scopes)
                    ->map(
                        fn($s) => [
                            'scope' => $s,
                            'label' => $scopeMap[$s]['label'] ?? $s,
                            'color' => $scopeMap[$s]['color'] ?? 'zinc',
                            'hits' => $userLogs->where('scope', $s)->sum('hits'),
                            'success' => $userLogs->where('scope', $s)->sum('success_hits'),
                            'errors' => $userLogs->where('scope', $s)->sum('error_hits'),
                            'today' => $userToday->where('scope', $s)->sum('hits'),
                        ],
                    )
                    ->filter(fn($r) => $r['hits'] > 0)
                    ->values();

                // Logs tanpa scope (scope = null)
                $noScopeRow = [
                    'hits' => $userLogs->whereNull('scope')->sum('hits'),
                    'success' => $userLogs->whereNull('scope')->sum('success_hits'),
                    'errors' => $userLogs->whereNull('scope')->sum('error_hits'),
                    'today' => $userToday->whereNull('scope')->sum('hits'),
                ];

                return [
                    'name' => $u->name,
                    'username' => $u->username,
                    'is_active' => $u->is_active,
                    'scopes' => $u->scopes ?? [],
                    'total' => $userLogs->sum('hits'),
                    'success' => $userLogs->sum('success_hits'),
                    'errors' => $userLogs->sum('error_hits'),
                    'today' => $userToday->sum('hits'),
                    'scopeRows' => $scopeRows,
                    'noScope' => $noScopeRow,
                ];
            });

        // ── Hit Per Scope ──────────────────────────────────────────────────────
        $perScope = ApiLog::select('scope', DB::raw('count(*) as total'), DB::raw('sum(case when response_status between 200 and 299 then 1 else 0 end) as success'), DB::raw('sum(case when response_status >= 400 then 1 else 0 end) as errors'), DB::raw('avg(response_time_ms) as avg_ms'), DB::raw('sum(case when date(created_at) = curdate() then 1 else 0 end) as today'))->groupBy('scope')->orderByDesc('total')->get();

        // ── Detail Log ────────────────────────────────────────────────────────
        $logs = ApiLog::query()->when($this->search, fn($q) => $q->search($this->search))->when($this->filterScope, fn($q) => $q->forScope($this->filterScope))->when($this->filterMethod, fn($q) => $q->forMethod($this->filterMethod))->when($this->filterStatus, fn($q) => $q->forStatus($this->filterStatus))->when($this->filterDate, fn($q) => $q->whereDate('created_at', $this->filterDate))->orderByDesc('created_at')->paginate($this->perPage);

        $hasActiveFilter = $this->search || $this->filterScope || $this->filterMethod || $this->filterStatus || $this->filterDate;

        $selectedLog = $this->selectedLogId ? ApiLog::find($this->selectedLogId) : null;

        return compact('total', 'success', 'errors4xx', 'errors5xx', 'today', 'avgMs', 'perUser', 'perScope', 'logs', 'hasActiveFilter', 'selectedLog', 'scopeMap');
    }
};
?>

<div>
    <x-ui.page-header title="Log API" subtitle="Traffic, breakdown per user & scope, dan detail request masuk">
        <x-slot:actions>
            <x-atoms.button icon="arrow-path" variant="ghost" wire:click="$refresh">Refresh</x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats Cards --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <x-organisms.stat-card title="Total Request" :value="number_format($total)" icon="queue-list" color="zinc" />
        <x-organisms.stat-card title="Sukses (2xx)" :value="number_format($success)" icon="check-circle" color="emerald" />
        <x-organisms.stat-card title="Client Error (4xx)" :value="number_format($errors4xx)" icon="exclamation-triangle"
            color="amber" />
        <x-organisms.stat-card title="Server Error (5xx)" :value="number_format($errors5xx)" icon="x-circle" color="red" />
        <x-organisms.stat-card title="Hari Ini" :value="number_format($today)" icon="calendar-days" color="blue" />
        <x-organisms.stat-card title="Avg. Waktu" :value="$avgMs ? number_format($avgMs, 0) . ' ms' : '—'" icon="clock" color="violet" />
    </div>

    {{-- Hit Per User & Hit Per Scope --}}
    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-5">

        {{-- Hit Per User (col 3) --}}
        <x-organisms.data-panel class="lg:col-span-3" title="Hit Per User API"
            subtitle="Rincian per scope untuk setiap user">

            <div class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                @forelse ($perUser as $row)
                    <div x-data="{ open: false }">
                        {{-- Row utama --}}
                        <x-atoms.button variant="ghost" @click="open = !open"
                            class="flex w-full rounded-none! items-center gap-3 px-5 py-3 text-left transition-colors hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/20">
                            <span class="mr-auto">
                                <span class="block text-sm font-medium text-zinc-800 dark:text-primary-dark-100">
                                    {{ $row['name'] }}
                                </span>
                                <span class="font-mono text-xs text-zinc-400 dark:text-primary-dark-500">
                                    {{ $row['username'] }}
                                </span>
                            </span>
                            <span
                                class="w-20 text-right font-mono text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">
                                {{ number_format($row['total']) }}
                            </span>
                            <span class="w-16 text-right font-mono text-xs text-emerald-600 dark:text-emerald-400">
                                {{ number_format($row['success']) }} ✓
                            </span>
                            <span
                                class="w-16 text-right font-mono text-xs {{ $row['errors'] > 0 ? 'text-red-500 dark:text-red-400' : 'text-zinc-300 dark:text-primary-dark-600' }}">
                                {{ number_format($row['errors']) }} ✗
                            </span>
                            <span class="w-14 text-right font-mono text-xs text-blue-500 dark:text-blue-400">
                                +{{ number_format($row['today']) }}
                            </span>
                            <flux:icon.chevron-down
                                class="size-3.5 shrink-0 text-zinc-400 transition-transform duration-200"
                                ::class="open ? 'rotate-180' : ''" />
                        </x-atoms.button>

                        {{-- Per-scope breakdown --}}
                        <div x-show="open" x-collapse>
                            <div
                                class="border-t border-zinc-50 bg-zinc-50/60 px-5 py-2 dark:border-primary-dark-700/30 dark:bg-primary-dark-900/30">
                                @php
                                    $scopeColors = [
                                        'whatsapp-gateway' => 'emerald',
                                        'tte' => 'blue',
                                        'simrs' => 'amber',
                                    ];
                                    $scopeColorMap = [
                                        'emerald' =>
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                        'amber' =>
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                        'zinc' =>
                                            'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400',
                                    ];
                                @endphp
                                @forelse ($row['scopeRows'] as $sr)
                                    <div class="flex items-center gap-3 py-1.5">
                                        <span
                                            class="inline-flex w-36 shrink-0 items-center justify-center rounded-full px-2 py-0.5 text-xs font-medium {{ $scopeColorMap[$scopeColors[$sr['scope']] ?? 'zinc'] }}">
                                            {{ $sr['label'] }}
                                        </span>
                                        <span
                                            class="ml-auto w-16 text-right font-mono text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">
                                            {{ number_format($sr['hits']) }}
                                        </span>
                                        <span
                                            class="w-14 text-right font-mono text-xs text-emerald-600 dark:text-emerald-400">
                                            {{ number_format($sr['success']) }}
                                        </span>
                                        <span
                                            class="w-12 text-right font-mono text-xs {{ $sr['errors'] > 0 ? 'text-red-500' : 'text-zinc-300 dark:text-primary-dark-600' }}">
                                            {{ number_format($sr['errors']) }}
                                        </span>
                                        <span
                                            class="w-14 text-right font-mono text-xs text-blue-500 dark:text-blue-400">
                                            +{{ number_format($sr['today']) }}
                                        </span>
                                        <span class="w-3.5 shrink-0"></span>
                                    </div>
                                @empty
                                    <p class="py-2 text-xs italic text-zinc-400 dark:text-primary-dark-500">Belum ada
                                        log</p>
                                @endforelse
                                @if ($row['noScope']['hits'] > 0)
                                    <div class="flex items-center gap-3 py-1.5">
                                        <span
                                            class="inline-flex w-36 shrink-0 items-center justify-center rounded-full px-2 py-0.5 text-xs font-medium {{ $scopeColorMap['zinc'] }}">
                                            Tanpa Scope
                                        </span>
                                        <span
                                            class="ml-auto w-16 text-right font-mono text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">
                                            {{ number_format($row['noScope']['hits']) }}
                                        </span>
                                        <span
                                            class="w-14 text-right font-mono text-xs text-emerald-600 dark:text-emerald-400">
                                            {{ number_format($row['noScope']['success']) }}
                                        </span>
                                        <span
                                            class="w-12 text-right font-mono text-xs {{ $row['noScope']['errors'] > 0 ? 'text-red-500' : 'text-zinc-300 dark:text-primary-dark-600' }}">
                                            {{ number_format($row['noScope']['errors']) }}
                                        </span>
                                        <span
                                            class="w-14 text-right font-mono text-xs text-blue-500 dark:text-blue-400">
                                            +{{ number_format($row['noScope']['today']) }}
                                        </span>
                                        <span class="w-3.5 shrink-0"></span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-zinc-400 dark:text-primary-dark-500">
                        Belum ada user API terdaftar
                    </div>
                @endforelse
            </div>

            {{-- Legend footer --}}
            <x-slot:footer>
                <div
                    class="flex items-center gap-3 px-5 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                    <span class="mr-auto">User</span>
                    <span class="w-20">Total</span>
                    <span class="w-16">Sukses</span>
                    <span class="w-16">Error</span>
                    <span class="w-14">Hari Ini</span>
                    <span class="w-3.5"></span>
                </div>
            </x-slot:footer>
        </x-organisms.data-panel>

        {{-- Hit Per Scope (col 2) --}}
        <x-organisms.data-panel class="lg:col-span-2" title="Hit Per Scope" subtitle="Akumulasi seluruh user per scope">

            <div class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                @php
                    $scopeLabelMap = collect($scopeMap)->map(fn($s) => $s['label']);
                    $scopeColorCls = collect($scopeMap)
                        ->mapWithKeys(
                            fn($s, $key) => [
                                $key => match ($s['color']) {
                                    'green'
                                        => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                    'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                    'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                    'red' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    'violet'
                                        => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
                                    default
                                        => 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400',
                                },
                            ],
                        )
                        ->all();
                @endphp
                @forelse ($perScope as $row)
                    @php
                        $pct = $total > 0 ? round(($row->total / $total) * 100) : 0;
                        $label = $scopeLabelMap->get($row->scope, $row->scope ?? 'Tanpa Scope');
                        $cls =
                            $scopeColorCls[$row->scope] ??
                            'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400';
                    @endphp
                    <div class="px-5 py-3.5">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $cls }}">
                                {{ $label }}
                            </span>
                            <div class="flex items-center gap-3">
                                <span
                                    class="text-xs text-zinc-400 dark:text-primary-dark-500">{{ $pct }}%</span>
                                <span class="font-mono text-sm font-bold text-zinc-700 dark:text-primary-dark-200">
                                    {{ number_format($row->total) }}
                                </span>
                            </div>
                        </div>
                        <div
                            class="mb-2 h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-primary-dark-700">
                            <div class="h-full rounded-full bg-primary-500 dark:bg-primary-400"
                                style="width: {{ $pct }}%"></div>
                        </div>
                        <div class="flex items-center gap-4 text-xs">
                            <span class="text-emerald-600 dark:text-emerald-400">✓ {{ number_format($row->success) }}
                                sukses</span>
                            <span class="{{ $row->errors > 0 ? 'text-red-500' : 'text-zinc-400' }}">✗
                                {{ number_format($row->errors) }} error</span>
                            <span class="ml-auto text-zinc-400 dark:text-primary-dark-500">⌀
                                {{ $row->avg_ms ? number_format($row->avg_ms, 0) . ' ms' : '—' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-zinc-400 dark:text-primary-dark-500">
                        Belum ada data log
                    </div>
                @endforelse
            </div>
        </x-organisms.data-panel>
    </div>

    {{-- Detail Log ──────── --}}
    <x-organisms.data-panel title="Detail Log Request" :subtitle="number_format($logs->total()) . ' entri ditemukan'">
        <x-slot:filter>
            <div class="flex items-center gap-2">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="Cari path, IP, user..." clearable size="sm" />
                <flux:select wire:model.live="filterScope" class="w-40" size="sm">
                    <flux:select.option value="">Semua Scope</flux:select.option>
                    @foreach (ApiUser::SCOPES as $s)
                        <flux:select.option value="{{ $s['key'] }}">{{ $s['label'] }}</flux:select.option>
                    @endforeach
                    <flux:select.option value="none">Tanpa Scope</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="filterMethod" class="w-28" size="sm">
                    <flux:select.option value="">Semua Method</flux:select.option>
                    @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $m)
                        <flux:select.option value="{{ $m }}">{{ $m }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterStatus" class="w-32" size="sm">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="2xx">2xx Sukses</flux:select.option>
                    <flux:select.option value="4xx">4xx Client Error</flux:select.option>
                    <flux:select.option value="5xx">5xx Server Error</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="perPage" class="w-24" size="sm">
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                </flux:select>
                <flux:input wire:model.live="filterDate" type="date" class="w-24" size="sm" />
                <x-atoms.button wire:click="clearFilters" size="sm" variant="ghost"
                    icon="x-mark">Reset</x-atoms.button>
            </div>
        </x-slot:filter>

        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-36">Waktu</x-atoms.table-heading>
                <x-atoms.table-heading>User / Scope</x-atoms.table-heading>
                <x-atoms.table-heading class="w-20">Method</x-atoms.table-heading>
                <x-atoms.table-heading>Path</x-atoms.table-heading>
                <x-atoms.table-heading class="w-20">Status</x-atoms.table-heading>
                <x-atoms.table-heading align="right" class="w-24">Waktu (ms)</x-atoms.table-heading>
                <x-atoms.table-heading class="w-32">IP</x-atoms.table-heading>
                <x-atoms.table-heading class="w-10 px-4 py-3"></x-atoms.table-heading>
            </x-slot:headings>
            @forelse ($logs as $log)
                @php
                    $methodColors = [
                        'GET' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                        'POST' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                        'PUT' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                        'PATCH' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                        'DELETE' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                    ];
                    $statusColors = [
                        'green' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                        'red' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                        'zinc' => 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400',
                    ];
                @endphp
                <x-molecules.table-row wire:key="log-{{ $log->id }}">
                    <x-atoms.table-cell :nowrap="true" class="text-xs text-zinc-500 dark:text-primary-dark-400">
                        {{ $log->created_at->format('d/m H:i:s') }}
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300">
                            {{ $log->api_user_name ?? '—' }}
                        </p>
                        @if ($log->scope)
                            @php $scopeMeta = $scopeMap[$log->scope] ?? null; @endphp
                            <span
                                class="mt-0.5 inline-block rounded-full px-1.5 py-px text-xs
                                        {{ match ($log->scope) {
                                            'whatsapp-gateway' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                            'tte' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                            'simrs' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                            default => 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400',
                                        } }}">
                                {{ $scopeMeta['label'] ?? $log->scope }}
                            </span>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <span
                            class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold
                                    {{ $methodColors[$log->method] ?? 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400' }}">
                            {{ $log->method }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <span class="font-mono text-xs text-zinc-600 dark:text-primary-dark-300 line-clamp-1">
                            {{ $log->path }}
                        </span>
                        @if ($log->query_string)
                            <span
                                class="mt-0.5 block font-mono text-xs text-zinc-400 dark:text-primary-dark-500 line-clamp-1">
                                ?{{ $log->query_string }}
                            </span>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold
                                    {{ $statusColors[$log->status_color] ?? $statusColors['zinc'] }}">
                            {{ $log->response_status }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="right">
                        @if ($log->response_time_ms !== null)
                            <span
                                class="font-mono text-xs {{ $log->response_time_ms > 2000 ? 'text-red-500' : ($log->response_time_ms > 500 ? 'text-amber-500' : 'text-zinc-500 dark:text-primary-dark-400') }}">
                                {{ number_format($log->response_time_ms) }}
                            </span>
                        @else
                            <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                        @endif
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-zinc-400 dark:text-primary-dark-500">
                        {{ $log->ip_address ?? '—' }}
                    </x-atoms.table-cell>
                    <x-atoms.table-cell action align="right">
                        <x-atoms.button size="sm" variant="ghost" icon="eye"
                            wire:click="viewLog('{{ $log->id }}')" />
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="8" class="py-12 text-center">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon.queue-list class="size-8 text-zinc-300 dark:text-primary-dark-600" />
                            <p class="text-sm text-zinc-400 dark:text-primary-dark-500">
                                Tidak ada log yang cocok dengan filter
                            </p>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        @if ($logs->hasPages())
            <x-slot:footer>
                <div class="px-5 py-4">
                    {{ $logs->links() }}
                </div>
            </x-slot:footer>
        @endif
    </x-organisms.data-panel>

    {{-- ================================================================ --}}
    {{-- Modal: Detail Log                                                 --}}
    {{-- ================================================================ --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="4xl" title="Detail Log Request">
        @if ($selectedLog)
            @php
                $mColors = [
                    'GET' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                    'POST' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                    'PUT' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                    'PATCH' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                    'DELETE' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                ];
                $sColors = [
                    'green' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                    'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                    'red' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                    'zinc' => 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400',
                ];
                $scopeMeta = $scopeMap[$selectedLog->scope] ?? null;
                $speedLabel = match (true) {
                    $selectedLog->response_time_ms === null => '—',
                    $selectedLog->response_time_ms <= 200 => 'Cepat',
                    $selectedLog->response_time_ms <= 500 => 'Normal',
                    $selectedLog->response_time_ms <= 2000 => 'Lambat',
                    default => 'Sangat Lambat',
                };
                $speedColor = match (true) {
                    $selectedLog->response_time_ms === null => 'text-zinc-400',
                    $selectedLog->response_time_ms <= 200 => 'text-emerald-600 dark:text-emerald-400',
                    $selectedLog->response_time_ms <= 500 => 'text-blue-600 dark:text-blue-400',
                    $selectedLog->response_time_ms <= 2000 => 'text-amber-600 dark:text-amber-400',
                    default => 'text-red-600 dark:text-red-400',
                };
            @endphp

            <div x-data="{ logTab: 'info' }" class="space-y-4">
                @php
                    $isSuccess = $selectedLog->status_color === 'green';
                    $heroIconBg = match ($selectedLog->status_color) {
                        'green' => 'bg-emerald-100 dark:bg-emerald-900/50',
                        'amber' => 'bg-amber-100 dark:bg-amber-900/50',
                        'red' => 'bg-red-100 dark:bg-red-900/50',
                        default => 'bg-zinc-100 dark:bg-primary-dark-700',
                    };
                    $heroIconColor = match ($selectedLog->status_color) {
                        'green' => 'text-emerald-600 dark:text-emerald-400',
                        'amber' => 'text-amber-600 dark:text-amber-400',
                        'red' => 'text-red-600 dark:text-red-400',
                        default => 'text-zinc-500 dark:text-primary-dark-400',
                    };
                    $heroIcon = match ($selectedLog->status_color) {
                        'green' => 'check-circle',
                        'red' => 'x-circle',
                        default => 'exclamation-triangle',
                    };
                @endphp
                <div class="flex items-start gap-4 border-b border-zinc-200 pb-4 dark:border-primary-dark-700">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg {{ $heroIconBg }}">
                        <flux:icon name="{{ $heroIcon }}" class="size-6 {{ $heroIconColor }}" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="break-all font-mono text-base font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ $selectedLog->path }}
                        </h2>
                        @if ($selectedLog->query_string)
                            <p class="mt-0.5 break-all font-mono text-xs text-zinc-400 dark:text-primary-dark-500">
                                ?{{ $selectedLog->query_string }}
                            </p>
                        @endif
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <flux:badge
                                :color="match($selectedLog->method) {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                'GET' => 'blue', 'POST' => 'green',
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                'PUT', 'PATCH' => 'yellow', 'DELETE' => 'red', default => 'zinc'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            }"
                                size="sm">{{ $selectedLog->method }}</flux:badge>
                            <flux:badge
                                :color="match($selectedLog->status_color) {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                'green' => 'green', 'amber' => 'yellow', 'red' => 'red', default => 'zinc'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            }"
                                size="sm">{{ $selectedLog->response_status ?? '—' }}</flux:badge>
                            @if ($scopeMeta)
                                <flux:badge color="zinc" size="sm">{{ $scopeMeta['label'] }}</flux:badge>
                            @endif
                            @if ($selectedLog->response_time_ms !== null)
                                <span class="font-mono text-xs font-semibold {{ $speedColor }}">
                                    {{ number_format($selectedLog->response_time_ms) }} ms · {{ $speedLabel }}
                                </span>
                            @endif
                            @if ($selectedLog->api_user_name)
                                <flux:badge color="zinc" size="sm" icon="lock-closed">Auth: Bearer
                                    ({{ $selectedLog->api_user_name }})</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm" icon="lock-open">Auth: Public</flux:badge>
                            @endif
                            <span class="text-xs text-zinc-400 dark:text-primary-dark-500">
                                {{ $selectedLog->created_at->format('d M Y H:i:s') }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Tab Bar --}}
                <x-molecules.section-tabs model="logTab" :items="[
                    ['key' => 'info', 'label' => 'Info'],
                    ['key' => 'request', 'label' => 'Request'],
                    ['key' => 'response', 'label' => 'Response'],
                ]" />

                {{-- Tab: Request --}}
                <div x-show="logTab === 'request'" class="space-y-3">
                    {{-- URL --}}
                    <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                        <div class="border-b border-zinc-100 px-4 py-2.5 dark:border-primary-dark-700">
                            <span
                                class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">URL</span>
                        </div>
                        <div class="px-4 py-3">
                            <p class="break-all font-mono text-xs text-zinc-700 dark:text-primary-dark-200">
                                {{ $selectedLog->path }}
                            </p>
                        </div>
                    </div>

                    {{-- Query Params --}}
                    @if ($selectedLog->query_string)
                        @php
                            parse_str($selectedLog->query_string, $queryParams);
                        @endphp
                        <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                            <div class="border-b border-zinc-100 px-4 py-2.5 dark:border-primary-dark-700">
                                <span
                                    class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">Query
                                    Params</span>
                            </div>
                            <div class="divide-y divide-zinc-100 dark:divide-primary-dark-700/60">
                                @foreach ($queryParams as $key => $val)
                                    <div class="flex items-start gap-4 px-4 py-2.5">
                                        <span
                                            class="w-36 shrink-0 font-mono text-xs font-semibold text-zinc-500 dark:text-primary-dark-400">{{ $key }}</span>
                                        <span
                                            class="break-all font-mono text-xs text-zinc-700 dark:text-primary-dark-200">{{ is_array($val) ? implode(', ', $val) : $val }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Request Headers --}}
                    @if (!empty($selectedLog->request_headers))
                        <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                            <div class="border-b border-zinc-100 px-4 py-2.5 dark:border-primary-dark-700">
                                <span
                                    class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">Request
                                    Headers</span>
                            </div>
                            <div class="px-4 py-3">
                                <x-atoms.code-block
                                    language="json">{{ json_encode($selectedLog->request_headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                            </div>
                        </div>
                    @endif

                    {{-- Request Body --}}
                    <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                        <div class="border-b border-zinc-100 px-4 py-2.5 dark:border-primary-dark-700">
                            <span
                                class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">Request
                                Body</span>
                        </div>
                        <div class="px-4 py-3">
                            @if ($selectedLog->request_body)
                                <x-atoms.code-block
                                    language="json">{{ json_encode($selectedLog->request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                            @else
                                <p class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Tidak ada request
                                    body</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Tab: Response --}}
                <div x-show="logTab === 'response'" class="space-y-3">
                    {{-- Stat mini-cards --}}
                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-xl border border-zinc-200 p-3 text-center dark:border-primary-dark-700">
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">HTTP Status</p>
                            <p
                                class="mt-1 text-2xl font-bold
                                {{ $sColors[$selectedLog->status_color] ?? '' }}
                                {{ str_contains($sColors[$selectedLog->status_color] ?? '', 'emerald') ? 'text-emerald-600 dark:text-emerald-400' : (str_contains($sColors[$selectedLog->status_color] ?? '', 'red') ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400') }}">
                                {{ $selectedLog->response_status ?? '—' }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-zinc-200 p-3 text-center dark:border-primary-dark-700">
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">Response Time</p>
                            <p class="mt-1 text-2xl font-bold {{ $speedColor }}">
                                {{ $selectedLog->response_time_ms !== null ? number_format($selectedLog->response_time_ms) : '—' }}
                                @if ($selectedLog->response_time_ms !== null)
                                    <span class="text-sm font-normal">ms</span>
                                @endif
                            </p>
                        </div>
                        <div class="rounded-xl border border-zinc-200 p-3 text-center dark:border-primary-dark-700">
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">Kecepatan</p>
                            <p class="mt-1 text-xl font-bold {{ $speedColor }}">{{ $speedLabel }}</p>
                        </div>
                    </div>

                    {{-- Response Body --}}
                    <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                        <div class="border-b border-zinc-100 px-4 py-2.5 dark:border-primary-dark-700">
                            <span
                                class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">Response
                                Body</span>
                        </div>
                        <div class="px-4 py-3">
                            @if ($selectedLog->response_body)
                                <x-atoms.code-block
                                    language="json">{{ json_encode($selectedLog->response_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                            @else
                                <p class="text-xs italic text-zinc-400 dark:text-primary-dark-500">Tidak ada response
                                    body</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Tab: Info --}}
                <div x-show="logTab === 'info'">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @php
                            $infoCards = [
                                [
                                    'label' => 'Waktu',
                                    'value' => $selectedLog->created_at->format('d M Y, H:i:s'),
                                    'mono' => true,
                                ],
                                [
                                    'label' => 'HTTP Status',
                                    'value' => $selectedLog->response_status ?? '—',
                                    'mono' => true,
                                ],
                                [
                                    'label' => 'Response Time',
                                    'value' =>
                                        $selectedLog->response_time_ms !== null
                                            ? number_format($selectedLog->response_time_ms) . ' ms · ' . $speedLabel
                                            : '—',
                                    'mono' => true,
                                    'color' => $speedColor,
                                ],
                                ['label' => 'Method', 'value' => $selectedLog->method, 'mono' => true],
                                ['label' => 'User API', 'value' => $selectedLog->api_user_name ?? '—'],
                                ['label' => 'Scope', 'value' => $scopeMeta['label'] ?? ($selectedLog->scope ?? '—')],
                                ['label' => 'IP Address', 'value' => $selectedLog->ip_address ?? '—', 'mono' => true],
                                ['label' => 'Path', 'value' => $selectedLog->path, 'mono' => true, 'span' => true],
                            ];
                            if ($selectedLog->query_string) {
                                $infoCards[] = [
                                    'label' => 'Query String',
                                    'value' => '?' . $selectedLog->query_string,
                                    'mono' => true,
                                    'span' => true,
                                ];
                            }
                            if ($selectedLog->user_agent) {
                                $infoCards[] = [
                                    'label' => 'User Agent',
                                    'value' => $selectedLog->user_agent,
                                    'mono' => false,
                                    'span' => true,
                                ];
                            }
                            $infoCards[] = [
                                'label' => 'Log ID',
                                'value' => $selectedLog->id,
                                'mono' => true,
                                'span' => true,
                                'xs' => true,
                            ];
                        @endphp
                        @foreach ($infoCards as $card)
                            <div
                                class="flex flex-col gap-0.5 rounded-xl bg-zinc-50 p-3 dark:bg-primary-dark-900/50 {{ $card['span'] ?? false ? 'sm:col-span-2' : '' }}">
                                <span
                                    class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                    {{ $card['label'] }}
                                </span>
                                <span
                                    class="break-all font-medium {{ $card['mono'] ?? false ? 'font-mono' : '' }} {{ $card['xs'] ?? false ? 'text-xs' : 'text-sm' }} {{ $card['color'] ?? 'text-zinc-700 dark:text-primary-dark-300' }}">
                                    {{ $card['value'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="py-12 text-center text-sm text-zinc-400 dark:text-primary-dark-500">
                    Memuat data...
                </div>
        @endif

        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button wire:click="$set('showDetailModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
