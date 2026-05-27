<?php

use App\Models\SatuSehat\SatuSehatLog;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

new #[Lazy] class extends Component {
    use WithPagination;

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

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('pages::logs._satusehat._skeleton-tab');
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

        return [
            'logs' => $query->paginate($this->perPage),
            'resourceTypes' => SatuSehatLog::getResourceTypes(),
            'actions' => SatuSehatLog::getActions(),
        ];
    }
};
?>

<div>
    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-5">
        <x-organisms.stat-card title="Total Request"
            value="{{ number_format(App\Models\SatuSehat\SatuSehatLog::count()) }}" icon="document-text" color="zinc" />
        <x-organisms.stat-card title="Sukses"
            value="{{ number_format(App\Models\SatuSehat\SatuSehatLog::successful()->count()) }}" icon="check-circle"
            color="emerald" />
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
                                <div class="text-xs w-96 truncate font-mono text-zinc-500 dark:text-primary-dark-400">
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


    <x-organisms.modal wire:model="showDetailModal" maxWidth="4xl" title="">
        @if ($selectedLog)
            <div class="space-y-6">
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

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">Endpoint
                        </h4>
                        <p class="mt-1 font-mono text-sm break-all text-zinc-900 dark:text-primary-dark-100">
                            {{ $selectedLog->endpoint }}</p>
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
                                Number</h4>
                            <p class="mt-1 font-mono text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedLog->ihs_number }}</p>
                        </div>
                    @endif
                    @if ($selectedLog->patient_nik)
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                                Patient NIK</h4>
                            <p class="mt-1 font-mono text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedLog->patient_nik }}</p>
                        </div>
                    @endif
                    @if ($selectedLog->user)
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">User
                            </h4>
                            <p class="mt-1 text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedLog->user->name }}</p>
                        </div>
                    @endif
                    @if ($selectedLog->ip_address)
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">IP
                                Address</h4>
                            <p class="mt-1 font-mono text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedLog->ip_address }}</p>
                        </div>
                    @endif
                </div>

                @if ($selectedLog->error_message)
                    <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                        <h4 class="text-xs font-semibold uppercase text-red-600 dark:text-red-400">Error Message</h4>
                        <p class="mt-1 text-sm text-red-800 dark:text-red-200">{{ $selectedLog->error_message }}</p>
                    </div>
                @endif
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

                <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                    <x-atoms.button variant="ghost" wire:click="closeDetail">Tutup</x-atoms.button>
                </div>
            </div>
        @endif
    </x-organisms.modal>
</div>
