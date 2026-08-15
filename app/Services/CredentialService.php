<?php

namespace App\Services;

use App\Enums\CredentialLevel;
use App\Enums\PipelineStage;
use App\Models\StudyParticipation;
use App\Models\User;

/**
 * FEATURE — Participant Credentialing (Bronze / Gold / Expert)
 *
 * The rule: your credential level is your completed-study count, nothing else.
 * No application, no admin approval, no field a participant can edit.
 *
 * Every piece of credential logic lives in this one class, so there is exactly
 * one answer to "how is my level decided?".
 */
class CredentialService
{
    /**
     * How many studies this participant has actually finished.
     *
     * PAID counts as completed: a paid session was necessarily completed
     * first, it just also had money released afterwards.
     */
    public function completedCount(User $user): int
    {
        return StudyParticipation::where('participant_id', $user->id)
            ->whereIn('stage', [PipelineStage::COMPLETED, PipelineStage::PAID])
            ->count();
    }

    /**
     * Recount, then save the count and the level it earns.
     *
     * Returns what changed so the caller can tell the participant about a
     * promotion instead of silently updating a number.
     */
    public function recalculate(User $user): array
    {
        $profile = $user->participantProfile;

        $before = $profile->credential_level;
        $count  = $this->completedCount($user);
        $after  = CredentialLevel::fromCompletions($count);

        $profile->update([
            'completed_studies_count' => $count,
            'credential_level'        => $after,
        ]);

        return [
            'profile'   => $profile->fresh(),
            'count'     => $count,
            'from'      => $before,
            'to'        => $after,
            'promoted'  => $before !== $after,
        ];
    }

    /**
     * The tier directly above this one, or null at the top.
     */
    public function nextTier(CredentialLevel $current): ?CredentialLevel
    {
        $ladder = [
            CredentialLevel::NONE,
            CredentialLevel::BRONZE,
            CredentialLevel::GOLD,
            CredentialLevel::EXPERT,
        ];

        return $ladder[array_search($current, $ladder, true) + 1] ?? null;
    }

    /**
     * How far along the participant is towards the next tier, 0–100.
     * Used by the progress ring and the progress bar.
     */
    public function progressPercent(int $count, CredentialLevel $current): int
    {
        $next = $this->nextTier($current);

        if (! $next) {
            return 100;   // already Expert
        }

        $floor  = $current->minCompletions();
        $target = $next->minCompletions();
        $span   = max(1, $target - $floor);

        return (int) min(100, max(0, round(($count - $floor) / $span * 100)));
    }

    /**
     * The three real tiers with what each one unlocks. Built from the enum,
     * so a threshold change flows through to every page that shows the ladder.
     */
    public function ladder(): array
    {
        return [
            [
                'level' => CredentialLevel::BRONZE,
                'perk'  => 'Apply to standard, everyday study listings.',
            ],
            [
                'level' => CredentialLevel::GOLD,
                'perk'  => 'Unlocks better-paying studies reserved for proven participants.',
            ],
            [
                'level' => CredentialLevel::EXPERT,
                'perk'  => 'Eligible for high-paying, specialised and invitation-only research.',
            ],
        ];
    }
}
