<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Dicom\Worklist;
use App\Services\Dicom\DicomWorklistService;
use App\Services\Dicom\OrthancService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DicomWorklistController extends Controller
{
    public function __construct(
        private readonly DicomWorklistService $worklistService,
        private readonly OrthancService $orthanc,
    ) {
    }

    /**
     * POST /api/v1/worklists/batch
     * Terima batch worklist dari SIMRS Java, kirim ke Orthanc, simpan ke dicom_studies.
     */
    public function batch(Request $request): JsonResponse
    {
        $items = $request->json()->all();

        // Normalisasi input: jika user mengirimkan objek tunggal, bungkus dalam array agar tetap batch-compatible
        if (is_array($items) && !isset($items[0]) && !empty($items)) {
            $items = [$items];
        }

        if (empty($items) || !is_array($items)) {
            return response()->json([
                'success' => false,
                'message' => 'Body harus berupa JSON array atau objek tunggal yang valid.',
            ], 422);
        }

        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($request->all() as $index => $item) {
            $validator = \Illuminate\Support\Facades\Validator::make($item, [
                'accession_number' => 'required|string',
                'type'             => 'nullable|string|in:radiologi,usg',
                'bypass'           => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                $results[] = [
                    'index' => $index,
                    'accession_number' => $item['accession_number'] ?? null,
                    'success' => false,
                    'message' => 'Validasi gagal: ' . implode(', ', $validator->errors()->all()),
                ];
                $failedCount++;
                continue;
            }

            try {
                $noorder = $item['accession_number'];
                $type    = $item['type'] ?? 'radiologi';
                $bypass  = $item['bypass'] ?? false;

                $splitResults = $this->worklistService->processSimrsOrder($noorder, $bypass, $type);
                
                foreach ($splitResults as $res) {
                    $results[] = $res;
                    if ($res['success']) {
                        $successCount++;
                    } else {
                        $failedCount++;
                    }
                }
            } catch (\Exception $e) {
                $results[] = [
                    'accession_number' => $item['accession_number'],
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
                $failedCount++;
            }
        }

        $success = $successCount > 0 && $failedCount === 0;
        $message = "{$successCount} order berhasil disimpan";
        if ($failedCount > 0) {
            $message .= ", {$failedCount} gagal";
        }

        return response()->json([
            'success' => $success || ($successCount > 0),
            'message' => $message,
            'data' => [
                'total' => count($items),
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'results' => $results,
            ],
        ], $failedCount === count($items) ? 422 : 200);
    }

    /**
     * POST /api/v1/worklists
     * Kirim satu worklist item.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'accession_number' => 'required|string|max:64',
            'type'             => 'nullable|string|in:radiologi,usg',
            'bypass'           => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $noorder = $request->accession_number;
            $type    = $request->type ?? 'radiologi';
            $bypass  = $request->bypass ?? false;

            $splitResults = $this->worklistService->processSimrsOrder($noorder, $bypass, $type);
            
            $success = collect($splitResults)->where('success', true)->isNotEmpty();
            $message = $success ? 'Order berhasil diproses' : 'Gagal memproses order';
            
            return response()->json([
                'success' => $success,
                'message' => $message,
                'details' => $splitResults
            ], $success ? 200 : 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/worklists/{noorder}
     * Detail satu worklist + status dari Orthanc jika tersedia.
     */
    public function show(string $accessionNumber): JsonResponse
    {
        $study = Worklist::where('accession_number', $accessionNumber)->first();

        if (!$study) {
            return response()->json([
                'success' => false,
                'message' => 'Worklist tidak ditemukan.',
            ], 404);
        }

        $data = $study->toArray();

        // Ambil status real-time dari Orthanc jika sudah ada gambar
        if ($study->orthanc_study_id && $this->orthanc->isConfigured()) {
            $orthancDetail = $this->orthanc->getStudy($study->orthanc_study_id);
            if ($orthancDetail['success']) {
                $data['orthanc'] = [
                    'series_count' => count($orthancDetail['data']['Series'] ?? []),
                    'is_stable' => $orthancDetail['data']['IsStable'] ?? null,
                    'last_update' => $orthancDetail['data']['LastUpdate'] ?? null,
                ];
            }
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * DELETE /api/v1/worklists/{noorder}
     * Hapus worklist dari DicomStudy lokal (tidak menghapus dari Orthanc).
     */
    public function destroy(string $accessionNumber): JsonResponse
    {
        $study = Worklist::where('accession_number', $accessionNumber)->first();

        if (!$study) {
            return response()->json([
                'success' => false,
                'message' => 'Worklist tidak ditemukan.',
            ], 404);
        }

        $study->delete();

        return response()->json([
            'success' => true,
            'message' => 'Worklist berhasil dihapus.',
        ]);
    }
}
