<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Observers\StudyParticipationObserver;
use App\Services\FreeToPaidUnlockService;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        StudyParticipation::observe(StudyParticipationObserver::class);

        // Whoever builds "apply to a study" should call:
        //   Gate::authorize('applyToStudy', $study);
        // before creating the StudyParticipation.
        Gate::define('applyToStudy', function (User $user, Study $study) {
            return app(FreeToPaidUnlockService::class)->canApplyToStudy($user, $study);
        });
    }
}
