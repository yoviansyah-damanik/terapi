<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ConfigurationHelper;
use App\Http\Controllers\Controller;
use App\Models\WaGateway\Gowa\GowaMessage;
use App\Models\WaGateway\Waha\WahaMessage;
use App\Services\GowaService;
use App\Services\WahaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Unified WhatsApp API Controller
 *
 * Dispatch otomatis ke gateway aktif (WAHA / GOWA)
 * berdasarkan konfigurasi `whatsapp.active_gateway`.
 */
class WhatsappApiController extends Controller
{
    protected string $gateway;

    public function __construct()
    {
        $this->gateway = ConfigurationHelper::get('whatsapp.active_gateway', 'waha');
    }

    // ========== SHARED ENDPOINTS ==========

    /**
     * Kirim pesan teks
     */
    public function sendText(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'message' => 'required|string|max:4096',
        ], [
            'phone.required' => 'Nomor tujuan wajib diisi',
            'phone.min' => 'Nomor tujuan minimal 10 karakter',
            'message.required' => 'Pesan wajib diisi',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        if ($this->isWaha()) {
            $message = WahaMessage::create([
                'phone' => $request->phone,
                'message' => $request->message,
                'type' => 'text',
                'status' => 'pending',
            ]);

            $result = app(WahaService::class)->sendText($request->phone, $request->message);

            return $this->handleWahaResult($message, $result, 'Pesan berhasil dikirim');
        }

        $message = GowaMessage::create([
            'phone' => $request->phone,
            'message' => $request->message,
            'type' => 'text',
            'status' => 'pending',
        ]);

        $result = app(GowaService::class)->sendMessage($request->phone, $request->message);

        return $this->handleGowaResult($message, $result, 'Pesan berhasil dikirim');
    }

    /**
     * Kirim gambar (base64)
     */
    public function sendImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'image' => 'required|string',
            'filename' => 'required|string|max:255',
            'caption' => 'nullable|string|max:4096',
        ], [
            'phone.required' => 'Nomor tujuan wajib diisi',
            'image.required' => 'Data gambar (base64) wajib diisi',
            'filename.required' => 'Nama file wajib diisi',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        if ($this->isWaha()) {
            $imageData = base64_decode($request->image, true);
            if ($imageData === false) {
                return response()->json(['success' => false, 'message' => 'Format base64 gambar tidak valid'], 422);
            }

            $path = 'whatsapp/api/' . uniqid() . '_' . $request->filename;
            $fullPath = storage_path('app/public/' . $path);
            if (!is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }
            file_put_contents($fullPath, $imageData);

            $message = WahaMessage::create([
                'phone' => $request->phone,
                'message' => $request->caption,
                'type' => 'image',
                'file_path' => $path,
                'file_name' => $request->filename,
                'status' => 'pending',
            ]);

            $result = app(WahaService::class)->sendImage($request->phone, $fullPath, $request->caption);

            return $this->handleWahaResult($message, $result, 'Gambar berhasil dikirim');
        }

        $tempPath = $this->decodeBase64ToFile($request->image, $request->filename, 'image');
        if (!$tempPath) {
            return response()->json(['success' => false, 'message' => 'Format base64 gambar tidak valid'], 422);
        }

        $message = GowaMessage::create([
            'phone' => $request->phone,
            'message' => $request->caption,
            'type' => 'image',
            'file_name' => $request->filename,
            'status' => 'pending',
        ]);

        $result = app(GowaService::class)->sendImage($request->phone, $tempPath, $request->caption);
        @unlink($tempPath);

        return $this->handleGowaResult($message, $result, 'Gambar berhasil dikirim');
    }

    /**
     * Kirim file/dokumen (base64)
     */
    public function sendFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'file' => 'required|string',
            'filename' => 'required|string|max:255',
        ], [
            'phone.required' => 'Nomor tujuan wajib diisi',
            'file.required' => 'Data file (base64) wajib diisi',
            'filename.required' => 'Nama file wajib diisi',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        if ($this->isWaha()) {
            $fileData = base64_decode($request->file, true);
            if ($fileData === false) {
                return response()->json(['success' => false, 'message' => 'Format base64 file tidak valid'], 422);
            }

            $path = 'whatsapp/api/' . uniqid() . '_' . $request->filename;
            $fullPath = storage_path('app/public/' . $path);
            if (!is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }
            file_put_contents($fullPath, $fileData);

            $message = WahaMessage::create([
                'phone' => $request->phone,
                'type' => 'file',
                'file_path' => $path,
                'file_name' => $request->filename,
                'status' => 'pending',
            ]);

            $result = app(WahaService::class)->sendFile($request->phone, $fullPath, $request->filename);

            return $this->handleWahaResult($message, $result, 'File berhasil dikirim');
        }

        $tempPath = $this->decodeBase64ToFile($request->file, $request->filename, 'file');
        if (!$tempPath) {
            return response()->json(['success' => false, 'message' => 'Format base64 file tidak valid'], 422);
        }

        $message = GowaMessage::create([
            'phone' => $request->phone,
            'type' => 'file',
            'file_name' => $request->filename,
            'status' => 'pending',
        ]);

        $result = app(GowaService::class)->sendFile($request->phone, $tempPath);
        @unlink($tempPath);

        return $this->handleGowaResult($message, $result, 'File berhasil dikirim');
    }

    /**
     * Cek status koneksi
     */
    public function getStatus(): JsonResponse
    {
        if ($this->isWaha()) {
            $result = app(WahaService::class)->getSessionStatus();

            if ($result['success']) {
                $data = $result['data'] ?? [];
                $status = $data['status'] ?? 'UNKNOWN';

                return response()->json([
                    'success' => true,
                    'data' => [
                        'gateway' => 'waha',
                        'session' => $data['name'] ?? null,
                        'status' => $status,
                        'connected' => in_array($status, ['WORKING', 'AUTHENTICATED']),
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? $result['error'] ?? 'Gagal memeriksa status',
            ], $result['status_code'] ?? 502);
        }

        $result = app(GowaService::class)->getDevices();

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'data' => [
                    'gateway' => 'gowa',
                    'connected' => true,
                    'devices' => $result['data'] ?? [],
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? $result['error'] ?? 'Gagal memeriksa status',
        ], $result['status_code'] ?? 502);
    }

    /**
     * Cek status pesan berdasarkan ID
     */
    public function getMessageStatus(string $id): JsonResponse
    {
        if ($this->isWaha()) {
            $message = WahaMessage::find($id);
        } else {
            $message = GowaMessage::find($id);
        }

        if (!$message) {
            return response()->json(['success' => false, 'message' => 'Pesan tidak ditemukan'], 404);
        }

        $data = [
            'id' => $message->id,
            'phone' => $message->phone,
            'type' => $message->type,
            'status' => $message->status,
            'error_message' => $message->error_message,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'created_at' => $message->created_at->toIso8601String(),
        ];

        if (!$this->isWaha() && isset($message->gowa_message_id)) {
            $data['gowa_message_id'] = $message->gowa_message_id;
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ========== GOWA-ONLY ENDPOINTS ==========

    /**
     * Kirim video (GOWA only)
     */
    public function sendVideo(Request $request): JsonResponse
    {
        if ($this->isWaha()) {
            return $this->gowaOnlyError('video');
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'video' => 'required|string',
            'filename' => 'required|string|max:255',
            'caption' => 'nullable|string|max:4096',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $tempPath = $this->decodeBase64ToFile($request->video, $request->filename, 'video');
        if (!$tempPath) {
            return response()->json(['success' => false, 'message' => 'Format base64 video tidak valid'], 422);
        }

        $message = GowaMessage::create([
            'phone' => $request->phone,
            'message' => $request->caption,
            'type' => 'video',
            'file_name' => $request->filename,
            'status' => 'pending',
        ]);

        $result = app(GowaService::class)->sendVideo($request->phone, $tempPath, $request->caption);
        @unlink($tempPath);

        return $this->handleGowaResult($message, $result, 'Video berhasil dikirim');
    }

    /**
     * Kirim audio (GOWA only)
     */
    public function sendAudio(Request $request): JsonResponse
    {
        if ($this->isWaha()) {
            return $this->gowaOnlyError('audio');
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'audio' => 'required|string',
            'filename' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $tempPath = $this->decodeBase64ToFile($request->audio, $request->filename, 'audio');
        if (!$tempPath) {
            return response()->json(['success' => false, 'message' => 'Format base64 audio tidak valid'], 422);
        }

        $message = GowaMessage::create([
            'phone' => $request->phone,
            'type' => 'audio',
            'file_name' => $request->filename,
            'status' => 'pending',
        ]);

        $result = app(GowaService::class)->sendAudio($request->phone, $tempPath);
        @unlink($tempPath);

        return $this->handleGowaResult($message, $result, 'Audio berhasil dikirim');
    }

    /**
     * Kirim lokasi (GOWA only)
     */
    public function sendLocation(Request $request): JsonResponse
    {
        if ($this->isWaha()) {
            return $this->gowaOnlyError('location');
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $message = GowaMessage::create([
            'phone' => $request->phone,
            'type' => 'location',
            'metadata' => ['latitude' => $request->latitude, 'longitude' => $request->longitude],
            'status' => 'pending',
        ]);

        $result = app(GowaService::class)->sendLocation($request->phone, $request->latitude, $request->longitude);

        return $this->handleGowaResult($message, $result, 'Lokasi berhasil dikirim');
    }

    /**
     * Kirim kontak (GOWA only)
     */
    public function sendContact(Request $request): JsonResponse
    {
        if ($this->isWaha()) {
            return $this->gowaOnlyError('contact');
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'contact_name' => 'required|string|max:255',
            'contact_phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $message = GowaMessage::create([
            'phone' => $request->phone,
            'type' => 'contact',
            'metadata' => ['contact_name' => $request->contact_name, 'contact_phone' => $request->contact_phone],
            'status' => 'pending',
        ]);

        $result = app(GowaService::class)->sendContact($request->phone, $request->contact_name, $request->contact_phone);

        return $this->handleGowaResult($message, $result, 'Kontak berhasil dikirim');
    }

    /**
     * Kirim link (GOWA only)
     */
    public function sendLink(Request $request): JsonResponse
    {
        if ($this->isWaha()) {
            return $this->gowaOnlyError('link');
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'link' => 'required|url|max:2048',
            'caption' => 'nullable|string|max:4096',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $message = GowaMessage::create([
            'phone' => $request->phone,
            'message' => $request->caption,
            'type' => 'link',
            'metadata' => ['link' => $request->link],
            'status' => 'pending',
        ]);

        $result = app(GowaService::class)->sendLink($request->phone, $request->link, $request->caption);

        return $this->handleGowaResult($message, $result, 'Link berhasil dikirim');
    }

    /**
     * Kirim poll (GOWA only)
     */
    public function sendPoll(Request $request): JsonResponse
    {
        if ($this->isWaha()) {
            return $this->gowaOnlyError('poll');
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'question' => 'required|string|max:255',
            'options' => 'required|array|min:2|max:12',
            'options.*' => 'required|string|max:100',
            'max_answer' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $maxAnswer = $request->input('max_answer', 1);

        $message = GowaMessage::create([
            'phone' => $request->phone,
            'type' => 'poll',
            'metadata' => [
                'question' => $request->question,
                'options' => $request->options,
                'max_answer' => $maxAnswer,
            ],
            'status' => 'pending',
        ]);

        $result = app(GowaService::class)->sendPoll($request->phone, $request->question, $request->options, $maxAnswer);

        return $this->handleGowaResult($message, $result, 'Poll berhasil dikirim');
    }

    /**
     * Cek nomor WhatsApp terdaftar (GOWA only)
     */
    public function checkUser(Request $request): JsonResponse
    {
        if ($this->isWaha()) {
            return $this->gowaOnlyError('check user');
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $result = app(GowaService::class)->checkUser($request->phone);

        if ($result['success']) {
            return response()->json(['success' => true, 'data' => $result['data']]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Gagal memeriksa nomor',
        ], $result['status_code'] ?? 502);
    }

    /**
     * Tarik pesan / revoke (GOWA only)
     */
    public function revokeMessage(string $id): JsonResponse
    {
        if ($this->isWaha()) {
            return $this->gowaOnlyError('revoke message');
        }

        $message = GowaMessage::find($id);

        if (!$message || !$message->gowa_message_id) {
            return response()->json(['success' => false, 'message' => 'Pesan tidak ditemukan atau belum terkirim'], 404);
        }

        $result = app(GowaService::class)->revokeMessage($message->phone, $message->gowa_message_id);

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => 'Pesan berhasil ditarik']);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Gagal menarik pesan',
        ], $result['status_code'] ?? 502);
    }

    /**
     * Kirim reaksi ke pesan (GOWA only)
     */
    public function reactMessage(Request $request, string $id): JsonResponse
    {
        if ($this->isWaha()) {
            return $this->gowaOnlyError('react message');
        }

        $validator = Validator::make($request->all(), [
            'emoji' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $message = GowaMessage::find($id);

        if (!$message || !$message->gowa_message_id) {
            return response()->json(['success' => false, 'message' => 'Pesan tidak ditemukan atau belum terkirim'], 404);
        }

        $result = app(GowaService::class)->reactMessage($message->phone, $message->gowa_message_id, $request->emoji);

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => 'Reaksi berhasil dikirim']);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Gagal mengirim reaksi',
        ], $result['status_code'] ?? 502);
    }

    // ========== HELPERS ==========

    private function isWaha(): bool
    {
        return $this->gateway === 'waha';
    }

    private function gowaOnlyError(string $feature): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "Fitur \"{$feature}\" hanya tersedia pada gateway GOWA. Gateway aktif saat ini: WAHA.",
        ], 400);
    }

    private function validationError($validator): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors(),
        ], 422);
    }

    private function handleWahaResult(WahaMessage $message, array $result, string $successMsg): JsonResponse
    {
        if ($result['success']) {
            $message->update(['status' => 'sent', 'sent_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'data' => [
                    'id' => $message->id,
                    'phone' => $message->phone,
                    'type' => $message->type,
                    'status' => 'sent',
                    'sent_at' => $message->fresh()->sent_at->toIso8601String(),
                ],
            ]);
        }

        $errorMsg = $result['message'] ?? $result['error'] ?? 'Gagal mengirim pesan';
        $message->update(['status' => 'failed', 'error_message' => $errorMsg]);

        return response()->json([
            'success' => false,
            'message' => $errorMsg,
            'data' => [
                'id' => $message->id,
                'phone' => $message->phone,
                'type' => $message->type,
                'status' => 'failed',
            ],
        ], $result['status_code'] ?? 502);
    }

    private function handleGowaResult(GowaMessage $message, array $result, string $successMsg): JsonResponse
    {
        if ($result['success']) {
            $message->update([
                'status' => 'sent',
                'gowa_message_id' => $result['data']['message_id'] ?? null,
                'sent_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'data' => [
                    'id' => $message->id,
                    'phone' => $message->phone,
                    'type' => $message->type,
                    'status' => 'sent',
                    'sent_at' => $message->fresh()->sent_at->toIso8601String(),
                ],
            ]);
        }

        $errorMsg = $result['message'] ?? $result['error'] ?? 'Gagal mengirim pesan';
        $message->update(['status' => 'failed', 'error_message' => $errorMsg]);

        return response()->json([
            'success' => false,
            'message' => $errorMsg,
            'data' => [
                'id' => $message->id,
                'phone' => $message->phone,
                'type' => $message->type,
                'status' => 'failed',
            ],
        ], $result['status_code'] ?? 502);
    }

    private function decodeBase64ToFile(string $base64, string $filename, string $prefix): ?string
    {
        $data = base64_decode($base64, true);
        if ($data === false) {
            return null;
        }

        $dir = storage_path('app/temp/whatsapp');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/' . $prefix . '_' . uniqid() . '_' . $filename;
        file_put_contents($path, $data);

        return $path;
    }
}
