<?php

use App\Models\Bpjs\BpjsErm;
use App\Models\Simrs\Bangsal;
use App\Models\Simrs\BridgingSep;
use App\Models\Simrs\Poliklinik;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('BPJS — Detail eRM')] class extends Component {
    public string $id = '';

    public ?string $viewJsonId = null;
    public array $compareIds = [];
    public array $compareResult = [];
    public bool $showCompare = false;

    public function mount(string $id): void
    {
        $this->id = $id;
        abort_if(!BpjsErm::where('id', $id)->exists(), 404);
    }

    public function toggleCompare(string $compareId): void
    {
        if (in_array($compareId, $this->compareIds)) {
            $this->compareIds = array_values(array_filter($this->compareIds, fn($i) => $i !== $compareId));
        } elseif (count($this->compareIds) < 2) {
            $this->compareIds[] = $compareId;
        }
        $this->compareResult = [];
        $this->showCompare = false;
    }

    public function runCompare(): void
    {
        if (count($this->compareIds) !== 2) {
            return;
        }

        $records = BpjsErm::whereIn('id', $this->compareIds)->orderBy('sent_at')->get();
        if ($records->count() !== 2) {
            return;
        }

        $this->compareResult = $this->diffArrays($records->first()->bundle ?? [], $records->last()->bundle ?? []);
        $this->showCompare = true;
    }

    public function backToHistory(): void
    {
        $this->compareResult = [];
        $this->showCompare = false;
    }

    public function resetCompare(): void
    {
        $this->compareIds = [];
        $this->compareResult = [];
        $this->showCompare = false;
    }

    public function openJsonModal(string $id): void
    {
        $this->viewJsonId = $id;
        $this->dispatch('open-json-viewer');
    }

    /** Recursive diff antar dua array, menghasilkan daftar perbedaan dengan path */
    private function diffArrays(array $a, array $b, string $path = '', int $depth = 0): array
    {
        if ($depth > 6) {
            return json_encode($a) !== json_encode($b) ? [['path' => $path, 'type' => 'changed', 'old' => $a, 'new' => $b]] : [];
        }

        $diffs = [];
        $keys = array_unique([...array_keys($a), ...array_keys($b)]);

        foreach ($keys as $key) {
            $label = is_int($key) ? "[{$key}]" : (string) $key;
            $currentPath = $path !== '' ? "{$path}.{$label}" : $label;

            if (!array_key_exists($key, $a)) {
                $diffs[] = ['path' => $currentPath, 'type' => 'added', 'old' => null, 'new' => $b[$key]];
            } elseif (!array_key_exists($key, $b)) {
                $diffs[] = ['path' => $currentPath, 'type' => 'removed', 'old' => $a[$key], 'new' => null];
            } elseif (is_array($a[$key]) && is_array($b[$key])) {
                $diffs = [...$diffs, ...$this->diffArrays($a[$key], $b[$key], $currentPath, $depth + 1)];
            } elseif ($a[$key] !== $b[$key]) {
                $diffs[] = ['path' => $currentPath, 'type' => 'changed', 'old' => $a[$key], 'new' => $b[$key]];
            }
        }

        return $diffs;
    }

    public function with(): array
    {
        $record = BpjsErm::findOrFail($this->id);

        $historyRecords = BpjsErm::where('no_rawat', $record->no_rawat)
            ->where('no_sep', $record->no_sep)
            ->orderByDesc('sent_at')
            ->get(['id', 'sent_at', 'bundle_id', 'no_rawat', 'no_sep']);

        $viewJsonRecord = $this->viewJsonId ? BpjsErm::find($this->viewJsonId, ['id', 'no_rawat', 'no_sep', 'bundle_id', 'bundle', 'sent_at']) : null;

        $compareLabels = [];
        if ($this->showCompare && count($this->compareIds) === 2) {
            $compareLabels = BpjsErm::whereIn('id', $this->compareIds)
                ->orderBy('sent_at')
                ->get(['id', 'sent_at'])
                ->mapWithKeys(fn($r) => [$r->id => $r->sent_at->format('d/m/Y H:i')])
                ->toArray();
        }

        $roomNames = [];
        $sep = null;
        try {
            $roomNames = [...Poliklinik::pluck('nm_poli', 'kd_poli')->toArray(), ...Bangsal::pluck('nm_bangsal', 'kd_bangsal')->toArray()];
            if ($record->no_sep) {
                $sep = BridgingSep::find($record->no_sep);
            }
        } catch (\Exception) {
        }

        return [
            'record' => $record,
            'roomNames' => $roomNames,
            'historyRecords' => $historyRecords,
            'compareLabels' => $compareLabels,
            'viewJsonRecord' => $viewJsonRecord,
            'sep' => $sep,
            'procedures' => $record->procedures,
            'labReports' => $record->lab_reports,
            'radiologyReports' => $record->radiology_reports,
        ];
    }
}; ?>

<div x-data="{}" @open-json-viewer.window="$flux.modal('json-bundle').show()">
    @php
        $badgeColor = match ($record->encounter_type) {
            'EMER' => 'red',
            'IMP' => 'emerald',
            'AMB' => 'blue',
            default => 'zinc',
        };
        $roomName = $roomNames[$record->room_code] ?? null;
        $patient = $record->patient_resource;
        $sections = $record->composition_sections;
        $medications = $record->medications;
        $conditions = $record->conditions;

        // Helper: singkat system URI → label readable
        $sysLabel = fn(?string $s): ?string => match (true) {
            !$s || $s === '' => null,
            str_contains($s, 'snomed.info/sct') => 'SNOMED CT',
            str_contains($s, 'loinc.org') => 'LOINC',
            str_contains($s, 'icd-10') => 'ICD-10',
            str_contains($s, 'v3-ObservationInterpretation') => 'HL7 v3-OI',
            str_contains($s, 'v2-0203') => 'HL7 v2-0203',
            str_contains($s, 'prescription-category') => 'HL7 Rx-Cat',
            str_contains($s, 'condition-category') => 'HL7 Cond-Cat',
            str_contains($s, 'organization-type') => 'HL7 Org-Type',
            str_contains($s, 'unitsofmeasure.org') => 'UCUM',
            str_contains($s, 'sys-ids.kemkes.go.id/kfa') => 'KFA',
            str_contains($s, 'kemkes.go.id') => 'Kemkes',
            str_contains($s, 'terminology.hl7.org') => 'HL7',
            str_contains($s, 'hl7.org/fhir') => 'FHIR',
            default => basename(rtrim($s, '/')),
        };

        $sectionLabels = [
            'Admission diagnosis' => 'Diagnosis Masuk',
            'Admission reason' => 'Alasan Masuk',
            'Chief complaint' => 'Keluhan Utama',
            'Physical findings note' => 'Pemeriksaan Fisik',
            'Evaluation note' => 'Assessment',
            'Plan of care note' => 'Rencana & Terapi',
            'Discharge instructions' => 'Instruksi Pulang',
            'Diagnostic test results' => 'Hasil Penunjang',
            'Vital Signs' => 'Tanda Vital',
        ];
        $penunjangCount = count($labReports) + count($radiologyReports);
    @endphp

    <div class="flex items-center gap-2 mb-5 text-sm text-zinc-500 dark:text-primary-dark-400">
        <a wire:navigate href="{{ route('bpjs.erm') }}"
            class="flex items-center gap-1.5 hover:text-zinc-800 dark:hover:text-primary-dark-200 transition-colors">
            <flux:icon name="arrow-left" class="w-4 h-4" />
            eRM
        </a>
        <flux:icon name="chevron-right" class="w-3.5 h-3.5 text-zinc-300 dark:text-primary-dark-600" />
        <span
            class="text-zinc-700 dark:text-primary-dark-300 font-medium truncate max-w-xs">{{ $record->patient_name ?? $record->no_rawat }}</span>
    </div>

    {{-- Page header --}}
    <div class="flex flex-col gap-3 mb-6 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 shrink-0">
                <flux:icon name="document-check" class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-primary-dark-100">
                    {{ $record->patient_name ?? 'Detail eRM' }}
                </h1>
                <div class="flex items-center flex-wrap gap-2 mt-1">
                    <flux:badge color="{{ $badgeColor }}" size="sm">{{ $record->encounter_type_label }}
                    </flux:badge>
                    <span
                        class="font-mono text-xs text-zinc-400 dark:text-primary-dark-500">{{ $record->no_sep }}</span>
                    <span class="text-zinc-300 dark:text-primary-dark-600">·</span>
                    <span class="text-xs text-zinc-500 dark:text-primary-dark-400">
                        Dikirim {{ $record->sent_at->format('d/m/Y H:i') }}
                    </span>
                    @if ($historyRecords->count() > 1)
                        <span class="text-zinc-300 dark:text-primary-dark-600">·</span>
                        <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                            {{ $historyRecords->count() }}× dikirim
                        </span>
                    @endif
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <x-atoms.button icon="clock" @click="$flux.modal('erm-history').show(); $wire.resetCompare()">
                Riwayat Kirim
                @if ($historyRecords->count() > 1)
                    <span class="ml-1.5 px-1.5 py-0.5 rounded-full bg-white/20 text-[10px] font-bold">
                        {{ $historyRecords->count() }}
                    </span>
                @endif
            </x-atoms.button>
            <x-atoms.button icon="document-text" href="{{ route('erm.detail', ['noRawat' => $record->no_rawat]) }}"
                wire:navigate variant="ghost">
                Lihat eRM
            </x-atoms.button>
            <x-atoms.button icon="code-bracket" wire:click="openJsonModal('{{ $record->id }}')" variant="ghost">
                Lihat JSON
            </x-atoms.button>
        </div>
    </div>

    {{-- Modal JSON Bundle --}}
    @php $jr = $viewJsonRecord ?? $record; @endphp
    <x-organisms.modal wire:model="viewJsonId" title="FHIR Bundle JSON" maxWidth="7xl">
        <x-slot name="description">
            <div
                class="flex items-center mt-0.5 [&>*:not(:first-child)]:before:content-['·'] [&>*:not(:first-child)]:before:mx-1 [&>*]:text-xs [&>*]:text-zinc-500 [&>*]:dark:text-primary-dark-400 [&>*]:font-mono">
                <span>{{ $jr->no_rawat }}</span>
                <span>{{ $jr->no_sep }}</span>
                <span>{{ $jr->sent_at?->format('d/m/Y H:i:s') }}</span>
            </div>
        </x-slot>

        <span x-ref="rawJson"
            class="hidden">{{ json_encode($jr->bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</span>
        <x-atoms.code-block language="json" :searchable="true"
            maxHeight="max-h-[60vh]">{{ json_encode($jr->bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>

        <x-slot name="footer">
            <div class="flex items-center justify-between w-full">
                <x-atoms.button size="sm" icon="arrow-down-tray"
                    x-on:click="
                        const blob = new Blob([$refs.rawJson.textContent], { type: 'application/json' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'bundle-{{ $jr->no_rawat }}.json';
                        a.click();
                        URL.revokeObjectURL(url);
                    ">
                    Unduh JSON
                </x-atoms.button>
                <x-atoms.button size="sm" wire:click="$set('viewJsonId', null)" variant="ghost">
                    Tutup
                </x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Layout 2-kolom --}}
    <div class="flex flex-col gap-5 lg:flex-row lg:items-start">

        {{-- Kolom kiri: summary cards --}}
        <div class="flex flex-col gap-4 lg:w-72 xl:w-80 shrink-0">
            {{-- Bundle ID --}}
            <div
                class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500 mb-2">
                    FHIR
                    Bundle ID</p>
                <p class="font-mono text-[11px] text-zinc-500 dark:text-primary-dark-400 break-all leading-relaxed">
                    {{ $record->bundle_id }}</p>
            </div>

            {{-- Riwayat Pengiriman --}}
            <div
                class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm overflow-hidden">
                <div
                    class="flex items-center justify-between gap-2 px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/60 dark:bg-primary-dark-900/30">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                        Riwayat
                        Pengiriman</p>
                    <span
                        class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[11px] font-bold
                        {{ $historyRecords->count() > 1 ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400' : 'bg-zinc-100 dark:bg-primary-dark-700 text-zinc-500 dark:text-primary-dark-400' }}">
                        {{ $historyRecords->count() }}
                    </span>
                </div>
                <div class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                    @foreach ($historyRecords->take(3) as $hrec)
                        <div
                            class="flex items-center gap-2 px-4 py-2.5 {{ $hrec->id === $id ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-xs font-medium {{ $hrec->id === $id ? 'text-blue-700 dark:text-blue-400' : 'text-zinc-700 dark:text-primary-dark-300' }}">
                                    {{ $hrec->sent_at->format('d/m/Y H:i') }}
                                    @if ($hrec->id === $id)
                                        <span class="ml-1 text-[10px] font-semibold text-blue-500">(ini)</span>
                                    @endif
                                </p>
                                <p class="font-mono text-[10px] text-zinc-400 dark:text-primary-dark-500 truncate">
                                    {{ $hrec->bundle_id ?? '—' }}</p>
                            </div>
                            <x-atoms.button wire:click="openJsonModal('{{ $hrec->id }}')"
                                tooltip="Lihat JSON bundle" icon="code-bracket" variant="ghost" size="sm">
                            </x-atoms.button>
                            @if ($hrec->id !== $id)
                                <a wire:navigate href="{{ route('bpjs.erm-detail', $hrec->id) }}"
                                    class="shrink-0 text-zinc-400 dark:text-primary-dark-500 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                    <flux:icon name="arrow-top-right-on-square" class="w-3.5 h-3.5" />
                                </a>
                            @endif
                        </div>
                    @endforeach
                    @if ($historyRecords->count() > 3)
                        <div class="px-4 py-2.5">
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                                +{{ $historyRecords->count() - 3 }}
                                pengiriman lainnya</p>
                        </div>
                    @endif
                </div>
                @if ($historyRecords->count() > 1)
                    <div class="px-4 py-3 border-t border-zinc-100 dark:border-primary-dark-700/60">
                        <x-atoms.button class="w-full" icon="arrows-right-left"
                            @click="$flux.modal('erm-history').show(); $wire.resetCompare()">
                            Bandingkan Pengiriman
                        </x-atoms.button>
                    </div>
                @endif
            </div>

            {{-- Data Pasien --}}
            <div
                class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm overflow-hidden">
                <div
                    class="px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/60 dark:bg-primary-dark-900/30">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                        Data
                        Pasien</p>
                </div>
                <dl class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                    <div class="px-4 py-3">
                        <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">Nama</dt>
                        <dd class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                            {{ $record->patient_name ?? '-' }}</dd>
                    </div>
                    <div class="px-4 py-3">
                        <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">NIK</dt>
                        <dd class="font-mono text-sm text-zinc-700 dark:text-primary-dark-300">
                            {{ $record->patient_nik ?? '-' }}</dd>
                    </div>
                    <div class="px-4 py-3">
                        <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">No Peserta BPJS</dt>
                        <dd class="font-mono text-sm text-zinc-700 dark:text-primary-dark-300">
                            {{ $record->patient_bpjs ?? '-' }}</dd>
                    </div>
                    @if ($patient)
                        <div class="px-4 py-3">
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">Jenis Kelamin</dt>
                            <dd class="text-sm text-zinc-700 dark:text-primary-dark-300">
                                {{ match ($patient['gender'] ?? '') {'male' => 'Laki-laki','female' => 'Perempuan',default => '-'} }}
                            </dd>
                        </div>
                        <div class="px-4 py-3">
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">Tanggal Lahir</dt>
                            <dd class="text-sm text-zinc-700 dark:text-primary-dark-300">
                                {{ isset($patient['birthDate']) ? \Carbon\Carbon::parse($patient['birthDate'])->format('d/m/Y') : '-' }}
                            </dd>
                        </div>
                        @php $mobile = collect($patient['telecom'] ?? [])->firstWhere('use', 'mobile'); @endphp
                        @if (!empty($mobile['value']))
                            <div class="px-4 py-3">
                                <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">No. HP</dt>
                                <dd class="font-mono text-sm text-zinc-700 dark:text-primary-dark-300">
                                    {{ $mobile['value'] }}
                                </dd>
                            </div>
                        @endif
                        @if (!empty($patient['address'][0]['text']))
                            <div class="px-4 py-3">
                                <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">Alamat</dt>
                                <dd class="text-sm text-zinc-700 dark:text-primary-dark-300 leading-snug">
                                    {{ $patient['address'][0]['text'] }}
                                    @php
                                        $city =
                                            ($patient['address'][0]['city'] ?? '') !== '-'
                                                ? $patient['address'][0]['city'] ?? ''
                                                : '';
                                        $state =
                                            ($patient['address'][0]['state'] ?? '') !== '-'
                                                ? $patient['address'][0]['state'] ?? ''
                                                : '';
                                    @endphp
                                    @if ($city)
                                        , {{ $city }}
                                    @endif
                                    @if ($state)
                                        , {{ $state }}
                                    @endif
                                </dd>
                            </div>
                        @endif
                    @endif
                    @if ($record->organization_name)
                        <div class="px-4 py-3">
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">Unit Pelayanan</dt>
                            <dd class="text-sm text-zinc-700 dark:text-primary-dark-300">
                                {{ $record->organization_name }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Info Kunjungan --}}
            <div
                class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm overflow-hidden">
                <div
                    class="px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/60 dark:bg-primary-dark-900/30">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500">
                        Info
                        Kunjungan</p>
                </div>
                <dl class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                    <div class="px-4 py-3">
                        <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">No SEP</dt>
                        <dd class="font-mono text-sm font-bold text-zinc-800 dark:text-primary-dark-100">
                            {{ $record->no_sep }}
                        </dd>
                    </div>
                    <div class="px-4 py-3">
                        <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">No Rawat</dt>
                        <dd class="font-mono text-sm text-zinc-700 dark:text-primary-dark-300">{{ $record->no_rawat }}
                        </dd>
                    </div>
                    <div class="px-4 py-3">
                        <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">Ruang / Unit</dt>
                        <dd class="text-sm text-zinc-700 dark:text-primary-dark-300">
                            {{ $roomName ?? $record->room_code }}
                            @if ($roomName)
                                <span class="font-mono text-xs text-zinc-400 ml-1">({{ $record->room_code }})</span>
                            @endif
                        </dd>
                    </div>
                    <div class="px-4 py-3">
                        <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">DPJP</dt>
                        <dd class="text-sm text-zinc-700 dark:text-primary-dark-300">
                            {{ $record->practitioner_name ?? '-' }}
                        </dd>
                    </div>
                    <div class="px-4 py-3">
                        <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">Bulan / Tahun</dt>
                        <dd class="text-sm text-zinc-700 dark:text-primary-dark-300">
                            {{ \Carbon\Carbon::create()->month($record->bulan)->translatedFormat('F') }}
                            {{ $record->tahun }}
                        </dd>
                    </div>
                    <div class="px-4 py-3">
                        <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">Tanggal Masuk</dt>
                        <dd class="text-sm text-zinc-700 dark:text-primary-dark-300">
                            {{ $record->period_start?->format('d/m/Y H:i') ?? '-' }}</dd>
                    </div>
                    <div class="px-4 py-3">
                        <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">Tanggal Keluar</dt>
                        <dd class="text-sm text-zinc-700 dark:text-primary-dark-300">
                            {{ $record->period_end?->format('d/m/Y H:i') ?? '-' }}</dd>
                    </div>
                    @if ($record->discharge_disposition)
                        <div class="px-4 py-3">
                            <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">Kondisi Pulang</dt>
                            <dd class="text-sm text-zinc-700 dark:text-primary-dark-300">
                                {{ $record->discharge_disposition }}
                            </dd>
                        </div>
                    @endif
                    <div class="px-4 py-3">
                        <dt class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-0.5">Dikirim</dt>
                        <dd class="text-sm text-zinc-700 dark:text-primary-dark-300">
                            {{ $record->sent_at->format('d/m/Y H:i:s') }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Kolom kanan: konten tabbed --}}
        <div class="flex-1 min-w-0" x-data="{ ermTab: 'soap' }">

            {{-- Diagnosa utama pinned di atas --}}
            @if ($record->diagnosa_utama)
                <div
                    class="flex items-start gap-3 p-4 mb-4 rounded-2xl bg-blue-50/70 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/40">
                    <span
                        class="inline-block font-mono text-sm font-bold px-2 py-0.5 rounded-lg
                        bg-blue-100 dark:bg-blue-800/60 text-blue-700 dark:text-blue-300
                        ring-1 ring-blue-200 dark:ring-blue-700 shrink-0 mt-0.5">
                        {{ $record->diagnosa_utama }}
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                            {{ $record->diagnosa_utama_display }}</p>
                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-0.5">Diagnosis Utama</p>
                    </div>
                </div>
            @endif

            {{-- Tab navigation --}}
            @php
                $ermTabs = [
                    ['soap', 'Catatan Klinis'],
                    ['diagnosa', 'Diagnosa (' . count($conditions) . ')'],
                    ['tindakan', 'Tindakan (' . count($procedures) . ')'],
                    ['penunjang', "Penunjang ({$penunjangCount})"],
                    ['obat', 'Obat (' . count($medications) . ')'],
                    ['sep', 'SEP'],
                ];
            @endphp
            <x-molecules.tabs class="mb-4 flex-wrap">
                @foreach ($ermTabs as [$key, $label])
                    <x-atoms.tab-item :active="true" x-show="ermTab === '{{ $key }}'"
                        @click="ermTab = '{{ $key }}'">
                        {{ $label }}
                    </x-atoms.tab-item>
                    <x-atoms.tab-item :active="false" x-show="ermTab !== '{{ $key }}'"
                        @click="ermTab = '{{ $key }}'">
                        {{ $label }}
                    </x-atoms.tab-item>
                @endforeach
            </x-molecules.tabs>

            {{-- Tab: Catatan Klinis (SOAP) --}}
            <div x-show="ermTab === 'soap'" class="space-y-3">
                @forelse ($sections as $section)
                    @php
                        $secLabel = $sectionLabels[$section['title']] ?? $section['title'];
                        $secTitle = $section['title'];
                        $secText = $section['text'] ?? null;
                        $textStatus = $section['textStatus'] ?? null;
                        $secCode = $section['code'] ?? null;
                        $secSys = $section['system'] ?? null;
                        $secDisp = $section['display'] ?? null;
                        $entries = $section['entries'] ?? [];
                    @endphp
                    @if ($secText || count($entries))
                        <div
                            class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm overflow-hidden">

                            {{-- Header --}}
                            <div
                                class="px-5 py-3 border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/60 dark:bg-primary-dark-900/30">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p
                                            class="text-xs font-bold uppercase tracking-wide text-zinc-600 dark:text-primary-dark-300">
                                            {{ $secLabel }}</p>
                                        @if ($secLabel !== $secTitle)
                                            <p
                                                class="text-[10px] text-zinc-400 dark:text-primary-dark-500 mt-0.5 italic">
                                                {{ $secTitle }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0 flex-wrap justify-end">
                                        @if ($textStatus)
                                            <flux:badge size="sm" color="zinc">{{ $textStatus }}
                                            </flux:badge>
                                        @endif
                                        @if (count($entries))
                                            <span
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-zinc-100 dark:bg-primary-dark-700 text-zinc-500 dark:text-primary-dark-400 ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                                                <flux:icon name="link" class="size-3" />{{ count($entries) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Coding row --}}
                                @if ($secSys || $secCode || $secDisp)
                                    <div class="flex items-center gap-1.5 flex-wrap mt-2">
                                        <span
                                            class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">code</span>
                                        @if ($secSys)
                                            <span
                                                class="font-mono text-[10px] px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-400 ring-1 ring-zinc-200 dark:ring-primary-dark-600"
                                                title="{{ $secSys }}">{{ $sysLabel($secSys) ?? $secSys }}</span>
                                        @endif
                                        @if ($secCode)
                                            <span
                                                class="font-mono text-[10px] font-bold text-zinc-600 dark:text-primary-dark-300">{{ $secCode }}</span>
                                        @endif
                                        @if ($secDisp)
                                            <span
                                                class="text-[10px] text-zinc-400 dark:text-primary-dark-500 italic">{{ $secDisp }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Text content --}}
                            @if ($secText)
                                <div
                                    class="px-5 py-4 text-sm text-zinc-700 dark:text-primary-dark-300 leading-relaxed">
                                    {!! strip_tags($secText, '<div><br><b><strong><em><p>') !!}
                                </div>
                            @endif

                            {{-- Entry references --}}
                            @if (count($entries))
                                @php
                                    $resolvedEntries = $record->resolveReferences($entries);
                                @endphp
                                <div class="px-5 pb-3 @if ($secText) border-t border-zinc-100 dark:border-primary-dark-700/40 pt-3 @endif">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500 mb-1.5">entry ({{ count($entries) }})</p>
                                    
                                    <div class="space-y-2">
                                        @foreach ($entries as $ref)
                                            @php $res = $resolvedEntries[$ref] ?? null; @endphp
                                            @if ($res)
                                                @if (($res['resourceType'] ?? '') === 'DiagnosticReport')
                                                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-100 dark:border-primary-dark-700/50">
                                                        <div class="flex items-center justify-between gap-2 mb-2">
                                                            <div class="flex items-center gap-1.5 min-w-0">
                                                                <flux:icon name="beaker" class="size-3.5 text-zinc-400 shrink-0" />
                                                                <span class="text-xs font-semibold text-zinc-700 dark:text-primary-dark-200 truncate">
                                                                    {{ $res['code']['text'] ?? ($res['code']['coding'][0]['display'] ?? 'Diagnostic Report') }}
                                                                </span>
                                                            </div>
                                                            <span class="text-[9px] text-zinc-400 font-mono shrink-0">{{ $ref }}</span>
                                                        </div>
                                                        @if (!empty($res['result']))
                                                            <div class="space-y-1.5">
                                                                @foreach ($res['result'] as $obs)
                                                                    @if(is_array($obs))
                                                                        <div class="flex items-start justify-between gap-2 text-[11px]">
                                                                            <span class="text-zinc-600 dark:text-primary-dark-300">
                                                                                {{ $obs['code']['text'] ?? ($obs['code']['coding'][0]['display'] ?? 'Observation') }}
                                                                            </span>
                                                                            <div class="flex items-center gap-1 shrink-0 font-medium text-zinc-800 dark:text-primary-dark-100">
                                                                                @php
                                                                                    $val = $obs['valueQuantity']['value'] ?? ($obs['valueString'] ?? ($obs['valueCodeableConcept']['text'] ?? ''));
                                                                                    $unit = $obs['valueQuantity']['unit'] ?? '';
                                                                                @endphp
                                                                                @if ($val !== '')
                                                                                    <span>{{ $val }}</span>
                                                                                    @if ($unit)
                                                                                        <span class="text-zinc-400 font-normal">{{ $unit }}</span>
                                                                                    @endif
                                                                                @else
                                                                                    <span class="text-zinc-400">—</span>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @elseif (($res['resourceType'] ?? '') === 'Observation')
                                                    <div class="p-2.5 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-100 dark:border-primary-dark-700/50 flex items-center justify-between gap-2">
                                                        <div class="flex items-center gap-1.5 min-w-0">
                                                            <flux:icon name="eye" class="size-3.5 text-zinc-400 shrink-0" />
                                                            <span class="text-xs font-semibold text-zinc-700 dark:text-primary-dark-200 truncate">
                                                                {{ $res['code']['text'] ?? ($res['code']['coding'][0]['display'] ?? 'Observation') }}
                                                            </span>
                                                        </div>
                                                        <div class="flex items-center gap-1 shrink-0 text-xs font-medium text-zinc-800 dark:text-primary-dark-100">
                                                            @php
                                                                $val = $res['valueQuantity']['value'] ?? ($res['valueString'] ?? ($res['valueCodeableConcept']['text'] ?? ''));
                                                                $unit = $res['valueQuantity']['unit'] ?? '';
                                                            @endphp
                                                            @if ($val !== '')
                                                                <span>{{ $val }}</span>
                                                                @if ($unit)
                                                                    <span class="text-zinc-400 font-normal">{{ $unit }}</span>
                                                                @endif
                                                            @else
                                                                <span class="text-zinc-400">—</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @else
                                                    {{-- Fallback --}}
                                                    <div class="flex items-center justify-between gap-2 p-1.5 rounded bg-zinc-50 dark:bg-primary-dark-900/40 border border-zinc-100 dark:border-primary-dark-700/50">
                                                        <div class="flex items-center gap-1.5">
                                                            <flux:icon name="link" class="size-3 text-zinc-400" />
                                                            <span class="text-[10px] font-semibold text-zinc-600 dark:text-primary-dark-300">{{ $res['resourceType'] ?? 'Resource' }}</span>
                                                        </div>
                                                        <span class="font-mono text-[9px] text-zinc-400 dark:text-primary-dark-500 truncate" title="{{ $ref }}">{{ $ref }}</span>
                                                    </div>
                                                @endif
                                            @else
                                                <div class="flex items-center gap-1.5 p-1.5">
                                                    <flux:icon name="link" class="size-3 text-zinc-300" />
                                                    <span class="font-mono text-[10px] text-zinc-400 dark:text-primary-dark-500 truncate" title="{{ $ref }}">{{ $ref }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                @empty
                    <div class="flex flex-col items-center gap-3 py-20">
                        <div
                            class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                            <flux:icon name="document-text"
                                class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                        </div>
                        <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Tidak ada catatan klinis</p>
                    </div>
                @endforelse
            </div>

            {{-- Tab: Diagnosa --}}
            <div x-show="ermTab === 'diagnosa'" class="space-y-3">
                @forelse ($conditions as $cond)
                    @php
                        $cCodings = $cond['code']['coding'] ?? [];
                        $cDisplay = $cond['code']['text'] ?? ($cCodings[0]['display'] ?? null);
                        $catCoding = $cond['category'][0]['coding'][0] ?? null;
                        $clinSt = is_string($cond['clinicalStatus'] ?? null)
                            ? $cond['clinicalStatus']
                            : $cond['clinicalStatus']['coding'][0]['code'] ?? null;
                        $verifSt = is_string($cond['verificationStatus'] ?? null)
                            ? $cond['verificationStatus']
                            : $cond['verificationStatus']['coding'][0]['code'] ?? null;
                        $onsetDt = !empty($cond['onsetDateTime'])
                            ? \Carbon\Carbon::parse($cond['onsetDateTime'])->format('d/m/Y H:i')
                            : null;
                        $condNote = $cond['note'][0]['text'] ?? null;
                    @endphp
                    @if (count($cCodings) > 0 || $cDisplay)
                        <div
                            class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm overflow-hidden">
                            {{-- Header --}}
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/60 dark:bg-primary-dark-900/30 flex-wrap">
                                @foreach($cCodings as $coding)
                                    @if (!empty($coding['code']))
                                        <div class="flex items-center gap-0.5 shrink-0">
                                            <span class="font-mono text-[11px] font-bold px-1.5 py-0.5 rounded-l bg-zinc-100 dark:bg-primary-dark-700 text-zinc-700 dark:text-primary-dark-200 ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                                                {{ $coding['code'] }}
                                            </span>
                                            @if (!empty($coding['system']))
                                                <span class="font-mono text-[10px] px-1.5 py-0.5 rounded-r bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 ring-1 ring-blue-200 dark:ring-blue-800" title="{{ $coding['system'] }}">
                                                    {{ $sysLabel($coding['system']) ?? $coding['system'] }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                                @if ($catCoding && !empty($catCoding['code']))
                                    <span
                                        class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-500 ring-1 ring-zinc-200 dark:ring-primary-dark-600 shrink-0"
                                        title="{{ $catCoding['system'] ?? '' }}">{{ $catCoding['display'] ?? $catCoding['code'] }}</span>
                                @endif
                                <div class="flex items-center gap-1.5 ml-auto">
                                    @if ($clinSt)
                                        <flux:badge size="sm"
                                            color="{{ $clinSt === 'active' ? 'green' : 'zinc' }}">{{ $clinSt }}
                                        </flux:badge>
                                    @endif
                                    @if ($verifSt)
                                        <flux:badge size="sm"
                                            color="{{ $verifSt === 'confirmed' ? 'blue' : 'zinc' }}">
                                            {{ $verifSt }}</flux:badge>
                                    @endif
                                </div>
                            </div>
                            {{-- Body --}}
                            <div class="px-4 py-3 space-y-2">
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $cDisplay }}</p>
                                
                                {{-- Codings list --}}
                                @if (count($cCodings) > 0)
                                    <div class="space-y-1.5">
                                        @foreach($cCodings as $coding)
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="text-[10px] text-zinc-400 dark:text-primary-dark-500 font-semibold uppercase tracking-wide">code</span>
                                                @if (!empty($coding['system']))
                                                    <span class="font-mono text-[10px] px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-400 ring-1 ring-zinc-200 dark:ring-primary-dark-600" title="{{ $coding['system'] }}">{{ $sysLabel($coding['system']) ?? $coding['system'] }}</span>
                                                @endif
                                                @if (!empty($coding['code']))
                                                    <span class="font-mono text-[10px] font-bold text-zinc-600 dark:text-primary-dark-300">{{ $coding['code'] }}</span>
                                                @endif
                                                @if (!empty($coding['display']) && $coding['display'] !== $cDisplay)
                                                    <span class="text-[10px] text-zinc-400 dark:text-primary-dark-500 italic">{{ $coding['display'] }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                {{-- Category coding --}}
                                @if ($catCoding)
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span
                                            class="text-[10px] text-zinc-400 dark:text-primary-dark-500 font-semibold uppercase tracking-wide">category</span>
                                        @if (!empty($catCoding['system']))
                                            <span
                                                class="font-mono text-[10px] px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-400 ring-1 ring-zinc-200 dark:ring-primary-dark-600"
                                                title="{{ $catCoding['system'] }}">{{ $sysLabel($catCoding['system']) ?? basename($catCoding['system']) }}</span>
                                        @endif
                                        @if (!empty($catCoding['code']))
                                            <span
                                                class="font-mono text-[10px] font-bold text-zinc-600 dark:text-primary-dark-300">{{ $catCoding['code'] }}</span>
                                        @endif
                                        @if (!empty($catCoding['display']))
                                            <span
                                                class="text-[10px] text-zinc-400 dark:text-primary-dark-500 italic">{{ $catCoding['display'] }}</span>
                                        @endif
                                    </div>
                                @endif
                                @if ($onsetDt)
                                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                        <span
                                            class="font-semibold uppercase tracking-wide text-[10px] text-zinc-400 mr-1">onset</span>{{ $onsetDt }}
                                    </p>
                                @endif
                                @if ($condNote)
                                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug">
                                        {{ $condNote }}</p>
                                @endif
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="flex flex-col items-center gap-3 py-20">
                        <div
                            class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                            <flux:icon name="clipboard-document-list"
                                class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                        </div>
                        <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Tidak ada data diagnosa tambahan
                        </p>
                    </div>
                @endforelse
            </div>

            {{-- Tab: Tindakan --}}
            <div x-show="ermTab === 'tindakan'" class="space-y-3">
                @forelse ($procedures as $proc)
                    @php
                        $pCodings = $proc['code']['coding'] ?? [];
                        $pDisplay = $proc['code']['text'] ?? ($pCodings[0]['display'] ?? '-');
                        $pStatus = $proc['status'] ?? null;
                        $pNote = $proc['note'][0]['text'] ?? null;
                        $pActor = $proc['performer'][0]['actor']['display'] ?? null;
                        $pRoleCod = $proc['performer'][0]['role']['coding'][0] ?? null;
                        $pReasonTxt = $proc['reasonCode'][0]['text'] ?? null;
                        $pBodySite = $proc['bodySite'][0]['coding'][0] ?? null;
                        $pBsText = $proc['bodySite'][0]['text'] ?? null;
                        $pStart = !empty($proc['performedPeriod']['start'])
                            ? \Carbon\Carbon::parse($proc['performedPeriod']['start'])->format('d/m/Y H:i')
                            : null;
                        $pEnd = !empty($proc['performedPeriod']['end'])
                            ? \Carbon\Carbon::parse($proc['performedPeriod']['end'])->format('d/m/Y H:i')
                            : null;
                    @endphp
                    <div
                        class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm overflow-hidden">
                        {{-- Header --}}
                        <div
                            class="flex items-center gap-2 px-4 py-2.5 border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/60 dark:bg-primary-dark-900/30 flex-wrap">
                            @foreach($pCodings as $coding)
                                @if (!empty($coding['code']))
                                    <div class="flex items-center gap-0.5 shrink-0">
                                        <span class="font-mono text-[11px] font-bold px-1.5 py-0.5 rounded-l bg-zinc-100 dark:bg-primary-dark-700 text-zinc-700 dark:text-primary-dark-200 ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                                            {{ $coding['code'] }}
                                        </span>
                                        @if (!empty($coding['system']))
                                            <span class="font-mono text-[10px] px-1.5 py-0.5 rounded-r bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-200 dark:ring-emerald-800" title="{{ $coding['system'] }}">
                                                {{ $sysLabel($coding['system']) ?? $coding['system'] }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                            @if ($pNote)
                                <flux:badge size="sm" color="zinc">{{ $pNote }}</flux:badge>
                            @endif
                            @if ($pStatus)
                                <flux:badge size="sm" color="{{ $pStatus === 'completed' ? 'green' : 'zinc' }}"
                                    class="ml-auto">{{ $pStatus }}</flux:badge>
                            @endif
                        </div>
                        {{-- Body --}}
                        <div class="px-4 py-3 space-y-2">
                            <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                {{ $pDisplay }}</p>

                            {{-- Codings list --}}
                            @if (count($pCodings) > 0)
                                <div class="space-y-1.5">
                                    @foreach($pCodings as $coding)
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">code</span>
                                            @if (!empty($coding['system']))
                                                <span class="font-mono text-[10px] px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-400 ring-1 ring-zinc-200 dark:ring-primary-dark-600" title="{{ $coding['system'] }}">{{ $sysLabel($coding['system']) ?? $coding['system'] }}</span>
                                            @endif
                                            @if (!empty($coding['code']))
                                                <span class="font-mono text-[10px] font-bold text-zinc-600 dark:text-primary-dark-300">{{ $coding['code'] }}</span>
                                            @endif
                                            @if (!empty($coding['display']) && $coding['display'] !== $pDisplay)
                                                <span class="text-[10px] text-zinc-400 italic">{{ $coding['display'] }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Performer --}}
                            @if ($pActor)
                                <div class="flex items-start gap-1.5 flex-wrap">
                                    <span
                                        class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">performer</span>
                                    <span
                                        class="text-xs text-zinc-700 dark:text-primary-dark-300">{{ $pActor }}</span>
                                    @if ($pRoleCod && !empty($pRoleCod['code']))
                                        <div class="flex items-center gap-1">
                                            @if (!empty($pRoleCod['system']))
                                                <span
                                                    class="font-mono text-[10px] px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-400 ring-1 ring-zinc-200 dark:ring-primary-dark-600"
                                                    title="{{ $pRoleCod['system'] }}">{{ $sysLabel($pRoleCod['system']) ?? basename($pRoleCod['system']) }}</span>
                                            @endif
                                            <span
                                                class="font-mono text-[10px] font-bold text-zinc-500">{{ $pRoleCod['code'] }}</span>
                                            @if (!empty($pRoleCod['display']))
                                                <span
                                                    class="text-[10px] text-zinc-400 italic">{{ $pRoleCod['display'] }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Reason --}}
                            @if ($pReasonTxt && $pReasonTxt !== $pDisplay)
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">reason</span>
                                    <span
                                        class="text-xs text-zinc-600 dark:text-primary-dark-400">{{ $pReasonTxt }}</span>
                                </div>
                            @endif

                            {{-- Body site --}}
                            @if ($pBodySite && !empty($pBodySite['code']))
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span
                                        class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">bodySite</span>
                                    @if (!empty($pBodySite['system']))
                                        <span
                                            class="font-mono text-[10px] px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-400 ring-1 ring-zinc-200 dark:ring-primary-dark-600"
                                            title="{{ $pBodySite['system'] }}">{{ $sysLabel($pBodySite['system']) ?? basename($pBodySite['system']) }}</span>
                                    @endif
                                    <span
                                        class="font-mono text-[10px] font-bold text-zinc-600 dark:text-primary-dark-300">{{ $pBodySite['code'] }}</span>
                                    @if (!empty($pBodySite['display']))
                                        <span
                                            class="text-[10px] text-zinc-400 italic">{{ $pBodySite['display'] }}</span>
                                    @endif
                                    @if ($pBsText && $pBsText !== ($pBodySite['display'] ?? ''))
                                        <span
                                            class="text-xs text-zinc-500 dark:text-primary-dark-400">({{ $pBsText }})</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Period --}}
                            @if ($pStart)
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">period</span>
                                    <span class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">
                                        {{ $pStart }}@if ($pEnd && $pEnd !== $pStart)
                                            &ndash; {{ $pEnd }}
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center gap-3 py-20">
                        <div
                            class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                            <flux:icon name="scissors" class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                        </div>
                        <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Tidak ada data tindakan</p>
                    </div>
                @endforelse
            </div>

            {{-- Tab: Penunjang Medis --}}
            <div x-show="ermTab === 'penunjang'" x-data="{ penTab: 'lab' }" class="space-y-4">

                {{-- Sub-tab nav --}}
                <div class="flex gap-1 p-1 rounded-xl bg-zinc-100 dark:bg-primary-dark-800/60 w-fit">
                    <button @click="penTab = 'lab'"
                        :class="penTab === 'lab'
                            ?
                            'bg-white dark:bg-primary-dark-700 text-zinc-800 dark:text-primary-dark-100 shadow-sm' :
                            'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200'"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all">
                        <flux:icon name="beaker" class="size-3.5" />
                        Laboratorium
                        <span class="font-bold tabular-nums">({{ count($labReports) }})</span>
                    </button>
                    <button @click="penTab = 'rad'"
                        :class="penTab === 'rad'
                            ?
                            'bg-white dark:bg-primary-dark-700 text-zinc-800 dark:text-primary-dark-100 shadow-sm' :
                            'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200'"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all">
                        <flux:icon name="photo" class="size-3.5" />
                        Radiologi
                        <span class="font-bold tabular-nums">({{ count($radiologyReports) }})</span>
                    </button>
                </div>

                {{-- Sub-tab: Lab --}}
                <div x-show="penTab === 'lab'" class="space-y-5">
                    @forelse ($labReports as $lab)
                        @php
                            $specimen = $lab['specimen'][0] ?? null;
                            $specId = $specimen['id'] ?? null;
                            $specStatus = $specimen['status'] ?? null;
                            $specSubject = $specimen['subject']['display'] ?? null;
                            $specIdentSys = $specimen['identifier'][0]['system'] ?? null;
                            $specIdentVal = $specimen['identifier'][0]['value'] ?? null;
                            $specIdentAsgn = $specimen['identifier'][0]['assigner']['reference'] ?? null;
                            $specTypeCoding = $specimen['type']['coding'][0] ?? null;
                            $specTypeText = $specimen['type']['text'] ?? null;
                            $collectedAt = !empty($specimen['collection']['collectedDateTime'])
                                ? \Carbon\Carbon::parse($specimen['collection']['collectedDateTime'])->format(
                                    'd/m/Y H:i',
                                )
                                : null;
                            $receivedAt = !empty($specimen['receivedTime'])
                                ? \Carbon\Carbon::parse($specimen['receivedTime'])->format('d/m/Y H:i')
                                : null;
                            $transportedAt = null;
                            foreach ($specimen['extension'] ?? [] as $ext) {
                                if (
                                    str_contains($ext['url'] ?? '', 'TransportedTime') &&
                                    !empty($ext['valueDateTime'])
                                ) {
                                    $transportedAt = \Carbon\Carbon::parse($ext['valueDateTime'])->format('d/m/Y H:i');
                                }
                            }
                            $performer = $lab['performer'][0]['display'] ?? null;
                            $labStatus = $lab['status'] ?? null;
                            $observations = $lab['result'] ?? [];
                            $issuedAt = !empty($observations[0]['issued'])
                                ? \Carbon\Carbon::parse($observations[0]['issued'])->format('d/m/Y H:i')
                                : null;
                            $abnormalCount = collect($observations)
                                ->filter(
                                    fn($o) => in_array($o['interpretation']['coding']['code'] ?? '', [
                                        'H',
                                        'HH',
                                        'L',
                                        'LL',
                                    ]),
                                )
                                ->count();
                        @endphp

                        <div
                            class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm overflow-hidden">

                            {{-- ── Header DiagnosticReport ── --}}
                            <div
                                class="flex items-center justify-between gap-3 px-4 py-3 bg-blue-50/60 dark:bg-blue-900/10 border-b border-blue-100 dark:border-blue-900/30">
                                <div class="flex items-center gap-2">
                                    <flux:icon name="beaker"
                                        class="size-4 text-blue-600 dark:text-blue-400 shrink-0" />
                                    <span
                                        class="text-xs font-semibold text-blue-700 dark:text-blue-300 uppercase tracking-wide">DiagnosticReport</span>
                                    @if ($labStatus)
                                        <span
                                            class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-300">{{ $labStatus }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5">
                                    @if ($abnormalCount > 0)
                                        <span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400">
                                            <flux:icon name="exclamation-triangle" class="size-3" />
                                            {{ $abnormalCount }} abnormal
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400">
                                            <flux:icon name="check-circle" class="size-3" />Normal semua
                                        </span>
                                    @endif
                                    <flux:badge size="sm" color="blue">LAB</flux:badge>
                                </div>
                            </div>

                            {{-- ── Specimen ── --}}
                            <div
                                class="px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/40 dark:bg-primary-dark-900/20">
                                <p
                                    class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500 mb-2">
                                    Specimen</p>

                                {{-- Identifier --}}
                                @if ($specIdentVal)
                                    <div class="flex items-start gap-2 mb-2">
                                        <span
                                            class="text-[10px] font-semibold text-zinc-400 dark:text-primary-dark-500 uppercase tracking-wide w-20 shrink-0 pt-0.5">Identifier</span>
                                        <div>
                                            <span
                                                class="font-mono text-sm font-bold text-zinc-800 dark:text-primary-dark-100">{{ $specIdentVal }}</span>
                                            @if ($specIdentSys)
                                                <p class="font-mono text-[10px] text-zinc-400 dark:text-primary-dark-500 mt-0.5"
                                                    title="{{ $specIdentSys }}">{{ $specIdentSys }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-xs">

                                    {{-- Status --}}
                                    @if ($specStatus)
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-[10px] font-semibold text-zinc-400 dark:text-primary-dark-500 uppercase tracking-wide w-20 shrink-0">Status</span>
                                            <flux:badge size="sm"
                                                color="{{ $specStatus === 'available' ? 'green' : 'zinc' }}">
                                                {{ $specStatus }}</flux:badge>
                                        </div>
                                    @endif

                                    {{-- Subject --}}
                                    @if ($specSubject)
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-[10px] font-semibold text-zinc-400 dark:text-primary-dark-500 uppercase tracking-wide w-20 shrink-0">Pasien</span>
                                            <span
                                                class="text-zinc-700 dark:text-primary-dark-300">{{ $specSubject }}</span>
                                        </div>
                                    @endif

                                    {{-- Jenis specimen (coding) --}}
                                    @if ($specTypeCoding || $specTypeText)
                                        <div class="flex items-start gap-2 sm:col-span-2">
                                            <span
                                                class="text-[10px] font-semibold text-zinc-400 dark:text-primary-dark-500 uppercase tracking-wide w-20 shrink-0 pt-0.5">Jenis</span>
                                            <div>
                                                <span
                                                    class="font-medium text-zinc-700 dark:text-primary-dark-200">{{ $specTypeText }}</span>
                                                @if ($specTypeCoding)
                                                    <div class="flex items-center gap-1.5 mt-1">
                                                        @php $sys = $sysLabel($specTypeCoding['system'] ?? null); @endphp
                                                        @if ($sys)
                                                            <span
                                                                class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-500 dark:text-primary-dark-400 ring-1 ring-zinc-200 dark:ring-primary-dark-600"
                                                                title="{{ $specTypeCoding['system'] ?? '' }}">{{ $sys }}</span>
                                                        @endif
                                                        @if (!empty($specTypeCoding['code']))
                                                            <span
                                                                class="font-mono text-[10px] font-bold text-zinc-600 dark:text-primary-dark-300">{{ $specTypeCoding['code'] }}</span>
                                                        @endif
                                                        @if (!empty($specTypeCoding['display']))
                                                            <span
                                                                class="text-[10px] text-zinc-500 dark:text-primary-dark-400">{{ $specTypeCoding['display'] }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Waktu --}}
                                    @foreach ([['Pengambilan', $collectedAt], ['Diterima', $receivedAt], ['Transportasi', $transportedAt], ['Hasil Keluar', $issuedAt]] as [$label, $val])
                                        @if ($val)
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="text-[10px] font-semibold text-zinc-400 dark:text-primary-dark-500 uppercase tracking-wide w-20 shrink-0">{{ $label }}</span>
                                                <span
                                                    class="font-mono text-zinc-700 dark:text-primary-dark-300">{{ $val }}</span>
                                            </div>
                                        @endif
                                    @endforeach

                                    {{-- Performer --}}
                                    @if ($performer)
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-[10px] font-semibold text-zinc-400 dark:text-primary-dark-500 uppercase tracking-wide w-20 shrink-0">Unit</span>
                                            <span
                                                class="text-zinc-700 dark:text-primary-dark-300">{{ $performer }}</span>
                                        </div>
                                    @endif

                                </div>
                            </div>

                            {{-- ── Tabel Hasil Observasi ── --}}
                            @if (count($observations))
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs min-w-[640px]">
                                        <thead>
                                            <tr
                                                class="bg-zinc-50/80 dark:bg-primary-dark-900/30 border-b border-zinc-100 dark:border-primary-dark-700/60">
                                                <th
                                                    class="px-4 py-2.5 text-left font-semibold text-zinc-500 dark:text-primary-dark-400">
                                                    Parameter</th>
                                                <th
                                                    class="px-4 py-2.5 text-right font-semibold text-zinc-500 dark:text-primary-dark-400 w-32">
                                                    Hasil</th>
                                                <th
                                                    class="px-4 py-2.5 text-center font-semibold text-zinc-500 dark:text-primary-dark-400 w-24">
                                                    Interpretasi</th>
                                                <th
                                                    class="px-4 py-2.5 text-center font-semibold text-zinc-500 dark:text-primary-dark-400 w-40">
                                                    Nilai Rujukan</th>
                                                <th
                                                    class="px-4 py-2.5 text-left font-semibold text-zinc-500 dark:text-primary-dark-400 w-24">
                                                    Waktu</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-50 dark:divide-primary-dark-700/20">
                                            @foreach ($observations as $obs)
                                                @php
                                                    // Code
                                                    $codeCoding = $obs['code']['coding'] ?? null;
                                                    $codeSys = $codeCoding['system'] ?? null;
                                                    $codeCode = $codeCoding['code'] ?? null;
                                                    $codeDisp = $codeCoding['display'] ?? null;
                                                    $codeTxt = $obs['code']['text'] ?? ($codeDisp ?? '-');

                                                    // Value
                                                    $obsVal =
                                                        $obs['valueQuantity']['value'] ??
                                                        ($obs['valueString'] ??
                                                            ($obs['valueCodeableConcept']['text'] ?? null));
                                                    $obsUnit = $obs['valueQuantity']['unit'] ?? null;
                                                    $obsUnitSys = $obs['valueQuantity']['system'] ?? null;
                                                    $obsUnitCod = $obs['valueQuantity']['code'] ?? null;

                                                    // Interpretation
                                                    $intCoding = $obs['interpretation']['coding'] ?? null;
                                                    $intSys = $intCoding['system'] ?? null;
                                                    $flagCode = $intCoding['code'] ?? null;
                                                    $flagDisp = $intCoding['display'] ?? null;

                                                    // Reference range
                                                    $rangeLow = $obs['referenceRange']['low']['value'] ?? null;
                                                    $rangeLowUnit = $obs['referenceRange']['low']['unit'] ?? null;
                                                    $rangeLowSys = $obs['referenceRange']['low']['system'] ?? null;
                                                    $rangeLowCode = $obs['referenceRange']['low']['code'] ?? null;
                                                    $rangeHigh = $obs['referenceRange']['high']['value'] ?? null;
                                                    $rangeHighUnit = $obs['referenceRange']['high']['unit'] ?? null;
                                                    $rangeHighSys = $obs['referenceRange']['high']['system'] ?? null;

                                                    // Timing & misc
                                                    $obsIssued = !empty($obs['issued'])
                                                        ? \Carbon\Carbon::parse($obs['issued'])->format('d/m H:i')
                                                        : (!empty($obs['effectiveDateTime'])
                                                            ? \Carbon\Carbon::parse($obs['effectiveDateTime'])->format(
                                                                'd/m H:i',
                                                            )
                                                            : null);
                                                    $obsConc =
                                                        isset($obs['conclusion']) &&
                                                        $obs['conclusion'] !== '-' &&
                                                        $obs['conclusion'] !== ''
                                                            ? $obs['conclusion']
                                                            : null;

                                                    $isAbnormal = in_array($flagCode, ['H', 'HH', 'L', 'LL']);
                                                    $valClass = match (true) {
                                                        in_array($flagCode, ['H', 'HH'])
                                                            => 'text-red-600 dark:text-red-400',
                                                        in_array($flagCode, ['L', 'LL'])
                                                            => 'text-blue-600 dark:text-blue-400',
                                                        $flagCode === 'N' => 'text-emerald-700 dark:text-emerald-400',
                                                        default => 'text-zinc-800 dark:text-primary-dark-100',
                                                    };
                                                    $flagBg = match (true) {
                                                        in_array($flagCode, ['H', 'HH'])
                                                            => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 ring-red-200 dark:ring-red-800',
                                                        in_array($flagCode, ['L', 'LL'])
                                                            => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 ring-blue-200 dark:ring-blue-800',
                                                        $flagCode === 'N'
                                                            => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 ring-emerald-200 dark:ring-emerald-800',
                                                        default
                                                            => 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400 ring-zinc-200 dark:ring-primary-dark-600',
                                                    };
                                                @endphp
                                                <tr
                                                    class="{{ $isAbnormal ? 'bg-red-50/40 dark:bg-red-900/5' : 'hover:bg-zinc-50/50 dark:hover:bg-primary-dark-900/20' }} transition-colors align-top">

                                                    {{-- Parameter --}}
                                                    <td class="px-4 py-3">
                                                        <p
                                                            class="font-semibold text-zinc-800 dark:text-primary-dark-100">
                                                            {{ $codeTxt }}</p>
                                                        {{-- code coding: system · code · display --}}
                                                        @if ($codeSys || $codeCode || ($codeDisp && $codeDisp !== $codeTxt))
                                                            <div class="flex items-center gap-1 mt-1 flex-wrap">
                                                                @if ($codeSys && $codeSys !== '')
                                                                    <span
                                                                        class="font-mono text-[9px] px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-400 dark:text-primary-dark-500 ring-1 ring-zinc-200 dark:ring-primary-dark-600"
                                                                        title="{{ $codeSys }}">{{ $sysLabel($codeSys) ?? $codeSys }}</span>
                                                                @endif
                                                                @if ($codeCode && $codeCode !== '')
                                                                    <span
                                                                        class="font-mono text-[9px] font-bold text-zinc-500 dark:text-primary-dark-400">{{ $codeCode }}</span>
                                                                @endif
                                                                @if ($codeDisp && $codeDisp !== $codeTxt)
                                                                    <span
                                                                        class="text-[9px] text-zinc-400 dark:text-primary-dark-500 italic">{{ $codeDisp }}</span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                        @if ($obsConc)
                                                            <p
                                                                class="text-[10px] text-zinc-400 dark:text-primary-dark-500 mt-1 leading-snug">
                                                                {{ $obsConc }}</p>
                                                        @endif
                                                    </td>

                                                    {{-- Hasil --}}
                                                    <td class="px-4 py-3 text-right">
                                                        <span
                                                            class="font-mono font-bold text-sm {{ $valClass }}">
                                                            {{ $obsVal !== null && $obsVal !== '' ? $obsVal : '—' }}
                                                        </span>
                                                        @if ($obsUnit)
                                                            <span
                                                                class="text-[10px] text-zinc-400 dark:text-primary-dark-500 ml-0.5">{{ $obsUnit }}</span>
                                                        @endif
                                                        {{-- unit coding --}}
                                                        @if ($obsUnitSys || $obsUnitCod)
                                                            <div class="flex items-center justify-end gap-1 mt-0.5">
                                                                @if ($obsUnitSys)
                                                                    <span
                                                                        class="font-mono text-[9px] text-zinc-400 dark:text-primary-dark-600"
                                                                        title="{{ $obsUnitSys }}">{{ $sysLabel($obsUnitSys) }}</span>
                                                                @endif
                                                                @if ($obsUnitCod && $obsUnitCod !== $obsUnit)
                                                                    <span
                                                                        class="font-mono text-[9px] text-zinc-400 dark:text-primary-dark-600">{{ $obsUnitCod }}</span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </td>

                                                    {{-- Interpretasi --}}
                                                    <td class="px-4 py-3 text-center">
                                                        @if ($flagCode)
                                                            <span
                                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold ring-1 {{ $flagBg }}">
                                                                {{ $flagCode }}
                                                            </span>
                                                            @if ($flagDisp)
                                                                <p
                                                                    class="text-[10px] text-zinc-400 dark:text-primary-dark-500 mt-0.5">
                                                                    {{ $flagDisp }}</p>
                                                            @endif
                                                            @if ($intSys)
                                                                <p class="font-mono text-[9px] text-zinc-300 dark:text-primary-dark-600 mt-0.5"
                                                                    title="{{ $intSys }}">
                                                                    {{ $sysLabel($intSys) }}</p>
                                                            @endif
                                                        @else
                                                            <span
                                                                class="text-zinc-300 dark:text-primary-dark-600">—</span>
                                                        @endif
                                                    </td>

                                                    {{-- Nilai Rujukan --}}
                                                    <td class="px-4 py-3 text-center">
                                                        @if ($rangeLow !== null || $rangeHigh !== null)
                                                            <span
                                                                class="font-mono text-zinc-600 dark:text-primary-dark-300">
                                                                {{ $rangeLow ?? '?' }} – {{ $rangeHigh ?? '?' }}
                                                            </span>
                                                            @if ($rangeLowUnit)
                                                                <span
                                                                    class="block text-[10px] text-zinc-400 dark:text-primary-dark-500">{{ $rangeLowUnit }}</span>
                                                            @endif
                                                            @if ($rangeLowSys)
                                                                <span
                                                                    class="block font-mono text-[9px] text-zinc-300 dark:text-primary-dark-600"
                                                                    title="{{ $rangeLowSys }}">{{ $sysLabel($rangeLowSys) }}</span>
                                                            @endif
                                                            @if ($rangeLowCode && $rangeLowCode !== $rangeLowUnit)
                                                                <span
                                                                    class="block font-mono text-[9px] text-zinc-400 dark:text-primary-dark-600">{{ $rangeLowCode }}</span>
                                                            @endif
                                                        @else
                                                            <span
                                                                class="text-zinc-300 dark:text-primary-dark-600">—</span>
                                                        @endif
                                                    </td>

                                                    {{-- Waktu --}}
                                                    <td
                                                        class="px-4 py-3 text-zinc-400 dark:text-primary-dark-500 tabular-nums">
                                                        {{ $obsIssued ?? '—' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="px-4 py-4 text-xs text-zinc-400 dark:text-primary-dark-500 text-center">Tidak
                                    ada hasil pemeriksaan.</p>
                            @endif
                        </div>
                    @empty
                        <div class="flex flex-col items-center gap-3 py-20">
                            <div
                                class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="beaker" class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Tidak ada data laboratorium</p>
                        </div>
                    @endforelse
                </div>

                {{-- Sub-tab: Radiologi --}}
                <div x-show="penTab === 'rad'" class="space-y-4">
                    @forelse ($radiologyReports as $rad)
                        @php
                            $radObs = $rad['result'][0] ?? null;
                            // DiagnosticReport level
                            $radStatus = $rad['status'] ?? null;
                            $radCatCoding = is_array($rad['category']['coding'] ?? null)
                                ? $rad['category']['coding'][0] ?? null
                                : $rad['category']['coding'] ?? null;
                            $radPerformer = $rad['performer'][0]['display'] ?? null;
                            // Observation level
                            $obsCoding = $radObs['code']['coding'] ?? null;
                            // coding bisa object atau array
                            $obsCodingArr = $obsCoding[0] ?? $obsCoding;
                            $obsCodeSys = $obsCodingArr['system'] ?? null;
                            $obsCodeCode = $obsCodingArr['code'] ?? null;
                            $obsCodeDisp = $obsCodingArr['display'] ?? null;
                            $obsCodeTxt = $radObs['code']['text'] ?? ($obsCodeDisp ?? 'Radiologi');
                            $obsPerf = $radObs['performer']['display'] ?? $radPerformer;
                            $obsIssued = !empty($radObs['issued'])
                                ? \Carbon\Carbon::parse($radObs['issued'])->format('d/m/Y H:i')
                                : null;
                            $obsEffective = !empty($radObs['effectiveDateTime'])
                                ? \Carbon\Carbon::parse($radObs['effectiveDateTime'])->format('d/m/Y H:i')
                                : null;
                            $obsStatus = $radObs['status'] ?? null;
                            // Body site
                            $bsCoding = $radObs['bodySite']['coding'][0] ?? null;
                            $bsText = $radObs['bodySite']['text'] ?? null;
                            // Image
                            $radImgUrl = $radObs['image']['link']['reference'] ?? null;
                            $radImgLabel = $radObs['image']['link']['display'] ?? ($radObs['image']['comment'] ?? null);
                            // Conclusion
                            $radConc = $radObs['conclusion'] ?? null;
                        @endphp
                        <div
                            class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm overflow-hidden">

                            {{-- Header DiagnosticReport --}}
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 border-b border-zinc-100 dark:border-primary-dark-700/60 bg-violet-50/60 dark:bg-violet-900/10 flex-wrap">
                                <flux:icon name="photo" class="size-4 text-violet-500 shrink-0" />
                                <span
                                    class="text-xs font-semibold text-violet-700 dark:text-violet-300 uppercase tracking-wide">DiagnosticReport</span>
                                @if ($radStatus)
                                    <span
                                        class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-300">{{ $radStatus }}</span>
                                @endif
                                @if ($radCatCoding)
                                    <div class="flex items-center gap-1">
                                        @if (!empty($radCatCoding['system']))
                                            <span
                                                class="font-mono text-[10px] px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-400 ring-1 ring-zinc-200 dark:ring-primary-dark-600"
                                                title="{{ $radCatCoding['system'] }}">{{ $sysLabel($radCatCoding['system']) ?? basename($radCatCoding['system']) }}</span>
                                        @endif
                                        @if (!empty($radCatCoding['code']))
                                            <span
                                                class="font-mono text-[10px] font-bold text-zinc-600 dark:text-primary-dark-300">{{ $radCatCoding['code'] }}</span>
                                        @endif
                                        @if (!empty($radCatCoding['display']))
                                            <span
                                                class="text-[10px] text-zinc-400 italic">{{ $radCatCoding['display'] }}</span>
                                        @endif
                                    </div>
                                @endif
                                <flux:badge size="sm" color="purple" class="ml-auto">RAD</flux:badge>
                            </div>

                            {{-- Observation: header --}}
                            <div class="px-4 pt-3 pb-2 border-b border-zinc-100 dark:border-primary-dark-700/60">
                                <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                    {{ $obsCodeTxt }}</p>

                                {{-- code coding --}}
                                @if ($obsCodeSys || $obsCodeCode)
                                    <div class="flex items-center gap-1.5 flex-wrap mt-1">
                                        <span
                                            class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">code</span>
                                        @if ($obsCodeSys)
                                            <span
                                                class="font-mono text-[10px] px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-400 ring-1 ring-zinc-200 dark:ring-primary-dark-600"
                                                title="{{ $obsCodeSys }}">{{ $sysLabel($obsCodeSys) ?? $obsCodeSys }}</span>
                                        @endif
                                        @if ($obsCodeCode)
                                            <span
                                                class="font-mono text-[10px] font-bold text-zinc-600 dark:text-primary-dark-300">{{ $obsCodeCode }}</span>
                                        @endif
                                        @if ($obsCodeDisp && $obsCodeDisp !== $obsCodeTxt)
                                            <span class="text-[10px] text-zinc-400 italic">{{ $obsCodeDisp }}</span>
                                        @endif
                                    </div>
                                @endif

                                {{-- Meta baris --}}
                                <div class="flex items-center gap-3 flex-wrap mt-2">
                                    @if ($obsPerf)
                                        <div class="flex items-center gap-1">
                                            <span
                                                class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">performer</span>
                                            <span
                                                class="text-xs text-zinc-600 dark:text-primary-dark-400">{{ $obsPerf }}</span>
                                        </div>
                                    @endif
                                    @if ($obsEffective)
                                        <div class="flex items-center gap-1">
                                            <span
                                                class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">effective</span>
                                            <span
                                                class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">{{ $obsEffective }}</span>
                                        </div>
                                    @endif
                                    @if ($obsIssued)
                                        <div class="flex items-center gap-1">
                                            <span
                                                class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">issued</span>
                                            <span
                                                class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">{{ $obsIssued }}</span>
                                        </div>
                                    @endif
                                    @if ($obsStatus)
                                        <flux:badge size="sm" color="zinc">{{ $obsStatus }}</flux:badge>
                                    @endif
                                </div>

                                {{-- Body site coding --}}
                                @if ($bsCoding || $bsText)
                                    <div class="flex items-center gap-1.5 flex-wrap mt-2">
                                        <span
                                            class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">bodySite</span>
                                        @if ($bsCoding)
                                            @if (!empty($bsCoding['system']))
                                                <span
                                                    class="font-mono text-[10px] px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-400 ring-1 ring-zinc-200 dark:ring-primary-dark-600"
                                                    title="{{ $bsCoding['system'] }}">{{ $sysLabel($bsCoding['system']) ?? basename($bsCoding['system']) }}</span>
                                            @endif
                                            @if (!empty($bsCoding['code']))
                                                <span
                                                    class="font-mono text-[10px] font-bold text-zinc-600 dark:text-primary-dark-300">{{ $bsCoding['code'] }}</span>
                                            @endif
                                            @if (!empty($bsCoding['display']))
                                                <span
                                                    class="text-[10px] text-zinc-400 italic">{{ $bsCoding['display'] }}</span>
                                            @endif
                                        @endif
                                        @if ($bsText && $bsText !== ($bsCoding['display'] ?? ''))
                                            <span
                                                class="text-xs text-zinc-500 dark:text-primary-dark-400">({{ $bsText }})</span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Conclusion --}}
                            @if ($radConc)
                                <div class="px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700/60">
                                    <p
                                        class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500 mb-1.5">
                                        conclusion</p>
                                    <div class="text-sm text-zinc-700 dark:text-primary-dark-300 leading-relaxed">
                                        {!! strip_tags($radConc, '<div><br><b><strong><em><p>') !!}
                                    </div>
                                </div>
                            @endif

                            {{-- Image link --}}
                            @if ($radImgUrl)
                                <div class="px-4 py-2.5">
                                    <p
                                        class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-primary-dark-500 mb-1">
                                        image</p>
                                    <a href="{{ $radImgUrl }}" target="_blank"
                                        class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline break-all">
                                        <flux:icon name="arrow-top-right-on-square" class="size-3.5 shrink-0" />
                                        {{ $radImgLabel ?? $radImgUrl }}
                                    </a>
                                    <p
                                        class="font-mono text-[10px] text-zinc-400 dark:text-primary-dark-500 mt-0.5 break-all">
                                        {{ $radImgUrl }}</p>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="flex flex-col items-center gap-3 py-20">
                            <div
                                class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                <flux:icon name="photo" class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                            </div>
                            <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Tidak ada data radiologi</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Tab: SEP --}}
            <div x-show="ermTab === 'sep'">
                @if ($sep)
                    @include('pages.erm.detail-tabs._bpjs.sep', ['sep' => $sep])
                @else
                    <div
                        class="flex flex-col items-center gap-3 py-20 bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
                        <div
                            class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                            <flux:icon name="document-minus"
                                class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                        </div>
                        <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Data SEP tidak ditemukan untuk No.
                            SEP ini.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Tab: Obat --}}
            <div x-show="ermTab === 'obat'" class="space-y-3">
                @forelse ($medications as $i => $med)
                    @php
                        $mCoding = $med['medicationCodeableConcept']['coding'][0] ?? null;
                        $mCode = $mCoding['code'] ?? null;
                        $mDisplay = $med['medicationCodeableConcept']['text'] ?? ($mCoding['display'] ?? '-');
                        $mSys = $mCoding['system'] ?? null;
                        $mIntent = $med['intent'] ?? null;
                        $mStatus = $med['text']['status'] ?? null;
                        // Identifier / kategori resep
                        $idTypeCod = $med['identifier']['type']['coding'][0] ?? null;
                        $idTypeTxt = $med['identifier']['type']['text'] ?? null;
                        $idSys = $med['identifier']['system'] ?? null;
                        $idVal = $med['identifier']['value'] ?? null;
                        $idAsgn = $med['identifier']['assigner']['display'] ?? null;
                        // Requester
                        $reqAgent = $med['requester']['agent']['display'] ?? null;
                        // Dosage
                        $di = $med['dosageInstruction'][0] ?? null;
                        $qty = $di ? $di['doseQuantity']['value'] ?? null : null;
                        $qtyUnit = $di ? $di['doseQuantity']['unit'] ?? null : null;
                        $qtySys = $di ? $di['doseQuantity']['system'] ?? null : null;
                        $qtyCode = $di ? $di['doseQuantity']['code'] ?? null : null;
                        $aturan = $di ? $di['additionalInstruction'][0]['text'] ?? null : null;
                        $freq = $di ? $di['timing']['repeat']['frequency'] ?? null : null;
                        $period = $di ? $di['timing']['repeat']['period'] ?? null : null;
                        $periodUnit = $di ? $di['timing']['repeat']['periodUnit'] ?? null : null;
                        // KFA (route)
                        $kfaCoding = $di ? $di['route']['coding'][0] ?? null : null;
                        $kfaCode = $kfaCoding['code'] ?? null;
                        $kfaSys = $kfaCoding['system'] ?? null;
                        $kfaDisplay = $kfaCoding['display'] ?? ($di['route']['text'] ?? null);
                    @endphp
                    <div
                        class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm overflow-hidden">
                        {{-- Header --}}
                        <div
                            class="flex items-center gap-2 px-4 py-2.5 border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/60 dark:bg-primary-dark-900/30 flex-wrap">
                            <div
                                class="flex items-center justify-center w-5 h-5 rounded-md bg-violet-100 dark:bg-violet-900/40 shrink-0">
                                <span
                                    class="text-[11px] font-bold text-violet-600 dark:text-violet-400">{{ $i + 1 }}</span>
                            </div>
                            @if ($mCode)
                                <span
                                    class="font-mono text-[11px] font-bold px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-700 dark:text-primary-dark-200 ring-1 ring-zinc-200 dark:ring-primary-dark-600 shrink-0">{{ $mCode }}</span>
                            @endif
                            @if ($mSys)
                                <span
                                    class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-violet-50 dark:bg-violet-900/20 text-violet-600 dark:text-violet-400 ring-1 ring-violet-200 dark:ring-violet-800 shrink-0"
                                    title="{{ $mSys }}">{{ $sysLabel($mSys) ?? $mSys }}</span>
                            @endif
                            @if ($idTypeTxt)
                                <flux:badge size="sm" color="zinc">{{ $idTypeTxt }}</flux:badge>
                            @endif
                            <div class="flex items-center gap-1.5 ml-auto shrink-0">
                                @if ($qty !== null && $qty !== '')
                                    <span
                                        class="font-mono text-xs bg-violet-50 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 px-2 py-0.5 rounded-md font-semibold">{{ $qty }}
                                        {{ $qtyUnit }}</span>
                                @endif
                                @if ($aturan)
                                    <span
                                        class="font-mono text-xs bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300 px-2 py-0.5 rounded-md">{{ $aturan }}</span>
                                @endif
                            </div>
                        </div>
                        {{-- Body --}}
                        <div class="px-4 py-3 space-y-2.5">
                            <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">
                                {{ $mDisplay }}</p>

                            {{-- medicationCodeableConcept coding --}}
                            @if ($mSys || $mCode)
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span
                                        class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">medication</span>
                                    @if ($mSys)
                                        <span
                                            class="font-mono text-[10px] px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-400 ring-1 ring-zinc-200 dark:ring-primary-dark-600"
                                            title="{{ $mSys }}">{{ $sysLabel($mSys) ?? $mSys }}</span>
                                    @endif
                                    @if ($mCode)
                                        <span
                                            class="font-mono text-[10px] font-bold text-zinc-600 dark:text-primary-dark-300">{{ $mCode }}</span>
                                    @endif
                                    @if (!empty($mCoding['display']) && $mCoding['display'] !== $mDisplay)
                                        <span
                                            class="text-[10px] text-zinc-400 italic">{{ $mCoding['display'] }}</span>
                                    @endif
                                </div>
                            @endif

                            {{-- KFA / Route coding --}}
                            @if ($kfaCode || $kfaDisplay)
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span
                                            class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">route
                                            (KFA)</span>
                                        @if ($kfaSys)
                                            <span
                                                class="font-mono text-[10px] px-1 py-0.5 rounded bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 ring-1 ring-amber-200 dark:ring-amber-800"
                                                title="{{ $kfaSys }}">{{ $sysLabel($kfaSys) ?? $kfaSys }}</span>
                                        @endif
                                        @if ($kfaCode)
                                            <span
                                                class="font-mono text-[10px] font-bold text-amber-700 dark:text-amber-300">{{ $kfaCode }}</span>
                                        @endif
                                    </div>
                                    @if ($kfaDisplay)
                                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400 leading-snug pl-1">
                                            {{ $kfaDisplay }}</p>
                                    @endif
                                </div>
                            @endif

                            {{-- Dosis & Timing --}}
                            @if ($qty !== null || $freq || $aturan)
                                <div class="flex items-center gap-3 flex-wrap">
                                    @if ($qty !== null && $qty !== '')
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">dosis</span>
                                            <span
                                                class="font-mono text-xs text-zinc-700 dark:text-primary-dark-200">{{ $qty }}
                                                {{ $qtyUnit }}</span>
                                            @if ($qtySys)
                                                <span class="font-mono text-[9px] text-zinc-400"
                                                    title="{{ $qtySys }}">{{ $sysLabel($qtySys) }}</span>
                                            @endif
                                            @if ($qtyCode && $qtyCode !== $qtyUnit)
                                                <span
                                                    class="font-mono text-[9px] text-zinc-400">{{ $qtyCode }}</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($freq && $period)
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">frekuensi</span>
                                            <span
                                                class="font-mono text-xs text-zinc-700 dark:text-primary-dark-200">{{ $freq }}×
                                                / {{ $period }} {{ $periodUnit }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Identifier resep --}}
                            @if ($idTypeCod || $idVal)
                                <div class="pt-1 border-t border-zinc-100 dark:border-primary-dark-700/40 space-y-1">
                                    @if ($idTypeCod)
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span
                                                class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">identifier.type</span>
                                            @if (!empty($idTypeCod['system']))
                                                <span
                                                    class="font-mono text-[10px] px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-400 ring-1 ring-zinc-200 dark:ring-primary-dark-600"
                                                    title="{{ $idTypeCod['system'] }}">{{ $sysLabel($idTypeCod['system']) ?? basename($idTypeCod['system']) }}</span>
                                            @endif
                                            @if (!empty($idTypeCod['code']))
                                                <span
                                                    class="font-mono text-[10px] font-bold text-zinc-500 dark:text-primary-dark-400">{{ $idTypeCod['code'] }}</span>
                                            @endif
                                            @if (!empty($idTypeCod['display']))
                                                <span
                                                    class="text-[10px] text-zinc-400 italic">{{ $idTypeCod['display'] }}</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($idVal)
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">identifier</span>
                                            <span
                                                class="font-mono text-[10px] text-zinc-500 dark:text-primary-dark-400 truncate">{{ $idVal }}</span>
                                        </div>
                                    @endif
                                    @if ($reqAgent)
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="text-[10px] text-zinc-400 font-semibold uppercase tracking-wide">requester</span>
                                            <span
                                                class="text-xs text-zinc-600 dark:text-primary-dark-400">{{ $reqAgent }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center gap-3 py-20">
                        <div
                            class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                            <flux:icon name="beaker" class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                        </div>
                        <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Tidak ada data obat</p>
                    </div>
                @endforelse
            </div>

        </div>
    </div>

    <x-organisms.modal name="erm-history" title="Riwayat Pengiriman eRM" maxWidth="4xl">
        <x-slot name="description">
            No. Rawat: <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ $record->no_rawat }}</span>
            &nbsp;·&nbsp;
            SEP: <span class="font-mono text-emerald-600 dark:text-emerald-400">{{ $record->no_sep }}</span>
        </x-slot>

        <div class="space-y-6 min-h-[40vh]">
            @if (!$showCompare)
                {{-- Daftar Riwayat --}}
                @if ($historyRecords->count() > 1)
                    <div
                        class="flex items-center gap-3 p-4 rounded-xl bg-blue-50/70 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/40">
                        <flux:icon name="information-circle" class="size-5 text-blue-500 shrink-0" />
                        <p class="text-xs text-blue-700 dark:text-blue-300">
                            Pilih <strong>2 pengiriman</strong> untuk membandingkan isi bundle FHIR yang dikirimkan.
                        </p>
                    </div>
                @endif

                <div
                    class="overflow-hidden rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-900/20">
                    <x-organisms.table>
                        <x-slot:headings>
                            @if ($historyRecords->count() > 1)
                                <th class="w-10 px-4 py-3"></th>
                            @endif
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500 w-12">
                                No</th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                Tanggal Kirim</th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                Bundle ID</th>
                            <th class="w-10 px-4 py-3"></th>
                            <th class="w-10 px-4 py-3"></th>
                        </x-slot:headings>

                        @foreach ($historyRecords as $i => $hrec)
                            <tr @class([
                                'group transition-colors hover:bg-zinc-50 dark:hover:bg-primary-dark-800/30',
                                'bg-emerald-50/30 dark:bg-emerald-900/10' => $hrec->id === $id,
                            ])>
                                @if ($historyRecords->count() > 1)
                                    <td class="px-4 py-3">
                                        <input type="checkbox" wire:click="toggleCompare('{{ $hrec->id }}')"
                                            {{ in_array($hrec->id, $compareIds) ? 'checked' : '' }}
                                            {{ !in_array($hrec->id, $compareIds) && count($compareIds) >= 2 ? 'disabled' : '' }}
                                            class="w-4 h-4 rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-primary-dark-600 dark:bg-primary-dark-800 disabled:opacity-30">
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-xs font-medium text-zinc-500 dark:text-primary-dark-400">
                                    {{ $i + 1 }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-zinc-800 dark:text-primary-dark-100">
                                            {{ $hrec->sent_at->format('d M Y, H:i:s') }}
                                        </span>
                                        @if ($hrec->id === $id)
                                            <span
                                                class="px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/40 text-[9px] font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">Aktif</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="font-mono text-[11px] text-zinc-400 dark:text-primary-dark-500 truncate block max-w-[200px]"
                                        title="{{ $hrec->bundle_id }}">
                                        {{ $hrec->bundle_id ?: '-' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <x-atoms.button variant="ghost" size="xs" icon="code-bracket"
                                        title="Lihat JSON" wire:click="openJsonModal('{{ $hrec->id }}')" />
                                </td>
                                <td class="px-4 py-3">
                                    @if ($hrec->id !== $id)
                                        <x-atoms.button variant="ghost" size="xs" icon="eye"
                                            title="Buka detail" wire:navigate
                                            href="{{ route('bpjs.erm-detail', $hrec->id) }}" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-organisms.table>
                </div>
            @else
                {{-- Hasil Compare --}}
                @php
                    $label1 = array_values($compareLabels)[0] ?? '-';
                    $label2 = array_values($compareLabels)[1] ?? '-';
                    $fmtVal = fn($v) => $v === null
                        ? '—'
                        : (is_array($v)
                            ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                            : (string) $v);
                @endphp

                <div class="flex items-center gap-3 mb-4">
                    <x-atoms.button wire:click="backToHistory" icon="arrow-left" variant="ghost" size="sm">
                        Kembali ke Riwayat
                    </x-atoms.button>
                    <div
                        class="flex-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-primary-dark-400">
                        <span
                            class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-primary-dark-700 font-mono">{{ $label1 }}</span>
                        <flux:icon name="arrow-right" class="w-3.5 h-3.5" />
                        <span
                            class="px-2 py-0.5 rounded-md bg-blue-100 dark:bg-blue-900/40 font-mono text-blue-700 dark:text-blue-400">{{ $label2 }}</span>
                    </div>
                </div>

                @if (empty($compareResult))
                    <div class="flex flex-col items-center gap-3 py-12 text-center">
                        <div
                            class="flex items-center justify-center w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-900/30">
                            <flux:icon name="check-circle" class="w-6 h-6 text-emerald-500" />
                        </div>
                        <p class="text-sm font-semibold text-zinc-600 dark:text-primary-dark-300">Tidak ada
                            perbedaan</p>
                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500">Kedua bundle FHIR identik.
                        </p>
                    </div>
                @else
                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mb-3">
                        {{ count($compareResult) }}
                        perbedaan
                        ditemukan</p>
                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead>
                                    <tr
                                        class="bg-zinc-50 dark:bg-primary-dark-900/40 border-b border-zinc-200 dark:border-primary-dark-700">
                                        <th
                                            class="px-4 py-3 text-left font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                                            Path</th>
                                        <th
                                            class="px-4 py-3 text-center font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500 w-24">
                                            Perubahan</th>
                                        <th
                                            class="px-4 py-3 text-left font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500 w-64">
                                            Nilai Lama</th>
                                        <th
                                            class="px-4 py-3 text-left font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500 w-64">
                                            Nilai Baru</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700/50">
                                    @foreach ($compareResult as $diff)
                                        @php
                                            $typeColor = match ($diff['type']) {
                                                'added' => 'emerald',
                                                'removed' => 'red',
                                                default => 'amber',
                                            };
                                            $typeLabel = match ($diff['type']) {
                                                'added' => 'Ditambah',
                                                'removed' => 'Dihapus',
                                                default => 'Berubah',
                                            };
                                            $rowBg = match ($diff['type']) {
                                                'added' => 'bg-emerald-50/40 dark:bg-emerald-900/10',
                                                'removed' => 'bg-red-50/40 dark:bg-red-900/10',
                                                default => 'bg-amber-50/40 dark:bg-amber-900/10',
                                            };
                                        @endphp
                                        <tr class="{{ $rowBg }}">
                                            <td class="px-4 py-2.5">
                                                <code
                                                    class="font-mono text-[11px] text-zinc-600 dark:text-primary-dark-300 break-all">{{ $diff['path'] }}</code>
                                            </td>
                                            <td class="px-4 py-2.5 text-center">
                                                <flux:badge color="{{ $typeColor }}" size="sm">
                                                    {{ $typeLabel }}</flux:badge>
                                            </td>
                                            <td class="px-4 py-2.5">
                                                @if ($diff['old'] !== null)
                                                    @php $oldStr = $fmtVal($diff['old']); @endphp
                                                    @if (str_contains($oldStr, "\n"))
                                                        <pre
                                                            class="text-[11px] text-red-700 dark:text-red-400 whitespace-pre-wrap break-all font-mono bg-red-50 dark:bg-red-900/20 rounded p-2 max-h-32 overflow-y-auto">{{ $oldStr }}</pre>
                                                    @else
                                                        <span
                                                            class="text-[11px] text-red-700 dark:text-red-400 font-mono">{{ $oldStr }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5">
                                                @if ($diff['new'] !== null)
                                                    @php $newStr = $fmtVal($diff['new']); @endphp
                                                    @if (str_contains($newStr, "\n"))
                                                        <pre
                                                            class="text-[11px] text-emerald-700 dark:text-emerald-400 whitespace-pre-wrap break-all font-mono bg-emerald-50 dark:bg-emerald-900/20 rounded p-2 max-h-32 overflow-y-auto">{{ $newStr }}</pre>
                                                    @else
                                                        <span
                                                            class="text-[11px] text-emerald-700 dark:text-emerald-400 font-mono">{{ $newStr }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif

        </div>
    </x-organisms.modal>
</div>
