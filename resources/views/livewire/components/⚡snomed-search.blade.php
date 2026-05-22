<?php

use App\Models\Terminology\SnomedSemanticTag;
use App\Services\Snomed\SnowstormService;
use Livewire\Attributes\Prop;
use Livewire\Component;

/**
 * Komponen pencarian SNOMED CT via Snowstorm API (dengan cache otomatis).
 *
 * Penggunaan:
 *   <livewire:components.snomed-search defaultTag="occupation" />
 *
 * Event yang di-dispatch saat item dipilih:
 *   snomed-selected → { system_code, system_term, system_display, category }
 */
new class extends Component {
    /** Semantic tag default saat pertama ditampilkan */
    #[Prop]
    public ?string $defaultTag = null;

    /** Isi awal field pencarian (misal: system_term yang sudah tersimpan) */
    #[Prop]
    public string $initialSearch = '';

    public string $search = '';
    public string $selectedTag = '';
    public int $page = 1;
    public int $perPage = 15;

    /** Daftar semantic tag aktif (di-load sekali saat mount) */
    public array $semanticTags = [];

    public function mount(): void
    {
        $this->selectedTag = $this->defaultTag ?? '';
        $this->search = $this->initialSearch;
        $this->semanticTags = SnomedSemanticTag::where('active', true)->orderBy('tag')->pluck('tag')->toArray();
    }

    public function updatingSearch(): void
    {
        $this->page = 1;
    }

    public function updatingSelectedTag(): void
    {
        $this->page = 1;
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function nextPage(): void
    {
        // Ambil total terkini dari cache (tidak trigger API call baru jika cache hit)
        $total = $this->fetchTotal();
        if ($this->offset() + $this->perPage < $total) {
            $this->page++;
        }
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /** Ambil total saja dari Snowstorm (hit cache jika tersedia) */
    private function fetchTotal(): int
    {
        if (strlen($this->search) < 2 && !$this->selectedTag) {
            return 0;
        }
        $result = app(SnowstormService::class)->search(term: $this->search, limit: $this->perPage, offset: $this->offset(), semanticTag: $this->selectedTag !== '' ? $this->selectedTag : null);
        return $result['total'] ?? 0;
    }

    /** Dispatch event ke parent saat item dipilih */
    public function select(string $conceptId, string $pt, string $fsn): void
    {
        // Ekstrak semantic tag dari FSN: "Term (tag)" → "tag"
        $category = '';
        if (preg_match('/\(([^)]+)\)$/', $fsn, $matches)) {
            $category = $matches[1];
        }

        $this->dispatch('snomed-selected',
            system_code: $conceptId,
            system_term: $pt,
            system_display: 'http://snomed.info/sct',
            category: $category,
        );
    }

    public function with(): array
    {
        if (strlen($this->search) >= 2 || $this->selectedTag) {
            $result = app(SnowstormService::class)->search(term: $this->search, limit: $this->perPage, offset: $this->offset(), semanticTag: $this->selectedTag !== '' ? $this->selectedTag : null);

            return [
                'items' => $result['items'] ?? [],
                'total' => $result['total'] ?? 0,
            ];
        }

        return ['items' => [], 'total' => 0];
    }
};
?>

<div class="space-y-3">
    {{-- Filter --}}
    <div class="flex flex-col gap-2 sm:flex-row">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.500ms="search"
                placeholder="Cari kode atau istilah SNOMED (min. 2 karakter)..." icon="magnifying-glass" clearable />
        </div>
        <div class="sm:w-56">
            <flux:select wire:model.live="selectedTag" wire:key="snomed-tag-select" placeholder="Semua semantic tag">
                <flux:select.option value="">Semua semantic tag</flux:select.option>
                @foreach ($semanticTags as $tag)
                    <flux:select.option value="{{ $tag }}">{{ Str::headline($tag) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Petunjuk awal --}}
    @if (strlen($search) < 2 && !$selectedTag)
        <div class="flex flex-col items-center justify-center py-10 text-center">
            <flux:icon name="magnifying-glass" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-400">Ketik minimal 2 karakter atau pilih semantic tag untuk mencari.</p>
        </div>
    @else
        {{-- Tabel hasil --}}
        <div class="overflow-auto border rounded-lg border-zinc-200 dark:border-primary-dark-700">
            <table class="w-full text-sm border-collapse">
                <thead class="bg-zinc-50 dark:bg-primary-dark-800">
                    <tr class="text-left">
                        <th
                            class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-primary-dark-400">
                            Concept ID</th>
                        <th
                            class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-primary-dark-400">
                            Preferred Term</th>
                        <th
                            class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-primary-dark-400">
                            Semantic Tag</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-800">
                    @forelse ($items as $item)
                        @php
                            $conceptId = $item['conceptId'] ?? '';
                            $pt = $item['pt']['term'] ?? '';
                            $fsn = $item['fsn']['term'] ?? '';
                            // Ekstrak semantic tag dari FSN
                            preg_match('/\(([^)]+)\)$/', $fsn, $m);
                            $tag = $m[1] ?? '';
                        @endphp
                        <tr class="hover:bg-zinc-100 dark:hover:bg-primary-dark-700/60 cursor-pointer"
                            data-concept-id="{{ $conceptId }}" data-pt="{{ $pt }}"
                            data-fsn="{{ $fsn }}"
                            x-on:click="$wire.select($el.dataset.conceptId, $el.dataset.pt, $el.dataset.fsn)">
                            <td
                                class="px-3 py-2 font-mono text-xs text-zinc-600 dark:text-primary-dark-400 whitespace-nowrap">
                                {{ $conceptId }}
                            </td>
                            <td class="px-3 py-2 text-zinc-800 dark:text-primary-dark-200">
                                {{ $fsn }}
                            </td>
                            <td class="px-3 py-2">
                                @if ($tag)
                                    <flux:badge size="sm" color="indigo">{{ Str::headline($tag) }}</flux:badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-8 text-sm text-center text-zinc-400">
                                Tidak ada hasil ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginasi manual (offset-based) --}}
        @if ($total > $perPage)
            @php
                $from = $this->offset() + 1;
                $to = min($this->offset() + $perPage, $total);
            @endphp
            <div class="flex items-center justify-between text-sm text-zinc-500 dark:text-primary-dark-400">
                <span>Menampilkan {{ $from }}–{{ $to }} dari {{ number_format($total) }} hasil</span>
                <div class="flex gap-2">
                    <flux:button size="xs" variant="ghost" wire:click="previousPage" :disabled="$page <= 1"
                        icon="chevron-left">
                        Sebelumnya
                    </flux:button>
                    <flux:button size="xs" variant="ghost" wire:click="nextPage"
                        :disabled="$this->offset() + $perPage >= $total" icon-trailing="chevron-right">
                        Berikutnya
                    </flux:button>
                </div>
            </div>
        @endif
    @endif
</div>
