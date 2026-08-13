<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Support\Facades\Log;

/**
 * FEATURE — Referral system  (Member 4)
 *
 * Attributes a brand-new user to whoever referred them.
 *
 * WHY AN OBSERVER
 * ---------------
 * AuthController::signup() calls User::create() and Auth::login() directly
 * rather than going through Laravel's registration, so the framework's
 * Registered event is never dispatched. The Eloquent `created` hook always
 * fires, and it fires for BOTH signup paths — the web form and
 * POST /api/v1/auth/register — which a hidden form field would not.
 *
 * ACTION AT A DISTANCE
 * --------------------
 * This is invisible to anyone debugging a signup, which is why it is
 * announced in the handoff notes and pointed at from AuthController. If you
 * are here because a signup did something unexpected, this is the file.
 *
 * NOTHING IN HERE MAY BREAK A SIGNUP. The whole body is wrapped in a
 * try/catch that logs and returns. Somebody being unable to create an
 * account because a referral cookie was malformed is a far worse bug than a
 * lost attribution.
 */
class ReferralAttributionObserver
{
    public function __construct(private ReferralService $referrals) {}

    public function created(User $user): void
    {
        try {
            $code = request()?->cookie(ReferralService::COOKIE);

            // No cookie means no referral. This is also what makes the
            // observer a no-op in seeders, artisan commands and any test
            // that does not deliberately set the cookie.
            if (! is_string($code) || trim($code) === '') {
                return;
            }

            $this->referrals->attribute($user, $code);
        } catch (\Throwable $e) {
            Log::warning('Referral attribution failed; signup continued.', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
