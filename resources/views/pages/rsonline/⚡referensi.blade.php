<?php

use App\Services\RsOnline\RsOnlineService;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app', ['title' => 'RS Online'])] class extends Component {
    public string $activeTab = 'status_rawat';
    public array $data = [];
    public bool $loading = false;
    public ?string $error = null;

    /** Ambil data referensi untuk tab yang aktif */
    public function loadReferensi(?string $type = null): void
    {
        $type = $type ?? $this->activeTab;
        $this->activeTab = $type;
        $this->data = [];
        $this->error = null;

        $service = new RsOnlineService();

        if (!$service->isConfigured()) {
            $this->error = 'Konfigurasi RS Online belum lengkap. Silakan atur di halaman Konfigurasi.';
            return;
        }

        $result = $service->getReferensi($type);

        if ($result['success']) {
            $raw = $result['data'];
            $this->data = is_array($raw) ? (array_is_list($raw) ? $raw : [$raw]) : [];
        } else {
            $this->error = $result['message'];
        }
    }

    public function with(): array
    {
        return ['types' => RsOnlineService::REFERENSI_TYPES];
    }
};
?>

<div>
    <x-ui.page-header title="Master Referensi RS Online" subtitle="Data referensi dari API RS Online Kemenkes">
        <x-slot:actions>
            <x-atoms.button wire:click="loadReferensi" icon="arrow-path" variant="ghost" wire:loading.attr="disabled"
                wire:target="loadReferensi">
                <span wire:loading.remove wire:target="loadReferensi">Refresh</span>
                <span wire:loading wire:target="loadReferensi">Memuat...</span>
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="bg-white dark:bg-primary-dark-800 rounded-xl shadow border border-zinc-200 dark:border-primary-dark-700">
        {{-- Tab Navigasi --}}
        <x-molecules.tabs>
    
                @foreach ($types as $key => $label)
                    <x-atoms.tab-item wire:click="loadReferensi('{{ $key }}')">{{ $label }}</x-atoms.tab-item>
                @endforeach
            
    </x-molecules.tabs>

        {{-- Konten Tab --}}
        <div class="p-6">
            {{-- Loading --}}
            <div wire:loading wire:target="loadReferensi" class="flex items-center justify-center py-12">
                <div class="flex items-center gap-3 text-zinc-500 dark:text-primary-dark-400">
                    <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <span>Memuat data...</span>
                </div>
            </div>

            {{-- Error --}}
            @if ($error)
                <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4">
                    <div class="flex items-start gap-3">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                        <div>
                            <p class="text-sm font-medium text-red-800 dark:text-red-300">Gagal memuat data</p>
                            <p class="text-sm text-red-700 dark:text-red-400 mt-1">{{ $error }}</p>
                            @if (str_contains($error, 'Konfigurasi'))
                                <a wire:navigate href="{{ route('rsonline.configuration') }}"
                                    class="inline-flex items-center gap-1 mt-2 text-sm font-medium text-red-600 dark:text-red-400 hover:underline">
                                    <flux:icon name="arrow-right" class="w-4 h-4" />
                                    Atur Konfigurasi
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Empty State (sebelum memuat) --}}
            @if (!$error && empty($data))
                <div wire:loading.remove wire:target="loadReferensi"
                    class="flex flex-col items-center justify-center py-12 text-zinc-400 dark:text-primary-dark-600">
                    <flux:icon name="book-open" class="w-12 h-12 mb-3" />
                    <p class="text-sm">Klik tab di atas untuk memuat data referensi</p>
                    <x-atoms.button wire:click="loadReferensi('{{ $activeTab }}')" variant="ghost" size="sm"
                        class="mt-3">
                        Muat Data
                    </x-atoms.button>
                </div>
            @endif

            {{-- Tabel Data --}}
            @if (!$error && !empty($data))
                <div wire:loading.remove wire:target="loadReferensi">
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mb-3">
                        Menampilkan <span class="font-semibold">{{ count($data) }}</span> data
                        <span class="font-medium">{{ $types[$activeTab] ?? $activeTab }}</span>
                    </p>

                    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-primary-dark-700">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 dark:bg-primary-dark-900/50">
                                <tr>
                                    @foreach (array_keys((array) $data[0]) as $col)
                                        <th
                                            class="px-4 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-primary-dark-400 uppercase tracking-wide">
                                            {{ $col }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                                @foreach ($data as $row)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/30">
                                        @foreach ((array) $row as $val)
                                            <td class="px-4 py-2.5 text-zinc-800 dark:text-primary-dark-200">
                                                {{ is_array($val) ? json_encode($val) : $val }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Raw JSON toggle --}}
                    <details class="mt-4">
                        <summary
                            class="text-xs text-zinc-400 dark:text-primary-dark-600 cursor-pointer select-none hover:text-zinc-600 dark:hover:text-primary-dark-400">
                            Lihat JSON mentah
                        </summary>
                        <x-atoms.code-block language="json" maxHeight="max-h-60" class="mt-2">{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-atoms.code-block>
                    </details>
                </div>
            @endif
        </div>
    </div>
</div>
