<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        /*
        |------------------------------------------------------------------
        | Sanctum stateful API
        |------------------------------------------------------------------
        |
        | This one line is what makes the API-driven frontend possible.
        |
        | Without it, every /api/* route accepts ONLY a Bearer token, so
        | JavaScript running in the browser would have to store a token,
        | attach it to every request, and handle it expiring.
        |
        | With it, Sanctum accepts EITHER:
        |
        |   - the ordinary session cookie the browser already has after
        |     logging in  (used by our own pages), OR
        |   - an Authorization: Bearer <token> header  (used by Postman)
        |
        | Same routes, same controllers, same auth:sanctum guard. Two ways
        | in. Nobody has to write token-handling code.
        |
        | Requires SANCTUM_STATEFUL_DOMAINS=trybe.test in .env
        |
        */
        $middleware->statefulApi();

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function ($request, $throwable) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();