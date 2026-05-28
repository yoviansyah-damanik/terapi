<?php

use App\Models\Simrs\Poliklinik;
use App\Models\Simrs\RegPeriksa;
use App\Models\Bpjs\BpjsLog;
use App\Models\SatuSehat\SatuSehatEncounter;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('eRM — Rawat Jalan')] #[Lazy] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $filterClinic = '';
    #[Url]
    public string $filterStartDate = '';
    #[Url]
    public string $filterEndDate = '';
    #[Url]
    public string $filterStatusBpjs = '';
    #[Url]
    public string $filterStatusSs = '';
    #[Url]
    public int $perPage = 15;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterClinic(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStartDate(): void
    {
        $this->resetPage();
    }
    public function updatedFilterEndDate(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatusBpjs(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatusSs(): void
    {
        $this->resetPage();
    }
    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('placeholders._erm-list');
    }

    public function with(): array
    {
        // Kode poliklinik IGD — dieksklusi dari Rawat Jalan
        $igdCodes = Poliklinik::where(function ($q) {
            $q->where('nm_poli', 'like', '%gawat%')->orWhere('kd_poli', 'like', '%igd%')->orWhere('kd_poli', 'like', '%ugd%');
        })
            ->pluck('kd_poli')
            ->toArray();

        $sentBpjs = BpjsLog::forService('erm')->where('status', 'success')->pluck('no_rawat')->toArray();
        $failedBpjs = BpjsLog::forService('erm')->where('status', 'failed')->pluck('no_rawat')->toArray();
        $pendingBpjs = BpjsLog::forService('erm')->where('status', 'pending')->pluck('no_rawat')->toArray();
        $ssStatuses = SatuSehatEncounter::pluck('status', 'local_id')->toArray();

        $query = RegPeriksa::query()
            ->with(['pasien', 'dokter', 'poliklinik', 'penjab'])
            ->where('status_lanjut', 'Ralan')
            ->whereNotIn('kd_poli', $igdCodes)
            ->search($this->search)
            ->filterTanggalRange($this->filterStartDate, $this->filterEndDate)
            ->filterPoli($this->filterClinic);

        if ($this->filterStatusBpjs === 'sent') {
            $query->whereIn('no_rawat', $sentBpjs);
        } elseif ($this->filterStatusBpjs === 'failed') {
            $query->whereIn('no_rawat', $failedBpjs);
        } elseif ($this->filterStatusBpjs === 'pending') {
            $query->whereIn('no_rawat', $pendingBpjs);
        } elseif ($this->filterStatusBpjs === 'not_sent') {
            $query->whereNotIn('no_rawat', array_merge($sentBpjs, $failedBpjs, $pendingBpjs));
        }

        if ($this->filterStatusSs === 'finished') {
            $query->whereIn('no_rawat', array_keys(array_filter($ssStatuses, fn($s) => $s === 'finished')));
        } elseif ($this->filterStatusSs === 'in-progress') {
            $query->whereIn('no_rawat', array_keys(array_filter($ssStatuses, fn($s) => $s === 'in-progress')));
        } elseif ($this->filterStatusSs === 'not_sent') {
            $query->whereNotIn('no_rawat', array_keys($ssStatuses));
        }

        $query->orderByDesc('tgl_registrasi')->orderByDesc('jam_reg');

        return [
            'registrations' => $query->paginate($this->perPage),
            'clinics' => Poliklinik::active()->whereNotIn('kd_poli', $igdCodes)->orderBy('nm_poli')->get(),
            'sentBpjs' => $sentBpjs,
            'failedBpjs' => $failedBpjs,
            'pendingBpjs' => $pendingBpjs,
            'ssStatuses' => $ssStatuses,
        ];
    }
};
?>

<div>
    <x-ui.page-header title="eRM — Rawat Jalan"
        subtitle="Status pengiriman rekam medis elektronik rawat jalan ke BPJS dan Satu Sehat" />

    {{-- Filter --}}
    <div class="p-4 mb-6 bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="grid items-end grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari no rawat, RM, nama, no SEP..."
                    icon="magnifying-glass" />
            </div>
            <flux:input type="date" wire:model.live="filterStartDate" label="Tanggal Mulai" />
            <flux:input type="date" wire:model.live="filterEndDate" label="Tanggal Selesai" />
        </div>
        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:select wire:model.live="filterClinic">
                <flux:select.option value="">Semua Poliklinik</flux:select.option>
                @foreach ($clinics as $poli)
                    <flux:select.option value="{{ $poli->kd_poli }}">{{ $poli->nm_poli }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="filterStatusBpjs">
                <flux:select.option value="">Status eRM BPJS</flux:select.option>
                <flux:select.option value="sent">Terkirim</flux:select.option>
                <flux:select.option value="pending">Menunggu</flux:select.option>
                <flux:select.option value="failed">Gagal</flux:select.option>
                <flux:select.option value="not_sent">Belum</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="filterStatusSs">
                <flux:select.option value="">Status Satu Sehat</flux:select.option>
                <flux:select.option value="finished">Selesai</flux:select.option>
                <flux:select.option value="in-progress">Dalam Proses</flux:select.option>
                <flux:select.option value="not_sent">Belum Dikirim</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="perPage">
                <flux:select.option value="15">15 per halaman</flux:select.option>
                <flux:select.option value="25">25 per halaman</flux:select.option>
                <flux:select.option value="50">50 per halaman</flux:select.option>
                <flux:select.option value="100">100 per halaman</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Tanggal</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            No. Rawat</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Pasien</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase md:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Poliklinik</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase lg:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Dokter</th>
                        <th
                            class="hidden px-4 py-3 text-xs font-medium tracking-wider text-left uppercase sm:table-cell text-zinc-500 dark:text-primary-dark-400">
                            Jenis Bayar</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-center uppercase text-zinc-500 dark:text-primary-dark-400">
                            eRM BPJS</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-center uppercase text-zinc-500 dark:text-primary-dark-400">
                            Satu Sehat</th>
                        <th
                            class="px-4 py-3 text-xs font-medium tracking-wider text-center uppercase text-zinc-500 dark:text-primary-dark-400">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                    @forelse ($registrations as $reg)
                        @php
                            $isSentBpjs = in_array($reg->no_rawat, $sentBpjs);
                            $isFailedBpjs = in_array($reg->no_rawat, $failedBpjs);
                            $isPendingBpjs = in_array($reg->no_rawat, $pendingBpjs);
                            $ssStatus = $ssStatuses[$reg->no_rawat] ?? null;
                        @endphp
                        <tr wire:key="rj-{{ $reg->no_rawat }}"
                            class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm text-zinc-900 dark:text-primary-dark-100">
                                    {{ $reg->tgl_registrasi?->format('d/m/Y') }}</div>
                                <div class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $reg->jam_reg }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span
                                    class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400">{{ $reg->no_rawat }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium truncate text-zinc-900 dark:text-primary-dark-100">
                                    {{ $reg->pasien?->nm_pasien ?? '-' }}</p>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $reg->no_rkm_medis }}</p>
                            </td>
                            <td
                                class="hidden px-4 py-3 text-sm whitespace-nowrap md:table-cell text-zinc-700 dark:text-primary-dark-300">
                                {{ $reg->poliklinik?->nm_poli ?? '-' }}
                            </td>
                            <td class="hidden px-4 py-3 text-sm lg:table-cell text-zinc-700 dark:text-primary-dark-300">
                                {{ $reg->dokter?->nm_dokter ?? '-' }}
                            </td>
                            <td
                                class="hidden px-4 py-3 text-sm whitespace-nowrap sm:table-cell text-zinc-700 dark:text-primary-dark-300">
                                {{ $reg->penjab?->png_jawab ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if ($isSentBpjs)
                                    <flux:badge color="green" size="sm">Terkirim</flux:badge>
                                @elseif ($isPendingBpjs)
                                    <flux:badge color="yellow" size="sm">Menunggu</flux:badge>
                                @elseif ($isFailedBpjs)
                                    <flux:badge color="red" size="sm">Gagal</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Belum</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if ($ssStatus === 'finished')
                                    <flux:badge color="green" size="sm">Selesai</flux:badge>
                                @elseif ($ssStatus === 'in-progress')
                                    <flux:badge color="blue" size="sm">Dalam Proses</flux:badge>
                                @elseif ($ssStatus !== null)
                                    <flux:badge color="yellow" size="sm">{{ $ssStatus }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Belum</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <x-atoms.button variant="ghost" size="sm" icon="eye" :navigate="true"
                                    href="{{ route('erm.detail', ['noRawat' => $reg->no_rawat]) }}" wire:navigate
                                    title="Detail eRM" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="document-text"
                                        class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada data
                                        rawat jalan
                                        ditemukan</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($registrations->hasPages())
            <div class="px-4 py-4 border-t border-zinc-200 dark:border-primary-dark-700">
                {{ $registrations->links() }}
            </div>
        @endif
    </div>
</div>
