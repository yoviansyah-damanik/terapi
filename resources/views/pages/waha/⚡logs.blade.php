<?php

use App\Models\WaGateway\Waha\WahaLog;
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
    public string $filterDirection = '';

    #[Url]
    public string $filterStatus = '';

    // Detail modal
    public bool $showDetailModal = false;
    public ?WahaLog $detailLog = null;

    public function updatedSearch(): void
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

    /**
     * Lihat detail log
     */
    public function viewDetail(string $id): void
    {
        $this->detailLog = WahaLog::findOrFail($id);
        $this->showDetailModal = true;
    }

    public function with(): array
    {
        $query = WahaLog::query()->when($this->search, fn($q) => $q->where('phone', 'like', "%{$this->search}%"))->when($this->filterDirection, fn($q) => $q->where('direction', $this->filterDirection))->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))->orderByDesc('created_at');

        return [
            'logs' => $query->paginate(20),
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Log WhatsApp" subtitle="Riwayat request/response API WhatsApp (WAHA)" />

    {{-- Filter --}}
    <div class="p-4 mb-6 bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="md:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nomor telepon..."
                    icon="magnifying-glass" />
            </div>
            <flux:select wire:model.live="filterDirection">
                <flux:select.option value="">Semua Arah</flux:select.option>
                <flux:select.option value="outgoing">Keluar</flux:select.option>
                <flux:select.option value="incoming">Masuk</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="filterStatus">
                <flux:select.option value="">Semua Status</flux:select.option>
                <flux:select.option value="success">Sukses</flux:select.option>
                <flux:select.option value="failed">Gagal</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Tabel Log --}}
    <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Waktu</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Arah</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Nomor</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Tipe</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Status</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Error</th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-right uppercase text-zinc-500 dark:text-primary-dark-400">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                    @forelse ($logs as $log)
                        <tr :key="$log->id" class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-6 py-4 text-sm whitespace-nowrap text-zinc-500 dark:text-primary-dark-400">
                                {{ $log->created_at->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge :color="$log->direction === 'outgoing' ? 'blue' : 'lime'" size="sm">
                                    {{ $log->direction === 'outgoing' ? 'Keluar' : 'Masuk' }}
                                </flux:badge>
                            </td>
                            <td
                                class="px-6 py-4 text-sm font-medium whitespace-nowrap text-zinc-900 dark:text-primary-dark-100">
                                {{ $log->phone ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap text-zinc-700 dark:text-primary-dark-300">
                                {{ ucfirst($log->type) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge :color="$log->status === 'success' ? 'green' : 'red'" size="sm">
                                    {{ $log->status === 'success' ? 'Sukses' : 'Gagal' }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 text-sm text-red-500">
                                {{ Str::limit($log->error_message, 40) ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-right whitespace-nowrap">
                                <x-atoms.button variant="ghost" size="sm" icon="eye"
                                    wire:click="viewDetail('{{ $log->id }}')" title="Detail" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="document-text"
                                        class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Belum ada log</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="px-6 py-4 border-t border-zinc-200 dark:border-primary-dark-700">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    {{-- Detail Modal --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="2xl" title="">
        @if ($detailLog)
            <div class="space-y-6">
                <flux:heading size="lg">Detail Log</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Waktu</span>
                        <span
                            class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ $detailLog->created_at->format('d/m/Y H:i:s') }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Arah</span>
                        <flux:badge :color="$detailLog->direction === 'outgoing' ? 'blue' : 'lime'" size="sm">
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
                        <flux:badge :color="$detailLog->status === 'success' ? 'green' : 'red'" size="sm">
                            {{ $detailLog->status === 'success' ? 'Sukses' : 'Gagal' }}
                        </flux:badge>
                    </div>

                    @if ($detailLog->error_message)
                        <div class="py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                            <span class="text-sm text-zinc-500 dark:text-primary-dark-400">Error</span>
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $detailLog->error_message }}</p>
                        </div>
                    @endif

                    {{-- Payload --}}
                    <div class="py-2 border-b border-zinc-100 dark:border-primary-dark-700">
                        <span class="text-sm text-zinc-500 dark:text-primary-dark-400 mb-2 block">Payload</span>
                        <x-atoms.code-block language="json" maxHeight="max-h-60">{{ json_encode($detailLog->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </div>

                    {{-- Response --}}
                    @if ($detailLog->response)
                        <div class="py-2">
                            <span class="text-sm text-zinc-500 dark:text-primary-dark-400 mb-2 block">Response</span>
                            <x-atoms.code-block language="json" maxHeight="max-h-60">{{ json_encode($detailLog->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end">
                    <x-atoms.button variant="ghost" wire:click="$set('showDetailModal', false)">
                        Tutup
                    </x-atoms.button>
                </div>
            </div>
        @endif
    
    </x-organisms.modal>
</div>
