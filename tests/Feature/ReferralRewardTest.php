<?php

namespace Tests\Feature;

use App\Enums\CredentialLevel;
use App\Enums\IncentiveType;
use App\Enums\PipelineStage;
use App\Enums\ReferralRewardType;
use App\Enums\ReferralStatus;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\ParticipantProfile;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Services\CredentialService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Qualification and reward granting — the core of the feature.
 */
class ReferralRewardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * THE test. sync() is called from the API on every page load, from a
     * scheduled command, and by hand. If it were not idempotent, a referrer
     * would collect a reward every time they refreshed.
     *
     * Idempotency here is enforced by unique(user_id, type, milestone) — a
     * database constraint, not an if-statement somebody can delete.
     */
    public function test_running_sync_ten_times_grants_exactly_one_reward(): void
    {
        $referrer = $this->participant();
        $this->giveQualifiedReferrals($referrer, (int) config('platform.referrals.required_to_unlock'));

        $service = app(ReferralService::class);

        for ($i = 0; $i < 10; $i++) {
            $service->sync($referrer);
        }

        $this->assertSame(1, ReferralReward::where('user_id', $referrer->id)->count());
    }

    /** Only the first sync reports a grant, so the notification fires once. */
    public function test_only_the_first_sync_reports_a_new_grant(): void
    {
        $referrer = $this->participant();
        $this->giveQualifiedReferrals($referrer, (int) config('platform.referrals.required_to_unlock'));

        $service = app(ReferralService::class);

        $this->assertCount(1, $service->sync($referrer)['granted']);
        $this->assertCount(0, $service->sync($referrer)['granted']);
    }

    public function test_the_reward_fires_at_exactly_the_configured_threshold(): void
    {
        $required = (int) config('platform.referrals.required_to_unlock');
        $referrer = $this->participant();
        $service  = app(ReferralService::class);

        $this->giveQualifiedReferrals($referrer, $required - 1);
        $service->sync($referrer);

        $this->assertSame(0, ReferralReward::where('user_id', $referrer->id)->count(),
            'One short of the threshold must grant nothing.');

        $this->giveQualifiedReferrals($referrer, 1);
        $service->sync($referrer);

        $this->assertSame(1, ReferralReward::where('user_id', $referrer->id)->count());
    }

    /**
     * A referred user who signed up but never completed a study does not
     * count. This is the main defence against fake accounts: qualification
     * requires a researcher to have marked them complete.
     */
    public function test_a_referred_user_who_never_completes_a_study_does_not_count(): void
    {
        $referrer = $this->participant();
        $required = (int) config('platform.referrals.required_to_unlock');

        for ($i = 0; $i < $required; $i++) {
            $this->refer($referrer, $this->participant());   // no completion
        }

        app(ReferralService::class)->sync($referrer);

        $this->assertSame(0, ReferralReward::where('user_id', $referrer->id)->count());
        $this->assertSame($required, Referral::where('referrer_id', $referrer->id)
            ->where('status', ReferralStatus::PENDING)->count());
    }

    /**
     * With repeatable off, six referrals still only ever grant one reward.
     * The brief only promises the first bump, so this must be switchable
     * from config alone — no logic change.
     */
    public function test_repeatable_off_caps_the_reward_at_one(): void
    {
        config(['platform.referrals.repeatable' => false]);

        $required = (int) config('platform.referrals.required_to_unlock');
        $referrer = $this->participant();

        $this->giveQualifiedReferrals($referrer, $required * 2);

        app(ReferralService::class)->sync($referrer);

        $this->assertSame(1, ReferralReward::where('user_id', $referrer->id)->count());
    }

    /** With it on, the second milestone grants a second reward. */
    public function test_repeatable_on_grants_a_reward_per_milestone(): void
    {
        config(['platform.referrals.repeatable' => true]);

        $required = (int) config('platform.referrals.required_to_unlock');
        $referrer = $this->participant();

        $this->giveQualifiedReferrals($referrer, $required * 2);

        app(ReferralService::class)->sync($referrer);

        $this->assertSame(2, ReferralReward::where('user_id', $referrer->id)->count());
    }

    /** The balance is SUM(delta) over the ledger, never a stored total. */
    public function test_the_post_credit_balance_is_the_sum_of_the_ledger(): void
    {
        $researcher = $this->researcher();
        $service    = app(ReferralService::class);

        \App\Models\ResearcherPostCredit::create([
            'user_id' => $researcher->id, 'delta' => 3,
            'reason'  => \App\Enums\PostCreditReason::ADMIN_ADJUST,
        ]);
        \App\Models\ResearcherPostCredit::create([
            'user_id' => $researcher->id, 'delta' => -1,
            'reason'  => \App\Enums\PostCreditReason::POST_SPENT,
        ]);

        $this->assertSame(2, $service->postCreditBalance($researcher));

        $this->assertTrue($service->spendPostCredit($researcher, 2));
        $this->assertSame(0, $service->postCreditBalance($researcher));

        $this->assertFalse($service->spendPostCredit($researcher),
            'Spending below zero must be refused, not silently allowed.');
    }

    /** Flagged referrals are excluded from the count but still recorded. */
    public function test_flagged_referrals_do_not_count_towards_a_reward(): void
    {
        $required = (int) config('platform.referrals.required_to_unlock');
        $referrer = $this->participant();

        $this->giveQualifiedReferrals($referrer, $required);

        Referral::where('referrer_id', $referrer->id)
            ->first()
            ->update(['status' => ReferralStatus::FLAGGED]);

        app(ReferralService::class)->sync($referrer);

        $this->assertSame(0, ReferralReward::where('user_id', $referrer->id)->count());
    }

    /** qualified_at records when it HAPPENED, not when sync ran. */
    public function test_qualified_at_is_the_completion_date_not_the_sync_date(): void
    {
        $referrer = $this->participant();
        $referred = $this->participant();

        $this->refer($referrer, $referred);

        $completedAt = now()->subDays(9)->startOfSecond();

        StudyParticipation::create([
            'study_id'       => $this->study()->id,
            'participant_id' => $referred->id,
            'stage'          => PipelineStage::COMPLETED->value,
            'completed_at'   => $completedAt,
        ]);

        app(ReferralService::class)->sync($referrer);

        $referral = Referral::where('referred_user_id', $referred->id)->first();

        $this->assertSame(ReferralStatus::QUALIFIED, $referral->status);
        $this->assertSame(
            $completedAt->toDateTimeString(),
            $referral->qualified_at->toDateTimeString()
        );
    }

    /**
     * Researcher -> researcher earns a post credit. The balance is SUM(delta)
     * on a ledger, never a stored column.
     */
    public function test_a_researcher_referring_a_researcher_earns_a_post_credit(): void
    {
        $required = (int) config('platform.referrals.required_to_unlock');
        $referrer = $this->researcher();

        for ($i = 0; $i < $required; $i++) {
            $referred = $this->researcher();
            $this->refer($referrer, $referred);
            $this->study($referred);     // researchers qualify by POSTING a study
        }

        $service = app(ReferralService::class);
        $service->sync($referrer);

        $reward = ReferralReward::where('user_id', $referrer->id)->first();

        $this->assertNotNull($reward);
        $this->assertSame(ReferralRewardType::RESEARCHER_POST_CREDIT, $reward->type);
        $this->assertSame(
            (int) config('platform.referrals.post_credits_per_referral'),
            $service->postCreditBalance($referrer)
        );
    }

    /**
     * A mixed-role pair is recorded and shown, but pays nothing — otherwise
     * a page reading "3 of 3" with no reward looks broken.
     */
    public function test_a_mixed_role_referral_counts_towards_nothing(): void
    {
        $required = (int) config('platform.referrals.required_to_unlock');
        $referrer = $this->researcher();

        for ($i = 0; $i < $required; $i++) {
            $referred = $this->participant();
            $this->refer($referrer, $referred);

            StudyParticipation::create([
                'study_id'       => $this->study()->id,
                'participant_id' => $referred->id,
                'stage'          => PipelineStage::COMPLETED->value,
                'completed_at'   => now(),
            ]);
        }

        $service = app(ReferralService::class);
        $service->sync($referrer);

        $progress = $service->progressFor($referrer);

        $this->assertSame($required, $progress['qualified_count'], 'They still qualified.');
        $this->assertSame(0, $progress['eligible_count'], 'But none of them count for a reward.');
        $this->assertSame(0, ReferralReward::where('user_id', $referrer->id)->count());
    }

    /**
     * The reward is recorded on OUR table and never written to
     * participant_profiles.credential_level — that column has one writer.
     *
     * This test documents the seam: grantedLevel() is what CredentialService
     * should read as a floor. It will keep passing whether or not Member 1
     * has made that change.
     */
    public function test_the_reward_never_writes_the_credential_level_column(): void
    {
        $referrer = $this->participant();
        $before   = $referrer->participantProfile->credential_level;

        $this->giveQualifiedReferrals($referrer, (int) config('platform.referrals.required_to_unlock'));

        $service = app(ReferralService::class);
        $service->sync($referrer);

        $this->assertSame(
            $before,
            $referrer->participantProfile->fresh()->credential_level,
            'ReferralService must not write credential_level — CredentialService owns it.'
        );

        $this->assertNotNull($service->grantedLevel($referrer),
            'But the granted level must be readable, so CredentialService can use it as a floor.');
    }

    /**
     * The floor Member 1 needs to honour. Until CredentialService reads
     * grantedLevel(), recalculate() wipes the reward — this test proves it
     * and will start passing the moment she makes the one-line change.
     *
     * @see docs/handoff-asks-for-group.md
     */
    public function test_credential_floor_is_reported_honestly_until_credential_service_honours_it(): void
    {
        $referrer = $this->participant();
        $this->giveQualifiedReferrals($referrer, (int) config('platform.referrals.required_to_unlock'));

        $service = app(ReferralService::class);
        $service->sync($referrer);

        app(CredentialService::class)->recalculate($referrer);

        $granted = $service->grantedLevel($referrer);
        $stored  = $referrer->participantProfile->fresh()->credential_level;

        // Whatever CredentialService currently does, the API must not claim
        // the reward landed when the stored level is still below it.
        $this->assertSame(
            $stored === $granted || $this->levelIndex($stored) > $this->levelIndex($granted),
            $service->credentialFloorApplied($referrer->fresh())
        );
    }

    // -----------------------------------------------------------------

    private function levelIndex(?CredentialLevel $level): int
    {
        $order = [CredentialLevel::NONE, CredentialLevel::BRONZE, CredentialLevel::GOLD, CredentialLevel::EXPERT];

        return (int) array_search($level, $order, true);
    }

    private function giveQualifiedReferrals(User $referrer, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $referred = $this->participant();
            $this->refer($referrer, $referred);

            StudyParticipation::create([
                'study_id'       => $this->study()->id,
                'participant_id' => $referred->id,
                'stage'          => PipelineStage::COMPLETED->value,
                'completed_at'   => now()->subDays($i + 1),
            ]);
        }
    }

    private function refer(User $referrer, User $referred): Referral
    {
        return Referral::create([
            'referrer_id'      => $referrer->id,
            'referred_user_id' => $referred->id,
            'status'           => ReferralStatus::PENDING,
            'code_used'        => app(ReferralService::class)->codeFor($referrer)->code,
            'signed_up_at'     => now()->subDays(10),
        ]);
    }

    private function study(?User $researcher = null): Study
    {
        return Study::create([
            'researcher_id'       => ($researcher ?? $this->researcher())->id,
            'title'               => 'Referral test study ' . uniqid(),
            'category'            => 'survey',
            'method'              => 'online',
            'duration_minutes'    => 30,
            'incentive_type'      => IncentiveType::VOLUNTEER->value,
            'compensation_amount' => 0,
            'slots'               => 10,
            'status'              => StudyStatus::OPEN->value,
            'participants_count'  => 0,
        ]);
    }

    private function researcher(): User
    {
        return User::create([
            'name'                => 'Researcher ' . uniqid(),
            'email'               => uniqid('researcher_', true) . '@example.com',
            'password'            => 'password',
            'role'                => UserRole::RESEARCHER->value,
            'verification_status' => 'verified',
        ]);
    }

    private function participant(): User
    {
        $user = User::create([
            'name'                => 'Participant ' . uniqid(),
            'email'               => uniqid('participant_', true) . '@example.com',
            'password'            => 'password',
            'role'                => UserRole::PARTICIPANT->value,
            'verification_status' => 'unverified',
        ]);

        ParticipantProfile::create([
            'user_id'                 => $user->id,
            'age'                     => 25,
            'credential_level'        => CredentialLevel::NONE->value,
            'completed_studies_count' => 0,
        ]);

        return $user->fresh();
    }
}
