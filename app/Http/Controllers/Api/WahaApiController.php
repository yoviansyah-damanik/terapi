<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaGateway\Waha\WahaMessage;
use App\Services\WahaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WahaApiController extends Controller
{
    public function __construct(
        protected WahaService $whatsapp,
    ) {
    }

    /**
     * Kirim pesan teks WhatsApp
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
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $waMessage = WahaMessage::create([
            'phone' => $request->phone,
            'message' => $request->message,
            'type' => 'text',
            'status' => 'pending',
        ]);

        $result = $this->whatsapp->sendText($request->phone, $request->message);

        if ($result['success']) {
            $waMessage->update(['status' => 'sent', 'sent_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Pesan berhasil dikirim',
                'data' => [
                    'id' => $waMessage->id,
                    'phone' => $waMessage->phone,
                    'status' => 'sent',
                    'sent_at' => $waMessage->fresh()->sent_at->toIso8601String(),
                ],
            ]);
        }

        $errorMsg = $result['message'] ?? $result['error'] ?? 'Gagal mengirim pesan';
        $waMessage->update(['status' => 'failed', 'error_message' => $errorMsg]);

        return response()->json([
            'success' => false,
            'message' => $errorMsg,
            'data' => [
                'id' => $waMessage->id,
                'phone' => $waMessage->phone,
                'status' => 'failed',
            ],
        ], $result['status_code'] ?? 502);
    }

    /**
     * Kirim gambar WhatsApp (base64)
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
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Simpan file dari base64 ke storage
        $imageData = base64_decode($request->image, true);
        if ($imageData === false) {
            return response()->json([
                'success' => false,
                'message' => 'Format base64 gambar tidak valid',
            ], 422);
        }

        $path = 'whatsapp/api/' . uniqid() . '_' . $request->filename;
        $fullPath = storage_path('app/public/' . $path);

        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }
        file_put_contents($fullPath, $imageData);

        $waMessage = WahaMessage::create([
            'phone' => $request->phone,
            'message' => $request->caption,
            'type' => 'image',
            'file_path' => $path,
            'file_name' => $request->filename,
            'status' => 'pending',
        ]);

        $result = $this->whatsapp->sendImage($request->phone, $fullPath, $request->caption);

        if ($result['success']) {
            $waMessage->update(['status' => 'sent', 'sent_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Gambar berhasil dikirim',
                'data' => [
                    'id' => $waMessage->id,
                    'phone' => $waMessage->phone,
                    'status' => 'sent',
                    'sent_at' => $waMessage->fresh()->sent_at->toIso8601String(),
                ],
            ]);
        }

        $errorMsg = $result['message'] ?? $result['error'] ?? 'Gagal mengirim gambar';
        $waMessage->update(['status' => 'failed', 'error_message' => $errorMsg]);

        return response()->json([
            'success' => false,
            'message' => $errorMsg,
            'data' => [
                'id' => $waMessage->id,
                'phone' => $waMessage->phone,
                'status' => 'failed',
            ],
        ], $result['status_code'] ?? 502);
    }

    /**
     * Kirim file/dokumen WhatsApp (base64)
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
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $fileData = base64_decode($request->file, true);
        if ($fileData === false) {
            return response()->json([
                'success' => false,
                'message' => 'Format base64 file tidak valid',
            ], 422);
        }

        $path = 'whatsapp/api/' . uniqid() . '_' . $request->filename;
        $fullPath = storage_path('app/public/' . $path);

        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }
        file_put_contents($fullPath, $fileData);

        $waMessage = WahaMessage::create([
            'phone' => $request->phone,
            'type' => 'file',
            'file_path' => $path,
            'file_name' => $request->filename,
            'status' => 'pending',
        ]);

        $result = $this->whatsapp->sendFile($request->phone, $fullPath, $request->filename);

        if ($result['success']) {
            $waMessage->update(['status' => 'sent', 'sent_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'File berhasil dikirim',
                'data' => [
                    'id' => $waMessage->id,
                    'phone' => $waMessage->phone,
                    'status' => 'sent',
                    'sent_at' => $waMessage->fresh()->sent_at->toIso8601String(),
                ],
            ]);
        }

        $errorMsg = $result['message'] ?? $result['error'] ?? 'Gagal mengirim file';
        $waMessage->update(['status' => 'failed', 'error_message' => $errorMsg]);

        return response()->json([
            'success' => false,
            'message' => $errorMsg,
            'data' => [
                'id' => $waMessage->id,
                'phone' => $waMessage->phone,
                'status' => 'failed',
            ],
        ], $result['status_code'] ?? 502);
    }

    /**
     * Cek status koneksi WhatsApp session
     */
    public function getStatus(): JsonResponse
    {
        $result = $this->whatsapp->getSessionStatus();

        if ($result['success']) {
            $data = $result['data'] ?? [];
            $status = $data['status'] ?? 'UNKNOWN';

            return response()->json([
                'success' => true,
                'data' => [
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

    /**
     * Cek status pesan berdasarkan ID
     */
    public function getMessageStatus(string $id): JsonResponse
    {
        $message = WahaMessage::find($id);

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Pesan tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'phone' => $message->phone,
                'type' => $message->type,
                'status' => $message->status,
                'error_message' => $message->error_message,
                'sent_at' => $message->sent_at?->toIso8601String(),
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ]);
    }
}
