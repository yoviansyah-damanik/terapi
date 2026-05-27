<?php

use App\Models\SatuSehat\SatuSehatBundle;
use App\Models\SatuSehat\SatuSehatBundleLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

new #[Lazy] class extends Component {
    use WithPagination;

    #[Url(as: 'mg_resource')] public string $mgResourceType = '';
    #[Url(as: 'mg_search')]   public string $mgSearch       = '';
    #[Url(as: 'mg_per_page')] public int    $mgPerPage      = 25;

    public bool   $showBundlesModal  = false;
    public string $modalResourceType = '';
    public string $modalSimrsCode    = '';
    public array  $modalBundles      = [];

    public function updatedMgResourceType(): void { $this->resetPage('mgPage'); }
    public function updatedMgSearch(): void { $this->resetPage('mgPage'); }

    public function showBundleDetails(string $resourceType, string $simrsCode): void
    {
        $this->modalResourceType = $resourceType;
        $this->modalSimrsCode    = $simrsCode;

        $raw = SatuSehatBundleLog::query()
            ->join('satu_sehat_bundles as b', 'satu_sehat_bundle_logs.bundle_id', '=', 'b.id')
            ->select([
                'satu_sehat_bundle_logs.bundle_id',
                'satu_sehat_bundle_logs.local_id',
                'satu_sehat_bundle_logs.error_message',
                'b.no_rawat',
            ])
            ->where('satu_sehat_bundle_logs.status', 'warning')
            ->where($this->mappingFailureFilter())
            ->where('satu_sehat_bundle_logs.resource_type', $resourceType)
            ->get();

        $bundleIds = $raw
            ->filter(fn($e) => $this->extractSimrsCode($e) === $simrsCode)
            ->pluck('bundle_id')
            ->unique()
            ->values()
            ->all();

        $this->modalBundles = SatuSehatBundle::whereIn('id', $bundleIds)
            ->with('triggeredBy')
            ->latest()
            ->get()
            ->map(fn($b) => [
                'id'            => $b->id,
                'no_rawat'      => $b->no_rawat,
                'status'        => $b->status,
                'status_color'  => $b->status_color,
                'status_label'  => $b->status_label,
                'started_at'    => $b->started_at?->format('d M Y H:i:s'),
                'duration'      => $b->duration,
                'triggered_by'  => $b->triggeredBy?->name,
                'error_message' => $raw->firstWhere('bundle_id', $b->id)?->error_message,
                'erm_url'       => route('erm.detail', $b->no_rawat),
            ])
            ->toArray();

        $this->showBundlesModal = true;
    }

    public function closeBundleDetails(): void
    {
        $this->showBundlesModal = false;
        $this->modalBundles     = [];
    }

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('pages::logs._satusehat._skeleton-tab');
    }

    private function mappingFailureFilter(): \Closure
    {
        return fn($q) => $q->where('satu_sehat_bundle_logs.error_message', 'like', '%mapping%')->orWhere('satu_sehat_bundle_logs.error_message', 'like', '%KFA%')->orWhere('satu_sehat_bundle_logs.error_message', 'like', '%SNOMED%')->orWhere('satu_sehat_bundle_logs.error_message', 'like', '%LOINC%')->orWhere('satu_sehat_bundle_logs.error_message', 'like', '%dipetakan%');
    }

    private function extractSimrsCode(object $entry): string
    {
        $code = null;
        if (preg_match('/^\[([^\]]+)\]/', $entry->error_message ?? '', $m)) {
            $code = $m[1];
        }
        if (!$code && preg_match('/-(MED_REQ|PROC|LAB|RAD|ALERG|VKO|STMT|TND)_(.+?)-\d{8}/', $entry->local_id ?? '', $m)) {
            $parts = explode('_', $m[2]);
            $code  = end($parts);
        }
        return $code ?? ($entry->local_id ?? '?');
    }

    /** Kembalikan [table, key_column] mapping yang relevan untuk resource type + local_id. */
    private function resolveMappingTable(string $rt, string $localId): array
    {
        return match (true) {
            in_array($rt, ['MedicationRequest', 'MedicationDispense', 'MedicationStatement', 'Medication', 'Immunization'])
                => ['map_medication', 'local_code'],
            $rt === 'Procedure'
                => ['map_procedure', 'procedure_code'],
            $rt === 'AllergyIntolerance'
                => ['map_allergy', 'allergy_id'],
            $rt === 'Condition'
                => ['map_icd10', 'icd10_code'],
            str_contains($localId, '-LAB_')
                => ['map_lab', 'local_code'],
            str_contains($localId, '-RAD_')
                => ['map_rad', 'local_code'],
            default => [null, null],
        };
    }

    private function processMappingFailures(Collection $raw, string $search, int $perPage): LengthAwarePaginator
    {
        $processed = $raw->map(function ($entry) {
            $entry->simrs_code = $this->extractSimrsCode($entry);
            return $entry;
        });

        $grouped = $processed
            ->groupBy(fn($e) => $e->resource_type . '|||' . $e->simrs_code)
            ->map(function ($entries) {
                $first    = $entries->first();
                $cleanMsg = preg_replace('/^\[[^\]]+\]\s*/', '', $first->error_message ?? '');
                return (object) [
                    'resource_type'   => $first->resource_type,
                    'simrs_code'      => $first->simrs_code,
                    'error_message'   => $cleanMsg,
                    'local_id_sample' => $first->local_id,
                    'bundle_count'    => $entries->pluck('bundle_id')->unique()->count(),
                    'encounter_count' => $entries->pluck('no_rawat')->unique()->count(),
                    'last_seen'       => $entries->max('created_at'),
                    'is_mapped'       => false, // diisi setelah bulk-query
                ];
            })
            ->values()
            ->when($search, fn($c) => $c->filter(fn($g) => str_contains(strtolower($g->simrs_code), strtolower($search))))
            ->sortByDesc('bundle_count')
            ->values();

        // Bulk-query tabel mapping per resource type untuk validasi status
        $buckets = [];
        foreach ($grouped as $item) {
            [$table, $key] = $this->resolveMappingTable($item->resource_type, $item->local_id_sample ?? '');
            if ($table) {
                $bucketKey = "{$table}|{$key}";
                $buckets[$bucketKey] ??= ['table' => $table, 'key' => $key, 'codes' => []];
                $buckets[$bucketKey]['codes'][] = $item->simrs_code;
            }
        }

        $mappedSets = [];
        foreach ($buckets as $bucketKey => $bucket) {
            $mappedSets[$bucketKey] = \DB::table($bucket['table'])
                ->whereIn($bucket['key'], array_unique($bucket['codes']))
                ->pluck($bucket['key'])
                ->flip()
                ->all();
        }

        $grouped = $grouped->map(function ($item) use ($mappedSets) {
            [$table, $key] = $this->resolveMappingTable($item->resource_type, $item->local_id_sample ?? '');
            if ($table) {
                $bucketKey    = "{$table}|{$key}";
                $item->is_mapped = isset($mappedSets[$bucketKey][$item->simrs_code]);
            }
            return $item;
        })->values();

        $total = $grouped->count();
        $page = Paginator::resolveCurrentPage('mgPage');
        $items = $grouped->forPage($page, $perPage);

        return new LengthAwarePaginator($items, $total, $perPage, $page, ['path' => request()->url(), 'pageName' => 'mgPage']);
    }

    public function with(): array
    {
        $mgRaw = SatuSehatBundleLog::query()
            ->join('satu_sehat_bundles as b', 'satu_sehat_bundle_logs.bundle_id', '=', 'b.id')
            ->select(['satu_sehat_bundle_logs.resource_type', 'satu_sehat_bundle_logs.local_id', 'satu_sehat_bundle_logs.error_message', 'satu_sehat_bundle_logs.bundle_id', 'b.no_rawat', 'satu_sehat_bundle_logs.created_at'])
            ->where('satu_sehat_bundle_logs.status', 'warning')
            ->where($this->mappingFailureFilter())
            ->when($this->mgResourceType, fn($q) => $q->where('satu_sehat_bundle_logs.resource_type', $this->mgResourceType))
            ->get();

        $mappingFailures = $this->processMappingFailures($mgRaw, $this->mgSearch, $this->mgPerPage);

        return [
            'mappingFailures' => $mappingFailures,
            'mgResourceTypes' => $mgRaw->pluck('resource_type')->unique()->sort()->values(),
            'mgStats' => [
                'total_codes' => $mappingFailures->total(),
                'total_bundles' => $mgRaw->pluck('bundle_id')->unique()->count(),
                'total_encounters' => $mgRaw->pluck('no_rawat')->unique()->count(),
            ],
        ];
    }
};
?>

<div>
    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 gap-3 mb-5 sm:grid-cols-3">
        <x-organisms.stat-card title="Kode Belum Dipetakan" value="{{ number_format($mgStats['total_codes']) }}"
            icon="exclamation-triangle" color="red" />
        <x-organisms.stat-card title="Bundle Terdampak" value="{{ number_format($mgStats['total_bundles']) }}"
            icon="paper-airplane" color="amber" />
        <x-organisms.stat-card title="Encounter Terdampak" value="{{ number_format($mgStats['total_encounters']) }}"
            icon="users" color="orange" />
    </div>

    {{-- Filter --}}
    <div
        class="mb-4 flex flex-wrap items-center gap-3 bg-white dark:bg-primary-dark-800 p-4 rounded-2xl border border-zinc-200 dark:border-primary-dark-700 shadow-sm">
        <flux:select wire:model.live="mgResourceType" class="w-52">
            <flux:select.option value="">Semua Resource Type</flux:select.option>
            @foreach ($mgResourceTypes as $rt)
                <flux:select.option value="{{ $rt }}">{{ $rt }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live.debounce.300ms="mgSearch" placeholder="Cari kode SIMRS..." icon="magnifying-glass"
            clearable class="flex-1 min-w-48" />
        <flux:select wire:model.live="mgPerPage" class="w-40 ml-auto">
            <flux:select.option value="25">25 / halaman</flux:select.option>
            <flux:select.option value="50">50 / halaman</flux:select.option>
            <flux:select.option value="100">100 / halaman</flux:select.option>
        </flux:select>
    </div>

    {{-- Tabel --}}
    <x-organisms.data-panel title="Kode SIMRS Belum Termapping"
        subtitle="Dikelompokkan per kode SIMRS — diurutkan berdasarkan jumlah bundle terdampak terbanyak">
        <div class="overflow-x-auto">
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Resource Type</x-atoms.table-heading>
                    <x-atoms.table-heading>Kode SIMRS</x-atoms.table-heading>
                    <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                    <x-atoms.table-heading align="center">Status</x-atoms.table-heading>
                    <x-atoms.table-heading align="center">Bundle ↓</x-atoms.table-heading>
                    <x-atoms.table-heading align="center">Encounter</x-atoms.table-heading>
                    <x-atoms.table-heading>Terakhir</x-atoms.table-heading>
                    <x-atoms.table-heading align="center">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($mappingFailures as $item)
                    @php
                        $localIdSample = $item->local_id_sample ?? '';
                        $rt = $item->resource_type;

                        $mappingHref = match (true) {
                            in_array($rt, [
                                'MedicationRequest',
                                'MedicationDispense',
                                'MedicationStatement',
                                'Medication',
                            ])
                                => route('local.medication.medicine'),
                            $rt === 'Immunization' => route('local.medication.vaccine'),
                            $rt === 'Procedure' => route('local.clinical.procedure'),
                            $rt === 'AllergyIntolerance' => route('local.allergy.allergy'),
                            $rt === 'Condition' => route('local.clinical.icd10'),
                            str_contains($localIdSample, '-LAB_') || str_contains($rt, 'Lab') => route(
                                'local.observation.laboratory',
                            ),
                            str_contains($localIdSample, '-RAD_') || str_contains($rt, 'Rad') => route(
                                'local.observation.radiology',
                            ),
                            default => null,
                        };

                        if ($mappingHref && $item->simrs_code) {
                            $mappingHref .= '?search=' . urlencode($item->simrs_code);
                        }

                        $badgeColor = match (true) {
                            in_array($rt, [
                                'MedicationRequest',
                                'MedicationDispense',
                                'MedicationStatement',
                                'Medication',
                                'Immunization',
                            ])
                                => 'blue',
                            $rt === 'Procedure' => 'violet',
                            $rt === 'AllergyIntolerance' => 'orange',
                            $rt === 'Condition' => 'cyan',
                            str_contains($localIdSample, '-LAB_') || str_contains($rt, 'Lab') => 'lime',
                            str_contains($localIdSample, '-RAD_') || str_contains($rt, 'Rad') => 'pink',
                            default => 'zinc',
                        };
                    @endphp
                    <x-molecules.table-row :key="$item->resource_type . $item->simrs_code">
                        <x-atoms.table-cell :nowrap="true">
                            <flux:badge color="{{ $badgeColor }}" size="sm">{{ $rt }}</flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :nowrap="true">
                            <span class="font-mono text-xs font-semibold text-zinc-800 dark:text-primary-dark-100">
                                {{ $item->simrs_code }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <span class="text-xs text-zinc-500 dark:text-primary-dark-400 line-clamp-1 max-w-xs">
                                {{ $item->error_message ?: '-' }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" :nowrap="true">
                            @if ($item->is_mapped)
                                <flux:badge color="green" size="sm" icon="check-circle">Sudah Dipetakan</flux:badge>
                            @else
                                <flux:badge color="red" size="sm" icon="x-circle">Belum Dipetakan</flux:badge>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" :nowrap="true">
                            <span
                                class="inline-flex items-center gap-1 text-sm font-bold {{ $item->bundle_count >= 10 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400' }}">
                                {{ number_format($item->bundle_count) }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" :nowrap="true">
                            <span class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">
                                {{ number_format($item->encounter_count) }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :nowrap="true">
                            <span class="text-xs text-zinc-400 dark:text-primary-dark-500"
                                title="{{ $item->last_seen }}">
                                {{ \Carbon\Carbon::parse($item->last_seen)->diffForHumans() }}
                            </span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" :nowrap="true" :action="true">
                            <div class="flex items-center justify-center gap-1.5">
                                <x-atoms.button variant="ghost" size="sm" icon="document-magnifying-glass"
                                    wire:click="showBundleDetails('{{ $rt }}', '{{ $item->simrs_code }}')"
                                    tooltip="Lihat bundle terlibat" />
                                @if ($mappingHref)
                                    <x-atoms.button variant="ghost" size="sm" icon="arrow-top-right-on-square"
                                        href="{{ $mappingHref }}" target="_blank" :navigate="false"
                                        tooltip="Buka halaman mapping" />
                                @endif
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="8" class="py-16 text-center">
                            <div class="flex flex-col items-center">
                                <flux:icon name="check-badge"
                                    class="w-14 h-14 text-emerald-300 dark:text-emerald-700" />
                                <p class="mt-3 text-sm font-semibold text-zinc-600 dark:text-primary-dark-300">
                                    Semua kode sudah termapping!</p>
                                <p class="mt-1 text-xs text-zinc-400 dark:text-primary-dark-500">Tidak ada kode SIMRS
                                    yang belum dipetakan ke terminologi standar.</p>
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse

                @if ($mappingFailures->hasPages())
                    <x-slot:footer>
                        <div class="px-4 py-4">
                            {{ $mappingFailures->links() }}
                        </div>
                    </x-slot:footer>
                @endif
            </x-organisms.table>
        </div>
    </x-organisms.data-panel>

    {{-- Modal: Bundle Terlibat --}}
    <x-organisms.modal wire:model="showBundlesModal" maxWidth="3xl" title="">
        <div class="space-y-4">
            {{-- Header --}}
            <div class="flex items-start gap-3 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/40">
                    <flux:icon name="document-magnifying-glass" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-base font-bold text-zinc-900 dark:text-primary-dark-100">Bundle Terlibat</h2>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        <flux:badge color="zinc" size="sm">{{ $modalResourceType }}</flux:badge>
                        <span class="font-mono text-xs font-semibold text-amber-600 dark:text-amber-400">
                            {{ $modalSimrsCode }}
                        </span>
                        <span class="text-xs text-zinc-400 dark:text-primary-dark-500">
                            {{ count($modalBundles) }} bundle
                        </span>
                    </div>
                </div>
            </div>

            {{-- Daftar Bundle --}}
            <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-1">
                @forelse ($modalBundles as $b)
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-900/40 space-y-3">
                        {{-- No Rawat + Status --}}
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <flux:icon name="identification" class="w-4 h-4 text-zinc-400 dark:text-primary-dark-500 flex-shrink-0" />
                                <span class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">
                                    {{ $b['no_rawat'] }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <flux:badge color="{{ $b['status_color'] }}" size="sm">{{ $b['status_label'] }}</flux:badge>
                                <x-atoms.button variant="ghost" size="sm" icon="arrow-top-right-on-square"
                                    href="{{ $b['erm_url'] }}" target="_blank" :navigate="false"
                                    tooltip="Buka eRM">eRM</x-atoms.button>
                            </div>
                        </div>

                        {{-- Info Grid --}}
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs sm:grid-cols-3">
                            @if ($b['started_at'])
                                <div>
                                    <span class="text-zinc-400 dark:text-primary-dark-500">Dikirim</span>
                                    <p class="font-medium text-zinc-700 dark:text-primary-dark-200">{{ $b['started_at'] }}</p>
                                </div>
                            @endif
                            @if ($b['duration'])
                                <div>
                                    <span class="text-zinc-400 dark:text-primary-dark-500">Durasi</span>
                                    <p class="font-medium text-zinc-700 dark:text-primary-dark-200">{{ $b['duration'] }}</p>
                                </div>
                            @endif
                            @if ($b['triggered_by'])
                                <div>
                                    <span class="text-zinc-400 dark:text-primary-dark-500">Oleh</span>
                                    <p class="font-medium text-zinc-700 dark:text-primary-dark-200">{{ $b['triggered_by'] }}</p>
                                </div>
                            @endif
                        </div>

                        {{-- Error --}}
                        @if ($b['error_message'])
                            <div class="px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50">
                                <p class="text-xs text-amber-800 dark:text-amber-200">{{ $b['error_message'] }}</p>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="py-10 text-center text-sm text-zinc-400 dark:text-primary-dark-500">
                        Tidak ada data bundle ditemukan.
                    </div>
                @endforelse
            </div>

            {{-- Footer --}}
            <div class="flex justify-end pt-3 border-t border-zinc-200 dark:border-primary-dark-700">
                <x-atoms.button variant="ghost" wire:click="closeBundleDetails">Tutup</x-atoms.button>
            </div>
        </div>
    </x-organisms.modal>

</div>
