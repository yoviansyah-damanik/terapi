<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Api\ApiToken;
use App\Models\Api\ApiUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiAuthController extends Controller
{
    /**
     * Buat token baru menggunakan kredensial dari header x-username dan x-password
     */
    public function createToken(Request $request): JsonResponse
    {
        $username = $request->header('x-username');
        $password = $request->header('x-password');

        if (!$username || !$password) {
            return response()->json([
                'success' => false,
                'message' => 'Header x-username dan x-password wajib diisi',
            ], 401);
        }

        $apiUser = ApiUser::active()->where('username', $username)->first();

        if (!$apiUser || !Hash::check($password, $apiUser->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau password salah',
            ], 401);
        }

        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        $tokenName = $request->input('name', 'Token ' . now()->format('d/m/Y H:i'));
        $expiresAt = $request->input('expires_in_hours')
            ? now()->addHours((int) $request->input('expires_in_hours'))
            : null;

        $apiToken = $apiUser->tokens()->create([
            'token' => $hashedToken,
            'name' => $tokenName,
            'expires_at' => $expiresAt,
        ]);

        $apiUser->update(['last_login_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Token berhasil dibuat',
            'data' => [
                'token' => $plainToken,
                'name' => $apiToken->name,
                'scopes' => $apiUser->scopes ?? [],
                'expires_at' => $apiToken->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Hapus token yang sedang digunakan
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $apiToken = $request->attributes->get('api_token');

        if ($apiToken) {
            $apiToken->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Token berhasil dicabut',
        ]);
    }
}
