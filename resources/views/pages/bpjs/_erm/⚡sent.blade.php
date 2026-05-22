<?php

use App\Models\Bpjs\BpjsErm;
use App\Models\Simrs\Poliklinik;
use App\Models\Simrs\Bangsal;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

new class extends Component {
    use WithPagination;

    public bool $ready = false;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterType = '';

    #[Url]
    public string $filterBulan = '';

    #[Url]
    public string $filterTahun = '';

    public bool $showHistoryModal = false;
    public ?string $historyNoRawat = null;
    public ?string $historyNoSep = null;
    public array $compareIds = [];
    public array $compareResult = [];
    public bool $showCompare = false;

    public function init(): void
    {
        $this->ready = true;
    }

    #[On('refresh-erm-sent')]
    public function onRefresh(): void
    {
        // trigger re-render
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFilterType(): void
    {
        $this->resetPage();
    }
    public function updatedFilterBulan(): void
    {
        $this->resetPage();
    }
    public function updatedFilterTahun(): void
    {
        $this->resetPage();
    }

    public function openHistory(string $noRawat, string $noSep): void
    {
        $this->historyNoRawat = $noRawat;
        $this->historyNoSep = $noSep;
        $this->compareIds = [];
        $this->compareResult = [];
        $this->showCompare = false;
        $this->showHistoryModal = true;
    }

    public function closeHistory(): void
    {
        $this->showHistoryModal = false;
        $this->historyNoRawat = null;
        $this->historyNoSep = null;
        $this->compareIds = [];
        $this->compareResult = [];
        $this->showCompare = false;
    }

    public function toggleCompare(string $id): void
    {
        if (in_array($id, $this->compareIds)) {
            $this->compareIds = array_values(array_filter($this->compareIds, fn($i) => $i !== $id));
        } elseif (count($this->compareIds) < 2) {
            $this->compareIds[] = $id;
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
        if (!$this->ready) {
            return [
                'records' => collect(),
                'total' => 0,
                'statRalan' => 0,
                'statRanap' => 0,
                'statIgd' => 0,
                'years' => collect(),
                'roomNames' => [],
                'historyRecords' => collect(),
                'compareLabels' => [],
            ];
        }

        $roomNames = [];
        try {
            $roomNames = [...Poliklinik::pluck('nm_poli', 'kd_poli')->toArray(), ...Bangsal::pluck('nm_bangsal', 'kd_bangsal')->toArray()];
        } catch (\Exception) {
        }

        $latestSub = BpjsErm::selectRaw('no_rawat, no_sep, MAX(id) as latest_id, COUNT(*) as send_count')->groupBy('no_rawat', 'no_sep');

        $query = BpjsErm::joinSub($latestSub, 'g', fn($j) => $j->on('bpjs_erm.id', '=', 'g.latest_id'))->select('bpjs_erm.*', 'g.send_count')->orderByDesc('bpjs_erm.sent_at');

        if ($this->search) {
            $s = $this->search;
            $query->where(fn($q) => $q->where('bpjs_erm.no_rawat', 'like', "%{$s}%")->orWhere('bpjs_erm.no_sep', 'like', "%{$s}%"));
        }
        if ($this->filterType !== '') {
            $query->where('bpjs_erm.encounter_type', $this->filterType);
        }
        if ($this->filterBulan !== '') {
            $query->where('bpjs_erm.bulan', $this->filterBulan);
        }
        if ($this->filterTahun !== '') {
            $query->where('bpjs_erm.tahun', $this->filterTahun);
        }

        $records = $query->paginate(25);
        $years = BpjsErm::selectRaw('DISTINCT tahun')->orderByDesc('tahun')->pluck('tahun');
        $total = BpjsErm::selectRaw('COUNT(DISTINCT CONCAT(no_rawat, "|", no_sep)) as cnt')->value('cnt');
        $statRalan = BpjsErm::selectRaw('COUNT(DISTINCT CONCAT(no_rawat, "|", no_sep)) as cnt')->where('encounter_type', 'AMB')->value('cnt');
        $statRanap = BpjsErm::selectRaw('COUNT(DISTINCT CONCAT(no_rawat, "|", no_sep)) as cnt')->where('encounter_type', 'IMP')->value('cnt');
        $statIgd = BpjsErm::selectRaw('COUNT(DISTINCT CONCAT(no_rawat, "|", no_sep)) as cnt')->where('encounter_type', 'EMER')->value('cnt');

        $historyRecords = collect();
        $compareLabels = [];
        if ($this->historyNoRawat && $this->historyNoSep) {
            $historyRecords = BpjsErm::where('no_rawat', $this->historyNoRawat)
                ->where('no_sep', $this->historyNoSep)
                ->orderByDesc('sent_at')
                ->get(['id', 'sent_at', 'bundle_id']);
        }
        if ($this->showCompare && count($this->compareIds) === 2) {
            $compareLabels = BpjsErm::whereIn('id', $this->compareIds)
                ->orderBy('sent_at')
                ->get(['id', 'sent_at'])
                ->mapWithKeys(fn($r) => [$r->id => $r->sent_at->format('d/m/Y H:i')])
                ->toArray();
        }

        return compact('records', 'total', 'statRalan', 'statRanap', 'statIgd', 'years', 'roomNames', 'historyRecords', 'compareLabels');
    }
};
?>

<div wire:init="init">
    @if (!$ready)
        {{-- Skeleton --}}
        <div class="animate-pulse space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                @foreach (range(1, 4) as $_)
                    <div class="h-20 bg-zinc-100 dark:bg-primary-dark-700 rounded-2xl"></div>
                @endforeach
            </div>
            <div class="h-64 bg-zinc-100 dark:bg-primary-dark-700 rounded-2xl"></div>
        </div>
    @else
        {{-- Stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <x-organisms.stat-card title="Total Kunjungan" :value="number_format($total)" icon="document-text" color="zinc" />
            <x-organisms.stat-card title="Rawat Jalan" :value="number_format($statRalan)" icon="building-office" color="blue" />
            <x-organisms.stat-card title="Rawat Inap" :value="number_format($statRanap)" icon="building-office-2" color="emerald" />
            <x-organisms.stat-card title="IGD" :value="number_format($statIgd)" icon="exclamation-triangle" color="rose" />
        </div>

        {{-- Tabel --}}
        <x-organisms.data-panel :padding="false">
            <x-slot:filter>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:flex-wrap">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        placeholder="No rawat atau No SEP..." clearable class="flex-1" />
                    <flux:select wire:model.live="filterType" class="sm:w-44">
                        <flux:select.option value="">Semua Jenis</flux:select.option>
                        <flux:select.option value="AMB">Rawat Jalan</flux:select.option>
                        <flux:select.option value="IMP">Rawat Inap</flux:select.option>
                        <flux:select.option value="EMER">IGD</flux:select.option>
                    </flux:select>
                    <flux:select wire:model.live="filterBulan" class="sm:w-36">
                        <flux:select.option value="">Semua Bulan</flux:select.option>
                        @foreach (range(1, 12) as $m)
                            <flux:select.option value="{{ $m }}">
                                {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="filterTahun" class="sm:w-28">
                        <flux:select.option value="">Semua Tahun</flux:select.option>
                        @foreach ($years as $y)
                            <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </x-slot:filter>
            <x-organisms.table>
                <x-slot:headings>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500 w-36">
                        Tanggal Kirim Terakhir</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                        No SEP / No Rawat</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                        Pasien</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                        Ruang</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                        Diagnosis Utama</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-28">
                        Kelas</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-28">
                        Jumlah Kirim</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-20">
                        Aksi</th>
                </x-slot:headings>
                @forelse ($records as $rec)
                    @php
                        $badgeColor = match ($rec->encounter_type) {
                            'EMER' => 'red',
                            'IMP' => 'emerald',
                            'AMB' => 'blue',
                            default => 'zinc',
                        };
                        $roomName = $roomNames[$rec->room_code] ?? null;
                    @endphp
                    <tr wire:key="sent-{{ $rec->id }}"
                        class="group transition-colors hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/20">
                        <td class="px-5 py-4 whitespace-nowrap">
                            <p class="text-sm text-zinc-700 dark:text-primary-dark-300">
                                {{ $rec->sent_at->format('d/m/Y') }}</p>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                                {{ $rec->sent_at->format('H:i') }}</p>
                        </td>
                        <td class="px-5 py-4">
                            <p class="font-mono text-xs font-bold text-zinc-700 dark:text-primary-dark-200">
                                {{ $rec->no_sep }}</p>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">{{ $rec->no_rawat }}</p>
                            @if ($rec->bundle_id)
                                <p class="font-mono text-[10px] text-zinc-300 dark:text-primary-dark-600 mt-0.5 truncate max-w-[180px]"
                                    title="{{ $rec->bundle_id }}">{{ $rec->bundle_id }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">
                                {{ $rec->patient_name ?? '-' }}</p>
                            @if ($rec->patient_bpjs)
                                <p class="font-mono text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">
                                    {{ $rec->patient_bpjs }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <p class="text-sm text-zinc-700 dark:text-primary-dark-300 truncate max-w-[160px]"
                                title="{{ $roomName ?? $rec->room_code }}">
                                {{ $roomName ?? $rec->room_code }}
                            </p>
                            @if ($roomName)
                                <p class="font-mono text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">
                                    {{ $rec->room_code }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if ($rec->diagnosa_utama)
                                <span
                                    class="inline-block font-mono text-xs font-bold px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-600 dark:text-primary-dark-300 ring-1 ring-zinc-200 dark:ring-primary-dark-600">
                                    {{ $rec->diagnosa_utama }}
                                </span>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5 max-w-xs truncate"
                                    title="{{ $rec->diagnosa_utama_display }}">
                                    {{ $rec->diagnosa_utama_display }}
                                </p>
                            @else
                                <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-center">
                            <flux:badge color="{{ $badgeColor }}" size="sm">{{ $rec->encounter_type_label }}
                            </flux:badge>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <x-atoms.button variant="ghost" size="sm" icon="clock"
                                wire:click="openHistory('{{ $rec->no_rawat }}', '{{ $rec->no_sep }}')"
                                tooltip="{{ $rec->send_count }}× — Lihat riwayat"
                                class="{{ $rec->send_count > 1 ? 'text-amber-600 dark:text-amber-400' : '' }}">
                                {{ $rec->send_count }}
                            </x-atoms.button>
                        </td>
                        <x-atoms.table-cell :action="true" align="center">
                            <x-atoms.button variant="ghost" size="sm" icon="eye" tooltip="Lihat Detail"
                                href="{{ route('bpjs.erm-detail', $rec->id) }}" />
                        </x-atoms.table-cell>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-20 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div
                                    class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                    <flux:icon name="document-text"
                                        class="w-7 h-7 text-zinc-300 dark:text-primary-dark-500" />
                                </div>
                                <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Belum ada
                                    data eRM yang terkirim</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </x-organisms.table>

            @if ($records->hasPages())
                <div
                    class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                    {{ $records->links() }}
                </div>
            @endif
        </x-organisms.data-panel>

        {{-- Modal: Riwayat Pengiriman --}}
        <x-organisms.modal wire:model="showHistoryModal" title="Riwayat Pengiriman eRM" maxWidth="4xl">
            <x-slot name="description">
                @if ($historyNoRawat)
                    No. Rawat: <span
                        class="font-mono font-medium text-emerald-600 dark:text-emerald-400">{{ $historyNoRawat }}</span>
                    &nbsp;·&nbsp;
                    SEP: <span
                        class="font-mono font-medium text-emerald-600 dark:text-emerald-400">{{ $historyNoSep }}</span>
                @endif
            </x-slot>

            <div class="space-y-4">
                @if (!$showCompare)
                    @if ($historyRecords->count() > 1)
                        <div
                            class="flex items-center gap-2 p-3 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                            <flux:icon name="information-circle" class="w-4 h-4 text-blue-500 shrink-0" />
                            <p class="text-xs text-blue-700 dark:text-blue-300">
                                Pilih <strong>2 pengiriman</strong> untuk membandingkan isi bundle FHIR.
                            </p>
                        </div>
                    @endif

                    <x-organisms.card-box :padding="false">
                        <x-organisms.table>
                            <x-slot:headings></x-slot:headings>
                            @foreach ($historyRecords as $i => $hrec)
                                @php $isChecked = in_array($hrec->id, $compareIds); @endphp
                                <tr
                                    class="transition-colors {{ $isChecked ? 'bg-blue-50/60 dark:bg-blue-900/10' : 'hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/20' }}">
                                    @if ($historyRecords->count() > 1)
                                        <td class="px-4 py-3 text-center">
                                            <input type="checkbox" wire:click="toggleCompare('{{ $hrec->id }}')"
                                                @checked($isChecked) @disabled(!$isChecked && count($compareIds) >= 2)
                                                class="w-4 h-4 rounded border-zinc-300 dark:border-primary-dark-600 text-blue-600 focus:ring-blue-500 cursor-pointer disabled:cursor-not-allowed disabled:opacity-40" />
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 text-sm text-zinc-500 dark:text-primary-dark-400">
                                        {{ $i + 1 }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">
                                            {{ $hrec->sent_at->format('d/m/Y') }}</p>
                                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                                            {{ $hrec->sent_at->format('H:i:s') }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400"
                                            title="{{ $hrec->bundle_id }}">{{ $hrec->bundle_id ?? '—' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a wire:navigate href="{{ route('bpjs.erm-detail', $hrec->id) }}"
                                            class="text-zinc-400 dark:text-primary-dark-500 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                                            title="Buka pengiriman ini">
                                            <flux:icon name="arrow-top-right-on-square" class="w-4 h-4" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </x-organisms.table>
                    </x-organisms.card-box>

                    @if ($historyRecords->count() > 1)
                        <div class="flex items-center justify-end gap-3">
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">{{ count($compareIds) }}/2
                                dipilih</p>
                            <x-atoms.button wire:click="runCompare" icon="arrows-right-left" variant="primary"
                                size="sm" :disabled="count($compareIds) !== 2">
                                Bandingkan Bundle
                            </x-atoms.button>
                        </div>
                    @endif
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
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">Kedua bundle FHIR identik.</p>
                        </div>
                    @else
                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mb-3">
                            {{ count($compareResult) }} perbedaan ditemukan</p>
                        <x-organisms.card-box :padding="false">
                            <x-organisms.table>
                                <x-slot:headings>
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
                                </x-slot:headings>
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
                            </x-organisms.table>
                        </x-organisms.card-box>
                    @endif
                @endif
            </div>

            <x-slot name="footer">
                <div class="flex justify-end">
                    <x-atoms.button wire:click="closeHistory" variant="ghost">Tutup</x-atoms.button>
                </div>
            </x-slot>
        </x-organisms.modal>
    @endif
</div>
