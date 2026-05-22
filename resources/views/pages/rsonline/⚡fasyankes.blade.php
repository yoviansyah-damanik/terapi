<?php

use App\Services\RsOnline\RsOnlineService;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app', ['title' => 'RS Online'])] class extends Component {
    public string $activeTab = 'fasyankes';

    // ---- Data list ----
    public array $fasynakesList = [];
    public array $sdmList = [];
    public array $apdList = [];
    public ?string $listError = null;

    // ---- Form state ----
    public string $formMode = '';
    public array $form = [];
    public ?array $formResult = null;

    /** Field default tiap tab */
    private function defaultForm(string $tab): array
    {
        return match ($tab) {
            'fasyankes' => [
                'tgl_update' => now()->format('Y-m-d'),
                'tt_total' => '',
                'tt_terisi' => '',
                'tt_covid' => '',
                'tt_covid_terisi' => '',
            ],
            'sdm' => [
                'tgl_update' => now()->format('Y-m-d'),
                'jenis_sdm' => '',
                'jumlah' => '',
            ],
            'apd' => [
                'tgl_update' => now()->format('Y-m-d'),
                'jenis_apd' => '',
                'jumlah' => '',
                'satuan' => '',
            ],
            default => [],
        };
    }

    /** Ganti tab aktif */
    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->formMode = '';
        $this->formResult = null;
        $this->listError = null;
    }

    // =================== GET ===================

    public function loadFasyankes(): void
    {
        $this->listError = null;
        $service = new RsOnlineService();

        if (!$service->isConfigured()) {
            $this->listError = 'Konfigurasi RS Online belum lengkap.';
            return;
        }

        $result = $service->getFasyankes();
        $this->fasynakesList = $result['success'] ? (array) ($result['data'] ?? []) : [];
        if (!$result['success']) {
            $this->listError = $result['message'];
        }
    }

    public function loadSdm(): void
    {
        $this->listError = null;
        $service = new RsOnlineService();

        if (!$service->isConfigured()) {
            $this->listError = 'Konfigurasi RS Online belum lengkap.';
            return;
        }

        $result = $service->getSdm();
        $this->sdmList = $result['success'] ? (array) ($result['data'] ?? []) : [];
        if (!$result['success']) {
            $this->listError = $result['message'];
        }
    }

    public function loadApd(): void
    {
        $this->listError = null;
        $service = new RsOnlineService();

        if (!$service->isConfigured()) {
            $this->listError = 'Konfigurasi RS Online belum lengkap.';
            return;
        }

        $result = $service->getApd();
        $this->apdList = $result['success'] ? (array) ($result['data'] ?? []) : [];
        if (!$result['success']) {
            $this->listError = $result['message'];
        }
    }

    // =================== Form ===================

    public function openForm(?array $row = null): void
    {
        $this->form = $row ? array_merge($this->defaultForm($this->activeTab), $row) : $this->defaultForm($this->activeTab);
        $this->formMode = $row ? 'edit' : 'add';
        $this->formResult = null;
    }

    public function submit(): void
    {
        $service = new RsOnlineService();

        $result = match ($this->activeTab) {
            'fasyankes' => $this->formMode === 'edit' ? $service->updateFasyankes($this->form) : $service->kirimFasyankes($this->form),
            'sdm' => $this->formMode === 'edit' ? $service->updateSdm($this->form) : $service->kirimSdm($this->form),
            'apd' => $this->formMode === 'edit' ? $service->updateApd($this->form) : $service->kirimApd($this->form),
            default => ['success' => false, 'message' => 'Tab tidak dikenal.'],
        };

        $this->formResult = $result;

        if ($result['success']) {
            $this->formMode = '';
            $this->reloadActive();
            $this->dispatch('toast', type: 'success', message: 'Data berhasil disimpan.');
        } else {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $result['message']);
        }
    }

    public function deleteRow(array $data): void
    {
        $service = new RsOnlineService();

        $result = match ($this->activeTab) {
            'fasyankes' => $service->deleteFasyankes($data),
            'sdm' => $service->deleteSdm($data),
            'apd' => $service->deleteApd($data),
            default => ['success' => false, 'message' => 'Tab tidak dikenal.'],
        };

        if ($result['success']) {
            $this->reloadActive();
            $this->dispatch('toast', type: 'success', message: 'Data berhasil dihapus.');
        } else {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $result['message']);
        }
    }

    public function cancelForm(): void
    {
        $this->formMode = '';
        $this->formResult = null;
    }

    /** Reload data tab aktif */
    private function reloadActive(): void
    {
        match ($this->activeTab) {
            'fasyankes' => $this->loadFasyankes(),
            'sdm' => $this->loadSdm(),
            'apd' => $this->loadApd(),
            default => null,
        };
    }
};
?>

<div>
    <x-ui.page-header title="Data Fasyankes RS Online"
        subtitle="Kelola data tempat tidur, SDM, dan APD di RS Online Kemenkes">
        <x-slot:actions>
            <a wire:navigate href="{{ route('rsonline.configuration') }}">
                <x-atoms.button icon="cog-6-tooth" variant="ghost" size="sm">Konfigurasi</x-atoms.button>
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="bg-white dark:bg-primary-dark-800 rounded-xl shadow border border-zinc-200 dark:border-primary-dark-700">
        {{-- Tab navigasi --}}
        <x-molecules.tabs>
    
                @foreach (['fasyankes' => 'Tempat Tidur', 'sdm' => 'SDM', 'apd' => 'APD/Alkes'] as $tab => $label)
                    <x-atoms.tab-item wire:click="switchTab('{{ $tab }}')">{{ $label }}</x-atoms.tab-item>
                @endforeach
            
    </x-molecules.tabs>

        <div class="p-6">
            {{-- Error --}}
            @if ($listError)
                <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4 mb-4">
                    <div class="flex items-start gap-3">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $listError }}</p>
                    </div>
                </div>
            @endif

            {{-- Form --}}
            @if ($formMode)
                <div
                    class="mb-6 bg-zinc-50 dark:bg-primary-dark-900/50 rounded-lg border border-zinc-200 dark:border-primary-dark-700 p-5">
                    <h4 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-200 mb-4">
                        {{ $formMode === 'edit' ? 'Edit Data' : 'Tambah Data Baru' }}
                    </h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {{-- Tanggal update (semua tab) --}}
                        <div>
                            <flux:label>Tanggal Update</flux:label>
                            <flux:input wire:model="form.tgl_update" type="date" class="mt-1" />
                        </div>

                        {{-- Tab Fasyankes (Tempat Tidur) --}}
                        @if ($activeTab === 'fasyankes')
                            <div>
                                <flux:label>Total TT</flux:label>
                                <flux:input wire:model="form.tt_total" type="number" min="0" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>TT Terisi</flux:label>
                                <flux:input wire:model="form.tt_terisi" type="number" min="0" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>TT Covid</flux:label>
                                <flux:input wire:model="form.tt_covid" type="number" min="0" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>TT Covid Terisi</flux:label>
                                <flux:input wire:model="form.tt_covid_terisi" type="number" min="0"
                                    class="mt-1" />
                            </div>
                        @endif

                        {{-- Tab SDM --}}
                        @if ($activeTab === 'sdm')
                            <div>
                                <flux:label>Jenis SDM</flux:label>
                                <flux:input wire:model="form.jenis_sdm" placeholder="Kode jenis SDM" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Jumlah</flux:label>
                                <flux:input wire:model="form.jumlah" type="number" min="0" class="mt-1" />
                            </div>
                        @endif

                        {{-- Tab APD --}}
                        @if ($activeTab === 'apd')
                            <div>
                                <flux:label>Jenis APD</flux:label>
                                <flux:input wire:model="form.jenis_apd" placeholder="Kode jenis APD" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Jumlah</flux:label>
                                <flux:input wire:model="form.jumlah" type="number" min="0" class="mt-1" />
                            </div>
                            <div>
                                <flux:label>Satuan</flux:label>
                                <flux:input wire:model="form.satuan" placeholder="Mis: Unit, Buah" class="mt-1" />
                            </div>
                        @endif
                    </div>

                    @if ($formResult && !$formResult['success'])
                        <div
                            class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-sm text-red-700 dark:text-red-400">
                            {{ $formResult['message'] }}
                        </div>
                    @endif

                    <div class="flex gap-3 mt-5 pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                        <x-atoms.button wire:click="submit" variant="primary" wire:loading.attr="disabled"
                            wire:target="submit">
                            <span wire:loading.remove
                                wire:target="submit">{{ $formMode === 'edit' ? 'Update' : 'Kirim' }}</span>
                            <span wire:loading wire:target="submit">Memproses...</span>
                        </x-atoms.button>
                        <x-atoms.button wire:click="cancelForm" variant="ghost">Batal</x-atoms.button>
                    </div>
                </div>
            @endif

            {{-- Toolbar --}}
            <div class="flex items-center justify-between mb-4">
                <x-atoms.button
                    wire:click="{{ $activeTab === 'fasyankes' ? 'loadFasyankes' : ($activeTab === 'sdm' ? 'loadSdm' : 'loadApd') }}"
                    icon="arrow-path" variant="ghost" size="sm" wire:loading.attr="disabled">
                    Muat Data
                </x-atoms.button>
                @if (!$formMode)
                    <x-atoms.button wire:click="openForm" icon="plus" variant="primary" size="sm">
                        Tambah Data
                    </x-atoms.button>
                @endif
            </div>

            {{-- Tabel --}}
            @php
                $currentList = match ($activeTab) {
                    'fasyankes' => $fasynakesList,
                    'sdm' => $sdmList,
                    'apd' => $apdList,
                    default => [],
                };
            @endphp

            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-primary-dark-700">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 dark:bg-primary-dark-900/50">
                        @if ($activeTab === 'fasyankes')
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500">Tgl Update</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-zinc-500">Total TT</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-zinc-500">TT Terisi</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-zinc-500">TT Covid</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-zinc-500">TT Covid Terisi
                                </th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500">Aksi</th>
                            </tr>
                        @elseif ($activeTab === 'sdm')
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500">Tgl Update</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500">Jenis SDM</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-zinc-500">Jumlah</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500">Aksi</th>
                            </tr>
                        @else
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500">Tgl Update</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500">Jenis APD</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-zinc-500">Jumlah</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500">Satuan</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500">Aksi</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                        @forelse ($currentList as $row)
                            @php $row = (array) $row; @endphp
                            <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/30">
                                @if ($activeTab === 'fasyankes')
                                    <td class="px-4 py-2.5 text-zinc-700 dark:text-primary-dark-300">
                                        {{ $row['tgl_update'] ?? '-' }}</td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-zinc-800 dark:text-primary-dark-200">
                                        {{ $row['tt_total'] ?? '-' }}</td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-zinc-800 dark:text-primary-dark-200">
                                        {{ $row['tt_terisi'] ?? '-' }}</td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-zinc-800 dark:text-primary-dark-200">
                                        {{ $row['tt_covid'] ?? '-' }}</td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-zinc-800 dark:text-primary-dark-200">
                                        {{ $row['tt_covid_terisi'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex gap-1">
                                            <x-atoms.button wire:click="openForm({{ json_encode($row) }})"
                                                icon="pencil" size="xs" variant="ghost" />
                                            <x-atoms.button
                                                wire:click="deleteRow({{ json_encode(['tgl_update' => $row['tgl_update'] ?? '']) }})"
                                                wire:confirm="Hapus data ini?" icon="trash" size="xs"
                                                variant="ghost" class="text-red-500 hover:text-red-700" />
                                        </div>
                                    </td>
                                @elseif ($activeTab === 'sdm')
                                    <td class="px-4 py-2.5 text-zinc-700 dark:text-primary-dark-300">
                                        {{ $row['tgl_update'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5 text-zinc-800 dark:text-primary-dark-200">
                                        {{ $row['jenis_sdm'] ?? '-' }}</td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-zinc-800 dark:text-primary-dark-200">
                                        {{ $row['jumlah'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex gap-1">
                                            <x-atoms.button wire:click="openForm({{ json_encode($row) }})"
                                                icon="pencil" size="xs" variant="ghost" />
                                            <x-atoms.button
                                                wire:click="deleteRow({{ json_encode(['tgl_update' => $row['tgl_update'] ?? '', 'jenis_sdm' => $row['jenis_sdm'] ?? '']) }})"
                                                wire:confirm="Hapus data ini?" icon="trash" size="xs"
                                                variant="ghost" class="text-red-500 hover:text-red-700" />
                                        </div>
                                    </td>
                                @else
                                    <td class="px-4 py-2.5 text-zinc-700 dark:text-primary-dark-300">
                                        {{ $row['tgl_update'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5 text-zinc-800 dark:text-primary-dark-200">
                                        {{ $row['jenis_apd'] ?? '-' }}</td>
                                    <td
                                        class="px-4 py-2.5 text-right font-mono text-zinc-800 dark:text-primary-dark-200">
                                        {{ $row['jumlah'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5 text-zinc-600 dark:text-primary-dark-400">
                                        {{ $row['satuan'] ?? '-' }}</td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex gap-1">
                                            <x-atoms.button wire:click="openForm({{ json_encode($row) }})"
                                                icon="pencil" size="xs" variant="ghost" />
                                            <x-atoms.button
                                                wire:click="deleteRow({{ json_encode(['tgl_update' => $row['tgl_update'] ?? '', 'jenis_apd' => $row['jenis_apd'] ?? '']) }})"
                                                wire:confirm="Hapus data ini?" icon="trash" size="xs"
                                                variant="ghost" class="text-red-500 hover:text-red-700" />
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6"
                                    class="px-4 py-8 text-center text-zinc-400 dark:text-primary-dark-600 text-sm">
                                    Belum ada data. Klik "Muat Data" untuk mengambil dari API.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
