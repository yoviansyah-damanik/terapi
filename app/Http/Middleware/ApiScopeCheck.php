<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiScopeCheck
{
    /**
     * Periksa apakah user pemilik token memiliki scope yang diperlukan.
     */
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $apiToken = $request->attributes->get('api_token');
        $apiUser = $apiToken?->apiUser;

        if (!$apiUser || !$apiUser->hasScope($scope)) {
            return response()->json([
                'success' => false,
                'message' => 'Akun API Anda tidak memiliki akses ke endpoint ini',
                'required_scope' => $scope,
            ], 403);
        }

        return $next($request);
    }
}
