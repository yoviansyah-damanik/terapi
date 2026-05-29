<?php

use App\Constants\SecurityConfig;
use App\Models\Api\ApiLog;
use App\Models\Api\ApiSecurityLog;
use App\Models\Api\ApiToken;
use App\Models\Api\ApiUser;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Ringkasan API')] class extends Component {
    public function with(): array
    {
        // ── Overview stats ────────────────────────────────────────────────────
        $totalUsers = ApiUser::count();
        $activeUsers = ApiUser::active()->count();
        $totalTokens = ApiToken::count();
        $activeTokens = ApiToken::where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();
        $totalScopes = collect(ApiUser::scopesMap())->count();

        $totalHits = ApiLog::count();
        $successHits = ApiLog::whereBetween('response_status', [200, 299])->count();
        $errors4xx = ApiLog::whereBetween('response_status', [400, 499])->count();
        $errors5xx = ApiLog::where('response_status', '>=', 500)->count();
        $todayHits = ApiLog::whereDate('created_at', today())->count();
        $avgMs = ApiLog::avg('response_time_ms');
        $successRate = $totalHits > 0 ? round(($successHits / $totalHits) * 100, 1) : 0;
        $firstLog = ApiLog::min('created_at');
        $lastLog = ApiLog::max('created_at');

        // ── Tren request 14 hari terakhir ─────────────────────────────────────
        $trendDays = 14;
        $trendData = ApiLog::select(DB::raw('date(created_at) as day'), DB::raw('count(*) as total'), DB::raw('sum(case when response_status between 200 and 299 then 1 else 0 end) as success'), DB::raw('sum(case when response_status >= 400 then 1 else 0 end) as errors'))
            ->where(
                'created_at',
                '>=',
                now()
                    ->subDays($trendDays - 1)
                    ->startOfDay(),
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $trendLabels = $trendTotal = $trendSuccess = $trendErrors = [];
        for ($i = $trendDays - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $row = $trendData->get($date);
            $trendLabels[] = now()->subDays($i)->format('d/m');
            $trendTotal[] = $row?->total ?? 0;
            $trendSuccess[] = $row?->success ?? 0;
            $trendErrors[] = $row?->errors ?? 0;
        }

        // ── Distribusi per jam (hari ini) ─────────────────────────────────────
        $hourlyRaw = ApiLog::select(DB::raw('hour(created_at) as hour'), DB::raw('count(*) as hits'), DB::raw('sum(case when response_status >= 400 then 1 else 0 end) as errors'))->whereDate('created_at', today())->groupBy('hour')->get()->keyBy('hour');

        $hourlyLabels = $hourlyHits = $hourlyErrors = [];
        for ($h = 0; $h < 24; $h++) {
            $row = $hourlyRaw->get($h);
            $hourlyLabels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $hourlyHits[] = $row?->hits ?? 0;
            $hourlyErrors[] = $row?->errors ?? 0;
        }

        // ── Distribusi per scope (enriched) ───────────────────────────────────
        $scopeStats = ApiLog::select('scope', DB::raw('count(*) as total'), DB::raw('sum(case when response_status between 200 and 299 then 1 else 0 end) as success'), DB::raw('sum(case when response_status >= 400 then 1 else 0 end) as errors'), DB::raw('avg(response_time_ms) as avg_ms'), DB::raw('sum(case when date(created_at) = curdate() then 1 else 0 end) as today'))->groupBy('scope')->orderByDesc('total')->get();

        $scopeLabels = $scopeValues = $scopeBgColors = [];
        $colorPalette = ['whatsapp-gateway' => '#10b981', 'tte' => '#3b82f6', 'log-simrs' => '#f59e0b', null => '#71717a'];
        foreach ($scopeStats as $row) {
            $scopeLabels[] = ApiUser::scopesMap()[$row->scope]['label'] ?? ($row->scope ?? 'Tanpa Scope');
            $scopeValues[] = $row->total;
            $scopeBgColors[] = $colorPalette[$row->scope] ?? '#a1a1aa';
        }

        // ── Distribusi per method ──────────────────────────────────────────────
        $methodDist = ApiLog::select('method', DB::raw('count(*) as hits'))->groupBy('method')->orderByDesc('hits')->get();
        $methodLabels = $methodDist->pluck('method')->toArray();
        $methodValues = $methodDist->pluck('hits')->toArray();
        $methodColorMap = ['GET' => '#3b82f6', 'POST' => '#10b981', 'PUT' => '#f59e0b', 'PATCH' => '#f59e0b', 'DELETE' => '#ef4444'];
        $methodBgColors = array_map(fn($m) => $methodColorMap[$m] ?? '#71717a', $methodLabels);

        // ── Top users by hit ──────────────────────────────────────────────────
        $topUserHits = ApiLog::select('api_user_id', DB::raw('count(*) as total'), DB::raw('sum(case when response_status between 200 and 299 then 1 else 0 end) as success'), DB::raw('sum(case when response_status >= 400 then 1 else 0 end) as errors'), DB::raw('avg(response_time_ms) as avg_ms'), DB::raw('sum(case when date(created_at) = curdate() then 1 else 0 end) as today'), DB::raw('max(created_at) as last_hit'))->groupBy('api_user_id')->orderByDesc('total')->limit(10)->get();

        $userMap = ApiUser::all()->keyBy('id');
        $topUsers = $topUserHits->map(function ($row) use ($userMap) {
            $user = $userMap->get($row->api_user_id);
            return array_merge($row->toArray(), [
                'name' => $user?->name ?? '(unknown)',
                'username' => $user?->username ?? '—',
                'scopes' => $user?->scopes ?? [],
            ]);
        });

        // ── Top paths ─────────────────────────────────────────────────────────
        $topPaths = ApiLog::select('path', 'method', DB::raw('count(*) as hits'), DB::raw('avg(response_time_ms) as avg_ms'), DB::raw('sum(case when response_status between 200 and 299 then 1 else 0 end) as success'), DB::raw('sum(case when response_status >= 400 then 1 else 0 end) as errors'), DB::raw('sum(case when date(created_at) = curdate() then 1 else 0 end) as today'))->groupBy('path', 'method')->orderByDesc('hits')->limit(10)->get();

        // ── Perbandingan 2xx / 4xx / 5xx per hari (14 hari) ──────────────────
        $statusTrendRaw = ApiLog::select(DB::raw('date(created_at) as day'), DB::raw('sum(case when response_status between 200 and 299 then 1 else 0 end) as s2xx'), DB::raw('sum(case when response_status between 400 and 499 then 1 else 0 end) as s4xx'), DB::raw('sum(case when response_status >= 500 then 1 else 0 end) as s5xx'))
            ->where(
                'created_at',
                '>=',
                now()
                    ->subDays($trendDays - 1)
                    ->startOfDay(),
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $statusLabels = $status2xx = $status4xx = $status5xx = [];
        for ($i = $trendDays - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $row = $statusTrendRaw->get($date);
            $statusLabels[] = now()->subDays($i)->format('d/m');
            $status2xx[] = $row?->s2xx ?? 0;
            $status4xx[] = $row?->s4xx ?? 0;
            $status5xx[] = $row?->s5xx ?? 0;
        }

        // ── Perbandingan scope (success vs error per scope) ────────────────────
        $scopeCompLabels = $scopeCompSuccess = $scopeCompErrors = $scopeCompToday = $scopeCompColors = [];
        foreach ($scopeStats as $s) {
            $scopeCompLabels[] = ApiUser::scopesMap()[$s->scope]['label'] ?? ($s->scope ?? 'Tanpa Scope');
            $scopeCompSuccess[] = (int) $s->success;
            $scopeCompErrors[] = (int) $s->errors;
            $scopeCompToday[] = (int) $s->today;
            $scopeCompColors[] = $colorPalette[$s->scope] ?? '#a1a1aa';
        }

        // ── Tren token diterbitkan per hari (30 hari) ─────────────────────────
        $tokenDays = 30;
        $tokenRaw = ApiToken::select(DB::raw('date(created_at) as day'), DB::raw('count(*) as total'))
            ->where(
                'created_at',
                '>=',
                now()
                    ->subDays($tokenDays - 1)
                    ->startOfDay(),
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $tokenLabels = $tokenCounts = $tokenCumulative = [];
        $cumulative = 0;
        for ($i = $tokenDays - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $cnt = $tokenRaw->get($date)?->total ?? 0;
            $cumulative += $cnt;
            $tokenLabels[] = now()->subDays($i)->format('d/m');
            $tokenCounts[] = $cnt;
            $tokenCumulative[] = $cumulative;
        }

        // ── Recent users ──────────────────────────────────────────────────────
        $recentUsers = ApiUser::withCount(['tokens'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // ── Ringkasan keamanan ────────────────────────────────────────────────
        $secActiveIncidents = ApiSecurityLog::whereNull('resolved_at')->count();
        $secTodayIncidents = ApiSecurityLog::whereDate('created_at', today())->count();
        $secRateLimitEnabled = SecurityConfig::bool('api.security.rate_limit.enabled');
        $secInputSizeEnabled = SecurityConfig::bool('api.security.input_size.enabled');
        $secAnomalyEnabled = SecurityConfig::bool('api.security.anomaly.enabled');
        $secByType = ApiSecurityLog::select('type', DB::raw('COUNT(*) as total'))->groupBy('type')->pluck('total', 'type');

        return compact('totalUsers', 'activeUsers', 'totalTokens', 'activeTokens', 'totalScopes', 'totalHits', 'successHits', 'errors4xx', 'errors5xx', 'todayHits', 'avgMs', 'successRate', 'firstLog', 'lastLog', 'trendLabels', 'trendTotal', 'trendSuccess', 'trendErrors', 'statusLabels', 'status2xx', 'status4xx', 'status5xx', 'hourlyLabels', 'hourlyHits', 'hourlyErrors', 'scopeStats', 'scopeLabels', 'scopeValues', 'scopeBgColors', 'scopeCompLabels', 'scopeCompSuccess', 'scopeCompErrors', 'scopeCompToday', 'scopeCompColors', 'methodLabels', 'methodValues', 'methodBgColors', 'tokenLabels', 'tokenCounts', 'tokenCumulative', 'topUsers', 'topPaths', 'recentUsers', 'secActiveIncidents', 'secTodayIncidents', 'secRateLimitEnabled', 'secInputSizeEnabled', 'secAnomalyEnabled', 'secByType');
    }
};
?>

<div>
    <x-ui.page-header title="Ringkasan API" subtitle="Overview menyeluruh: manajemen, traffic, dan performa API">
        <x-slot:actions>
            <x-atoms.button icon="arrow-path" wire:click="$refresh" variant="ghost">Refresh</x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats: User & Token ─────────────────────────────────────────────────── --}}

    <div class="mb-3 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-4">
        @foreach ([['label' => 'Total User', 'value' => $totalUsers, 'color' => 'zinc', 'icon' => 'users', 'sub' => $activeUsers . ' aktif'], ['label' => 'Token Diterbitkan', 'value' => $totalTokens, 'color' => 'blue', 'icon' => 'key', 'sub' => $activeTokens . ' aktif'], ['label' => 'Scope Tersedia', 'value' => $totalScopes, 'color' => 'violet', 'icon' => 'squares-2x2', 'sub' => 'scope terdaftar'], ['label' => 'User Aktif', 'value' => $activeUsers, 'color' => 'emerald', 'icon' => 'check-badge', 'sub' => 'dari ' . $totalUsers . ' total']] as $card)
            <x-organisms.stat-card title="{{ $card['label'] }}" value="{{ number_format($card['value']) }}"
                icon="{{ $card['icon'] }}" color="{{ $card['color'] }}" subtitle="{{ $card['sub'] }}" />
        @endforeach
    </div>

    {{-- Stats: Traffic ──────────────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-4">
        @foreach ([
        ['label' => 'Total Request', 'value' => number_format($totalHits), 'color' => 'zinc', 'icon' => 'queue-list', 'sub' => $firstLog ? \Carbon\Carbon::parse($firstLog)->format('d/m/Y') . ' – sekarang' : '—'],
        ['label' => 'Sukses (2xx)', 'value' => number_format($successHits), 'color' => 'emerald', 'icon' => 'check-circle', 'sub' => $successRate . '% sukses rate'],
        ['label' => 'Client Error (4xx)', 'value' => number_format($errors4xx), 'color' => 'amber', 'icon' => 'exclamation-triangle', 'sub' => ($totalHits > 0 ? round(($errors4xx / $totalHits) * 100, 1) : 0) . '% dari total'],
        ['label' => 'Server Error (5xx)', 'value' => number_format($errors5xx), 'color' => 'red', 'icon' => 'x-circle', 'sub' => ($totalHits > 0 ? round(($errors5xx / $totalHits) * 100, 1) : 0) . '% dari total'],
        ['label' => 'Hit Hari Ini', 'value' => number_format($todayHits), 'color' => 'sky', 'icon' => 'calendar-days', 'sub' => \Carbon\Carbon::today()->format('d M Y')],
        ['label' => 'Avg. Response Time', 'value' => $avgMs ? number_format($avgMs, 0) . ' ms' : '—', 'color' => 'indigo', 'icon' => 'clock', 'sub' => 'rata-rata semua request'],
    ] as $card)
            <x-organisms.stat-card title="{{ $card['label'] }}" value="{{ $card['value'] }}" icon="{{ $card['icon'] }}"
                color="{{ $card['color'] }}" subtitle="{{ $card['sub'] }}" />
        @endforeach
    </div>

    {{-- Tren 14 Hari + Scope Donut ──────────────────────────────────────────── --}}
    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-organisms.card-box title="Tren Request (14 Hari Terakhir)" :padding="false" class="lg:col-span-2">
            <div class="p-5" wire:ignore>
                <canvas id="trendChart" height="80"></canvas>
            </div>
        </x-organisms.card-box>

        <x-organisms.card-box title="Distribusi per Scope" :padding="false">
            <div class="flex flex-col items-center p-5">
                <div class="relative w-44" wire:ignore>
                    <canvas id="scopeChart"></canvas>
                </div>
                <div class="mt-4 w-full space-y-1.5">
                    @foreach ($scopeLabels as $i => $label)
                        <div class="flex items-center gap-2 text-xs">
                            <span class="size-2.5 shrink-0 rounded-full"
                                style="background-color: {{ $scopeBgColors[$i] ?? '#a1a1aa' }}"></span>
                            <span class="flex-1 text-zinc-600 dark:text-primary-dark-400">{{ $label }}</span>
                            <span
                                class="font-mono font-medium text-zinc-700 dark:text-primary-dark-300">{{ number_format($scopeValues[$i]) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-organisms.card-box>
    </div>

    {{-- Hourly Chart (hari ini) + Method Chart ───────────────────────────────── --}}
    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-organisms.card-box title="Traffic Hari Ini (per Jam)"
            subtitle="{{ \Carbon\Carbon::today()->format('d M Y') }} — total {{ number_format($todayHits) }} request"
            :padding="false" class="lg:col-span-2">
            <div class="p-5" wire:ignore>
                <canvas id="hourlyChart" height="80"></canvas>
            </div>
        </x-organisms.card-box>

        <x-organisms.card-box title="Distribusi Method" :padding="false">
            <div class="p-5" wire:ignore>
                <canvas id="methodChart" height="160"></canvas>
            </div>
        </x-organisms.card-box>
    </div>

    {{-- 3 Grafik Baru: 2xx/4xx/5xx | Scope Comparison | Token Terbit ──────── --}}
    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- Perbandingan 2xx / 4xx / 5xx --}}
        <x-organisms.card-box title="Perbandingan Status (2xx / 4xx / 5xx)"
            subtitle="14 hari terakhir — stacked per hari" :padding="false" class="lg:col-span-2">
            <div class="p-5" wire:ignore>
                <canvas id="statusChart" height="90"></canvas>
            </div>
        </x-organisms.card-box>

        {{-- Scope Comparison --}}
        <x-organisms.card-box title="Perbandingan Scope" subtitle="Sukses vs Error per scope" :padding="false">
            <div class="p-5" wire:ignore>
                <canvas id="scopeCompChart" height="160"></canvas>
            </div>
        </x-organisms.card-box>
    </div>

    {{-- Token Terbit ─────────────────────────────────────────────────────────── --}}
    <x-organisms.card-box title="Token Diterbitkan (30 Hari Terakhir)"
        subtitle="Total: {{ number_format($totalTokens) }} token · Aktif: {{ number_format($activeTokens) }} · Kadaluarsa: {{ number_format($totalTokens - $activeTokens) }}"
        :padding="false" class="mb-4">
        <div class="p-5" wire:ignore>
            <canvas id="tokenChart" height="60"></canvas>
        </div>
    </x-organisms.card-box>

    {{-- Top Endpoint ─────────────────────────────────────────────────────────── --}}
    <x-organisms.card-box title="Top Endpoint" subtitle="10 endpoint dengan hit terbanyak" :padding="false"
        class="mb-4">
        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading class="w-20">Method</x-atoms.table-heading>
                <x-atoms.table-heading>Path</x-atoms.table-heading>
                <x-atoms.table-heading align="right" class="w-24">Total</x-atoms.table-heading>
                <x-atoms.table-heading align="right" class="w-24">Sukses</x-atoms.table-heading>
                <x-atoms.table-heading align="right" class="w-24">Error</x-atoms.table-heading>
                <x-atoms.table-heading align="right" class="w-24">Hari Ini</x-atoms.table-heading>
                <x-atoms.table-heading align="right" class="w-24">Avg ms</x-atoms.table-heading>
            </x-slot:headings>

            @php
                $methodBadge = [
                    'GET' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                    'POST' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                    'PUT' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                    'PATCH' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                    'DELETE' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                ];
            @endphp
            @forelse ($topPaths as $p)
                <x-molecules.table-row>
                    <x-atoms.table-cell>
                        <span
                            class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold {{ $methodBadge[$p->method] ?? 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400' }}">
                            {{ $p->method }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <span class="line-clamp-1 font-mono text-[11px] text-zinc-600 dark:text-primary-dark-300">
                            {{ $p->path }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="right">
                        <span class="font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">
                            {{ number_format($p->hits) }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="right">
                        <span class="font-mono text-[11px] text-emerald-600 dark:text-emerald-400">
                            {{ number_format($p->success) }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="right">
                        <span
                            class="font-mono text-[11px] {{ $p->errors > 0 ? 'text-red-500 dark:text-red-400' : 'text-zinc-300 dark:text-primary-dark-600' }}">
                            {{ number_format($p->errors) }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="right">
                        <span class="font-mono text-[11px] text-blue-500 dark:text-blue-400">
                            +{{ number_format($p->today) }}
                        </span>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="right">
                        <span class="font-mono text-[11px] text-zinc-500 dark:text-primary-dark-400">
                            {{ $p->avg_ms ? number_format($p->avg_ms, 0) : '—' }}
                        </span>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="7"
                        class="py-12 text-center text-sm text-zinc-400 dark:text-primary-dark-500">
                        Belum ada data log
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>
    </x-organisms.card-box>

    {{-- Top Users + Scope Stats ──────────────────────────────────────────────── --}}
    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-5">

        {{-- Top Users (col 3) --}}
        <x-organisms.card-box title="Top User API" subtitle="Berdasarkan jumlah request" :padding="false"
            class="lg:col-span-3">
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>User</x-atoms.table-heading>
                    <x-atoms.table-heading align="right" class="w-24">Total</x-atoms.table-heading>
                    <x-atoms.table-heading align="right" class="w-24">Sukses</x-atoms.table-heading>
                    <x-atoms.table-heading align="right" class="w-24">Error</x-atoms.table-heading>
                    <x-atoms.table-heading align="right" class="w-24">Hari Ini</x-atoms.table-heading>
                    <x-atoms.table-heading align="right" class="w-24">Avg ms</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($topUsers as $u)
                    <x-molecules.table-row>
                        <x-atoms.table-cell>
                            <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-200">
                                {{ $u['name'] }}
                            </p>
                            <p class="font-mono text-[10px] text-zinc-400 dark:text-primary-dark-500">
                                {{ $u['username'] }}
                            </p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="right">
                            <span class="font-mono text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">
                                {{ number_format($u['total']) }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="right">
                            <span class="font-mono text-xs text-emerald-600 dark:text-emerald-400">
                                {{ number_format($u['success']) }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="right">
                            <span
                                class="font-mono text-xs {{ $u['errors'] > 0 ? 'text-red-500 dark:text-red-400' : 'text-zinc-300 dark:text-primary-dark-600' }}">
                                {{ number_format($u['errors']) }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="right">
                            <span class="font-mono text-xs text-blue-500 dark:text-blue-400">
                                +{{ number_format($u['today']) }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="right">
                            <span class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400">
                                {{ $u['avg_ms'] ? number_format($u['avg_ms'], 0) : '—' }}
                            </span>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="6"
                            class="py-12 text-center text-sm text-zinc-400 dark:text-primary-dark-500">
                            Belum ada data log
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse
            </x-organisms.table>
        </x-organisms.card-box>

        {{-- Scope Stats (col 2) --}}
        <x-organisms.card-box title="Statistik per Scope" subtitle="Performa dan traffic per scope" :padding="false"
            class="lg:col-span-2">
            @php
                $scopeColorCls = [
                    'whatsapp-gateway' =>
                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                    'tte' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                    'log-simrs' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                ];
            @endphp
            <div class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                @forelse ($scopeStats as $s)
                    @php
                        $label = ApiUser::scopesMap()[$s->scope]['label'] ?? ($s->scope ?? 'Tanpa Scope');
                        $cls =
                            $scopeColorCls[$s->scope] ??
                            'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400';
                        $pct = $totalHits > 0 ? round(($s->total / $totalHits) * 100) : 0;
                        $errRate = $s->total > 0 ? round(($s->errors / $s->total) * 100, 1) : 0;
                    @endphp
                    <div class="px-5 py-3.5">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $cls }}">
                                {{ $label }}
                            </span>
                            <span
                                class="font-mono text-sm font-bold text-zinc-700 dark:text-primary-dark-200">{{ number_format($s->total) }}</span>
                        </div>
                        <div
                            class="mb-2 h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-primary-dark-700">
                            <div class="h-full rounded-full bg-primary-500 dark:bg-primary-400"
                                style="width: {{ $pct }}%"></div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-2 text-xs">
                            <span class="text-emerald-600 dark:text-emerald-400">✓ {{ number_format($s->success) }}
                                sukses</span>
                            <span class="{{ $s->errors > 0 ? 'text-red-500' : 'text-zinc-400' }}">✗
                                {{ number_format($s->errors) }} ({{ $errRate }}%)</span>
                            <span class="text-blue-500 dark:text-blue-400">+{{ number_format($s->today) }} hari
                                ini</span>
                            <span class="text-zinc-400 dark:text-primary-dark-500">⌀
                                {{ $s->avg_ms ? number_format($s->avg_ms, 0) . ' ms' : '—' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-zinc-400 dark:text-primary-dark-500">Belum ada data
                        log
                    </div>
                @endforelse
            </div>
        </x-organisms.card-box>
    </div>

    {{-- Recent Users + Quick Links ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 mb-4">
        <x-organisms.card-box title="User API Terbaru" :padding="false">
            <div class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                @forelse ($recentUsers as $u)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/30">
                            <span
                                class="text-xs font-bold text-primary-600 dark:text-primary-400">{{ strtoupper(substr($u->name, 0, 1)) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-zinc-800 dark:text-primary-dark-100">
                                {{ $u->name }}</p>
                            <p class="font-mono text-xs text-zinc-400 dark:text-primary-dark-500">{{ $u->username }}
                                ·
                                {{ $u->tokens_count }} token</p>
                        </div>
                        @if ($u->is_active)
                            <span class="size-2 shrink-0 rounded-full bg-emerald-400"></span>
                        @else
                            <span class="size-2 shrink-0 rounded-full bg-zinc-300 dark:bg-primary-dark-600"></span>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-zinc-400 dark:text-primary-dark-500">Belum ada user
                        API
                    </div>
                @endforelse
            </div>
        </x-organisms.card-box>

        <div class="lg:col-span-2">
            <div class="grid grid-cols-2 gap-3">
                @php
                    $quickLinks = [
                        ['href' => route('api-portal.management'),    'title' => 'Manajemen API', 'desc' => 'Kelola user, token, dan hak akses',         'icon' => 'key',          'color' => 'blue',    'permission' => 'api_portal.management'],
                        ['href' => route('api-portal.logs'),          'title' => 'Log API',       'desc' => 'Detail traffic, breakdown per scope & user', 'icon' => 'queue-list',   'color' => 'zinc',    'permission' => 'api_portal.logs'],
                        ['href' => route('api-portal.documentation'), 'title' => 'Dokumentasi',   'desc' => 'Referensi endpoint dan autentikasi',         'icon' => 'book-open',    'color' => 'emerald', 'permission' => 'api_portal.documentation'],
                        ['href' => route('api-portal.integration'),   'title' => 'Integrasi',     'desc' => 'Panduan integrasi TTE, BPJS, Satu Sehat',   'icon' => 'code-bracket', 'color' => 'violet',  'permission' => 'api_portal.integration'],
                    ];
                @endphp
                @foreach ($quickLinks as $link)
                    @if (auth()->user()?->hasPermission($link['permission']))
                        <x-molecules.action-card href="{{ $link['href'] }}" wire:navigate
                            title="{{ $link['title'] }}" description="{{ $link['desc'] }}"
                            icon="{{ $link['icon'] }}" color="{{ $link['color'] }}" />
                    @else
                        <x-molecules.action-card title="{{ $link['title'] }}" description="{{ $link['desc'] }}"
                            icon="{{ $link['icon'] }}" color="{{ $link['color'] }}" />
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- Ringkasan Keamanan ───────────────────────────────────────────────────── --}}
    <x-organisms.card-box title="Keamanan API" subtitle="Status perlindungan & insiden aktif" :padding="false"
        variant="danger" class="mb-6">
        <x-slot:footer>
            @if (auth()->user()?->hasPermission('api_portal.security'))
                <a href="{{ route('api-portal.security') }}" wire:navigate
                    class="text-xs text-primary-500 hover:text-primary-600 dark:text-primary-400 flex items-center gap-1 w-full justify-end px-5 py-3">
                    Detail <flux:icon.arrow-right class="w-3.5 h-3.5" />
                </a>
            @else
                <span class="text-xs text-zinc-400 dark:text-primary-dark-600 flex items-center gap-1 w-full justify-end px-5 py-3 opacity-50">
                    Detail <flux:icon.arrow-right class="w-3.5 h-3.5" />
                </span>
            @endif
        </x-slot:footer>

        <div class="p-5">
            {{-- Status Modul ─────────────────────────────────────────────── --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
                @foreach ([['label' => 'Trusted Proxy', 'active' => true, 'icon' => 'server'], ['label' => 'Rate Limiting', 'active' => $secRateLimitEnabled, 'icon' => 'bolt'], ['label' => 'CORS Policy', 'active' => true, 'icon' => 'globe-alt'], ['label' => 'Input Size Limit', 'active' => $secInputSizeEnabled, 'icon' => 'archive-box-x-mark'], ['label' => 'Anomaly Detection', 'active' => $secAnomalyEnabled, 'icon' => 'eye'], ['label' => 'Token Auth', 'active' => true, 'icon' => 'key']] as $mod)
                    <div
                        class="flex flex-col items-center gap-1.5 p-3 rounded-xl
                        {{ $mod['active']
                            ? 'bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800/30'
                            : 'bg-zinc-50 dark:bg-primary-dark-900/50 border border-zinc-100 dark:border-primary-dark-700/50' }}">
                        <flux:icon :name="$mod['icon']"
                            class="w-5 h-5 {{ $mod['active'] ? 'text-emerald-500 dark:text-emerald-400' : 'text-zinc-300 dark:text-primary-dark-600' }}" />
                        <span
                            class="text-[10px] font-medium text-center leading-tight
                            {{ $mod['active'] ? 'text-emerald-700 dark:text-emerald-300' : 'text-zinc-400 dark:text-primary-dark-500' }}">
                            {{ $mod['label'] }}
                        </span>
                        <span
                            class="text-[9px] font-semibold uppercase tracking-wide
                            {{ $mod['active'] ? 'text-emerald-500 dark:text-emerald-400' : 'text-zinc-400 dark:text-primary-dark-500' }}">
                            {{ $mod['active'] ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                @endforeach
            </div>

            {{-- Insiden & Statistik ──────────────────────────────────────── --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Alert insiden aktif --}}
                <div>
                    @if ($secActiveIncidents > 0)
                        <div
                            class="flex items-start gap-3 p-3.5 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/40 mb-3">
                            <flux:icon.exclamation-triangle class="w-4 h-4 text-red-500 shrink-0 mt-0.5" />
                            <div>
                                <p class="text-xs font-semibold text-red-700 dark:text-red-300">
                                    {{ $secActiveIncidents }} insiden keamanan aktif
                                </p>
                                <p class="text-xs text-red-500 dark:text-red-400">
                                    {{ $secTodayIncidents }} insiden hari ini — segera tinjau halaman Keamanan API.
                                </p>
                            </div>
                        </div>
                    @else
                        <div
                            class="flex items-start gap-3 p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800/30 mb-3">
                            <flux:icon.shield-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                            <div>
                                <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">Tidak ada
                                    insiden aktif</p>
                                <p class="text-xs text-emerald-500 dark:text-emerald-400">Semua perlindungan berjalan
                                    normal.</p>
                            </div>
                        </div>
                    @endif
                    <p class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400 mb-2">Insiden hari ini per
                        tipe
                    </p>
                    <div class="space-y-1.5">
                        @foreach ([['type' => 'rate_limited', 'label' => 'Rate Limited', 'color' => 'text-amber-600 dark:text-amber-400'], ['type' => 'oversized_request', 'label' => 'Request Terlalu Besar', 'color' => 'text-orange-600 dark:text-orange-400'], ['type' => 'anomaly_high_failure', 'label' => 'Error Rate Tinggi', 'color' => 'text-red-600 dark:text-red-400'], ['type' => 'anomaly_high_volume', 'label' => 'Volume Anomali', 'color' => 'text-violet-600 dark:text-violet-400'], ['type' => 'anomaly_brute_force', 'label' => 'Brute Force', 'color' => 'text-red-700 dark:text-red-500']] as $t)
                            @php $cnt = $secByType->get($t['type'], 0); @endphp
                            <div class="flex items-center justify-between text-xs">
                                <span class="{{ $t['color'] }} font-medium">{{ $t['label'] }}</span>
                                <span
                                    class="font-mono font-semibold text-zinc-600 dark:text-primary-dark-300">{{ $cnt }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Konfigurasi aktif --}}
                <div>
                    <p class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400 mb-2">Konfigurasi aktif
                    </p>
                    <div class="space-y-2 text-xs">
                        @php
                            $cfgRows = [
                                [
                                    'label' => 'Auth Rate Limit',
                                    'value' =>
                                        SecurityConfig::int('api.security.rate_limit.auth_max') .
                                        '× / ' .
                                        SecurityConfig::int('api.security.rate_limit.auth_window') .
                                        'min per IP',
                                ],
                                [
                                    'label' => 'API Rate Limit',
                                    'value' =>
                                        SecurityConfig::int('api.security.rate_limit.general_max') .
                                        ' req/min per token',
                                ],
                                [
                                    'label' => 'Webhook Limit',
                                    'value' =>
                                        SecurityConfig::int('api.security.rate_limit.webhook_max') . ' req/min per IP',
                                ],
                                [
                                    'label' => 'Max Size Auth',
                                    'value' => SecurityConfig::int('api.security.input_size.auth_kb') . ' KB',
                                ],
                                [
                                    'label' => 'Max Size SIMRS',
                                    'value' => SecurityConfig::int('api.security.input_size.simrs_kb') . ' KB',
                                ],
                                [
                                    'label' => 'Anomaly Window',
                                    'value' => SecurityConfig::int('api.security.anomaly.window_minutes') . ' menit',
                                ],
                                [
                                    'label' => 'Error Rate Thresh',
                                    'value' => SecurityConfig::int('api.security.anomaly.error_rate_pct') . '%',
                                ],
                                [
                                    'label' => 'CORS Origins',
                                    'value' => SecurityConfig::get('api.security.cors.allowed_origins'),
                                ],
                            ];
                        @endphp
                        @foreach ($cfgRows as $row)
                            <div
                                class="flex items-start justify-between gap-2 py-1 border-b border-zinc-50 dark:border-primary-dark-700/50">
                                <span
                                    class="text-zinc-400 dark:text-primary-dark-500 shrink-0">{{ $row['label'] }}</span>
                                <span
                                    class="font-mono text-zinc-600 dark:text-primary-dark-300 text-right break-all">{{ $row['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </x-organisms.card-box>

    {{-- Data grafik — dalam <div> agar re-render saat Livewire refresh --}}
    <script type="application/json" id="api-summary-chart-data">
    {
        "trendLabels":      @json($trendLabels),
        "trendTotal":       @json($trendTotal),
        "trendSuccess":     @json($trendSuccess),
        "trendErrors":      @json($trendErrors),
        "statusLabels":     @json($statusLabels),
        "status2xx":        @json($status2xx),
        "status4xx":        @json($status4xx),
        "status5xx":        @json($status5xx),
        "hourlyLabels":     @json($hourlyLabels),
        "hourlyHits":       @json($hourlyHits),
        "hourlyErrors":     @json($hourlyErrors),
        "scopeLabels":      @json($scopeLabels),
        "scopeValues":      @json($scopeValues),
        "scopeBgColors":    @json($scopeBgColors),
        "scopeCompLabels":  @json($scopeCompLabels),
        "scopeCompSuccess": @json($scopeCompSuccess),
        "scopeCompErrors":  @json($scopeCompErrors),
        "scopeCompColors":  @json($scopeCompColors),
        "methodLabels":     @json($methodLabels),
        "methodValues":     @json($methodValues),
        "methodBgColors":   @json($methodBgColors),
        "tokenLabels":      @json($tokenLabels),
        "tokenCounts":      @json($tokenCounts),
        "tokenCumulative":  @json($tokenCumulative),
        "totalHits":        {{ $totalHits }}
    }
    </script>
</div>

@pushOnce('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
        const API_CHART_IDS = ['trendChart', 'scopeChart', 'methodChart', 'hourlyChart', 'statusChart', 'scopeCompChart',
            'tokenChart'
        ];

        function getApiSummaryData() {
            const el = document.getElementById('api-summary-chart-data');
            return el ? JSON.parse(el.textContent) : null;
        }

        function destroyApiSummaryCharts() {
            API_CHART_IDS.forEach(id => {
                const existing = Chart.getChart(id);
                if (existing) existing.destroy();
            });
        }

        function initApiSummaryCharts() {
            const d = getApiSummaryData();
            if (!d) return;

            const isDark = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
            const labelColor = isDark ? '#a1a1aa' : '#71717a';

            // ── Tren 14 hari ─────────────────────────────────────────────────────
            const trendCtx = document.getElementById('trendChart');
            if (trendCtx) {
                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: d.trendLabels,
                        datasets: [{
                                label: 'Total',
                                data: d.trendTotal,
                                borderColor: '#6366f1',
                                backgroundColor: 'rgba(99,102,241,0.08)',
                                fill: true,
                                tension: 0.4,
                                pointRadius: 3,
                                pointHoverRadius: 5
                            },
                            {
                                label: 'Sukses',
                                data: d.trendSuccess,
                                borderColor: '#10b981',
                                backgroundColor: 'transparent',
                                fill: false,
                                tension: 0.4,
                                pointRadius: 2,
                                borderDash: [4, 3]
                            },
                            {
                                label: 'Error',
                                data: d.trendErrors,
                                borderColor: '#ef4444',
                                backgroundColor: 'transparent',
                                fill: false,
                                tension: 0.4,
                                pointRadius: 2,
                                borderDash: [4, 3]
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    color: labelColor,
                                    boxWidth: 12,
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    color: gridColor
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: gridColor
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 11
                                    },
                                    precision: 0
                                }
                            },
                        },
                    },
                });
            }

            // ── Hourly (hari ini) ─────────────────────────────────────────────────
            const hourlyCtx = document.getElementById('hourlyChart');
            if (hourlyCtx) {
                new Chart(hourlyCtx, {
                    type: 'bar',
                    data: {
                        labels: d.hourlyLabels,
                        datasets: [{
                                label: 'Request',
                                data: d.hourlyHits,
                                backgroundColor: 'rgba(99,102,241,0.65)',
                                borderRadius: 4,
                                borderSkipped: false
                            },
                            {
                                label: 'Error',
                                data: d.hourlyErrors,
                                backgroundColor: 'rgba(239,68,68,0.65)',
                                borderRadius: 4,
                                borderSkipped: false
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    color: labelColor,
                                    boxWidth: 12,
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 9
                                    },
                                    maxRotation: 0
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: gridColor
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 11
                                    },
                                    precision: 0
                                }
                            },
                        },
                    },
                });
            }

            // ── Donut: Scope ──────────────────────────────────────────────────────
            const scopeCtx = document.getElementById('scopeChart');
            if (scopeCtx) {
                new Chart(scopeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: d.scopeLabels,
                        datasets: [{
                            data: d.scopeValues,
                            backgroundColor: d.scopeBgColors,
                            borderWidth: 2,
                            borderColor: isDark ? '#27272a' : '#ffffff',
                            hoverOffset: 6
                        }],
                    },
                    options: {
                        responsive: true,
                        cutout: '65%',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: ctx =>
                                        ` ${ctx.label}: ${ctx.formattedValue} (${d.totalHits > 0 ? Math.round(ctx.parsed / d.totalHits * 100) : 0}%)`
                                }
                            },
                        },
                    },
                });
            }

            // ── Bar: Method ───────────────────────────────────────────────────────
            const methodCtx = document.getElementById('methodChart');
            if (methodCtx) {
                new Chart(methodCtx, {
                    type: 'bar',
                    data: {
                        labels: d.methodLabels,
                        datasets: [{
                            label: 'Hits',
                            data: d.methodValues,
                            backgroundColor: d.methodBgColors,
                            borderRadius: 6,
                            borderSkipped: false
                        }],
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: gridColor
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 11
                                    },
                                    precision: 0
                                }
                            },
                        },
                    },
                });
            }

            // ── Stacked Bar: 2xx / 4xx / 5xx per hari ────────────────────────────
            const statusCtx = document.getElementById('statusChart');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'bar',
                    data: {
                        labels: d.statusLabels,
                        datasets: [{
                                label: '2xx Sukses',
                                data: d.status2xx,
                                backgroundColor: 'rgba(16,185,129,0.75)',
                                borderRadius: 3,
                                stack: 'status'
                            },
                            {
                                label: '4xx Client Error',
                                data: d.status4xx,
                                backgroundColor: 'rgba(245,158,11,0.75)',
                                borderRadius: 3,
                                stack: 'status'
                            },
                            {
                                label: '5xx Server Error',
                                data: d.status5xx,
                                backgroundColor: 'rgba(239,68,68,0.75)',
                                borderRadius: 3,
                                stack: 'status'
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    color: labelColor,
                                    boxWidth: 12,
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                stacked: true,
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                grid: {
                                    color: gridColor
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 11
                                    },
                                    precision: 0
                                }
                            },
                        },
                    },
                });
            }

            // ── Horizontal Bar: Scope comparison (Sukses vs Error) ────────────────
            const scopeCompCtx = document.getElementById('scopeCompChart');
            if (scopeCompCtx) {
                new Chart(scopeCompCtx, {
                    type: 'bar',
                    data: {
                        labels: d.scopeCompLabels,
                        datasets: [{
                                label: 'Sukses',
                                data: d.scopeCompSuccess,
                                backgroundColor: 'rgba(16,185,129,0.75)',
                                borderRadius: 4,
                                borderSkipped: false
                            },
                            {
                                label: 'Error',
                                data: d.scopeCompErrors,
                                backgroundColor: 'rgba(239,68,68,0.75)',
                                borderRadius: 4,
                                borderSkipped: false
                            },
                        ],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    color: labelColor,
                                    boxWidth: 12,
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: {
                                    color: gridColor
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 11
                                    },
                                    precision: 0
                                }
                            },
                            y: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                        },
                    },
                });
            }

            // ── Line + Bar: Token diterbitkan ─────────────────────────────────────
            const tokenCtx = document.getElementById('tokenChart');
            if (tokenCtx) {
                new Chart(tokenCtx, {
                    type: 'bar',
                    data: {
                        labels: d.tokenLabels,
                        datasets: [{
                                label: 'Token Terbit',
                                data: d.tokenCounts,
                                backgroundColor: 'rgba(99,102,241,0.55)',
                                borderRadius: 4,
                                borderSkipped: false,
                                yAxisID: 'y',
                            },
                            {
                                label: 'Kumulatif',
                                data: d.tokenCumulative,
                                type: 'line',
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245,158,11,0.08)',
                                fill: true,
                                tension: 0.4,
                                pointRadius: 2,
                                yAxisID: 'y2',
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    color: labelColor,
                                    boxWidth: 12,
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 10
                                    },
                                    maxTicksLimit: 15
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: gridColor
                                },
                                ticks: {
                                    color: labelColor,
                                    font: {
                                        size: 11
                                    },
                                    precision: 0
                                },
                                title: {
                                    display: true,
                                    text: 'Per Hari',
                                    color: labelColor,
                                    font: {
                                        size: 10
                                    }
                                }
                            },
                            y2: {
                                beginAtZero: true,
                                position: 'right',
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#f59e0b',
                                    font: {
                                        size: 11
                                    },
                                    precision: 0
                                },
                                title: {
                                    display: true,
                                    text: 'Kumulatif',
                                    color: '#f59e0b',
                                    font: {
                                        size: 10
                                    }
                                }
                            },
                        },
                    },
                });
            }
        }

        document.addEventListener('DOMContentLoaded', initApiSummaryCharts);
        document.addEventListener('livewire:navigated', initApiSummaryCharts);

        // Saat Livewire refresh: canvas dipreserve wire:ignore, JSON data sudah ter-update.
        // Destroy instance lama (pada canvas yang sama) lalu init ulang dengan data baru.
        document.addEventListener('livewire:updated', () => {
            if (!document.getElementById('api-summary-chart-data')) return;
            destroyApiSummaryCharts();
            initApiSummaryCharts();
        });
    </script>
@endPushOnce
