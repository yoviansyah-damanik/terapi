<?php

namespace App\Services\Dicom;

use App\Helpers\ConfigurationHelper;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class OrthancService
{
    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct()
    {
        $this->baseUrl = rtrim(ConfigurationHelper::get('dicom.orthanc.url') ?? ConfigurationHelper::get('dicom.url', ''), '/');
        $this->username = ConfigurationHelper::get('dicom.orthanc.username') ?? ConfigurationHelper::get('dicom.username', '');
        $this->password = ConfigurationHelper::get('dicom.orthanc.password') ?? ConfigurationHelper::get('dicom.password', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl);
    }

    private function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    private function client(int $timeout = 30): PendingRequest
    {
        $client = Http::timeout($timeout)->acceptJson();

        if (!empty($this->username)) {
            $client = $client->withBasicAuth($this->username, $this->password);
        }

        return $client;
    }

    private function wrap(callable $fn): array
    {
        $start = microtime(true);

        try {
            $response = $fn();
            $responseTime = (int) round((microtime(true) - $start) * 1000);

            return [
                'success' => $response->successful(),
                'http_status' => $response->status(),
                'response_time' => $responseTime,
                'data' => $response->json() ?? $response->body(),
                'message' => $response->successful() ? 'Berhasil' : ($response->json('Message') ?? $response->json('message') ?? 'Gagal'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'http_status' => null,
                'response_time' => (int) round((microtime(true) - $start) * 1000),
                'data' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    // -------------------------------------------------------------------------
    // System
    // -------------------------------------------------------------------------

    /** GET /system — info Orthanc version + konfigurasi */
    public function getSystemInfo(): array
    {
        return $this->wrap(fn() => $this->client()->get($this->url('/system')));
    }

    /** GET /statistics — statistik jumlah studi, series, instance, dan disk size */
    public function getStatistics(): array
    {
        return $this->wrap(fn() => $this->client()->get($this->url('/statistics')));
    }

    /** GET /plugins — daftar plugin yang aktif */
    public function getPlugins(): array
    {
        return $this->wrap(fn() => $this->client()->get($this->url('/plugins')));
    }

    // -------------------------------------------------------------------------
    // Studies
    // -------------------------------------------------------------------------

    /** GET /studies?limit=&since= — daftar studi (ID Orthanc saja) */
    public function getStudies(int $limit = 50, int $since = 0): array
    {
        return $this->wrap(fn() => $this->client()->get($this->url('/studies'), [
            'limit' => $limit,
            'since' => $since,
        ]));
    }

    /**
     * POST /tools/find — query studi dengan filter DICOM tag.
     */
    public function findStudyIdsByAccession(string $accession): array
    {
        $resp = $this->wrap(fn() => $this->client()->post($this->url('/tools/find'), [
            'Level' => 'Study',
            'Query' => [
                'AccessionNumber' => $accession
            ]
        ]));

        return $resp['success'] ? ($resp['data'] ?? []) : [];
    }


    /** Cleanup existing worklist entry by Accession Number */
    public function cleanupWorklist(string $accession): void
    {
        $studyIds = $this->findStudyIdsByAccession($accession);
        foreach ($studyIds as $id) {
            $this->deleteStudy($id);
        }
    }

    /**
     * POST /tools/find — query studi dengan filter DICOM tag.
     *
     * @param array $query  Contoh: ['PatientName' => 'Budi*', 'ModalitiesInStudy' => 'CT']
     */
    public function findStudies(array $query = []): array
    {
        return $this->wrap(fn() => $this->client()->post($this->url('/tools/find'), [
            'Level' => 'Study',
            'Query' => $query,
            'Expand' => true,
        ]));
    }

    /** GET /studies/{id} — detail satu studi */
    public function getStudy(string $orthancId): array
    {
        return $this->wrap(fn() => $this->client()->get($this->url("/studies/{$orthancId}")));
    }

    /** DELETE /studies/{id} — hapus studi dari Orthanc */
    public function deleteStudy(string $orthancId): array
    {
        return $this->wrap(fn() => $this->client()->delete($this->url("/studies/{$orthancId}")));
    }

    // -------------------------------------------------------------------------
    // Instances
    // -------------------------------------------------------------------------

    /** GET /instances/{id} — metadata satu instance */
    public function getInstance(string $instanceId): array
    {
        return $this->wrap(fn() => $this->client()->get($this->url("/instances/{$instanceId}")));
    }

    /** GET /instances/{id}/file — download file DICOM binary (timeout 120s) */
    public function getInstanceFile(string $instanceId): string
    {
        $response = $this->client(120)
            ->withHeaders(['Accept' => 'application/dicom'])
            ->get($this->url("/instances/{$instanceId}/file"));

        return $response->successful() ? $response->body() : '';
    }

    // -------------------------------------------------------------------------
    // Modalities
    // -------------------------------------------------------------------------

    /**
     * GET /modalities — daftar AE Title yang terdaftar di Orthanc.
     * Orthanc dapat mengembalikan list ["AE1","AE2"] atau object {"AE1":[ops],"AE2":[ops]}.
     * Method ini selalu menormalisasi ke simple array of strings.
     */
    public function getModalities(): array
    {
        $result = $this->wrap(fn() => $this->client()->get($this->url('/modalities')));
        if ($result['success'] && is_array($result['data']) && !array_is_list($result['data'])) {
            $result['data'] = array_keys($result['data']);
        }
        return $result;
    }

    /** GET /modalities/{ae} — detail satu modality (AET, Host, Port, Manufacturer) */
    public function getModalityDetail(string $ae): array
    {
        return $this->wrap(fn() => $this->client()->get($this->url("/modalities/{$ae}")));
    }

    /** GET semua modality beserta detailnya dalam satu panggilan */
    public function getModalitiesWithDetails(): array
    {
        $listResult = $this->getModalities();
        if (!$listResult['success'] || !is_array($listResult['data'])) {
            return ['success' => false, 'data' => []];
        }

        $details = [];
        foreach ($listResult['data'] as $ae) {
            $detail = $this->getModalityDetail($ae);
            if ($detail['success'] && is_array($detail['data'])) {
                $details[$ae] = $detail['data'];
            }
        }

        return ['success' => true, 'data' => $details];
    }

    /**
     * PUT /modalities/{ae} — daftarkan atau update modality di Orthanc.
     *
     * @param string $manufacturer  Opsional, contoh: 'Generic'
     */
    public function registerModality(string $ae, string $host, int $port, string $manufacturer = 'Generic'): array
    {
        return $this->wrap(fn() => $this->client()->put($this->url("/modalities/{$ae}"), [
            'AET' => $ae,
            'Host' => $host,
            'Port' => $port,
            'Manufacturer' => $manufacturer,
        ]));
    }

    /**
     * POST /modalities/{ae}/query — standard C-FIND (Study/Series/Instance level).
     * Digunakan untuk uji konektivitas DICOM C-FIND tanpa memerlukan MWL.
     */
    public function queryModality(string $ae, string $level = 'Study', array $query = []): array
    {
        return $this->wrap(fn() => $this->client(30)->post($this->url("/modalities/{$ae}/query"), [
            'Level' => $level,
            'Query' => (object) $query,
        ]));
    }

    /** DELETE /modalities/{ae} — hapus modality dari Orthanc */
    public function deleteModality(string $ae): array
    {
        return $this->wrap(fn() => $this->client()->delete($this->url("/modalities/{$ae}")));
    }

    /** POST /modalities/{ae}/echo — C-ECHO test koneksi DICOM */
    public function echoModality(string $ae): array
    {
        return $this->wrap(fn() => $this->client()->post($this->url("/modalities/{$ae}/echo"), (object) []));
    }

    /**
     * Buat worklist entry di Orthanc.
     *
     * Orthanc TIDAK memiliki endpoint REST POST /worklists.
     * Plugin worklist Orthanc bekerja dengan file `.wl` di folder filesystem.
     * Solusi: gunakan POST /tools/create-dicom dengan tag worklist standar DICOM.
     * File .wl akan dibuat oleh Orthanc secara otomatis dari instance DICOM yang kita buat.
     *
     * Alternatif: jika folder worklist dapat diakses dari PHP, tulis file .wl langsung.
     */
    public function createWorklist(array $tags): array
    {
        return $this->pushWorklist($tags);
    }

    /**
     * Update existing worklist entry in Orthanc.
     */
    public function updateWorklist(array $tags): array
    {
        // For Orthanc MWL plugin, updating is usually done by pushing a new instance
        // with the same AccessionNumber/UIDs. The 'Force' => true flag handles the overwrite.
        return $this->pushWorklist($tags);
    }

    /**
     * Helper to push worklist data to Orthanc via /tools/create-dicom.
     */
    // private function pushWorklist(array $tags): array
    // {
    //     // Cek dulu apakah Orthanc dapat diakses
    //     if (empty($this->baseUrl)) {
    //         return ['success' => false, 'message' => 'Orthanc URL belum dikonfigurasi.', 'data' => null];
    //     }

    //     $payload = [
    //         'Tags' => $tags,
    //         'Force' => true,
    //         'PrivateCreator' => 'TerapiMWL',
    //     ];

    //     return $this->wrap(fn() => $this->client(60)->post($this->url('/tools/create-dicom'), $payload));
    // }

    private function pushWorklist(array $tags): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'message' => 'Orthanc URL belum dikonfigurasi.', 'data' => null];
        }

        // ❗ langsung kirim tags, TANPA wrapper "Tags"
        return $this->wrap(
            fn() =>
            $this->client(60)->withHeader(
                'Content-Type',
                'application/json'
            )->post(
                    $this->url('/worklists'),
                    $tags
                )
        );
    }

    /**
     * Menghapus semua worklist di Orthanc yang Accession Number-nya dimulai dengan $prefix.
     */
    public function cleanupWorklists(string $prefix): array
    {
        if (empty($this->baseUrl)) {
            return ['success' => false, 'message' => 'Orthanc URL belum dikonfigurasi.'];
        }

        // 1. Ambil semua ID worklist
        $listResponse = $this->wrap(fn() => $this->client()->get($this->url('/worklists')));
        if (!$listResponse['success'] || !is_array($listResponse['data'])) {
            return $listResponse;
        }

        $deletedCount = 0;
        foreach ($listResponse['data'] as $id) {
            // 2. Cek metadata/tags tiap worklist
            $detail = $this->wrap(fn() => $this->client()->get($this->url("/worklists/{$id}")));
            if ($detail['success'] && isset($detail['data']['0008,0050']['Value'][0])) {
                $accNum = $detail['data']['0008,0050']['Value'][0];
                if (str_starts_with($accNum, $prefix)) {
                    // 3. Delete jika match
                    $this->client()->delete($this->url("/worklists/{$id}"));
                    $deletedCount++;
                }
            }
        }

        return ['success' => true, 'message' => "Berhasil menghapus {$deletedCount} worklist dengan prefix {$prefix}"];
    }

    /** POST /modalities/{ae}/find-worklist — query worklist DICOM */
    public function findWorklist(string $ae, array $query): array
    {
        $provider = \App\Helpers\ConfigurationHelper::get('dicom.provider', 'orthanc');

        // Jika dcm4chee, gunakan DICOMweb QIDO-RS MWL
        if ($provider === 'dcm4chee') {
            return $this->findWorklistDcm4chee($query);
        }

        // Auto-register AE ke Orthanc (karena Orthanc akan error jika item belum ada)
        $router = \App\Models\Dicom\DicomRouter::where('ae_title', $ae)->first();
        if ($router) {
            $this->registerModality($router->ae_title, $router->host, $router->port);
        } else {
            // Fallback ke config default jika tidak ada di DB
            $host = \App\Helpers\ConfigurationHelper::get('dicom.router.default_host', '127.0.0.1');
            $port = (int) \App\Helpers\ConfigurationHelper::get('dicom.router.default_port', 104);
            $this->registerModality($ae, $host, $port);
        }

        // Jika Orthanc, gunakan native C-FIND internal ke remote modalitas
        return $this->wrap(fn() => $this->client()->post($this->url("/modalities/{$ae}/find-worklist"), $query));
    }

    private function findWorklistDcm4chee(array $query): array
    {
        // 1. Build DICOMweb QIDO-RS parameters
        $mwlParams = [];

        if (isset($query['PatientName'])) {
            $mwlParams['PatientName'] = $query['PatientName'];
        }

        if (isset($query['ScheduledProcedureStepSequence'][0]['ScheduledProcedureStepStartDate'])) {
            $mwlParams['ScheduledProcedureStepSequence.ScheduledProcedureStepStartDate'] = $query['ScheduledProcedureStepSequence'][0]['ScheduledProcedureStepStartDate'];
        }

        if (isset($query['ScheduledProcedureStepSequence'][0]['Modality'])) {
            $mwlParams['ScheduledProcedureStepSequence.Modality'] = $query['ScheduledProcedureStepSequence'][0]['Modality'];
        }

        // 2. Fetch dari dcm4chee menggunakan /mwl endpoint
        $response = $this->wrap(fn() => $this->client()->get($this->url("/mwl"), $mwlParams));

        // 3. Modifikasi DICOMweb JSON kembali ke format sederhana Orthanc agar UI tidak perlu berubah
        if ($response['success'] && is_array($response['data'])) {
            $mappedData = [];
            foreach ($response['data'] as $item) {
                // VR: PN biasanya memiliki sub-key Alphabetic
                $patientName = $item['00100010']['Value'][0]['Alphabetic'] ?? ($item['00100010']['Value'][0] ?? '');

                $mappedItem = [
                    'AccessionNumber' => $item['00080050']['Value'][0] ?? '',
                    'PatientName' => $patientName,
                    'PatientID' => $item['00100020']['Value'][0] ?? '',
                    'ScheduledProcedureStepSequence' => [
                        [
                            'ScheduledProcedureStepDescription' => '',
                            'Modality' => '',
                            'ScheduledProcedureStepStartDate' => '',
                        ]
                    ],
                ];

                if (isset($item['00400100']['Value'][0])) {
                    $sps = $item['00400100']['Value'][0];
                    $mappedItem['ScheduledProcedureStepSequence'][0] = [
                        'ScheduledProcedureStepDescription' => $sps['00400007']['Value'][0] ?? '',
                        'Modality' => $sps['00080060']['Value'][0] ?? '',
                        'ScheduledProcedureStepStartDate' => $sps['00400002']['Value'][0] ?? '',
                    ];
                }

                $mappedData[] = $mappedItem;
            }
            $response['data'] = $mappedData;
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // C-STORE
    // -------------------------------------------------------------------------

    /**
     * POST /modalities/{ae}/store — kirim resource ke modality via C-STORE.
     *
     * @param array  $resourceIds  Array Orthanc study/series/instance ID
     * @param bool   $sync         true = tunggu selesai, false = return job ID
     */
    public function storeToModality(string $ae, array $resourceIds, bool $sync = false): array
    {
        return $this->wrap(fn() => $this->client(300)->post($this->url("/modalities/{$ae}/store"), [
            'Resources' => $resourceIds,
            'Synchronous' => $sync,
        ]));
    }

    // -------------------------------------------------------------------------
    // Convert Image → DICOM
    // -------------------------------------------------------------------------

    /**
     * POST /tools/create-dicom — konversi gambar base64 menjadi instance DICOM.
     *
     * @param array  $tags           DICOM tags: PatientName, Modality, dll
     * @param string $base64Content  Data URI atau base64 murni dari gambar
     */
    public function createDicom(array $tags, string $base64Content): array
    {
        return $this->wrap(fn() => $this->client(60)->post($this->url('/tools/create-dicom'), [
            'Tags' => $tags,
            'Content' => $base64Content,
            'Force' => true,
        ]));
    }

    /**
     * POST /tools/create-dicom — buat instance DICOM sintetis dengan pixel 1×1.
     * Content wajib ada agar SOPClassUID ter-set dengan benar untuk C-STORE.
     */
    public function createTestDicom(): array
    {
        $jpegBase64 = $this->generateMinimalJpegBase64();

        return $this->wrap(fn() => $this->client(30)->post($this->url('/tools/create-dicom'), [
            'Tags' => [
                'PatientName' => 'DIAGNOSTIC^TEST',
                'PatientID' => 'DIAG-TEST-' . now()->format('YmdHis'),
                'StudyDate' => now()->format('Ymd'),
                'StudyTime' => now()->format('His'),
                'Modality' => 'OT',
                'SeriesNumber' => '1',
                'InstanceNumber' => '1',
            ],
            'Content' => 'data:image/jpeg;base64,' . $jpegBase64,
            'Force' => true,
        ]));
    }

    /** Generate JPEG 1×1 pixel abu-abu menggunakan GD — tidak memerlukan file eksternal. */
    private function generateMinimalJpegBase64(): string
    {
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(1, 1);
            imagecolorallocate($img, 128, 128, 128);
            ob_start();
            imagejpeg($img, null, 50);
            $jpeg = ob_get_clean();
            imagedestroy($img);
            return base64_encode($jpeg);
        }

        // Fallback: JPEG 1×1 pixel hardcoded (valid JFIF)
        return '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAAqCAABAAEDAREAAhEBAxEB/8QAFgABAQEAAAAAAAAAAAAAAAAABgUE/8QAHBABAAIDAQEBAAAAAAAAAAAAAQIRAAMBM//EABQBAQAAAAAAAAAAAAAAAAAAAAD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwQV3OixmLIqXz9EhJHrUkAAAAAAAAAA//2Q==';
    }

    /** DELETE /instances/{id} — hapus satu instance DICOM dari Orthanc */
    public function deleteInstance(string $instanceId): array
    {
        return $this->wrap(fn() => $this->client()->delete($this->url("/instances/{$instanceId}")));
    }

    /**
     * Uji C-STORE ke modality target menggunakan DICOM sintetis sementara.
     * Instance dibuat sebelum tes dan dihapus setelahnya (berhasil atau tidak).
     */
    public function storeTestToModality(string $ae): array
    {
        $createResult = $this->createTestDicom();
        if (!$createResult['success']) {
            return ['success' => false, 'message' => 'Gagal membuat DICOM tes: ' . ($createResult['message'] ?? ''), 'response_time' => 0];
        }

        $instanceId = $createResult['data']['ID'] ?? null;
        if (!$instanceId) {
            return ['success' => false, 'message' => 'ID instance DICOM tes tidak ditemukan.', 'response_time' => 0];
        }

        $storeResult = $this->storeToModality($ae, [$instanceId], true);

        // Bersihkan instance tes dari PACS
        $this->deleteInstance($instanceId);

        return $storeResult;
    }

    // -------------------------------------------------------------------------
    // Jobs
    // -------------------------------------------------------------------------

    /** GET /jobs/{id} — status job Orthanc (C-STORE, dsb.) */
    public function getJob(string $jobId): array
    {
        return $this->wrap(fn() => $this->client()->get($this->url("/jobs/{$jobId}")));
    }
}
