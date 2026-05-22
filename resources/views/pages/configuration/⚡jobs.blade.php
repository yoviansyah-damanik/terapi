<?php

use App\Models\Configuration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Job & Queue')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'settings';

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterQueue = '';

    #[Url]
    public int $perPage = 25;

    public bool $showClearModal = false;
    public string $clearType = '';

    public bool $showDetailModal = false;
    public ?array $selectedJob = null;

    // Queue Worker Settings
    public string $queueTimeout = '60';
    public string $queueMemory = '128';
    public string $queueSleep = '3';
    public string $queueTries = '3';
    public string $queueBackoff = '10';
    public string $queueMaxJobs = '1000';
    public string $queueMaxTime = '3600';
    public string $queueNames = 'default';

    // Satu Sehat Sync
    public string $syncBatchSize = '1000';

    public function mount()
    {
        $this->loadQueueSettings();
    }

    public function updatedTab()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterQueue()
    {
        $this->resetPage();
    }

    private function loadQueueSettings(): void
    {
        $settings = Configuration::whereIn('key', ['queue.timeout', 'queue.memory', 'queue.sleep', 'queue.tries', 'queue.backoff', 'queue.max_jobs', 'queue.max_time', 'queue.queue_names', 'satusehat.sync_batch_size'])->pluck('value', 'key');

        $this->queueTimeout = $settings->get('queue.timeout', '60');
        $this->queueMemory = $settings->get('queue.memory', '128');
        $this->queueSleep = $settings->get('queue.sleep', '3');
        $this->queueTries = $settings->get('queue.tries', '3');
        $this->queueBackoff = $settings->get('queue.backoff', '10');
        $this->queueMaxJobs = $settings->get('queue.max_jobs', '1000');
        $this->queueMaxTime = $settings->get('queue.max_time', '3600');
        $this->queueNames = $settings->get('queue.queue_names', 'default');
        $this->syncBatchSize = $settings->get('satusehat.sync_batch_size', '1000');
    }

    public function saveQueueSettings()
    {
        $this->validate([
            'queueTimeout' => 'required|integer|min:0|max:86400',
            'queueMemory' => 'required|integer|min:32|max:2048',
            'queueSleep' => 'required|integer|min:0|max:60',
            'queueTries' => 'required|integer|min:0|max:100',
            'queueBackoff' => 'required|integer|min:0|max:3600',
            'queueMaxJobs' => 'required|integer|min:0|max:100000',
            'queueMaxTime' => 'required|integer|min:0|max:86400',
            'queueNames' => 'required|string|max:255',
        ]);

        $settings = [
            'queue.timeout' => $this->queueTimeout,
            'queue.memory' => $this->queueMemory,
            'queue.sleep' => $this->queueSleep,
            'queue.tries' => $this->queueTries,
            'queue.backoff' => $this->queueBackoff,
            'queue.max_jobs' => $this->queueMaxJobs,
            'queue.max_time' => $this->queueMaxTime,
            'queue.queue_names' => $this->queueNames,
        ];

        foreach ($settings as $key => $value) {
            Configuration::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->toastSuccess('Pengaturan queue berhasil disimpan');
    }

    public function saveSatuSehatSettings(): void
    {
        $this->validate([
            'syncBatchSize' => 'required|integer|min:10|max:10000',
        ]);

        Configuration::updateOrCreate(['key' => 'satusehat.sync_batch_size'], ['value' => $this->syncBatchSize]);

        $this->toastSuccess('Pengaturan Satu Sehat berhasil disimpan');
    }

    public function getWorkerCommandProperty(): string
    {
        $parts = ['php artisan queue:work'];

        if ($this->queueNames && $this->queueNames !== 'default') {
            $parts[] = '--queue=' . $this->queueNames;
        }

        $parts[] = '--timeout=' . ($this->queueTimeout ?: '60');
        $parts[] = '--memory=' . ($this->queueMemory ?: '128');
        $parts[] = '--sleep=' . ($this->queueSleep ?: '3');
        $parts[] = '--tries=' . ($this->queueTries ?: '3');
        $parts[] = '--backoff=' . ($this->queueBackoff ?: '10');

        if ((int) $this->queueMaxJobs > 0) {
            $parts[] = '--max-jobs=' . $this->queueMaxJobs;
        }

        if ((int) $this->queueMaxTime > 0) {
            $parts[] = '--max-time=' . $this->queueMaxTime;
        }

        return implode(' ', $parts);
    }

    public function restartQueue()
    {
        try {
            Artisan::call('queue:restart');
            $this->toastSuccess('Queue worker akan di-restart');
        } catch (\Exception $e) {
            $this->toastError('Gagal restart queue: ' . $e->getMessage());
        }
    }

    public function showDetail(string $id, string $type)
    {
        if ($type === 'pending') {
            $job = DB::table('jobs')->where('id', $id)->first();
            if ($job) {
                $payload = json_decode($job->payload, true);
                $this->selectedJob = [
                    'id' => $job->id,
                    'type' => 'pending',
                    'queue' => $job->queue,
                    'attempts' => $job->attempts,
                    'job_class' => $payload['displayName'] ?? 'Unknown',
                    'payload' => $payload,
                    'created_at' => \Carbon\Carbon::createFromTimestamp($job->created_at),
                    'available_at' => \Carbon\Carbon::createFromTimestamp($job->available_at),
                    'reserved_at' => $job->reserved_at ? \Carbon\Carbon::createFromTimestamp($job->reserved_at) : null,
                ];
            }
        } elseif ($type === 'failed') {
            $job = DB::table('failed_jobs')->where('id', $id)->first();
            if ($job) {
                $payload = json_decode($job->payload, true);
                $this->selectedJob = [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'type' => 'failed',
                    'queue' => $job->queue,
                    'connection' => $job->connection,
                    'job_class' => $payload['displayName'] ?? 'Unknown',
                    'payload' => $payload,
                    'exception' => $job->exception,
                    'failed_at' => \Carbon\Carbon::parse($job->failed_at),
                ];
            }
        }
        $this->showDetailModal = true;
    }

    public function closeDetail()
    {
        $this->showDetailModal = false;
        $this->selectedJob = null;
    }

    public function retryJob(string $uuid)
    {
        try {
            Artisan::call('queue:retry', ['id' => [$uuid]]);
            $this->toastSuccess('Job berhasil di-retry');
        } catch (\Exception $e) {
            $this->toastError('Gagal retry job: ' . $e->getMessage());
        }
    }

    public function deleteFailedJob(string $uuid)
    {
        try {
            DB::table('failed_jobs')->where('uuid', $uuid)->delete();
            $this->toastSuccess('Failed job berhasil dihapus');
            $this->closeDetail();
        } catch (\Exception $e) {
            $this->toastError('Gagal menghapus job: ' . $e->getMessage());
        }
    }

    public function retryAllFailed()
    {
        try {
            Artisan::call('queue:retry', ['id' => ['all']]);
            $this->toastSuccess('Semua failed jobs di-retry');
        } catch (\Exception $e) {
            $this->toastError('Gagal retry jobs: ' . $e->getMessage());
        }
    }

    public function flushFailedJobs()
    {
        try {
            DB::table('failed_jobs')->truncate();
            $this->toastSuccess('Semua failed jobs berhasil dihapus');
        } catch (\Exception $e) {
            $this->toastError('Gagal menghapus failed jobs: ' . $e->getMessage());
        }
    }

    public function openClearModal(string $type)
    {
        $this->clearType = $type;
        $this->showClearModal = true;
    }

    public function closeClearModal()
    {
        $this->showClearModal = false;
        $this->clearType = '';
    }

    public function confirmClear()
    {
        try {
            match ($this->clearType) {
                'pending' => DB::table('jobs')->truncate(),
                'failed' => DB::table('failed_jobs')->truncate(),
                'batches' => DB::table('job_batches')->truncate(),
                'all' => $this->clearAll(),
                default => throw new \Exception('Tipe tidak valid'),
            };

            $this->toastSuccess('Data berhasil dihapus');
            $this->closeClearModal();
        } catch (\Exception $e) {
            $this->toastError('Gagal menghapus data: ' . $e->getMessage());
        }
    }

    private function clearAll(): void
    {
        DB::table('jobs')->truncate();
        DB::table('failed_jobs')->truncate();
        DB::table('job_batches')->truncate();
    }

    public function clearCache()
    {
        try {
            Cache::flush();
            $this->toastSuccess('Cache berhasil dibersihkan');
        } catch (\Exception $e) {
            $this->toastError('Gagal membersihkan cache: ' . $e->getMessage());
        }
    }

    public function with(): array
    {
        $pendingCount = DB::table('jobs')->count();
        $failedCount = DB::table('failed_jobs')->count();
        $batchCount = DB::table('job_batches')->count();

        $queueConfig = [
            'driver' => config('queue.default'),
            'connection' => config('queue.connections.' . config('queue.default')),
            'failed_driver' => config('queue.failed.driver'),
        ];

        $queues = DB::table('jobs')
            ->select('queue')
            ->distinct()
            ->pluck('queue')
            ->merge(DB::table('failed_jobs')->select('queue')->distinct()->pluck('queue'))
            ->unique()
            ->values();

        $queueCounts = DB::table('jobs')->select('queue', DB::raw('COUNT(*) as count'))->groupBy('queue')->pluck('count', 'queue')->toArray();

        $data = [];

        if ($this->tab === 'pending') {
            $query = DB::table('jobs')->when($this->filterQueue, fn($q) => $q->where('queue', $this->filterQueue))->when($this->search, fn($q) => $q->where('payload', 'like', "%{$this->search}%"))->orderByDesc('id');
            $data['jobs'] = $query->paginate($this->perPage);
        } elseif ($this->tab === 'failed') {
            $query = DB::table('failed_jobs')->when($this->filterQueue, fn($q) => $q->where('queue', $this->filterQueue))->when($this->search, fn($q) => $q->where('payload', 'like', "%{$this->search}%")->orWhere('exception', 'like', "%{$this->search}%"))->orderByDesc('failed_at');
            $data['jobs'] = $query->paginate($this->perPage);
        } elseif ($this->tab === 'batches') {
            $query = DB::table('job_batches')->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))->orderByDesc('created_at');
            $data['batches'] = $query->paginate($this->perPage);
        }

        $recentFailedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(5)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'job_class' => class_basename($payload['displayName'] ?? 'Unknown'),
                    'queue' => $job->queue,
                    'failed_at' => \Carbon\Carbon::parse($job->failed_at),
                    'error' => Str::limit(explode("\n", $job->exception)[0] ?? '', 80),
                ];
            });

        return array_merge($data, [
            'pendingCount' => $pendingCount,
            'failedCount' => $failedCount,
            'batchCount' => $batchCount,
            'queueConfig' => $queueConfig,
            'queues' => $queues,
            'queueCounts' => $queueCounts,
            'recentFailedJobs' => $recentFailedJobs,
        ]);
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Job & Queue" subtitle="Pengaturan, monitoring, dan manajemen antrian job">
        <x-slot:actions>
            <x-atoms.button icon="arrow-path" variant="ghost" wire:click="$refresh">
                Refresh
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 md:grid-cols-4">
        <x-organisms.stat-card title="Pending Jobs" :value="number_format($pendingCount)" color="blue" icon="queue-list" />

        <x-organisms.stat-card title="Failed Jobs" :value="number_format($failedCount)" color="red" icon="exclamation-triangle" />

        <x-organisms.stat-card title="Job Batches" :value="number_format($batchCount)" color="violet" icon="rectangle-stack" />

        <x-organisms.stat-card title="Queue Driver" :value="$queueConfig['driver']" color="emerald" icon="server" />
    </div>

    {{-- Tab Navigation --}}
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <x-molecules.tabs class="flex-1 w-full sm:w-auto">
            <x-atoms.tab-item :active="$tab === 'settings'" wire:click="$set('tab', 'settings')">
                <div class="flex items-center gap-2">
                    <flux:icon.cog-6-tooth class="size-4" />
                    <span>Pengaturan</span>
                </div>
            </x-atoms.tab-item>
            <x-atoms.tab-item :active="$tab === 'pending'" wire:click="$set('tab', 'pending')">
                <div class="flex items-center gap-2">
                    <flux:icon.queue-list class="size-4" />
                    <span>Pending</span>
                    @if ($pendingCount > 0)
                        <flux:badge color="blue" inset size="sm" class="ml-1 px-1.5">
                            {{ number_format($pendingCount) }}</flux:badge>
                    @endif
                </div>
            </x-atoms.tab-item>
            <x-atoms.tab-item :active="$tab === 'failed'" wire:click="$set('tab', 'failed')">
                <div class="flex items-center gap-2">
                    <flux:icon.exclamation-triangle class="size-4" />
                    <span>Failed</span>
                    @if ($failedCount > 0)
                        <flux:badge color="red" inset size="sm" class="ml-1 px-1.5">
                            {{ number_format($failedCount) }}</flux:badge>
                    @endif
                </div>
            </x-atoms.tab-item>
            <x-atoms.tab-item :active="$tab === 'batches'" wire:click="$set('tab', 'batches')">
                <div class="flex items-center gap-2">
                    <flux:icon.rectangle-stack class="size-4" />
                    <span>Batches</span>
                    @if ($batchCount > 0)
                        <flux:badge color="purple" inset size="sm" class="ml-1 px-1.5">
                            {{ number_format($batchCount) }}</flux:badge>
                    @endif
                </div>
            </x-atoms.tab-item>
        </x-molecules.tabs>

        @if ($tab === 'failed' && $failedCount > 0)
            <div class="flex items-center gap-2 shrink-0">
                <x-atoms.button icon="arrow-path" variant="ghost" size="sm" wire:click="retryAllFailed"
                    wire:confirm="Retry semua failed jobs?">
                    Retry All
                </x-atoms.button>
                <x-atoms.button icon="trash" variant="ghost" size="sm"
                    class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-900/20"
                    wire:click="flushFailedJobs"
                    wire:confirm="Hapus semua failed jobs? Aksi ini tidak dapat dibatalkan.">
                    Flush All
                </x-atoms.button>
            </div>
        @endif
    </div>

    {{-- ==================== TAB: PENGATURAN ==================== --}}
    @if ($tab === 'settings')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Queue Worker Settings --}}
            <div class="p-6 bg-white rounded-lg shadow dark:bg-primary-dark-800 lg:col-span-2">
                <h3 class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                    <flux:icon name="cog-6-tooth" class="w-5 h-5" />
                    Pengaturan Queue Worker
                </h3>

                <form wire:submit="saveQueueSettings">
                    <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                        <flux:field>
                            <flux:label>Timeout (detik)</flux:label>
                            <flux:input type="number" wire:model="queueTimeout" min="0" max="86400" />
                            <flux:description>Batas waktu eksekusi per job</flux:description>
                            <flux:error name="queueTimeout" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Memory Limit (MB)</flux:label>
                            <flux:input type="number" wire:model="queueMemory" min="32" max="2048" />
                            <flux:description>Batas memori worker</flux:description>
                            <flux:error name="queueMemory" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Sleep (detik)</flux:label>
                            <flux:input type="number" wire:model="queueSleep" min="0" max="60" />
                            <flux:description>Jeda saat tidak ada job</flux:description>
                            <flux:error name="queueSleep" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Tries</flux:label>
                            <flux:input type="number" wire:model="queueTries" min="0" max="100" />
                            <flux:description>Jumlah percobaan ulang</flux:description>
                            <flux:error name="queueTries" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Backoff (detik)</flux:label>
                            <flux:input type="number" wire:model="queueBackoff" min="0" max="3600" />
                            <flux:description>Jeda sebelum retry</flux:description>
                            <flux:error name="queueBackoff" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Max Jobs</flux:label>
                            <flux:input type="number" wire:model="queueMaxJobs" min="0" max="100000" />
                            <flux:description>Maks job sebelum worker berhenti (0 = tanpa batas)</flux:description>
                            <flux:error name="queueMaxJobs" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Max Time (detik)</flux:label>
                            <flux:input type="number" wire:model="queueMaxTime" min="0" max="86400" />
                            <flux:description>Maks waktu worker berjalan (0 = tanpa batas)</flux:description>
                            <flux:error name="queueMaxTime" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Queue Names</flux:label>
                            <flux:input type="text" wire:model="queueNames" placeholder="default" />
                            <flux:description>Nama queue, pisahkan dengan koma</flux:description>
                            <flux:error name="queueNames" />
                        </flux:field>
                    </div>

                    <div
                        class="flex items-center justify-between pt-5 mt-5 border-t border-zinc-200 dark:border-primary-dark-700">
                        <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                            Setelah menyimpan, restart worker agar pengaturan diterapkan.
                        </p>
                        <div class="flex gap-3">
                            <x-atoms.button variant="primary" type="submit" icon="check">
                                Simpan Pengaturan
                            </x-atoms.button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Generated Command --}}
            <div class="p-6 bg-white rounded-lg shadow dark:bg-primary-dark-800 lg:col-span-2">
                <h3
                    class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                    <flux:icon name="command-line" class="w-5 h-5" />
                    Perintah Queue Worker
                </h3>

                <div class="p-4 rounded-lg bg-zinc-900 dark:bg-primary-dark-950">
                    <p class="mb-3 text-xs text-zinc-400">Perintah berdasarkan pengaturan saat ini:</p>
                    <div class="flex items-start gap-2 font-mono text-sm">
                        <span class="text-green-400 select-none">$</span>
                        <code class="text-zinc-100 break-all">{{ $this->workerCommand }}</code>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-3">
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                            Development</h4>
                        <p class="mt-1 text-xs text-zinc-600 dark:text-primary-dark-400">
                            Gunakan <code
                                class="px-1 py-0.5 rounded bg-zinc-200 dark:bg-primary-dark-700">queue:listen</code>
                            agar auto-reload saat kode berubah
                        </p>
                    </div>
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">Supervisor
                            (Linux)
                        </h4>
                        <p class="mt-1 text-xs text-zinc-600 dark:text-primary-dark-400">
                            Gunakan Supervisor untuk menjalankan worker secara daemon di production
                        </p>
                    </div>
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">Windows
                            Service
                        </h4>
                        <p class="mt-1 text-xs text-zinc-600 dark:text-primary-dark-400">
                            Gunakan NSSM atau Task Scheduler untuk menjalankan worker di Windows
                        </p>
                    </div>
                </div>
            </div>

            {{-- Satu Sehat Sync Settings --}}
            <div class="p-6 bg-white rounded-lg shadow dark:bg-primary-dark-800 lg:col-span-2">
                <h3
                    class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                    <flux:icon name="arrow-path" class="w-5 h-5" />
                    Pengaturan Sync Satu Sehat
                </h3>

                <form wire:submit="saveSatuSehatSettings">
                    <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                        <flux:field>
                            <flux:label>Ukuran Batch Sync</flux:label>
                            <flux:input type="number" wire:model="syncBatchSize" min="10" max="10000" />
                            <flux:description>Jumlah data per batch saat sync Patient/Practitioner</flux:description>
                            <flux:error name="syncBatchSize" />
                        </flux:field>
                    </div>

                    <div
                        class="flex items-center justify-between pt-5 mt-5 border-t border-zinc-200 dark:border-primary-dark-700">
                        <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                            Contoh: 10.000 data dengan ukuran batch 1.000 akan membuat 10 batch secara bersamaan.
                        </p>
                        <x-atoms.button variant="primary" type="submit" icon="check">
                            Simpan
                        </x-atoms.button>
                    </div>
                </form>
            </div>

            {{-- Queue Info --}}
            <div class="p-6 bg-white rounded-lg shadow dark:bg-primary-dark-800">
                <h3
                    class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                    <flux:icon name="information-circle" class="w-5 h-5" />
                    Info Queue
                </h3>

                <div class="space-y-4">
                    <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-zinc-500 dark:text-primary-dark-400">Queue Driver</dt>
                                <dd class="font-mono font-medium text-zinc-900 dark:text-primary-dark-100 uppercase">
                                    {{ $queueConfig['driver'] }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-zinc-500 dark:text-primary-dark-400">Failed Job Driver</dt>
                                <dd class="font-mono font-medium text-zinc-900 dark:text-primary-dark-100">
                                    {{ $queueConfig['failed_driver'] ?? 'database-uuids' }}
                                </dd>
                            </div>
                            @if ($queueConfig['driver'] === 'database')
                                <div class="flex justify-between">
                                    <dt class="text-zinc-500 dark:text-primary-dark-400">Table</dt>
                                    <dd class="font-mono font-medium text-zinc-900 dark:text-primary-dark-100">
                                        {{ $queueConfig['connection']['table'] ?? 'jobs' }}
                                    </dd>
                                </div>
                            @endif
                            @if (isset($queueConfig['connection']['retry_after']))
                                <div class="flex justify-between">
                                    <dt class="text-zinc-500 dark:text-primary-dark-400">Retry After</dt>
                                    <dd class="font-mono font-medium text-zinc-900 dark:text-primary-dark-100">
                                        {{ $queueConfig['connection']['retry_after'] }} detik
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    @if (count($queueCounts) > 0)
                        <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="mb-3 text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                                Jobs per Queue
                            </h4>
                            <div class="space-y-2">
                                @foreach ($queueCounts as $queue => $count)
                                    <div class="flex items-center justify-between">
                                        <span
                                            class="text-sm text-zinc-700 dark:text-primary-dark-300">{{ $queue }}</span>
                                        <flux:badge color="blue" size="sm">{{ number_format($count) }}
                                        </flux:badge>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Quick Actions & Data Management --}}
            <div class="space-y-6">
                {{-- Quick Actions --}}
                <div class="p-6 bg-white rounded-lg shadow dark:bg-primary-dark-800">
                    <h3
                        class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                        <flux:icon name="bolt" class="w-5 h-5" />
                        Quick Actions
                    </h3>

                    <div class="space-y-3">
                        <div
                            class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <div>
                                <h4 class="font-medium text-zinc-900 dark:text-primary-dark-100">Restart Queue Worker
                                </h4>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                    Mengirim sinyal restart ke semua queue worker
                                </p>
                            </div>
                            <x-atoms.button variant="primary" size="sm" icon="arrow-path"
                                wire:click="restartQueue">
                                Restart
                            </x-atoms.button>
                        </div>

                        <div
                            class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <div>
                                <h4 class="font-medium text-zinc-900 dark:text-primary-dark-100">Retry All Failed Jobs
                                </h4>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                    Menjalankan ulang semua job yang gagal ({{ number_format($failedCount) }} jobs)
                                </p>
                            </div>
                            <x-atoms.button variant="filled" size="sm" icon="arrow-path"
                                wire:click="retryAllFailed"
                                wire:confirm="Retry semua {{ $failedCount }} failed jobs?" :disabled="$failedCount === 0">
                                Retry All
                            </x-atoms.button>
                        </div>

                        <div
                            class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <div>
                                <h4 class="font-medium text-zinc-900 dark:text-primary-dark-100">Clear Cache</h4>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                    Membersihkan semua cache aplikasi
                                </p>
                            </div>
                            <x-atoms.button variant="filled" size="sm" icon="trash" wire:click="clearCache"
                                wire:confirm="Bersihkan semua cache?">
                                Clear
                            </x-atoms.button>
                        </div>
                    </div>
                </div>

                {{-- Data Management --}}
                <div class="p-6 bg-white rounded-lg shadow dark:bg-primary-dark-800">
                    <h3
                        class="flex items-center gap-2 mb-4 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                        <flux:icon name="trash" class="w-5 h-5" />
                        Manajemen Data
                    </h3>

                    <div
                        class="p-4 mb-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            <flux:icon name="exclamation-triangle" class="inline w-4 h-4 mr-1" />
                            Perhatian: Aksi di bawah ini akan menghapus data secara permanen dan tidak dapat dibatalkan.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <div
                            class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <div>
                                <h4 class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">Pending Jobs
                                </h4>
                                <p class="text-xs text-zinc-500">{{ number_format($pendingCount) }} jobs</p>
                            </div>
                            <x-atoms.button variant="danger" size="sm" wire:click="openClearModal('pending')"
                                :disabled="$pendingCount === 0">
                                Hapus
                            </x-atoms.button>
                        </div>

                        <div
                            class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <div>
                                <h4 class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">Failed Jobs
                                </h4>
                                <p class="text-xs text-zinc-500">{{ number_format($failedCount) }} jobs</p>
                            </div>
                            <x-atoms.button variant="danger" size="sm" wire:click="openClearModal('failed')"
                                :disabled="$failedCount === 0">
                                Hapus
                            </x-atoms.button>
                        </div>

                        <div
                            class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <div>
                                <h4 class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">Job Batches
                                </h4>
                                <p class="text-xs text-zinc-500">{{ number_format($batchCount) }} batches</p>
                            </div>
                            <x-atoms.button variant="danger" size="sm" wire:click="openClearModal('batches')"
                                :disabled="$batchCount === 0">
                                Hapus
                            </x-atoms.button>
                        </div>

                        <div class="pt-3 border-t border-zinc-200 dark:border-primary-dark-700">
                            <x-atoms.button icon="trash" variant="danger" class="w-full"
                                wire:click="openClearModal('all')" :disabled="$pendingCount === 0 && $failedCount === 0 && $batchCount === 0">
                                Hapus Semua Data Job
                            </x-atoms.button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent Failed Jobs --}}
            <div class="p-6 bg-white rounded-lg shadow dark:bg-primary-dark-800 lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="flex items-center gap-2 text-lg font-semibold text-zinc-900 dark:text-primary-dark-100">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500" />
                        Failed Jobs Terbaru
                    </h3>
                    <x-atoms.button variant="ghost" size="sm" wire:click="$set('tab', 'failed')">
                        Lihat Semua
                    </x-atoms.button>
                </div>

                @if ($recentFailedJobs->count() > 0)
                    <div class="space-y-3">
                        @foreach ($recentFailedJobs as $job)
                            <div
                                class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                            {{ $job['job_class'] }}
                                        </p>
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400 truncate"
                                            title="{{ $job['error'] }}">
                                            {{ $job['error'] }}
                                        </p>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-primary-dark-400">
                                            {{ $job['failed_at']->diffForHumans() }} &bull; {{ $job['queue'] }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center py-8">
                        <flux:icon name="check-circle" class="w-12 h-12 text-green-300 dark:text-green-600" />
                        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                            Tidak ada failed jobs
                        </p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ==================== TAB: PENDING / FAILED / BATCHES ==================== --}}
    @if (in_array($tab, ['pending', 'failed', 'batches']))
        {{-- Filters --}}
        <div class="p-4 mb-6 bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <flux:input wire:model.live.debounce.300ms="search"
                        placeholder="Cari job class, payload, exception..." icon="magnifying-glass" />
                </div>
                @if ($tab !== 'batches')
                    <flux:select wire:model.live="filterQueue">
                        <flux:select.option value="">Semua Queue</flux:select.option>
                        @foreach ($queues as $queue)
                            <flux:select.option value="{{ $queue }}">{{ $queue }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
                <flux:select wire:model.live="perPage">
                    <flux:select.option value="25">25 per halaman</flux:select.option>
                    <flux:select.option value="50">50 per halaman</flux:select.option>
                    <flux:select.option value="100">100 per halaman</flux:select.option>
                </flux:select>
            </div>
        </div>
    @endif

    {{-- Table: Pending Jobs --}}
    @if ($tab === 'pending')
        <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <x-organisms.table class="min-w-full">
                <x-slot:headings>
                    <x-atoms.table-heading>ID</x-atoms.table-heading>
                    <x-atoms.table-heading>Job</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden md:table-cell">Queue</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden sm:table-cell"
                        align="center">Attempts</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden lg:table-cell">Created</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden xl:table-cell">Available At</x-atoms.table-heading>
                    <x-atoms.table-heading align="center">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($jobs as $job)
                    @php
                        $payload = json_decode($job->payload, true);
                        $jobClass = $payload['displayName'] ?? 'Unknown';
                        $shortClass = class_basename($jobClass);
                    @endphp
                    <x-molecules.table-row wire:key="pending-{{ $job->id }}">
                        <x-atoms.table-cell nowrap>
                            <span
                                class="font-mono text-sm text-zinc-600 dark:text-primary-dark-400">{{ $job->id }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 rounded bg-blue-100 dark:bg-blue-900/50">
                                    <flux:icon name="cog-6-tooth" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                        {{ $shortClass }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 max-w-48 truncate"
                                        title="{{ $jobClass }}">{{ $jobClass }}</p>
                                </div>
                            </div>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden md:table-cell" nowrap>
                            <flux:badge color="zinc" size="sm">{{ $job->queue }}</flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden sm:table-cell" align="center" nowrap>
                            <span
                                class="text-sm {{ $job->attempts > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-600 dark:text-primary-dark-400' }}">{{ $job->attempts }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden lg:table-cell" nowrap>
                            <span
                                class="text-sm text-zinc-600 dark:text-primary-dark-400">{{ \Carbon\Carbon::createFromTimestamp($job->created_at)->diffForHumans() }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden xl:table-cell" nowrap>
                            <span
                                class="text-sm text-zinc-600 dark:text-primary-dark-400">{{ \Carbon\Carbon::createFromTimestamp($job->available_at)->format('d/m/Y H:i:s') }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" nowrap>
                            <x-atoms.button variant="ghost" size="sm" icon="eye"
                                wire:click="showDetail('{{ $job->id }}', 'pending')" title="Lihat Detail" />
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="7" align="center" class="py-12">
                            <div class="flex flex-col items-center">
                                <flux:icon name="check-circle" class="w-12 h-12 text-green-300 dark:text-green-600" />
                                <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada job dalam
                                    antrian</p>
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse

                @if ($jobs->hasPages())
                    <x-slot:footer>
                        <div class="px-4 py-3">{{ $jobs->links() }}</div>
                    </x-slot:footer>
                @endif
            </x-organisms.table>
        </div>
    @endif

    {{-- Table: Failed Jobs --}}
    @if ($tab === 'failed')
        <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <x-organisms.table class="min-w-full">
                <x-slot:headings>
                    <x-atoms.table-heading>ID</x-atoms.table-heading>
                    <x-atoms.table-heading>Job</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden md:table-cell">Queue</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden lg:table-cell">Error</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden sm:table-cell">Failed At</x-atoms.table-heading>
                    <x-atoms.table-heading align="center">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($jobs as $job)
                    @php
                        $payload = json_decode($job->payload, true);
                        $jobClass = $payload['displayName'] ?? 'Unknown';
                        $shortClass = class_basename($jobClass);
                        $errorLines = explode("\n", $job->exception);
                        $shortError = $errorLines[0] ?? 'Unknown error';
                    @endphp
                    <x-molecules.table-row wire:key="failed-{{ $job->id }}">
                        <x-atoms.table-cell nowrap>
                            <span
                                class="font-mono text-sm text-zinc-600 dark:text-primary-dark-400">{{ $job->id }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 rounded bg-red-100 dark:bg-red-900/50">
                                    <flux:icon name="exclamation-triangle"
                                        class="w-4 h-4 text-red-600 dark:text-red-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                        {{ $shortClass }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 max-w-48 truncate"
                                        title="{{ $jobClass }}">{{ $jobClass }}</p>
                                </div>
                            </div>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden md:table-cell" nowrap>
                            <flux:badge color="zinc" size="sm">{{ $job->queue }}</flux:badge>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden lg:table-cell">
                            <p class="text-xs text-red-600 dark:text-red-400 max-w-64 truncate"
                                title="{{ $shortError }}">{{ Str::limit($shortError, 60) }}</p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden sm:table-cell" nowrap>
                            <div class="text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ \Carbon\Carbon::parse($job->failed_at)->format('d/m/Y') }}</div>
                            <div class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                {{ \Carbon\Carbon::parse($job->failed_at)->format('H:i:s') }}</div>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" nowrap>
                            <div class="flex items-center justify-center gap-1">
                                <x-atoms.button variant="ghost" size="sm" icon="eye"
                                    wire:click="showDetail('{{ $job->id }}', 'failed')" title="Lihat Detail" />
                                <x-atoms.button variant="ghost" size="sm" icon="arrow-path"
                                    wire:click="retryJob('{{ $job->uuid }}')" title="Retry Job" />
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="6" align="center" class="py-12">
                            <div class="flex flex-col items-center">
                                <flux:icon name="check-circle" class="w-12 h-12 text-green-300 dark:text-green-600" />
                                <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada failed jobs
                                </p>
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse

                @if ($jobs->hasPages())
                    <x-slot:footer>
                        <div class="px-4 py-3">{{ $jobs->links() }}</div>
                    </x-slot:footer>
                @endif
            </x-organisms.table>
        </div>
    @endif

    {{-- Table: Job Batches --}}
    @if ($tab === 'batches')
        <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
            <x-organisms.table class="min-w-full">
                <x-slot:headings>
                    <x-atoms.table-heading>ID</x-atoms.table-heading>
                    <x-atoms.table-heading>Name</x-atoms.table-heading>
                    <x-atoms.table-heading align="center">Progress</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden sm:table-cell" align="center">Total</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden md:table-cell" align="center">Pending</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden lg:table-cell" align="center">Failed</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden text-center" align="center">Status</x-atoms.table-heading>
                    <x-atoms.table-heading class="hidden xl:table-cell">Created</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($batches as $batch)
                    @php
                        $progress =
                            $batch->total_jobs > 0
                                ? round((($batch->total_jobs - $batch->pending_jobs) / $batch->total_jobs) * 100)
                                : 0;
                        $isFinished = $batch->finished_at !== null;
                        $isCancelled = $batch->cancelled_at !== null;
                        $hasFailed = $batch->failed_jobs > 0;
                    @endphp
                    <x-molecules.table-row wire:key="batch-{{ $batch->id }}">
                        <x-atoms.table-cell nowrap>
                            <span
                                class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">{{ Str::limit($batch->id, 8) }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <p class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                {{ $batch->name }}</p>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell nowrap>
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 rounded-full bg-zinc-200 dark:bg-primary-dark-700 w-20">
                                    <div class="h-2 rounded-full {{ $hasFailed ? 'bg-red-500' : ($isFinished ? 'bg-green-500' : 'bg-blue-500') }}"
                                        style="width: {{ $progress }}%"></div>
                                </div>
                                <span
                                    class="text-xs text-zinc-600 dark:text-primary-dark-400">{{ $progress }}%</span>
                            </div>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden sm:table-cell" align="center" nowrap>
                            <span
                                class="text-sm text-zinc-600 dark:text-primary-dark-400">{{ number_format($batch->total_jobs) }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden md:table-cell" align="center" nowrap>
                            <span
                                class="text-sm {{ $batch->pending_jobs > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-600 dark:text-primary-dark-400' }}">{{ number_format($batch->pending_jobs) }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden lg:table-cell" align="center" nowrap>
                            <span
                                class="text-sm {{ $batch->failed_jobs > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-600 dark:text-primary-dark-400' }}">{{ number_format($batch->failed_jobs) }}</span>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="center" nowrap>
                            @if ($isCancelled)
                                <flux:badge color="zinc" size="sm">Cancelled</flux:badge>
                            @elseif ($isFinished)
                                @if ($hasFailed)
                                    <flux:badge color="amber" size="sm">Completed with errors</flux:badge>
                                @else
                                    <flux:badge color="green" size="sm">Completed</flux:badge>
                                @endif
                            @else
                                <flux:badge color="blue" size="sm">Processing</flux:badge>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell class="hidden xl:table-cell" nowrap>
                            <span
                                class="text-sm text-zinc-600 dark:text-primary-dark-400">{{ \Carbon\Carbon::createFromTimestamp($batch->created_at)->diffForHumans() }}</span>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="8" align="center" class="py-12">
                            <div class="flex flex-col items-center">
                                <flux:icon name="rectangle-stack"
                                    class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada job batches
                                </p>
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse

                @if ($batches->hasPages())
                    <x-slot:footer>
                        <div class="px-4 py-3">{{ $batches->links() }}</div>
                    </x-slot:footer>
                @endif
            </x-organisms.table>
        </div>
    @endif

    {{-- Detail Modal --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="4xl" title="">
        @if ($selectedJob)
            <div class="space-y-6">
                {{-- Header --}}
                <div class="flex items-start gap-4 pb-4 border-b border-zinc-200 dark:border-primary-dark-700">
                    <div
                        class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-lg {{ $selectedJob['type'] === 'failed' ? 'bg-red-100 dark:bg-red-900/50' : 'bg-blue-100 dark:bg-blue-900/50' }}">
                        <flux:icon
                            name="{{ $selectedJob['type'] === 'failed' ? 'exclamation-triangle' : 'cog-6-tooth' }}"
                            class="w-6 h-6 {{ $selectedJob['type'] === 'failed' ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400' }}" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-primary-dark-100">
                            {{ class_basename($selectedJob['job_class']) }}
                        </h2>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <flux:badge :color="$selectedJob['type'] === 'failed' ? 'red' : 'blue'" size="sm">
                                {{ $selectedJob['type'] === 'failed' ? 'Failed' : 'Pending' }}
                            </flux:badge>
                            <flux:badge color="zinc" size="sm">
                                {{ $selectedJob['queue'] }}
                            </flux:badge>
                            @if (isset($selectedJob['attempts']))
                                <span class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                    Attempts: {{ $selectedJob['attempts'] }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Info Grid --}}
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                        <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">Job Class
                        </h4>
                        <p class="mt-1 font-mono text-sm break-all text-zinc-900 dark:text-primary-dark-100">
                            {{ $selectedJob['job_class'] }}
                        </p>
                    </div>
                    @if ($selectedJob['type'] === 'pending')
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                                Created At
                            </h4>
                            <p class="mt-1 text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedJob['created_at']->format('d M Y H:i:s') }}
                            </p>
                        </div>
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                                Available At
                            </h4>
                            <p class="mt-1 text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedJob['available_at']->format('d M Y H:i:s') }}
                            </p>
                        </div>
                        @if ($selectedJob['reserved_at'])
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                                <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                                    Reserved
                                    At</h4>
                                <p class="mt-1 text-sm text-zinc-900 dark:text-primary-dark-100">
                                    {{ $selectedJob['reserved_at']->format('d M Y H:i:s') }}
                                </p>
                            </div>
                        @endif
                    @else
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">Failed
                                At</h4>
                            <p class="mt-1 text-sm text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedJob['failed_at']->format('d M Y H:i:s') }}
                            </p>
                        </div>
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <h4 class="text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">UUID
                            </h4>
                            <p class="mt-1 font-mono text-xs text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedJob['uuid'] }}
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Exception --}}
                @if (isset($selectedJob['exception']))
                    <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                        <h4 class="text-xs font-semibold uppercase text-red-600 dark:text-red-400">Exception</h4>
                        <div class="mt-2 overflow-auto text-xs max-h-64">
                            <pre class="text-red-800 dark:text-red-200 whitespace-pre-wrap">{{ $selectedJob['exception'] }}</pre>
                        </div>
                    </div>
                @endif

                {{-- Payload --}}
                @if (isset($selectedJob['payload']))
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase text-zinc-500 dark:text-primary-dark-400">
                            Payload</h4>
                        <x-atoms.code-block language="json"
                            maxHeight="max-h-64">{{ json_encode($selectedJob['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</x-atoms.code-block>
                    </div>
                @endif

                {{-- Actions --}}
                <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-primary-dark-700">
                    <x-atoms.button variant="ghost" wire:click="closeDetail">
                        Tutup
                    </x-atoms.button>
                    @if ($selectedJob['type'] === 'failed')
                        <x-atoms.button variant="danger" icon="trash"
                            wire:click="deleteFailedJob('{{ $selectedJob['uuid'] }}')"
                            wire:confirm="Hapus failed job ini?">
                            Hapus
                        </x-atoms.button>
                        <x-atoms.button variant="primary" icon="arrow-path"
                            wire:click="retryJob('{{ $selectedJob['uuid'] }}')">
                            Retry
                        </x-atoms.button>
                    @endif
                </div>
            </div>
        @endif

    </x-organisms.modal>

    {{-- Clear Confirmation Modal --}}
    <x-organisms.modal wire:model="showClearModal" maxWidth="md" title="">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="exclamation-triangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">Konfirmasi Hapus</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        @switch($clearType)
                            @case('pending')
                                Hapus {{ number_format($pendingCount) }} pending jobs?
                            @break

                            @case('failed')
                                Hapus {{ number_format($failedCount) }} failed jobs?
                            @break

                            @case('batches')
                                Hapus {{ number_format($batchCount) }} job batches?
                            @break

                            @case('all')
                                Hapus semua data job?
                            @break
                        @endswitch
                    </p>
                </div>
            </div>

            <div class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                <p class="text-sm text-red-800 dark:text-red-200">
                    <flux:icon name="exclamation-triangle" class="inline w-4 h-4 mr-1" />
                    Aksi ini tidak dapat dibatalkan. Data yang dihapus tidak dapat dikembalikan.
                </p>
            </div>


            <x-slot:footer>
                <div class="flex justify-end gap-3">
                    <x-atoms.button variant="ghost" wire:click="closeClearModal">
                        Batal
                    </x-atoms.button>
                    <x-atoms.button variant="danger" icon="trash" wire:click="confirmClear">
                        Ya, Hapus
                    </x-atoms.button>
                </div>
            </x-slot:footer>
        </div>
    </x-organisms.modal>
</div>
