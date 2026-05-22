<?php

use App\Models\Bpjs\BpjsLog;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('BPJS — Log')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterService = '';

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $filterDate = '';

    public ?string $viewLogId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    public function updatingFilterService(): void
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

    public function with(): array
    {
        $query = BpjsLog::orderByDesc('created_at');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('no_rawat', 'like', "%{$this->search}%")->orWhere('no_sep', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterService) {
            $query->where('service', $this->filterService);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterDate) {
            $query->whereDate('created_at', $this->filterDate);
        }

        $stats = BpjsLog::selectRaw(
            "
            COUNT(*) as total,
            SUM(status = 'success') as success,
            SUM(status = 'failed') as failed,
            SUM(status = 'pending') as pending
        ",
        )->first();

        $services = BpjsLog::distinct()->orderBy('service')->pluck('service');

        $viewLog = $this->viewLogId ? BpjsLog::find($this->viewLogId) : null;

        return [
            'logs' => $query->paginate(25),
            'stats' => $stats,
            'services' => $services,
            'viewLog' => $viewLog,
        ];
    }
}; ?>

<div @open-log-detail.window="$flux.modal('log-detail').show()">
    <x-ui.page-header title="BPJS — Log" subtitle="Riwayat pengiriman dan aktivitas layanan BPJS Kesehatan">
        <x-slot:actions>
            <x-atoms.button variant="ghost" icon="arrow-path" wire:click="$refresh">Refresh</x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-4">
        @php
            $statCards = [
                ['label' => 'Total', 'value' => $stats->total ?? 0, 'color' => 'zinc', 'icon' => 'queue-list'],
                [
                    'label' => 'Berhasil',
                    'value' => $stats->success ?? 0,
                    'color' => 'emerald',
                    'icon' => 'check-circle',
                ],
                ['label' => 'Gagal', 'value' => $stats->failed ?? 0, 'color' => 'red', 'icon' => 'x-circle'],
                ['label' => 'Pending', 'value' => $stats->pending ?? 0, 'color' => 'amber', 'icon' => 'clock'],
            ];
        @endphp
        @foreach ($statCards as $card)
            <x-organisms.stat-card title="{{ $card['label'] }}" value="{{ number_format($card['value']) }}"
                icon="{{ $card['icon'] }}" color="{{ $card['color'] }}" />
        @endforeach
    </div>

    {{-- Data Panel --}}
    <x-organisms.data-panel title="Log Sistem BPJS" subtitle="Riwayat interaksi web service">
        <x-slot:filter>
            <div class="flex gap-3">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari No. Rawat / No. SEP…"
                    icon="magnifying-glass" clearable class="flex-1" />
                <flux:select wire:model.live="filterService" class="w-40">
                    <flux:select.option value="">Semua Service</flux:select.option>
                    @foreach ($services as $svc)
                        <flux:select.option value="{{ $svc }}">{{ strtoupper($svc) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterStatus" class="w-40">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="success">Berhasil</flux:select.option>
                    <flux:select.option value="failed">Gagal</flux:select.option>
                    <flux:select.option value="pending">Pending</flux:select.option>
                </flux:select>
                <flux:input wire:model.live="filterDate" type="date" class="w-44!" />
                @if ($search || $filterService || $filterStatus || $filterDate)
                    <x-atoms.button
                        wire:click="$set('search', ''); $set('filterService', ''); $set('filterStatus', ''); $set('filterDate', '')"
                        variant="ghost" icon="x-mark">
                        Reset
                    </x-atoms.button>
                @endif
            </div>
        </x-slot:filter>

        <div class="overflow-x-auto">
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="w-36">Waktu</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-24">Service</x-atoms.table-heading>
                    <x-atoms.table-heading>Path</x-atoms.table-heading>
                    <x-atoms.table-heading>No. Rawat/No. SEP</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden lg:table-cell">Keterangan</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-24" align="center">Status</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-16" align="center">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($logs as $log)
                    <x-molecules.table-row :key="$log->id">
                        <x-atoms.table-cell :nowrap="true">
                            <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300">
                                {{ $log->created_at?->format('d/m/Y') }}</p>
                            <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500">
                                {{ $log->created_at?->format('H:i:s') }}</p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <span
                                class="font-mono text-xs font-semibold text-primary-600 dark:text-primary-400 uppercase">
                                {{ $log->service }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <x-atoms.method-badge :method="$log->method ?? 'POST'" />
                            <span
                                class="text-xs font-mono text-zinc-500 dark:text-primary-dark-400">{{ $log->endpoint }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <span
                                class="font-mono text-xs text-zinc-700 dark:text-primary-dark-300">{{ $log->no_rawat ?? '—' }}</span>
                            <span
                                class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400">{{ $log->no_sep ?? '—' }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden lg:table-cell max-w-xs">
                            @if ($log->error_message)
                                <p class="text-xs text-red-600 dark:text-red-400 truncate"
                                    title="{{ $log->error_message }}">
                                    {{ $log->error_message }}</p>
                            @elseif ($log->endpoint)
                                <p class="font-mono text-[11px] text-zinc-400 dark:text-primary-dark-500 truncate"
                                    title="{{ $log->endpoint }}">{{ $log->endpoint }}</p>
                            @elseif ($log->status === 'success' && $log->sent_at)
                                <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                                    Dikirim {{ $log->sent_at->format('d/m/Y H:i') }}</p>
                            @else
                                <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center">
                            <x-atoms.status-badge :status="$log->status" />
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
                                    @if ($search || $filterService || $filterStatus || $filterDate)
                                        Tidak ada log yang sesuai filter.
                                    @else
                                        Belum ada log aktivitas BPJS.
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
    <x-organisms.modal name="log-detail" @close="$wire.set('viewLogId', null)" maxWidth="3xl" title="">
        @if ($viewLog)
            <div class="space-y-4" x-data="{ tab: 'response' }">
                {{-- Header --}}
                <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div>
                        <div class="flex items-center flex-wrap gap-2 mb-1">
                            <x-atoms.status-badge :status="$viewLog->status" />
                            <span
                                class="font-mono text-xs font-bold text-primary-600 dark:text-primary-400 uppercase">{{ $viewLog->service }}</span>
                        </div>
                        <div class="flex flex-col gap-1 mt-1">
                            @if ($viewLog->endpoint)
                                <div class="flex items-center gap-1.5 text-xs">
                                    <x-atoms.method-badge :method="$viewLog->method ?? 'POST'" />
                                    <code
                                        class="font-mono text-zinc-500 dark:text-primary-dark-400 break-all">{{ $viewLog->endpoint }}</code>
                                </div>
                            @endif
                            <div
                                class="flex items-center gap-3 text-xs text-zinc-400 dark:text-primary-dark-500 [&>*:not(:first-child)]:before:content-['·'] [&>*:not(:first-child)]:before:mr-1.5 [&>*:not(:first-child)]:before:ml-0">
                                @if ($viewLog->no_rawat)
                                    <span class="font-mono">{{ $viewLog->no_rawat }}</span>
                                @endif
                                @if ($viewLog->no_sep)
                                    <span class="font-mono">{{ $viewLog->no_sep }}</span>
                                @endif
                                <span>{{ $viewLog->created_at?->format('d/m/Y H:i:s') }}</span>
                            </div>
                        </div>
                        @if ($viewLog->error_message)
                            <p
                                class="mt-2 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-2.5 py-1.5 rounded-lg border border-red-200 dark:border-red-800">
                                {{ $viewLog->error_message }}</p>
                        @endif
                    </div>
                </div>

                {{-- Tab selector --}}
                @php
                    $rawTabs = [
                        'response' => [
                            'label' => 'Response',
                            'data' => is_string($viewLog->response_payload)
                                ? json_decode($viewLog->response_payload, true) ?? $viewLog->response_payload
                                : $viewLog->response_payload,
                        ],
                        'request' => [
                            'label' => 'Request',
                            'data' => is_string($viewLog->request_payload)
                                ? json_decode($viewLog->request_payload, true) ?? $viewLog->request_payload
                                : $viewLog->request_payload,
                        ],
                        'bundle' => [
                            'label' => 'Bundle',
                            'data' => is_string($viewLog->bundle)
                                ? json_decode($viewLog->bundle, true) ?? $viewLog->bundle
                                : $viewLog->bundle,
                        ],
                    ];
                    $tabs = array_filter($rawTabs, fn($t) => !empty($t['data']));

                    $tabOptions = [];
                    foreach ($tabs as $key => $tabData) {
                        $tabOptions[$key] = $tabData['label'];
                    }
                @endphp
                @if (count($tabOptions) > 1)
                    <x-molecules.tabs-2 model="tab" :options="$tabOptions" />
                @endif


                {{-- Payload viewers --}}
                @foreach ($tabs as $tabKey => $tab)
                    <div x-show="tab === '{{ $tabKey }}'">
                        <x-atoms.code-block language="json" maxHeight="max-h-96">{{ json_encode($tab['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                        <div class="flex justify-end mt-3">
                            <x-atoms.button size="sm" icon="x-mark" variant="ghost"
                                x-on:click="$flux.modal('log-detail').close()">Tutup</x-atoms.button>
                        </div>
                    </div>
                @endforeach

                @if (empty($tabs))
                    <p class="text-sm text-center text-zinc-400 dark:text-primary-dark-500 py-8">Tidak ada payload
                        untuk log
                        ini.</p>
                    <div class="flex justify-end">
                        <x-atoms.button size="sm" variant="ghost" x-on:click="$flux.modal('log-detail').close()">
                            Tutup</x-atoms.button>
                    </div>
                @endif
            </div>
        @endif
    
    </x-organisms.modal>
</div>
