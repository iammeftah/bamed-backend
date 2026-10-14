<?php
// backend/bootstrap/app.php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // No web login page: guests get a 401 JSON, never a redirect
        $middleware->redirectGuestsTo(fn () => null);

        // The only caller is the Next.js server, which forwards the visitor's IP in
        // X-Forwarded-For (see clientIp() in frontend/lib/server/backend.ts).
        // Trusting that server makes request()->ip() the visitor, so throttle:5,1
        // limits each visitor instead of locking everybody out after 5 attempts.
        // Set TRUSTED_PROXIES to the Next server's IP (comma separated) in production.
        $middleware->trustProxies(
            at: array_filter(explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))),
            headers: Request::HEADER_X_FORWARDED_FOR,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always answer JSON on the API, even without an Accept header
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $e) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();