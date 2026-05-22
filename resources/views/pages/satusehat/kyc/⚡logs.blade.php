<?php

use App\Models\SatuSehat\KycLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Riwayat KYC')] class extends Component {
    use WithPagination;

    public string $filterType = '';
    public string $filterStatus = '';
    public string $filterDate = '';
    public string $search = '';
    public int $perPage = 25;

    public function updatedFilterType(): void
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
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $logs = KycLog::query()
            ->when($this->filterType, fn($q) => $q->where('kyc_type', $this->filterType))
            ->when($this->filterStatus !== '', fn($q) => $q->where('is_success', (bool) $this->filterStatus))
            ->when($this->filterDate, fn($q) => $q->whereDate('created_at', $this->filterDate))
            ->when(
                $this->search,
                fn($q) => $q->where(function ($sq) {
                    $sq->where('agent_name', 'like', "%{$this->search}%")
                        ->orWhere('agent_nik', 'like', "%{$this->search}%")
                        ->orWhere('patient_nik', 'like', "%{$this->search}%")
                        ->orWhere('patient_name', 'like', "%{$this->search}%")
                        ->orWhere('ihs_number', 'like', "%{$this->search}%");
                }),
            )
            ->latest()
            ->paginate($this->perPage);

        return ['logs' => $logs];
    }
}; ?>
<div>
    <x-ui.page-header title="Riwayat KYC" subtitle="Log semua aktivitas verifikasi identitas pasien"
        backUrl="{{ route('satusehat.kyc.generate-url') }}" />

    {{-- Filter Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex-1 min-w-48">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Cari petugas, NIK, IHS..." clearable />
        </div>

        <flux:select wire:model.live="filterType" class="w-44">
            <flux:select.option value="">Semua Tipe</flux:select.option>
            <flux:select.option value="generate_url">Generate URL</flux:select.option>
            <flux:select.option value="challenge_code">Challenge Code</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="filterStatus" class="w-36">
            <flux:select.option value="">Semua Status</flux:select.option>
            <flux:select.option value="1">Sukses</flux:select.option>
            <flux:select.option value="0">Gagal</flux:select.option>
        </flux:select>

        <flux:input wire:model.live="filterDate" type="date" class="w-40" />

        <flux:select wire:model.live="perPage" class="w-36">
            <flux:select.option value="25">25 / halaman</flux:select.option>
            <flux:select.option value="50">50 / halaman</flux:select.option>
            <flux:select.option value="100">100 / halaman</flux:select.option>
        </flux:select>
    </div>

    {{-- Tabel Log --}}
    <div
        class="overflow-hidden bg-white border rounded-2xl dark:bg-primary-dark-800 border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr
                        class="border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/70 dark:bg-primary-dark-900/40">
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Waktu</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Petugas</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Tipe</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Pasien</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Kode / URL</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            IHS Number</th>
                        <th
                            class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-24">
                            Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                    @forelse ($logs as $log)
                        <tr wire:key="kyc-log-{{ $log->id }}"
                            class="group transition-colors hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/20">
                            <td class="px-5 py-4 whitespace-nowrap">
                                <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300">
                                    {{ $log->created_at->format('d/m/Y') }}
                                </p>
                                <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                                    {{ $log->created_at->format('H:i:s') }}
                                </p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">
                                    {{ $log->agent_name }}
                                </p>
                                <p class="text-xs font-mono text-zinc-400 dark:text-primary-dark-500">
                                    {{ $log->agent_nik }}</p>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <flux:badge color="{{ $log->type_badge_color }}" size="sm">{{ $log->type_label }}
                                </flux:badge>
                            </td>
                            <td class="px-5 py-4">
                                @if ($log->patient_name || $log->patient_nik)
                                    <p class="text-sm text-zinc-700 dark:text-primary-dark-200">
                                        {{ $log->patient_name ?? '—' }}
                                    </p>
                                    <p class="text-xs font-mono text-zinc-400 dark:text-primary-dark-500">
                                        {{ $log->patient_nik }}</p>
                                @else
                                    <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 max-w-48">
                                @if ($log->challenge_code)
                                    <span
                                        class="font-mono text-lg font-bold tracking-widest text-purple-600 dark:text-purple-400">
                                        {{ $log->challenge_code }}
                                    </span>
                                    @if ($log->expires_at)
                                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">
                                            Exp: {{ $log->expires_at->format('H:i d/m') }}
                                        </p>
                                    @endif
                                @elseif ($log->kyc_url)
                                    <p class="text-xs font-mono text-blue-600 dark:text-blue-400 truncate"
                                        title="{{ $log->kyc_url }}">
                                        {{ Str::limit($log->kyc_url, 40) }}
                                    </p>
                                @elseif ($log->error_message)
                                    <p class="text-xs text-red-500 dark:text-red-400 leading-snug line-clamp-2">
                                        {{ $log->error_message }}
                                    </p>
                                @else
                                    <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if ($log->ihs_number)
                                    <span
                                        class="font-mono text-xs text-zinc-600 dark:text-primary-dark-300">{{ $log->ihs_number }}</span>
                                @else
                                    <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center">
                                @if ($log->is_success)
                                    <flux:badge color="green" size="sm">Sukses</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm">Gagal</flux:badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div
                                        class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                        <flux:icon name="clipboard-document-list"
                                            class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak
                                            ada
                                            riwayat KYC</p>
                                        <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">Coba ubah
                                            filter atau
                                            lakukan verifikasi KYC terlebih dahulu</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div
                class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
