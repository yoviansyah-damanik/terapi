<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SimrsUpdateReport;
use App\Models\SimrsVersion;
use App\Services\SimrsVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApiSimrsVersionController extends Controller
{
    public function __construct(private SimrsVersionService $service) {}

    /**
     * Kembalikan info versi yang sedang aktif.
     * Query param ?type=main (default) atau ?type=launcher.
     * Endpoint publik — tidak memerlukan api.token.
     */
    public function version(Request $request): JsonResponse
    {
        $type   = in_array($request->query('type'), ['launcher', 'main']) ? $request->query('type') : 'main';
        $record = $this->service->getActive($type);

        if (!$record) {
            return response()->json(['message' => 'Tidak ada versi aktif'], 404);
        }

        return response()->json([
            'type'        => $record->type,
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
            'type'             => 'nullable|in:main,launcher',
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
     * Stream file update ke klien.
     * Query param ?type=main (default) atau ?type=launcher.
     * Throttle 5 request/menit untuk mencegah penyalahgunaan.
     */
    public function download(Request $request, string $version): BinaryFileResponse|JsonResponse
    {
        $type   = in_array($request->query('type'), ['launcher', 'main']) ? $request->query('type') : 'main';
        $record = SimrsVersion::where('version', $version)->where('type', $type)->first();

        if (!$record || !$record->file_path) {
            return response()->json(['message' => 'Versi tidak ditemukan'], 404);
        }

        $fullPath = Storage::disk('simrs_updates')->path($record->file_path);

        if (!file_exists($fullPath)) {
            return response()->json(['message' => 'File tidak tersedia di server'], 500);
        }

        return response()->download($fullPath, "simrs-{$type}-{$version}.zip", [
            'Content-Type'      => 'application/zip',
            'X-Checksum-SHA256' => $record->checksum ?? '',
        ]);
    }
}
