<?php

use App\Models\SatuSehat\SatuSehatBundle;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

new #[Lazy] class extends Component {
    use WithPagination;

    #[Url(as: 'bundle_search')]
    public string $bundleSearch = '';
    #[Url(as: 'bundle_status')]
    public string $bundleStatus = '';
    #[Url(as: 'bundle_date')]
    public string $bundleDate = '';
    #[Url(as: 'bundle_per_page')]
    public int $bundlePerPage = 25;

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

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('pages::logs._satusehat._skeleton-tab');
    }

    public function with(): array
    {
        $query = SatuSehatBundle::query()
            ->with(['triggeredBy', 'items' => fn($q) => $q->orderBy('created_at')])
            ->when($this->bundleSearch, fn($q) => $q->where('no_rawat', 'like', "%{$this->bundleSearch}%"))
            ->when($this->bundleStatus, fn($q) => $q->where('status', $this->bundleStatus))
            ->when($this->bundleDate === 'today', fn($q) => $q->whereDate('created_at', today()))
            ->when($this->bundleDate === 'week', fn($q) => $q->where('created_at', '>=', now()->startOfWeek()))
            ->when($this->bundleDate === 'month', fn($q) => $q->where('created_at', '>=', now()->startOfMonth()))
            ->latest();

        return [
            'bundles' => $query->paginate($this->bundlePerPage, ['*'], 'bundlePage'),
            'bundleStats' => [
                'total' => SatuSehatBundle::count(),
                'completed' => SatuSehatBundle::byStatus(SatuSehatBundle::STATUS_COMPLETED)->count(),
                'partial' => SatuSehatBundle::byStatus(SatuSehatBundle::STATUS_PARTIAL)->count(),
                'failed' => SatuSehatBundle::byStatus(SatuSehatBundle::STATUS_FAILED)->count(),
            ],
        ];
    }
};
?>

<div>
    <div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-4">
        <x-organisms.stat-card title="Total Bundle" value="{{ number_format($bundleStats['total']) }}"
            icon="paper-airplane" color="zinc" />
        <x-organisms.stat-card title="Selesai" value="{{ number_format($bundleStats['completed']) }}" icon="check-circle"
            color="emerald" />
        <x-organisms.stat-card title="Sebagian Gagal" value="{{ number_format($bundleStats['partial']) }}"
            icon="exclamation-triangle" color="amber" />
        <x-organisms.stat-card title="Gagal" value="{{ number_format($bundleStats['failed']) }}" icon="x-circle"
            color="red" />
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
                        <span class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ $bundle->total_sent }}
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
                            <span class="font-bold {{ $tagClass }} shrink-0 w-10">[{{ $tag }}]</span>
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

</div>
