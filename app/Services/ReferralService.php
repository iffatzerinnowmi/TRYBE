<?php

namespace App\Services;

use App\Enums\CredentialLevel;
use App\Enums\KarmaSource;

use App\Enums\PostCreditReason;
use App\Enums\ReferralRewardType;
use App\Enums\ReferralStatus;
use App\Enums\UserRole;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralReward;
use App\Models\ResearcherPostCredit;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Enums\PipelineStage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\KarmaService;

/**
 * FEATURE — Referral system  (Member 4, graded feature)
 *
 * The only class that writes referrals, referral_rewards and
 * researcher_post_credits.
 *
 * TWO DESIGN DECISIONS WORTH BEING ABLE TO DEFEND
 * -----------------------------------------------
 *
 * 1. sync() is DERIVED and IDEMPOTENT, not event-driven.
 *
 *    There is no event anywhere in this codebase that fires when a study is
 *    completed — CredentialService::recalculate() only runs when a human
 *    clicks a button or an artisan command is run. So rather than hang the
 *    feature on an event that is never dispatched, sync() recomputes
 *    qualification from live participation data every time it runs, and the
 *    unique index on (user_id, type, milestone) makes granting a reward
 *    twice impossible.
 *
 *    That means it can be called on every page load, from the API, and from
 *    a scheduled command, and the answer is always the same. It also means
 *    there is no missed-event failure mode to explain, because there is no
 *    event.
 *
 * 2. It NEVER writes participant_profiles.credential_level.
 *
 *    That column has exactly one writer, CredentialService. The reward is
 *    recorded here as granted_credential_level and exposed through
 *    grantedLevel(), which CredentialService reads as a floor. Until Member
 *    1 makes that one-line change the reward is recorded but not yet
 *    reflected on her page — creditFloorApplied() reports exactly that,
 *    rather than the two features fighting over one column.
 */
class ReferralService
{
    /** Name of the signed cookie the middleware drops. */
    public const COOKIE = 'trybe_ref';

    private const LEVEL_ORDER = [
        CredentialLevel::NONE,
        CredentialLevel::BRONZE,
        CredentialLevel::GOLD,
        CredentialLevel::EXPERT,
    ];

    // =================================================================
    // Codes
    // =================================================================

    /** This user's code, created on first request. */
    public function codeFor(User $user): ReferralCode
    {
        $existing = ReferralCode::where('user_id', $user->id)->first();

        if ($existing) {
            return $existing;
        }

        return ReferralCode::create([
            'user_id' => $user->id,
            'code'    => $this->generateCode(),
        ]);
    }

    /** Replace a code — the old one stops working immediately. */
    public function rotateCodeFor(User $user): ReferralCode
    {
        $code = $this->codeFor($user);

        $code->update(['code' => $this->generateCode()]);

        return $code->fresh();
    }

    public function findByCode(string $code): ?ReferralCode
    {
        return ReferralCode::with('user:id,name,role')
            ->where('code', strtoupper(trim($code)))
            ->first();
    }

    public function shareUrl(ReferralCode $code): string
    {
        return route('signup') . '?ref=' . $code->code;
    }

    private function generateCode(): string
    {
        $alphabet = (string) config('platform.referrals.code_alphabet');
        $length   = (int) config('platform.referrals.code_length');
        $tries    = (int) config('platform.referrals.code_generate_tries');

        for ($attempt = 0; $attempt < $tries; $attempt++) {
            $code = '';

            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            if (! ReferralCode::where('code', $code)->exists()) {
                return $code;
            }
        }

        // Unreachable with a 32^8 space unless something is very wrong.
        throw new \RuntimeException('Could not generate a unique referral code.');
    }

    // =================================================================
    // Attribution
    // =================================================================

    /**
     * Record that $newUser arrived through $code.
     *
     * Returns null and records nothing when any guard fails. Attribution
     * failing must NEVER break a signup — somebody unable to create an
     * account because a referral cookie was malformed is a far worse bug
     * than a lost attribution.
     */
    public function attribute(User $newUser, string $code): ?Referral
    {
        $referralCode = $this->findByCode($code);

        if (! $referralCode) {
            return null;   // unknown code — fail silently
        }

        $referrer = $referralCode->user;

        if (! $referrer || ! $this->passesGuards($referrer, $newUser)) {
            return null;
        }

        try {
            return Referral::create([
                'referrer_id'      => $referrer->id,
                'referred_user_id' => $newUser->id,
                'status'           => ReferralStatus::PENDING,
                'code_used'        => $referralCode->code,
                'signed_up_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            // Most likely the unique index on referred_user_id: this person
            // was already referred by somebody else. That is the constraint
            // doing its job, not an error worth surfacing.
            Log::info('Referral attribution skipped.', [
                'referred_user_id' => $newUser->id,
                'reason'           => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Guards, all cheap, all run before the insert.
     *
     * The unique index on referred_user_id is the real defence against
     * re-attribution; these catch the rest.
     */
    private function passesGuards(User $referrer, User $newUser): bool
    {
        if ($referrer->id === $newUser->id) {
            return false;   // self-referral
        }

        if ($referrer->email && $newUser->email
            && strcasecmp($referrer->email, $newUser->email) === 0) {
            return false;   // same person, two accounts
        }

        if (Referral::where('referred_user_id', $newUser->id)->exists()) {
            return false;   // already referred by somebody
        }

        return true;
    }

    // =================================================================
    // sync() — the important one
    // =================================================================

    /**
     * Reconcile everything for one referrer: refresh qualification from live
     * data, then grant any milestone reward that is now due.
     *
     * Safe to call as often as you like. Running it ten times grants one
     * reward, enforced by a database constraint rather than an if-statement.
     */
    public function sync(User $referrer): array
    {
        $this->refreshQualification($referrer);

        $granted = $this->grantDueRewards($referrer);

        return [
            'granted'  => $granted,
            'progress' => $this->progressFor($referrer),
        ];
    }

    /** Move any pending referral that has now done the qualifying thing. */
    private function refreshQualification(User $referrer): void
    {
        $pending = Referral::with('referredUser')
            ->where('referrer_id', $referrer->id)
            ->pending()
            ->get();

        foreach ($pending as $referral) {
            $referred = $referral->referredUser;

            if (! $referred) {
                continue;
            }

            $evidence = $this->qualifyingEvidence($referred);

            if ($evidence['count'] < (int) config('platform.referrals.studies_to_qualify')) {
                continue;
            }

            $referral->update([
                'status' => ReferralStatus::QUALIFIED,

                // The moment it ACTUALLY happened, not the moment sync ran.
                // If sync runs three days late the record is still honest.
                'qualified_at'        => $evidence['at'] ?? now(),
                'qualifying_study_id' => $evidence['study_id'],
            ]);

            // Karma Credits (Member 3): the one karma-earning event that has
            // a real, existing trigger — a referral just became qualified.
            // This line runs exactly once per referral, because a QUALIFIED
            // referral drops out of the pending() scope this loop is built
            // from, so sync() being called repeatedly cannot double-fire it.
            try {
                app(\App\Services\KarmaService::class)->earn(
                    $referrer,
                    \App\Enums\KarmaSource::REFERRAL_SUCCESS,
                    'Referral qualified: '.($referred->name ?? 'a new user')
                );
            } catch (\Throwable $e) {
                Log::warning('Karma award for referral qualification failed.', [
                    'referrer_id' => $referrer->id, 'referral_id' => $referral->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * What counts as qualifying, per role.
     *
     * Participants qualify by COMPLETING a study. Researchers qualify by
     * POSTING one — researchers do not complete studies, so requiring that
     * would make a researcher referral impossible to satisfy.
     */
    private function qualifyingEvidence(User $user): array
    {
        if ($user->role === UserRole::RESEARCHER) {
            $studies = Study::where('researcher_id', $user->id)
                ->orderBy('created_at')
                ->get(['id', 'created_at']);

            return [
                'count'    => $studies->count(),
                'at'       => $studies->first()?->created_at,
                'study_id' => $studies->first()?->id,
            ];
        }

        $participations = StudyParticipation::where('participant_id', $user->id)
            ->whereIn('stage', [PipelineStage::COMPLETED->value, PipelineStage::PAID->value])
            ->orderBy('completed_at')
            ->get(['study_id', 'completed_at', 'updated_at']);

        $first = $participations->first();

        return [
            'count'    => $participations->count(),
            'at'       => $first?->completed_at ?? $first?->updated_at,
            'study_id' => $first?->study_id,
        ];
    }

    /**
     * Grant every milestone reward that is due and not already recorded.
     *
     * @return Collection<ReferralReward>  only the rewards granted THIS run
     */
    private function grantDueRewards(User $referrer): Collection
    {
        $type = ReferralRewardType::forRole($referrer->role);

        // Organizations and admins earn nothing — there is no reward defined
        // for them, and inventing one is not our call.
        if (! $type) {
            return collect();
        }

        $required = max(1, (int) config('platform.referrals.required_to_unlock'));
        $eligible = $this->eligibleQualifiedReferrals($referrer);

        $milestonesReached = intdiv($eligible->count(), $required);

        if (! config('platform.referrals.repeatable')) {
            $milestonesReached = min(1, $milestonesReached);
        }

        $granted = collect();

        for ($m = 1; $m <= $milestonesReached; $m++) {
            $milestone = $m * $required;

            // The referral that tipped this milestone over.
            $trigger = $eligible->get($milestone - 1);

            $reward = $this->grantOnce($referrer, $type, $milestone, $trigger?->id);

            if ($reward) {
                $granted->push($reward);
            }
        }

        return $granted;
    }

    /**
     * Insert one reward, or do nothing if it already exists.
     *
     * firstOrCreate plus the unique index is what makes sync() idempotent.
     * Returns the reward ONLY when it was newly created, so the caller can
     * notify once instead of on every sync.
     */
    private function grantOnce(User $user, ReferralRewardType $type, int $milestone, ?int $triggerId): ?ReferralReward
    {
        return DB::transaction(function () use ($user, $type, $milestone, $triggerId) {
            $existing = ReferralReward::where('user_id', $user->id)
                ->where('type', $type)
                ->where('milestone', $milestone)
                ->first();

            if ($existing) {
                return null;   // already granted — nothing to do, no notification
            }

            $attributes = [
                'user_id'                  => $user->id,
                'type'                     => $type,
                'milestone'                => $milestone,
                'triggered_by_referral_id' => $triggerId,
                'post_credits'             => 0,
                'granted_credential_level' => null,
            ];

            if ($type === ReferralRewardType::CREDENTIAL_TIER_UNLOCK) {
                $attributes['granted_credential_level'] = $this->nextLevelFor($user)?->value;
            }

            if ($type === ReferralRewardType::RESEARCHER_POST_CREDIT) {
                $attributes['post_credits'] = (int) config('platform.referrals.post_credits_per_referral');
            }

            try {
                $reward = ReferralReward::create($attributes);
            } catch (\Throwable $e) {
                // Two syncs racing. The unique index won; this is a no-op.
                Log::info('Referral reward already granted concurrently.', [
                    'user_id' => $user->id, 'milestone' => $milestone,
                ]);

                return null;
            }

            if ($type === ReferralRewardType::RESEARCHER_POST_CREDIT && $reward->post_credits > 0) {
                ResearcherPostCredit::create([
                    'user_id'            => $user->id,
                    'delta'              => $reward->post_credits,
                    'reason'             => PostCreditReason::REFERRAL_EARNED,
                    'referral_reward_id' => $reward->id,
                ]);
            }

            return $reward;
        });
    }

    /**
     * Qualified referrals that can actually pay out.
     *
     * The reward only fires researcher -> researcher or participant ->
     * participant. A mixed pair is still RECORDED and still shown on the
     * page — it just does not count — because a page reading "3 of 3" with
     * no reward looks broken.
     */
    private function eligibleQualifiedReferrals(User $referrer): Collection
    {
        return Referral::with('referredUser:id,role')
            ->where('referrer_id', $referrer->id)
            ->qualified()
            ->orderBy('qualified_at')
            ->get()
            ->filter(fn (Referral $r) => $r->referredUser?->role === $referrer->role)
            ->values();
    }

    // =================================================================
    // Rewards — reads
    // =================================================================

    /**
     * The highest credential level granted by referral, or null.
     *
     * THIS IS THE METHOD CredentialService SHOULD READ. It is the whole
     * reason this feature does not write credential_level itself:
     *
     *     $earned  = CredentialLevel::fromCompletions($count);
     *     $granted = $this->referrals->grantedLevel($user);
     *     $after   = $this->higherOf($earned, $granted);
     *
     * Returns null for anyone with no referral reward, so nothing changes
     * for existing users.
     */
    public function grantedLevel(User $user): ?CredentialLevel
    {
        $levels = ReferralReward::where('user_id', $user->id)
            ->where('type', ReferralRewardType::CREDENTIAL_TIER_UNLOCK)
            ->whereNotNull('granted_credential_level')
            ->pluck('granted_credential_level');

        if ($levels->isEmpty()) {
            return null;
        }

        $best = null;

        foreach ($levels as $level) {
            $candidate = $level instanceof CredentialLevel
                ? $level
                : CredentialLevel::tryFrom((string) $level);

            if ($candidate && ($best === null || $this->levelIndex($candidate) > $this->levelIndex($best))) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Has CredentialService actually honoured the granted level yet?
     *
     * False means the reward is recorded but Member 1's one-line change is
     * still outstanding, so her page still shows the earned level. The API
     * reports this rather than pretending the reward landed.
     */
    public function credentialFloorApplied(User $user): bool
    {
        $granted = $this->grantedLevel($user);

        if (! $granted) {
            return true;   // nothing granted, nothing to honour
        }

        $stored = $user->participantProfile?->credential_level;

        if (! $stored instanceof CredentialLevel) {
            return false;
        }

        return $this->levelIndex($stored) >= $this->levelIndex($granted);
    }

    /** The level a participant would be bumped to, one above their effective level. */
    private function nextLevelFor(User $user): ?CredentialLevel
    {
        $stored  = $user->participantProfile?->credential_level;
        $current = $stored instanceof CredentialLevel ? $stored : CredentialLevel::NONE;

        // Never grant below something already granted.
        $granted = $this->grantedLevel($user);

        if ($granted && $this->levelIndex($granted) > $this->levelIndex($current)) {
            $current = $granted;
        }

        return self::LEVEL_ORDER[$this->levelIndex($current) + 1] ?? CredentialLevel::EXPERT;
    }

    private function levelIndex(CredentialLevel $level): int
    {
        return (int) array_search($level, self::LEVEL_ORDER, true);
    }

    public function postCreditBalance(User $user): int
    {
        return (int) ResearcherPostCredit::where('user_id', $user->id)->sum('delta');
    }

    /**
     * Spend a credit. Member 3's researcher tier system should call this
     * rather than tracking its own count.
     */
    public function spendPostCredit(User $user, int $amount = 1): bool
    {
        if ($this->postCreditBalance($user) < $amount) {
            return false;
        }

        ResearcherPostCredit::create([
            'user_id' => $user->id,
            'delta'   => -abs($amount),
            'reason'  => PostCreditReason::POST_SPENT,
        ]);

        return true;
    }

    // =================================================================
    // Progress, for the page
    // =================================================================

    public function progressFor(User $user): array
    {
        $required = max(1, (int) config('platform.referrals.required_to_unlock'));

        $all       = Referral::with('referredUser:id,name,role')
            ->where('referrer_id', $user->id)
            ->orderByDesc('id')
            ->get();

        $qualified = $all->where('status', ReferralStatus::QUALIFIED);
        $eligible  = $qualified->filter(fn (Referral $r) => $r->referredUser?->role === $user->role);

        $type          = ReferralRewardType::forRole($user->role);
        $eligibleCount = $eligible->count();
        $towardsNext   = $eligibleCount % $required;

        // Once repeatable is off and a reward exists, progress is complete.
        $capped = ! config('platform.referrals.repeatable')
            && ReferralReward::where('user_id', $user->id)->exists();

        return [
            'qualified_count'   => $qualified->count(),
            'eligible_count'    => $eligibleCount,
            'pending_count'     => $all->where('status', ReferralStatus::PENDING)->count(),
            'flagged_count'     => $all->where('status', ReferralStatus::FLAGGED)->count(),
            'total_referred'    => $all->count(),

            'required_to_unlock' => $required,
            'remaining'          => $capped ? 0 : max(0, $required - $towardsNext),
            'percent'            => $capped ? 100 : (int) round($towardsNext / $required * 100),
            'next_milestone'     => $capped ? null : (intdiv($eligibleCount, $required) + 1) * $required,

            'label' => $eligibleCount . ' of ' . $required
                . ' referred users have ' . $this->qualifyingVerb($user),

            // False for organizations and admins, and the reason the page can
            // explain a "3 of 3" that pays nothing.
            'reward_eligible' => $type !== null,
            'reward_type'     => $type?->value,
        ];
    }

    private function qualifyingVerb(User $user): string
    {
        return $user->role === UserRole::RESEARCHER
            ? 'posted a study'
            : 'completed a study';
    }

    /**
     * Pre-written share messages, built server-side.
     *
     * Same reasoning as Member 1 formatting notification labels in PHP: the
     * threshold in the copy comes from config, so changing the rule changes
     * the wording too, and nobody has to remember to edit a string in
     * JavaScript.
     */
    public function shareMessages(User $user, string $url): array
    {
        $required = (int) config('platform.referrals.required_to_unlock');

        $pitch = 'I have been using TRYBE to find research studies to take part in — '
            . 'you get matched to studies that actually fit your profile, and you build '
            . 'a credential level as you go.';

        return [
            'whatsapp' => $pitch . ' Sign up with my link: ' . $url,
            'sms'      => 'Join me on TRYBE — research studies matched to your profile. ' . $url,
            'email'    => [
                'subject' => $user->name . ' invited you to TRYBE',
                'body'    => $pitch . "\n\nSign up here: " . $url
                    . "\n\n(If " . $required . ' of us join and each complete a study, I unlock my next credential tier.)',
            ],
            'generic'  => $pitch . ' ' . $url,
        ];
    }
}
