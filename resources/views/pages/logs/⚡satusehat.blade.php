<?php

use App\Models\SatuSehat\SatuSehatBundle;
use App\Models\SatuSehat\SatuSehatBundleLog;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Log Satu Sehat')] #[Lazy] class extends Component {
    #[Url(as: 'tab')]
    public string $activeTab = 'api';

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('pages::logs._satusehat._placeholder');
    }

    public function with(): array
    {
        $mgFilter = fn($q) => $q->where('error_message', 'like', '%mapping%')->orWhere('error_message', 'like', '%KFA%')->orWhere('error_message', 'like', '%SNOMED%')->orWhere('error_message', 'like', '%LOINC%')->orWhere('error_message', 'like', '%dipetakan%');

        return [
            'bundleFailedCount' => SatuSehatBundle::byStatus(SatuSehatBundle::STATUS_PARTIAL)->count() + SatuSehatBundle::byStatus(SatuSehatBundle::STATUS_FAILED)->count(),
            'blFailedCount' => SatuSehatBundleLog::where('status', 'failed')->count(),
            'mgBadgeCount' => SatuSehatBundleLog::where('status', 'warning')->where($mgFilter)->count(),
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Log Satu Sehat" subtitle="Riwayat akses API dan pengiriman bundle Satu Sehat">
        <x-slot:actions>
            <x-atoms.button variant="ghost" icon="arrow-path" wire:click="$refresh">Refresh</x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Tab Navigation --}}
    <div
        class="mb-5 overflow-hidden bg-white border rounded-2xl dark:bg-primary-dark-800/60 border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm">
        <div class="p-3">
            <div class="flex gap-1 p-1 rounded-xl bg-zinc-100 dark:bg-primary-dark-900/60">
                <button wire:click="$set('activeTab', 'api')"
                    class="flex flex-1 items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all duration-200
                        {{ $activeTab === 'api'
                            ? 'bg-white dark:bg-primary-dark-700 text-primary-700 dark:text-primary-300 shadow-sm ring-1 ring-zinc-200/70 dark:ring-primary-dark-600/50'
                            : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                    <flux:icon name="document-text" class="w-4 h-4" />
                    API Log
                </button>
                <button wire:click="$set('activeTab', 'bundle')"
                    class="flex flex-1 items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all duration-200
                        {{ $activeTab === 'bundle'
                            ? 'bg-white dark:bg-primary-dark-700 text-primary-700 dark:text-primary-300 shadow-sm ring-1 ring-zinc-200/70 dark:ring-primary-dark-600/50'
                            : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                    <flux:icon name="paper-airplane" class="w-4 h-4" />
                    Bundle Job
                    @if ($bundleFailedCount > 0)
                        <flux:badge color="red" size="sm">{{ number_format($bundleFailedCount) }}</flux:badge>
                    @endif
                </button>
                <button wire:click="$set('activeTab', 'bundle-log')"
                    class="flex flex-1 items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all duration-200
                        {{ $activeTab === 'bundle-log'
                            ? 'bg-white dark:bg-primary-dark-700 text-primary-700 dark:text-primary-300 shadow-sm ring-1 ring-zinc-200/70 dark:ring-primary-dark-600/50'
                            : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                    <flux:icon name="queue-list" class="w-4 h-4" />
                    Bundle Log
                    @if ($blFailedCount > 0)
                        <flux:badge color="red" size="sm">{{ number_format($blFailedCount) }}</flux:badge>
                    @endif
                </button>
                <button wire:click="$set('activeTab', 'mapping-gagal')"
                    class="flex flex-1 items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all duration-200
                        {{ $activeTab === 'mapping-gagal'
                            ? 'bg-white dark:bg-primary-dark-700 text-primary-700 dark:text-primary-300 shadow-sm ring-1 ring-zinc-200/70 dark:ring-primary-dark-600/50'
                            : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                    <flux:icon name="exclamation-triangle" class="w-4 h-4" />
                    Mapping Gagal
                    @if ($mgBadgeCount > 0)
                        <flux:badge color="red" size="sm">{{ number_format($mgBadgeCount) }}</flux:badge>
                    @endif
                </button>
            </div>
        </div>
    </div>

    {{-- Tab Panels (lazy-loaded per-tab) --}}
    @if ($activeTab === 'api')
        <livewire:logs._satusehat.tab-api :key="'tab-api'" />
    @endif

    @if ($activeTab === 'bundle')
        <livewire:logs._satusehat.tab-bundle :key="'tab-bundle'" />
    @endif

    @if ($activeTab === 'bundle-log')
        <livewire:logs._satusehat.tab-bundle-log :key="'tab-bundle-log'" />
    @endif

    @if ($activeTab === 'mapping-gagal')
        <livewire:logs._satusehat.tab-mapping-gagal :key="'tab-mapping-gagal'" />
    @endif
</div>
