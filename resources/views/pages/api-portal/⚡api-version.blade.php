<?php

use App\Helpers\ConfigurationHelper;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Versi Modul API')] class extends Component {
    // Definisi statis modul: prefix harus cocok dengan segmen ketiga URI route api/{version}/{prefix}
    private const MODULE_DEFS = [
        'auth'     => ['label' => 'Auth',            'icon' => 'key',              'prefix' => 'auth',      'scope' => '-'],
        'hospital' => ['label' => 'Hospital',         'icon' => 'building-office',  'prefix' => 'hospital',  'scope' => 'hospital'],
        'simrs'    => ['label' => 'SIMRS',            'icon' => 'computer-desktop', 'prefix' => 'simrs',     'scope' => 'simrs'],
        'whatsapp' => ['label' => 'WhatsApp Gateway', 'icon' => 'chat-bubble-left', 'prefix' => 'whatsapp',  'scope' => 'whatsapp-gateway'],
        'tte'      => ['label' => 'TTE',              'icon' => 'pencil-square',    'prefix' => 'tte',       'scope' => 'tte'],
        'qrcode'   => ['label' => 'QR Code',          'icon' => 'qr-code',          'prefix' => 'qrcode',    'scope' => 'qrcode'],
        'ai'       => ['label' => 'AI Provider',      'icon' => 'cpu-chip',         'prefix' => 'ai',        'scope' => 'ai'],
        'dicom'    => ['label' => 'DICOM Worklist',   'icon' => 'photo',            'prefix' => 'worklists', 'scope' => 'dicom'],
    ];

    // Prefix route → module key (untuk worklists yang berbeda dari key-nya)
    private const PREFIX_TO_MODULE = [
        'auth'      => 'auth',
        'hospital'  => 'hospital',
        'simrs'     => 'simrs',
        'whatsapp'  => 'whatsapp',
        'tte'       => 'tte',
        'qrcode'    => 'qrcode',
        'ai'        => 'ai',
        'worklists' => 'dicom',
    ];

    public array $activeVersions = [];

    public function mount(): void
    {
        foreach (array_keys(self::MODULE_DEFS) as $module) {
            $this->activeVersions[$module] = ConfigurationHelper::get("api.modules.{$module}.active_version", 'v1');
        }
    }

    public function saveAll(): void
    {
        $moduleDefs = $this->buildModuleDefs();

        foreach ($this->activeVersions as $module => $version) {
            $def = $moduleDefs[$module] ?? null;
            if (!$def || !in_array($version, $def['versions'])) {
                continue;
            }
            ConfigurationHelper::set("api.modules.{$module}.active_version", $version);
        }

        $this->toastSuccess('Versi modul API berhasil disimpan.');
    }

    // Deteksi versi yang tersedia per modul dari route yang terdaftar
    private function buildModuleDefs(): array
    {
        $detected = [];
        foreach (Route::getRoutes() as $route) {
            // Cocokkan pola: api/{version}/{prefix}/...
            if (!preg_match('#^api/(v\d+)/([^/]+)#', $route->uri(), $m)) {
                continue;
            }
            $version = $m[1];
            $prefix  = $m[2];
            $moduleKey = self::PREFIX_TO_MODULE[$prefix] ?? null;
            if ($moduleKey) {
                $detected[$moduleKey][$version] = true;
            }
        }

        $defs = [];
        foreach (self::MODULE_DEFS as $key => $base) {
            $versions = array_keys($detected[$key] ?? ['v1' => true]);
            usort($versions, 'strnatcmp');
            $defs[$key] = array_merge($base, ['versions' => $versions]);
        }

        return $defs;
    }

    public function with(): array
    {
        return ['moduleDefs' => $this->buildModuleDefs()];
    }
}; ?>

<div>
    <x-ui.page-header title="Versi Modul API"
        subtitle="Atur versi aktif per modul API. Request ke versi yang tidak aktif akan dikembalikan 410 Gone." />

    {{-- Info panel --}}
    <div
        class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800/40 flex items-start gap-3">
        <flux:icon name="information-circle" class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
        <div class="text-sm text-amber-700 dark:text-amber-400 space-y-1">
            <p class="font-semibold">Cara kerja version gating</p>
            <p>Request ke <code class="font-mono text-xs">/api/v1/qrcode</code> akan dikembalikan <strong>410
                    Gone</strong> jika versi aktif qrcode bukan <code class="font-mono text-xs">v1</code>. Consumer API
                harus menggunakan URL dengan versi yang sesuai dengan konfigurasi di sini.</p>
        </div>
    </div>

    <x-ui.section-card class="mt-4" title="Modul API">
        <x-slot:subtitle>
            Semua modul saat ini menggunakan <code
                class="font-mono text-xs bg-zinc-100 dark:bg-primary-dark-700 px-1.5 py-0.5 rounded">v1</code>.
            Saat versi baru tersedia, pilih versi aktif yang diinginkan per modul.
        </x-slot:subtitle>

        <div class="overflow-x-auto -mx-6">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-primary-dark-700">
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">
                            Modul</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">
                            Scope</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">
                            Versi Tersedia</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">
                            Versi Aktif</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">
                            Base URL Aktif</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700/60">
                    @foreach ($moduleDefs as $module => $def)
                        <tr class="hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/30 transition-colors">
                            {{-- Modul --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-50 dark:bg-primary-900/20 ring-1 ring-primary-200 dark:ring-primary-800 shrink-0">
                                        <flux:icon name="{{ $def['icon'] }}"
                                            class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                                    </div>
                                    <span
                                        class="font-medium text-zinc-800 dark:text-primary-dark-100">{{ $def['label'] }}</span>
                                </div>
                            </td>

                            {{-- Scope --}}
                            <td class="px-6 py-4">
                                @if ($def['scope'] !== '-')
                                    <flux:badge color="zinc" size="sm" class="font-mono">{{ $def['scope'] }}
                                    </flux:badge>
                                @else
                                    <span class="text-xs text-zinc-400 dark:text-primary-dark-500 italic">publik</span>
                                @endif
                            </td>

                            {{-- Versi Tersedia --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    @foreach ($def['versions'] as $ver)
                                        <flux:badge
                                            color="{{ $ver === ($activeVersions[$module] ?? 'v1') ? 'green' : 'zinc' }}"
                                            size="sm" class="font-mono">
                                            {{ $ver }}
                                        </flux:badge>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Versi Aktif (selector) --}}
                            <td class="px-6 py-4">
                                @if (count($def['versions']) > 1)
                                    <flux:select wire:model="activeVersions.{{ $module }}" class="font-mono w-24">
                                        @foreach ($def['versions'] as $ver)
                                            <flux:select.option value="{{ $ver }}">{{ $ver }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <span class="font-mono text-sm font-semibold text-green-600 dark:text-green-400">
                                        {{ $activeVersions[$module] ?? 'v1' }}
                                    </span>
                                @endif
                            </td>

                            {{-- Base URL Aktif --}}
                            <td class="px-6 py-4">
                                <code
                                    class="text-xs font-mono text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20 px-2 py-0.5 rounded">
                                    /api/{{ $activeVersions[$module] ?? 'v1' }}/{{ $def['prefix'] }}
                                </code>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <div class="flex items-center gap-3">
                <x-atoms.button wire:click="saveAll" variant="primary" icon="check">Simpan</x-atoms.button>
                <span wire:loading wire:target="saveAll"
                    class="text-sm text-zinc-500 dark:text-primary-dark-400">Menyimpan...</span>
            </div>
        </x-slot:footer>
    </x-ui.section-card>
</div>
