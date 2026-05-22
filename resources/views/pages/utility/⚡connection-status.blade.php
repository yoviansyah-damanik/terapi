<?php

use App\Services\SatuSehatService;
use App\Services\Bpjs\AplicareService;
use App\Services\Bpjs\BpjsService;
use App\Services\Snomed\SnowstormService;
use App\Services\WahaService;
use App\Services\GowaService;
use App\Services\RsOnline\RsOnlineService;
use App\Services\TteService;
use App\Helpers\ConfigurationHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Status Koneksi')] class extends Component {
    public array $satuSehatResults = [];
    public array $bpjsResults = [];
    public array $otherResults = [];

    public ?string $satuSehatToken = null;
    public ?string $antrianRsToken = null;

    /** Timeout dalam detik yang berlaku per item uji (bukan total keseluruhan) */
    public int $itemTimeout = 10;

    private function defaultResult(): array
    {
        return [
            'status' => 'pending',
            'message' => null,
            'response_time' => null,
            'http_status' => null,
        ];
    }

    private function getSatuSehatResources(): array
    {
        return config('satusehat.test_resources', []);
    }

    private function getBpjsEndpoints(): array
    {
        return [
            'vclaim' => config('bpjs.vclaim.test_endpoints'),
            'antrian_online' => config('bpjs.antrian_online.test_endpoints'),
            'apotek_online' => config('bpjs.apotek_online.test_endpoints'),
            'icare' => config('bpjs.icare.test_endpoints'),
            'erm' => config('bpjs.erm.test_endpoints'),
            'antrian_rs' => config('bpjs.antrian_rs.test_endpoints'),
        ];
    }

    public function mount(): void
    {
        $this->satuSehatResults['token'] = $this->defaultResult();
        foreach ($this->getSatuSehatResources() as $resource) {
            $this->satuSehatResults[$resource['type']] = $this->defaultResult();
        }

        foreach ($this->getBpjsEndpoints() as $module => $items) {
            foreach ($items as $index => $ep) {
                $this->bpjsResults["{$module}_{$index}"] = array_merge($this->defaultResult(), ['meta_code' => null]);
            }
        }

        foreach (['snomed', 'kfa', 'whatsapp', 'rsonline', 'tte', 'simrs_db', 'dicom'] as $svc) {
            $this->otherResults[$svc] = $this->defaultResult();
        }

        $this->bpjsResults['aplicare_0'] = array_merge($this->defaultResult(), ['meta_code' => null]);
    }

    // =================== SATU SEHAT ===================

    public function testSatuSehatToken(): void
    {
        $service = new SatuSehatService();
        $result = $service->testToken();

        $this->satuSehatResults['token'] = [
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'],
            'response_time' => $result['response_time'],
            'http_status' => $result['http_status'],
        ];

        if ($result['token']) {
            $this->satuSehatToken = $result['token'];
        }

        $this->dispatch('toast', type: $result['success'] ? 'success' : 'error', message: 'Get Token: ' . $result['message']);
    }

    public function testSatuSehatResource(string $resourceType): void
    {
        $service = new SatuSehatService();

        if (!$this->satuSehatToken) {
            $this->fetchSatuSehatToken($service);
            if (!$this->satuSehatToken) {
                $this->satuSehatResults[$resourceType] = array_merge($this->defaultResult(), [
                    'status' => 'failed',
                    'message' => 'Gagal mendapatkan token',
                ]);
                return;
            }
        }

        $result = $service->testResource($resourceType, $this->satuSehatToken);

        $this->satuSehatResults[$resourceType] = [
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'],
            'response_time' => $result['response_time'],
            'http_status' => $result['http_status'],
        ];

        $this->dispatch('toast', type: $result['success'] ? 'success' : 'error', message: $resourceType . ': ' . $result['message']);
    }

    public function testSatuSehatGroup(string $group): void
    {
        $service = new SatuSehatService();

        if (!$this->satuSehatToken) {
            $this->fetchSatuSehatToken($service);
            if (!$this->satuSehatToken) {
                $this->dispatch('toast', type: 'error', message: 'Gagal mendapatkan token Satu Sehat');
                return;
            }
        }

        $resources = collect($this->getSatuSehatResources())->where('group', $group);
        $success = 0;
        $failed = 0;

        foreach ($resources as $resource) {
            $result = $service->testResource($resource['type'], $this->satuSehatToken);

            $this->satuSehatResults[$resource['type']] = [
                'status' => $result['success'] ? 'success' : 'failed',
                'message' => $result['message'],
                'response_time' => $result['response_time'],
                'http_status' => $result['http_status'],
            ];

            $result['success'] ? $success++ : $failed++;
        }

        $this->dispatch('toast', type: $failed === 0 ? 'success' : ($success === 0 ? 'error' : 'warning'), message: "Satu Sehat - {$group}: {$success} berhasil, {$failed} gagal");
    }

    public function testSatuSehat(): void
    {
        $service = new SatuSehatService();
        $this->fetchSatuSehatToken($service);

        if (!$this->satuSehatToken) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mendapatkan token Satu Sehat');
            return;
        }

        $totalSuccess = 0;
        $totalFail = 0;

        foreach ($this->getSatuSehatResources() as $resource) {
            $result = $service->testResource($resource['type'], $this->satuSehatToken);

            $this->satuSehatResults[$resource['type']] = [
                'status' => $result['success'] ? 'success' : 'failed',
                'message' => $result['message'],
                'response_time' => $result['response_time'],
                'http_status' => $result['http_status'],
            ];

            $result['success'] ? $totalSuccess++ : $totalFail++;
        }

        $this->dispatch('toast', type: $totalFail === 0 ? 'success' : ($totalSuccess === 0 ? 'error' : 'warning'), message: "Satu Sehat: {$totalSuccess} berhasil, {$totalFail} gagal");
    }

    private function fetchSatuSehatToken(SatuSehatService $service): void
    {
        $result = $service->testToken();

        $this->satuSehatResults['token'] = [
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'],
            'response_time' => $result['response_time'],
            'http_status' => $result['http_status'],
        ];

        if ($result['token']) {
            $this->satuSehatToken = $result['token'];
        }
    }

    // =================== BPJS ===================

    public function testBpjsEndpoint(string $module, int $index): void
    {
        $service = new BpjsService();
        $endpoints = $this->getBpjsEndpoints();
        $key = "{$module}_{$index}";

        if (!isset($endpoints[$module][$index])) {
            return;
        }

        $ep = $endpoints[$module][$index];

        if ($module === 'antrian_rs' && $index > 0 && !$this->antrianRsToken) {
            $this->fetchAntrianRsToken($service);
        }

        $token = $module === 'antrian_rs' && $index > 0 ? $this->antrianRsToken : null;
        $result = $service->testEndpoint($module, $ep['method'], $ep['test_path'], $token);

        $this->bpjsResults[$key] = [
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'],
            'response_time' => $result['response_time'],
            'http_status' => $result['http_status'],
            'meta_code' => $result['meta_code'],
        ];

        if ($module === 'antrian_rs' && $index === 0 && $result['token']) {
            $this->antrianRsToken = $result['token'];
        }

        $this->dispatch('toast', type: $result['success'] ? 'success' : 'error', message: $ep['label'] . ': ' . $result['message']);
    }

    public function testBpjsModule(string $module): void
    {
        $service = new BpjsService();
        $endpoints = $this->getBpjsEndpoints();

        if (!isset($endpoints[$module])) {
            return;
        }

        if ($module === 'antrian_rs') {
            $this->fetchAntrianRsToken($service);
        }

        foreach ($endpoints[$module] as $index => $ep) {
            $token = $module === 'antrian_rs' && $index > 0 ? $this->antrianRsToken : null;
            $result = $service->testEndpoint($module, $ep['method'], $ep['test_path'], $token);

            $this->bpjsResults["{$module}_{$index}"] = [
                'status' => $result['success'] ? 'success' : 'failed',
                'message' => $result['message'],
                'response_time' => $result['response_time'],
                'http_status' => $result['http_status'],
                'meta_code' => $result['meta_code'],
            ];

            if ($module === 'antrian_rs' && $index === 0 && $result['token']) {
                $this->antrianRsToken = $result['token'];
            }
        }

        $moduleResults = collect($endpoints[$module])->map(fn($ep, $i) => $this->bpjsResults["{$module}_{$i}"]);
        $success = $moduleResults->where('status', 'success')->count();
        $failed = $moduleResults->where('status', 'failed')->count();
        $config = $service->getModuleConfig($module);

        $this->dispatch('toast', type: $failed === 0 ? 'success' : ($success === 0 ? 'error' : 'warning'), message: $config['name'] . ": {$success} berhasil, {$failed} gagal");
    }

    public function testBpjs(): void
    {
        $service = new BpjsService();
        $endpoints = $this->getBpjsEndpoints();
        $totalSuccess = 0;
        $totalFail = 0;

        foreach ($endpoints as $module => $items) {
            if ($module === 'antrian_rs') {
                $this->fetchAntrianRsToken($service);
            }

            foreach ($items as $index => $ep) {
                $token = $module === 'antrian_rs' && $index > 0 ? $this->antrianRsToken : null;
                $result = $service->testEndpoint($module, $ep['method'], $ep['test_path'], $token);

                $this->bpjsResults["{$module}_{$index}"] = [
                    'status' => $result['success'] ? 'success' : 'failed',
                    'message' => $result['message'],
                    'response_time' => $result['response_time'],
                    'http_status' => $result['http_status'],
                    'meta_code' => $result['meta_code'],
                ];

                if ($module === 'antrian_rs' && $index === 0 && $result['token']) {
                    $this->antrianRsToken = $result['token'];
                }

                $result['success'] ? $totalSuccess++ : $totalFail++;
            }
        }

        $this->dispatch('toast', type: $totalFail === 0 ? 'success' : ($totalSuccess === 0 ? 'error' : 'warning'), message: "BPJS: {$totalSuccess} berhasil, {$totalFail} gagal");
    }

    private function fetchAntrianRsToken(BpjsService $service): void
    {
        $endpoints = $this->getBpjsEndpoints();
        $ep = $endpoints['antrian_rs'][0];
        $result = $service->testEndpoint('antrian_rs', $ep['method'], $ep['test_path']);

        $this->bpjsResults['antrian_rs_0'] = [
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'],
            'response_time' => $result['response_time'],
            'http_status' => $result['http_status'],
            'meta_code' => $result['meta_code'],
        ];

        if ($result['token']) {
            $this->antrianRsToken = $result['token'];
        }
    }

    // =================== Snowstorm ===================

    public function testSnomed(): void
    {
        $svc = new SnowstormService();
        $url = rtrim($svc->baseUrl, '/');

        if (empty($url)) {
            $this->otherResults['snomed'] = array_merge($this->defaultResult(), [
                'status' => 'failed',
                'message' => 'SNOWSTORM_URL belum dikonfigurasi di .env',
            ]);
            $this->dispatch('toast', type: 'error', message: 'Snowstorm: URL tidak dikonfigurasi');
            return;
        }

        $start = microtime(true);
        try {
            $response = Http::timeout($this->itemTimeout)->get("{$url}/version");
            $time = round((microtime(true) - $start) * 1000);

            $this->otherResults['snomed'] = [
                'status' => $response->successful() ? 'success' : 'failed',
                'message' => $response->successful() ? 'Terhubung ke server Snowstorm' : 'Server merespon HTTP ' . $response->status(),
                'response_time' => $time,
                'http_status' => $response->status(),
            ];
        } catch (\Exception $e) {
            $this->otherResults['snomed'] = [
                'status' => 'failed',
                'message' => 'Tidak dapat terhubung: ' . $e->getMessage(),
                'response_time' => round((microtime(true) - $start) * 1000),
                'http_status' => null,
            ];
        }

        $r = $this->otherResults['snomed'];
        $this->dispatch('toast', type: $r['status'] === 'success' ? 'success' : 'error', message: 'Snowstorm: ' . $r['message']);
    }

    // =================== KFA ===================

    public function testKfa(): void
    {
        $ssService = new SatuSehatService();

        if (!$ssService->isConfigured()) {
            $this->otherResults['kfa'] = array_merge($this->defaultResult(), [
                'status' => 'failed',
                'message' => 'Konfigurasi Satu Sehat belum lengkap (KFA menggunakan token yang sama)',
            ]);
            $this->dispatch('toast', type: 'error', message: 'KFA: Konfigurasi Satu Sehat tidak lengkap');
            return;
        }

        if (!$this->satuSehatToken) {
            $this->fetchSatuSehatToken($ssService);
        }

        if (!$this->satuSehatToken) {
            $this->otherResults['kfa'] = array_merge($this->defaultResult(), [
                'status' => 'failed',
                'message' => 'Gagal mendapatkan token Satu Sehat untuk KFA',
                'response_time' => $this->satuSehatResults['token']['response_time'],
                'http_status' => $this->satuSehatResults['token']['http_status'],
            ]);
            $this->dispatch('toast', type: 'error', message: 'KFA: Gagal mendapatkan token');
            return;
        }

        $baseUrl = rtrim(config('satusehat.base_url', ''), '/');
        $start = microtime(true);

        try {
            $response = Http::withToken($this->satuSehatToken)
                ->timeout(15)
                ->get("{$baseUrl}/kfa-v2/products/all", ['product_type' => 'farmasi', 'page' => 1, 'size' => 1]);
            $time = round((microtime(true) - $start) * 1000);

            $this->otherResults['kfa'] = [
                'status' => $response->successful() ? 'success' : 'failed',
                'message' => $response->successful() ? 'KFA API terhubung' : 'Server merespon HTTP ' . $response->status(),
                'response_time' => $time,
                'http_status' => $response->status(),
            ];
        } catch (\Exception $e) {
            $this->otherResults['kfa'] = [
                'status' => 'failed',
                'message' => 'Tidak dapat terhubung: ' . $e->getMessage(),
                'response_time' => round((microtime(true) - $start) * 1000),
                'http_status' => null,
            ];
        }

        $r = $this->otherResults['kfa'];
        $this->dispatch('toast', type: $r['status'] === 'success' ? 'success' : 'error', message: 'KFA: ' . $r['message']);
    }

    // =================== WA GATEWAY ===================

    public function testWhatsapp(): void
    {
        $activeGateway = ConfigurationHelper::get('whatsapp.active_gateway', 'waha');
        $start = microtime(true);

        try {
            if ($activeGateway === 'waha') {
                $result = app(WahaService::class)->getSessionStatus();
                $time = round((microtime(true) - $start) * 1000);
                $sessionStatus = $result['data']['status'] ?? 'UNKNOWN';
                $isOk = in_array($sessionStatus, ['WORKING', 'AUTHENTICATED']);

                $this->otherResults['whatsapp'] = [
                    'status' => $result['success'] && $isOk ? 'success' : 'failed',
                    'message' => $result['success']
                        ? match ($sessionStatus) {
                            'WORKING', 'AUTHENTICATED' => 'Session WAHA aktif dan terhubung',
                            'SCAN_QR_CODE' => 'Menunggu scan QR Code',
                            'STARTING' => 'Session sedang dimulai',
                            'STOPPED' => 'Session dihentikan',
                            default => "Status: {$sessionStatus}",
                        }
                        : $result['message'] ?? 'Gagal terhubung ke WAHA',
                    'response_time' => $time,
                    'http_status' => $result['status_code'] ?? null,
                ];
            } else {
                $result = app(GowaService::class)->getDevices();
                $time = round((microtime(true) - $start) * 1000);

                $this->otherResults['whatsapp'] = [
                    'status' => $result['success'] ? 'success' : 'failed',
                    'message' => $result['success'] ? 'Server GOWA terhubung' : $result['message'] ?? 'Gagal terhubung ke GOWA',
                    'response_time' => $time,
                    'http_status' => $result['status_code'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            $this->otherResults['whatsapp'] = [
                'status' => 'failed',
                'message' => 'Tidak dapat terhubung: ' . $e->getMessage(),
                'response_time' => round((microtime(true) - $start) * 1000),
                'http_status' => null,
            ];
        }

        $r = $this->otherResults['whatsapp'];
        $this->dispatch('toast', type: $r['status'] === 'success' ? 'success' : 'error', message: 'WA Gateway: ' . $r['message']);
    }

    // =================== RS ONLINE ===================

    public function testRsOnline(): void
    {
        $service = new RsOnlineService();

        if (!$service->isConfigured()) {
            $this->otherResults['rsonline'] = array_merge($this->defaultResult(), [
                'status' => 'failed',
                'message' => 'Konfigurasi RS Online belum lengkap.',
            ]);
            $this->dispatch('toast', type: 'error', message: 'RS Online: Konfigurasi tidak lengkap');
            return;
        }

        $result = $service->testConnection();

        $this->otherResults['rsonline'] = [
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'],
            'response_time' => $result['response_time'] ?? null,
            'http_status' => $result['http_status'] ?? null,
        ];

        $this->dispatch('toast', type: $result['success'] ? 'success' : 'error', message: 'RS Online: ' . $result['message']);
    }

    // =================== TTE ===================

    public function testTte(): void
    {
        $baseUrl = config('services.tte.base_url');

        if (empty($baseUrl)) {
            $this->otherResults['tte'] = array_merge($this->defaultResult(), [
                'status' => 'failed',
                'message' => 'TTE_BASE_URL belum dikonfigurasi di .env',
            ]);
            $this->dispatch('toast', type: 'error', message: 'TTE: Base URL tidak dikonfigurasi');
            return;
        }

        $start = microtime(true);
        try {
            $result = app(TteService::class)->checkUserStatus('0000000000000000');
            $time = round((microtime(true) - $start) * 1000);
            $ok = $result['success'] || isset($result['status_code']);

            $this->otherResults['tte'] = [
                'status' => $ok ? 'success' : 'failed',
                'message' => $ok ? 'Terhubung ke server TTE' : 'Server merespon status ' . ($result['status_code'] ?? 'unknown'),
                'response_time' => $time,
                'http_status' => $result['status_code'] ?? null,
            ];
        } catch (\Exception $e) {
            $this->otherResults['tte'] = [
                'status' => 'failed',
                'message' => 'Tidak dapat terhubung: ' . $e->getMessage(),
                'response_time' => round((microtime(true) - $start) * 1000),
                'http_status' => null,
            ];
        }

        $r = $this->otherResults['tte'];
        $this->dispatch('toast', type: $r['status'] === 'success' ? 'success' : 'error', message: 'TTE: ' . $r['message']);
    }

    // =================== SIMRS DB ===================

    public function testSimrsDb(): void
    {
        $config = config('database.connections.simrs');
        $start = microtime(true);

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                $config['host'],
                $config['port'] ?? 3306,
                $config['database'],
            );
            new \PDO($dsn, $config['username'], $config['password'], [
                \PDO::ATTR_TIMEOUT => $this->itemTimeout,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $time = round((microtime(true) - $start) * 1000);

            $this->otherResults['simrs_db'] = [
                'status' => 'success',
                'message' => 'Terhubung ke database SIMRS',
                'response_time' => $time,
                'http_status' => null,
            ];
        } catch (\Exception $e) {
            $this->otherResults['simrs_db'] = [
                'status' => 'failed',
                'message' => 'Gagal: ' . $e->getMessage(),
                'response_time' => round((microtime(true) - $start) * 1000),
                'http_status' => null,
            ];
        }

        $r = $this->otherResults['simrs_db'];
        $this->dispatch('toast', type: $r['status'] === 'success' ? 'success' : 'error', message: 'DB SIMRS: ' . $r['message']);
    }

    // =================== DICOM (PACS) ===================

    public function testDicom(): void
    {
        $dicomUrl = rtrim(ConfigurationHelper::get('dicom.url', ''), '/');
        $provider  = ConfigurationHelper::get('dicom.provider', 'orthanc');
        $username  = ConfigurationHelper::get('dicom.username', '');
        $password  = ConfigurationHelper::get('dicom.password', '');

        if (empty($dicomUrl)) {
            $this->otherResults['dicom'] = array_merge($this->defaultResult(), [
                'status'  => 'failed',
                'message' => 'URL DICOM/PACS belum dikonfigurasi.',
            ]);
            $this->dispatch('toast', type: 'error', message: 'DICOM: URL belum dikonfigurasi');
            return;
        }

        $testPath = $provider === 'orthanc' ? '/system' : '/studies?limit=1';
        $start = microtime(true);

        try {
            $req = Http::timeout($this->itemTimeout);
            if (!empty($username) || !empty($password)) {
                $req = $req->withBasicAuth($username, $password);
            }
            $response = $req->get($dicomUrl . $testPath);
            $time = round((microtime(true) - $start) * 1000);

            $this->otherResults['dicom'] = [
                'status'        => $response->successful() ? 'success' : 'failed',
                'message'       => $response->successful()
                    ? 'Terhubung ke server DICOM (' . strtoupper($provider) . ')'
                    : 'Server merespon HTTP ' . $response->status(),
                'response_time' => $time,
                'http_status'   => $response->status(),
            ];
        } catch (\Exception $e) {
            $this->otherResults['dicom'] = [
                'status'        => 'failed',
                'message'       => 'Tidak dapat terhubung: ' . $e->getMessage(),
                'response_time' => round((microtime(true) - $start) * 1000),
                'http_status'   => null,
            ];
        }

        $r = $this->otherResults['dicom'];
        $this->dispatch('toast', type: $r['status'] === 'success' ? 'success' : 'error', message: 'DICOM: ' . $r['message']);
    }

    // =================== APLICARE ===================

    public function testAplicare(): void
    {
        $service = app(AplicareService::class);

        if (!$service->isConfigured()) {
            $this->bpjsResults['aplicare_0'] = array_merge($this->defaultResult(), [
                'status' => 'failed',
                'message' => 'Konfigurasi belum lengkap (APLICARE_BPJS_URL / USERNAME / PASSWORD)',
                'meta_code' => null,
            ]);
            $this->dispatch('toast', type: 'error', message: 'Aplicare: Konfigurasi tidak lengkap');
            return;
        }

        $start = microtime(true);
        $result = $service->getBeds(1, 1);
        $time = round((microtime(true) - $start) * 1000);

        $this->bpjsResults['aplicare_0'] = [
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['success'] ? 'Terhubung ke server Aplicare' : $result['message'],
            'response_time' => $time,
            'http_status' => $result['http_status'] ?? null,
            'meta_code' => null,
        ];

        $r = $this->bpjsResults['aplicare_0'];
        $this->dispatch('toast', type: $r['status'] === 'success' ? 'success' : 'error', message: 'Aplicare: ' . $r['message']);
    }

    // =================== SUMMARY (dipanggil Alpine setelah semua selesai) ===================

    public function dispatchSummary(): void
    {
        $ssResults = collect($this->satuSehatResults)->except('token');
        $bpjsResults = collect($this->bpjsResults);
        $otherRes = collect($this->otherResults);

        $totalSuccess = $ssResults->where('status', 'success')->count()
            + $bpjsResults->where('status', 'success')->count()
            + $otherRes->where('status', 'success')->count();
        $totalFail = $ssResults->where('status', 'failed')->count()
            + $bpjsResults->where('status', 'failed')->count()
            + $otherRes->where('status', 'failed')->count();

        $this->dispatch('toast',
            type: $totalFail === 0 ? 'success' : ($totalSuccess === 0 ? 'error' : 'warning'),
            message: "Selesai: {$totalSuccess} berhasil, {$totalFail} gagal",
        );
    }

    public function with(): array
    {
        // === Satu Sehat ===
        $ssService = new SatuSehatService();
        $ssResources = $this->getSatuSehatResources();

        $ssGrouped = collect($ssResources)
            ->groupBy('group')
            ->map(function ($items, $group) {
                $results = $items->map(fn($item) => $this->satuSehatResults[$item['type']] ?? $this->defaultResult());
                return [
                    'group' => $group,
                    'items' => $items
                        ->map(
                            fn($item) => array_merge($item, [
                                'result' => $this->satuSehatResults[$item['type']] ?? $this->defaultResult(),
                            ]),
                        )
                        ->values()
                        ->all(),
                    'success_count' => $results->where('status', 'success')->count(),
                    'failed_count' => $results->where('status', 'failed')->count(),
                    'pending_count' => $results->where('status', 'pending')->count(),
                ];
            })
            ->all();

        $ssAllResults = collect($this->satuSehatResults)->except('token');

        // === BPJS ===
        $bpjsService = new BpjsService();
        $allBpjsEndpoints = $this->getBpjsEndpoints();
        $bpjsModules = [];

        foreach ($bpjsService->getModules() as $key) {
            $config = $bpjsService->getModuleConfig($key);
            $isAntrianRs = $key === 'antrian_rs';

            $endpointsWithResults = [];
            foreach ($allBpjsEndpoints[$key] ?? [] as $index => $ep) {
                $ep['result'] = $this->bpjsResults["{$key}_{$index}"] ?? array_merge($this->defaultResult(), ['meta_code' => null]);
                $endpointsWithResults[] = $ep;
            }

            $epResults = collect($endpointsWithResults)->pluck('result');

            $bpjsModules[$key] = [
                'key' => $key,
                'name' => $config['name'],
                'description' => $config['description'],
                'base_url' => $config['base_url'],
                'auth_label' => $isAntrianRs ? 'Username' : 'Cons ID',
                'auth_value' => $isAntrianRs ? $config['username'] ?? null : $config['cons_id'] ?? null,
                'is_configured' => $bpjsService->isConfigured($key),
                'endpoints' => $endpointsWithResults,
                'success_count' => $epResults->where('status', 'success')->count(),
                'failed_count' => $epResults->where('status', 'failed')->count(),
                'pending_count' => $epResults->where('status', 'pending')->count(),
            ];
        }

        $allBpjsResults = collect($this->bpjsResults);
        $activeGateway = ConfigurationHelper::get('whatsapp.active_gateway', 'waha');

        return [
            // Satu Sehat
            'ssIsConfigured' => $ssService->isConfigured(),
            'ssTokenResult' => $this->satuSehatResults['token'] ?? $this->defaultResult(),
            'ssGrouped' => $ssGrouped,
            'ssAuthUrl' => config('satusehat.auth_url'),
            'ssBaseUrl' => config('satusehat.fhir_url'),
            'ssClientId' => config('satusehat.client_id') ? Str::mask(config('satusehat.client_id'), '*', 8) : null,
            'ssOrganizationId' => config('satusehat.organization_id'),
            'ssTotalResources' => $ssAllResults->count(),
            'ssSuccessCount' => $ssAllResults->where('status', 'success')->count(),
            'ssFailedCount' => $ssAllResults->where('status', 'failed')->count(),
            'ssPendingCount' => $ssAllResults->where('status', 'pending')->count(),

            // BPJS
            'bpjsModules' => $bpjsModules,
            'bpjsTotalEndpoints' => $allBpjsResults->count(),
            'bpjsSuccessCount' => $allBpjsResults->where('status', 'success')->count(),
            'bpjsFailedCount' => $allBpjsResults->where('status', 'failed')->count(),
            'bpjsPendingCount' => $allBpjsResults->where('status', 'pending')->count(),

            // Simple services
            'snomedResult' => $this->otherResults['snomed'] ?? $this->defaultResult(),
            'snomedUrl' => config('services.snowstorm.url', ''),
            'kfaResult' => $this->otherResults['kfa'] ?? $this->defaultResult(),
            'kfaBaseUrl' => rtrim(config('satusehat.base_url', ''), '/') . '/kfa-v2',
            'whatsappResult' => $this->otherResults['whatsapp'] ?? $this->defaultResult(),
            'activeGateway' => $activeGateway,
            'wahaApiUrl' => ConfigurationHelper::get('whatsapp.api_url', ''),
            'gowaApiUrl' => ConfigurationHelper::get('gowa.api_url', ''),
            'rsOnlineResult' => $this->otherResults['rsonline'] ?? $this->defaultResult(),
            'rsOnlineUrl' => ConfigurationHelper::get('rsonline.base_url', ''),
            'tteResult' => $this->otherResults['tte'] ?? $this->defaultResult(),
            'tteBaseUrl' => config('services.tte.base_url', ''),

            // SIMRS DB
            'simrsDbResult' => $this->otherResults['simrs_db'] ?? $this->defaultResult(),
            'simrsDbHost'   => config('database.connections.simrs.host', '') . ':' . config('database.connections.simrs.port', '3306'),
            'simrsDbName'   => config('database.connections.simrs.database', ''),

            // DICOM PACS
            'dicomResult'   => $this->otherResults['dicom'] ?? $this->defaultResult(),
            'dicomProvider' => ConfigurationHelper::get('dicom.provider', 'orthanc'),
            'dicomUrl'      => ConfigurationHelper::get('dicom.url', ''),

            // Aplicare — ditampilkan di dalam panel BPJS
            'aplicareModule' => [
                'name' => config('bpjs.aplicare.name', 'Aplicare'),
                'description' => config('bpjs.aplicare.description', 'Ketersediaan tempat tidur real-time BPJS'),
                'base_url' => rtrim((string) config('bpjs.aplicare.base_url', ''), '/'),
                'cons_id' => (string) config('bpjs.aplicare.cons_id', ''),
                'is_configured' => app(AplicareService::class)->isConfigured(),
                'result' => $this->bpjsResults['aplicare_0'] ?? array_merge($this->defaultResult(), ['meta_code' => null]),
            ],
        ];
    }
};
?>

<div x-data="{
    running: false,
    currentLabel: '',
    progress: 0,
    total: 0,

    queue: [
        { method: 'testBpjs',      label: 'BPJS' },
        { method: 'testSatuSehat', label: 'Satu Sehat' },
        { method: 'testSnomed',    label: 'Snowstorm' },
        { method: 'testKfa',       label: 'KFA' },
        { method: 'testWhatsapp',  label: 'WA Gateway' },
        { method: 'testRsOnline',  label: 'RS Online' },
        { method: 'testTte',       label: 'TTE' },
        { method: 'testSimrsDb',   label: 'DB SIMRS' },
        { method: 'testAplicare',  label: 'Aplicare' },
        { method: 'testDicom',     label: 'DICOM' },
    ],

    async startTestAll() {
        this.running = true;
        this.progress = 0;
        this.total = this.queue.length;

        for (const item of this.queue) {
            this.currentLabel = item.label;
            try {
                await $wire[item.method]();
            } catch (e) {
                console.warn('Test timeout/error:', item.method, e);
            }
            this.progress++;
        }

        this.currentLabel = '';
        this.running = false;
        await $wire.dispatchSummary();
    }
}">
    {{-- Header --}}
    <x-ui.page-header title="Status Koneksi" subtitle="Tes konektivitas ke seluruh layanan eksternal yang terintegrasi">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <label class="text-xs text-zinc-500 dark:text-primary-dark-400 whitespace-nowrap">Timeout/item</label>
                <flux:select wire:model.live="itemTimeout" class="w-24">
                    <flux:select.option value="5">5 dtk</flux:select.option>
                    <flux:select.option value="10">10 dtk</flux:select.option>
                    <flux:select.option value="15">15 dtk</flux:select.option>
                    <flux:select.option value="30">30 dtk</flux:select.option>
                </flux:select>
            </div>

            {{-- Progress bar saat running --}}
            <div x-show="running" x-cloak class="flex items-center gap-2 text-xs text-zinc-500 dark:text-primary-dark-400">
                <div class="w-28 h-1.5 rounded-full bg-zinc-200 dark:bg-primary-dark-700 overflow-hidden">
                    <div class="h-full rounded-full bg-primary-500 transition-all duration-300"
                        :style="`width: ${total > 0 ? Math.round((progress / total) * 100) : 0}%`"></div>
                </div>
                <span x-text="currentLabel" class="font-medium text-zinc-600 dark:text-primary-dark-300 w-20 truncate"></span>
            </div>

            <button @click="startTestAll" :disabled="running"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold text-white transition-colors
                       bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed">
                <flux:icon name="play" class="w-4 h-4" />
                <span x-show="!running">Test Semua</span>
                <span x-show="running" x-cloak x-text="`${progress}/${total}`"></span>
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    @php
        $simpleServices = [
            [
                'key' => 'snomed',
                'label' => 'Snowstorm',
                'icon' => 'snomed',
                'url' => $snomedUrl,
                'result' => $snomedResult,
                'method' => 'testSnomed',
            ],
            [
                'key' => 'kfa',
                'label' => 'KFA',
                'icon' => 'satusehat',
                'url' => $kfaBaseUrl,
                'result' => $kfaResult,
                'method' => 'testKfa',
            ],
            [
                'key' => 'whatsapp',
                'label' => 'WA Gateway (' . strtoupper($activeGateway) . ')',
                'icon' => 'whatsapp',
                'url' => $activeGateway === 'waha' ? $wahaApiUrl : $gowaApiUrl,
                'result' => $whatsappResult,
                'method' => 'testWhatsapp',
            ],
            [
                'key' => 'rsonline',
                'label' => 'RS Online',
                'icon' => 'rsonline',
                'url' => $rsOnlineUrl,
                'result' => $rsOnlineResult,
                'method' => 'testRsOnline',
            ],
            [
                'key' => 'tte',
                'label' => 'TTE',
                'icon' => 'tte',
                'url' => $tteBaseUrl,
                'result' => $tteResult,
                'method' => 'testTte',
            ],
            [
                'key'    => 'simrs_db',
                'label'  => 'DB SIMRS',
                'icon'   => 'sirs',
                'url'    => $simrsDbHost . ' / ' . $simrsDbName,
                'result' => $simrsDbResult,
                'method' => 'testSimrsDb',
                'is_db'  => true,
            ],
            [
                'key'    => 'dicom',
                'label'  => 'DICOM (PACS) ' . strtoupper($dicomProvider),
                'icon'   => 'photo',
                'url'    => $dicomUrl,
                'result' => $dicomResult,
                'method' => 'testDicom',
            ],
        ];
    @endphp

    {{-- Layanan Lain (SNOMED, KFA, WA, RS Online, TTE, DB SIMRS) --}}
    <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        @foreach ($simpleServices as $svc)
            @php
                $r = $svc['result'];
                $st = $r['status'];
                $topBar = match ($st) {
                    'success' => 'bg-green-500',
                    'failed' => 'bg-red-500',
                    default => 'bg-zinc-200 dark:bg-primary-dark-600',
                };
                $badgeCls = match ($st) {
                    'success' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                    'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                    default => 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400',
                };
                $badgeLabel = match ($st) {
                    'success' => 'OK',
                    'failed' => 'Gagal',
                    default => '—',
                };
            @endphp
            <div
                class="bg-white dark:bg-primary-dark-800 rounded-xl shadow-sm border border-zinc-200 dark:border-primary-dark-700 overflow-hidden flex flex-col">
                <div class="h-1 {{ $topBar }} transition-colors duration-300"></div>
                <div class="p-4 flex flex-col flex-1">
                    {{-- Service name + badge --}}
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <flux:icon.{{ $svc['icon'] }}
                                class="w-4 h-4 text-zinc-500 dark:text-primary-dark-400 shrink-0" />
                            <span
                                class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100 truncate">{{ $svc['label'] }}</span>
                        </div>
                        <span
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium shrink-0 {{ $badgeCls }}">
                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                            {{ $badgeLabel }}
                        </span>
                    </div>

                    {{-- URL --}}
                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500 truncate mb-3"
                        title="{{ $svc['url'] }}">
                        {{ $svc['url'] ?: 'Belum dikonfigurasi' }}
                    </p>

                    {{-- Result message --}}
                    <div class="flex-1">
                        @if ($st !== 'pending')
                            <p
                                class="text-xs leading-relaxed {{ $st === 'success' ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                {{ $r['message'] }}
                            </p>
                            <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-1">
                                @if ($r['response_time'] !== null)
                                    {{ number_format($r['response_time']) }} ms
                                @endif
                                @if (!empty($svc['is_db']))
                                    <span
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 font-mono text-[10px] text-zinc-500 dark:text-primary-dark-400">
                                        TCP
                                    </span>
                                @elseif ($r['http_status'])
                                    · HTTP {{ $r['http_status'] }}
                                @endif
                            </p>
                        @else
                            <p class="text-xs text-zinc-400 italic">Belum ditest</p>
                        @endif
                    </div>

                    {{-- Test button --}}
                    <div class="mt-3 flex justify-end">
                        <x-atoms.button wire:click="{{ $svc['method'] }}" size="xs" variant="outline" icon="play"
                            wire:loading.attr="disabled" wire:target="{{ $svc['method'] }}">
                            <span wire:loading.remove wire:target="{{ $svc['method'] }}">Test</span>
                            <span wire:loading wire:target="{{ $svc['method'] }}">...</span>
                        </x-atoms.button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- BPJS Kesehatan --}}
    @php
        $bpjsAllTested = $bpjsPendingCount === 0;
        $bpjsAllOk = $bpjsAllTested && $bpjsFailedCount === 0;
        $bpjsAllFail = $bpjsAllTested && $bpjsSuccessCount === 0;
        $bpjsTopBar = match (true) {
            !$bpjsAllTested => 'bg-zinc-200 dark:bg-primary-dark-600',
            $bpjsAllOk => 'bg-green-500',
            $bpjsAllFail => 'bg-red-500',
            default => 'bg-amber-500',
        };
    @endphp
    <div
        class="bg-white dark:bg-primary-dark-800 rounded-xl shadow-sm border border-zinc-200 dark:border-primary-dark-700 mb-6 overflow-hidden">
        <div class="h-1 {{ $bpjsTopBar }} transition-colors duration-300"></div>

        {{-- Panel Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 dark:border-primary-dark-700">
            <div class="flex items-center gap-3">
                <flux:icon.bpjs class="w-5 h-5 text-zinc-500 dark:text-primary-dark-400" />
                <div>
                    <h2 class="font-semibold text-zinc-900 dark:text-primary-dark-100">BPJS Kesehatan</h2>
                    <div class="flex items-center gap-3 mt-0.5 text-sm text-zinc-500 dark:text-primary-dark-400">
                        <span>{{ $bpjsTotalEndpoints }} endpoint</span>
                        @if ($bpjsAllTested)
                            <span class="text-green-600 dark:text-green-400">{{ $bpjsSuccessCount }} OK</span>
                            @if ($bpjsFailedCount > 0)
                                <span class="text-red-500 dark:text-red-400">{{ $bpjsFailedCount }} gagal</span>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
            <x-atoms.button wire:click="testBpjs" icon="play" size="sm" variant="primary"
                wire:loading.attr="disabled" wire:target="testBpjs, testBpjsModule, testBpjsEndpoint">
                <span wire:loading.remove wire:target="testBpjs">Test BPJS</span>
                <span wire:loading wire:target="testBpjs">Menguji...</span>
            </x-atoms.button>
        </div>

        {{-- Module Sections --}}
        @foreach ($bpjsModules as $module)
            @php
                $mTested = $module['pending_count'] === 0;
                $mAllOk = $mTested && $module['failed_count'] === 0;
                $mAllFail = $mTested && $module['success_count'] === 0;
                $mDotCls = match (true) {
                    !$mTested => 'bg-zinc-300 dark:bg-primary-dark-600',
                    $mAllOk => 'bg-green-500',
                    $mAllFail => 'bg-red-500',
                    default => 'bg-amber-500',
                };
            @endphp
            <div class="border-t border-zinc-100 dark:border-primary-dark-700">
                {{-- Module Header --}}
                <div class="flex items-center justify-between px-6 py-3 bg-zinc-50 dark:bg-primary-dark-900/40">
                    <div class="flex items-center gap-3">
                        <span
                            class="w-2.5 h-2.5 rounded-full {{ $mDotCls }} shrink-0 transition-colors duration-300"></span>
                        <div>
                            <span
                                class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">{{ $module['name'] }}</span>
                            <span
                                class="text-xs text-zinc-400 dark:text-primary-dark-500 ml-2">{{ $module['description'] }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        @if ($mTested)
                            <span class="text-green-600 dark:text-green-400">{{ $module['success_count'] }} OK</span>
                            @if ($module['failed_count'] > 0)
                                <span class="text-red-500 dark:text-red-400">{{ $module['failed_count'] }} gagal</span>
                            @endif
                        @endif
                        @if (!$module['is_configured'])
                            <span class="text-amber-600 dark:text-amber-400">Belum dikonfigurasi</span>
                        @endif
                        <x-atoms.button wire:click="testBpjsModule('{{ $module['key'] }}')" icon="play" size="xs"
                            variant="ghost" wire:loading.attr="disabled"
                            wire:target="testBpjsModule('{{ $module['key'] }}'), testBpjs">
                            <span wire:loading.remove wire:target="testBpjsModule('{{ $module['key'] }}')">Test</span>
                            <span wire:loading wire:target="testBpjsModule('{{ $module['key'] }}')">...</span>
                        </x-atoms.button>
                    </div>
                </div>

                {{-- Module config info --}}
                <div
                    class="px-6 py-2 flex flex-wrap gap-x-6 gap-y-1 text-xs text-zinc-500 dark:text-primary-dark-400 border-b border-zinc-100 dark:border-primary-dark-700/50">
                    <span>Base URL: <span
                            class="font-mono text-zinc-700 dark:text-primary-dark-300">{{ $module['base_url'] ?: '-' }}</span></span>
                    <span>{{ $module['auth_label'] }}: <span
                            class="font-mono text-zinc-700 dark:text-primary-dark-300">{{ $module['auth_value'] ?: '-' }}</span></span>
                </div>

                {{-- Endpoint rows --}}
                <table class="w-full text-xs">
                    <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700/50">
                        @foreach ($module['endpoints'] as $index => $endpoint)
                            @php
                                $epResult = $endpoint['result'];
                                $epStatus = $epResult['status'];
                            @endphp
                            <tr wire:key="{{ $module['key'] }}_{{ $index }}"
                                class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/20">
                                <td class="pl-10 pr-3 py-2.5 w-16">
                                    <span
                                        class="inline-flex items-center justify-center px-1.5 py-0.5 rounded font-mono font-bold
                                        {{ match ($endpoint['method']) {'GET' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400','POST' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-400',default => 'bg-zinc-100 text-zinc-700 dark:bg-primary-dark-700 dark:text-primary-dark-300'} }}">
                                        {{ $endpoint['method'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="font-medium text-zinc-700 dark:text-primary-dark-200">{{ $endpoint['label'] }}</span>
                                    <span
                                        class="font-mono text-zinc-400 dark:text-primary-dark-500 ml-2 hidden sm:inline">{{ $endpoint['path'] }}</span>
                                </td>
                                <td class="px-3 py-2.5 w-24">
                                    @if ($epStatus === 'success')
                                        <span
                                            class="inline-flex items-center gap-1 text-green-600 dark:text-green-400 font-medium">
                                            <flux:icon name="check-circle" class="w-3.5 h-3.5" /> OK
                                        </span>
                                    @elseif ($epStatus === 'failed')
                                        <span
                                            class="inline-flex items-center gap-1 text-red-600 dark:text-red-400 font-medium">
                                            <flux:icon name="x-circle" class="w-3.5 h-3.5" /> Gagal
                                        </span>
                                    @else
                                        <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 hidden md:table-cell max-w-xs">
                                    @if ($epStatus !== 'pending')
                                        <span
                                            class="truncate block {{ $epStatus === 'success' ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}"
                                            title="{{ $epResult['message'] }}">{{ Str::limit($epResult['message'], 55) }}</span>
                                        @if ($epResult['meta_code'])
                                            <span class="text-zinc-400 dark:text-primary-dark-500">Meta
                                                {{ $epResult['meta_code'] }}</span>
                                        @endif
                                    @else
                                        <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                                    @endif
                                </td>
                                <td
                                    class="px-3 py-2.5 w-28 text-right hidden sm:table-cell whitespace-nowrap text-zinc-500 dark:text-primary-dark-400">
                                    @if ($epResult['response_time'] !== null)
                                        <span
                                            class="{{ $epResult['response_time'] > 3000 ? 'text-amber-500' : '' }}">{{ number_format($epResult['response_time']) }}
                                            ms</span>
                                    @endif
                                    @if ($epResult['http_status'] !== null)
                                        <span
                                            class="font-mono {{ $epResult['http_status'] < 300 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }} ml-2">{{ $epResult['http_status'] }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 w-10 text-right">
                                    <x-atoms.button
                                        wire:click="testBpjsEndpoint('{{ $module['key'] }}', {{ $index }})"
                                        icon="play" size="xs" variant="ghost" wire:loading.attr="disabled"
                                        wire:target="testBpjsEndpoint('{{ $module['key'] }}', {{ $index }}), testBpjsModule('{{ $module['key'] }}'), testBpjs" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach

        {{-- Aplicare --}}
        @php
            $aplResult = $aplicareModule['result'];
            $aplStatus = $aplResult['status'];
            $aplDotCls = match ($aplStatus) {
                'success' => 'bg-green-500',
                'failed' => 'bg-red-500',
                default => 'bg-zinc-300 dark:bg-primary-dark-600',
            };
        @endphp
        <div class="border-t border-zinc-100 dark:border-primary-dark-700">
            {{-- Module Header --}}
            <div class="flex items-center justify-between px-6 py-3 bg-zinc-50 dark:bg-primary-dark-900/40">
                <div class="flex items-center gap-3">
                    <span
                        class="w-2.5 h-2.5 rounded-full {{ $aplDotCls }} shrink-0 transition-colors duration-300"></span>
                    <div>
                        <span
                            class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">{{ $aplicareModule['name'] }}</span>
                        <span
                            class="text-xs text-zinc-400 dark:text-primary-dark-500 ml-2">{{ $aplicareModule['description'] }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    @if ($aplStatus === 'success')
                        <span class="text-green-600 dark:text-green-400">1 OK</span>
                    @elseif ($aplStatus === 'failed')
                        <span class="text-red-500 dark:text-red-400">1 gagal</span>
                    @endif
                    @if (!$aplicareModule['is_configured'])
                        <span class="text-amber-600 dark:text-amber-400">Belum dikonfigurasi</span>
                    @endif
                    <x-atoms.button wire:click="testAplicare" icon="play" size="xs" variant="ghost"
                        wire:loading.attr="disabled" wire:target="testAplicare">
                        <span wire:loading.remove wire:target="testAplicare">Test</span>
                        <span wire:loading wire:target="testAplicare">...</span>
                    </x-atoms.button>
                </div>
            </div>

            {{-- Config info --}}
            <div
                class="px-6 py-2 flex flex-wrap gap-x-6 gap-y-1 text-xs text-zinc-500 dark:text-primary-dark-400 border-b border-zinc-100 dark:border-primary-dark-700/50">
                <span>Base URL: <span
                        class="font-mono text-zinc-700 dark:text-primary-dark-300">{{ $aplicareModule['base_url'] ?: '-' }}</span></span>
                <span>Cons ID: <span
                        class="font-mono text-zinc-700 dark:text-primary-dark-300">{{ $aplicareModule['cons_id'] ?: '-' }}</span></span>
            </div>

            {{-- Result row --}}
            <table class="w-full text-xs">
                <tbody>
                    <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/20">
                        <td class="pl-10 pr-3 py-2.5 w-16">
                            <span
                                class="inline-flex items-center justify-center px-1.5 py-0.5 rounded font-mono font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400">GET</span>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="font-medium text-zinc-700 dark:text-primary-dark-200">Lihat Kamar</span>
                            <span
                                class="font-mono text-zinc-400 dark:text-primary-dark-500 ml-2 hidden sm:inline">/rest/bed/read/{kodePpk}/1/1</span>
                        </td>
                        <td class="px-3 py-2.5 w-24">
                            @if ($aplStatus === 'success')
                                <span
                                    class="inline-flex items-center gap-1 text-green-600 dark:text-green-400 font-medium">
                                    <flux:icon name="check-circle" class="w-3.5 h-3.5" /> OK
                                </span>
                            @elseif ($aplStatus === 'failed')
                                <span
                                    class="inline-flex items-center gap-1 text-red-600 dark:text-red-400 font-medium">
                                    <flux:icon name="x-circle" class="w-3.5 h-3.5" /> Gagal
                                </span>
                            @else
                                <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 hidden md:table-cell max-w-xs">
                            @if ($aplStatus !== 'pending')
                                <span
                                    class="truncate block {{ $aplStatus === 'success' ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}"
                                    title="{{ $aplResult['message'] }}">{{ Str::limit($aplResult['message'], 55) }}</span>
                            @else
                                <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                            @endif
                        </td>
                        <td
                            class="px-3 py-2.5 w-28 text-right hidden sm:table-cell whitespace-nowrap text-zinc-500 dark:text-primary-dark-400">
                            @if ($aplResult['response_time'] !== null)
                                <span
                                    class="{{ $aplResult['response_time'] > 3000 ? 'text-amber-500' : '' }}">{{ number_format($aplResult['response_time']) }}
                                    ms</span>
                            @endif
                            @if ($aplResult['http_status'] !== null)
                                <span
                                    class="font-mono {{ $aplResult['http_status'] < 300 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }} ml-2">{{ $aplResult['http_status'] }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 w-10 text-right">
                            <x-atoms.button wire:click="testAplicare" icon="play" size="xs" variant="ghost"
                                wire:loading.attr="disabled" wire:target="testAplicare, testBpjs" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Satu Sehat --}}
    @php
        $ssAllTested = $ssPendingCount === 0;
        $ssAllOk = $ssAllTested && $ssFailedCount === 0;
        $ssAllFail = $ssAllTested && $ssSuccessCount === 0;
        $ssTopBar = match (true) {
            !$ssAllTested => 'bg-zinc-200 dark:bg-primary-dark-600',
            $ssAllOk => 'bg-green-500',
            $ssAllFail => 'bg-red-500',
            default => 'bg-amber-500',
        };
        $tokenSt = $ssTokenResult['status'];
        $tokenDotCls = match ($tokenSt) {
            'success' => 'bg-green-500',
            'failed' => 'bg-red-500',
            default => 'bg-zinc-300 dark:bg-primary-dark-600',
        };
    @endphp
    <div
        class="bg-white dark:bg-primary-dark-800 rounded-xl shadow-sm border border-zinc-200 dark:border-primary-dark-700 overflow-hidden">
        <div class="h-1 {{ $ssTopBar }} transition-colors duration-300"></div>

        {{-- Panel Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 dark:border-primary-dark-700">
            <div class="flex items-center gap-3">
                <flux:icon.satusehat class="w-5 h-5 text-zinc-500 dark:text-primary-dark-400" />
                <div>
                    <h2 class="font-semibold text-zinc-900 dark:text-primary-dark-100">Satu Sehat</h2>
                    <div class="flex items-center gap-3 mt-0.5 text-sm text-zinc-500 dark:text-primary-dark-400">
                        <span>{{ $ssTotalResources }} FHIR resource</span>
                        @if ($ssAllTested)
                            <span class="text-green-600 dark:text-green-400">{{ $ssSuccessCount }} OK</span>
                            @if ($ssFailedCount > 0)
                                <span class="text-red-500 dark:text-red-400">{{ $ssFailedCount }} gagal</span>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                @if (!$ssIsConfigured)
                    <span class="text-xs text-amber-600 dark:text-amber-400">Konfigurasi tidak lengkap</span>
                @endif
                <x-atoms.button wire:click="testSatuSehat" icon="play" size="sm" variant="primary"
                    wire:loading.attr="disabled"
                    wire:target="testSatuSehat, testSatuSehatGroup, testSatuSehatResource">
                    <span wire:loading.remove wire:target="testSatuSehat">Test Satu Sehat</span>
                    <span wire:loading wire:target="testSatuSehat">Menguji...</span>
                </x-atoms.button>
            </div>
        </div>

        {{-- OAuth2 Token Section --}}
        <div class="border-b border-zinc-100 dark:border-primary-dark-700">
            <div class="flex items-center justify-between px-6 py-3 bg-zinc-50 dark:bg-primary-dark-900/40">
                <div class="flex items-center gap-3">
                    <span
                        class="w-2.5 h-2.5 rounded-full {{ $tokenDotCls }} shrink-0 transition-colors duration-300"></span>
                    <span class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">OAuth2 Token</span>
                    @if ($tokenSt !== 'pending')
                        <span
                            class="text-xs {{ $tokenSt === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            · {{ $ssTokenResult['message'] }}
                            @if ($ssTokenResult['response_time'] !== null)
                                ({{ number_format($ssTokenResult['response_time']) }} ms)
                            @endif
                        </span>
                    @endif
                </div>
                <x-atoms.button wire:click="testSatuSehatToken" icon="play" size="xs" variant="ghost"
                    wire:loading.attr="disabled" wire:target="testSatuSehatToken, testSatuSehat" />
            </div>
            <div class="px-6 py-2 flex flex-wrap gap-x-6 gap-y-1 text-xs text-zinc-500 dark:text-primary-dark-400">
                <span>Auth URL: <span
                        class="font-mono text-zinc-700 dark:text-primary-dark-300">{{ $ssAuthUrl ?: '-' }}</span></span>
                <span>Client ID: <span
                        class="font-mono text-zinc-700 dark:text-primary-dark-300">{{ $ssClientId ?: '-' }}</span></span>
                <span>Org ID: <span
                        class="font-mono text-zinc-700 dark:text-primary-dark-300">{{ $ssOrganizationId ?: '-' }}</span></span>
            </div>
        </div>

        {{-- Resource Groups --}}
        @foreach ($ssGrouped as $groupKey => $group)
            @php
                $gTested = $group['pending_count'] === 0;
                $gAllOk = $gTested && $group['failed_count'] === 0;
                $gAllFail = $gTested && $group['success_count'] === 0;
                $gDotCls = match (true) {
                    !$gTested => 'bg-zinc-300 dark:bg-primary-dark-600',
                    $gAllOk => 'bg-green-500',
                    $gAllFail => 'bg-red-500',
                    default => 'bg-amber-500',
                };
            @endphp
            <div class="border-t border-zinc-100 dark:border-primary-dark-700">
                {{-- Group Header --}}
                <div class="flex items-center justify-between px-6 py-3 bg-zinc-50 dark:bg-primary-dark-900/40">
                    <div class="flex items-center gap-3">
                        <span
                            class="w-2.5 h-2.5 rounded-full {{ $gDotCls }} shrink-0 transition-colors duration-300"></span>
                        <span
                            class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">{{ $group['group'] }}</span>
                        <span class="text-xs text-zinc-400 dark:text-primary-dark-500">{{ count($group['items']) }}
                            resource</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        @if ($gTested)
                            <span class="text-green-600 dark:text-green-400">{{ $group['success_count'] }} OK</span>
                            @if ($group['failed_count'] > 0)
                                <span class="text-red-500 dark:text-red-400">{{ $group['failed_count'] }} gagal</span>
                            @endif
                        @endif
                        <x-atoms.button wire:click="testSatuSehatGroup('{{ $group['group'] }}')" icon="play"
                            size="xs" variant="ghost" wire:loading.attr="disabled"
                            wire:target="testSatuSehatGroup('{{ $group['group'] }}'), testSatuSehat">
                            <span wire:loading.remove wire:target="testSatuSehatGroup('{{ $group['group'] }}')">Test
                                Grup</span>
                            <span wire:loading wire:target="testSatuSehatGroup('{{ $group['group'] }}')">...</span>
                        </x-atoms.button>
                    </div>
                </div>

                {{-- Resource rows --}}
                <table class="w-full text-xs">
                    <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700/50">
                        @foreach ($group['items'] as $item)
                            @php
                                $itemResult = $item['result'];
                                $itemStatus = $itemResult['status'];
                                $itemMethod = $item['method'] ?? 'GET';
                            @endphp
                            <tr wire:key="ss_{{ $item['type'] }}"
                                class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/20">
                                <td class="pl-10 pr-3 py-2.5 w-16">
                                    <span
                                        class="inline-flex items-center justify-center px-1.5 py-0.5 rounded font-mono font-bold
                                        {{ match ($itemMethod) {
                                            'POST' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-400',
                                            'PUT' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
                                            'DELETE' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
                                            default => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
                                        } }}">
                                        {{ $itemMethod }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="font-medium text-zinc-700 dark:text-primary-dark-200">{{ $item['label'] }}</span>
                                    <span
                                        class="font-mono text-zinc-400 dark:text-primary-dark-500 ml-2 hidden sm:inline">{{ $item['type'] }}</span>
                                </td>
                                <td class="px-3 py-2.5 w-24">
                                    @if ($itemStatus === 'success')
                                        <span
                                            class="inline-flex items-center gap-1 text-green-600 dark:text-green-400 font-medium">
                                            <flux:icon name="check-circle" class="w-3.5 h-3.5" /> OK
                                        </span>
                                    @elseif ($itemStatus === 'failed')
                                        <span
                                            class="inline-flex items-center gap-1 text-red-600 dark:text-red-400 font-medium">
                                            <flux:icon name="x-circle" class="w-3.5 h-3.5" /> Gagal
                                        </span>
                                    @else
                                        <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 hidden md:table-cell max-w-xs">
                                    @if ($itemStatus !== 'pending')
                                        <span
                                            class="truncate block {{ $itemStatus === 'success' ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}"
                                            title="{{ $itemResult['message'] }}">{{ Str::limit($itemResult['message'], 55) }}</span>
                                    @else
                                        <span class="text-zinc-300 dark:text-primary-dark-600">—</span>
                                    @endif
                                </td>
                                <td
                                    class="px-3 py-2.5 w-28 text-right hidden sm:table-cell whitespace-nowrap text-zinc-500 dark:text-primary-dark-400">
                                    @if ($itemResult['response_time'] !== null)
                                        <span
                                            class="{{ $itemResult['response_time'] > 3000 ? 'text-amber-500' : '' }}">{{ number_format($itemResult['response_time']) }}
                                            ms</span>
                                    @endif
                                    @if ($itemResult['http_status'] !== null)
                                        <span
                                            class="font-mono {{ $itemResult['http_status'] < 300 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }} ml-2">{{ $itemResult['http_status'] }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 w-10 text-right">
                                    <x-atoms.button wire:click="testSatuSehatResource('{{ $item['type'] }}')"
                                        icon="play" size="xs" variant="ghost" wire:loading.attr="disabled"
                                        wire:target="testSatuSehatResource('{{ $item['type'] }}'), testSatuSehatGroup('{{ $group['group'] }}'), testSatuSehat" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</div>
