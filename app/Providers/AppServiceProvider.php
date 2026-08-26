<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\PipelineWriter;
use App\Models\User;
use App\Observers\ReferralAttributionObserver;
use App\Services\Pipeline\UnavailablePipelineWriter;
use App\Services\Payments\SimulatedPaymentGateway;
use Illuminate\Support\ServiceProvider;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\StudyReview;
use App\Observers\StudyEscrowRefundObserver;
use App\Observers\StudyParticipationObserver;
use App\Observers\StudyParticipationPayoutObserver;
use App\Observers\StudyReviewObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PipelineWriter::class, function ($app) {
            $pipelineService = 'App\\Services\\PipelineService';

            if (class_exists($pipelineService) && is_a($pipelineService, PipelineWriter::class, true)) {
                return $app->make($pipelineService);
            }

            return new UnavailablePipelineWriter();
        });

        // Verified Payment Escrow (Member 3) — swap this ONE binding
        // for a real gateway later; nothing else changes.
        $this->app->bind(PaymentGateway::class, SimulatedPaymentGateway::class);
        $this->app->bind(PaymentEscrowService::class, function ($app) {
            return new PaymentEscrowService($app->make(PaymentGateway::class));
        });
    }

    public function boot(): void
    {
        User::observe(ReferralAttributionObserver::class);
        StudyParticipation::observe(StudyParticipationObserver::class);
        StudyReview::observe(StudyReviewObserver::class);

        // Verified Payment Escrow (Member 3)
        StudyParticipation::observe(StudyParticipationPayoutObserver::class);
        Study::observe(StudyEscrowRefundObserver::class);
    }
}
