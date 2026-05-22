<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\ImageBase64Rule;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrCodeController extends Controller
{
    public function __construct(
        protected QrCodeService $qrCodeService,
    ) {
    }

    /**
     * Generate QR code dan kembalikan sebagai base64 PNG
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'image' => 'nullable|array',
            'image.base64' => ['nullable', 'string', new ImageBase64Rule()],
            'image.size' => 'nullable|integer|min:1|max:500',
            'qrCode' => 'nullable|array',
            'qrCode.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'qrCode.backgroundColor' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'qrCode.margin' => 'nullable|integer|min:0|max:100',
            'qrCode.size' => 'nullable|integer|min:50|max:2000',
            'qrCode.level' => 'nullable|string|in:low,medium,quartile,high',
        ]);

        try {
            $base64 = $this->qrCodeService->generate(
                content: $validated['content'],
                imageOptions: $validated['image'] ?? null,
                qrCodeOptions: $validated['qrCode'] ?? null,
            );

            return response()->json([
                'success' => true,
                'image' => $base64,
                'mime' => 'image/png',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate QR code: ' . $e->getMessage(),
            ], 500);
        }
    }
}
