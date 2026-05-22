<?php

use App\Models\Bpjs\BpjsLog;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

new class extends Component {
    use WithPagination;

    public bool $ready = false;

    #[Url]
    public string $searchFailed = '';

    #[Url]
    public string $filterBulanFailed = '';

    #[Url]
    public string $filterTahunFailed = '';

    public bool $showFailedDetail = false;
    public ?string $failedDetailNoRawat = null;
    public ?string $failedDetailNoSep = null;

    public function init(): void
    {
        $this->ready = true;
    }

    #[On('refresh-erm-failed')]
    public function onRefresh(): void
    {
        // trigger re-render
    }

    public function updatedSearchFailed(): void { $this->resetPage(); }
    public function updatedFilterBulanFailed(): void { $this->resetPage(); }
    public function updatedFilterTahunFailed(): void { $this->resetPage(); }

    public function openFailedDetail(string $noRawat, string $noSep): void
    {
        $this->failedDetailNoRawat = $noRawat;
        $this->failedDetailNoSep = $noSep;
        $this->showFailedDetail = true;
    }

    public function closeFailedDetail(): void
    {
        $this->failedDetailNoRawat = null;
        $this->failedDetailNoSep = null;
        $this->showFailedDetail = false;
    }

    public function with(): array
    {
        if (!$this->ready) {
            return [
                'failedRecords' => collect(), 'failedTotal' => 0, 'failedTotalAttempts' => 0,
                'failedYears' => collect(), 'failedDetailRecords' => collect(),
            ];
        }

        $failedBase = BpjsLog::forService('erm')->failed();

        $failedSub = BpjsLog::selectRaw('no_rawat, no_sep, MAX(id) as latest_id, COUNT(*) as attempt_count')
            ->where('service', 'erm')->where('status', 'failed')
            ->groupBy('no_rawat', 'no_sep');

        $failedQuery = BpjsLog::joinSub($failedSub, 'fg', fn($j) => $j->on('bpjs_logs.id', '=', 'fg.latest_id'))
            ->select('bpjs_logs.*', 'fg.attempt_count')
            ->orderByDesc('bpjs_logs.created_at');

        if ($this->searchFailed) {
            $s = $this->searchFailed;
            $failedQuery->where(fn($q) => $q
                ->where('bpjs_logs.no_rawat', 'like', "%{$s}%")
                ->orWhere('bpjs_logs.no_sep', 'like', "%{$s}%")
                ->orWhere('bpjs_logs.error_message', 'like', "%{$s}%")
            );
        }
        if ($this->filterBulanFailed !== '') {
            $failedQuery->whereMonth('bpjs_logs.created_at', $this->filterBulanFailed);
        }
        if ($this->filterTahunFailed !== '') {
            $failedQuery->whereYear('bpjs_logs.created_at', $this->filterTahunFailed);
        }

        $failedRecords = $failedQuery->paginate(25);
        $failedTotal = (int) $failedBase->selectRaw('COUNT(DISTINCT CONCAT(no_rawat, "|", no_sep)) as cnt')->value('cnt');
        $failedTotalAttempts = BpjsLog::forService('erm')->failed()->count();
        $failedYears = BpjsLog::where('service', 'erm')->where('status', 'failed')
            ->selectRaw('DISTINCT YEAR(created_at) as y')->orderByDesc('y')->pluck('y');

        $failedDetailRecords = collect();
        if ($this->showFailedDetail && $this->failedDetailNoRawat && $this->failedDetailNoSep) {
            $failedDetailRecords = BpjsLog::forService('erm')->failed()
                ->where('no_rawat', $this->failedDetailNoRawat)
                ->where('no_sep', $this->failedDetailNoSep)
                ->orderByDesc('created_at')
                ->get();
        }

        return compact('failedRecords', 'failedTotal', 'failedTotalAttempts', 'failedYears', 'failedDetailRecords');
    }
};
?>

<div wire:init="init">
    @if (!$ready)
        {{-- Skeleton --}}
        <div class="animate-pulse space-y-4">
            <div class="grid grid-cols-3 gap-4">
                @foreach (range(1, 3) as $_)
                    <div class="h-20 bg-zinc-100 dark:bg-primary-dark-700 rounded-2xl"></div>
                @endforeach
            </div>
            <div class="h-64 bg-zinc-100 dark:bg-primary-dark-700 rounded-2xl"></div>
        </div>
    @else
        {{-- Stats gagal --}}
        <div class="grid grid-cols-2 gap-4 mb-6 sm:grid-cols-3">
            <x-organisms.stat-card title="Kunjungan Gagal" :value="number_format($failedTotal)" icon="x-circle" color="red" subtitle="kunjungan unik" />
            <x-organisms.stat-card title="Total Percobaan" :value="number_format($failedTotalAttempts)" icon="arrow-path" color="amber" subtitle="percobaan gagal" />
            <div class="col-span-2 sm:col-span-1">
                <x-organisms.stat-card title="Rata-rata Percobaan"
                    :value="$failedTotal > 0 ? number_format($failedTotalAttempts / $failedTotal, 1) : '—'"
                    icon="chart-bar" color="zinc" subtitle="per kunjungan" />
            </div>
        </div>

        {{-- Tabel gagal --}}
        <x-organisms.data-panel :padding="false">
            <x-slot:filter>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:flex-wrap">
                    <flux:input wire:model.live.debounce.300ms="searchFailed" icon="magnifying-glass"
                        placeholder="No rawat, No SEP, atau pesan error..." clearable class="flex-1" />
                    <flux:select wire:model.live="filterBulanFailed" class="sm:w-36">
                        <flux:select.option value="">Semua Bulan</flux:select.option>
                        @foreach (range(1, 12) as $m)
                            <flux:select.option value="{{ $m }}">
                                {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="filterTahunFailed" class="sm:w-28">
                        <flux:select.option value="">Semua Tahun</flux:select.option>
                        @foreach ($failedYears as $y)
                            <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </x-slot:filter>
            <x-organisms.table>
                <x-slot:headings>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500 w-36">
                        Percobaan Terakhir</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                        No SEP / No Rawat</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                        Pesan Error</th>
                    <th
                        class="px-5 py-3.5 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-28">
                        HTTP Status</th>
                    <th class="px-5 py-3.5 w-28"></th>
                </x-slot:headings>
                @forelse ($failedRecords as $frec)
                    <tr wire:key="failed-{{ $frec->id }}"
                        class="group transition-colors hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/20">
                        <td class="px-5 py-4 whitespace-nowrap">
                            <p class="text-sm text-zinc-700 dark:text-primary-dark-300">
                                {{ $frec->created_at->format('d/m/Y') }}</p>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                                {{ $frec->created_at->format('H:i') }}</p>
                        </td>
                        <td class="px-5 py-4">
                            <p class="font-mono text-xs font-bold text-zinc-700 dark:text-primary-dark-200">
                                {{ $frec->no_sep ?? '—' }}</p>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">
                                {{ $frec->no_rawat ?? '—' }}</p>
                        </td>
                        <td class="px-5 py-4 max-w-xs">
                            @if ($frec->error_message)
                                <p class="text-xs text-red-600 dark:text-red-400 line-clamp-2 leading-relaxed"
                                    title="{{ $frec->error_message }}">{{ $frec->error_message }}</p>
                            @else
                                <span class="text-xs text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-center">
                            @if ($frec->response_status)
                                <span
                                    class="inline-flex items-center rounded-md px-2 py-1 font-mono text-xs font-bold
                                    {{ $frec->response_status >= 500 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : ($frec->response_status >= 400 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-zinc-100 text-zinc-600 dark:bg-primary-dark-700 dark:text-primary-dark-300') }}">
                                    {{ $frec->response_status }}
                                </span>
                            @else
                                <span class="text-xs text-zinc-400">—</span>
                            @endif
                        </td>
                        <x-atoms.table-cell :action="true" align="center">
                            <x-atoms.button variant="ghost" size="sm" icon="exclamation-circle"
                                wire:click="openFailedDetail('{{ $frec->no_rawat }}', '{{ $frec->no_sep }}')"
                                tooltip="{{ $frec->attempt_count }}× — Lihat percobaan"
                                class="{{ $frec->attempt_count > 1 ? 'text-red-500' : '' }}" />
                            <x-atoms.button size="sm" variant="ghost" icon="list-bullet"
                                wire:click="openFailedDetail('{{ $frec->no_rawat }}', '{{ $frec->no_sep }}')"
                                tooltip="Riwayat Percobaan" />
                        </x-atoms.table-cell>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-20 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div
                                    class="flex items-center justify-center w-14 h-14 rounded-2xl bg-zinc-100 dark:bg-primary-dark-700">
                                    <flux:icon name="check-circle" class="w-7 h-7 text-emerald-400 dark:text-emerald-500" />
                                </div>
                                <p class="text-sm font-semibold text-zinc-500 dark:text-primary-dark-400">Tidak ada
                                    eRM yang gagal dikirim</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </x-organisms.table>

            @if ($failedRecords->hasPages())
                <div
                    class="px-5 py-4 border-t border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/50 dark:bg-primary-dark-900/20">
                    {{ $failedRecords->links() }}
                </div>
            @endif
        </x-organisms.data-panel>

        {{-- Modal: Riwayat Percobaan Gagal --}}
        <x-organisms.modal wire:model="showFailedDetail" title="Riwayat Percobaan Gagal" maxWidth="4xl">
            <x-slot name="description">
                @if ($failedDetailNoRawat)
                    No. Rawat: <span
                        class="font-mono font-medium text-emerald-600 dark:text-emerald-400">{{ $failedDetailNoRawat }}</span>
                    &nbsp;·&nbsp;
                    SEP: <span
                        class="font-mono font-medium text-emerald-600 dark:text-emerald-400">{{ $failedDetailNoSep }}</span>
                @endif
            </x-slot>

            <div class="space-y-4">
                <div
                    class="flex items-start gap-4 p-4 rounded-xl bg-red-50 dark:bg-red-900/10 border border-red-100 dark:border-red-900/50">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                        <flux:icon.exclamation-triangle class="size-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-red-900 dark:text-red-200">Log Pengiriman Gagal</h4>
                            <flux:badge color="red" size="sm">{{ $failedDetailRecords->count() }}× Gagal</flux:badge>
                        </div>
                        <p class="mt-1 text-xs text-red-700 dark:text-red-300 leading-relaxed">
                            Data bundle eRM gagal dikirim ke server tujuan. Klik baris log untuk melihat rincian alasan
                            kegagalan dari server.
                        </p>
                    </div>
                </div>

                @if ($failedDetailRecords->isEmpty())
                    <div
                        class="py-12 flex flex-col items-center justify-center text-zinc-400 dark:text-primary-dark-600">
                        <flux:icon name="inbox" class="size-10 mb-2 opacity-20" />
                        <p class="text-sm italic">Tidak ada rincian percobaan gagal.</p>
                    </div>
                @else
                    <div
                        class="divide-y divide-zinc-100 dark:divide-primary-dark-800 max-h-[50vh] overflow-y-auto border border-zinc-200 dark:border-primary-dark-700 rounded-xl bg-white dark:bg-primary-dark-900/20">
                        @foreach ($failedDetailRecords as $i => $attempt)
                            <div x-data="{ open: false }"
                                class="px-5 py-4 transition-colors hover:bg-zinc-50/50 dark:hover:bg-primary-dark-800/10">
                                <div class="flex items-center gap-4">
                                    <span
                                        class="shrink-0 flex items-center justify-center size-7 rounded-full bg-zinc-100 dark:bg-primary-dark-800 text-xs font-bold text-zinc-500 dark:text-primary-dark-400 border border-zinc-200 dark:border-primary-dark-700">
                                        {{ $i + 1 }}
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-bold text-zinc-800 dark:text-primary-dark-100">
                                                {{ $attempt->created_at->format('d M Y, H:i:s') }}
                                            </span>
                                            @if ($attempt->response_status)
                                                <span
                                                    class="font-mono text-[11px] font-bold px-2 py-0.5 rounded border
                                                    {{ $attempt->response_status >= 500 ? 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800' : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800' }}">
                                                    HTTP {{ $attempt->response_status }}
                                                </span>
                                            @endif
                                            @if ($attempt->response_time)
                                                <span
                                                    class="text-[11px] font-medium text-zinc-400 dark:text-primary-dark-500 bg-zinc-50 dark:bg-primary-dark-800/50 px-1.5 py-0.5 rounded border border-zinc-100 dark:border-primary-dark-700/50">
                                                    {{ number_format($attempt->response_time, 2) }}s
                                                </span>
                                            @endif
                                        </div>
                                        @if ($attempt->error_message)
                                            <p class="mt-1.5 text-xs text-red-600 dark:text-red-400 font-medium leading-relaxed">
                                                {{ $attempt->error_message }}</p>
                                        @endif
                                    </div>
                                    @if ($attempt->response_payload)
                                        <x-atoms.button variant="ghost" size="sm" @click="open = !open">
                                            <span x-text="open ? 'Tutup' : 'Respons'"></span>
                                            <flux:icon name="chevron-down" class="size-3 transition-transform"
                                                ::class="open ? 'rotate-180' : ''" />
                                        </x-atoms.button>
                                    @endif
                                </div>
                                @if ($attempt->response_payload)
                                    <div x-show="open" x-cloak x-collapse class="mt-4">
                                        <x-atoms.code-block language="json" maxHeight="max-h-80">{{ json_encode($attempt->response_payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <x-slot name="footer">
                <div class="flex justify-end">
                    <x-atoms.button wire:click="closeFailedDetail" variant="ghost">Tutup</x-atoms.button>
                </div>
            </x-slot>
        </x-organisms.modal>
    @endif
</div>
