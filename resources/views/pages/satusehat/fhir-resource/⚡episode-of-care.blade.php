<?php

use App\Models\FhirDictionary;
use App\Models\SatuSehat\SatuSehatEpisodeOfCare;
use App\Services\SatuSehat\Resources\EpisodeOfCareService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Satu Sehat — Episode of Care')] class extends Component {
    use WithPagination;

    #[Url]
    public string $activeTab = 'deteksi';
    #[Url]
    public string $search = '';
    #[Url]
    public string $filterEoc = '';
    #[Url]
    public string $filterStatus = '';
    #[Url]
    public int $perPage = 25;

    // Modal kirim
    public bool $showSendModal = false;
    public ?array $sendEpisode = null;
    public bool $isSending = false;
    public string $sendError = '';

    // Modal update status
    public bool $showUpdateModal = false;
    public ?string $updateLocalId = null;
    public string $updateNewStatus = '';
    public string $updateError = '';

    // Modal detail
    public bool $showDetailModal = false;
    public ?SatuSehatEpisodeOfCare $detailRecord = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterEoc(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->search = '';
        $this->filterEoc = '';
        $this->filterStatus = '';
        $this->resetPage();
    }

    // ── Refresh cache ─────────────────────────────────────────────────────────

    public function refreshDeteksi(): void
    {
        app(EpisodeOfCareService::class)->clearDetectionCache();
        $this->toastSuccess('Cache deteksi dihapus, data akan di-scan ulang.');
    }

    // ── Kirim ─────────────────────────────────────────────────────────────────

    public function confirmSend(array $episode): void
    {
        $this->sendEpisode = $episode;
        $this->sendError = '';
        $this->showSendModal = true;
    }

    public function executeSend(): void
    {
        if (!$this->sendEpisode) {
            return;
        }

        $this->isSending = true;
        $this->sendError = '';

        try {
            app(EpisodeOfCareService::class)->sendEpisode($this->sendEpisode['no_rkm_medis'], $this->sendEpisode['eoc_code'], new \DateTime($this->sendEpisode['first_date']));

            $this->showSendModal = false;
            $this->sendEpisode = null;
            $this->toastSuccess('Episode of Care berhasil dikirim ke SatuSehat.');
        } catch (\Throwable $e) {
            $this->sendError = $e->getMessage();
        } finally {
            $this->isSending = false;
        }
    }

    // ── Update Status ─────────────────────────────────────────────────────────

    public function confirmUpdateStatus(string $localId, string $newStatus): void
    {
        $this->updateLocalId = $localId;
        $this->updateNewStatus = $newStatus;
        $this->updateError = '';
        $this->showUpdateModal = true;
    }

    public function executeUpdateStatus(): void
    {
        if (!$this->updateLocalId) {
            return;
        }

        try {
            app(EpisodeOfCareService::class)->updateEpisodeStatus($this->updateLocalId, $this->updateNewStatus);

            $this->showUpdateModal = false;
            $this->toastSuccess('Status Episode of Care berhasil diperbarui.');
        } catch (\Throwable $e) {
            $this->updateError = $e->getMessage();
        }
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    public function openDetail(string $localId): void
    {
        $this->detailRecord = SatuSehatEpisodeOfCare::where('local_id', $localId)->first();
        $this->showDetailModal = true;
    }

    // ── Data ─────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $eocTypes = FhirDictionary::where('type', 'episode-of-care-type')->orderBy('system_term')->get();

        if ($this->activeTab === 'deteksi') {
            return $this->withDeteksi($eocTypes);
        }

        return $this->withTerkirim($eocTypes);
    }

    private function withDeteksi($eocTypes): array
    {
        $all = app(EpisodeOfCareService::class)->detectAllCached();

        $search = strtolower($this->search);

        $filtered = $all
            ->when($search, fn($c) => $c->filter(fn($e) => str_contains(strtolower($e['nm_pasien']), $search) || str_contains($e['no_rkm_medis'], $search) || str_contains($e['no_ktp'] ?? '', $search)))
            ->when($this->filterEoc, fn($c) => $c->filter(fn($e) => $e['eoc_code'] === $this->filterEoc))
            ->when($this->filterStatus === 'pending', fn($c) => $c->filter(fn($e) => !$e['sent'] && $e['prereq']['passed'] && !$e['prereq']['warning']))
            ->when($this->filterStatus === 'warning', fn($c) => $c->filter(fn($e) => !$e['sent'] && $e['prereq']['warning']))
            ->when($this->filterStatus === 'blocked', fn($c) => $c->filter(fn($e) => !$e['prereq']['passed']))
            ->when($this->filterStatus === 'sent', fn($c) => $c->filter(fn($e) => $e['sent']));

        $statTotal = $all->count();
        $statSent = $all->filter(fn($e) => $e['sent'])->count();
        $statPending = $all->filter(fn($e) => !$e['sent'] && $e['prereq']['passed'])->count();
        $statBlocked = $all->filter(fn($e) => !$e['prereq']['passed'])->count();

        $page = $this->getPage();
        $episodes = new \Illuminate\Pagination\LengthAwarePaginator($filtered->forPage($page, $this->perPage)->values(), $filtered->count(), $this->perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);

        return compact('eocTypes', 'episodes', 'statTotal', 'statSent', 'statPending', 'statBlocked');
    }

    private function withTerkirim($eocTypes): array
    {
        $query = SatuSehatEpisodeOfCare::query()
            ->when(
                $this->search,
                fn($q) => $q->where(
                    fn($sq) => $sq
                        ->where('local_id', 'like', "%{$this->search}%")
                        ->orWhere('ihs_number', 'like', "%{$this->search}%")
                        ->orWhere('type_code', 'like', "%{$this->search}%"),
                ),
            )
            ->when($this->filterEoc, fn($q) => $q->where('type_code', $this->filterEoc))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->orderByDesc('created_at');

        $episodes = $query->paginate($this->perPage);

        return compact('eocTypes', 'episodes');
    }
};
?>

<div>
    <x-ui.page-header title="Satu Sehat — Episode of Care"
        subtitle="Deteksi dan pengiriman Episode of Care berdasarkan mapping ICD-10 ke SatuSehat">
        <x-slot:actions>
            @if ($activeTab === 'deteksi')
                <x-atoms.button variant="outline" icon="arrow-path" wire:click="refreshDeteksi">
                    Refresh Deteksi
                </x-atoms.button>
            @endif
            <x-atoms.button variant="outline" icon="map" as="a"
                href="{{ route('local.episode-of-care.index') }}" wire:navigate>
                Kelola Mapping
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Tab Navigation --}}
    <div class="mb-6">
        <x-molecules.tabs>
            <x-atoms.tab-item wire:click="switchTab('deteksi')" :active="$activeTab === 'deteksi'">
                Deteksi Episode
            </x-atoms.tab-item>
            <x-atoms.tab-item wire:click="switchTab('terkirim')" :active="$activeTab === 'terkirim'">
                Terkirim
            </x-atoms.tab-item>
        </x-molecules.tabs>
    </div>

    {{-- ══════════════════ TAB: DETEKSI ══════════════════ --}}
    @if ($activeTab === 'deteksi')

        {{-- Stats --}}
        <div class="grid grid-cols-2 gap-4 mb-6 sm:grid-cols-4">
            <x-organisms.stat-card title="Total Terdeteksi" :value="number_format($statTotal)" icon="document-magnifying-glass"
                color="zinc" />
            <x-organisms.stat-card title="Menunggu Kirim" :value="number_format($statPending)" icon="paper-airplane" color="blue" />
            <x-organisms.stat-card title="Sudah Terkirim" :value="number_format($statSent)" icon="check-circle" color="emerald" />
            <x-organisms.stat-card title="Terblokir" :value="number_format($statBlocked)" icon="x-circle" color="red" />
        </div>

        <x-organisms.data-panel>
            <x-slot:filter>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                            placeholder="Cari no. RM, nama, NIK..." clearable />
                    </div>
                    <flux:select wire:model.live="filterEoc" class="sm:w-52">
                        <flux:select.option value="">Semua Episode Type</flux:select.option>
                        @foreach ($eocTypes as $et)
                            <flux:select.option value="{{ $et->system_code }}">
                                {{ $et->system_code }} — {{ $et->system_term }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="filterStatus" class="sm:w-40">
                        <flux:select.option value="">Semua Status</flux:select.option>
                        <flux:select.option value="pending">Menunggu Kirim</flux:select.option>
                        <flux:select.option value="warning">Peringatan</flux:select.option>
                        <flux:select.option value="blocked">Terblokir</flux:select.option>
                        <flux:select.option value="sent">Terkirim</flux:select.option>
                    </flux:select>
                    <flux:select wire:model.live="perPage" class="w-20">
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                    </flux:select>
                </div>
            </x-slot:filter>

            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="w-32">No. RM</x-atoms.table-heading>
                    <x-atoms.table-heading>Nama Pasien</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-10">JK</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-44">Episode Type</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-28">ICD-10 Cocok</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-28">Tgl Pertama</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-32">Prasyarat</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-24">Status</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-20" align="center">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($episodes as $ep)
                    <x-molecules.table-row wire:key="eoc-{{ $ep['local_id'] }}">

                        <x-atoms.table-cell nowrap>
                            <span class="font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">
                                {{ $ep['no_rkm_medis'] }}
                            </span>
                        </x-atoms.table-cell>

                        <x-atoms.table-cell>
                            <p class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                {{ $ep['nm_pasien'] }}
                            </p>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500 font-mono">
                                {{ $ep['no_ktp'] ?: '-' }}
                            </p>
                        </x-atoms.table-cell>

                        <x-atoms.table-cell>
                            <flux:badge size="sm" :color="($ep['jk'] ?? '') === 'L' ? 'blue' : 'pink'">
                                {{ $ep['jk'] ?? '?' }}
                            </flux:badge>
                        </x-atoms.table-cell>

                        <x-atoms.table-cell nowrap>
                            <flux:badge size="sm" color="zinc" class="font-mono font-bold">
                                {{ $ep['eoc_code'] }}
                            </flux:badge>
                        </x-atoms.table-cell>

                        <x-atoms.table-cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach (array_slice($ep['icd10_codes'], 0, 3) as $code)
                                    <flux:badge size="sm" color="violet" class="font-mono">{{ $code }}
                                    </flux:badge>
                                @endforeach
                                @if (count($ep['icd10_codes']) > 3)
                                    <flux:badge size="sm" color="zinc">+{{ count($ep['icd10_codes']) - 3 }}
                                    </flux:badge>
                                @endif
                            </div>
                        </x-atoms.table-cell>

                        <x-atoms.table-cell nowrap class="text-xs text-zinc-500 dark:text-primary-dark-400">
                            {{ $ep['first_date'] ? \Carbon\Carbon::parse($ep['first_date'])->format('d/m/Y') : '-' }}
                        </x-atoms.table-cell>

                        <x-atoms.table-cell>
                            @if (!$ep['prereq']['passed'])
                                <div class="flex items-start gap-1.5">
                                    <flux:icon name="x-circle" class="w-3.5 h-3.5 text-red-500 shrink-0 mt-0.5" />
                                    <span class="text-xs text-red-600 dark:text-red-400 leading-snug">
                                        {{ $ep['prereq']['message'] }}
                                    </span>
                                </div>
                            @elseif ($ep['prereq']['warning'])
                                <div class="flex items-start gap-1.5">
                                    <flux:icon name="exclamation-triangle"
                                        class="w-3.5 h-3.5 text-amber-500 shrink-0 mt-0.5" />
                                    <span class="text-xs text-amber-600 dark:text-amber-400 leading-snug">
                                        {{ $ep['prereq']['message'] }}
                                    </span>
                                </div>
                            @else
                                <span class="text-xs text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                    <flux:icon name="check-circle" class="w-3.5 h-3.5" />
                                    Terpenuhi
                                </span>
                            @endif
                        </x-atoms.table-cell>

                        <x-atoms.table-cell nowrap>
                            @if ($ep['sent'])
                                <flux:badge size="sm" color="emerald">Terkirim</flux:badge>
                                @if ($ep['sent_status'])
                                    <p class="text-[10px] text-zinc-400 dark:text-primary-dark-500 mt-0.5">
                                        {{ $ep['sent_status'] }}
                                    </p>
                                @endif
                            @elseif (!$ep['prereq']['passed'])
                                <flux:badge size="sm" color="red">Terblokir</flux:badge>
                            @elseif ($ep['prereq']['warning'])
                                <flux:badge size="sm" color="amber">Perlu Cek</flux:badge>
                            @else
                                <flux:badge size="sm" color="blue">Siap Kirim</flux:badge>
                            @endif
                        </x-atoms.table-cell>

                        <x-atoms.table-cell align="center" :action="true">
                            @if ($ep['sent'])
                                <x-atoms.button variant="ghost" size="sm" icon="eye"
                                    wire:click="openDetail('{{ $ep['local_id'] }}')" tooltip="Lihat detail">
                                </x-atoms.button>
                            @elseif ($ep['prereq']['passed'])
                                <x-atoms.button variant="ghost" size="sm" icon="paper-airplane"
                                    wire:click="confirmSend({{ json_encode($ep) }})" :tooltip="$ep['prereq']['warning']
                                        ? 'Kirim (ada peringatan)'
                                        : 'Kirim ke SatuSehat'">
                                </x-atoms.button>
                            @endif
                        </x-atoms.table-cell>

                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="9">
                            <x-ui.empty-state icon="document-magnifying-glass" title="Tidak ada episode terdeteksi"
                                description="Tidak ada pasien dengan diagnosa yang cocok dengan mapping ICD-10, atau belum ada pasien yang terdaftar di SatuSehat." />
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse
            </x-organisms.table>

            <x-slot:footer>
                @if ($episodes->hasPages())
                    {{ $episodes->links() }}
                @endif
            </x-slot:footer>
        </x-organisms.data-panel>

    @endif

    {{-- ══════════════════ TAB: TERKIRIM ══════════════════ --}}
    @if ($activeTab === 'terkirim')

        <x-organisms.data-panel>
            <x-slot:filter>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                            placeholder="Cari local ID, IHS, episode type..." clearable />
                    </div>
                    <flux:select wire:model.live="filterEoc" class="sm:w-52">
                        <flux:select.option value="">Semua Episode Type</flux:select.option>
                        @foreach ($eocTypes as $et)
                            <flux:select.option value="{{ $et->system_code }}">
                                {{ $et->system_code }} — {{ $et->system_term }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="filterStatus" class="sm:w-40">
                        <flux:select.option value="">Semua Status</flux:select.option>
                        <flux:select.option value="active">Active</flux:select.option>
                        <flux:select.option value="finished">Finished</flux:select.option>
                        <flux:select.option value="onhold">On Hold</flux:select.option>
                        <flux:select.option value="cancelled">Cancelled</flux:select.option>
                    </flux:select>
                    <flux:select wire:model.live="perPage" class="w-20">
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                    </flux:select>
                </div>
            </x-slot:filter>

            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading class="w-48">Local ID</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-48">IHS Number</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-40">Episode Type</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-28">Status</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-32">Period Start</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-32">Tgl Kirim</x-atoms.table-heading>
                    <x-atoms.table-heading class="w-24" align="center">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($episodes as $rec)
                    <x-molecules.table-row wire:key="sent-{{ $rec->id }}">

                        <x-atoms.table-cell nowrap>
                            <span class="font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">
                                {{ $rec->local_id }}
                            </span>
                        </x-atoms.table-cell>

                        <x-atoms.table-cell nowrap>
                            <span class="font-mono text-xs text-zinc-600 dark:text-primary-dark-300">
                                {{ $rec->ihs_number }}
                            </span>
                        </x-atoms.table-cell>

                        <x-atoms.table-cell nowrap>
                            <flux:badge size="sm" color="zinc" class="font-mono font-bold">
                                {{ $rec->type_code }}
                            </flux:badge>
                            @if ($rec->type_display)
                                <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">
                                    {{ $rec->type_display }}
                                </p>
                            @endif
                        </x-atoms.table-cell>

                        <x-atoms.table-cell nowrap>
                            @php
                                $statusColor = match ($rec->status) {
                                    'active' => 'emerald',
                                    'finished' => 'zinc',
                                    'onhold' => 'amber',
                                    'cancelled' => 'red',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge size="sm" :color="$statusColor">{{ $rec->status }}</flux:badge>
                        </x-atoms.table-cell>

                        <x-atoms.table-cell nowrap class="text-xs text-zinc-500 dark:text-primary-dark-400">
                            {{ $rec->period_start?->format('d/m/Y') ?? '-' }}
                        </x-atoms.table-cell>

                        <x-atoms.table-cell nowrap class="text-xs text-zinc-500 dark:text-primary-dark-400">
                            {{ $rec->created_at?->format('d/m/Y H:i') ?? '-' }}
                        </x-atoms.table-cell>

                        <x-atoms.table-cell align="center" :action="true">
                            <x-atoms.button variant="ghost" size="sm"
                                wire:click="openDetail('{{ $rec->local_id }}')" tooltip="Lihat detail">
                                <flux:icon name="eye" class="size-4" />
                            </x-atoms.button>
                            @if ($rec->status === 'active')
                                <x-atoms.button variant="ghost" size="sm"
                                    wire:click="confirmUpdateStatus('{{ $rec->local_id }}', 'finished')"
                                    tooltip="Selesaikan episode">
                                    <flux:icon name="check-badge" class="size-4" />
                                </x-atoms.button>
                            @endif
                        </x-atoms.table-cell>

                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="7">
                            <x-ui.empty-state icon="paper-airplane" title="Belum ada episode terkirim"
                                description="Episode yang berhasil dikirim ke SatuSehat akan muncul di sini." />
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse
            </x-organisms.table>

            <x-slot:footer>
                @if ($episodes->hasPages())
                    {{ $episodes->links() }}
                @endif
            </x-slot:footer>
        </x-organisms.data-panel>

    @endif

    {{-- ═══ Modal: Konfirmasi Kirim ═══ --}}
    <x-organisms.modal wire:model="showSendModal" maxWidth="lg" title="Kirim Episode of Care" :description="$sendEpisode ? $sendEpisode['no_rkm_medis'] . ' — ' . $sendEpisode['nm_pasien'] : ''">

        @if ($sendEpisode)
            <div class="space-y-4">

                @if ($sendEpisode['prereq']['warning'])
                    <div
                        class="flex items-start gap-3 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                        <p class="text-sm text-amber-700 dark:text-amber-300">
                            {{ $sendEpisode['prereq']['message'] }}
                        </p>
                    </div>
                @endif

                @php
                    $infoRows = [
                        'Episode Type' => $sendEpisode['eoc_code'],
                        'IHS Pasien' => $sendEpisode['ihs_patient'] ?? '-',
                        'Tgl Pertama Dx' => $sendEpisode['first_date']
                            ? \Carbon\Carbon::parse($sendEpisode['first_date'])->format('d/m/Y')
                            : '-',
                        'Local ID' => $sendEpisode['local_id'],
                    ];
                @endphp

                <div class="grid grid-cols-2 gap-x-6 gap-y-3">
                    @foreach ($infoRows as $label => $value)
                        <div>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">{{ $label }}</p>
                            <p class="mt-0.5 text-sm font-medium text-zinc-800 dark:text-primary-dark-100 font-mono">
                                {{ $value }}
                            </p>
                        </div>
                    @endforeach
                </div>

                <div>
                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mb-1.5">ICD-10 yang cocok</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($sendEpisode['icd10_codes'] as $code)
                            <flux:badge size="sm" color="violet" class="font-mono">{{ $code }}
                            </flux:badge>
                        @endforeach
                    </div>
                </div>

                @if ($sendError)
                    <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $sendError }}</p>
                    </div>
                @endif

            </div>
        @endif

        <x-slot:footer>
            <div class="flex items-center justify-between w-full">
                <x-atoms.button variant="ghost" wire:click="$set('showSendModal', false)">
                    Batal
                </x-atoms.button>
                <x-atoms.button variant="primary" icon="paper-airplane" wire:click="executeSend"
                    :wire:loading.attr="'disabled'">
                    <span wire:loading.remove wire:target="executeSend">Kirim Sekarang</span>
                    <span wire:loading wire:target="executeSend">Mengirim...</span>
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- ═══ Modal: Update Status ═══ --}}
    <x-organisms.modal wire:model="showUpdateModal" maxWidth="sm" title="Perbarui Status Episode"
        :description="$updateLocalId ?? ''">

        <div class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-primary-dark-300">
                Ubah status Episode of Care
                <span class="font-mono font-semibold">{{ $updateLocalId }}</span>
                menjadi
                <flux:badge size="sm" color="zinc">{{ $updateNewStatus }}</flux:badge>?
            </p>

            @if ($updateNewStatus === 'finished')
                <div
                    class="flex items-start gap-2 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/50">
                    <flux:icon name="information-circle" class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" />
                    <p class="text-xs text-blue-700 dark:text-blue-300">
                        Tanggal akhir episode akan diisi dengan waktu sekarang.
                    </p>
                </div>
            @endif

            @if ($updateError)
                <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $updateError }}</p>
                </div>
            @endif
        </div>

        <x-slot:footer>
            <div class="flex items-center justify-between w-full">
                <x-atoms.button variant="ghost" wire:click="$set('showUpdateModal', false)">
                    Batal
                </x-atoms.button>
                <x-atoms.button variant="primary" icon="check" wire:click="executeUpdateStatus">
                    Perbarui Status
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- ═══ Modal: Detail Record ═══ --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="3xl" title="Detail Episode of Care" :description="$detailRecord?->local_id ?? ''">

        @if ($detailRecord)
            <div class="space-y-4">

                @php
                    $detailFields = [
                        'IHS Number' => $detailRecord->ihs_number,
                        'Local ID' => $detailRecord->local_id,
                        'Episode Type' => "{$detailRecord->type_code} — {$detailRecord->type_display}",
                        'Status' => $detailRecord->status,
                        'Patient IHS' => $detailRecord->patient_ihs,
                        'Organization IHS' => $detailRecord->managing_organization_ihs,
                        'Period Start' => $detailRecord->period_start?->format('d/m/Y H:i'),
                        'Period End' => $detailRecord->period_end?->format('d/m/Y H:i') ?? '-',
                        'Synced At' => $detailRecord->synced_at?->format('d/m/Y H:i'),
                    ];
                @endphp

                <div class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                    @foreach ($detailFields as $label => $value)
                        <div>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">{{ $label }}</p>
                            <p class="mt-0.5 text-sm font-medium text-zinc-800 dark:text-primary-dark-100 break-all">
                                {{ $value ?? '-' }}
                            </p>
                        </div>
                    @endforeach
                </div>

                @if ($detailRecord->raw_response)
                    <div>
                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mb-1.5">Raw Response</p>
                        <x-atoms.code-block language="json" maxHeight="max-h-60">{{ json_encode($detailRecord->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </div>
                @endif

            </div>
        @endif

        <x-slot:footer>
            <div class="flex items-center justify-between w-full">
                @if ($detailRecord?->status === 'active')
                    <x-atoms.button variant="outline" size="sm" icon="check-badge"
                        wire:click="confirmUpdateStatus('{{ $detailRecord->local_id }}', 'finished')">
                        Selesaikan Episode
                    </x-atoms.button>
                @else
                    <div></div>
                @endif
                <x-atoms.button variant="ghost" wire:click="$set('showDetailModal', false)">
                    Tutup
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

</div>
