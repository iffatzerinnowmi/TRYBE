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
                $middleware->validateCsrfTokens(except: [
            'researcher/sslcommerz/success',
            'researcher/sslcommerz/fail',
            'researcher/sslcommerz/cancel',
        ]);

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

        /*
        |------------------------------------------------------------------
        | Referral link capture  (Member 4)
        |------------------------------------------------------------------
        |
        | Watches every web request for ?ref=CODE and remembers it in an
        | encrypted cookie, so the attribution survives someone opening the
        | link on Monday and signing up on Wednesday.
        |
        | It returns immediately when the parameter is absent, which is
        | almost every request. The attribution itself happens in
        | App\Observers\ReferralAttributionObserver.
        */
        $middleware->appendToGroup('web', \App\Http\Middleware\CaptureReferralCode::class);

        /*
        |------------------------------------------------------------------
        | The referral cookie is not encrypted
        |------------------------------------------------------------------
        |
        | It holds a referral CODE, which is public by design — it is printed
        | in the share URL, shown on the Refer a Friend page, and readable by
        | anyone through the public validate endpoint. Encrypting it protects
        | nothing.
        |
        | Leaving it in plaintext has two practical benefits:
        |
        |   1. API clients can take part in the same attribution flow, by
        |      sending  Cookie: trybe_ref=<code>. The api middleware group
        |      does not run EncryptCookies, so an encrypted value would
        |      arrive undecryptable there and attribution would silently
        |      fail.
        |
        |   2. It is inspectable in devtools, which makes the flow
        |      demonstrable.
        |
        | It grants nothing on its own: the referral is only recorded if the
        | code resolves to a real user, and every guard in
        | ReferralService::passesGuards() still applies. Forging one is no
        | more powerful than clicking somebody's share link.
        */
        $middleware->encryptCookies(except: ['trybe_ref']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function ($request, $throwable) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();