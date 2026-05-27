<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Api\ApiSimrsLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API Controller untuk menerima dan mengambil log dari aplikasi SIMRS.
 */
class SimrsLogController extends Controller
{
    /**
     * Kirim satu entri log dari aplikasi SIMRS.
     *
     * POST /api/simrs/log
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'error_id'            => 'nullable|string|max:100|unique:api_simrs_logs,error_id',
            'message'             => 'required|string|max:5000',
            'level'               => 'nullable|in:error,warning,info,debug',
            'category'            => 'nullable|string|max:100',
            'module'              => 'nullable|string|max:100',
            'exception_class'     => 'nullable|string|max:255',
            'stack_trace'         => 'nullable|string',
            'app_version'         => 'nullable|string|max:50',
            'host_name'           => 'nullable|string|max:100',
            'simrs_user'          => 'nullable|string|max:100',
            'simrs_user_role'     => 'nullable|string|max:100',
            'db_host'             => 'nullable|string|max:100',
            'db_name'             => 'nullable|string|max:100',
            'db_connected'        => 'nullable|boolean',
            'db_response_time_ms' => 'nullable|integer|min:0',
            'context'             => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['ip_address'] = $request->ip();

        $log = ApiSimrsLog::record($data);

        return response()->json([
            'success' => true,
            'message' => 'Log berhasil disimpan',
            'id'      => $log->id,
        ], 201);
    }

    /**
     * Kirim beberapa entri log sekaligus (batch).
     *
     * POST /api/simrs/log/batch
     */
    public function storeBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logs'                         => 'required|array|min:1|max:100',
            'logs.*.error_id'              => 'nullable|string|max:100|distinct|unique:api_simrs_logs,error_id',
            'logs.*.message'               => 'required|string|max:5000',
            'logs.*.level'                 => 'nullable|in:error,warning,info,debug',
            'logs.*.category'              => 'nullable|string|max:100',
            'logs.*.module'                => 'nullable|string|max:100',
            'logs.*.exception_class'       => 'nullable|string|max:255',
            'logs.*.stack_trace'           => 'nullable|string',
            'logs.*.app_version'           => 'nullable|string|max:50',
            'logs.*.host_name'             => 'nullable|string|max:100',
            'logs.*.simrs_user'            => 'nullable|string|max:100',
            'logs.*.simrs_user_role'       => 'nullable|string|max:100',
            'logs.*.db_host'               => 'nullable|string|max:100',
            'logs.*.db_name'               => 'nullable|string|max:100',
            'logs.*.db_connected'          => 'nullable|boolean',
            'logs.*.db_response_time_ms'   => 'nullable|integer|min:0',
            'logs.*.context'               => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ipAddress = $request->ip();
        $count = 0;

        foreach ($validator->validated()['logs'] as $entry) {
            $entry['ip_address'] = $ipAddress;
            ApiSimrsLog::record($entry);
            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} log berhasil disimpan",
            'count'   => $count,
        ], 201);
    }

    /**
     * Ambil daftar log (paginasi).
     *
     * GET /api/simrs/logs
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApiSimrsLog::orderByDesc('created_at');

        if ($request->filled('level')) {
            $query->forLevel($request->level);
        }

        if ($request->filled('category')) {
            $query->forCategory($request->category);
        }

        if ($request->filled('module')) {
            $query->forModule($request->module);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $logs->items(),
            'meta'    => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    /**
     * Ambil detail satu log.
     *
     * GET /api/simrs/logs/{id}
     */
    public function show(string $id): JsonResponse
    {
        $log = ApiSimrsLog::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $log,
        ]);
    }
}
