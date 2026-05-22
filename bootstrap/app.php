<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'api.token'    => \App\Http\Middleware\ApiTokenAuth::class,
            'api.scope'    => \App\Http\Middleware\ApiScopeCheck::class,
            'api.size'     => \App\Http\Middleware\LimitRequestSize::class,
            'sec.headers'  => \App\Http\Middleware\SecurityHeaders::class,
            'ip.blacklist' => \App\Http\Middleware\CheckIpBlacklist::class,
        ]);

        // $middleware->appendToGroup('api', \Illuminate\Http\Middleware\HandleCors::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\ForceJsonResponse::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\ApiRequestLogger::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\CheckIpBlacklist::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\CheckIpBlacklist::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn($request) => $request->is('api/*'));
    })->create();
