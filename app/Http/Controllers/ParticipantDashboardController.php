<?php

namespace App\Http\Controllers;

use App\Enums\CredentialLevel;
use App\Enums\PipelineStage;
use App\Models\StudyParticipation;
use App\Services\StudyMatchingService;

/**
 * The participant's home screen.
 *
 * Everything here is counted from study_participations and read off the
 * participant_profiles row. Nothing is typed into the view.
 */
class ParticipantDashboardController extends Controller
{
    public function index(StudyMatchingService $matching)
    {
        $user = auth()->user();
        $profile = $user->participantProfile;

        // A participant who signed up before their profile row existed.
        abort_if(! $profile, 404, 'No participant profile found for this account.');

        /* ---- unlock progress: volunteer studies completed vs the rule ---- */
        $unlockTarget = (int) config('platform.free_forms_to_unlock_paid');

        $completedCount = StudyParticipation::where('participant_id', $user->id)
            ->where('stage', PipelineStage::COMPLETED)
            ->count();

        $unlockRemaining = max(0, $unlockTarget - $completedCount);

        /* ---- next credential tier ---- */
        $current = $profile->credential_level;
        $next = $this->nextTier($current);

        /* ---- studies this participant has NOT applied to yet ---- */
        $appliedStudyIds = StudyParticipation::where('participant_id', $user->id)
            ->pluck('study_id');

        $recommended = $matching->recommendStudiesForParticipant($user, 4);

        /* ---- their own applications, newest first ---- */
        $applications = StudyParticipation::query()
            ->with('study.researcher')
            ->where('participant_id', $user->id)
            ->latest('updated_at')
            ->take(6)
            ->get();

        /* ---- badge wall, worked out from the profile row ---- */
        $badges = [
            [
                'icon' => '🥉',
                'name' => 'Bronze',
                'earned' => $profile->completed_studies_count >= CredentialLevel::BRONZE->minCompletions(),
            ],
            [
                'icon' => '🥈',
                'name' => 'Gold',
                'earned' => $profile->completed_studies_count >= CredentialLevel::GOLD->minCompletions(),
            ],
            [
                'icon' => '🏆',
                'name' => 'Expert',
                'earned' => $profile->completed_studies_count >= CredentialLevel::EXPERT->minCompletions(),
            ],
            [
                'icon' => '🔥',
                'name' => 'Reliable',
                'earned' => $profile->current_streak_weeks >= (int) config('platform.streak_badge_weeks'),
            ],
            [
                'icon' => '🎯',
                'name' => 'Streak ' . config('platform.streak_karma_bonus_weeks') . 'wk',
                'earned' => $profile->current_streak_weeks >= (int) config('platform.streak_karma_bonus_weeks'),
            ],
            [
                'icon' => '🏅',
                'name' => 'Verified',
                'earned' => $profile->is_verified_participant,
            ],
        ];

        return view('dashboards.participant', compact(
            'user',
            'profile',
            'unlockTarget',
            'completedCount',
            'unlockRemaining',
            'next',
            'recommended',
            'applications',
            'badges'
        ));
    }

    /**
     * The tier above the one you're on, or null if you're already Expert.
     * Read from the enum so a threshold change flows through automatically.
     */
    private function nextTier(CredentialLevel $current): ?CredentialLevel
    {
        $ladder = [
            CredentialLevel::NONE,
            CredentialLevel::BRONZE,
            CredentialLevel::GOLD,
            CredentialLevel::EXPERT,
        ];

        $position = array_search($current, $ladder, true);

        return $ladder[$position + 1] ?? null;
    }
}
