<?php

namespace App\Services;

use App\Models\SimrsVersion;
use Illuminate\Support\Facades\Storage;

class SimrsVersionService
{
    public function getActive(string $type = 'main'): ?SimrsVersion
    {
        return SimrsVersion::ofType($type)->active()->first();
    }

    /**
     * Pindahkan file dari path sementara (hasil chunk upload) ke disk simrs_updates
     * dan simpan record versi. Checksum SHA-256 dihitung server-side.
     */
    public function create(array $data, string $tempPath): SimrsVersion
    {
        $type     = $data['type'] ?? 'main';
        $version  = $data['version'];
        $destPath = "{$type}/{$version}/update.zip";

        $disk     = Storage::disk('simrs_updates');
        $destFull = $disk->path($destPath);

        if (!is_dir(dirname($destFull))) {
            mkdir(dirname($destFull), 0755, true);
        }

        rename($tempPath, $destFull);

        $checksum = hash_file('sha256', $destFull);
        $fileSize = filesize($destFull);

        return SimrsVersion::create([
            'type'        => $type,
            'version'     => $version,
            'notes'       => $data['notes'] ?? null,
            'released_at' => $data['released_at'],
            'is_active'   => false,
            'file_path'   => $destPath,
            'checksum'    => $checksum,
            'file_size'   => $fileSize,
        ]);
    }

    /** Tandai versi ini sebagai aktif (model boot menangani reset baris sesama tipe) */
    public function setActive(SimrsVersion $version): void
    {
        $version->update(['is_active' => true]);
    }

    /** Hapus file dari storage dan record dari database */
    public function delete(SimrsVersion $version): void
    {
        if ($version->file_path) {
            $dir = dirname($version->file_path);
            Storage::disk('simrs_updates')->deleteDirectory($dir);
        }

        $version->delete();
    }
}
