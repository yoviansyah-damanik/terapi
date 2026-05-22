<?php

use Livewire\Attributes\Prop;
use Livewire\Component;

/**
 * Komponen pencarian resource Satu Sehat FHIR (generic).
 *
 * Penggunaan:
 *   <livewire:components.satusehat-resource-search
 *       :serviceClass="OrganizationService::class"
 *       resourceLabel="Organization" />
 *
 * Event yang di-dispatch saat item dipilih:
 *   satusehat-resource-selected → { resource: array }
 */
new class extends Component {
    /** FQCN service Satu Sehat yang akan dipanggil */
    #[Prop]
    public string $serviceClass;

    /** Nama method pencarian di service */
    #[Prop]
    public string $searchMethod = 'searchByName';

    /** Label resource untuk placeholder & pesan */
    #[Prop]
    public string $resourceLabel = 'Resource';

    /** Nilai awal search — jika diisi, langsung jalankan pencarian saat mount */
    #[Prop]
    public string $initialSearch = '';

    public string $search = '';
    public array $results = [];
    public ?string $error = null;

    public function mount(): void
    {
        if (!blank($this->initialSearch)) {
            $this->search = $this->initialSearch;
            $this->performSearch();
        }
    }

    public function performSearch(): void
    {
        if (blank($this->search)) {
            return;
        }

        $this->results = [];
        $this->error   = null;

        try {
            $response = app($this->serviceClass)->{$this->searchMethod}(trim($this->search));

            if (!$response->success) {
                $this->error = $response->error ?? 'Pencarian gagal.';
                return;
            }

            $resources = $response->getResources();

            if (empty($resources)) {
                $this->error = "Tidak ada {$this->resourceLabel} ditemukan.";
                return;
            }

            $valid = array_values(array_filter($resources, fn($r) => !empty($r['id'])));

            if (empty($valid)) {
                $this->toastError('Terjadi masalah pada server. Coba beberapa saat lagi.');
                return;
            }

            $this->results = $valid;
        } catch (\Exception) {
            $this->toastError('Terjadi masalah pada server. Coba beberapa saat lagi.');
        }
    }

    public function select(string $ihsNumber): void
    {
        $resource = collect($this->results)->firstWhere('id', $ihsNumber);
        if (!$resource) {
            return;
        }

        $this->dispatch('satusehat-resource-selected', resource: $resource);
    }

    public function resetState(): void
    {
        $this->reset(['search', 'results', 'error']);
    }
};
?>

<div class="space-y-4">
    {{-- Search bar --}}
    <div class="flex gap-2">
        <div class="flex-1">
            <flux:input wire:model="search" wire:keydown.enter="performSearch"
                placeholder="Nama {{ $resourceLabel }} di Satu Sehat..." icon="magnifying-glass" />
        </div>
        <x-atoms.button wire:click="performSearch" variant="primary" icon="magnifying-glass">
            <span wire:loading.remove wire:target="performSearch">Cari</span>
            <span wire:loading wire:target="performSearch">Mencari...</span>
        </x-atoms.button>
    </div>

    {{-- Error --}}
    @if ($error)
        <div
            class="flex items-center gap-2.5 px-3.5 py-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
            <flux:icon name="exclamation-circle" class="w-4 h-4 shrink-0" />
            {{ $error }}
        </div>
    @endif

    {{-- Hasil pencarian --}}
    @if (!empty($results))
        <div class="rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
            <div
                class="px-4 py-2.5 bg-zinc-50 dark:bg-primary-dark-900/40 border-b border-zinc-200 dark:border-primary-dark-700">
                <p class="text-xs font-semibold text-zinc-500 dark:text-primary-dark-400 uppercase tracking-wide">
                    {{ count($results) }} hasil ditemukan
                </p>
            </div>
            <ul class="divide-y divide-zinc-100 dark:divide-primary-dark-700/50 max-h-72 overflow-y-auto">
                @foreach ($results as $resource)
                    @php
                        $resIhs = $resource['id'] ?? '-';
                        $resNama = $resource['name'] ?? '-';
                        // Normalisasi status: Organization/HS pakai boolean 'active', Location pakai string 'status'
                        if (isset($resource['status'])) {
                            $resStatus = $resource['status'];
                        } elseif (isset($resource['active'])) {
                            $resStatus = $resource['active'] ? 'active' : 'inactive';
                        } else {
                            $resStatus = 'active';
                        }
                        $resMgOrg = isset($resource['managingOrganization']['reference'])
                            ? (last(explode('/', $resource['managingOrganization']['reference'])) ?: null)
                            : null;
                    @endphp
                    <li wire:click="select('{{ $resIhs }}')"
                        wire:loading.class="opacity-50 pointer-events-none" wire:target="select"
                        class="flex items-center justify-between gap-4 px-4 py-3 cursor-pointer select-none group hover:bg-emerald-50 dark:hover:bg-emerald-900/20 active:bg-emerald-100 dark:active:bg-emerald-900/30 transition-colors">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span
                                    class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ $resIhs }}</span>
                                <flux:badge :color="$resStatus === 'active' ? 'green' : 'zinc'" size="sm">
                                    {{ $resStatus === 'active' ? 'Aktif' : ucfirst($resStatus) }}
                                </flux:badge>
                            </div>
                            <p
                                class="mt-0.5 text-sm font-medium text-zinc-800 dark:text-primary-dark-200 truncate">
                                {{ $resNama }}</p>
                            @if ($resMgOrg)
                                <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500 font-mono">
                                    Org: {{ $resMgOrg }}</p>
                            @endif
                        </div>
                        <flux:icon name="check-circle"
                            class="w-5 h-5 shrink-0 text-emerald-400 dark:text-emerald-500 opacity-0 group-hover:opacity-100 transition-opacity" />
                    </li>
                @endforeach
            </ul>
        </div>
    @elseif (empty($results) && !$error)
        <div class="flex flex-col items-center gap-2 py-8 text-center text-zinc-400 dark:text-primary-dark-500">
            <flux:icon name="magnifying-glass" class="w-8 h-8 opacity-40" />
            <p class="text-sm">Ketik nama {{ $resourceLabel }} lalu klik <strong>Cari</strong></p>
        </div>
    @endif
</div>
