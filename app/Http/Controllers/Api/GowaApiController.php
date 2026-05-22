<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaGateway\Gowa\GowaMessage;
use App\Services\GowaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GowaApiController extends Controller
{
    public function __construct(
        protected GowaService $gowa,
    ) {}

    /**
     * Kirim pesan teks
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'message' => 'required|string|max:4096',
        ], [
            'phone.required' => 'Nomor tujuan wajib diisi',
            'message.required' => 'Pesan wajib diisi',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $gowaMessage = GowaMessage::create([
            'phone' => $request->phone,
            'message' => $request->message,
            'type' => 'text',
            'status' => 'pending',
        ]);

        $result = $this->gowa->sendMessage($request->phone, $request->message);

        return $this->handleResult($gowaMessage, $result, 'Pesan berhasil dikirim');
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
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $tempPath = $this->decodeBase64ToFile($request->image, $request->filename, 'image');
        if (!$tempPath) {
            return response()->json(['success' => false, 'message' => 'Format base64 gambar tidak valid'], 422);
        }

        $gowaMessage = GowaMessage::create([
            'phone' => $request->phone,
            'message' => $request->caption,
            'type' => 'image',
            'file_name' => $request->filename,
            'status' => 'pending',
        ]);

        $result = $this->gowa->sendImage($request->phone, $tempPath, $request->caption);
        @unlink($tempPath);

        return $this->handleResult($gowaMessage, $result, 'Gambar berhasil dikirim');
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
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $tempPath = $this->decodeBase64ToFile($request->file, $request->filename, 'file');
        if (!$tempPath) {
            return response()->json(['success' => false, 'message' => 'Format base64 file tidak valid'], 422);
        }

        $gowaMessage = GowaMessage::create([
            'phone' => $request->phone,
            'type' => 'file',
            'file_name' => $request->filename,
            'status' => 'pending',
        ]);

        $result = $this->gowa->sendFile($request->phone, $tempPath);
        @unlink($tempPath);

        return $this->handleResult($gowaMessage, $result, 'File berhasil dikirim');
    }

    /**
     * Kirim video (base64)
     */
    public function sendVideo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'video' => 'required|string',
            'filename' => 'required|string|max:255',
            'caption' => 'nullable|string|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $tempPath = $this->decodeBase64ToFile($request->video, $request->filename, 'video');
        if (!$tempPath) {
            return response()->json(['success' => false, 'message' => 'Format base64 video tidak valid'], 422);
        }

        $gowaMessage = GowaMessage::create([
            'phone' => $request->phone,
            'message' => $request->caption,
            'type' => 'video',
            'file_name' => $request->filename,
            'status' => 'pending',
        ]);

        $result = $this->gowa->sendVideo($request->phone, $tempPath, $request->caption);
        @unlink($tempPath);

        return $this->handleResult($gowaMessage, $result, 'Video berhasil dikirim');
    }

    /**
     * Kirim audio (base64)
     */
    public function sendAudio(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'audio' => 'required|string',
            'filename' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $tempPath = $this->decodeBase64ToFile($request->audio, $request->filename, 'audio');
        if (!$tempPath) {
            return response()->json(['success' => false, 'message' => 'Format base64 audio tidak valid'], 422);
        }

        $gowaMessage = GowaMessage::create([
            'phone' => $request->phone,
            'type' => 'audio',
            'file_name' => $request->filename,
            'status' => 'pending',
        ]);

        $result = $this->gowa->sendAudio($request->phone, $tempPath);
        @unlink($tempPath);

        return $this->handleResult($gowaMessage, $result, 'Audio berhasil dikirim');
    }

    /**
     * Kirim lokasi
     */
    public function sendLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $gowaMessage = GowaMessage::create([
            'phone' => $request->phone,
            'type' => 'location',
            'metadata' => ['latitude' => $request->latitude, 'longitude' => $request->longitude],
            'status' => 'pending',
        ]);

        $result = $this->gowa->sendLocation($request->phone, $request->latitude, $request->longitude);

        return $this->handleResult($gowaMessage, $result, 'Lokasi berhasil dikirim');
    }

    /**
     * Kirim kontak
     */
    public function sendContact(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'contact_name' => 'required|string|max:255',
            'contact_phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $gowaMessage = GowaMessage::create([
            'phone' => $request->phone,
            'type' => 'contact',
            'metadata' => ['contact_name' => $request->contact_name, 'contact_phone' => $request->contact_phone],
            'status' => 'pending',
        ]);

        $result = $this->gowa->sendContact($request->phone, $request->contact_name, $request->contact_phone);

        return $this->handleResult($gowaMessage, $result, 'Kontak berhasil dikirim');
    }

    /**
     * Kirim link
     */
    public function sendLink(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'link' => 'required|url|max:2048',
            'caption' => 'nullable|string|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $gowaMessage = GowaMessage::create([
            'phone' => $request->phone,
            'message' => $request->caption,
            'type' => 'link',
            'metadata' => ['link' => $request->link],
            'status' => 'pending',
        ]);

        $result = $this->gowa->sendLink($request->phone, $request->link, $request->caption);

        return $this->handleResult($gowaMessage, $result, 'Link berhasil dikirim');
    }

    /**
     * Kirim poll
     */
    public function sendPoll(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'question' => 'required|string|max:255',
            'options' => 'required|array|min:2|max:12',
            'options.*' => 'required|string|max:100',
            'max_answer' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $maxAnswer = $request->input('max_answer', 1);

        $gowaMessage = GowaMessage::create([
            'phone' => $request->phone,
            'type' => 'poll',
            'metadata' => [
                'question' => $request->question,
                'options' => $request->options,
                'max_answer' => $maxAnswer,
            ],
            'status' => 'pending',
        ]);

        $result = $this->gowa->sendPoll($request->phone, $request->question, $request->options, $maxAnswer);

        return $this->handleResult($gowaMessage, $result, 'Poll berhasil dikirim');
    }

    /**
     * Cek status koneksi GOWA
     */
    public function getStatus(): JsonResponse
    {
        $result = $this->gowa->getDevices();

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'data' => [
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
     * Cek nomor WhatsApp terdaftar
     */
    public function checkUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $result = $this->gowa->checkUser($request->phone);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'data' => $result['data'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Gagal memeriksa nomor',
        ], $result['status_code'] ?? 502);
    }

    /**
     * Cek status pesan
     */
    public function getMessageStatus(string $id): JsonResponse
    {
        $message = GowaMessage::find($id);

        if (!$message) {
            return response()->json(['success' => false, 'message' => 'Pesan tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'phone' => $message->phone,
                'type' => $message->type,
                'status' => $message->status,
                'gowa_message_id' => $message->gowa_message_id,
                'error_message' => $message->error_message,
                'sent_at' => $message->sent_at?->toIso8601String(),
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Tarik pesan (revoke)
     */
    public function revokeMessage(string $id): JsonResponse
    {
        $message = GowaMessage::find($id);

        if (!$message || !$message->gowa_message_id) {
            return response()->json(['success' => false, 'message' => 'Pesan tidak ditemukan atau belum terkirim'], 404);
        }

        $result = $this->gowa->revokeMessage($message->phone, $message->gowa_message_id);

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => 'Pesan berhasil ditarik']);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Gagal menarik pesan',
        ], $result['status_code'] ?? 502);
    }

    /**
     * Kirim reaksi ke pesan
     */
    public function reactMessage(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'emoji' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $message = GowaMessage::find($id);

        if (!$message || !$message->gowa_message_id) {
            return response()->json(['success' => false, 'message' => 'Pesan tidak ditemukan atau belum terkirim'], 404);
        }

        $result = $this->gowa->reactMessage($message->phone, $message->gowa_message_id, $request->emoji);

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => 'Reaksi berhasil dikirim']);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Gagal mengirim reaksi',
        ], $result['status_code'] ?? 502);
    }

    /**
     * Decode base64 ke file sementara
     */
    private function decodeBase64ToFile(string $base64, string $filename, string $prefix): ?string
    {
        $data = base64_decode($base64, true);
        if ($data === false) {
            return null;
        }

        $dir = storage_path('app/temp/gowa');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/' . $prefix . '_' . uniqid() . '_' . $filename;
        file_put_contents($path, $data);

        return $path;
    }

    /**
     * Handle hasil pengiriman dan update status pesan
     */
    private function handleResult(GowaMessage $message, array $result, string $successMsg): JsonResponse
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
}
