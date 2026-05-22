<?php

use App\Models\Simrs\RegPeriksa;
use App\Models\SatuSehat\SatuSehatBundle;
use App\Models\SatuSehat\SatuSehatPatient;
use App\Jobs\SendSatuSehatBundleJob;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Carbon\Carbon;

new #[Layout('layouts::app')] #[Title('Penjadwalan Satu Sehat')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $tglMulai = '';

    #[Url]
    public string $tglSelesai = '';

    #[Url]
    public string $statusLanjut = 'all';

    #[Url]
    public string $filterSync = 'all';

    public bool $showItemModal = false;
    public bool $showJsonModal = false;
    public bool $showBulkModal = false;
    public string $jsonTitle = '';
    public string $jsonData = '';
    public ?SatuSehatBundle $selectedLog = null;

    // Form Kirim Semua Bundle
    public string $bulkTglMulai = '';
    public string $bulkTglSelesai = '';
    public string $bulkStatusRawat = 'all';
    public string $bulkTipePengiriman = 'all';

    public function mount()
    {
        if (!isset($this->tglMulai)) {
            $this->tglMulai = now()->format('Y-m-d');
        }
        if (!isset($this->tglSelesai)) {
            $this->tglSelesai = now()->format('Y-m-d');
        }

        $this->bulkTglMulai = $this->tglMulai;
        $this->bulkTglSelesai = $this->tglSelesai;
    }

    public function openBulkModal(): void
    {
        $this->bulkTglMulai = $this->tglMulai;
        $this->bulkTglSelesai = $this->tglSelesai;
        $this->bulkStatusRawat = $this->statusLanjut;
        $this->showBulkModal = true;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
    public function updatedTglMulai()
    {
        $this->resetPage();
    }
    public function updatedTglSelesai()
    {
        $this->resetPage();
    }
    public function updatedStatusLanjut()
    {
        $this->resetPage();
    }
    public function updatedFilterSync()
    {
        $this->resetPage();
    }

    public function syncSingle(string $noRawat)
    {
        $bundle = SatuSehatBundle::create(['no_rawat' => $noRawat, 'status' => SatuSehatBundle::STATUS_QUEUED, 'triggered_by' => auth()->id()]);
        SendSatuSehatBundleJob::dispatch($noRawat, $bundle);

        $this->toastSuccess('Sinkronisasi bundle dijadwalkan.');
    }

    public function syncBulk(): void
    {
        $this->showBulkModal = false;

        $igdCodes = \App\Models\Simrs\Poliklinik::where(function ($q) {
            $q->where('nm_poli', 'like', '%gawat%')->orWhere('kd_poli', 'like', '%igd%')->orWhere('kd_poli', 'like', '%ugd%');
        })
            ->pluck('kd_poli')
            ->toArray();

        $query = RegPeriksa::query()
            ->whereBetween('tgl_registrasi', [$this->bulkTglMulai, $this->bulkTglSelesai])
            ->orderBy('tgl_registrasi');

        if ($this->bulkStatusRawat === 'IGD') {
            $query->where('status_lanjut', 'Ralan')->whereIn('kd_poli', $igdCodes);
        } elseif ($this->bulkStatusRawat === 'Ralan') {
            $query->where('status_lanjut', 'Ralan')->whereNotIn('kd_poli', $igdCodes);
        } elseif ($this->bulkStatusRawat === 'Ranap') {
            $query->where('status_lanjut', 'Ranap');
        }

        // Filter berdasarkan tipe pengiriman
        if ($this->bulkTipePengiriman === 'resend') {
            // Hanya rawatan yang sudah memiliki bundle log (pengiriman ulang)
            $hasBundle = SatuSehatBundle::pluck('no_rawat')->toArray();
            $query->whereIn('no_rawat', $hasBundle);
        } elseif ($this->bulkTipePengiriman === 'new') {
            // Hanya rawatan yang belum memiliki bundle log
            $hasBundle = SatuSehatBundle::pluck('no_rawat')->toArray();
            $query->whereNotIn('no_rawat', $hasBundle);
        }

        // Batch per tanggal — dispatch per-hari agar antrian lebih teratur
        $count = 0;
        $noRawatsByDate = $query
            ->select(['no_rawat', 'tgl_registrasi'])
            ->get()
            ->groupBy(fn($r) => $r->tgl_registrasi->format('Y-m-d'));

        foreach ($noRawatsByDate as $tanggal => $registrations) {
            foreach ($registrations as $reg) {
                $bundle = SatuSehatBundle::create(['no_rawat' => $reg->no_rawat, 'status' => SatuSehatBundle::STATUS_QUEUED, 'triggered_by' => auth()->id()]);
                SendSatuSehatBundleJob::dispatch($reg->no_rawat, $bundle);
                $count++;
            }
        }

        $this->toastSuccess("{$count} sinkronisasi bundle dijadwalkan ({$this->bulkTglMulai} s/d {$this->bulkTglSelesai}).");
    }

    public function showItems(string $logId)
    {
        $this->selectedLog = SatuSehatBundle::with(['items' => fn($q) => $q->orderBy('created_at')])->find($logId);
        $this->showItemModal = true;
    }

    public function viewJson(string $title, $data)
    {
        $this->jsonTitle = $title;
        $this->jsonData = is_array($data) ? json_encode($data, JSON_PRETTY_PRINT) : (string) $data;
        $this->showJsonModal = true;
    }

    private function getQuery()
    {
        $igdCodes = \App\Models\Simrs\Poliklinik::where(function ($q) {
            $q->where('nm_poli', 'like', '%gawat%')->orWhere('kd_poli', 'like', '%igd%')->orWhere('kd_poli', 'like', '%ugd%');
        })
            ->pluck('kd_poli')
            ->toArray();

        $query = RegPeriksa::query()
            ->with(['pasien.satuSehatPatient', 'dokter.pegawai', 'poliklinik', 'satuSehatEncounter', 'satuSehatBundle.items', 'kamarInap.kamar.bangsal'])
            ->whereBetween('tgl_registrasi', [$this->tglMulai, $this->tglSelesai])
            ->when($this->search, function ($q) {
                $q->where(fn($sq) => $sq->where('no_rawat', 'like', "%{$this->search}%")->orWhereHas('pasien', fn($pq) => $pq->where('nm_pasien', 'like', "%{$this->search}%")));
            });

        if ($this->statusLanjut === 'IGD') {
            $query->where('status_lanjut', 'Ralan')->whereIn('kd_poli', $igdCodes);
        } elseif ($this->statusLanjut === 'Ralan') {
            $query->where('status_lanjut', 'Ralan')->whereNotIn('kd_poli', $igdCodes);
        } elseif ($this->statusLanjut === 'Ranap') {
            $query->where('status_lanjut', 'Ranap');
        }

        if ($this->filterSync !== 'all') {
            if ($this->filterSync === 'not_sent') {
                $sentNoRawats = SatuSehatBundle::pluck('no_rawat')->toArray();
                $query->whereNotIn('no_rawat', $sentNoRawats);
            } elseif ($this->filterSync === 'failed') {
                $failedNoRawats = SatuSehatBundle::whereIn('status', [SatuSehatBundle::STATUS_FAILED, SatuSehatBundle::STATUS_PARTIAL])
                    ->pluck('no_rawat')
                    ->toArray();
                $query->whereIn('no_rawat', $failedNoRawats);
            } elseif ($this->filterSync === 'success') {
                $successNoRawats = SatuSehatBundle::where('status', 'completed')->pluck('no_rawat')->toArray();
                $query->whereIn('no_rawat', $successNoRawats);
            }
        }

        return $query->latest('jam_reg');
    }

    public function with(): array
    {
        $igdCodes = \App\Models\Simrs\Poliklinik::where(function ($q) {
            $q->where('nm_poli', 'like', '%gawat%')->orWhere('kd_poli', 'like', '%igd%')->orWhere('kd_poli', 'like', '%ugd%');
        })
            ->pluck('kd_poli')
            ->toArray();

        $baseQuery = RegPeriksa::query()->whereBetween('tgl_registrasi', [$this->tglMulai, $this->tglSelesai]);

        if ($this->statusLanjut === 'IGD') {
            $baseQuery->where('status_lanjut', 'Ralan')->whereIn('kd_poli', $igdCodes);
        } elseif ($this->statusLanjut === 'Ralan') {
            $baseQuery->where('status_lanjut', 'Ralan')->whereNotIn('kd_poli', $igdCodes);
        } elseif ($this->statusLanjut === 'Ranap') {
            $baseQuery->where('status_lanjut', 'Ranap');
        }

        $noRawatsInDate = (clone $baseQuery)->pluck('no_rawat')->toArray();

        $logs = SatuSehatBundle::whereIn('no_rawat', $noRawatsInDate)->get();
        $successCount = $logs->where('status', SatuSehatBundle::STATUS_COMPLETED)->count();
        $failedCount = $logs->whereIn('status', [SatuSehatBundle::STATUS_FAILED, SatuSehatBundle::STATUS_PARTIAL])->count();
        $totalCount = count($noRawatsInDate);

        return [
            'registrations' => $this->getQuery()->paginate(20),
            'stats' => [
                'total' => $totalCount,
                'success' => $successCount,
                'failed' => $failedCount,
                'pending' => $totalCount - $logs->count(),
            ],
            'igdCodes' => $igdCodes,
        ];
    }
};
?>

<div class="space-y-6 pb-12">
    {{-- Header --}}
    <x-ui.page-header title="Penjadwalan Pengiriman Satu Sehat"
        subtitle="Kelola dan pantau pengiriman data bundle FHIR ke Satu Sehat">
        <x-slot:actions>
            <x-atoms.button variant="ghost" icon="arrow-path" wire:click="$refresh"
                wire:loading.attr="disabled" wire:target="$refresh" tooltip="Refresh" />
            <x-atoms.button variant="primary" icon="paper-airplane" wire:click="openBulkModal">
                Kirim Bundle
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <x-organisms.stat-card title="Total Kunjungan" :value="number_format($stats['total'])" icon="users" color="zinc" />
        <x-organisms.stat-card title="Berhasil" :value="number_format($stats['success'])" icon="check-circle" color="emerald" />
        <x-organisms.stat-card title="Gagal / Parsial" :value="number_format($stats['failed'])" icon="exclamation-circle" color="red" />
        <x-organisms.stat-card title="Belum Dikirim" :value="number_format($stats['pending'])" icon="clock" color="amber" />
    </div>

    {{-- Filters --}}
    <div
        class="p-5 bg-white dark:bg-primary-dark-800 rounded-2xl shadow-sm border border-zinc-200 dark:border-primary-dark-700">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div class="md:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" label="Cari Pasien / No. Rawat"
                    icon="magnifying-glass" placeholder="Nama pasien atau nomor rawat..." />
            </div>
            <flux:field>
                <flux:label>Tanggal Mulai</flux:label>
                <flux:input type="date" wire:model.live="tglMulai" />
            </flux:field>
            <flux:field>
                <flux:label>Tanggal Selesai</flux:label>
                <flux:input type="date" wire:model.live="tglSelesai" />
            </flux:field>
            <flux:field>
                <flux:label>Jenis Rawat</flux:label>
                <flux:select wire:model.live="statusLanjut">
                    <flux:select.option value="all">Semua</flux:select.option>
                    <flux:select.option value="IGD">IGD</flux:select.option>
                    <flux:select.option value="Ralan">Rawat Jalan</flux:select.option>
                    <flux:select.option value="Ranap">Rawat Inap</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
        <div class="mt-4 pt-4 border-t border-zinc-100 dark:border-primary-dark-700 flex gap-4">
            <flux:radio.group wire:model.live="filterSync" variant="cards" class="flex-row w-full">
                <flux:radio value="all" label="Semua Status" />
                <flux:radio value="not_sent" label="Belum Dikirim" />
                <flux:radio value="success" label="Berhasil" />
                <flux:radio value="failed" label="Gagal" />
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
                        <th class="px-6 py-4 font-bold text-zinc-700 dark:text-primary-dark-200">Bundle</th>
                        <th class="px-6 py-4 font-bold text-zinc-700 dark:text-primary-dark-200">Encounter</th>
                        <th class="px-6 py-4 font-bold text-zinc-700 dark:text-primary-dark-200 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                    @forelse($registrations as $reg)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-900/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-mono font-bold text-primary-600 dark:text-primary-400">
                                    {{ $reg->no_rawat }}</div>
                                @php
                                    $isIgd = in_array($reg->kd_poli, $igdCodes);
                                    $isRanap = $reg->status_lanjut === 'Ranap';
                                    $lastKamar = $isRanap ? $reg->kamarInap->last() : null;
                                @endphp
                                <div class="text-xs text-zinc-500 mt-1">
                                    @if ($isRanap && $lastKamar)
                                        <div class="flex flex-col gap-0.5">
                                            <div class="flex items-center gap-1" title="Tgl Masuk">
                                                <flux:icon name="arrow-right-start-on-rectangle"
                                                    class="w-3 h-3 text-emerald-500" />
                                                <span>{{ $lastKamar->tgl_masuk->format('d/m/Y') }}
                                                    {{ $lastKamar->jam_masuk }}</span>
                                            </div>
                                            @if ($lastKamar->tgl_keluar && $lastKamar->tgl_keluar->year > 2000)
                                                <div class="flex items-center gap-1" title="Tgl Keluar">
                                                    <flux:icon name="arrow-right-end-on-rectangle"
                                                        class="w-3 h-3 text-red-500" />
                                                    <span>{{ $lastKamar->tgl_keluar->format('d/m/Y') }}
                                                        {{ $lastKamar->jam_keluar }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="flex items-center gap-1" title="Tgl Registrasi">
                                            <flux:icon name="calendar" class="w-3 h-3 text-zinc-400" />
                                            <span>{{ $reg->tgl_registrasi->format('d/m/Y') }} {{ $reg->jam_reg }}</span>
                                        </div>
                                    @endif
                                </div>
                                <flux:badge size="sm" :color="$isRanap ? 'violet' : ($isIgd ? 'red' : 'emerald')"
                                    class="mt-2">
                                    {{ $isRanap ? 'Ranap' : ($isIgd ? 'IGD' : 'Ralan') }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-zinc-900 dark:text-primary-dark-100">
                                    {{ $reg->pasien->nm_pasien }}</div>
                                <div class="text-xs text-zinc-500 mt-1">RM: {{ $reg->no_rkm_medis }} • NIK:
                                    {{ $reg->pasien->no_ktp ?? '-' }}</div>

                                {{-- IHS Indicator --}}
                                @if ($reg->pasien->satuSehatPatient)
                                    <div
                                        class="mt-2 flex items-center gap-1.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                                        <flux:icon name="check-badge" variant="solid" class="w-3.5 h-3.5" />
                                        IHS: {{ $reg->pasien->satuSehatPatient->ihs_number }}
                                    </div>
                                @else
                                    <div class="mt-2 flex items-center gap-1.5 text-[10px] font-bold text-red-500">
                                        <flux:icon name="exclamation-triangle" variant="solid" class="w-3.5 h-3.5" />
                                        PASIEN BELUM TERDAFTAR SATU SEHAT
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-zinc-800 dark:text-primary-dark-200">{{ $reg->poliklinik->nm_poli }}
                                </div>
                                <div class="text-xs text-zinc-500 mt-1">{{ $reg->dokter->nm_dokter }}</div>
                                @if ($isRanap && $lastKamar)
                                    <div class="mt-2 pt-2 border-t border-zinc-100 dark:border-primary-dark-700">
                                        <div
                                            class="flex items-center gap-1.5 text-xs font-bold text-primary-600 dark:text-primary-400">
                                            <flux:icon name="home" class="w-3.5 h-3.5" />
                                            <span>{{ $lastKamar->kd_kamar }}</span>
                                        </div>
                                        <div class="text-[10px] text-zinc-500 mt-0.5">
                                            {{ $lastKamar->kamar?->bangsal?->nm_bangsal ?? '-' }}
                                        </div>
                                    </div>
                                @endif
                            </td>
                            @php
                                $bundle = $reg->satuSehatBundle;
                                $bundleRunning = $bundle && in_array($bundle->status, ['running', 'queued']);
                                $successCount = $bundle?->items->where('status', 'success')->count() ?? 0;
                                $failedCount  = $bundle?->items->where('status', 'failed')->count() ?? 0;
                                $warningCount = $bundle?->items->where('status', 'warning')->count() ?? 0;
                            @endphp

                            {{-- Kolom Bundle --}}
                            <td class="px-6 py-4">
                                @if ($bundle)
                                    <div class="space-y-1.5">
                                        {{-- Bundle ID (short) --}}
                                        <button wire:click="showItems('{{ $bundle->id }}')"
                                            class="font-mono text-[10px] text-primary-500 dark:text-primary-400 hover:underline leading-none">
                                            #{{ substr($bundle->id, 0, 8) }}...
                                        </button>

                                        {{-- Status badge --}}
                                        <div>
                                            <button wire:click="showItems('{{ $bundle->id }}')"
                                                class="hover:opacity-75 transition-opacity">
                                                <flux:badge size="sm" :color="$bundle->status_color">
                                                    {{ strtoupper($bundle->status) }}
                                                </flux:badge>
                                            </button>
                                        </div>

                                        {{-- Item counts --}}
                                        <div class="flex items-center gap-1.5 text-[10px] font-bold">
                                            @if ($successCount > 0)
                                                <span class="flex items-center gap-0.5 text-emerald-600 dark:text-emerald-400" title="Berhasil">
                                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>{{ $successCount }}
                                                </span>
                                            @endif
                                            @if ($failedCount > 0)
                                                <span class="flex items-center gap-0.5 text-red-500 dark:text-red-400" title="Gagal">
                                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-red-500"></span>{{ $failedCount }}
                                                </span>
                                            @endif
                                            @if ($warningCount > 0)
                                                <span class="flex items-center gap-0.5 text-amber-500 dark:text-amber-400" title="Peringatan">
                                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500"></span>{{ $warningCount }}
                                                </span>
                                            @endif
                                            @if ($successCount + $failedCount + $warningCount === 0)
                                                <span class="text-zinc-400">-</span>
                                            @endif
                                        </div>

                                        {{-- Waktu --}}
                                        @if ($bundle->created_at)
                                            <div class="text-[10px] text-zinc-400">
                                                {{ $bundle->created_at->format('d/m H:i') }}
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <flux:badge size="sm" color="zinc">NONE</flux:badge>
                                @endif
                            </td>

                            {{-- Kolom Encounter --}}
                            <td class="px-6 py-4">
                                @if ($reg->satuSehatEncounter)
                                    <flux:badge size="sm" color="emerald">SENT</flux:badge>
                                    <div class="font-mono text-[10px] text-zinc-400 mt-1 truncate max-w-[120px]"
                                        title="{{ $reg->satuSehatEncounter->ihs_number }}">
                                        {{ $reg->satuSehatEncounter->ihs_number }}
                                    </div>
                                @else
                                    <flux:badge size="sm" color="zinc">NOT SENT</flux:badge>
                                @endif
                            </td>

                            {{-- Kolom Aksi --}}
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    @if ($bundle)
                                        <x-atoms.button variant="ghost" size="sm" icon="eye"
                                            wire:click="showItems('{{ $bundle->id }}')"
                                            tooltip="Lihat Detail Item" />
                                    @endif

                                    <x-atoms.button variant="primary" size="sm" icon="arrow-path"
                                        wire:click="syncSingle('{{ $reg->no_rawat }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="syncSingle('{{ $reg->no_rawat }}')"
                                        :disabled="!$reg->pasien->satuSehatPatient || $bundleRunning"
                                        :tooltip="$bundleRunning ? 'Bundle sedang diproses...' : 'Kirim Bundle'" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-zinc-500">Data tidak ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-zinc-100 dark:border-primary-dark-700">
            {{ $registrations->links() }}
        </div>
    </div>

    {{-- Detail Item Modal --}}
    <x-organisms.modal wire:model="showItemModal" title="Detail Pengiriman Satu Sehat" maxWidth="5xl">
        @if ($selectedLog)
            <div class="space-y-6">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-sm text-zinc-500">No. Rawat</div>
                        <div class="text-lg font-bold">{{ $selectedLog->no_rawat }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-zinc-500">Status Bundle</div>
                        <flux:badge :color="$selectedLog->status_color" size="lg">
                            {{ strtoupper($selectedLog->status) }}</flux:badge>
                    </div>
                </div>

                <div class="border rounded-xl overflow-hidden">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-zinc-50 dark:bg-primary-dark-900 border-b">
                            <tr>
                                <th class="px-4 py-3">Resource</th>
                                <th class="px-4 py-3">ID Lokal</th>
                                <th class="px-4 py-3">IHS ID</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($selectedLog->items as $item)
                                <tr class="hover:bg-zinc-50 transition-colors">
                                    <td class="px-4 py-3 font-bold">{{ $item->resource_type }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-zinc-500">{{ $item->local_id ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-primary-600">
                                        {{ $item->ihs_id ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <flux:badge size="sm" :color="$item->status_color"
                                            :title="$item->error_message">{{ strtoupper($item->status) }}</flux:badge>
                                        @if ($item->error_message)
                                            <div class="text-[10px] text-red-500 mt-1 max-w-[200px] truncate"
                                                title="{{ $item->error_message }}">
                                                {{ $item->error_message }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            @if ($item->payload)
                                                <flux:button size="xs" icon="code-bracket" variant="ghost"
                                                    title="Lihat Payload"
                                                    wire:click="viewJson('Payload {{ $item->resource_type }}', {{ json_encode($item->payload) }})" />
                                            @endif
                                            @if ($item->response)
                                                <flux:button size="xs" icon="chat-bubble-left-right"
                                                    variant="ghost" title="Lihat Response"
                                                    wire:click="viewJson('Response {{ $item->resource_type }}', {{ json_encode($item->response) }})" />
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Legacy Errors Section Removed --}}
            </div>
        @endif
    </x-organisms.modal>

    {{-- Bulk Send Modal --}}
    <x-organisms.modal wire:model="showBulkModal" title="Pengiriman Bundle Massal" maxWidth="4xl">
        <div class="space-y-5">
            <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                Konfigurasikan parameter pengiriman bundle Satu Sehat secara massal. Job akan dibuat dan dieksekusi per
                tanggal.
            </p>

            {{-- Interval Tanggal --}}
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

            {{-- Status Rawatan --}}
            <flux:field>
                <flux:label>Status Rawatan</flux:label>
                <flux:select wire:model="bulkStatusRawat">
                    <flux:select.option value="all">Semua</flux:select.option>
                    <flux:select.option value="IGD">IGD</flux:select.option>
                    <flux:select.option value="Ralan">Rawat Jalan</flux:select.option>
                    <flux:select.option value="Ranap">Rawat Inap</flux:select.option>
                </flux:select>
            </flux:field>

            {{-- Tipe Pengiriman --}}
            <flux:field>
                <flux:label>Tipe Pengiriman</flux:label>
                <flux:radio.group wire:model="bulkTipePengiriman" variant="cards">
                    <flux:radio value="all" label="Semua" description="Kirim semua rawatan pada periode ini" />
                    <flux:radio value="resend" label="Pengiriman Ulang"
                        description="Hanya rawatan yang sudah pernah dikirim (bundle sudah ada)" />
                    <flux:radio value="new" label="Pengiriman Baru"
                        description="Hanya rawatan yang belum pernah dikirim (belum ada bundle)" />
                </flux:radio.group>
            </flux:field>

            {{-- Info --}}
            <div
                class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-800/30">
                <flux:icon name="information-circle" class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" />
                <p class="text-xs text-amber-700 dark:text-amber-400">
                    Job akan dibuat <strong>per tanggal registrasi</strong> dan dimasukkan ke antrian secara berurutan.
                    Proses berjalan di background dan tidak mengganggu sistem.
                </p>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showBulkModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="paper-airplane" wire:click="syncBulk"
                    wire:loading.attr="disabled" wire:target="syncBulk">
                    <span wire:loading.remove wire:target="syncBulk">Mulai Kirim</span>
                    <span wire:loading wire:target="syncBulk">Memproses...</span>
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- JSON Modal --}}
    <x-organisms.modal wire:model="showJsonModal" :title="$jsonTitle" maxWidth="3xl">
        <div class="bg-zinc-950 rounded-xl p-4 overflow-auto max-h-[60vh]">
            <pre class="text-xs font-mono text-emerald-400">{{ $jsonData }}</pre>
        </div>
    </x-organisms.modal>
</div>
