<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimrsUpdateReport;
use App\Models\SimrsVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApiSimrsVersionController extends Controller
{
    /**
     * Kembalikan info versi SIMRS yang sedang aktif.
     * Endpoint publik — tidak memerlukan api.token.
     */
    public function version(): JsonResponse
    {
        $record = SimrsVersion::active()->first();

        if (!$record) {
            return response()->json(['message' => 'Tidak ada versi aktif'], 404);
        }

        return response()->json([
            'version'     => $record->version,
            'notes'       => $record->notes,
            'checksum'    => $record->checksum,
            'file_size'   => $record->file_size,
            'released_at' => $record->released_at->toIso8601String(),
        ]);
    }

    /**
     * Terima laporan hasil update dari SIMRS.
     */
    public function reportUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status'           => 'required|in:success,failed,rollback',
            'from_version'     => 'nullable|string|max:20',
            'to_version'       => 'nullable|string|max:20',
            'error_message'    => 'nullable|string|max:2000',
            'duration_seconds' => 'nullable|integer|min:0|max:86400',
            'host_name'        => 'nullable|string|max:100',
            'app_name'         => 'nullable|string|max:100',
        ]);

        $apiUser = $request->attributes->get('api_user');

        SimrsUpdateReport::create([
            ...$data,
            'api_user_id'   => $apiUser?->id,
            'api_user_name' => $apiUser?->name,
            'ip_address'    => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Laporan update berhasil dicatat.',
        ]);
    }

    /**
     * Stream file update SIMRS ke klien.
     * Throttle 5 request/menit untuk mencegah penyalahgunaan.
     */
    public function download(string $version): BinaryFileResponse|JsonResponse
    {
        $record = SimrsVersion::where('version', $version)->first();

        if (!$record || !$record->file_path) {
            return response()->json(['message' => 'Versi tidak ditemukan'], 404);
        }

        $fullPath = Storage::disk('simrs_updates')->path($record->file_path);

        if (!file_exists($fullPath)) {
            return response()->json(['message' => 'File tidak tersedia di server'], 500);
        }

        return response()->download($fullPath, "simrs-{$version}.zip", [
            'Content-Type'        => 'application/zip',
            'X-Checksum-SHA256'   => $record->checksum ?? '',
        ]);
    }
}
