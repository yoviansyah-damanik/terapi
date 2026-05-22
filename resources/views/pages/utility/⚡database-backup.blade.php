<?php

use App\Helpers\ConfigurationHelper;
use App\Jobs\DatabaseBackupJob;
use App\Models\DatabaseBackup;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Backup Database')] class extends Component {

    public string $activeTab = 'terapi';

    // Scheduler form
    public string $terapiSchedulePreset = 'daily';
    public string $terapiScheduleHour   = '2';
    public string $terapiScheduleDay    = '1';
    public string $terapiScheduleCron   = '0 2 * * *';
    public bool   $terapiEnabled        = false;

    public string $simrsSchedulePreset = 'daily';
    public string $simrsScheduleHour   = '3';
    public string $simrsScheduleDay    = '1';
    public string $simrsScheduleCron   = '0 3 * * *';
    public bool   $simrsEnabled        = false;

    // Retensi form
    public int $terapiKeep          = 7;
    public int $terapiRetentionDays = 0;
    public int $simrsKeep           = 7;
    public int $simrsRetentionDays  = 0;

    // Modal log
    public bool             $showLogModal  = false;
    public ?DatabaseBackup  $viewingBackup = null;

    // Konfirmasi hapus
    public bool    $showDeleteModal = false;
    public ?string $deleteId        = null;

    public function mount(): void
    {
        $this->terapiEnabled        = (bool) ConfigurationHelper::get('backup.terapi.enabled', false);
        $this->terapiScheduleCron   = ConfigurationHelper::get('backup.terapi.schedule', '0 2 * * *');
        $this->terapiKeep           = (int) ConfigurationHelper::get('backup.terapi.keep', 7);
        $this->terapiRetentionDays  = (int) ConfigurationHelper::get('backup.terapi.retention_days', 0);

        $this->simrsEnabled         = (bool) ConfigurationHelper::get('backup.simrs.enabled', false);
        $this->simrsScheduleCron    = ConfigurationHelper::get('backup.simrs.schedule', '0 3 * * *');
        $this->simrsKeep            = (int) ConfigurationHelper::get('backup.simrs.keep', 7);
        $this->simrsRetentionDays   = (int) ConfigurationHelper::get('backup.simrs.retention_days', 0);

        $this->syncPresetFromCron('terapi');
        $this->syncPresetFromCron('simrs');
    }

    private function syncPresetFromCron(string $conn): void
    {
        $cron = $conn === 'terapi' ? $this->terapiScheduleCron : $this->simrsScheduleCron;
        $parts = explode(' ', $cron);
        if (count($parts) !== 5) return;

        [$min, $hour, $dom, $month, $dow] = $parts;

        if ($min === '0' && $dom === '*' && $month === '*' && $dow === '*') {
            if ($hour === '*') {
                $this->{$conn . 'SchedulePreset'} = 'hourly';
            } else {
                $this->{$conn . 'SchedulePreset'} = 'daily';
                $this->{$conn . 'ScheduleHour'}   = $hour;
            }
        } elseif ($min === '0' && $dom === '*' && $month === '*' && $dow !== '*') {
            $this->{$conn . 'SchedulePreset'} = 'weekly';
            $this->{$conn . 'ScheduleHour'}   = $hour;
            $this->{$conn . 'ScheduleDay'}    = $dow;
        } else {
            $this->{$conn . 'SchedulePreset'} = 'custom';
        }
    }

    public function updatedTerapiSchedulePreset(): void { $this->rebuildCron('terapi'); }
    public function updatedTerapiScheduleHour(): void   { $this->rebuildCron('terapi'); }
    public function updatedTerapiScheduleDay(): void    { $this->rebuildCron('terapi'); }
    public function updatedSimrsSchedulePreset(): void  { $this->rebuildCron('simrs'); }
    public function updatedSimrsScheduleHour(): void    { $this->rebuildCron('simrs'); }
    public function updatedSimrsScheduleDay(): void     { $this->rebuildCron('simrs'); }

    private function rebuildCron(string $conn): void
    {
        $preset = $this->{$conn . 'SchedulePreset'};
        $hour   = (int) ($this->{$conn . 'ScheduleHour'} ?? 2);
        $day    = (int) ($this->{$conn . 'ScheduleDay'}  ?? 1);

        $this->{$conn . 'ScheduleCron'} = match ($preset) {
            'hourly' => '0 * * * *',
            'daily'  => "0 {$hour} * * *",
            'weekly' => "0 {$hour} * * {$day}",
            default  => $this->{$conn . 'ScheduleCron'},
        };
    }

    public function saveSchedule(string $connection): void
    {
        $cron    = $this->{$connection . 'ScheduleCron'};
        $enabled = $this->{$connection . 'Enabled'};

        if (!$this->isValidCron($cron)) {
            $this->toastError('Format cron tidak valid.');
            return;
        }

        ConfigurationHelper::set("backup.{$connection}.enabled",  $enabled ? '1' : '0');
        ConfigurationHelper::set("backup.{$connection}.schedule", $cron);
        $this->toastSuccess('Jadwal backup berhasil disimpan.');
    }

    public function saveRetention(string $connection): void
    {
        $keep           = max(1, (int) $this->{$connection . 'Keep'});
        $retentionDays  = max(0, (int) $this->{$connection . 'RetentionDays'});

        ConfigurationHelper::set("backup.{$connection}.keep",           (string) $keep);
        ConfigurationHelper::set("backup.{$connection}.retention_days", (string) $retentionDays);
        $this->toastSuccess('Pengaturan retensi berhasil disimpan.');
    }

    public function triggerBackup(string $connection): void
    {
        $running = DatabaseBackup::forConnection($connection)
            ->where('status', 'running')
            ->exists();

        if ($running) {
            $this->toastWarning('Backup sedang berjalan. Tunggu hingga selesai.');
            return;
        }

        DatabaseBackupJob::dispatch($connection);
        $this->toastSuccess("Backup {$connection} dijadwalkan. Halaman akan diperbarui otomatis.");
    }

    public function viewLog(string $id): void
    {
        $this->viewingBackup = DatabaseBackup::find($id);
        $this->showLogModal  = true;
    }

    public function downloadBackup(string $id): mixed
    {
        $backup = DatabaseBackup::find($id);
        if (!$backup || !$backup->filename) {
            $this->toastError('File backup tidak ditemukan.');
            return null;
        }

        $path = $backup->connection . '/' . $backup->filename;
        $disk = Storage::disk('database_backups');

        if (!$disk->exists($path)) {
            $this->toastError('File tidak ditemukan di storage.');
            return null;
        }

        return response()->streamDownload(function () use ($disk, $path) {
            echo $disk->get($path);
        }, $backup->filename, ['Content-Type' => 'application/gzip']);
    }

    public function confirmDelete(string $id): void
    {
        $this->deleteId        = $id;
        $this->showDeleteModal = true;
    }

    public function deleteBackup(): void
    {
        $backup = DatabaseBackup::find($this->deleteId);
        if (!$backup) {
            $this->showDeleteModal = false;
            return;
        }

        if ($backup->filename) {
            $path = $backup->connection . '/' . $backup->filename;
            $disk = Storage::disk('database_backups');
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }

        $backup->delete();
        $this->showDeleteModal = false;
        $this->deleteId        = null;
        $this->toastSuccess('Backup berhasil dihapus.');
    }

    private function isValidCron(string $cron): bool
    {
        $parts = explode(' ', trim($cron));
        return count($parts) === 5;
    }

    private function totalStorageUsed(string $connection): string
    {
        $dir  = storage_path('app/database-backups/' . $connection);
        if (!is_dir($dir)) return '0 B';

        $bytes = array_sum(array_map('filesize', glob("{$dir}/*")));
        if ($bytes >= 1_048_576) return round($bytes / 1_048_576, 2) . ' MB';
        if ($bytes >= 1_024)    return round($bytes / 1_024, 1) . ' KB';
        return $bytes . ' B';
    }

    public function with(): array
    {
        $terapiBackups = DatabaseBackup::forConnection('terapi')->latest('started_at')->limit(20)->get();
        $simrsBackups  = DatabaseBackup::forConnection('simrs')->latest('started_at')->limit(20)->get();

        $terapiRunning = $terapiBackups->first()?->status === 'running';
        $simrsRunning  = $simrsBackups->first()?->status  === 'running';

        $terapiLast    = $terapiBackups->first(fn($b) => $b->status === 'success');
        $simrsLast     = $simrsBackups->first(fn($b) => $b->status  === 'success');

        return [
            'terapiBackups'    => $terapiBackups,
            'simrsBackups'     => $simrsBackups,
            'terapiRunning'    => $terapiRunning,
            'simrsRunning'     => $simrsRunning,
            'terapiLast'       => $terapiLast,
            'simrsLast'        => $simrsLast,
            'terapiStorage'    => $this->totalStorageUsed('terapi'),
            'simrsStorage'     => $this->totalStorageUsed('simrs'),
        ];
    }
};
?>

<div @if($terapiRunning || $simrsRunning) wire:poll.5s @endif>
    <x-ui.page-header title="Backup Database" subtitle="Backup otomatis & manual untuk database Terapi dan SIMRS">
        <x-slot:actions>
            <x-atoms.button
                wire:click="triggerBackup('{{ $activeTab }}')"
                wire:loading.attr="disabled"
                wire:target="triggerBackup"
                icon="circle-stack"
                :disabled="($activeTab === 'terapi' && $terapiRunning) || ($activeTab === 'simrs' && $simrsRunning)">
                <span wire:loading.remove wire:target="triggerBackup">Backup Sekarang</span>
                <span wire:loading wire:target="triggerBackup">Memproses...</span>
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-organisms.stat-card
            title="Total Backup Terapi"
            :value="$terapiBackups->count()"
            icon="circle-stack"
            color="primary" />
        <x-organisms.stat-card
            title="Total Backup SIMRS"
            :value="$simrsBackups->count()"
            icon="circle-stack"
            color="sky" />
        <x-organisms.stat-card
            title="Storage Terapi"
            :value="$terapiStorage"
            icon="server"
            color="zinc" />
        <x-organisms.stat-card
            title="Storage SIMRS"
            :value="$simrsStorage"
            icon="server"
            color="zinc" />
    </div>

    {{-- Tab --}}
    <div class="flex gap-1 p-1 mb-5 rounded-xl bg-zinc-100 dark:bg-primary-dark-900/60 w-fit">
        @foreach(['terapi' => 'Terapi (Utama)', 'simrs' => 'SIMRS'] as $tab => $label)
            <button wire:click="$set('activeTab', '{{ $tab }}')"
                class="flex items-center gap-2 px-5 py-2 text-sm font-semibold rounded-lg transition-all duration-200
                    {{ $activeTab === $tab
                        ? 'bg-white dark:bg-primary-dark-700 text-primary-700 dark:text-primary-300 shadow-sm ring-1 ring-zinc-200/70 dark:ring-primary-dark-600/50'
                        : 'text-zinc-500 dark:text-primary-dark-400 hover:text-zinc-700 dark:hover:text-primary-dark-200' }}">
                <flux:icon name="circle-stack" class="w-4 h-4" />
                {{ $label }}
                @php $isRunning = $tab === 'terapi' ? $terapiRunning : $simrsRunning; @endphp
                @if ($isRunning)
                    <span class="flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-2 w-2 rounded-full bg-blue-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                    </span>
                @endif
            </button>
        @endforeach
    </div>

    @foreach(['terapi', 'simrs'] as $conn)
        @php
            $backups       = $conn === 'terapi' ? $terapiBackups        : $simrsBackups;
            $running       = $conn === 'terapi' ? $terapiRunning        : $simrsRunning;
            $lastSuccess   = $conn === 'terapi' ? $terapiLast           : $simrsLast;
            $connEnabled   = $conn === 'terapi' ? $terapiEnabled        : $simrsEnabled;
            $connPreset    = $conn === 'terapi' ? $terapiSchedulePreset : $simrsSchedulePreset;
            $connCron      = $conn === 'terapi' ? $terapiScheduleCron   : $simrsScheduleCron;
        @endphp
        <div @class(['hidden' => $activeTab !== $conn, 'space-y-5' => true])>

            {{-- Status terakhir --}}
            @if ($running)
                <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800/40">
                    <flux:icon.loading class="w-5 h-5 text-blue-500 shrink-0" />
                    <p class="text-sm text-blue-700 dark:text-blue-300">Backup sedang berjalan... Halaman diperbarui otomatis setiap 5 detik.</p>
                </div>
            @elseif ($lastSuccess)
                <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800/40">
                    <flux:icon name="check-circle" variant="solid" class="w-5 h-5 text-green-500 shrink-0" />
                    <p class="text-sm text-green-700 dark:text-green-300">
                        Backup terakhir: <strong>{{ $lastSuccess->started_at->format('d/m/Y H:i') }}</strong>
                        — {{ $lastSuccess->file_size_human }}
                    </p>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
                {{-- Jadwal --}}
                <x-organisms.card title="Jadwal Otomatis" subtitle="Konfigurasi backup terjadwal otomatis" :padding="false">
                    <div class="p-5 space-y-4">
                        <div class="flex items-center justify-between py-1">
                            <div>
                                <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-200">Aktifkan Scheduler</p>
                                <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-0.5">Jadwal berjalan via <span class="font-mono">schedule:run</span></p>
                            </div>
                            <flux:switch wire:model.live="{{ $conn }}Enabled" />
                        </div>

                        <flux:select wire:model.live="{{ $conn }}SchedulePreset" label="Frekuensi" :disabled="!$connEnabled">
                            <flux:select.option value="hourly">Setiap Jam</flux:select.option>
                            <flux:select.option value="daily">Harian</flux:select.option>
                            <flux:select.option value="weekly">Mingguan</flux:select.option>
                            <flux:select.option value="custom">Custom Cron</flux:select.option>
                        </flux:select>

                        @if ($connPreset === 'daily' || $connPreset === 'weekly')
                            <flux:select wire:model.live="{{ $conn }}ScheduleHour" label="Jam Eksekusi" :disabled="!$connEnabled">
                                @for ($h = 0; $h < 24; $h++)
                                    <flux:select.option value="{{ $h }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}:00</flux:select.option>
                                @endfor
                            </flux:select>
                        @endif

                        @if ($connPreset === 'weekly')
                            <flux:select wire:model.live="{{ $conn }}ScheduleDay" label="Hari" :disabled="!$connEnabled">
                                @foreach(['0' => 'Minggu', '1' => 'Senin', '2' => 'Selasa', '3' => 'Rabu', '4' => 'Kamis', '5' => 'Jumat', '6' => 'Sabtu'] as $d => $dayLabel)
                                    <flux:select.option value="{{ $d }}">{{ $dayLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        @if ($connPreset === 'custom')
                            <flux:input wire:model.live="{{ $conn }}ScheduleCron" label="Cron Expression"
                                placeholder="0 2 * * *" :disabled="!$connEnabled" />
                        @endif

                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50 border border-zinc-200 dark:border-primary-dark-700">
                            <flux:icon name="clock" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                            <span class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">{{ $connCron }}</span>
                        </div>
                    </div>
                    <x-slot:footer>
                        <x-atoms.button wire:click="saveSchedule('{{ $conn }}')" icon="check">
                            Simpan Jadwal
                        </x-atoms.button>
                    </x-slot:footer>
                </x-organisms.card>

                {{-- Retensi --}}
                <x-organisms.card title="Pengaturan Retensi" subtitle="Atur masa simpan dan jumlah backup yang dipertahankan" :padding="false">
                    <div class="p-5 space-y-4">
                        <flux:input
                            wire:model="{{ $conn }}Keep"
                            type="number"
                            label="Pertahankan N backup terakhir"
                            min="1" max="365"
                            description="Backup lama di atas batas ini akan dihapus otomatis." />

                        <flux:input
                            wire:model="{{ $conn }}RetentionDays"
                            type="number"
                            label="Hapus backup lebih dari N hari"
                            min="0" max="3650"
                            description="Set 0 untuk menonaktifkan. Backup kadaluarsa dihapus saat backup baru dibuat." />
                    </div>
                    <x-slot:footer>
                        <x-atoms.button wire:click="saveRetention('{{ $conn }}')" icon="check">
                            Simpan Retensi
                        </x-atoms.button>
                    </x-slot:footer>
                </x-organisms.card>
            </div>

            {{-- Riwayat --}}
            <div class="bg-white dark:bg-primary-dark-800 rounded-xl border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-zinc-100 dark:border-primary-dark-700">
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100 flex items-center gap-2">
                        <flux:icon name="list-bullet" class="w-4 h-4 text-zinc-400" />
                        Riwayat Backup
                        <flux:badge color="zinc" size="sm">{{ $backups->count() }}</flux:badge>
                    </h3>
                </div>

                @if ($backups->isEmpty())
                    <div class="flex flex-col items-center py-12">
                        <flux:icon name="circle-stack" class="w-10 h-10 text-zinc-300 dark:text-primary-dark-600 mb-2" />
                        <p class="text-sm text-zinc-500 dark:text-primary-dark-400">Belum ada backup.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
                            <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">Status</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">File</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">Ukuran</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">Waktu</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">Durasi</th>
                                    <th class="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                                @foreach ($backups as $backup)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                                        <td class="px-4 py-3">
                                            <flux:badge color="{{ $backup->status_color }}" size="sm">
                                                @if ($backup->status === 'running')
                                                    <flux:icon.loading class="w-3 h-3 mr-1" />
                                                @endif
                                                {{ $backup->status_label }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-primary-dark-300 max-w-xs truncate">
                                            {{ $backup->filename ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-zinc-500 dark:text-primary-dark-400">
                                            {{ $backup->file_size ? $backup->file_size_human : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-xs text-zinc-500 dark:text-primary-dark-400">
                                            <div>{{ $backup->started_at->format('d/m/Y') }}</div>
                                            <div class="font-mono">{{ $backup->started_at->format('H:i:s') }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-zinc-500 dark:text-primary-dark-400">
                                            {{ $backup->duration ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button wire:click="viewLog('{{ $backup->id }}')"
                                                    class="p-1.5 rounded-lg text-zinc-400 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-900/20 transition-colors"
                                                    title="Lihat Log">
                                                    <flux:icon name="document-text" class="w-4 h-4" />
                                                </button>
                                                @if ($backup->status === 'success' && $backup->filename)
                                                    <button wire:click="downloadBackup('{{ $backup->id }}')"
                                                        class="p-1.5 rounded-lg text-zinc-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors"
                                                        title="Download">
                                                        <flux:icon name="arrow-down-tray" class="w-4 h-4" />
                                                    </button>
                                                @endif
                                                @if ($backup->status !== 'running')
                                                    <button wire:click="confirmDelete('{{ $backup->id }}')"
                                                        class="p-1.5 rounded-lg text-zinc-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                                        title="Hapus">
                                                        <flux:icon name="trash" class="w-4 h-4" />
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    {{-- Modal Log --}}
    <x-organisms.modal wire:model="showLogModal" maxWidth="md" title="Log Backup">
        @if ($viewingBackup)
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-zinc-50 dark:bg-primary-dark-900/50 rounded-lg p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 mb-1">Status</p>
                        <flux:badge color="{{ $viewingBackup->status_color }}" size="sm">{{ $viewingBackup->status_label }}</flux:badge>
                    </div>
                    <div class="bg-zinc-50 dark:bg-primary-dark-900/50 rounded-lg p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 mb-1">Database</p>
                        <p class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100 capitalize">{{ $viewingBackup->connection }}</p>
                    </div>
                    <div class="bg-zinc-50 dark:bg-primary-dark-900/50 rounded-lg p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 mb-1">Mulai</p>
                        <p class="text-xs font-mono text-zinc-700 dark:text-primary-dark-200">{{ $viewingBackup->started_at->format('d/m/Y H:i:s') }}</p>
                    </div>
                    <div class="bg-zinc-50 dark:bg-primary-dark-900/50 rounded-lg p-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 mb-1">Selesai</p>
                        <p class="text-xs font-mono text-zinc-700 dark:text-primary-dark-200">
                            {{ $viewingBackup->completed_at?->format('d/m/Y H:i:s') ?? '—' }}
                        </p>
                    </div>
                </div>

                @if ($viewingBackup->duration)
                    <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-primary-dark-300">
                        <flux:icon name="clock" class="w-4 h-4 text-zinc-400" />
                        Durasi: <strong>{{ $viewingBackup->duration }}</strong>
                    </div>
                @endif

                @if ($viewingBackup->filename)
                    <div class="space-y-1">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400">File</p>
                        <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50 border border-zinc-200 dark:border-primary-dark-700">
                            <span class="font-mono text-xs text-zinc-700 dark:text-primary-dark-200 truncate">{{ $viewingBackup->filename }}</span>
                            <flux:badge color="zinc" size="sm">{{ $viewingBackup->file_size_human }}</flux:badge>
                        </div>
                    </div>
                @endif

                @if ($viewingBackup->error_message)
                    <div class="space-y-1">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-red-500">Error</p>
                        <div class="px-3 py-2.5 rounded-lg bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800/40">
                            <p class="text-xs text-red-700 dark:text-red-300 font-mono whitespace-pre-wrap">{{ $viewingBackup->error_message }}</p>
                        </div>
                    </div>
                @endif
            </div>
        @endif
        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button wire:click="$set('showLogModal', false)" variant="ghost">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Konfirmasi Hapus --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="sm" title="Hapus Backup"
        description="File backup dan riwayatnya akan dihapus permanen.">
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showDeleteModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="deleteBackup" variant="danger" icon="trash">Hapus</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
