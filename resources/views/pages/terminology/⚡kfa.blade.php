<?php

use App\Services\SatuSehat\KfaService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('KFA - Kamus Farmasi & Alkes')] class extends Component {
    // ── Tab aktif ─────────────────────────────────────────────────────
    public string $activeTab = 'farmasi'; // farmasi | alkes_produk | alkes_template

    // ── Filter bersama ────────────────────────────────────────────────
    public string $search = '';
    public int $page = 1;
    public int $size = 10;

    // ── Hasil pencarian ───────────────────────────────────────────────
    public array $results = [];
    public int $total = 0;
    public int $totalPage = 1;

    // ── Status ────────────────────────────────────────────────────────
    public bool $loading = false;
    public ?string $error = null;

    // ── Detail item ───────────────────────────────────────────────────
    public int $selectedItemIndex = -1;
    public bool $showDetailModal = false;

    // ── Debug API ──────────────────────────────────────────────────────
    // Disimpan sebagai string JSON agar tidak menyebabkan masalah serialisasi
    // Livewire saat round-trip (array besar bisa hilang saat ganti tab)
    public string $lastRequestMethod = '';
    public string $lastRequestUrl = '';
    public string $lastRequestJson = ''; // request params/body (pretty JSON)
    public string $lastResponseJson = ''; // response body (pretty JSON)
    public int $lastResponseStatus = 0;
    public bool $showApiModal = false;

    public function showDetail(int $index): void
    {
        $this->selectedItemIndex = $index;
        $this->showDetailModal = true;
    }

    public function gotoPage(int $p): void
    {
        $this->page = max(1, min($p, $this->totalPage));
        $this->search();
    }

    /** Menghasilkan array nomor halaman untuk paginasi (null = ellipsis). */
    public function getPageNumbers(): array
    {
        $total = $this->totalPage;
        $current = $this->page;

        if ($total <= 7) {
            return range(1, $total);
        }

        if ($current <= 4) {
            return [...range(1, 5), null, $total];
        }

        if ($current >= $total - 3) {
            return [1, null, ...range($total - 4, $total)];
        }

        return [1, null, $current - 1, $current, $current + 1, null, $total];
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->search();
    }

    public function updatedActiveTab(): void
    {
        $this->page = 1;
        $this->search = '';
        $this->results = [];
        $this->total = 0;
        $this->error = null;

        $this->selectedItemIndex = -1;
        $this->showDetailModal = false;
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->search();
        }
    }

    public function nextPage(): void
    {
        if ($this->page < $this->totalPage) {
            $this->page++;
            $this->search();
        }
    }

    /** Eksekusi pencarian ke API KFA sesuai tab aktif */
    public function search(): void
    {
        $this->loading = true;
        $this->error = null;

        try {
            $service = app(KfaService::class);

            match ($this->activeTab) {
                'farmasi' => $this->handleFarmasiResult($service->searchFarmasi($this->search, $this->page, $this->size)),
                'alkes_produk' => $this->handleAlkesResult($service->searchAlkesProducts($this->search, $this->page, $this->size)),
                'alkes_template' => $this->handleAlkesResult($service->searchAlkesTemplates($this->search, $this->page, $this->size)),
            };

            $req = $service->getLastRequestInfo();
            $res = $service->getLastResponseInfo();

            $this->lastRequestMethod = $req['method'] ?? '';
            $this->lastRequestUrl = $req['url'] ?? '';
            $this->lastRequestJson = json_encode(array_filter(['params' => $req['params'] ?? null, 'body' => $req['body'] ?? null]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->lastResponseStatus = $res['status'] ?? 0;
            $this->lastResponseJson = json_encode($res['body'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Exception $e) {
            $this->error = 'Gagal terhubung ke API KFA: ' . $e->getMessage();
            $this->results = [];
            $this->total = 0;
        } finally {
            $this->loading = false;
        }
    }

    private function handleFarmasiResult(array $data): void
    {
        $this->results = $data['items']['data'] ?? [];
        $this->total = $data['total'] ?? 0;
        $this->totalPage = (int) ceil($this->total / $this->size) ?: 1;
    }

    private function handleAlkesResult(array $data): void
    {
        // KFA v3: data ada di meta.data, total halaman dihitung dari item_count / limit
        $meta = $data['meta'] ?? [];
        $page = $meta['page'] ?? [];
        $itemCount = (int) ($meta['item_count'] ?? 0);
        $limit = (int) ($page['limit'] ?? $this->size);

        $this->results = $data['data'] ?? [];
        $this->total = $itemCount;
        $this->totalPage = $itemCount > 0 && $limit > 0 ? (int) ceil($itemCount / $limit) : 1;
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="KFA — Kamus Farmasi & Alkes"
        subtitle="Pencarian produk farmasi dan alat kesehatan via API Satu Sehat (KFA v2 & v3)." />

    {{-- Tab Nav --}}
    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-primary-dark-700 mb-4">
        <div class="flex gap-0">
            @foreach ([
                'farmasi' => 'Farmasi (KFA v2)',
                'alkes_produk' => 'Alkes — Produk (KFA v3)',
                'alkes_template' => 'Alkes — Template',
            ] as $tab => $label)
                <x-atoms.tab-item
                    :active="$activeTab === $tab"
                    wire:click="$set('activeTab', '{{ $tab }}')">{{ $label }}</x-atoms.tab-item>
            @endforeach
        </div>
        <x-atoms.button wire:click="$set('showApiModal', true)" icon="code-bracket" variant="ghost" size="sm"
            :disabled="$lastRequestUrl === ''" title="Tampilkan API Request & Response">
            Request & Response
        </x-atoms.button>
    </div>

    <x-organisms.data-panel title="Data KFA" subtitle="Hasil pencarian dari API KFA Satu Sehat">
        <x-slot:filter>
            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="flex-1">
                    <flux:input wire:model.live.debounce.500ms="search"
                        placeholder="{{ $activeTab === 'farmasi' ? 'Cari nama obat, kode KFA, NIE...' : 'Cari nama alat kesehatan...' }}"
                        icon="magnifying-glass" clearable />
                </div>
                <x-atoms.button wire:click="search" icon="arrow-path">Cari</x-atoms.button>
            </div>
        </x-slot:filter>

        @if ($loading)
            <div class="flex items-center justify-center py-16 text-zinc-400 dark:text-primary-dark-500">
                <flux:icon name="arrow-path" class="w-5 h-5 mr-2 animate-spin" />
                Memuat data dari API KFA...
            </div>
        @elseif (count($results) > 0)
            <x-organisms.table>
                <x-slot:headings>
                    @if ($activeTab === 'farmasi')
                        <x-atoms.table-heading>Kode KFA</x-atoms.table-heading>
                        <x-atoms.table-heading>Nama Produk</x-atoms.table-heading>
                        <x-atoms.table-heading>Jenis</x-atoms.table-heading>
                        <x-atoms.table-heading>Harga Fix</x-atoms.table-heading>
                        <x-atoms.table-heading>Status</x-atoms.table-heading>
                    @elseif ($activeTab === 'alkes_produk')
                        <x-atoms.table-heading>Kode KFA</x-atoms.table-heading>
                        <x-atoms.table-heading>Nama Produk</x-atoms.table-heading>
                        <x-atoms.table-heading>NIE</x-atoms.table-heading>
                        <x-atoms.table-heading>Produsen</x-atoms.table-heading>
                        <x-atoms.table-heading>Harga Fix</x-atoms.table-heading>
                    @else
                        <x-atoms.table-heading>Kode KFA</x-atoms.table-heading>
                        <x-atoms.table-heading>Nama Template</x-atoms.table-heading>
                        <x-atoms.table-heading>Kategori</x-atoms.table-heading>
                        <x-atoms.table-heading>Kelas Risiko</x-atoms.table-heading>
                    @endif
                    <x-atoms.table-heading align="right"><span class="sr-only">Aksi</span></x-atoms.table-heading>
                </x-slot:headings>

                @foreach ($results as $item)
                    <x-molecules.table-row>
                        @if ($activeTab === 'farmasi')
                            <x-atoms.table-cell class="font-mono text-zinc-500 dark:text-primary-dark-400">
                                {{ $item['kfa_code'] ?? '-' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $item['name'] ?? '-' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="text-zinc-500 dark:text-primary-dark-400">
                                {{ $item['farmalkes_type']['name'] ?? 'Farmasi' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">
                                @if (!empty($item['fix_price']))
                                    Rp {{ number_format($item['fix_price'], 0, ',', '.') }}
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </x-atoms.table-cell>
                            <x-atoms.table-cell>
                                @if (isset($item['active']))
                                    <flux:badge size="sm" color="{{ $item['active'] ? 'green' : 'zinc' }}">
                                        {{ $item['active'] ? 'Aktif' : 'Nonaktif' }}
                                    </flux:badge>
                                @endif
                            </x-atoms.table-cell>
                        @elseif ($activeTab === 'alkes_produk')
                            <x-atoms.table-cell class="font-mono text-zinc-500 dark:text-primary-dark-400">
                                {{ $item['kfa_code'] ?? '-' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $item['display_name'] ?? ($item['name'] ?? '-') }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="text-zinc-500 dark:text-primary-dark-400">
                                {{ $item['nie'] ?? '-' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="text-zinc-500 dark:text-primary-dark-400">
                                {{ $item['manufacturer'] ?? '-' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="text-zinc-700 dark:text-primary-dark-300">
                                @if (!empty($item['fix_price']))
                                    Rp {{ number_format($item['fix_price'], 0, ',', '.') }}
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </x-atoms.table-cell>
                        @else
                            <x-atoms.table-cell class="font-mono text-zinc-500 dark:text-primary-dark-400">
                                {{ $item['kfa_code'] ?? '-' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="text-zinc-900 dark:text-primary-dark-100">
                                {{ $item['name'] ?? '-' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell class="text-zinc-500 dark:text-primary-dark-400">
                                {{ $item['kategori']['name'] ?? '-' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell>
                                @if (!empty($item['med_dev_kelas_risiko']))
                                    <flux:badge size="sm" color="blue">
                                        Kelas {{ $item['med_dev_kelas_risiko'] }}
                                    </flux:badge>
                                @else
                                    <span class="text-sm text-zinc-400">-</span>
                                @endif
                            </x-atoms.table-cell>
                        @endif
                        <x-atoms.table-cell align="right">
                            <x-atoms.button size="xs" variant="ghost" icon="eye"
                                wire:click="showDetail({{ $loop->index }})">Detail</x-atoms.button>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforeach
            </x-organisms.table>
        @elseif ($search !== '')
            <div class="flex flex-col items-center justify-center py-16 text-zinc-500 dark:text-primary-dark-400">
                <flux:icon name="magnifying-glass" class="w-10 h-10 mb-2 text-zinc-300 dark:text-primary-dark-600" />
                <p class="text-sm">Tidak ada hasil untuk "<span class="font-medium">{{ $search }}</span>".</p>
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-16 text-zinc-500 dark:text-primary-dark-400">
                <flux:icon name="magnifying-glass" class="w-10 h-10 mb-2 text-zinc-300 dark:text-primary-dark-600" />
                <p class="text-sm">Masukkan kata kunci dan tekan <span class="font-medium">Cari</span> untuk mulai
                    pencarian.</p>
            </div>
        @endif

        <x-slot:footer>
            @if (!$loading && count($results) > 0)
                @php $pageNumbers = $this->getPageNumbers(); @endphp
                <x-molecules.pagination
                    :page="$page"
                    :total-page="$totalPage"
                    :total="$total"
                    :page-numbers="$pageNumbers"
                    on-prev="previousPage"
                    on-next="nextPage"
                    on-goto="gotoPage"
                />
            @endif
        </x-slot:footer>
    </x-organisms.data-panel>

    {{-- Modal Detail Item --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="3xl" title="Detail Item">
        @if ($selectedItemIndex >= 0 && isset($results[$selectedItemIndex]))
            @php $item = $results[$selectedItemIndex]; @endphp
            <div class="space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-primary-dark-100">
                        {{ $item['display_name'] ?? ($item['name'] ?? 'Detail Item') }}
                    </h3>
                    <p class="mt-0.5 text-xs font-mono text-zinc-500 dark:text-primary-dark-400">
                        KFA: {{ $item['kfa_code'] ?? '-' }}
                        @if (!empty($item['nie']))
                            &nbsp;&middot;&nbsp; NIE: {{ $item['nie'] }}
                        @endif
                        @if (isset($item['active']))
                            &nbsp;&middot;&nbsp;
                            <flux:badge size="sm" color="{{ $item['active'] ? 'green' : 'zinc' }}"
                                class="inline-flex">
                                {{ $item['active'] ? 'Aktif' : 'Nonaktif' }}
                            </flux:badge>
                        @endif
                    </p>
                </div>

                {{-- Field utama dalam grid --}}
                @php
                    $fields = array_filter([
                        'Nama' => $item['name'] ?? null,
                        'Nama Dagang' => $item['nama_dagang'] ?? null,
                        'Display Name' => $item['display_name'] ?? null,
                        'Farmalkes Type' => $item['farmalkes_type']['name'] ?? null,
                        'Jenis' => isset($item['jenis']) ? $item['jenis']['name'] ?? null : null,
                        'Kategori' => isset($item['kategori']) ? $item['kategori']['name'] ?? null : null,
                        'Sub Kategori' => isset($item['sub_kategori']) ? $item['sub_kategori']['name'] ?? null : null,
                        'Sub Jenis' => isset($item['sub_jenis']) ? $item['sub_jenis']['name'] ?? null : null,
                        'Kelas Risiko' => isset($item['med_dev_kelas_risiko'])
                            ? 'Kelas ' . $item['med_dev_kelas_risiko']
                            : null,
                        'Klasifikasi Izin' => isset($item['klasifikasi_izin'])
                            ? $item['klasifikasi_izin']['name'] ?? null
                            : null,
                        'Produsen' => $item['manufacturer'] ?? null,
                        'Negara Produsen' => isset($item['manufacturer_country'])
                            ? $item['manufacturer_country']['name'] ?? null
                            : null,
                        'Registrar' => $item['registrar'] ?? null,
                        'Produksi' => $item['produksi_buatan'] ?? null,
                        'UoM' => $item['uom_name'] ?? null,
                        'Kode KBKI' => $item['kode_kbki'] ?? null,
                        'Kode LKPP' => $item['kode_lkpp'] ?? null,
                        'Harga Fix' =>
                            isset($item['fix_price']) && $item['fix_price'] > 0
                                ? 'Rp ' . number_format($item['fix_price'], 0, ',', '.')
                                : null,
                        'Score TKDN' => isset($item['score_tkdn']) ? $item['score_tkdn'] . '%' : null,
                        'Score BMP' => isset($item['score_bmp']) ? $item['score_bmp'] : null,
                        'BMHP' => isset($item['bmhp']) ? ($item['bmhp'] ? 'Ya' : 'Tidak') : null,
                        'Fornas' => isset($item['fornas']) ? ($item['fornas'] ? 'Ya' : 'Tidak') : null,
                        'Dapat Dibeli LKPP' => isset($item['dapat_dibeli_lkpp'])
                            ? ($item['dapat_dibeli_lkpp']
                                ? 'Ya'
                                : 'Tidak')
                            : null,
                        'Tayang LKPP' => isset($item['tayang_lkpp']) ? ($item['tayang_lkpp'] ? 'Ya' : 'Tidak') : null,
                        'Stok Wajib Yankes' => isset($item['stok_wajib_yankes'])
                            ? ($item['stok_wajib_yankes']
                                ? 'Ya'
                                : 'Tidak')
                            : null,
                        'Sinonim' => $item['synonyms'] ?? null,
                        'Diperbarui' => $item['updated_at'] ?? null,
                    ]);
                @endphp
                @if (count($fields) > 0)
                    <div class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                        @foreach ($fields as $label => $value)
                            <div class="flex gap-2 py-1.5 border-b border-zinc-100 dark:border-primary-dark-700/50">
                                <span
                                    class="text-xs text-zinc-400 dark:text-primary-dark-500 shrink-0 w-36">{{ $label }}</span>
                                <span
                                    class="text-xs text-zinc-800 dark:text-primary-dark-200 break-words">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Identifiers (alkes_produk) --}}
                @if (!empty($item['identifier_ids']))
                    <div>
                        <p
                            class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                            Identifiers</p>
                        <div class="space-y-2">
                            @foreach ($item['identifier_ids'] as $id)
                                <div
                                    class="rounded-lg px-3 py-2 bg-zinc-50 dark:bg-primary-dark-900/50 ring-1 ring-zinc-200 dark:ring-primary-dark-700">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <span
                                                class="text-xs font-semibold text-zinc-700 dark:text-primary-dark-300">{{ $id['code'] ?? '-' }}</span>
                                            <span
                                                class="ml-2 text-xs text-zinc-400 dark:text-primary-dark-500">{{ $id['source_name'] ?? '' }}</span>
                                        </div>
                                        @if (!empty($id['use']))
                                            <flux:badge size="sm"
                                                color="{{ $id['use'] === 'official' ? 'blue' : 'zinc' }}"
                                                class="shrink-0">{{ $id['use'] }}</flux:badge>
                                        @endif
                                    </div>
                                    @if (!empty($id['name']))
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-primary-dark-400">
                                            {{ $id['name'] }}
                                        </p>
                                    @endif
                                    @if (!empty($id['url']))
                                        <a href="{{ $id['url'] }}" target="_blank"
                                            class="mt-1 text-xs text-primary-600 dark:text-primary-400 hover:underline break-all block">{{ $id['url'] }}</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Product Template info (alkes_produk) --}}
                @if (!empty($item['product_template']))
                    @php $tpl = $item['product_template']; @endphp
                    <div>
                        <p
                            class="mb-2 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                            Product Template</p>
                        <div
                            class="rounded-lg px-3 py-2 bg-zinc-50 dark:bg-primary-dark-900/50 ring-1 ring-zinc-200 dark:ring-primary-dark-700 text-xs text-zinc-700 dark:text-primary-dark-300">
                            <span class="font-mono">{{ $tpl['kfa_code'] ?? '-' }}</span>
                            &nbsp;—&nbsp;{{ $tpl['name'] ?? '' }}
                            @if (!empty($tpl['synonyms']))
                                <br><span class="text-zinc-400 dark:text-primary-dark-500">Sinonim:
                                    {{ $tpl['synonyms'] }}</span>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Raw JSON (untuk field yang tidak ditampilkan di atas) --}}
                <details class="group">
                    <summary
                        class="cursor-pointer text-xs font-semibold tracking-wider uppercase text-zinc-400 dark:text-primary-dark-500 hover:text-zinc-600 dark:hover:text-primary-dark-300 select-none">
                        Raw JSON (seluruh data)
                    </summary>
                    <div class="mt-2">
                        <x-atoms.code-block language="json" maxHeight="max-h-80">{{ json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </div>
                </details>

                </div>
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-16 text-zinc-500 dark:text-primary-dark-400">
                <flux:icon name="exclamation-circle" class="w-10 h-10 mb-2 text-zinc-300 dark:text-primary-dark-600" />
                <p class="text-sm">Tidak ada detail item yang dipilih.</p>
            </div>
        @endif

        <x-slot:footer>
            <div class="flex justify-end w-full">
                <x-atoms.button wire:click="$set('showDetailModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal API Request & Response --}}
    <x-organisms.modal wire:model="showApiModal" maxWidth="3xl" title="API Request & Response"
        description="Detail request terakhir yang dikirim ke API KFA Satu Sehat.">
        <div class="space-y-4">
            {{-- Request --}}
            <div>
                <p
                    class="text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400 mb-2">
                    Request
                </p>
                <div class="rounded-lg ring-1 ring-zinc-200 dark:ring-primary-dark-700 overflow-hidden">
                    {{-- Method + URL --}}
                    <div
                        class="flex items-start gap-3 px-4 py-3 bg-zinc-50 dark:bg-primary-dark-900/50 border-b border-zinc-200 dark:border-primary-dark-700">
                        <span @class([
                            'shrink-0 px-2 py-0.5 text-xs font-bold rounded font-mono',
                            'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' =>
                                $lastRequestMethod === 'GET',
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' =>
                                $lastRequestMethod === 'POST',
                        ])>{{ $lastRequestMethod ?: 'GET' }}</span>
                        <code
                            class="text-xs text-zinc-700 dark:text-primary-dark-300 break-all font-mono leading-relaxed">
                            {{ $lastRequestUrl ?: '-' }}
                        </code>
                    </div>
                    {{-- Body / Params --}}
                    @if ($lastRequestJson && $lastRequestJson !== '[]' && $lastRequestJson !== '{}')
                        <div class="px-4 py-3">
                            <p class="text-xs font-medium text-zinc-400 dark:text-primary-dark-500 mb-1">
                                {{ $lastRequestMethod === 'POST' ? 'Request Body' : 'Query Params' }}
                            </p>
                            <pre class="text-xs text-zinc-700 dark:text-primary-dark-300 overflow-x-auto whitespace-pre-wrap break-words">{{ $lastRequestJson }}</pre>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Response --}}
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <p class="text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400">
                        Response
                    </p>
                    @if ($lastResponseStatus > 0)
                        <flux:badge size="sm"
                            color="{{ $lastResponseStatus >= 200 && $lastResponseStatus < 300 ? 'green' : 'red' }}">
                            HTTP {{ $lastResponseStatus }}
                        </flux:badge>
                    @endif
                </div>
                <div class="rounded-lg ring-1 ring-zinc-200 dark:ring-primary-dark-700 overflow-hidden">
                    <pre
                        class="text-xs text-zinc-700 dark:text-primary-dark-300 p-4 overflow-auto max-h-96 whitespace-pre-wrap break-words bg-zinc-50 dark:bg-primary-dark-900/50">{{ $lastResponseJson ?: '—' }}</pre>
                </div>
            </div>

            <x-slot:footer>
                <div class="flex justify-end w-full">
                    <x-atoms.button wire:click="$set('showApiModal', false)" variant="ghost">Tutup</x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>
</div>
