<?php

namespace App\Http\Middleware;

use App\Models\Api\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak ditemukan. Gunakan header Authorization: Bearer {token}',
            ], 401);
        }

        $hashedToken = hash('sha256', $bearerToken);

        $apiToken = ApiToken::where('token', $hashedToken)->first();

        if (!$apiToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid',
            ], 401);
        }

        if ($apiToken->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Token sudah kedaluwarsa',
            ], 401);
        }

        if (!$apiToken->apiUser || !$apiToken->apiUser->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun API tidak aktif',
            ], 401);
        }

        $apiToken->update(['last_used_at' => now()]);

        $request->attributes->set('api_token', $apiToken);
        $request->attributes->set('api_user', $apiToken->apiUser);

        return $next($request);
    }
}
