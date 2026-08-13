<?php

namespace App\Http\Middleware;

use App\Services\ReferralService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * FEATURE — Referral system  (Member 4)
 *
 * Sees ?ref=CODE on any web request and remembers it in a cookie so the
 * attribution survives the person browsing around before signing up.
 *
 * WHY A COOKIE AND NOT A HIDDEN FORM FIELD
 * ----------------------------------------
 * AuthController::signup() says its field names are FROZEN because the other
 * three members validate against the same strings, and there are TWO signup
 * paths in this codebase — the web form and POST /api/v1/auth/register. A
 * hidden field would mean editing a frozen shared file and would still only
 * cover one of the two paths.
 *
 * This middleware writes nothing to the database except the visit counter.
 * The actual attribution happens in ReferralAttributionObserver when a user
 * row is created, which covers every signup path automatically.
 *
 * The cookie is written through Laravel's cookie jar, so the standard
 * EncryptCookies middleware encrypts it on the way out and decrypts it on
 * the way back in. Nobody can hand-craft one to credit themselves.
 */
class CaptureReferralCode
{
    public function __construct(private ReferralService $referrals) {}

    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->query('ref');

        if (! is_string($code) || trim($code) === '') {
            return $next($request);
        }

        $referralCode = $this->referrals->findByCode($code);

        // Unknown code: drop it silently. Setting a cookie for a code that
        // does not exist would only produce a confusing dead end later.
        if (! $referralCode) {
            return $next($request);
        }

        // Display-only counter. It grants nothing, so there is no incentive
        // to farm it — which is why it is safe to increment before signup.
        $referralCode->increment('visits');

        Cookie::queue(
            ReferralService::COOKIE,
            $referralCode->code,
            (int) config('platform.referrals.attribution_days') * 24 * 60   // minutes
        );

        return $next($request);
    }
}
