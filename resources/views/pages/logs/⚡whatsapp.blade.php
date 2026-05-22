<?php

use App\Models\WaGateway\Waha\WahaLog;
use App\Models\WaGateway\Gowa\GowaLog;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Log WhatsApp')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterGateway = '';

    #[Url]
    public string $filterDirection = '';

    #[Url]
    public string $filterStatus = '';

    // Detail modal
    public bool $showDetailModal = false;
    public ?string $detailLogId = null;
    public string $detailLogGateway = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterGateway(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDirection(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function viewDetail(string $id, string $gateway): void
    {
        $this->detailLogId = $id;
        $this->detailLogGateway = $gateway;
        $this->showDetailModal = true;
    }

    private function buildQuery(string $model, string $gatewayLabel)
    {
        return $model
            ::query()
            ->select(['id', 'direction', 'phone', 'type', 'payload', 'response', 'status', 'error_message', 'created_at', \Illuminate\Support\Facades\DB::raw("'{$gatewayLabel}' as gateway")])
            ->when($this->search, fn($q) => $q->where('phone', 'like', "%{$this->search}%"))
            ->when($this->filterDirection, fn($q) => $q->where('direction', $this->filterDirection))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus));
    }

    public function with(): array
    {
        $showWaha = !$this->filterGateway || $this->filterGateway === 'waha';
        $showGowa = !$this->filterGateway || $this->filterGateway === 'gowa';

        if ($showWaha && $showGowa) {
            $wahaQuery = $this->buildQuery(WahaLog::class, 'waha');
            $gowaQuery = $this->buildQuery(GowaLog::class, 'gowa');
            $query = $wahaQuery->union($gowaQuery)->orderByDesc('created_at');
        } elseif ($showWaha) {
            $query = $this->buildQuery(WahaLog::class, 'waha')->orderByDesc('created_at');
        } else {
            $query = $this->buildQuery(GowaLog::class, 'gowa')->orderByDesc('created_at');
        }

        $logs = $query->paginate(20);

        // Detail log
        $detailLog = null;
        if ($this->detailLogId && $this->detailLogGateway) {
            $model = $this->detailLogGateway === 'waha' ? WahaLog::class : GowaLog::class;
            $detailLog = $model::find($this->detailLogId);
        }

        return [
            'logs' => $logs,
            'detailLog' => $detailLog,
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Log WhatsApp" subtitle="Riwayat request/response API WhatsApp (WAHA & GOWA)">
        <x-slot:actions>
            <x-atoms.button variant="ghost" icon="arrow-path" wire:click="$refresh">Refresh</x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Filter bar --}}
    <x-organisms.data-panel title="Log Sistem WhatsApp" subtitle="Riwayat pesan masuk dan keluar">
        <x-slot:filter>
            <div class="flex flex-wrap gap-3">
                <div class="flex-1 min-w-48">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari pesan, nomor, nama…"
                        icon="magnifying-glass" clearable size="sm" />
                </div>
                <flux:select wire:model.live="filterGateway" class="w-36" size="sm">
                    <flux:select.option value="">Semua Gateway</flux:select.option>
                    <flux:select.option value="waha">WAHA</flux:select.option>
                    <flux:select.option value="gowa">GOWA</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="filterDirection" class="w-36" size="sm">
                    <flux:select.option value="">Semua Arah</flux:select.option>
                    <flux:select.option value="outgoing">Keluar</flux:select.option>
                    <flux:select.option value="incoming">Masuk</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="filterStatus" class="w-36" size="sm">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="success">Sukses</flux:select.option>
                    <flux:select.option value="failed">Gagal</flux:select.option>
                </flux:select>
                @if ($search || $filterGateway || $filterDirection || $filterStatus)
                    <x-atoms.button wire:click="$set('search', '')" variant="ghost" icon="x-mark" size="sm">Reset</x-atoms.button>
                @endif
            </div>
        </x-slot:filter>

        <div class="overflow-x-auto">
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="w-36">Waktu</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-24">Gateway</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-24">Arah</x-atoms.table-heading>
                    <x-atoms.table-heading>Nomor</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-24">Tipe</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-24" align="center">Status</x-atoms.table-heading>
                    <x-atoms.table-heading>Error</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-16" align="center">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($logs as $log)
                    <x-molecules.table-row wire:key="{{ $log->gateway }}-{{ $log->id }}">
                        <x-atoms.table-cell :nowrap="true">
                            <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300">
                                {{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y') }}</p>
                            <p class="text-[11px] text-zinc-400 dark:text-primary-dark-500">
                                {{ \Carbon\Carbon::parse($log->created_at)->format('H:i:s') }}</p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <flux:badge :color="$log->gateway === 'waha' ? 'blue' : 'emerald'" size="sm">
                                {{ strtoupper($log->gateway) }}
                            </flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <flux:badge :color="$log->direction === 'outgoing' ? 'sky' : 'lime'" size="sm">
                                {{ $log->direction === 'outgoing' ? 'Keluar' : 'Masuk' }}
                            </flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :nowrap="true">
                            <span class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                {{ $log->phone ?? '-' }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <span class="text-xs text-zinc-700 dark:text-primary-dark-300">{{ ucfirst($log->type) }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" :nowrap="true">
                            <x-atoms.status-badge :status="$log->status" />
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="max-w-xs">
                            @if ($log->error_message)
                                <p class="text-xs text-red-500 truncate" title="{{ $log->error_message }}">
                                    {{ Str::limit($log->error_message, 50) }}</p>
                            @else
                                <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :action="true" align="center">
                            <x-atoms.button variant="ghost" size="sm" icon="eye" tooltip="Detail"
                                wire:click="viewDetail('{{ $log->id }}', '{{ $log->gateway }}')" />
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="8" class="py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                    <flux:icon name="document-text" class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                </div>
                                <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Belum ada log WhatsApp</p>
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

    {{-- Detail Modal --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="2xl" title="">
        @if ($detailLog)
            <div class="space-y-6">
                <flux:heading size="lg">Detail Log</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Gateway</span>
                        <flux:badge :color="$detailLogGateway === 'waha' ? 'blue' : 'emerald'" size="sm">
                            {{ strtoupper($detailLogGateway) }}
                        </flux:badge>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Waktu</span>
                        <span
                            class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ $detailLog->created_at->format('d/m/Y H:i:s') }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Arah</span>
                        <flux:badge :color="$detailLog->direction === 'outgoing' ? 'sky' : 'lime'" size="sm">
                            {{ $detailLog->direction === 'outgoing' ? 'Keluar' : 'Masuk' }}
                        </flux:badge>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Nomor</span>
                        <span
                            class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ $detailLog->phone ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Tipe</span>
                        <span
                            class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ ucfirst($detailLog->type) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Status</span>
                        <x-atoms.status-badge :status="$detailLog->status" />
                    </div>

                    @if ($detailLog->error_message)
                        <div class="py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                            <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Error</span>
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $detailLog->error_message }}</p>
                        </div>
                    @endif

                    <div class="py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400 mb-2 block">Payload</span>
                        <x-atoms.code-block language="json" maxHeight="max-h-60">{{ json_encode($detailLog->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </div>

                    @if ($detailLog->response)
                        <div class="py-2">
                            <span class="text-sm text-zinc-500 dark:text-primary-dark-400 mb-2 block">Response</span>
                            <x-atoms.code-block language="json" maxHeight="max-h-60">{{ json_encode($detailLog->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end">
                    <x-atoms.button variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</x-atoms.button>
                </div>
            </div>
        @endif
    
    </x-organisms.modal>
</div>
