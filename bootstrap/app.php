<?php

use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\PerfProfiler;
use App\Http\Middleware\RejectCrossOriginPost;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Replaces Laravel's default token-based CSRF (see
        // RejectCrossOriginPost's docblock for why) and runs the idle
        // session timeout on every web request, same as the original
        // app's index.php did for every request.
        $middleware->web(remove: [
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);
        $middleware->web(append: [
            RejectCrossOriginPost::class,
            EnforceSessionTimeout::class,
            SecurityHeaders::class,
            // TEMPORARY: no-op unless PERF_DEBUG=1 or ?perf=1 — see the class.
            PerfProfiler::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
