<?php

use App\Models\Bpjs\BpjsLog;
use App\Models\Simrs\AplicareKetersediaanKamar;
use App\Models\Simrs\Kamar;
use App\Models\Simrs\BangsalGroup;
use App\Services\Bpjs\AplicareService;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('BPJS — Aplicare')] class extends Component {
    #[Url]
    public string $tab = 'simrs';

    public bool $apiLoaded = false;
    public array $apiData = [];

    // Modal hapus dari tab Aplicare
    public bool $showDeleteModal = false;
    public array $deleteTarget = [];

    // Modal tambah mapping
    public bool $showAddMappingModal = false;
    public array $addMappingData = [];
    public array $addMappingForm = [];

    // Modal edit mapping
    public bool $showEditMappingModal = false;
    public array $editMappingData = [];
    public array $editMappingForm = [];

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function loadApiData(bool $force = false): void
    {
        if ($this->apiLoaded && !$force) {
            return;
        }

        $service = app(AplicareService::class);

        if (!$service->isConfigured()) {
            $this->toastError('Konfigurasi Aplicare belum lengkap.');
            return;
        }

        $start = microtime(true);
        $result = $service->getBeds();
        $responseTime = round((microtime(true) - $start) * 1000, 2);

        BpjsLog::record(service: 'aplicare', status: $result['success'] ? 'success' : 'failed', method: 'GET', endpoint: $service->baseUrl() . "/rest/bed/read/{$service->kodePpk()}/1/1000", responseStatus: $result['http_status'], responseTime: $responseTime, responsePayload: is_array($result['data']) ? $result['data'] : null, errorMessage: $result['success'] ? null : $result['message'], success: $result['success']);

        if (!$result['success']) {
            $this->toastError($result['message']);
            return;
        }

        $list = $result['data']['response']['list'] ?? [];
        $this->apiData = is_array($list) ? $list : [];
        $this->apiLoaded = true;
    }

    /** Sync satu baris SIMRS ke Aplicare (create atau update) */
    public function syncRow(string $kdBangsal, string $kodeKelas): void
    {
        try {
            $row = AplicareKetersediaanKamar::where('kd_bangsal', $kdBangsal)->where('kode_kelas_aplicare', $kodeKelas)->with('bangsal')->first();

            if (!$row) {
                $this->toastError("Data tidak ditemukan: {$kdBangsal} / {$kodeKelas}");
                return;
            }
        } catch (\Exception $e) {
            $this->toastError('Koneksi SIMRS gagal: ' . $e->getMessage());
            return;
        }

        $service = app(AplicareService::class);
        if (!$service->isConfigured()) {
            $this->toastError('Konfigurasi Aplicare belum lengkap.');
            return;
        }

        $stats = $this->computeBedStats($row->kd_bangsal, $row->kelas);

        $payload = [
            'kodekelas' => $row->kode_kelas_aplicare,
            'koderuang' => $row->kd_bangsal,
            'namaruang' => $row->bangsal?->nm_bangsal ?? $row->kd_bangsal,
            'kapasitas' => $stats['kapasitas'],
            'tersedia' => $stats['tersedia'],
            'tersediapria' => $stats['tersediapria'],
            'tersediawanita' => $stats['tersediawanita'],
            'tersediapriawanita' => $stats['tersediapriawanita'],
        ];

        $apiMap = $this->buildApiMap();
        $key = "{$kdBangsal}|{$kodeKelas}";
        $exists = $apiMap->has($key);

        $start = microtime(true);
        $result = $exists ? $service->updateBed($payload) : $service->createBed($payload);
        $responseTime = round((microtime(true) - $start) * 1000, 2);

        BpjsLog::record(service: 'aplicare', status: $result['success'] ? 'success' : 'failed', method: 'POST', endpoint: $exists ? $service->baseUrl() . "/rest/bed/update/{$service->kodePpk()}" : $service->baseUrl() . "/rest/bed/create/{$service->kodePpk()}", requestPayload: $payload, responseStatus: $result['http_status'], responseTime: $responseTime, responsePayload: is_array($result['data']) ? $result['data'] : null, errorMessage: $result['success'] ? null : $result['message'], success: $result['success']);

        if (!$result['success']) {
            $this->toastError("[{$kdBangsal}] " . $result['message']);
            return;
        }

        $this->toastSuccess('Berhasil ' . ($exists ? 'memperbarui' : 'menambahkan') . " {$row->bangsal?->nm_bangsal} ke Aplicare.");
        $this->apiLoaded = false;
        $this->apiData = [];
    }

    /** Sync semua baris SIMRS ke Aplicare */
    public function syncAll(): void
    {
        $service = app(AplicareService::class);
        if (!$service->isConfigured()) {
            $this->toastError('Konfigurasi Aplicare belum lengkap.');
            return;
        }

        try {
            $rows = AplicareKetersediaanKamar::with('bangsal')->get();
        } catch (\Exception $e) {
            $this->toastError('Koneksi SIMRS gagal: ' . $e->getMessage());
            return;
        }

        if ($rows->isEmpty()) {
            $this->toastWarning('Tidak ada data di SIMRS untuk disinkronkan.');
            return;
        }

        // Muat data API saat ini bila belum dimuat
        if (!$this->apiLoaded) {
            $apiResult = $service->getBeds();
            if ($apiResult['success']) {
                $list = $apiResult['data']['response']['list'] ?? [];
                $this->apiData = is_array($list) ? $list : [];
                $this->apiLoaded = true;
            }
        }

        $apiMap = $this->buildApiMap();
        $baseUrl = $service->baseUrl();
        $kodePpk = $service->kodePpk();
        $success = 0;
        $errors = [];

        foreach ($rows as $row) {
            $key = "{$row->kd_bangsal}|{$row->kode_kelas_aplicare}";
            $exists = $apiMap->has($key);
            $stats = $this->computeBedStats($row->kd_bangsal, $row->kelas);

            $payload = [
                'kodekelas' => $row->kode_kelas_aplicare,
                'koderuang' => $row->kd_bangsal,
                'namaruang' => $row->bangsal?->nm_bangsal ?? $row->kd_bangsal,
                'kapasitas' => $stats['kapasitas'],
                'tersedia' => $stats['tersedia'],
                'tersediapria' => $stats['tersediapria'],
                'tersediawanita' => $stats['tersediawanita'],
                'tersediapriawanita' => $stats['tersediapriawanita'],
            ];

            $start = microtime(true);
            $result = $exists ? $service->updateBed($payload) : $service->createBed($payload);
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            BpjsLog::record(service: 'aplicare', status: $result['success'] ? 'success' : 'failed', method: 'POST', endpoint: $exists ? "{$baseUrl}/rest/bed/update/{$kodePpk}" : "{$baseUrl}/rest/bed/create/{$kodePpk}", requestPayload: $payload, responseStatus: $result['http_status'], responseTime: $responseTime, responsePayload: is_array($result['data']) ? $result['data'] : null, errorMessage: $result['success'] ? null : $result['message'], success: $result['success']);

            if ($result['success']) {
                $success++;
            } else {
                $errors[] = $row->kd_bangsal . ': ' . $result['message'];
            }
        }

        if (!empty($errors)) {
            $this->toastError(implode('; ', array_slice($errors, 0, 3)));
        }
        if ($success > 0) {
            $this->toastSuccess("Berhasil sync {$success} ruangan ke Aplicare.");
        }

        $this->apiLoaded = false;
        $this->apiData = [];
    }

    public function openAddMapping(string $kdBangsal, string $kelas, string $nmBangsal): void
    {
        $this->addMappingData = ['kd_bangsal' => $kdBangsal, 'kelas' => $kelas];
        try {
            $stats = $this->computeBedStats($kdBangsal, $kelas);
        } catch (\Exception) {
            $stats = ['kapasitas' => 0, 'tersedia' => 0, 'tersediapria' => 0, 'tersediawanita' => 0, 'tersediapriawanita' => 0];
        }
        $this->addMappingForm = [
            'kodekelas' => '',
            'koderuang' => $kdBangsal,
            'namaruang' => $nmBangsal,
            'kapasitas' => $stats['kapasitas'],
            'tersedia' => $stats['tersedia'],
            'tersediapria' => $stats['tersediapria'],
            'tersediawanita' => $stats['tersediawanita'],
            'tersediapriawanita' => $stats['tersediapriawanita'],
        ];
        $this->showAddMappingModal = true;
    }

    public function saveAddMapping(): void
    {
        $this->validate([
            'addMappingForm.kodekelas' => 'required|string',
            'addMappingForm.kapasitas' => 'required|integer|min:0',
            'addMappingForm.tersedia' => 'required|integer|min:0',
            'addMappingForm.tersediapria' => 'required|integer|min:0',
            'addMappingForm.tersediawanita' => 'required|integer|min:0',
            'addMappingForm.tersediapriawanita' => 'required|integer|min:0',
        ]);

        $service = app(AplicareService::class);
        if (!$service->isConfigured()) {
            $this->toastError('Konfigurasi Aplicare belum lengkap.');
            return;
        }

        try {
            AplicareKetersediaanKamar::updateOrCreate(
                ['kd_bangsal' => $this->addMappingData['kd_bangsal'], 'kelas' => $this->addMappingData['kelas']],
                [
                    'kode_kelas_aplicare' => $this->addMappingForm['kodekelas'],
                    'kapasitas' => (int) $this->addMappingForm['kapasitas'],
                    'tersedia' => (int) $this->addMappingForm['tersedia'],
                    'tersediapria' => (int) $this->addMappingForm['tersediapria'],
                    'tersediawanita' => (int) $this->addMappingForm['tersediawanita'],
                    'tersediapriawanita' => (int) $this->addMappingForm['tersediapriawanita'],
                ],
            );
        } catch (\Exception $e) {
            $this->toastError('Gagal menyimpan mapping SIMRS: ' . $e->getMessage());
            return;
        }

        if (!$this->apiLoaded) {
            $apiResult = $service->getBeds();
            if ($apiResult['success']) {
                $list = $apiResult['data']['response']['list'] ?? [];
                $this->apiData = is_array($list) ? $list : [];
                $this->apiLoaded = true;
            }
        }

        $apiMap = $this->buildApiMap();
        $key = "{$this->addMappingForm['koderuang']}|{$this->addMappingForm['kodekelas']}";
        $exists = $apiMap->has($key);

        $payload = [
            'kodekelas' => $this->addMappingForm['kodekelas'],
            'koderuang' => $this->addMappingForm['koderuang'],
            'namaruang' => $this->addMappingForm['namaruang'],
            'kapasitas' => (int) $this->addMappingForm['kapasitas'],
            'tersedia' => (int) $this->addMappingForm['tersedia'],
            'tersediapria' => (int) $this->addMappingForm['tersediapria'],
            'tersediawanita' => (int) $this->addMappingForm['tersediawanita'],
            'tersediapriawanita' => (int) $this->addMappingForm['tersediapriawanita'],
        ];

        $start = microtime(true);
        $result = $exists ? $service->updateBed($payload) : $service->createBed($payload);
        $responseTime = round((microtime(true) - $start) * 1000, 2);

        BpjsLog::record(service: 'aplicare', status: $result['success'] ? 'success' : 'failed', method: 'POST', endpoint: $exists ? $service->baseUrl() . "/rest/bed/update/{$service->kodePpk()}" : $service->baseUrl() . "/rest/bed/create/{$service->kodePpk()}", requestPayload: $payload, responseStatus: $result['http_status'], responseTime: $responseTime, responsePayload: is_array($result['data']) ? $result['data'] : null, errorMessage: $result['success'] ? null : $result['message'], success: $result['success']);

        $this->showAddMappingModal = false;
        $this->addMappingForm = [];
        $this->addMappingData = [];
        $this->apiLoaded = false;
        $this->apiData = [];

        if (!$result['success']) {
            $this->toastWarning("Mapping tersimpan di SIMRS, namun gagal kirim ke Aplicare: {$result['message']}");
            return;
        }

        $this->toastSuccess('Berhasil menambahkan dan mengirim data ke Aplicare.');
    }

    public function openEditMapping(string $kdBangsal, string $kelas, string $kodeKelasAplicare, string $nmBangsal): void
    {
        $this->editMappingData = ['kd_bangsal' => $kdBangsal, 'kelas' => $kelas];
        try {
            $stats = $this->computeBedStats($kdBangsal, $kelas);
        } catch (\Exception) {
            $stats = ['kapasitas' => 0, 'tersedia' => 0, 'tersediapria' => 0, 'tersediawanita' => 0, 'tersediapriawanita' => 0];
        }
        $this->editMappingForm = [
            'kodekelas' => $kodeKelasAplicare,
            'koderuang' => $kdBangsal,
            'namaruang' => $nmBangsal,
            'kapasitas' => $stats['kapasitas'],
            'tersedia' => $stats['tersedia'],
            'tersediapria' => $stats['tersediapria'],
            'tersediawanita' => $stats['tersediawanita'],
            'tersediapriawanita' => $stats['tersediapriawanita'],
        ];
        $this->showEditMappingModal = true;
    }

    public function saveEditMapping(): void
    {
        $this->validate([
            'editMappingForm.kodekelas' => 'required|string',
            'editMappingForm.kapasitas' => 'required|integer|min:0',
            'editMappingForm.tersedia' => 'required|integer|min:0',
            'editMappingForm.tersediapria' => 'required|integer|min:0',
            'editMappingForm.tersediawanita' => 'required|integer|min:0',
            'editMappingForm.tersediapriawanita' => 'required|integer|min:0',
        ]);

        $service = app(AplicareService::class);
        if (!$service->isConfigured()) {
            $this->toastError('Konfigurasi Aplicare belum lengkap.');
            return;
        }

        try {
            AplicareKetersediaanKamar::where('kd_bangsal', $this->editMappingData['kd_bangsal'])
                ->where('kelas', $this->editMappingData['kelas'])
                ->update([
                    'kode_kelas_aplicare' => $this->editMappingForm['kodekelas'],
                    'kapasitas' => (int) $this->editMappingForm['kapasitas'],
                    'tersedia' => (int) $this->editMappingForm['tersedia'],
                    'tersediapria' => (int) $this->editMappingForm['tersediapria'],
                    'tersediawanita' => (int) $this->editMappingForm['tersediawanita'],
                    'tersediapriawanita' => (int) $this->editMappingForm['tersediapriawanita'],
                ]);
        } catch (\Exception $e) {
            $this->toastError('Gagal memperbarui mapping SIMRS: ' . $e->getMessage());
            return;
        }

        $payload = [
            'kodekelas' => $this->editMappingForm['kodekelas'],
            'koderuang' => $this->editMappingForm['koderuang'],
            'namaruang' => $this->editMappingForm['namaruang'],
            'kapasitas' => (int) $this->editMappingForm['kapasitas'],
            'tersedia' => (int) $this->editMappingForm['tersedia'],
            'tersediapria' => (int) $this->editMappingForm['tersediapria'],
            'tersediawanita' => (int) $this->editMappingForm['tersediawanita'],
            'tersediapriawanita' => (int) $this->editMappingForm['tersediapriawanita'],
        ];

        $start = microtime(true);
        $result = $service->updateBed($payload);
        $responseTime = round((microtime(true) - $start) * 1000, 2);

        BpjsLog::record(service: 'aplicare', status: $result['success'] ? 'success' : 'failed', method: 'POST', endpoint: $service->baseUrl() . "/rest/bed/update/{$service->kodePpk()}", requestPayload: $payload, responseStatus: $result['http_status'], responseTime: $responseTime, responsePayload: is_array($result['data']) ? $result['data'] : null, errorMessage: $result['success'] ? null : $result['message'], success: $result['success']);

        $this->showEditMappingModal = false;
        $this->editMappingForm = [];
        $this->editMappingData = [];
        $this->apiLoaded = false;
        $this->apiData = [];

        if (!$result['success']) {
            $this->toastWarning("Mapping diperbarui di SIMRS, namun gagal kirim ke Aplicare: {$result['message']}");
            return;
        }

        $this->toastSuccess('Berhasil memperbarui dan mengirim data ke Aplicare.');
    }

    /** Sync semua bangsal dalam satu grup ke Aplicare */
    public function syncGroup(string $idGroup): void
    {
        $service = app(AplicareService::class);
        if (!$service->isConfigured()) {
            $this->toastError('Konfigurasi Aplicare belum lengkap.');
            return;
        }

        try {
            $group = BangsalGroup::with([
                'bangsals.kamars' => fn($q) => $q->where('statusdata', '1'),
            ])->find($idGroup);

            if (!$group) {
                $this->toastError('Grup tidak ditemukan.');
                return;
            }

            $bangsalCodes = $group->bangsals->pluck('kd_bangsal')->unique()->toArray();
            $rows = AplicareKetersediaanKamar::with('bangsal')->whereIn('kd_bangsal', $bangsalCodes)->get();
        } catch (\Exception $e) {
            $this->toastError('Koneksi SIMRS gagal: ' . $e->getMessage());
            return;
        }

        if ($rows->isEmpty()) {
            $this->toastWarning('Tidak ada mapping Aplicare untuk grup ini. Tambahkan mapping di SIMRS terlebih dahulu.');
            return;
        }

        if (!$this->apiLoaded) {
            $apiResult = $service->getBeds();
            if ($apiResult['success']) {
                $list = $apiResult['data']['response']['list'] ?? [];
                $this->apiData = is_array($list) ? $list : [];
                $this->apiLoaded = true;
            }
        }

        $apiMap = $this->buildApiMap();
        $baseUrl = $service->baseUrl();
        $kodePpk = $service->kodePpk();
        $success = 0;
        $errors = [];

        foreach ($rows as $row) {
            $key = "{$row->kd_bangsal}|{$row->kode_kelas_aplicare}";
            $exists = $apiMap->has($key);
            $stats = $this->computeBedStats($row->kd_bangsal, $row->kelas);

            $payload = [
                'kodekelas' => $row->kode_kelas_aplicare,
                'koderuang' => $row->kd_bangsal,
                'namaruang' => $row->bangsal?->nm_bangsal ?? $row->kd_bangsal,
                'kapasitas' => $stats['kapasitas'],
                'tersedia' => $stats['tersedia'],
                'tersediapria' => $stats['tersediapria'],
                'tersediawanita' => $stats['tersediawanita'],
                'tersediapriawanita' => $stats['tersediapriawanita'],
            ];

            $start = microtime(true);
            $result = $exists ? $service->updateBed($payload) : $service->createBed($payload);
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            BpjsLog::record(service: 'aplicare', status: $result['success'] ? 'success' : 'failed', method: 'POST', endpoint: $exists ? "{$baseUrl}/rest/bed/update/{$kodePpk}" : "{$baseUrl}/rest/bed/create/{$kodePpk}", requestPayload: $payload, responseStatus: $result['http_status'], responseTime: $responseTime, responsePayload: is_array($result['data']) ? $result['data'] : null, errorMessage: $result['success'] ? null : $result['message'], success: $result['success']);

            if ($result['success']) {
                $success++;
            } else {
                $errors[] = $row->kd_bangsal . ': ' . $result['message'];
            }
        }

        if (!empty($errors)) {
            $this->toastError(implode('; ', array_slice($errors, 0, 3)));
        }
        if ($success > 0) {
            $this->toastSuccess("Berhasil sync {$success} ruangan dalam grup ke Aplicare.");
        }

        $this->apiLoaded = false;
        $this->apiData = [];
    }

    public function confirmDelete(string $kodeRuang, string $kodeKelas): void
    {
        $this->deleteTarget = ['koderuang' => $kodeRuang, 'kodekelas' => $kodeKelas];
        $this->showDeleteModal = true;
    }

    public function doDelete(): void
    {
        $this->showDeleteModal = false;

        if (empty($this->deleteTarget)) {
            return;
        }

        $service = app(AplicareService::class);
        $payload = [
            'kodekelas' => $this->deleteTarget['kodekelas'],
            'koderuang' => $this->deleteTarget['koderuang'],
        ];

        $start = microtime(true);
        $result = $service->deleteBed($payload);
        $responseTime = round((microtime(true) - $start) * 1000, 2);

        BpjsLog::record(service: 'aplicare', status: $result['success'] ? 'success' : 'failed', method: 'POST', endpoint: $service->baseUrl() . "/rest/bed/delete/{$service->kodePpk()}", requestPayload: $payload, responseStatus: $result['http_status'], responseTime: $responseTime, responsePayload: is_array($result['data']) ? $result['data'] : null, errorMessage: $result['success'] ? null : $result['message'], success: $result['success']);

        if (!$result['success']) {
            $this->toastError($result['message']);
        } else {
            // Hapus juga mapping di SIMRS
            try {
                AplicareKetersediaanKamar::where('kd_bangsal', $this->deleteTarget['koderuang'])->where('kode_kelas_aplicare', $this->deleteTarget['kodekelas'])->delete();
            } catch (\Exception $e) {
                $this->toastWarning('Berhasil hapus dari Aplicare, namun gagal hapus mapping SIMRS: ' . $e->getMessage());
            }

            $this->toastSuccess("Berhasil menghapus ruangan {$this->deleteTarget['koderuang']} dari Aplicare dan mapping SIMRS.");
            $this->apiLoaded = false;
            $this->apiData = [];
            $this->loadApiData();
        }

        $this->deleteTarget = [];
    }

    /**
     * Hitung kapasitas & ketersediaan kamar dari tabel kamar secara live.
     * tersediapria/wanita/priawanita = tersedia (tidak ada kolom jenis kelamin di kamar).
     */
    private function computeBedStats(string $kdBangsal, string $kelas): array
    {
        $base = Kamar::where('statusdata', '1')->where('kd_bangsal', $kdBangsal)->where('kelas', $kelas);

        $kapasitas = (clone $base)->count();
        $tersedia = (clone $base)->where('status', 'KOSONG')->count();

        return [
            'kapasitas' => $kapasitas,
            'tersedia' => $tersedia,
            'tersediapria' => $tersedia,
            'tersediawanita' => $tersedia,
            'tersediapriawanita' => $tersedia,
        ];
    }

    /** Ambil opsi kode kelas dari API Aplicare dengan cache 1 hari. Fallback ke config jika API gagal. */
    private function getKelasOptions(): array
    {
        $default = config('bpjs.aplicare.kode_kelas_options', []);
        $service = app(AplicareService::class);

        if (!$service->isConfigured()) {
            return $default;
        }

        $cached = Cache::get('aplicare_kode_kelas');
        if ($cached !== null) {
            return $cached;
        }

        $result = $service->getKelas();
        if (!$result['success']) {
            return $default;
        }

        $response = $result['data']['response'] ?? [];
        $raw = isset($response['list']) ? $response['list'] : (is_array($response) && !isset($response['kode']) ? $response : [$response]);
        $opts = collect(is_array($raw) ? $raw : [])
            ->map(fn($item) => is_string($item) ? $item : $item['kode'] ?? ($item['kd_kelas'] ?? ($item['kodekelas'] ?? null)))
            ->filter()
            ->values()
            ->toArray();

        if (empty($opts)) {
            return $default;
        }

        Cache::put('aplicare_kode_kelas', $opts, now()->addDay());

        return $opts;
    }

    public function refreshKelasCache(): void
    {
        Cache::forget('aplicare_kode_kelas');
        $this->toastSuccess('Cache opsi kode kelas berhasil diperbarui.');
    }

    private function buildApiMap(): \Illuminate\Support\Collection
    {
        return collect($this->apiData)->keyBy(fn($r) => ($r['koderuang'] ?? '') . '|' . ($r['kodekelas'] ?? ''));
    }

    public function with(): array
    {
        $simrsError = null;
        $groupedRows = collect();

        try {
            // Load semua grup beserta kamar aktif (eager load) melalui bangsal
            $kamarGroups = BangsalGroup::with([
                'bangsals.kamars' => fn($q) => $q->where('statusdata', '1')->with('bangsal'),
            ])->get();

            // Lookup AplicareKetersediaanKamar per kd_bangsal|kelas
            $aplicareMaps = AplicareKetersediaanKamar::with('bangsal')->get()->groupBy(fn($r) => "{$r->kd_bangsal}|{$r->kelas}");

            $apiMap = $this->buildApiMap();

            $groupedRows = $kamarGroups->map(function ($group) use ($aplicareMaps, $apiMap) {
                $rooms = collect($group->bangsals)->flatMap->kamars;
                $kapasitas = $rooms->count();
                $tersedia = $rooms->where('status', 'KOSONG')->count();

                // Kumpulkan AplicareKetersediaanKamar entries yang relevan dengan group ini
                $bangsalKelasKeys = $rooms->map(fn($r) => "{$r->kd_bangsal}|{$r->kelas}")->unique();

                $aplicareRows = $bangsalKelasKeys
                    ->flatMap(fn($key) => $aplicareMaps->get($key, collect()))
                    ->map(function ($map) use ($apiMap, $rooms) {
                        // Hitung stats hanya untuk kamar dengan kd_bangsal+kelas yang sama
                        $subset = $rooms->where('kd_bangsal', $map->kd_bangsal)->where('kelas', $map->kelas);
                        $kap = $subset->count();
                        $ter = $subset->where('status', 'KOSONG')->count();

                        $syncStatus = null;
                        if ($this->apiLoaded) {
                            $api = $apiMap->get("{$map->kd_bangsal}|{$map->kode_kelas_aplicare}");
                            $syncStatus = !$api ? 'missing' : ((int) ($api['kapasitas'] ?? 0) === $kap && (int) ($api['tersedia'] ?? 0) === $ter && (int) ($api['tersediapria'] ?? 0) === $ter && (int) ($api['tersediawanita'] ?? 0) === $ter && (int) ($api['tersediapriawanita'] ?? 0) === $ter ? 'match' : 'different');
                        }

                        return [
                            'kd_bangsal' => $map->kd_bangsal,
                            'nm_bangsal' => $map->bangsal?->nm_bangsal ?? $map->kd_bangsal,
                            'kelas' => $map->kelas,
                            'kode_kelas_aplicare' => $map->kode_kelas_aplicare,
                            'kapasitas' => $kap,
                            'tersedia' => $ter,
                            'sync_status' => $syncStatus,
                        ];
                    })
                    ->values();

                $bangsalCodesInAplicare = $aplicareRows->pluck('kd_bangsal')->unique();
                $unmappedBangsals = $rooms->pluck('kd_bangsal')->unique()->diff($bangsalCodesInAplicare)->values()->toArray();

                $mappedBangsalKelasKeys = $aplicareRows->map(fn($r) => "{$r['kd_bangsal']}|{$r['kelas']}")->unique();
                $unmappedBangsalKelas = $rooms->map(fn($r) => ['kd_bangsal' => $r->kd_bangsal, 'kelas' => $r->kelas, 'nm_bangsal' => $r->bangsal?->nm_bangsal ?? $r->kd_bangsal])->unique(fn($r) => "{$r['kd_bangsal']}|{$r['kelas']}")->filter(fn($r) => !$mappedBangsalKelasKeys->contains("{$r['kd_bangsal']}|{$r['kelas']}"))->values()->toArray();

                return [
                    'id_group' => $group->id_group,
                    'nama_group' => $group->nama_group,
                    'kapasitas' => $kapasitas,
                    'tersedia' => $tersedia,
                    'tersediapria' => $tersedia,
                    'tersediawanita' => $tersedia,
                    'tersediapriawanita' => $tersedia,
                    'unmapped_bangsals' => $unmappedBangsals,
                    'unmapped_bangsal_kelas' => $unmappedBangsalKelas,
                    'rooms' => $rooms
                        ->map(
                            fn($r) => [
                                'kd_kamar' => $r->kd_kamar,
                                'kd_bangsal' => $r->kd_bangsal,
                                'nm_bangsal' => $r->bangsal?->nm_bangsal ?? $r->kd_bangsal,
                                'kelas' => $r->kelas,
                                'status' => $r->status,
                            ],
                        )
                        ->values()
                        ->toArray(),
                    'aplicare_rows' => $aplicareRows->toArray(),
                ];
            });
        } catch (\Exception $e) {
            $simrsError = 'Koneksi SIMRS tidak tersedia: ' . $e->getMessage();
        }

        return [
            'simrsError' => $simrsError,
            'groupedRows' => $groupedRows,
            'apiRows' => collect($this->apiData),
            'kodeKelasOptions' => $this->getKelasOptions(),
        ];
    }
};

?>

<div>
    <x-ui.page-header title="Aplicare" subtitle="Ketersediaan Tempat Tidur — Sinkronisasi Aplicare BPJS">
        <x-slot:actions>
            <x-atoms.button wire:navigate href="{{ route('simrs.room') }}" icon="squares-2x2" variant="primary">
                Kelola Kamar
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Banner konfigurasi belum lengkap --}}
    @php $configured = app(\App\Services\Bpjs\AplicareService::class)->isConfigured(); @endphp
    @unless ($configured)
        <div
            class="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-700/40 dark:bg-amber-900/20">
            <flux:icon.exclamation-triangle class="mt-0.5 size-5 shrink-0 text-amber-500" />
            <div class="text-sm text-amber-800 dark:text-amber-300">
                <p class="font-semibold">Konfigurasi Aplicare belum lengkap.</p>
                <p class="mt-0.5">Pastikan variabel berikut sudah diisi di file <code
                        class="mx-0.5 rounded bg-amber-100 px-1 py-0.5 font-mono text-xs dark:bg-amber-800/40">.env</code>:
                    <code
                        class="mx-0.5 rounded bg-amber-100 px-1 py-0.5 font-mono text-xs dark:bg-amber-800/40">APLICARE_BPJS_URL</code>,
                    <code
                        class="mx-0.5 rounded bg-amber-100 px-1 py-0.5 font-mono text-xs dark:bg-amber-800/40">CONS_ID_APLICARE_BPJS</code>,
                    <code
                        class="mx-0.5 rounded bg-amber-100 px-1 py-0.5 font-mono text-xs dark:bg-amber-800/40">SECRET_KEY_APLICARE_BPJS</code>,
                    <code
                        class="mx-0.5 rounded bg-amber-100 px-1 py-0.5 font-mono text-xs dark:bg-amber-800/40">USER_KEY_APLICARE_BPJS</code>,
                    dan
                    <code
                        class="mx-0.5 rounded bg-amber-100 px-1 py-0.5 font-mono text-xs dark:bg-amber-800/40">KODE_PPK_RS_BPJS</code>.
                </p>
            </div>
        </div>
    @endunless

    {{-- Tab navigasi --}}
    <x-molecules.tabs class="mb-5">
        <x-atoms.tab-item :active="$tab === 'simrs'" wire:click="switchTab('simrs')">
            <div class="flex items-center gap-2">
                <flux:icon.circle-stack class="size-4" />
                <span>Ketersediaan Kamar SIMRS</span>
                @if ($groupedRows->isNotEmpty())
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400">
                        {{ $groupedRows->count() }}
                    </span>
                @endif
            </div>
        </x-atoms.tab-item>
        <x-atoms.tab-item :active="$tab === 'aplicare'" wire:click="switchTab('aplicare')">
            <div class="flex items-center gap-2">
                <flux:icon.server class="size-4" />
                <span>Data Aplicare</span>
                @if ($apiLoaded)
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400">
                        {{ $apiRows->count() }}
                    </span>
                @endif
            </div>
        </x-atoms.tab-item>
    </x-molecules.tabs>

    {{-- Tab: Ketersediaan Kamar SIMRS --}}
    @if ($tab === 'simrs')
        <x-organisms.card-box :padding="false">
            {{-- Toolbar --}}
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-5 py-4 dark:border-primary-dark-700/60">
                <div class="flex items-center gap-2">
                    <flux:icon.circle-stack class="size-5 text-zinc-400" />
                    <span class="font-semibold text-zinc-800 dark:text-primary-dark-100">Ketersediaan Kamar SIMRS</span>
                    @if ($groupedRows->isNotEmpty())
                        @php $totalKamar = $groupedRows->sum(fn($g) => count($g['rooms'])); @endphp
                        <span
                            class="rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400">
                            {{ $groupedRows->count() }} grup · {{ $totalKamar }} kamar
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <x-atoms.button wire:click="loadApiData(true)" wire:loading.attr="disabled" icon="arrow-path" size="sm"
                        variant="ghost">
                        Bandingkan dengan API
                    </x-atoms.button>
                    <x-atoms.button wire:click="syncAll" wire:loading.attr="disabled"
                        wire:confirm="Sync semua Ketersediaan Kamar SIMRS ke Aplicare? Proses ini akan membuat atau memperbarui semua entri."
                        icon="arrow-up-on-square" size="sm" variant="primary">
                        Sync Semua
                    </x-atoms.button>
                </div>
            </div>

            {{-- Konten --}}
            @if ($simrsError)
                <x-ui.empty-state icon="exclamation-triangle" title="Koneksi SIMRS Gagal" :description="$simrsError" />
            @elseif ($groupedRows->isEmpty())
                <x-ui.empty-state icon="circle-stack" title="Tidak Ada Data"
                    description="Tidak ada grup kamar yang terdaftar di SIMRS." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead class="bg-zinc-50/70 dark:bg-primary-dark-900/40">
                            <tr>
                                <th class="w-10 px-3 py-3"></th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Grup Kamar</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Kamar</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Kapasitas</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Tersedia</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    P</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    W</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    P/W</th>
                                @if ($apiLoaded)
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                        Status Sync</th>
                                @endif
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Aksi</th>
                            </tr>
                        </thead>

                        @foreach ($groupedRows as $group)
                            @php
                                $colSpan = $apiLoaded ? 10 : 9;
                                $hasMissing = collect($group['aplicare_rows'])->contains('sync_status', 'missing');
                                $hasDifferent = collect($group['aplicare_rows'])->contains('sync_status', 'different');
                            @endphp

                            <tbody x-data="{ open: false }"
                                class="divide-y divide-zinc-100 dark:divide-primary-dark-700/60">

                                {{-- Baris grup --}}
                                <tr class="cursor-pointer hover:bg-zinc-50/50 dark:hover:bg-primary-dark-700/20 transition-colors"
                                    @click="open = !open">
                                    <td class="px-3 py-3 text-center text-zinc-400">
                                        <flux:icon.chevron-right class="size-4 transition-transform"
                                            ::class="open ? 'rotate-90' : ''" />
                                    </td>
                                    <td class="px-4 py-3 font-medium text-zinc-800 dark:text-primary-dark-100">
                                        {{ $group['nama_group'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-zinc-500 dark:text-primary-dark-400">
                                        {{ count($group['rooms']) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-zinc-700 dark:text-primary-dark-300">
                                        {{ $group['kapasitas'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-zinc-700 dark:text-primary-dark-300">
                                        {{ $group['tersedia'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-zinc-700 dark:text-primary-dark-300">
                                        {{ $group['tersediapria'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-zinc-700 dark:text-primary-dark-300">
                                        {{ $group['tersediawanita'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-zinc-700 dark:text-primary-dark-300">
                                        {{ $group['tersediapriawanita'] }}</td>
                                    @if ($apiLoaded)
                                        <td class="px-4 py-3">
                                            @if (empty($group['aplicare_rows']))
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400">
                                                    <flux:icon.minus-circle class="size-3" />
                                                    Tidak Ada Mapping
                                                </span>
                                            @elseif ($hasMissing)
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400">
                                                    <flux:icon.minus-circle class="size-3" />
                                                    Belum Sync
                                                </span>
                                            @elseif ($hasDifferent)
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                    <flux:icon.exclamation-triangle class="size-3" />
                                                    Perlu Update
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                    <flux:icon.check-circle class="size-3" />
                                                    Sesuai
                                                </span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 text-right" @click.stop>
                                        <div class="flex items-center justify-end gap-2">
                                            @if (!empty($group['unmapped_bangsals']))
                                                <span
                                                    title="Bangsal belum termapping: {{ implode(', ', $group['unmapped_bangsals']) }}"
                                                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 cursor-help">
                                                    <flux:icon.exclamation-triangle class="size-3" />
                                                    {{ count($group['unmapped_bangsals']) }} belum mapped
                                                </span>
                                            @endif
                                            @if (!empty($group['aplicare_rows']))
                                                <x-atoms.button wire:click="syncGroup('{{ $group['id_group'] }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="syncGroup('{{ $group['id_group'] }}')"
                                                    icon="arrow-path" size="xs" variant="ghost">
                                                    Sync Semua
                                                </x-atoms.button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                {{-- Baris detail (expandable) --}}
                                <tr x-show="open" x-cloak>
                                    <td colspan="{{ $colSpan }}"
                                        class="bg-zinc-50/60 px-6 py-4 dark:bg-primary-dark-900/30">
                                        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">

                                            {{-- Daftar kamar --}}
                                            <div>
                                                <p
                                                    class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                                    Daftar Kamar ({{ count($group['rooms']) }})
                                                </p>
                                                <div
                                                    class="overflow-hidden rounded-lg border border-zinc-200 dark:border-primary-dark-700">
                                                    <table class="w-full text-xs">
                                                        <thead class="bg-zinc-100 dark:bg-primary-dark-800">
                                                            <tr>
                                                                <th
                                                                    class="px-3 py-2 text-left font-semibold text-zinc-500">
                                                                    Kode Kamar</th>
                                                                <th
                                                                    class="px-3 py-2 text-left font-semibold text-zinc-500">
                                                                    Bangsal</th>
                                                                <th
                                                                    class="px-3 py-2 text-left font-semibold text-zinc-500">
                                                                    Kelas</th>
                                                                <th
                                                                    class="px-3 py-2 text-left font-semibold text-zinc-500">
                                                                    Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody
                                                            class="divide-y divide-zinc-100 dark:divide-primary-dark-700/60">
                                                            @foreach ($group['rooms'] as $room)
                                                                <tr>
                                                                    <td
                                                                        class="px-3 py-1.5 font-mono text-zinc-600 dark:text-primary-dark-300">
                                                                        {{ $room['kd_kamar'] }}</td>
                                                                    <td
                                                                        class="px-3 py-1.5 text-zinc-700 dark:text-primary-dark-300">
                                                                        {{ $room['nm_bangsal'] }}</td>
                                                                    <td
                                                                        class="px-3 py-1.5 text-zinc-600 dark:text-primary-dark-400">
                                                                        {{ $room['kelas'] }}</td>
                                                                    <td class="px-3 py-1.5">
                                                                        <span @class([
                                                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' =>
                                                                                $room['status'] === 'KOSONG',
                                                                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' =>
                                                                                $room['status'] !== 'KOSONG',
                                                                        ])>
                                                                            {{ $room['status'] }}
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            {{-- Mapping Aplicare --}}
                                            <div>
                                                <p
                                                    class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                                    Mapping Aplicare
                                                </p>

                                                @if (!empty($group['aplicare_rows']))
                                                    <div
                                                        class="overflow-hidden rounded-lg border border-zinc-200 dark:border-primary-dark-700">
                                                        <table class="w-full text-xs">
                                                            <thead class="bg-zinc-100 dark:bg-primary-dark-800">
                                                                <tr>
                                                                    <th
                                                                        class="px-3 py-2 text-left font-semibold text-zinc-500">
                                                                        Bangsal</th>
                                                                    <th
                                                                        class="px-3 py-2 text-left font-semibold text-zinc-500">
                                                                        Kelas</th>
                                                                    <th
                                                                        class="px-3 py-2 text-left font-semibold text-zinc-500">
                                                                        Kode Kelas</th>
                                                                    <th
                                                                        class="px-3 py-2 text-right font-semibold text-zinc-500">
                                                                        Kap.</th>
                                                                    <th
                                                                        class="px-3 py-2 text-right font-semibold text-zinc-500">
                                                                        Tersedia</th>
                                                                    @if ($apiLoaded)
                                                                        <th
                                                                            class="px-3 py-2 text-left font-semibold text-zinc-500">
                                                                            Status</th>
                                                                    @endif
                                                                    <th class="px-3 py-2"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody
                                                                class="divide-y divide-zinc-100 dark:divide-primary-dark-700/60">
                                                                @foreach ($group['aplicare_rows'] as $aRow)
                                                                    <tr>
                                                                        <td
                                                                            class="px-3 py-1.5 text-zinc-700 dark:text-primary-dark-300">
                                                                            {{ $aRow['nm_bangsal'] }}</td>
                                                                        <td
                                                                            class="px-3 py-1.5 text-zinc-600 dark:text-primary-dark-400">
                                                                            {{ $aRow['kelas'] }}</td>
                                                                        <td
                                                                            class="px-3 py-1.5 font-mono text-zinc-600 dark:text-primary-dark-400">
                                                                            {{ $aRow['kode_kelas_aplicare'] }}</td>
                                                                        <td
                                                                            class="px-3 py-1.5 text-right font-mono text-zinc-600 dark:text-primary-dark-400">
                                                                            {{ $aRow['kapasitas'] }}</td>
                                                                        <td
                                                                            class="px-3 py-1.5 text-right font-mono text-zinc-600 dark:text-primary-dark-400">
                                                                            {{ $aRow['tersedia'] }}</td>
                                                                        @if ($apiLoaded)
                                                                            <td class="px-3 py-1.5">
                                                                                @if ($aRow['sync_status'] === 'match')
                                                                                    <span
                                                                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                                                        <flux:icon.check-circle
                                                                                            class="size-3" />
                                                                                        Sesuai
                                                                                    </span>
                                                                                @elseif($aRow['sync_status'] === 'different')
                                                                                    <span
                                                                                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                                                        <flux:icon.exclamation-triangle
                                                                                            class="size-3" />
                                                                                        Perlu Update
                                                                                    </span>
                                                                                @else
                                                                                    <span
                                                                                        class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400">
                                                                                        <flux:icon.minus-circle
                                                                                            class="size-3" />
                                                                                        Belum Sync
                                                                                    </span>
                                                                                @endif
                                                                            </td>
                                                                        @endif
                                                                        <td class="px-3 py-1.5 text-right">
                                                                            <div
                                                                                class="flex items-center justify-end gap-1">
                                                                                <x-atoms.button
                                                                                    wire:click="openEditMapping('{{ $aRow['kd_bangsal'] }}', '{{ $aRow['kelas'] }}', '{{ $aRow['kode_kelas_aplicare'] }}', '{{ addslashes($aRow['nm_bangsal']) }}')"
                                                                                    size="xs" variant="ghost"
                                                                                    icon="pencil-square"
                                                                                    title="Edit mapping" />
                                                                                <x-atoms.button
                                                                                    wire:click="syncRow('{{ $aRow['kd_bangsal'] }}', '{{ $aRow['kode_kelas_aplicare'] }}')"
                                                                                    wire:loading.attr="disabled"
                                                                                    wire:target="syncRow('{{ $aRow['kd_bangsal'] }}', '{{ $aRow['kode_kelas_aplicare'] }}')"
                                                                                    icon="arrow-path" size="xs"
                                                                                    variant="ghost">
                                                                                    <span wire:loading.remove
                                                                                        wire:target="syncRow('{{ $aRow['kd_bangsal'] }}', '{{ $aRow['kode_kelas_aplicare'] }}')">Sync</span>
                                                                                    <span wire:loading
                                                                                        wire:target="syncRow('{{ $aRow['kd_bangsal'] }}', '{{ $aRow['kode_kelas_aplicare'] }}')">...</span>
                                                                                </x-atoms.button>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @endif

                                                {{-- Bangsal / kelas belum termapping --}}
                                                @if (!empty($group['unmapped_bangsal_kelas']))
                                                    <div class="mt-3">
                                                        <p
                                                            class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-amber-500">
                                                            Belum Termapping
                                                            ({{ count($group['unmapped_bangsal_kelas']) }})
                                                        </p>
                                                        <div
                                                            class="overflow-hidden rounded-lg border border-amber-200 dark:border-amber-700/40">
                                                            <table class="w-full text-xs">
                                                                <thead class="bg-amber-50 dark:bg-amber-900/20">
                                                                    <tr>
                                                                        <th
                                                                            class="px-3 py-2 text-left font-semibold text-amber-600 dark:text-amber-400">
                                                                            Bangsal</th>
                                                                        <th
                                                                            class="px-3 py-2 text-left font-semibold text-amber-600 dark:text-amber-400">
                                                                            Kelas</th>
                                                                        <th class="px-3 py-2"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody
                                                                    class="divide-y divide-amber-100 dark:divide-amber-700/30 bg-white dark:bg-primary-dark-800/60">
                                                                    @foreach ($group['unmapped_bangsal_kelas'] as $umk)
                                                                        <tr>
                                                                            <td
                                                                                class="px-3 py-1.5 text-zinc-700 dark:text-primary-dark-300">
                                                                                {{ $umk['nm_bangsal'] }}</td>
                                                                            <td
                                                                                class="px-3 py-1.5 text-zinc-600 dark:text-primary-dark-400">
                                                                                {{ $umk['kelas'] }}</td>
                                                                            <td class="px-3 py-1.5 text-right">
                                                                                <x-atoms.button
                                                                                    wire:click="openAddMapping('{{ $umk['kd_bangsal'] }}', '{{ $umk['kelas'] }}', '{{ addslashes($umk['nm_bangsal']) }}')"
                                                                                    size="xs" variant="primary"
                                                                                    icon="plus-circle">
                                                                                    Tambah
                                                                                </x-atoms.button>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @endif

                                                @if (empty($group['aplicare_rows']) && empty($group['unmapped_bangsal_kelas']))
                                                    <p class="text-xs text-zinc-400">Tidak ada kamar aktif dalam
                                                        grup ini.</p>
                                                @endif
                                            </div>

                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        @endforeach
                    </table>
                </div>
            @endif
        </x-organisms.card-box>
    @endif

    {{-- Tab: Data Aplicare --}}
    @if ($tab === 'aplicare')
        <x-organisms.card-box :padding="false" wire:init="loadApiData">
            {{-- Toolbar --}}
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-5 py-4 dark:border-primary-dark-700/60">
                <div class="flex items-center gap-2">
                    <flux:icon.server class="size-5 text-zinc-400" />
                    <span class="font-semibold text-zinc-800 dark:text-primary-dark-100">Data di Aplicare</span>
                    @if ($apiLoaded)
                        <span
                            class="rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400">
                            {{ $apiRows->count() }} data
                        </span>
                    @endif
                </div>
                <x-atoms.button wire:click="loadApiData(true)" wire:loading.attr="disabled" icon="arrow-path" size="sm"
                    variant="primary">
                    <span wire:loading.remove wire:target="loadApiData">Refresh Data</span>
                    <span wire:loading wire:target="loadApiData">Memuat...</span>
                </x-atoms.button>
            </div>

            {{-- Konten --}}
            @if (!$apiLoaded)
                <div class="flex flex-col items-center justify-center gap-3 py-16 text-zinc-400">
                    <flux:icon.cloud-arrow-down class="size-10 animate-bounce text-primary-500" />
                    <p class="text-sm">Menghubungkan ke API Aplicare BPJS...</p>
                </div>
            @elseif($apiRows->isEmpty())
                <x-ui.empty-state icon="inbox" title="Tidak Ada Data"
                    description="Tidak ada data tempat tidur yang terdaftar di Aplicare." />
            @else
                <x-organisms.table>
                    <x-slot:headings>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Kode Ruang</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Nama Ruang</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Kode Kelas</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Nama Kelas</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Kapasitas</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Tersedia</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    P</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    W</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    P/W</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Status</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Last Update</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Aksi</th>
                            </tr>
                    </x-slot:headings>
                    @foreach ($apiRows as $row)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-primary-dark-700/20 transition-colors">
                                    <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-primary-dark-300">
                                        {{ $row['koderuang'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-zinc-800 dark:text-primary-dark-100">
                                        {{ $row['namaruang'] ?? '-' }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-primary-dark-300">
                                        {{ $row['kodekelas'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-zinc-700 dark:text-primary-dark-300">
                                        {{ $row['namakelas'] ?? '-' }}</td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-zinc-700 dark:text-primary-dark-300">
                                        {{ $row['kapasitas'] ?? 0 }}</td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-zinc-700 dark:text-primary-dark-300">
                                        {{ $row['tersedia'] ?? 0 }}</td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-zinc-700 dark:text-primary-dark-300">
                                        {{ $row['tersediapria'] ?? 0 }}</td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-zinc-700 dark:text-primary-dark-300">
                                        {{ $row['tersediawanita'] ?? 0 }}</td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-zinc-700 dark:text-primary-dark-300">
                                        {{ $row['tersediapriawanita'] ?? 0 }}</td>
                                    <td class="px-4 py-3">
                                        @php $stat = $row['stat'] ?? '-'; @endphp
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' =>
                                                $stat === 'Sudah',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' =>
                                                $stat !== 'Sudah' && $stat !== '-',
                                            'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400' =>
                                                $stat === '-',
                                        ])>{{ $stat }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-zinc-500 dark:text-primary-dark-400">
                                        {{ $row['lastupdate'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <x-atoms.button
                                                wire:click="syncRow('{{ $row['koderuang'] ?? '' }}', '{{ $row['kodekelas'] ?? '' }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="syncRow('{{ $row['koderuang'] ?? '' }}', '{{ $row['kodekelas'] ?? '' }}')"
                                                icon="arrow-path" size="xs" variant="ghost">
                                                <span wire:loading.remove
                                                    wire:target="syncRow('{{ $row['koderuang'] ?? '' }}', '{{ $row['kodekelas'] ?? '' }}')">Sync</span>
                                                <span wire:loading
                                                    wire:target="syncRow('{{ $row['koderuang'] ?? '' }}', '{{ $row['kodekelas'] ?? '' }}')">...</span>
                                            </x-atoms.button>
                                            <x-atoms.button
                                                wire:click="confirmDelete('{{ $row['koderuang'] ?? '' }}', '{{ $row['kodekelas'] ?? '' }}')"
                                                icon="trash" size="xs" variant="danger">
                                                Hapus
                                            </x-atoms.button>
                                        </div>
                                    </td>
                                </tr>
                    @endforeach
                </x-organisms.table>
            @endif
        </x-organisms.card-box>
    @endif

    {{-- Modal Hapus --}}
    <x-organisms.modal wire:model="showDeleteModal" title="Hapus Ruangan?" maxWidth="md"
        description="Ruangan ini akan dihapus dari Aplicare BPJS dan mapping SIMRS.">
        <div class="space-y-4">
            <div
                class="flex items-center gap-4 p-4 rounded-xl bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800/50">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="trash" class="size-5 text-red-600 dark:text-red-400" />
                </div>
                <p class="text-xs text-red-700 dark:text-red-300">
                    Tindakan ini tidak dapat dibatalkan. Pastikan data ini memang perlu dihapus dari integrasi BPJS.
                </p>
            </div>
            @if (!empty($deleteTarget))
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div class="bg-zinc-50 dark:bg-primary-dark-800 p-2.5 rounded-lg border border-zinc-100 dark:border-primary-dark-700">
                        <dt class="text-zinc-400 mb-1">Kode Ruang</dt>
                        <dd class="font-mono font-bold text-zinc-800 dark:text-primary-dark-200">{{ $deleteTarget['koderuang'] }}</dd>
                    </div>
                    <div class="bg-zinc-50 dark:bg-primary-dark-800 p-2.5 rounded-lg border border-zinc-100 dark:border-primary-dark-700">
                        <dt class="text-zinc-400 mb-1">Kode Kelas</dt>
                        <dd class="font-mono font-bold text-zinc-800 dark:text-primary-dark-200">{{ $deleteTarget['kodekelas'] }}</dd>
                    </div>
                </div>
            @endif
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showDeleteModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="danger" icon="trash" wire:click="doDelete" wire:loading.attr="disabled"
                    wire:target="doDelete">
                    Hapus Sekarang
                </x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>

    {{-- Modal Tambah Mapping --}}
    <x-organisms.modal wire:model="showAddMappingModal" title="Tambah Mapping Aplicare" maxWidth="lg">
        <div class="space-y-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon.plus-circle class="size-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <flux:heading size="lg">Tambah Mapping Aplicare</flux:heading>
                    @if (!empty($addMappingData))
                        <flux:text class="mt-0.5">
                            {{ $addMappingForm['namaruang'] ?? '' }} · Kelas SIMRS
                            {{ $addMappingData['kelas'] ?? '' }}
                        </flux:text>
                    @endif
                </div>
            </div>

            {{-- Kode Kelas --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <flux:label>Kode Kelas Aplicare <span class="text-red-500">*</span></flux:label>
                    <x-atoms.button wire:click="refreshKelasCache" wire:loading.attr="disabled"
                        wire:target="refreshKelasCache" size="xs" variant="ghost" icon="arrow-path"
                        title="Perbarui opsi dari API" />
                </div>
                <flux:select wire:model="addMappingForm.kodekelas">
                    <flux:select.option value="">-- Pilih Kode Kelas --</flux:select.option>
                    @foreach ($kodeKelasOptions as $opt)
                        <flux:select.option value="{{ $opt }}">{{ $opt }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="addMappingForm.kodekelas" />
            </div>

            {{-- Kode Ruang & Nama Ruang --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <flux:label>Kode Ruang</flux:label>
                    <flux:input value="{{ $addMappingForm['koderuang'] ?? '' }}" readonly
                        class="bg-zinc-50 dark:bg-primary-dark-900/40" />
                </div>
                <div>
                    <flux:label>Nama Ruang</flux:label>
                    <flux:input value="{{ $addMappingForm['namaruang'] ?? '' }}" readonly
                        class="bg-zinc-50 dark:bg-primary-dark-900/40" />
                </div>
            </div>

            {{-- Kapasitas & Tersedia --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <flux:label>Kapasitas <span class="text-red-500">*</span></flux:label>
                    <flux:input type="number" min="0" wire:model="addMappingForm.kapasitas" />
                    <flux:error name="addMappingForm.kapasitas" />
                </div>
                <div>
                    <flux:label>Tersedia <span class="text-red-500">*</span></flux:label>
                    <flux:input type="number" min="0" wire:model="addMappingForm.tersedia" />
                    <flux:error name="addMappingForm.tersedia" />
                </div>
            </div>

            {{-- Tersedia P, W, P/W --}}
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <flux:label>Tersedia P <span class="text-red-500">*</span></flux:label>
                    <flux:input type="number" min="0" wire:model="addMappingForm.tersediapria" />
                    <flux:error name="addMappingForm.tersediapria" />
                </div>
                <div>
                    <flux:label>Tersedia W <span class="text-red-500">*</span></flux:label>
                    <flux:input type="number" min="0" wire:model="addMappingForm.tersediawanita" />
                    <flux:error name="addMappingForm.tersediawanita" />
                </div>
                <div>
                    <flux:label>Tersedia P/W <span class="text-red-500">*</span></flux:label>
                    <flux:input type="number" min="0" wire:model="addMappingForm.tersediapriawanita" />
                    <flux:error name="addMappingForm.tersediapriawanita" />
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3 pt-1">
                    <x-atoms.button variant="ghost" wire:click="$set('showAddMappingModal', false)">Batal</x-atoms.button>
                    <x-atoms.button variant="primary" icon="arrow-up-on-square" wire:click="saveAddMapping"
                        wire:loading.attr="disabled" wire:target="saveAddMapping">
                        Simpan & Kirim
                    </x-atoms.button>
                </div>
            </x-slot>
        </div>
    </x-organisms.modal>

    {{-- Modal Edit Mapping --}}
    <x-organisms.modal wire:model="showEditMappingModal" title="Edit Mapping Aplicare" maxWidth="lg">
        <x-slot name="description">
            @if (!empty($editMappingData))
                {{ $editMappingForm['namaruang'] ?? '' }} · Kelas SIMRS {{ $editMappingData['kelas'] ?? '' }}
            @endif
        </x-slot>

        <div class="space-y-4">
            {{-- Kode Kelas --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <flux:label>Kode Kelas Aplicare <span class="text-red-500">*</span></flux:label>
                    <x-atoms.button wire:click="refreshKelasCache" wire:loading.attr="disabled"
                        wire:target="refreshKelasCache" size="xs" variant="ghost" icon="arrow-path"
                        title="Perbarui opsi dari API" />
                </div>
                <flux:select wire:model="editMappingForm.kodekelas">
                    <flux:select.option value="">-- Pilih Kode Kelas --</flux:select.option>
                    @foreach ($kodeKelasOptions as $opt)
                        <flux:select.option value="{{ $opt }}">{{ $opt }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="editMappingForm.kodekelas" />
            </div>

            {{-- Kode Ruang & Nama Ruang --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <flux:label>Kode Ruang</flux:label>
                    <flux:input value="{{ $editMappingForm['koderuang'] ?? '' }}" readonly
                        class="bg-zinc-50 dark:bg-primary-dark-900/40" />
                </div>
                <div>
                    <flux:label>Nama Ruang</flux:label>
                    <flux:input value="{{ $editMappingForm['namaruang'] ?? '' }}" readonly
                        class="bg-zinc-50 dark:bg-primary-dark-900/40" />
                </div>
            </div>

            {{-- Kapasitas & Tersedia --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <flux:label>Kapasitas <span class="text-red-500">*</span></flux:label>
                    <flux:input type="number" min="0" wire:model="editMappingForm.kapasitas" />
                    <flux:error name="editMappingForm.kapasitas" />
                </div>
                <div>
                    <flux:label>Tersedia <span class="text-red-500">*</span></flux:label>
                    <flux:input type="number" min="0" wire:model="editMappingForm.tersedia" />
                    <flux:error name="editMappingForm.tersedia" />
                </div>
            </div>

            {{-- Tersedia P, W, P/W --}}
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <flux:label>Tersedia P <span class="text-red-500">*</span></flux:label>
                    <flux:input type="number" min="0" wire:model="editMappingForm.tersediapria" />
                    <flux:error name="editMappingForm.tersediapria" />
                </div>
                <div>
                    <flux:label>Tersedia W <span class="text-red-500">*</span></flux:label>
                    <flux:input type="number" min="0" wire:model="editMappingForm.tersediawanita" />
                    <flux:error name="editMappingForm.tersediawanita" />
                </div>
                <div>
                    <flux:label>Tersedia P/W <span class="text-red-500">*</span></flux:label>
                    <flux:input type="number" min="0" wire:model="editMappingForm.tersediapriawanita" />
                    <flux:error name="editMappingForm.tersediapriawanita" />
                </div>
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3 pt-1">
                <x-atoms.button variant="ghost" wire:click="$set('showEditMappingModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="primary" icon="arrow-up-on-square" wire:click="saveEditMapping"
                    wire:loading.attr="disabled" wire:target="saveEditMapping">
                    Simpan & Kirim
                </x-atoms.button>
            </div>
        </x-slot>
    </x-organisms.modal>
</div>
