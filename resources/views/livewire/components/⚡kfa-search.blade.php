<?php

use App\Services\SatuSehat\KfaService;
use Livewire\Attributes\Prop;
use Livewire\Component;

/**
 * Komponen pencarian KFA (Kamus Farmasi dan Alkes).
 *
 * Penggunaan:
 *   <livewire:components.kfa-search defaultType="farmasi" />
 *
 * Event yang di-dispatch saat item dipilih:
 *   kfa-selected → { kfa_code, name, kfa_type, system_url }
 */
new class extends Component {
    /** Jenis default: 'farmasi' (KFA v2) atau 'alkes' (KFA v3) */
    #[Prop]
    public string $defaultType = 'farmasi';

    public string $kfaType = 'farmasi';
    public string $search = '';
    public array $results = [];
    public bool $loading = false;
    public ?string $error = null;
    public int $page = 1;
    public int $totalPage = 1;

    /** Sembunyikan pilihan KFA v3 (Alkes) jika true */
    #[Prop]
    public bool $onlyV2 = false;

    /** Sembunyikan pilihan KFA v2 (Farmasi) jika true */
    #[Prop]
    public bool $onlyV3 = false;

    #[Prop]
    public ?string $initialSearch = '';

    public function mount(): void
    {
        if ($this->onlyV3) {
            $this->kfaType = 'alkes';
        } elseif ($this->onlyV2) {
            $this->kfaType = 'farmasi';
        } else {
            $this->kfaType = $this->defaultType;
        }

        if (!empty($this->initialSearch)) {
            $this->search = $this->initialSearch;
            $this->doSearch();
        }
    }

    public function updatedKfaType(): void
    {
        $this->reset(['search', 'results', 'error']);
        $this->page = 1;
        $this->totalPage = 1;
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->doSearch();
    }

    public function doSearch(): void
    {
        if (strlen($this->search) < 2) {
            $this->results = [];
            return;
        }

        $this->loading = true;
        $this->error = null;

        try {
            $service = app(KfaService::class);

            if ($this->kfaType === 'alkes') {
                $data = $service->searchAlkesProducts($this->search, $this->page, 10);
                $items = $data['data'] ?? [];

                $this->results = array_map(
                    fn($item) => [
                        'kfa_code' => $item['kfa_code'] ?? '',
                        'name' => $item['display_name'] ?? '',
                        'active' => $item['active'] ?? null,
                        'type_name' => 'Alat Kesehatan',
                        'fix_price' => null,
                        'system_url' => 'https://farmalkes.kemkes.go.id',
                        'payload' => $item,
                    ],
                    $items,
                );

                $this->totalPage = max(1, (int) ($data['meta']['page']['total'] ?? 1));
            } else {
                $data = $service->searchFarmasi($this->search, $this->page, 10);
                $items = $data['items']['data'] ?? [];

                $this->results = array_map(
                    fn($item) => [
                        'kfa_code' => $item['kfa_code'] ?? '',
                        'name' => $item['name'] ?? '',
                        'active' => $item['active'] ?? null,
                        'type_name' => $item['farmalkes_type']['name'] ?? 'Farmasi',
                        'fix_price' => $item['fix_price'] ?? null,
                        'system_url' => 'http://sys-ids.kemkes.go.id/kfa',
                        'payload' => $item,
                    ],
                    $items,
                );

                $total = $data['total'] ?? 0;
                $this->totalPage = (int) ceil($total / 10) ?: 1;
            }
        } catch (\Exception $e) {
            $this->error = 'Gagal menghubungi API KFA: ' . $e->getMessage();
            $this->results = [];
        } finally {
            $this->loading = false;
        }
    }

    public function prevPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->doSearch();
        }
    }

    public function nextPage(): void
    {
        if ($this->page < $this->totalPage) {
            $this->page++;
            $this->doSearch();
        }
    }

    /** Dispatch event ke parent saat item dipilih */
    public function select(string $kfaCode, string $name, array $payload = [], string $systemUrl = ''): void
    {
        if ($this->kfaType === 'farmasi') {
            try {
                $detail = app(KfaService::class)->getFarmasiDetail($kfaCode);
                if (!empty($detail['result'])) {
                    $payload = array_merge($payload, $detail['result']);
                }
            } catch (\Exception $e) {
                // Abaikan error, gunakan payload seadanya
            }
        }

        $this->dispatch('kfa-selected', kfa_code: $kfaCode, name: $name, kfa_type: $this->kfaType, system_url: $systemUrl, payload: $payload);
    }

    public function placeholder()
    {
        return <<<'HTML'
        <div class="flex flex-col items-center justify-center p-6 space-y-3 bg-zinc-50 dark:bg-primary-dark-900/50 rounded-xl border border-zinc-200 dark:border-primary-dark-800 animate-pulse">
            <svg class="animate-spin h-6 w-6 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-sm font-medium text-zinc-500 dark:text-primary-dark-400">Menyambungkan ke KFA...</p>
        </div>
        HTML;
    }
};
?>

<div class="space-y-3">
    {{-- Tab Farmasi / Alkes --}}
    @if (!$onlyV2 && !$onlyV3)
        <div class="flex gap-1 p-1 bg-zinc-100 rounded-lg dark:bg-primary-dark-900">
            <button wire:click="$set('kfaType', 'farmasi')"
                class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors
                {{ $kfaType === 'farmasi'
                    ? 'bg-white text-zinc-900 shadow dark:bg-primary-dark-700 dark:text-primary-dark-100'
                    : 'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400 dark:hover:text-primary-dark-200' }}">
                Farmasi <span class="text-xs opacity-60">(KFA v2)</span>
            </button>
            <button wire:click="$set('kfaType', 'alkes')"
                class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors
                {{ $kfaType === 'alkes'
                    ? 'bg-white text-zinc-900 shadow dark:bg-primary-dark-700 dark:text-primary-dark-100'
                    : 'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400 dark:hover:text-primary-dark-200' }}">
                Alat Kesehatan <span class="text-xs opacity-60">(KFA v3)</span>
            </button>
        </div>
    @endif

    {{-- Input pencarian --}}
    <flux:input wire:model.live.debounce.600ms="search"
        placeholder="{{ $kfaType === 'alkes' ? 'Cari nama alat kesehatan atau kode KFA...' : 'Cari nama obat, kode KFA, atau NIE...' }}"
        icon="magnifying-glass" clearable />

    {{-- Error --}}
    @if ($error)
        <div class="p-3 text-sm text-red-700 rounded-lg bg-red-50 dark:bg-red-900/20 dark:text-red-400">
            {{ $error }}
        </div>
    @endif

    {{-- Hasil --}}
    <div
        class="overflow-y-auto border divide-y rounded-lg max-h-72 border-zinc-200 dark:border-primary-dark-700 dark:divide-primary-dark-700">
        @if ($loading)
            <div class="flex items-center justify-center py-10 gap-2 text-zinc-400">
                <flux:icon name="arrow-path" class="w-5 h-5 animate-spin" />
                <span class="text-sm">Mencari di KFA...</span>
            </div>
        @elseif (count($results) > 0)
            @foreach ($results as $item)
                <div class="p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-primary-dark-800"
                    wire:click="select('{{ $item['kfa_code'] }}', '{{ addslashes($item['name']) }}', {{ json_encode($item['payload']) }}, '{{ $item['system_url'] }}')">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100 truncate">
                                {{ $item['name'] ?: '-' }}
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">
                                {{ $item['type_name'] }}
                                @if ($item['fix_price'])
                                    · Rp {{ number_format($item['fix_price'], 0, ',', '.') }}
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            <span class="font-mono text-xs font-bold text-primary-600 dark:text-primary-400">
                                {{ $item['kfa_code'] ?: '-' }}
                            </span>
                            @if (isset($item['active']))
                                <flux:badge size="sm" color="{{ $item['active'] ? 'green' : 'zinc' }}">
                                    {{ $item['active'] ? 'Aktif' : 'Nonaktif' }}
                                </flux:badge>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="flex flex-col items-center justify-center py-10 text-center">
                <flux:icon name="{{ strlen($search) >= 2 ? 'document-text' : 'magnifying-glass' }}"
                    class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
                <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                    {{ strlen($search) >= 2 ? 'Tidak ada hasil.' : 'Ketik minimal 2 karakter untuk mencari.' }}
                </p>
            </div>
        @endif
    </div>

    {{-- Paginasi --}}
    @if ($totalPage > 1)
        <div class="flex items-center justify-between text-xs text-zinc-500">
            <span>Hal. {{ $page }} / {{ $totalPage }}</span>
            <div class="flex gap-2">
                <flux:button size="xs" variant="ghost" icon="chevron-left" wire:click="prevPage"
                    :disabled="$page <= 1">
                    Sebelumnya</flux:button>
                <flux:button size="xs" variant="ghost" icon-trailing="chevron-right" wire:click="nextPage"
                    :disabled="$page >= $totalPage">Berikutnya</flux:button>
            </div>
        </div>
    @endif
</div>
