<?php

use App\Models\AiLog;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Log AI Provider')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterProvider = '';

    #[Url]
    public string $filterStatus = '';

    public int $perPage = 25;

    public bool $showDetailModal = false;
    public ?string $selectedLogId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterProvider(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filterProvider', 'filterStatus']);
        $this->resetPage();
    }

    public function viewLog(string $id): void
    {
        $this->selectedLogId = $id;
        $this->showDetailModal = true;
    }

    public function with(): array
    {
        // Stats
        $total = AiLog::count();
        $success = AiLog::where('status', 'success')->count();
        $error = AiLog::where('status', 'error')->count();
        $avgMs = AiLog::avg('response_time_ms');

        // Query
        $logs = AiLog::query()
            ->when($this->search, function ($q) {
                $q->where(function ($qItem) {
                    $qItem
                        ->where('prompt_user', 'like', "%{$this->search}%")
                        ->orWhere('response', 'like', "%{$this->search}%")
                        ->orWhere('model', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterProvider, fn($q) => $q->where('provider', $this->filterProvider))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate($this->perPage);

        $hasActiveFilter = $this->search || $this->filterProvider || $this->filterStatus;
        $selectedLog = $this->selectedLogId ? AiLog::find($this->selectedLogId) : null;

        return compact('total', 'success', 'error', 'avgMs', 'logs', 'hasActiveFilter', 'selectedLog');
    }
}; ?>

<div>
    <x-ui.page-header title="Log AI Provider"
        subtitle="Riwayat eksekusi prompt AI, durasi respon, dan model yang digunakan">
        <x-slot:actions>
            <x-atoms.button variant="ghost" icon="arrow-path" wire:click="$refresh">Refresh</x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats Cards --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-organisms.stat-card title="Total Prompt" value="{{ number_format($total) }}" icon="cpu-chip" color="zinc" />
        <x-organisms.stat-card title="Sukses" value="{{ number_format($success) }}" icon="check-circle"
            color="emerald" />
        <x-organisms.stat-card title="Error Gagal" value="{{ number_format($error) }}" icon="exclamation-triangle"
            color="red" />
        <x-organisms.stat-card title="Rata-Rata Waktu" value="{{ $avgMs ? number_format($avgMs, 0) . ' ms' : '—' }}"
            icon="clock" color="violet" />
    </div>

    {{-- Data Table --}}
    <x-organisms.data-panel title="Detail Transaksi AI" :subtitle="'Total ' . number_format($logs->total()) . ' entri ditemukan'">
        <x-slot:filter>
            <div class="flex items-center gap-3">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="Cari prompt, model..." clearable class="flex-1" />
                <flux:select wire:model.live="filterProvider" class="w-40">
                    <flux:select.option value="">Semua Provider</flux:select.option>
                    <flux:select.option value="ollama">Ollama</flux:select.option>
                    <flux:select.option value="claude">Claude</flux:select.option>
                    <flux:select.option value="openai">OpenAI</flux:select.option>
                    <flux:select.option value="gemini">Gemini</flux:select.option>
                    <flux:select.option value="grok">Grok</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="filterStatus" class="w-40">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="success">Sukses</flux:select.option>
                    <flux:select.option value="error">Error</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="perPage" class="w-24">
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                </flux:select>

                @if ($hasActiveFilter)
                    <x-atoms.button wire:click="clearFilters" variant="ghost" icon="x-mark">Reset</x-atoms.button>
                @endif
            </div>
        </x-slot:filter>

        <div class="overflow-x-auto">
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Waktu</x-atoms.table-heading>
                    <x-atoms.table-heading>Provider &amp; Model</x-atoms.table-heading>
                    <x-atoms.table-heading>Base URL</x-atoms.table-heading>
                    <x-atoms.table-heading>Prompt / Response Ringkas</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden md:table-cell" align="center">Durasi
                        (ms)</x-atoms.table-heading>
                    <x-atoms.table-heading align="center">Status</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-10"></x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($logs as $log)
                    <x-molecules.table-row wire:key="log-{{ $log->id }}">
                        <x-atoms.table-cell :nowrap="true" class="text-xs text-zinc-500">
                            {{ $log->created_at->format('d/m H:i:s') }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <p class="text-xs font-bold uppercase text-zinc-700 dark:text-primary-dark-200">
                                {{ $log->provider }}
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-primary-dark-400 line-clamp-1 mt-0.5">
                                {{ $log->model }}
                            </p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell
                            class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400 line-clamp-1">
                            {{ $log->base_url ?? '—' }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <p
                                class="text-xs font-medium text-emerald-700 dark:text-emerald-500 leading-snug line-clamp-1">
                                <span class="text-zinc-400 font-normal">P:</span> {{ $log->prompt_user }}
                            </p>
                            @if ($log->status === 'success')
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-snug line-clamp-1 mt-0.5">
                                    <span class="text-zinc-400 font-normal">R:</span>
                                    {{ str_replace("\n", ' ', $log->response) }}
                                </p>
                            @else
                                <p class="text-xs text-red-500 leading-snug line-clamp-1 mt-0.5">
                                    {{ $log->error_message }}
                                </p>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="right">
                            @if ($log->response_time_ms !== null)
                                <span
                                    class="font-mono text-xs {{ $log->response_time_ms > 10000 ? 'text-red-500' : ($log->response_time_ms > 3000 ? 'text-amber-500' : 'text-zinc-500 dark:text-primary-dark-400') }}">
                                    {{ number_format($log->response_time_ms) }}
                                </span>
                            @else
                                <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" :nowrap="true">
                            <x-atoms.status-badge :status="$log->status" />
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :action="true" align="center">
                            <x-atoms.button size="sm" variant="ghost" icon="eye" tooltip="Lihat Detail"
                                wire:click="viewLog('{{ $log->id }}')" />
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="7" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon.cpu-chip class="size-8 text-zinc-300 dark:text-primary-dark-600" />
                                <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Belum ada riwayat AI yang
                                    sesuai format pencarian</p>
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
    <x-organisms.modal wire:model="showDetailModal" maxWidth="5xl" title="">
        @if ($selectedLog)
            <div class="space-y-4" wire:key="ai-detail-{{ $selectedLog->id }}" x-data="{ tabAI: 'prompt' }">
                <div class="flex items-start gap-4 border-b border-zinc-200 pb-4 dark:border-primary-dark-700">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg {{ $selectedLog->status === 'success' ? 'bg-emerald-100 dark:bg-emerald-900/50' : 'bg-red-100 dark:bg-red-900/50' }}">
                        <flux:icon name="cpu-chip"
                            class="size-6 {{ $selectedLog->status === 'success' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="break-all font-mono text-base font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ strtoupper($selectedLog->provider) }}
                        </h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <flux:badge color="zinc" size="sm">{{ $selectedLog->model }}</flux:badge>
                            <x-atoms.status-badge :status="$selectedLog->status" />
                            @if ($selectedLog->response_time_ms !== null)
                                <span class="font-mono text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                                    {{ number_format($selectedLog->response_time_ms) }} ms
                                </span>
                            @endif
                            <span class="text-xs text-zinc-400">
                                {{ $selectedLog->created_at->format('d M Y H:i:s') }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Tab AI Modal --}}
                <x-molecules.tabs-2 model="tabAI" :options="[
                    'prompt' => 'Prompt',
                    'context' => 'Endpoint & System',
                    'response' => 'Response & Error',
                ]" class="mb-4" />

                {{-- Tab User Prompt --}}
                <div x-show="tabAI === 'prompt'" class="space-y-3">
                    <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                        <div
                            class="border-b border-zinc-100 px-4 py-2.5 dark:border-primary-dark-700 flex justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">User
                                Prompt</span>
                        </div>
                        <div
                            class="p-4 bg-zinc-50 dark:bg-primary-dark-900/40 rounded-b-xl overflow-auto max-h-[60vh]">
                            <p class="whitespace-pre-wrap font-mono text-sm text-zinc-800 dark:text-zinc-200">
                                {{ $selectedLog->prompt_user }}</p>
                        </div>
                    </div>
                </div>

                {{-- Tab Context --}}
                <div x-show="tabAI === 'context'" class="space-y-3">
                    <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                        <div class="border-b border-zinc-100 px-4 py-2.5 dark:border-primary-dark-700">
                            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Base URL</span>
                        </div>
                        <div class="px-4 py-3">
                            <p class="font-mono text-sm font-bold text-zinc-700 dark:text-primary-dark-200">
                                {{ $selectedLog->base_url }}</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 mt-4">
                        <div
                            class="border-b border-zinc-100 px-4 py-2.5 dark:border-primary-dark-700 flex justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">System Prompt
                                (Context)</span>
                        </div>
                        <div
                            class="p-4 bg-zinc-50 dark:bg-primary-dark-900/40 rounded-b-xl overflow-auto max-h-[50vh]">
                            <p class="whitespace-pre-wrap font-mono text-sm text-zinc-800 dark:text-zinc-200">
                                {{ $selectedLog->prompt_system }}</p>
                        </div>
                    </div>
                </div>

                {{-- Tab Response --}}
                <div x-show="tabAI === 'response'" class="space-y-3">
                    @if ($selectedLog->status === 'success')
                        <div class="rounded-xl border border-emerald-200 dark:border-emerald-800/40">
                            <div
                                class="border-b border-emerald-100 px-4 py-2.5 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-800/40 rounded-t-xl flex justify-between items-center">
                                <span
                                    class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Response
                                    Text</span>
                            </div>
                            <div
                                class="p-4 bg-white dark:bg-primary-dark-800 rounded-b-xl overflow-auto max-h-[60vh] font-mono text-sm text-zinc-800 dark:text-zinc-200 whitespace-pre-wrap">
                                {{ $selectedLog->response }}
                            </div>
                        </div>
                    @else
                        <div class="rounded-xl border border-red-200 dark:border-red-800/40">
                            <div
                                class="border-b border-red-100 px-4 py-2.5 bg-red-50 dark:bg-red-900/20 dark:border-red-800/40 rounded-t-xl">
                                <span
                                    class="text-xs font-bold uppercase tracking-wider text-red-700 dark:text-red-400">Error
                                    Exception</span>
                            </div>
                            <div
                                class="p-4 bg-white dark:bg-primary-dark-800 rounded-b-xl overflow-auto max-h-[60vh] font-mono text-sm text-red-600 dark:text-red-400 whitespace-pre-wrap">
                                {{ $selectedLog->error_message }}
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-3">
                    <x-atoms.button wire:click="$set('showDetailModal', false)" variant="ghost">Tutup</x-atoms.button>
                </div>
            </div>
        @endif

    </x-organisms.modal>
</div>
