<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Simrs\Pegawai;
use App\Models\TteDocument;
use App\Rules\PdfBase64Rule;
use App\Services\TteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TteController extends Controller
{
    public function __construct(
        protected TteService $tteService,
    ) {
    }

    /**
     * Statistik jumlah hit TTE: keseluruhan, bulan ini, hari ini
     */
    /**
     * Statistik jumlah hit TTE berdasarkan mode dan NIK (opsional)
     */
    public function hitStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nik'  => 'nullable|string',
            'mode' => 'required|string|in:full,today,this_week,this_month,this_year',
        ]);

        $nik  = $validated['nik'] ?? null;
        $mode = $validated['mode'];

        $base = TteDocument::query()->when($nik, fn($q) => $q->where('nik', $nik));

        if ($mode === 'full') {
            $rows = $base->selectRaw("
                COUNT(*) as total,
                SUM(action = 'sign_pdf') as sign_total,
                SUM(action = 'seal_pdf') as seal_total,
                SUM(YEAR(created_at) = YEAR(NOW())) as this_year,
                SUM(action = 'sign_pdf' AND YEAR(created_at) = YEAR(NOW())) as sign_this_year,
                SUM(action = 'seal_pdf' AND YEAR(created_at) = YEAR(NOW())) as seal_this_year,
                SUM(DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')) as this_month,
                SUM(action = 'sign_pdf' AND DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')) as sign_this_month,
                SUM(action = 'seal_pdf' AND DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')) as seal_this_month,
                SUM(YEARWEEK(created_at,1) = YEARWEEK(NOW(),1)) as this_week,
                SUM(action = 'sign_pdf' AND YEARWEEK(created_at,1) = YEARWEEK(NOW(),1)) as sign_this_week,
                SUM(action = 'seal_pdf' AND YEARWEEK(created_at,1) = YEARWEEK(NOW(),1)) as seal_this_week,
                SUM(DATE(created_at) = CURDATE()) as today,
                SUM(action = 'sign_pdf' AND DATE(created_at) = CURDATE()) as sign_today,
                SUM(action = 'seal_pdf' AND DATE(created_at) = CURDATE()) as seal_today
            ")->first();

            return response()->json([
                'mode' => $mode,
                'nik'  => $nik,
                'total'      => ['total' => (int) $rows->total,      'sign_pdf' => (int) $rows->sign_total,      'seal_pdf' => (int) $rows->seal_total],
                'this_year'  => ['total' => (int) $rows->this_year,  'sign_pdf' => (int) $rows->sign_this_year,  'seal_pdf' => (int) $rows->seal_this_year],
                'this_month' => ['total' => (int) $rows->this_month, 'sign_pdf' => (int) $rows->sign_this_month, 'seal_pdf' => (int) $rows->seal_this_month],
                'this_week'  => ['total' => (int) $rows->this_week,  'sign_pdf' => (int) $rows->sign_this_week,  'seal_pdf' => (int) $rows->seal_this_week],
                'today'      => ['total' => (int) $rows->today,      'sign_pdf' => (int) $rows->sign_today,      'seal_pdf' => (int) $rows->seal_today],
            ]);
        }

        $query = match ($mode) {
            'today'      => $base->whereDate('created_at', today()),
            'this_week'  => $base->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'this_month' => $base->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            'this_year'  => $base->whereYear('created_at', now()->year),
        };

        $rows = $query->selectRaw("
            COUNT(*) as total,
            SUM(action = 'sign_pdf') as sign_count,
            SUM(action = 'seal_pdf') as seal_count
        ")->first();

        return response()->json([
            'mode'     => $mode,
            'nik'      => $nik,
            'total'    => (int) $rows->total,
            'sign_pdf' => (int) $rows->sign_count,
            'seal_pdf' => (int) $rows->seal_count,
        ]);
    }

    /**
     * Cek status koneksi ke server TTE
     */
    public function connectionStatus(): JsonResponse
    {
        $result = $this->tteService->checkConnection();

        return response()->json($result, $result['connected'] ? 200 : 503);
    }

    /**
     * Tanda tangan PDF (visible/invisible, koordinat/tag, bulk signing)
     */
    public function signPdf(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => 'nullable|string|in:tag,coordinate,invisible',
            'nik' => 'required|string',
            'passphrase' => 'required|string',
            'signatureProperties' => 'required|array|min:1',
            'signatureProperties.*.tampilan' => 'required|string|in:VISIBLE,INVISIBLE',
            'signatureProperties.*.tag' => 'nullable|string',
            'signatureProperties.*.imageBase64' => 'nullable|string',
            'signatureProperties.*.page' => 'nullable|integer|min:1',
            'signatureProperties.*.originX' => 'nullable|numeric',
            'signatureProperties.*.originY' => 'nullable|numeric',
            'signatureProperties.*.width' => 'nullable|numeric',
            'signatureProperties.*.height' => 'nullable|numeric',
            'signatureProperties.*.location' => 'nullable|string',
            'signatureProperties.*.reason' => 'nullable|string',
            'signatureProperties.*.pdfPassword' => 'nullable|string',
            'file' => 'required|array|min:1',
            'file.*' => ['required', 'string', new PdfBase64Rule()],
        ]);

        // Capture mode sebelum di-strip oleh processSignatureProperties
        $mode = $data['mode'] ?? null;
        $startTime = microtime(true);

        [$data, $errors] = $this->processSignatureProperties($data['signatureProperties'], $data, $mode);
        if ($errors) {
            return response()->json(['message' => 'Data yang diberikan tidak valid.', 'errors' => $errors], 422);
        }

        if ($nikError = $this->validateNikExists($data['nik'])) {
            return $nikError;
        }

        $result = $this->tteService->signPdf($data);

        if ($result['success']) {
            TteDocument::createFromApiResult('sign_pdf', $result, $data, $request, $mode, $startTime);
        }

        return response()->json($result, $result['success'] ? 200 : ($result['status_code'] ?? 502));
    }

    /**
     * Cek status user di sistem TTE (NIK atau email)
     */
    public function checkUserStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nik' => 'nullable|string|required_without:email',
            'email' => 'nullable|email|required_without:nik',
        ]);

        $result = $this->tteService->checkUserStatus(
            nik: $validated['nik'] ?? null,
            email: $validated['email'] ?? null,
        );

        return response()->json($result, $result['success'] ? 200 : ($result['status_code'] ?? 502));
    }

    /**
     * Registrasi user baru ke sistem TTE
     */
    public function registerUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => 'required|string',
            'email' => 'required|email',
        ]);

        $result = $this->tteService->registerUser(
            nama: $validated['nama'],
            email: $validated['email'],
        );

        return response()->json($result, $result['success'] ? 200 : ($result['status_code'] ?? 502));
    }

    /**
     * Verifikasi tanda tangan pada dokumen PDF
     */
    public function verifyPdf(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|string',
            'password' => 'nullable|string',
        ]);

        $result = $this->tteService->verifyPdf(
            file: $validated['file'],
            password: $validated['password'] ?? null,
        );

        return response()->json($result, $result['success'] ? 200 : ($result['status_code'] ?? 502));
    }

    /**
     * Request TOTP untuk proses tanda tangan (NIK atau email)
     */
    public function requestSignTotp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nik' => 'nullable|string|required_without:email',
            'email' => 'nullable|email|required_without:nik',
            'data' => 'nullable|integer|min:1',
        ]);

        $result = $this->tteService->requestSignTotp(
            nik: $validated['nik'] ?? null,
            email: $validated['email'] ?? null,
            data: $validated['data'] ?? 1,
        );

        return response()->json($result, $result['success'] ? 200 : ($result['status_code'] ?? 502));
    }

    /**
     * Aktivasi TOTP untuk Segel Elektronik
     */
    public function sealGetActivation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idSubscriber' => 'required|string',
        ]);

        $result = $this->tteService->sealGetActivation($validated['idSubscriber']);

        return response()->json($result, $result['success'] ? 200 : ($result['status_code'] ?? 502));
    }

    /**
     * Refresh aktivasi seal
     */
    public function sealRefreshActivation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idSubscriber' => 'required|string',
            'totp' => 'required|string',
        ]);

        $result = $this->tteService->sealRefreshActivation(
            idSubscriber: $validated['idSubscriber'],
            totp: $validated['totp'],
        );

        return response()->json($result, $result['success'] ? 200 : ($result['status_code'] ?? 502));
    }

    /**
     * Revoke aktivasi seal
     */
    public function sealRevokeActivation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idSubscriber' => 'required|string',
            'totp' => 'required|string',
        ]);

        $result = $this->tteService->sealRevokeActivation(
            idSubscriber: $validated['idSubscriber'],
            totp: $validated['totp'],
        );

        return response()->json($result, $result['success'] ? 200 : ($result['status_code'] ?? 502));
    }

    /**
     * Request TOTP untuk proses seal
     */
    public function sealGetTotp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idSubscriber' => 'required|string',
            'data' => 'required|integer|min:1',
            'totp' => 'required|string',
        ]);

        $result = $this->tteService->sealGetTotp(
            idSubscriber: $validated['idSubscriber'],
            data: $validated['data'],
            totp: $validated['totp'],
        );

        return response()->json($result, $result['success'] ? 200 : ($result['status_code'] ?? 502));
    }

    /**
     * Segel dokumen PDF
     */
    public function sealPdf(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => 'nullable|string|in:tag,coordinate,invisible',
            'idSubscriber' => 'required|string',
            'totp' => 'required|string',
            'signatureProperties' => 'required|array|min:1',
            'signatureProperties.*.tampilan' => 'required|string|in:VISIBLE,INVISIBLE',
            'signatureProperties.*.tag' => 'nullable|string',
            'signatureProperties.*.imageBase64' => 'nullable|string',
            'signatureProperties.*.page' => 'nullable|integer|min:1',
            'signatureProperties.*.originX' => 'nullable|numeric',
            'signatureProperties.*.originY' => 'nullable|numeric',
            'signatureProperties.*.width' => 'nullable|numeric',
            'signatureProperties.*.height' => 'nullable|numeric',
            'signatureProperties.*.location' => 'nullable|string',
            'signatureProperties.*.reason' => 'nullable|string',
            'file' => 'required|array|min:1',
            'file.*' => ['required', 'string', new PdfBase64Rule()],
        ]);

        // Capture mode sebelum di-strip oleh processSignatureProperties
        $mode = $data['mode'] ?? null;
        $startTime = microtime(true);

        [$data, $errors] = $this->processSignatureProperties($data['signatureProperties'], $data, $mode);
        if ($errors) {
            return response()->json(['message' => 'Data yang diberikan tidak valid.', 'errors' => $errors], 422);
        }

        $result = $this->tteService->sealPdf(
            idSubscriber: $data['idSubscriber'],
            totp: $data['totp'],
            signatureProperties: $data['signatureProperties'],
            files: $data['file'],
        );

        if ($result['success']) {
            TteDocument::createFromApiResult('seal_pdf', $result, $data, $request, $mode, $startTime);
        }

        return response()->json($result, $result['success'] ? 200 : ($result['status_code'] ?? 502));
    }

    /**
     * Pastikan NIK terdaftar di tabel pegawai SIMRS sebelum proses TTE.
     */
    private function validateNikExists(string $nik): ?JsonResponse
    {
        try {
            $exists = Pegawai::where('no_ktp', $nik)->exists();
        } catch (\Throwable) {
            // Jika SIMRS tidak bisa diakses, lewati validasi agar proses tidak terhenti
            return null;
        }

        if (!$exists) {
            return response()->json([
                'message' => 'Data yang diberikan tidak valid.',
                'errors' => ['nik' => ['NIK tidak ditemukan di data pegawai.']],
            ], 422);
        }

        return null;
    }

    /**
     * Validasi conditional signatureProperties berdasarkan tampilan + mode (top-level),
     * terapkan default location/reason, dan strip 'mode' dari payload sebelum dikirim ke TTE.
     *
     * @return array{0: array, 1: array} [$data, $errors]
     */
    private function processSignatureProperties(array $items, array $data, ?string $mode): array
    {
        $errors = [];
        $defaultLocation = config('hospital.city', '');
        $defaultReason = 'TTE ' . config('hospital.alias', '');

        $hasVisible = collect($items)->contains('tampilan', 'VISIBLE');

        if ($mode === 'invisible') {
            if ($hasVisible) {
                $errors['mode'][] = 'Mode invisible tidak boleh memiliki signatureProperties dengan tampilan VISIBLE.';
            }
        } elseif ($hasVisible && empty($mode)) {
            $errors['mode'][] = 'Field mode wajib diisi ketika terdapat signatureProperties dengan tampilan VISIBLE (tag atau coordinate).';
        }

        foreach ($items as $i => $sp) {
            $prefix = "signatureProperties.{$i}";

            if ($sp['tampilan'] === 'VISIBLE' && !empty($mode) && $mode !== 'invisible') {
                if ($mode === 'tag') {
                    if (empty($sp['tag']))
                        $errors["{$prefix}.tag"][] = 'Field tag wajib diisi untuk mode tag.';
                    if (empty($sp['imageBase64']))
                        $errors["{$prefix}.imageBase64"][] = 'Field imageBase64 wajib diisi untuk mode tag.';
                    if (empty($sp['width']))
                        $errors["{$prefix}.width"][] = 'Field width wajib diisi untuk mode tag.';
                    if (empty($sp['height']))
                        $errors["{$prefix}.height"][] = 'Field height wajib diisi untuk mode tag.';
                } elseif ($mode === 'coordinate') {
                    if (empty($sp['imageBase64']))
                        $errors["{$prefix}.imageBase64"][] = 'Field imageBase64 wajib diisi untuk mode coordinate.';
                    if (!isset($sp['page']))
                        $errors["{$prefix}.page"][] = 'Field page wajib diisi untuk mode coordinate.';
                    if (!isset($sp['originX']))
                        $errors["{$prefix}.originX"][] = 'Field originX wajib diisi untuk mode coordinate.';
                    if (!isset($sp['originY']))
                        $errors["{$prefix}.originY"][] = 'Field originY wajib diisi untuk mode coordinate.';
                    if (empty($sp['width']))
                        $errors["{$prefix}.width"][] = 'Field width wajib diisi untuk mode coordinate.';
                    if (empty($sp['height']))
                        $errors["{$prefix}.height"][] = 'Field height wajib diisi untuk mode coordinate.';
                }
            }

            // Terapkan default location dan reason
            $data['signatureProperties'][$i]['location'] = $sp['location'] ?? $defaultLocation ?: null;
            $data['signatureProperties'][$i]['reason'] = $sp['reason'] ?? $defaultReason ?: null;
        }

        // Strip 'mode' — field internal, tidak dikirim ke TTE API
        unset($data['mode']);

        return [$data, $errors];
    }
}
