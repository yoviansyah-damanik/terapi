<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ConfigurationHelper;
use App\Http\Controllers\Controller;
use App\Models\Dicom\DicomStudy;
use App\Models\Dicom\Worklist;
use App\Models\Api\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrthancWebhookController extends Controller
{
    /**
     * Menerima webhook dari Orthanc-Sync (Django).
     */
    public function handle(Request $request)
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $startTime = microtime(true);
        $accessionNumber = $request->input('accession_number');
        $status = $request->input('status'); // "Berhasil" / "Gagal"
        $message = $request->input('message');

        Log::info('Orthanc Webhook received', $request->all());

        if (!$accessionNumber) {
            $response = response()->json(['success' => false, 'message' => 'Missing accession_number'], 400);
            return $this->logAndReturn($request, $response, $startTime);
        }

        $study = Worklist::where('accession_number', $accessionNumber)->first();

        if (!$study) {
            $response = response()->json(['success' => false, 'message' => 'Worklist not found'], 404);
            return $this->logAndReturn($request, $response, $startTime);
        }

        if ($status === 'Berhasil') {
            $study->status = 'worklist';
            $study->error_message = null;
        } else {
            $study->status = 'failed';
            $study->error_message = $message;
        }

        $study->save();

        $response = response()->json(['success' => true, 'message' => 'Status updated']);
        return $this->logAndReturn($request, $response, $startTime);
    }

    private function isAuthorized(Request $request): bool
    {
        $configUser = ConfigurationHelper::get('dicom.orthanc_sync_webhook_user', '');
        $configPass = ConfigurationHelper::get('dicom.orthanc_sync_webhook_password', '');

        if ($configUser === '' && $configPass === '') {
            return true;
        }

        return $request->getUser() === $configUser
            && $request->getPassword() === $configPass;
    }

    private function logAndReturn(Request $request, $response, $startTime)
    {
        try {
            ApiLog::create([
                'api_user_name' => 'Orthanc-Sync Webhook',
                'method' => $request->method(),
                'path' => $request->path(),
                'scope' => 'dicom',
                'query_string' => $request->getQueryString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_headers' => $request->headers->all(),
                'request_body' => $request->all(),
                'response_status' => $response->getStatusCode(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000),
                'response_body' => json_decode($response->getContent(), true),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to write ApiLog for Orthanc Webhook: ' . $e->getMessage());
        }

        return $response;
    }
}
