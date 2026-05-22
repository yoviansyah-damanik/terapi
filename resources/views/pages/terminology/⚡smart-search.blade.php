<?php

use App\Helpers\ConfigurationHelper;
use App\Services\Terminology\SmartSearchService;
use App\Services\Terminology\TerminologyTranslatorService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Pencarian Pintar Terminologi')] class extends Component {
    public string $activeTab = 'search';

    // Filter sumber (default: semua aktif)
    public array $selectedSources = ['icd10', 'icd9', 'snomed', 'loinc', 'kfa', 'icd_o_topography', 'icd_o_morphology', 'icd_pm', 'icd_mm', 'hl7'];

    // Tab: Pencarian
    public string $query = '';
    public array $results = [];
    public bool $usedAiFallback = false;
    public ?string $aiFallbackTerm = null;
    // Konteks AI dari Stage 1 (interpretasi + term medis)
    public ?array $aiSearchContext = null;

    // Tab: Terjemahan AI
    public string $aiQuery = '';
    public ?array $aiResult = null;
    public ?string $aiError = null;

    /** Label display per source slug */
    public function sourceLabel(string $source): string
    {
        return match ($source) {
            'icd10' => 'ICD-10',
            'icd9' => 'ICD-9CM',
            'snomed' => 'SNOMED CT',
            'loinc' => 'LOINC',
            'kfa' => 'KFA',
            'icd_o_topography' => 'ICD-O Topography',
            'icd_o_morphology' => 'ICD-O Morphology',
            'icd_pm' => 'ICD-PM',
            'icd_mm' => 'ICD-MM',
            'hl7' => 'HL7 CodeSystem',
            default => $source,
        };
    }

    public function isAiConfigured(): bool
    {
        $provider = ConfigurationHelper::get('ai.provider', 'ollama');
        return match ($provider) {
            'ollama' => !empty(ConfigurationHelper::get('ai.ollama_url')),
            'claude' => !empty(ConfigurationHelper::get('ai.claude_key')),
            'openai' => !empty(ConfigurationHelper::get('ai.openai_key')),
            'gemini' => !empty(ConfigurationHelper::get('ai.gemini_key')),
            'grok'   => !empty(ConfigurationHelper::get('ai.grok_key')),
            default  => !empty($provider), // Provider custom lain dianggap sudah dikonfigurasi
        };
    }

    public function search(): void
    {
        $this->validate(['query' => 'required|string|min:2']);
        $this->usedAiFallback = false;
        $this->aiFallbackTerm = null;
        $this->aiSearchContext = null;

        if (empty($this->selectedSources)) {
            $this->addError('selectedSources', 'Pilih minimal satu sumber terminologi.');
            return;
        }

        $service   = app(SmartSearchService::class);
        $rawQuery  = trim($this->query);
        $searchTerm = $rawQuery;
        $scoreRef   = $rawQuery; // Acuan scoring

        // === Stage 1: Normalisasi query dengan AI (jika tersedia) ===
        if ($this->isAiConfigured()) {
            try {
                $aiResp = app(TerminologyTranslatorService::class)->translate($rawQuery, []);
                $primaryMedTerm = $aiResp['medical_terms']['primary'] ?? null;

                if ($primaryMedTerm && $primaryMedTerm !== $rawQuery) {
                    $searchTerm = $primaryMedTerm;
                    $scoreRef   = $primaryMedTerm;
                    $this->aiSearchContext = [
                        'interpretation'      => $aiResp['interpretation'] ?? '',
                        'primary_medical_term'=> $primaryMedTerm,
                        'organism'            => $aiResp['medical_terms']['organism_or_agent'] ?? null,
                        'alternatives'        => $aiResp['medical_terms']['alternatives'] ?? [],
                        'type_hints'          => $aiResp['type_hints'] ?? [],
                    ];
                }
            } catch (\Exception $e) {
                // AI tidak tersedia, lanjut dengan query asli
            }
        }

        $this->results = $service->searchAll($searchTerm, $this->selectedSources, 8, $scoreRef);

        // Jika tidak ada hasil dan input terasa Indonesia, coba term alternatif
        $totalResults = collect($this->results)->sum(fn($s) => count($s['items'] ?? []));

        if ($totalResults === 0 && !empty($this->aiSearchContext['alternatives'])) {
            foreach ($this->aiSearchContext['alternatives'] as $altTerm) {
                $altResults = $service->searchAll($altTerm, $this->selectedSources, 8, $scoreRef);
                $altTotal = collect($altResults)->sum(fn($s) => count($s['items'] ?? []));
                if ($altTotal > 0) {
                    $this->results = $altResults;
                    $this->usedAiFallback  = true;
                    $this->aiFallbackTerm  = $altTerm;
                    break;
                }
            }
        }
    }

    public function translate(): void
    {
        $this->validate(['aiQuery' => 'required|string|min:5']);

        if (empty($this->selectedSources)) {
            $this->addError('selectedSources', 'Pilih minimal satu sumber terminologi.');
            return;
        }

        $this->aiResult = null;
        $this->aiError = null;

        try {
            $service = app(TerminologyTranslatorService::class);
            $this->aiResult = $service->translate(trim($this->aiQuery));
        } catch (\Exception $e) {
            $this->aiError = $e->getMessage();
        }
    }

    public function selectAllSources(): void
    {
        $this->selectedSources = ['icd10', 'icd9', 'snomed', 'loinc', 'kfa', 'icd_o_topography', 'icd_o_morphology', 'icd_pm', 'icd_mm', 'hl7'];
    }

    public function clearAllSources(): void
    {
        $this->selectedSources = [];
    }

    public function toggleSource(string $source): void
    {
        if (in_array($source, $this->selectedSources)) {
            $this->selectedSources = array_values(array_filter($this->selectedSources, fn($s) => $s !== $source));
        } else {
            $this->selectedSources[] = $source;
        }
    }
}; ?>

<div>
    @php
        $sourceColors = [
            'icd10'            => ['active' => 'bg-blue-600 text-white border-blue-600',        'inactive' => 'bg-white text-zinc-400 border-zinc-200 hover:border-blue-300 hover:text-blue-500 dark:bg-primary-dark-800 dark:border-primary-dark-600 dark:text-primary-dark-500'],
            'icd9'             => ['active' => 'bg-indigo-600 text-white border-indigo-600',     'inactive' => 'bg-white text-zinc-400 border-zinc-200 hover:border-indigo-300 hover:text-indigo-500 dark:bg-primary-dark-800 dark:border-primary-dark-600 dark:text-primary-dark-500'],
            'snomed'           => ['active' => 'bg-emerald-600 text-white border-emerald-600',   'inactive' => 'bg-white text-zinc-400 border-zinc-200 hover:border-emerald-300 hover:text-emerald-500 dark:bg-primary-dark-800 dark:border-primary-dark-600 dark:text-primary-dark-500'],
            'loinc'            => ['active' => 'bg-violet-600 text-white border-violet-600',     'inactive' => 'bg-white text-zinc-400 border-zinc-200 hover:border-violet-300 hover:text-violet-500 dark:bg-primary-dark-800 dark:border-primary-dark-600 dark:text-primary-dark-500'],
            'kfa'              => ['active' => 'bg-rose-600 text-white border-rose-600',         'inactive' => 'bg-white text-zinc-400 border-zinc-200 hover:border-rose-300 hover:text-rose-500 dark:bg-primary-dark-800 dark:border-primary-dark-600 dark:text-primary-dark-500'],
            'icd_o_topography' => ['active' => 'bg-orange-500 text-white border-orange-500',    'inactive' => 'bg-white text-zinc-400 border-zinc-200 hover:border-orange-300 hover:text-orange-500 dark:bg-primary-dark-800 dark:border-primary-dark-600 dark:text-primary-dark-500'],
            'icd_o_morphology' => ['active' => 'bg-amber-500 text-white border-amber-500',      'inactive' => 'bg-white text-zinc-400 border-zinc-200 hover:border-amber-300 hover:text-amber-500 dark:bg-primary-dark-800 dark:border-primary-dark-600 dark:text-primary-dark-500'],
            'icd_pm'           => ['active' => 'bg-teal-600 text-white border-teal-600',         'inactive' => 'bg-white text-zinc-400 border-zinc-200 hover:border-teal-300 hover:text-teal-500 dark:bg-primary-dark-800 dark:border-primary-dark-600 dark:text-primary-dark-500'],
            'icd_mm'           => ['active' => 'bg-cyan-600 text-white border-cyan-600',         'inactive' => 'bg-white text-zinc-400 border-zinc-200 hover:border-cyan-300 hover:text-cyan-500 dark:bg-primary-dark-800 dark:border-primary-dark-600 dark:text-primary-dark-500'],
            'hl7'              => ['active' => 'bg-zinc-700 text-white border-zinc-700',         'inactive' => 'bg-white text-zinc-400 border-zinc-200 hover:border-zinc-400 hover:text-zinc-600 dark:bg-primary-dark-800 dark:border-primary-dark-600 dark:text-primary-dark-500'],
        ];
    @endphp

    <x-ui.page-header title="Pencarian Pintar"
        subtitle="Cari lintas sumber terminologi atau terjemahkan teks klinis dengan AI" />

    {{-- Tabs --}}
    <x-molecules.tabs>
        <x-atoms.tab-item wire:click="$set('activeTab', 'search')">Pencarian</x-atoms.tab-item>
        <x-atoms.tab-item wire:click="$set('activeTab', 'translate')">Terjemahan AI</x-atoms.tab-item>
    
    </x-molecules.tabs>

    {{-- ======================== TAB: PENCARIAN ======================== --}}
    @if ($activeTab === 'search')
        <div class="flex flex-col gap-4" key="search">

            {{-- Input + Filter --}}
            <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
                <div class="p-4 flex gap-3">
                    <div class="flex-1">
                        <flux:input wire:model="query" wire:keydown.enter="search" icon="magnifying-glass"
                            placeholder="Ketik istilah klinis atau kode (contoh: dyspnea, R06.0, 59408-5)..."
                            clearable />
                        @error('query')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>
                    <x-atoms.button wire:click="search" variant="primary" icon="magnifying-glass">
                        Cari
                    </x-atoms.button>
                    <span wire:loading wire:target="search"
                        class="self-center text-sm text-zinc-500 dark:text-primary-dark-400">Mencari...</span>
                </div>

                <div class="px-4 pb-4 border-t border-zinc-100 dark:border-primary-dark-700 pt-3">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">Filter Sumber</span>
                        <div class="flex gap-3">
                            <x-atoms.button wire:click="selectAllSources" class="text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium">Semua</x-atoms.button>
                            <span class="text-zinc-300 dark:text-zinc-600">|</span>
                            <x-atoms.button wire:click="clearAllSources" class="text-xs text-zinc-400 dark:text-primary-dark-500 hover:underline">Hapus</x-atoms.button>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (array_keys($sourceColors) as $src)
                            @php $isActive = in_array($src, $selectedSources); @endphp
                            <x-atoms.button type="button"
                                wire:click="toggleSource('{{ $src }}')"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-xs font-medium transition-all duration-150
                                       {{ $isActive ? $sourceColors[$src]['active'] : $sourceColors[$src]['inactive'] }}">
                                @if($isActive)
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                @endif
                                {{ $this->sourceLabel($src) }}
                            </x-atoms.button>
                        @endforeach
                    </div>
                    @error('selectedSources')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
            </div>

            {{-- Banner Interpretasi AI (Stage 1) --}}
            @if(!empty($aiSearchContext))
                <div class="rounded-xl border border-blue-200 dark:border-blue-800/50 bg-blue-50 dark:bg-blue-900/15 px-4 py-3">
                    <div class="flex items-start gap-3">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/50 mt-0.5">
                            <flux:icon name="sparkles" class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-blue-800 dark:text-blue-300">AI Interpretation</p>
                            @if(!empty($aiSearchContext['interpretation']))
                                <p class="mt-0.5 text-xs text-blue-700 dark:text-blue-400 italic leading-snug">{{ $aiSearchContext['interpretation'] }}</p>
                            @endif
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="text-[10px] text-blue-600 dark:text-blue-500 font-medium">Dicari:</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-600 text-white dark:bg-blue-500">
                                    {{ $aiSearchContext['primary_medical_term'] }}
                                </span>
                                @if(!empty($aiSearchContext['organism']))
                                    <span class="text-[10px] text-blue-600 dark:text-blue-500 font-medium">Organisme:</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-700/40">
                                        {{ $aiSearchContext['organism'] }}
                                    </span>
                                @endif
                                @foreach(($aiSearchContext['alternatives'] ?? []) as $alt)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 border border-blue-200 dark:border-blue-700/40">
                                        {{ $alt }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Banner AI Fallback (term alternatif digunakan) --}}
            @if ($usedAiFallback && !empty($results))
                <div class="px-4 py-3 rounded-xl border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-900/15 flex items-center gap-3">
                    <flux:icon name="arrow-path" class="w-4 h-4 text-amber-500 shrink-0" />
                    <p class="text-xs text-amber-800 dark:text-amber-300 leading-snug">
                        Tidak ada hasil untuk term utama. Menampilkan hasil menggunakan sinonim alternatif:
                        <strong class="text-amber-900 dark:text-amber-200">"{{ $aiFallbackTerm }}"</strong>
                    </p>
                </div>
            @endif

            @if (!empty($results))
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($results as $source => $data)
                        <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
                            <div
                                class="flex items-center justify-between px-4 py-3 bg-zinc-50 dark:bg-primary-dark-900/50 border-b border-zinc-200 dark:border-primary-dark-700">
                                <span class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">
                                    {{ $this->sourceLabel($source) }}
                                </span>
                                @if (isset($data['error']))
                                    <flux:badge color="yellow" size="sm">Tidak tersedia</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ count($data['items']) }} hasil
                                    </flux:badge>
                                @endif
                            </div>

                            @if (isset($data['error']))
                                <div
                                    class="px-4 py-4 flex items-center gap-2 text-sm text-zinc-400 dark:text-primary-dark-500">
                                    <flux:icon name="exclamation-circle" class="w-4 h-4 shrink-0" />
                                    {{ $data['error'] }}
                                </div>
                            @elseif (empty($data['items']))
                                <div
                                    class="px-4 py-8 flex flex-col items-center justify-center text-zinc-400 dark:text-primary-dark-500">
                                    <flux:icon name="magnifying-glass" class="w-8 h-8 mb-2" />
                                    <p class="text-sm">Tidak ada hasil ditemukan.</p>
                                </div>
                            @else
                                <x-organisms.table>
                                    <x-slot:headings>
                                        <x-atoms.table-heading class="w-32">Kode</x-atoms.table-heading>
                                        <x-atoms.table-heading>Nama / Display</x-atoms.table-heading>
                                        <x-atoms.table-heading align="right" class="w-16">Skor</x-atoms.table-heading>
                                    </x-slot:headings>
                                    
                                    @foreach ($data['items'] as $item)
                                        <x-molecules.table-row>
                                            <x-atoms.table-cell>
                                                <flux:badge color="blue" size="sm">{{ $item['code'] }}</flux:badge>
                                            </x-atoms.table-cell>
                                            <x-atoms.table-cell class="text-zinc-800 dark:text-primary-dark-200">
                                                {{ $item['display'] }}
                                                @if (!empty($item['extra']) && $item['extra'] !== $item['display'])
                                                    <div class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-0.5 italic">{{ $item['extra'] }}</div>
                                                @endif
                                            </x-atoms.table-cell>
                                            <x-atoms.table-cell align="right">
                                                @if(isset($item['score']))
                                                    @php
                                                        $sc = round($item['score']);
                                                        $scColor = $sc >= 75 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                                 : ($sc >= 45 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                                                 : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400');
                                                    @endphp
                                                    <span class="inline-flex px-1.5 py-0.5 text-[10px] font-bold rounded {{ $scColor }}">{{ $sc }}%</span>
                                                @endif
                                            </x-atoms.table-cell>
                                        </x-molecules.table-row>
                                    @endforeach
                                </x-organisms.table>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- ======================== TAB: TERJEMAHAN AI ======================== --}}
    @if ($activeTab === 'translate')
        <div class="flex flex-col gap-4" key="translate">

            {{-- Peringatan jika belum dikonfigurasi --}}
            @if (!$this->isAiConfigured())
                <div
                    class="p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                    <div class="flex items-start gap-3">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
                        <div>
                            <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">AI belum dikonfigurasi
                            </h3>
                            <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                Atur provider AI di halaman
                                <a href="{{ route('configuration.connectivity') }}?tab=ai" wire:navigate
                                    class="font-medium underline hover:no-underline">
                                    Pengaturan → Konektivitas → AI Provider
                                </a>.
                            </p>
                        </div>
                    </div>
                </div>
            @else
                {{-- Info provider aktif --}}
                @php $activeProvider = ConfigurationHelper::get('ai.provider', 'ollama'); @endphp
                <div
                    class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-800 border border-zinc-200 dark:border-primary-dark-700 flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-primary-dark-400">
                        <flux:icon name="check-circle" class="w-4 h-4 text-green-500" />
                        Provider aktif:
                        <span class="font-medium text-zinc-800 dark:text-primary-dark-200">
                            {{ match ($activeProvider) {
                                'ollama' => 'Ollama (' . ConfigurationHelper::get('ai.ollama_model', 'llama3') . ')',
                                'claude' => 'Claude (' . ConfigurationHelper::get('ai.claude_model', 'claude-sonnet-4-6') . ')',
                                'openai' => 'OpenAI (' . ConfigurationHelper::get('ai.openai_model', 'gpt-4o') . ')',
                                'gemini' => 'Gemini (' . ConfigurationHelper::get('ai.gemini_model', 'gemini-2.5-flash') . ')',
                                'grok'   => 'Grok (' . ConfigurationHelper::get('ai.grok_model', 'grok-2-latest') . ')',
                                default  => strtoupper($activeProvider),
                            } }}
                        </span>
                    </div>
                    <a href="{{ route('configuration.connectivity') }}?tab=ai" wire:navigate
                        class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                        Ubah pengaturan →
                    </a>
                </div>
            @endif

            {{-- Input + Filter + Tombol --}}
            <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
                <div class="p-4 space-y-4">
                    <div>
                        <flux:label>Teks Klinis</flux:label>
                        <flux:textarea wire:model="aiQuery" rows="3"
                            placeholder="Masukkan teks klinis, contoh: pasien mengeluh sesak napas mendadak disertai nyeri dada kiri dan keringat dingin..." />
                        @error('aiQuery')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <flux:label>Sumber Terminologi</flux:label>
                            <div class="flex gap-3">
                                <x-atoms.button wire:click="selectAllSources" class="text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium">Semua</x-atoms.button>
                                <span class="text-zinc-300 dark:text-zinc-600">|</span>
                                <x-atoms.button wire:click="clearAllSources" class="text-xs text-zinc-400 dark:text-primary-dark-500 hover:underline">Hapus</x-atoms.button>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (array_keys($sourceColors) as $src)
                                @php $isActive = in_array($src, $selectedSources); @endphp
                                <x-atoms.button type="button"
                                    wire:click="toggleSource('{{ $src }}')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-xs font-medium transition-all duration-150
                                           {{ $isActive ? $sourceColors[$src]['active'] : $sourceColors[$src]['inactive'] }}">
                                    @if($isActive)
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    @endif
                                    {{ $this->sourceLabel($src) }}
                                </x-atoms.button>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-atoms.button wire:click="translate" variant="primary" icon="sparkles"
                            :disabled="!$this->isAiConfigured()">
                            Terjemahkan dengan AI
                        </x-atoms.button>
                        <span wire:loading wire:target="translate"
                            class="text-sm text-zinc-500 dark:text-primary-dark-400">Memproses...</span>
                    </div>
                </div>
            </div>

            {{-- Error AI --}}
            @if ($aiError)
                <div class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                    <div class="flex items-start gap-3">
                        <flux:icon name="x-circle" class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0" />
                        <div>
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Terjadi kesalahan</h3>
                            <p class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $aiError }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Hasil Terjemahan AI --}}
            @if ($aiResult)
                {{-- Interpretasi --}}
                <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <div class="flex items-start gap-3">
                        <flux:icon name="sparkles"
                            class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                        <div class="flex-1">
                            <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">Interpretasi AI</h3>
                            <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                {{ $aiResult['interpretation'] ?? '' }}
                            </p>
                            @if (!empty($aiResult['clinical_terms']))
                                <div class="flex flex-wrap gap-1.5 mt-3">
                                    @foreach ($aiResult['clinical_terms'] as $term)
                                        <flux:badge color="blue" size="sm">{{ $term }}</flux:badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Saran per sumber --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($selectedSources as $source)
                        @php $items = $aiResult['suggestions'][$source] ?? []; @endphp
                        <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
                            <div
                                class="flex items-center justify-between px-4 py-3 bg-zinc-50 dark:bg-primary-dark-900/50 border-b border-zinc-200 dark:border-primary-dark-700">
                                <span class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">
                                    {{ $this->sourceLabel($source) }}
                                </span>
                                <flux:badge color="{{ count($items) > 0 ? 'green' : 'zinc' }}" size="sm">
                                    {{ count($items) }} saran
                                </flux:badge>
                            </div>

                            @if (empty($items))
                                <div
                                    class="px-4 py-6 flex flex-col items-center justify-center text-zinc-400 dark:text-primary-dark-500">
                                    <flux:icon name="minus-circle" class="w-6 h-6 mb-1" />
                                    <p class="text-xs">Tidak ada saran</p>
                                </div>
                            @else
                                <x-organisms.table>
                                    <x-slot:headings>
                                        <x-atoms.table-heading class="w-32">Kode</x-atoms.table-heading>
                                        <x-atoms.table-heading>Keterangan</x-atoms.table-heading>
                                    </x-slot:headings>
                                    
                                    @foreach ($items as $item)
                                        <x-molecules.table-row>
                                            <x-atoms.table-cell>
                                                <flux:badge
                                                    color="{{ $item['unverified'] ?? false ? 'yellow' : 'green' }}"
                                                    size="sm">
                                                    {{ $item['code'] }}
                                                </flux:badge>
                                            </x-atoms.table-cell>
                                            <x-atoms.table-cell>
                                                @if (!empty($item['display']))
                                                    <div class="text-sm text-zinc-800 dark:text-primary-dark-200">
                                                        {{ $item['display'] }}</div>
                                                @endif
                                                @if (!empty($item['reason']))
                                                    <div
                                                        class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">
                                                        {{ $item['reason'] }}</div>
                                                @endif
                                                @if ($item['unverified'] ?? false)
                                                    <div class="text-xs text-amber-500 mt-0.5">Belum terverifikasi di
                                                        database lokal</div>
                                                @endif
                                            </x-atoms.table-cell>
                                        </x-molecules.table-row>
                                    @endforeach
                                </x-organisms.table>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

</div>
