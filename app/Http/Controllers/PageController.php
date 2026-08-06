<?php

namespace App\Http\Controllers;

use App\Enums\CredentialLevel;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;

/**
 * Public pages that anyone can see without logging in.
 *
 * Everything on the landing page is either counted out of the database or
 * read from config/platform.php. There are no numbers typed into the view.
 */
class PageController extends Controller
{
    public function landing()
    {
        /**
         * How many volunteer studies unlock paid access.
         * Comes from config/platform.php so all four members agree on it.
         */
        $unlockTarget = (int) config('platform.free_forms_to_unlock_paid');

        /**
         * Live platform numbers, counted at the moment the page loads.
         */
        $stats = [
            'open_studies'  => Study::where('status', StudyStatus::OPEN)->count(),
            'participants'  => User::where('role', UserRole::PARTICIPANT)->count(),
            'researchers'   => User::where('role', UserRole::RESEARCHER)->count(),
            'organizations' => User::where('role', UserRole::ORGANIZATION)->count(),
            'completed'     => StudyParticipation::where('stage', PipelineStage::COMPLETED)->count(),
        ];

        /**
         * The credential ladder, built from the enum rather than written out
         * by hand. Change a threshold in CredentialLevel and this page follows.
         */
        $tiers = collect([
            CredentialLevel::BRONZE,
            CredentialLevel::GOLD,
            CredentialLevel::EXPERT,
        ])->map(fn (CredentialLevel $tier) => [
            'key'   => $tier->value,
            'name'  => $tier->label(),
            'min'   => $tier->minCompletions(),
            'perk'  => match ($tier) {
                CredentialLevel::BRONZE => 'Full access to standard listings across the platform.',
                CredentialLevel::GOLD   => 'Eligible for mid-tier paid studies and higher search visibility.',
                CredentialLevel::EXPERT => 'Invitation-only research and niche, high-paying programs.',
                default => '',
            },
            'note'  => match ($tier) {
                CredentialLevel::BRONZE => 'Entry credential',
                CredentialLevel::GOLD   => 'Mid-tier unlock',
                CredentialLevel::EXPERT => 'Top-tier unlock',
                default => '',
            },
        ]);

        /**
         * A short preview of studies that are genuinely open right now.
         */
        $openStudies = Study::query()
            ->with('researcher')
            ->where('status', StudyStatus::OPEN)
            ->latest('id')
            ->take(3)
            ->get();

        return view('landing', compact('unlockTarget', 'stats', 'tiers', 'openStudies'));
    }
}
