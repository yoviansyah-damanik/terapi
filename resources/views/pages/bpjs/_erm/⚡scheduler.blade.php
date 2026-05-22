<?php

use App\Jobs\SendBpjsErmJob;
use App\Models\Bpjs\BpjsErm;
use App\Models\Bpjs\BpjsLog;
use App\Models\Simrs\Poliklinik;
use App\Models\Simrs\RegPeriksa;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

new class extends Component {
    use WithPagination;

    public bool $ready = false;

    #[Url]
    public string $schedulerSearch = '';

    #[Url]
    public string $schedulerTglMulai = '';

    #[Url]
    public string $schedulerTglSelesai = '';

    #[Url]
    public string $schedulerStatusLanjut = 'all';

    #[Url]
    public string $schedulerFilterSync = 'all';

    public bool $showBulkModal = false;

    public string $bulkTglMulai = '';
    public string $bulkTglSelesai = '';
    public string $bulkStatusRawat = 'all';
    public string $bulkTipePengiriman = 'all';

    public function mount(): void
    {
        if (!$this->schedulerTglMulai) {
            $this->schedulerTglMulai = now()->format('Y-m-d');
        }
        if (!$this->schedulerTglSelesai) {
            $this->schedulerTglSelesai = now()->format('Y-m-d');
        }
        $this->bulkTglMulai = $this->schedulerTglMulai;
        $this->bulkTglSelesai = $this->schedulerTglSelesai;
    }

    public function init(): void
    {
        $this->ready = true;
    }

    public function openBulkModal(): void
    {
        $this->bulkTglMulai = $this->schedulerTglMulai;
        $this->bulkTglSelesai = $this->schedulerTglSelesai;
        $this->bulkStatusRawat = $this->schedulerStatusLanjut;
        $this->showBulkModal = true;
    }

    #[On('open-erm-bulk-modal')]
    public function onOpenBulkModal(): void
    {
        $this->openBulkModal();
    }

    #[On('refresh-erm-scheduler')]
    public function onRefresh(): void
    {
        // trigger re-render
    }

    public function updatedSchedulerSearch(): void
    {
        $this->resetPage();
    }
    public function updatedSchedulerTglMulai(): void
    {
        $this->resetPage();
    }
    public function updatedSchedulerTglSelesai(): void
    {
        $this->resetPage();
    }
    public function updatedSchedulerStatusLanjut(): void
    {
        $this->resetPage();
    }
    public function updatedSchedulerFilterSync(): void
    {
        $this->resetPage();
    }

    public function sendSingle(string $noRawat): void
    {
        SendBpjsErmJob::dispatch($noRawat);
        $this->toastSuccess('Pengiriman eRM dijadwalkan.');
    }

    public function sendBulk(): void
    {
        $this->showBulkModal = false;

        $igdCodes = $this->igdCodes();

        $query = RegPeriksa::query()
            ->whereBetween('tgl_registrasi', [$this->bulkTglMulai, $this->bulkTglSelesai])
            ->whereHas('bridgingSep')
            ->orderBy('tgl_registrasi');

        if ($this->bulkStatusRawat === 'IGD') {
            $query->where('status_lanjut', 'Ralan')->whereIn('kd_poli', $igdCodes);
        } elseif ($this->bulkStatusRawat === 'Ralan') {
            $query->where('status_lanjut', 'Ralan')->whereNotIn('kd_poli', $igdCodes);
        } elseif ($this->bulkStatusRawat === 'Ranap') {
            $query->where('status_lanjut', 'Ranap');
        }

        if ($this->bulkTipePengiriman === 'resend') {
            $query->whereIn('no_rawat', BpjsErm::pluck('no_rawat')->toArray());
        } elseif ($this->bulkTipePengiriman === 'new') {
            $query->whereNotIn('no_rawat', BpjsErm::pluck('no_rawat')->toArray());
        }

        $count = 0;
        $groups = $query
            ->select(['no_rawat', 'tgl_registrasi'])
            ->get()
            ->groupBy(fn($r) => $r->tgl_registrasi->format('Y-m-d'));

        foreach ($groups as $regs) {
            foreach ($regs as $reg) {
                SendBpjsErmJob::dispatch($reg->no_rawat);
                $count++;
            }
        }

        $this->toastSuccess("{$count} pengiriman eRM dijadwalkan ({$this->bulkTglMulai} s/d {$this->bulkTglSelesai}).");
    }

    private function igdCodes(): array
    {
        return Poliklinik::where(function ($q) {
            $q->where('nm_poli', 'like', '%gawat%')->orWhere('kd_poli', 'like', '%igd%')->orWhere('kd_poli', 'like', '%ugd%');
        })
            ->pluck('kd_poli')
            ->toArray();
    }

    private function getQuery()
    {
        $igdCodes = $this->igdCodes();

        $query = RegPeriksa::query()
            ->with(['pasien', 'dokter', 'poliklinik', 'bridgingSep', 'kamarInap.kamar.bangsal'])
            ->whereBetween('tgl_registrasi', [$this->schedulerTglMulai, $this->schedulerTglSelesai])
            ->when($this->schedulerSearch, function ($q) {
                $q->where(fn($sq) => $sq->where('no_rawat', 'like', "%{$this->schedulerSearch}%")->orWhereHas('pasien', fn($pq) => $pq->where('nm_pasien', 'like', "%{$this->schedulerSearch}%")));
            });

        if ($this->schedulerStatusLanjut === 'IGD') {
            $query->where('status_lanjut', 'Ralan')->whereIn('kd_poli', $igdCodes);
        } elseif ($this->schedulerStatusLanjut === 'Ralan') {
            $query->where('status_lanjut', 'Ralan')->whereNotIn('kd_poli', $igdCodes);
        } elseif ($this->schedulerStatusLanjut === 'Ranap') {
            $query->where('status_lanjut', 'Ranap');
        }

        if ($this->schedulerFilterSync === 'not_sent') {
            $query->whereNotIn('no_rawat', BpjsErm::pluck('no_rawat')->toArray());
        } elseif ($this->schedulerFilterSync === 'success') {
            $query->whereIn('no_rawat', BpjsErm::pluck('no_rawat')->toArray());
        } elseif ($this->schedulerFilterSync === 'failed') {
            $failedIds = BpjsLog::forService('erm')->failed()->pluck('no_rawat')->toArray();
            $sentIds = BpjsErm::pluck('no_rawat')->toArray();
            $query->whereIn('no_rawat', $failedIds)->whereNotIn('no_rawat', $sentIds);
        } elseif ($this->schedulerFilterSync === 'no_sep') {
            $query->whereDoesntHave('bridgingSep');
        }

        return $query->latest('jam_reg');
    }

    public function with(): array
    {
        if (!$this->ready) {
            return [
                'registrations' => collect(),
                'stats' => ['total' => 0, 'success' => 0, 'failed' => 0, 'no_sep' => 0, 'pending' => 0],
                'igdCodes' => [],
                'sentNoRawats' => [],
                'sentErmIds' => [],
            ];
        }

        $igdCodes = $this->igdCodes();

        $baseQuery = RegPeriksa::query()->whereBetween('tgl_registrasi', [$this->schedulerTglMulai, $this->schedulerTglSelesai]);

        if ($this->schedulerStatusLanjut === 'IGD') {
            $baseQuery->where('status_lanjut', 'Ralan')->whereIn('kd_poli', $igdCodes);
        } elseif ($this->schedulerStatusLanjut === 'Ralan') {
            $baseQuery->where('status_lanjut', 'Ralan')->whereNotIn('kd_poli', $igdCodes);
        } elseif ($this->schedulerStatusLanjut === 'Ranap') {
            $baseQuery->where('status_lanjut', 'Ranap');
        }

        $noRawatsInDate = (clone $baseQuery)->pluck('no_rawat')->toArray();
        $noRawatsWithSep = (clone $baseQuery)->whereHas('bridgingSep')->pluck('no_rawat')->toArray();

        $sentErms = BpjsErm::whereIn('no_rawat', $noRawatsInDate)->get(['id', 'no_rawat']);
        $sentNoRawats = $sentErms->pluck('no_rawat')->toArray();
        $sentErmIds = $sentErms->pluck('id', 'no_rawat')->toArray();
        $failedNoRawats = BpjsLog::forService('erm')->failed()->whereIn('no_rawat', $noRawatsInDate)->whereNotIn('no_rawat', $sentNoRawats)->distinct()->pluck('no_rawat')->toArray();

        return [
            'registrations' => $this->getQuery()->paginate(20),
            'stats' => [
                'total' => count($noRawatsInDate),
                'success' => count($sentNoRawats),
                'failed' => count($failedNoRawats),
                'no_sep' => count($noRawatsInDate) - count($noRawatsWithSep),
                'pending' => count($noRawatsWithSep) - count($sentNoRawats) - count($failedNoRawats),
            ],
            'igdCodes' => $igdCodes,
            'sentNoRawats' => $sentNoRawats,
            'sentErmIds' => $sentErmIds,
        ];
    }
};
?>

<div wire:init="init">
    @if (!$ready)
        {{-- Skeleton --}}
        <div class="animate-pulse space-y-4">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                @foreach (range(1, 5) as $_)
                    <div class="h-20 bg-zinc-100 dark:bg-primary-dark-700 rounded-2xl"></div>
                @endforeach
            </div>
            <div class="h-16 bg-zinc-100 dark:bg-primary-dark-700 rounded-2xl"></div>
            <div class="h-64 bg-zinc-100 dark:bg-primary-dark-700 rounded-2xl"></div>
        </div>
    @else
        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <x-organisms.stat-card title="Total Kunjungan" :value="number_format($stats['total'])" icon="users" color="zinc" />
            <x-organisms.stat-card title="Berhasil Dikirim" :value="number_format($stats['success'])" icon="check-circle" color="emerald" />
            <x-organisms.stat-card title="Gagal" :value="number_format($stats['failed'])" icon="exclamation-circle" color="red" />
            <x-organisms.stat-card title="Belum Dikirim" :value="number_format($stats['pending'])" icon="clock" color="amber" />
            <x-organisms.stat-card title="Tanpa SEP" :value="number_format($stats['no_sep'])" icon="x-circle" color="zinc" />
        </div>

        {{-- Filters --}}
        <div
            class="p-5 bg-white dark:bg-primary-dark-800 rounded-2xl shadow-sm border border-zinc-200 dark:border-primary-dark-700 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <div class="md:col-span-2">
                    <flux:input wire:model.live.debounce.300ms="schedulerSearch" label="Cari Pasien / No. Rawat"
                        icon="magnifying-glass" placeholder="Nama pasien atau nomor rawat..." />
                </div>
                <flux:field>
                    <flux:label>Tanggal Mulai</flux:label>
                    <flux:input type="date" wire:model.live="schedulerTglMulai" />
                </flux:field>
                <flux:field>
                    <flux:label>Tanggal Selesai</flux:label>
                    <flux:input type="date" wire:model.live="schedulerTglSelesai" />
                </flux:field>
                <flux:field>
                    <flux:label>Jenis Rawat</flux:label>
                    <flux:select wire:model.live="schedulerStatusLanjut">
                        <flux:select.option value="all">Semua</flux:select.option>
                        <flux:select.option value="IGD">IGD</flux:select.option>
                        <flux:select.option value="Ralan">Rawat Jalan</flux:select.option>
                        <flux:select.option value="Ranap">Rawat Inap</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>
            <div class="mt-4 pt-4 border-t border-zinc-100 dark:border-primary-dark-700">
                <flux:radio.group wire:model.live="schedulerFilterSync" variant="cards" class="flex-row w-full">
                    <flux:radio value="all" label="Semua Status" />
                    <flux:radio value="not_sent" label="Belum Dikirim" />
                    <flux:radio value="success" label="Berhasil" />
                    <flux:radio value="failed" label="Gagal" />
                    <flux:radio value="no_sep" label="Tanpa SEP" />
                </flux:radio.group>
            </div>
        </div>

        {{-- Table --}}
        <div
            class="bg-white dark:bg-primary-dark-800 rounded-2xl shadow-sm border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead
                        class="bg-zinc-50 dark:bg-primary-dark-900 border-b border-zinc-200 dark:border-primary-dark-700">
                        <tr>
                            <th class="px-6 py-4 font-bold text-zinc-700 dark:text-primary-dark-200">Kunjungan</th>
                            <th class="px-6 py-4 font-bold text-zinc-700 dark:text-primary-dark-200">Pasien</th>
                            <th class="px-6 py-4 font-bold text-zinc-700 dark:text-primary-dark-200">Layanan</th>
                            <th class="px-6 py-4 font-bold text-zinc-700 dark:text-primary-dark-200">SEP</th>
                            <th class="px-6 py-4 font-bold text-zinc-700 dark:text-primary-dark-200">Status eRM</th>
                            <th class="px-6 py-4 font-bold text-zinc-700 dark:text-primary-dark-200 text-right">Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                        @forelse($registrations as $reg)
                            @php
                                $isIgd = in_array($reg->kd_poli, $igdCodes);
                                $isRanap = $reg->status_lanjut === 'Ranap';
                                $lastKamar = $isRanap ? $reg->kamarInap->last() : null;
                                $hasSep = (bool) $reg->bridgingSep;
                                $isSent = in_array($reg->no_rawat, $sentNoRawats);
                            @endphp
                            <tr wire:key="sched-{{ $reg->no_rawat }}"
                                class="hover:bg-zinc-50 dark:hover:bg-primary-dark-900/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-mono font-bold text-primary-600 dark:text-primary-400">
                                        {{ $reg->no_rawat }}</div>
                                    <div class="text-xs text-zinc-500 mt-1">
                                        @if ($isRanap && $lastKamar)
                                            <div class="flex items-center gap-1">
                                                <flux:icon name="arrow-right-start-on-rectangle"
                                                    class="w-3 h-3 text-emerald-500" />
                                                <span>{{ $lastKamar->tgl_masuk->format('d/m/Y') }}</span>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-1">
                                                <flux:icon name="calendar" class="w-3 h-3 text-zinc-400" />
                                                <span>{{ $reg->tgl_registrasi->format('d/m/Y') }}
                                                    {{ $reg->jam_reg }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <flux:badge size="sm"
                                        :color="$isRanap ? 'violet' : ($isIgd ? 'red' : 'emerald')" class="mt-2">
                                        {{ $isRanap ? 'Ranap' : ($isIgd ? 'IGD' : 'Ralan') }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-zinc-900 dark:text-primary-dark-100">
                                        {{ $reg->pasien->nm_pasien }}</div>
                                    <div class="text-xs text-zinc-500 mt-1">RM: {{ $reg->no_rkm_medis }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-zinc-800 dark:text-primary-dark-200">
                                        {{ $reg->poliklinik->nm_poli }}</div>
                                    <div class="text-xs text-zinc-500 mt-1">{{ $reg->dokter->nm_dokter }}</div>
                                    @if ($isRanap && $lastKamar)
                                        <div class="mt-1 text-xs font-bold text-primary-600 dark:text-primary-400">
                                            {{ $lastKamar->kd_kamar }}
                                            <span
                                                class="font-normal text-zinc-400">{{ $lastKamar->kamar?->bangsal?->nm_bangsal }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if ($hasSep)
                                        <div class="flex flex-col gap-0.5">
                                            <flux:badge size="sm" color="emerald" icon="check-circle">Ada SEP
                                            </flux:badge>
                                            <span
                                                class="text-[10px] font-mono text-zinc-500">{{ $reg->bridgingSep->no_sep }}</span>
                                        </div>
                                    @else
                                        <flux:badge size="sm" color="zinc" icon="x-circle">Tanpa SEP</flux:badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if ($isSent)
                                        <flux:badge size="sm" color="emerald" icon="check-circle">Terkirim
                                        </flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">Belum Dikirim</flux:badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        @if ($isSent && isset($sentErmIds[$reg->no_rawat]))
                                            <x-atoms.button variant="ghost" size="sm" icon="eye"
                                                href="{{ route('bpjs.erm-detail', $sentErmIds[$reg->no_rawat]) }}"
                                                tooltip="Lihat Detail eRM" />
                                        @endif
                                        @if ($hasSep)
                                            <x-atoms.button variant="primary" size="sm" icon="paper-airplane"
                                                wire:click="sendSingle('{{ $reg->no_rawat }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="sendSingle('{{ $reg->no_rawat }}')" :tooltip="$isSent ? 'Update eRM' : 'Kirim eRM'" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-zinc-500">Data tidak ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($registrations->hasPages())
                <div class="px-6 py-4 border-t border-zinc-100 dark:border-primary-dark-700">
                    {{ $registrations->links() }}
                </div>
            @endif
        </div>

        {{-- Bulk Send Modal --}}
        <x-organisms.modal wire:model="showBulkModal" title="Pengiriman eRM Massal" maxWidth="4xl">
            <div class="space-y-5">
                <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                    Hanya rawatan yang memiliki SEP yang akan dikirim. Job dibuat per tanggal registrasi.
                </p>
                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Tanggal Mulai</flux:label>
                        <flux:input type="date" wire:model="bulkTglMulai" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Tanggal Selesai</flux:label>
                        <flux:input type="date" wire:model="bulkTglSelesai" />
                    </flux:field>
                </div>
                <flux:field>
                    <flux:label>Status Rawatan</flux:label>
                    <flux:select wire:model="bulkStatusRawat">
                        <flux:select.option value="all">Semua</flux:select.option>
                        <flux:select.option value="IGD">IGD</flux:select.option>
                        <flux:select.option value="Ralan">Rawat Jalan</flux:select.option>
                        <flux:select.option value="Ranap">Rawat Inap</flux:select.option>
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Tipe Pengiriman</flux:label>
                    <flux:radio.group wire:model="bulkTipePengiriman" variant="cards">
                        <flux:radio value="all" label="Semua"
                            description="Kirim semua rawatan pada periode ini" />
                        <flux:radio value="resend" label="Pengiriman Ulang"
                            description="Hanya rawatan yang sudah pernah berhasil dikirim" />
                        <flux:radio value="new" label="Pengiriman Baru"
                            description="Hanya rawatan yang belum pernah berhasil dikirim" />
                    </flux:radio.group>
                </flux:field>
                <div
                    class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-800/30">
                    <flux:icon name="information-circle" class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" />
                    <p class="text-xs text-amber-700 dark:text-amber-400">
                        Hanya rawatan yang memiliki <strong>SEP</strong> yang akan diproses. Job berjalan di background
                        dan tidak mengganggu sistem.
                    </p>
                </div>
            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-3">
                    <x-atoms.button variant="ghost" wire:click="$set('showBulkModal', false)">Batal</x-atoms.button>
                    <x-atoms.button variant="primary" icon="paper-airplane" wire:click="sendBulk"
                        wire:loading.attr="disabled" wire:target="sendBulk">
                        <span wire:loading.remove wire:target="sendBulk">Mulai Kirim</span>
                        <span wire:loading wire:target="sendBulk">Memproses...</span>
                    </x-atoms.button>
                </div>
            </x-slot:footer>
        </x-organisms.modal>
    @endif
</div>
