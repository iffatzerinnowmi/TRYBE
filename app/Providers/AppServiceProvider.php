<?php

namespace App\Providers;

use App\Contracts\PipelineWriter;
use App\Models\User;
use App\Observers\ReferralAttributionObserver;
use App\Services\Pipeline\UnavailablePipelineWriter;
use Illuminate\Support\ServiceProvider;
// add to the use block at the top
use App\Models\StudyParticipation;
use App\Models\StudyReview;
use App\Observers\StudyParticipationObserver;
use App\Observers\StudyReviewObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
        |----------------------------------------------------------------------
        | Pipeline hand-off  (Member 4 -> Member 2)
        |----------------------------------------------------------------------
        |
        | When a participant accepts an invitation they should land in the
        | researcher's pipeline as CONFIRMED. study_participations.stage is
        | Member 2's column, and the team rule is one writer per column, so
        | the invitation feature asks for it through an interface instead of
        | writing it.
        |
        | Member 2: create App\Services\PipelineService implementing
        | App\Contracts\PipelineWriter and this binding picks it up on its
        | own. Nothing here needs editing.
        |
        | Until then the fallback records nothing and logs a warning, so the
        | gap is visible rather than silently duplicated.
        */
        $this->app->bind(PipelineWriter::class, function ($app) {
            $pipelineService = 'App\\Services\\PipelineService';

            if (class_exists($pipelineService) && is_a($pipelineService, PipelineWriter::class, true)) {
                return $app->make($pipelineService);
            }

            return new UnavailablePipelineWriter();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        |----------------------------------------------------------------------
        | Referral attribution  (Member 4)
        |----------------------------------------------------------------------
        |
        | Attributes a new signup to whoever referred them, by reading the
        | cookie CaptureReferralCode dropped.
        |
        | An observer rather than a hidden form field because
        | AuthController::signup() and POST /api/v1/auth/register are two
        | separate code paths that both create users, and because that
        | controller's field names are frozen.
        |
        | It no-ops when there is no referral cookie, so seeders, artisan
        | commands and ordinary signups are unaffected.
        */
        User::observe(ReferralAttributionObserver::class);
        StudyParticipation::observe(StudyParticipationObserver::class);
StudyReview::observe(StudyReviewObserver::class);
    }
}
