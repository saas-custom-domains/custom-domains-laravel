<?php

use App\Http\Middleware\AppHostOnly;
use App\Http\Middleware\ResolveBoard;
use App\Http\Middleware\UseServedForHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        // Outside the web group, so load balancers can call it by IP.
        health: '/up',
        // Webhooks from SaaS Custom Domains: no session, no CSRF,
        // any host.
        then: fn () => Route::middleware('api')
            ->group(__DIR__.'/../routes/hooks.php'),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [UseServedForHost::class]);

        $middleware->alias([
            'board' => ResolveBoard::class,
            'app-host' => AppHostOnly::class,
        ]);

        // Trust the client IP and scheme from the proxies listed in
        // TRUSTED_PROXIES (config/trustedproxy.php), so links on
        // customer domains are https://. Not X-Forwarded-Host: behind
        // SaaS Custom Domains it's your own hostname.
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->expectsJson(),
        );
    })->create();
