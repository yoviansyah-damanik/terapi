<?php

namespace App\Console\Commands;

use App\Jobs\DatabaseBackupJob;
use Illuminate\Console\Command;

class DatabaseBackup extends Command
{
    protected $signature   = 'database:backup {connection=all : Koneksi yang dibackup: terapi, simrs, atau all}';
    protected $description = 'Backup database Terapi dan/atau SIMRS ke storage lokal';

    public function handle(): int
    {
        $connection = $this->argument('connection');
        $targets    = match ($connection) {
            'terapi' => ['terapi'],
            'simrs'  => ['simrs'],
            default  => ['terapi', 'simrs'],
        };

        foreach ($targets as $conn) {
            $this->line("  Menjalankan backup <fg=cyan>{$conn}</>...");
            dispatch_sync(new DatabaseBackupJob($conn));
            $this->info("  Backup {$conn} selesai.");
        }

        return Command::SUCCESS;
    }
}
