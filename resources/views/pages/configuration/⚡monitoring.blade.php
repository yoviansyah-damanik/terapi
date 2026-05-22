<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Monitoring')] class extends Component {
    public function with(): array
    {
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $batches = DB::table('job_batches')->count();

        $cacheOk = rescue(fn() => Cache::put('_monitoring_ping', 1, 5) && Cache::get('_monitoring_ping') === 1, false);

        $dbOk = rescue(fn() => DB::connection()->getPdo() !== null, false);

        return [
            'pendingJobs' => $pendingJobs,
            'failedJobs' => $failedJobs,
            'batches' => $batches,
            'cacheOk' => $cacheOk,
            'dbOk' => $dbOk,
            'queueDriver' => strtoupper(config('queue.default', '-')),
            'cacheDriver' => strtoupper(config('cache.default', '-')),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'environment' => app()->environment(),
            'debugMode' => config('app.debug'),
            'timezone' => config('app.timezone'),
        ];
    }
};
?>

<div>
    <x-ui.page-header title="Monitoring" subtitle="Pemantauan performa, antrian, query, cache, dan exception aplikasi" />

    {{-- Stats Overview --}}
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <x-organisms.stat-card 
            title="Pending Jobs" 
            :value="number_format($pendingJobs)" 
            :color="$pendingJobs > 0 ? 'amber' : 'zinc'" 
            icon="queue-list" />

        <x-organisms.stat-card 
            title="Failed Jobs" 
            :value="number_format($failedJobs)" 
            :color="$failedJobs > 0 ? 'red' : 'zinc'" 
            icon="exclamation-triangle" />

        <x-organisms.stat-card 
            title="Job Batches" 
            :value="number_format($batches)" 
            color="purple" 
            icon="rectangle-stack" />

        <x-organisms.stat-card 
            title="Database" 
            :value="$dbOk ? 'OK' : 'Error'" 
            :color="$dbOk ? 'green' : 'red'" 
            icon="circle-stack" />

        <x-organisms.stat-card 
            title="Cache" 
            :value="$cacheOk ? 'OK' : 'Error'" 
            :color="$cacheOk ? 'green' : 'red'" 
            icon="bolt" 
            :subtitle="$cacheDriver" />

        <x-organisms.stat-card 
            title="Environment" 
            :value="ucfirst($environment)" 
            :color="$environment === 'production' ? 'green' : 'amber'" 
            icon="server" />
    </div>

    {{-- System Info Bar --}}
    <div
        class="flex flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 mb-6 bg-white rounded-lg shadow text-xs text-zinc-500 dark:bg-primary-dark-800 dark:text-primary-dark-400">
        <span><span class="font-medium text-zinc-700 dark:text-primary-dark-300">PHP</span> {{ $phpVersion }}</span>
        <span class="hidden sm:inline text-zinc-300 dark:text-primary-dark-600">|</span>
        <span><span class="font-medium text-zinc-700 dark:text-primary-dark-300">Laravel</span>
            {{ $laravelVersion }}</span>
        <span class="hidden sm:inline text-zinc-300 dark:text-primary-dark-600">|</span>
        <span><span class="font-medium text-zinc-700 dark:text-primary-dark-300">Queue</span>
            {{ $queueDriver }}</span>
        <span class="hidden sm:inline text-zinc-300 dark:text-primary-dark-600">|</span>
        <span><span class="font-medium text-zinc-700 dark:text-primary-dark-300">Timezone</span>
            {{ $timezone }}</span>
        @if ($debugMode)
            <span class="hidden sm:inline text-zinc-300 dark:text-primary-dark-600">|</span>
            <span class="text-amber-600 dark:text-amber-400 font-medium">Debug Mode ON</span>
        @endif
        <a href="{{ url('/pulse') }}" target="_blank"
            class="ml-auto flex items-center gap-1.5 text-primary-500 hover:text-primary-400 transition-colors">
            <flux:icon name="arrow-top-right-on-square" class="w-3.5 h-3.5" />
            Buka layar penuh
        </a>
    </div>

    {{-- Laravel Pulse — dimuat via iframe agar CSS/JS-nya tidak menabrak layout aplikasi --}}
    <div class="overflow-hidden rounded-lg shadow bg-white dark:bg-primary-dark-800">
        <iframe id="pulse-frame" src="{{ url('/pulse') }}" class="w-full border-0 block" style="height: auto;"
            title="Laravel Pulse Dashboard" onload="resizePulseFrame(this)"></iframe>
    </div>
</div>

@pushOnce('scripts')
    <script>
        function resizePulseFrame(frame) {
            try {
                const doc = frame.contentDocument || frame.contentWindow.document;
                const height = doc.documentElement.scrollHeight || doc.body.scrollHeight;
                if (height > 400) frame.style.height = height + 'px';
            } catch (e) {
                // cross-origin — biarkan tinggi default
            }
        }
    </script>
@endPushOnce
