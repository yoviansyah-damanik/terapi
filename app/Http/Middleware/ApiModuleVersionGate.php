<?php

namespace App\Http\Middleware;

use App\Helpers\ConfigurationHelper;
use Closure;
use Illuminate\Http\Request;

class ApiModuleVersionGate
{
    public function handle(Request $request, Closure $next, string $module, string $version): mixed
    {
        $active = ConfigurationHelper::get("api.modules.{$module}.active_version", 'v1');

        if ($active !== $version) {
            return response()->json([
                'message'           => "Module '{$module}' active version is '{$active}', not '{$version}'.",
                'module'            => $module,
                'active_version'    => $active,
                'requested_version' => $version,
            ], 410);
        }

        return $next($request);
    }
}
