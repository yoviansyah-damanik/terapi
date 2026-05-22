<?php

namespace App\Services\Dicom;

use App\Helpers\ConfigurationHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class OrthancSyncService
{
    private string $baseUrl;
    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(ConfigurationHelper::get('orthanc.sync_url', 'http://127.0.0.1:9123'), '/');
        $this->apiKey = ConfigurationHelper::get('orthanc.sync_api_key');
    }

    private function client(int $timeout = 5): PendingRequest
    {
        $client = Http::timeout($timeout)->acceptJson();

        if ($this->apiKey) {
            $client = $client->withHeaders([
                'X-API-Key' => $this->apiKey
            ]);
        }

        return $client;
    }

    private function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * Kirim data worklist ke Orthanc-Sync.
     */
    public function storeWorklist(array $payload, bool $bypass = false): array
    {
        try {
            $http = $this->client();
            $url = $this->url('/api/worklist');

            $method = "POST";
            if ($bypass) {
                $method = "PUT";
                $response = $http->put($url, $payload);
            } else {
                $response = $http->post($url, $payload);
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
                'url' => $url,
                'method' => $method,
                'message' => $response->json('message') ?? $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ambil detail worklist dari Orthanc-Sync.
     */
    public function getWorklist(string $accessionNumber): array
    {
        try {
            $response = $this->client()->get($this->url("/api/worklist/{$accessionNumber}"));
            return [
                'success' => $response->successful(),
                'data' => $response->json('data'),
                'message' => $response->json('message') ?? $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Hapus worklist dari Orthanc-Sync.
     */
    public function deleteWorklist(string $accessionNumber): array
    {
        try {
            $response = $this->client()->delete($this->url("/api/worklist/{$accessionNumber}"));
            return [
                'success' => $response->successful(),
                'message' => $response->json('message') ?? $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cek status ketersediaan study di PACS melalui Orthanc-Sync Bridge.
     */
    public function checkStudy(string $accessionNumber): array
    {
        try {
            $response = $this->client(10)->get($this->url("/api/check-study/{$accessionNumber}"));
            $json = $response->json();
            
            return [
                'success' => $response->successful() && ($json['success'] ?? false),
                'exists' => $json['exists'] ?? false,
                'data' => $json, // Entire payload is the data
                'count' => $json['instances_count'] ?? 0,
                'message' => $json['message'] ?? $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'exists' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cek status ketersediaan study di PACS sekaligus mengupdate tabel database (Worklist & DicomStudy).
     */
    public function checkAndUpdateStudy(string $accessionNumber, ?string $noRawat = null): array
    {
        $check = $this->checkStudy($accessionNumber);
        
        if ($check['success'] && $check['exists'] && !empty($check['data']['study_id'])) {
            $studyData = $check['data'];
            $modality = $studyData['modality'] ?? null;
            if (is_array($modality)) {
                $modality = implode(',', $modality);
            }

            if (!$noRawat) {
                $worklist = \App\Models\Dicom\Worklist::where('accession_number', $accessionNumber)->first();
                $noRawat = $worklist ? $worklist->no_rawat : null;
            }

            \App\Models\Dicom\Worklist::where('accession_number', $accessionNumber)
                ->update([
                    'orthanc_study_id' => $studyData['study_id'],
                    'study_instance_uid' => $studyData['study_instance_uid'] ?? null,
                    'series_count' => $studyData['series_count'] ?? 0,
                    'instance_count' => $studyData['instances_count'] ?? 0,
                    'status' => 'received',
                ]);

            \App\Models\Dicom\DicomStudy::updateOrCreate(
                ['noorder' => $accessionNumber],
                [
                    'no_rawat' => $noRawat,
                    'orthanc_study_id' => $studyData['study_id'],
                    'study_instance_uid' => $studyData['study_instance_uid'] ?? null,
                    'patient_id' => $studyData['patient_id'] ?? null,
                    'study_description' => $studyData['study_description'] ?? null,
                    'study_date' => isset($studyData['study_date']) ? \Carbon\Carbon::parse($studyData['study_date'])->format('Y-m-d') : null,
                    'modality' => $modality,
                    'series_count' => $studyData['series_count'] ?? 0,
                    'instance_count' => $studyData['instances_count'] ?? 0,
                    'status' => 'received',
                ]
            );
        } else {
            // Jika study tidak ditemukan di PACS, cek ketersediaan di Worklist
            try {
                $wlResponse = $this->client(5)->get($this->url("/api/check-wl/{$accessionNumber}"));
                $wlJson = $wlResponse->json();
                
                $isWlExist = ($wlResponse->successful() && (($wlJson['exists'] ?? false) || ($wlJson['physically_present'] ?? false)));
            } catch (\Exception $e) {
                $isWlExist = false;
            }

            $newStatus = $isWlExist ? 'worklist' : 'pending';

            \App\Models\Dicom\Worklist::where('accession_number', $accessionNumber)
                ->update([
                    'status' => $newStatus,
                    'orthanc_study_id' => null,
                    'study_instance_uid' => null,
                    'series_count' => 0,
                    'instance_count' => 0,
                ]);

            \App\Models\Dicom\DicomStudy::where('noorder', $accessionNumber)
                ->update([
                    'status' => $newStatus,
                    'orthanc_study_id' => null,
                    'study_instance_uid' => null,
                    'patient_id' => null,
                    'study_description' => null,
                    'study_date' => null,
                    'series_count' => 0,
                    'instance_count' => 0,
                ]);
        }

        return $check;
    }
}
