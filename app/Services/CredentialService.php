<?php

namespace App\Services;

use App\Enums\CredentialLevel;
use App\Enums\PipelineStage;
use App\Models\StudyParticipation;
use App\Models\User;

/**
 * FEATURE — Participant Credentialing (Bronze / Gold / Expert)
 *
 * The rule: your credential level is your completed-study count — and never
 * lower than a level you have already been given.
 *
 * SINGLE WRITER: this class is the only place that writes credential_level
 * and completed_studies_count. Member 4's referral system deliberately does
 * NOT write that column; it records the grant in referral_rewards and this
 * class reads it back as a floor.
 *
 * WHY THE LEVEL IS A FLOOR
 * ------------------------
 * The old version overwrote credential_level with whatever the count said,
 * every time. That is wrong in two ways:
 *
 *   1. It DEMOTES. Delete a participation, or move a stage backwards, and a
 *      Gold participant silently becomes Bronze. A credential that can go
 *      down on its own is not a credential anyone would trust.
 *
 *   2. It WIPES GRANTS. The referral reward unlocks the next tier without
 *      the completions. Under the old code the next recalculate erased it —
 *      and recalculate is reachable three ways: the button on the
 *      credentials page, the API endpoint, and php artisan trybe:recalculate.
 *
 * So the stored level becomes the highest of three things: what the count
 * earns, what a referral granted, and what is already saved.
 *
 * Consequence worth knowing: nothing lowers a level automatically any more.
 * That is deliberate. If a level ever needs correcting it should be a
 * separate, explicit admin action, not a side effect of a recount.
 *
 * WHY THIS CLASS DEPENDS ON ReferralService
 * -----------------------------------------
 * Ordinarily credentials should not know about referrals. The dependency
 * runs this way round because of the single-writer rule: only one class may
 * write credential_level, and it is this one, so the referral feature cannot
 * apply its own reward. It records the grant and this class reads it.
 *
 * The dependency is one-way and read-only. ReferralService has no
 * constructor dependencies at all, so there is no circular resolution — and
 * grantedLevel() returns null for everyone without a referral reward, which
 * is almost every user.
 */
class CredentialService
{
    public function __construct(private ReferralService $referrals) {}

    /**
     * Lowest to highest. One list, used by every comparison below, so the
     * ordering is stated once rather than implied in several places.
     */
    private const LEVEL_ORDER = [
        CredentialLevel::NONE,
        CredentialLevel::BRONZE,
        CredentialLevel::GOLD,
        CredentialLevel::EXPERT,
    ];

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
     * Recount, then save the count and the level it earns — or keep a higher
     * level if one has been earned or granted before.
     *
     * Returns what changed so the caller can tell the participant about a
     * promotion instead of silently updating a number.
     */
    public function recalculate(User $user): array
    {
        $profile = $user->participantProfile;

        $before = $profile->credential_level;
        $count  = $this->completedCount($user);

        // What the completions alone would earn.
        $earned = CredentialLevel::fromCompletions($count);

        // What a referral reward granted, if any. Null for almost everyone.
        $granted = $this->referrals->grantedLevel($user);

        // The floor: never below what is already stored, never below a grant.
        $after = $this->highest($earned, $granted, $before);

        $profile->update([
            'completed_studies_count' => $count,
            'credential_level'        => $after,
        ]);

        return [
            'profile'  => $profile->fresh(),
            'count'    => $count,
            'from'     => $before,
            'to'       => $after,
            'promoted' => $before !== $after,

            // Did the count alone justify this level, or is it being held up
            // by a grant? The page uses this to label the tier honestly.
            'earned'   => $earned,
            'granted'  => $granted,
        ];
    }

    /**
     * The credential level granted by a referral reward, or null.
     *
     * A passthrough to ReferralService, so the API controller only ever has
     * to talk to this service — the referral dependency stays in one place.
     */
    public function grantedLevel(User $user): ?CredentialLevel
    {
        return $this->referrals->grantedLevel($user);
    }

    /**
     * The tier directly above this one, or null at the top.
     */
    public function nextTier(CredentialLevel $current): ?CredentialLevel
    {
        return self::LEVEL_ORDER[$this->levelIndex($current) + 1] ?? null;
    }

    /**
     * Where a level sits in the ladder: NONE 0, BRONZE 1, GOLD 2, EXPERT 3.
     *
     * Public because the API controller needs it to work out whether a rung
     * on the ladder should be drawn as unlocked.
     */
    public function levelIndex(CredentialLevel $level): int
    {
        return (int) array_search($level, self::LEVEL_ORDER, true);
    }

    /**
     * The highest of however many levels are passed in. Nulls are ignored,
     * so a user with no referral grant behaves exactly as before.
     */
    private function highest(?CredentialLevel ...$levels): CredentialLevel
    {
        $best = CredentialLevel::NONE;

        foreach ($levels as $level) {
            if ($level && $this->levelIndex($level) > $this->levelIndex($best)) {
                $best = $level;
            }
        }

        return $best;
    }

    /**
     * How far along the participant is towards the next tier, 0–100.
     * Used by the progress ring and the progress bar.
     *
     * A granted level can sit above the completion count, in which case this
     * returns 0 — the participant genuinely has made no progress towards the
     * tier above their granted one yet, and saying so is more honest than
     * inventing a percentage.
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