<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Observers\StudyEscrowRefundObserver;
use App\Observers\StudyParticipationPayoutObserver;
use App\Services\Payments\SimulatedPaymentGateway;
use Illuminate\Support\ServiceProvider;

/**
 * FEATURE — Verified Payment Escrow (Member 3)
 *
 * Owns everything the feature needs to "just work" without touching
 * unrelated providers:
 *   - binds App\Contracts\PaymentGateway to the simulated gateway
 *   - registers the two model observers that react to state written by
 *     other members' code (participation stage -> completed, study
 *     status -> cancelled)
 *
 * MUST be registered in bootstrap/providers.php (Laravel 11+) or
 * config/app.php's providers array (Laravel 10) — see SNIPPETS.md.
 */
class EscrowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, SimulatedPaymentGateway::class);
    }

    public function boot(): void
    {
        StudyParticipation::observe(StudyParticipationPayoutObserver::class);
        Study::observe(StudyEscrowRefundObserver::class);
    }
}