<?php

namespace App\Jobs;

use App\Helpers\ConfigurationHelper;
use App\Models\DatabaseBackup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class DatabaseBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public string $dbConnection)
    {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        $record = DatabaseBackup::create([
            'connection' => $this->dbConnection,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $filename = $this->runBackup();

            $disk = Storage::disk('database_backups');
            $filePath = $this->dbConnection . '/' . $filename;
            $fileSize = $disk->exists($filePath) ? $disk->size($filePath) : null;

            $record->update([
                'status' => 'success',
                'filename' => $filename,
                'file_size' => $fileSize,
                'completed_at' => now(),
            ]);

            $this->pruneOldBackups();
        } catch (\Throwable $e) {
            $record->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    private function runBackup(): string
    {
        $timestamp = now()->format('Y_m_d_H_i_s');
        $connKey = $this->dbConnection === 'terapi' ? config('database.default') : 'simrs';
        $driver = config("database.connections.{$connKey}.driver");

        $dir = storage_path('app/database-backups/' . $this->dbConnection);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($driver === 'sqlite') {
            return $this->backupSqlite($connKey, $timestamp, $dir);
        }

        return $this->backupMysql($connKey, $timestamp, $dir);
    }

    private function backupMysql(string $connKey, string $timestamp, string $dir): string
    {
        $host = config("database.connections.{$connKey}.host");
        $port = config("database.connections.{$connKey}.port", 3306);
        $database = config("database.connections.{$connKey}.database");
        $username = config("database.connections.{$connKey}.username");
        $password = config("database.connections.{$connKey}.password");

        $filename = "{$database}_{$timestamp}.sql.gz";
        $filePath = $dir . '/' . $filename;

        $mysqldump = 'mysqldump';
        $args = [
            $mysqldump,
            "--host={$host}",
            "--port={$port}",
            "--user={$username}",
            "--single-transaction",
            "--routines",
            "--triggers",
            $database,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = array_merge($_ENV, ['MYSQL_PWD' => $password]);
        $proc = proc_open($args, $descriptors, $pipes, null, $env);

        if (!is_resource($proc)) {
            throw new \RuntimeException('Gagal menjalankan mysqldump.');
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        if ($exitCode !== 0) {
            throw new \RuntimeException("mysqldump gagal (exit {$exitCode}): {$stderr}");
        }

        $gz = gzopen($filePath, 'wb9');
        gzwrite($gz, $output);
        gzclose($gz);

        return $filename;
    }

    private function backupSqlite(string $connKey, string $timestamp, string $dir): string
    {
        $dbPath = config("database.connections.{$connKey}.database");
        $filename = 'terapi_' . $timestamp . '.sqlite.gz';
        $filePath = $dir . '/' . $filename;

        if (!file_exists($dbPath)) {
            throw new \RuntimeException("File SQLite tidak ditemukan: {$dbPath}");
        }

        $content = file_get_contents($dbPath);
        $gz = gzopen($filePath, 'wb9');
        gzwrite($gz, $content);
        gzclose($gz);

        return $filename;
    }

    private function pruneOldBackups(): void
    {
        $keep = (int) ConfigurationHelper::get("backup.{$this->dbConnection}.keep", 7);
        $retentionDays = (int) ConfigurationHelper::get("backup.{$this->dbConnection}.retention_days", 0);
        $disk = Storage::disk('database_backups');

        // Hapus berdasarkan jumlah — pertahankan $keep terbaru
        if ($keep > 0) {
            $toDelete = DatabaseBackup::forConnection($this->dbConnection)
                ->successful()
                ->orderByDesc('started_at')
                ->skip($keep)
                ->take(PHP_INT_MAX)
                ->get();

            foreach ($toDelete as $backup) {
                $this->deleteBackupFile($disk, $backup);
            }
        }

        // Hapus berdasarkan kadaluarsa
        if ($retentionDays > 0) {
            $cutoff = now()->subDays($retentionDays);
            $expired = DatabaseBackup::forConnection($this->dbConnection)
                ->where('started_at', '<', $cutoff)
                ->get();

            foreach ($expired as $backup) {
                $this->deleteBackupFile($disk, $backup);
            }
        }
    }

    private function deleteBackupFile(\Illuminate\Contracts\Filesystem\Filesystem $disk, DatabaseBackup $backup): void
    {
        if ($backup->filename) {
            $path = $this->dbConnection . '/' . $backup->filename;
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
        $backup->delete();
    }
}
