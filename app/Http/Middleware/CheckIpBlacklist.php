<?php

namespace App\Http\Middleware;

use App\Models\IpBlacklist;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckIpBlacklist
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (IpBlacklist::isBlocked($request->ip())) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'IP Anda telah diblokir dari sistem ini.'], 403);
                }
                abort(403, 'Akses Anda telah diblokir oleh administrator.');
            }
        } catch (\Throwable) {
            // Jangan biarkan error DB menghentikan request
        }

        return $next($request);
    }
}
