<?php

namespace Tests\Feature;

use App\Enums\IncentiveType;
use App\Enums\KarmaSource;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Services\FreeToPaidUnlockService;
use App\Services\KarmaService;
use App\Services\StudyApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Apply to Study — volunteer studies only  (Member 4)
 *
 * The rules are tested through StudyApplicationService::blockingReason(),
 * which is pure and needs no HTTP, and separately through the endpoint where
 * the status code is the thing being asserted.
 *
 * The test that matters most is
 * test_a_paid_study_is_rejected_server_side: a disabled button is not a
 * guard, and that test is the answer to "how do you stop someone applying to
 * a paid study without karma".
 */
class StudyApplicationTest extends TestCase
{
    use RefreshDatabase;

    // =================================================================
    // The rules, with no HTTP involved
    // =================================================================

    public function test_a_participant_can_apply_to_an_open_volunteer_study(): void
    {
        $participant = $this->participant();
        $study       = $this->study();

        $this->assertNull($this->service()->blockingReason($participant, $study));

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/apply")
            ->assertStatus(201)
            ->assertJsonPath('data.stage', PipelineStage::APPLIED->value);

        $this->assertDatabaseHas('study_participations', [
            'study_id'       => $study->id,
            'participant_id' => $participant->id,
            'stage'          => PipelineStage::APPLIED->value,
        ]);
    }

    public function test_applying_twice_is_rejected(): void
    {
        $participant = $this->participant();
        $study       = $this->study();

        $this->actingAs($participant)->postJson("/api/v1/studies/{$study->id}/apply")->assertStatus(201);
        $this->actingAs($participant)->postJson("/api/v1/studies/{$study->id}/apply")->assertStatus(409);

        $this->assertSame(1, StudyParticipation::where('study_id', $study->id)->count());
    }

    /**
     * THE important one.
     *
     * A participant with no volunteer progress and no karma cannot apply to a
     * paid study, and the refusal is SERVER-SIDE — a disabled button is a
     * courtesy, this is the guard.
     *
     * The reason code differs by branch, deliberately:
     *
     *   without Member 3's merge : 'paid_not_yet_available' (422) — no rule yet
     *   with it                  : 'paid_locked' (403) — the rule says no
     *
     * Both are refusals leaving no participation row, which is the property
     * that matters. Pinning one code would make this fail on merge for a
     * reason that has nothing to do with the behaviour being tested.
     */
    public function test_a_paid_study_is_rejected_server_side(): void
    {
        $participant = $this->participant();
        $study       = $this->study(['incentive_type' => IncentiveType::CASH, 'compensation_amount' => 500]);

        $reason = $this->service()->blockingReason($participant, $study);

        $this->assertContains(
            $reason,
            ['paid_not_yet_available', 'paid_locked', 'paid_no_slots'],
            'A participant with no progress and no karma must not reach a paid study.'
        );

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/apply")
            ->assertStatus(StudyApplicationService::REASON_STATUS[$reason]);

        $this->assertDatabaseCount('study_participations', 0);
    }

    // =================================================================
    // The paid routes — Member 3's rule, my entry point
    // =================================================================

    /**
     * Karma opens a paid study when the volunteer route has not been earned.
     *
     * Skipped rather than failed where Member 3's API is absent. A test that
     * cannot run says so; a test that fails for a missing dependency is noise,
     * and noise trains people to ignore red.
     */
    public function test_the_karma_route_opens_a_paid_study(): void
    {
        $this->skipWithoutPaidApi();

        $participant = $this->participant();
        $unlock      = app(FreeToPaidUnlockService::class);
        $karma       = app(KarmaService::class);

        $participant->participantProfile->update([
            'volunteer_progress' => 0,
            'paid_used'          => 0,
        ]);

        // Earn past the unlock cost through a legitimate source.
        while ($karma->balanceFor($participant->fresh()) < $unlock->karmaUnlockCost()) {
            $karma->earn($participant, KarmaSource::REFERRAL_SUCCESS);
        }

        $before = $karma->balanceFor($participant->fresh());
        $study  = $this->study(['incentive_type' => IncentiveType::CASH, 'compensation_amount' => 500]);

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/apply")
            ->assertStatus(201);

        $this->assertDatabaseHas('study_participations', [
            'study_id'       => $study->id,
            'participant_id' => $participant->id,
            'stage'          => PipelineStage::APPLIED->value,
        ]);

        // Exactly the cost — not zero, not twice.
        $this->assertSame(
            $before - $unlock->karmaUnlockCost(),
            $karma->balanceFor($participant->fresh())
        );
    }

    /**
     * The rule Member 3's spec states twice: if the volunteer route is already
     * earned, karma is NEVER spent.
     */
    public function test_a_participant_at_the_target_spends_no_karma(): void
    {
        $this->skipWithoutPaidApi();

        $participant = $this->participant();
        $unlock      = app(FreeToPaidUnlockService::class);
        $karma       = app(KarmaService::class);

        $participant->participantProfile->update([
            'volunteer_progress' => $unlock->cycleTarget(),
            'paid_used'          => 0,
        ]);

        $karma->earn($participant, KarmaSource::REFERRAL_SUCCESS);
        $before = $karma->balanceFor($participant->fresh());

        $study = $this->study(['incentive_type' => IncentiveType::CASH]);

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/apply")
            ->assertStatus(201);

        $this->assertSame(
            $before,
            $karma->balanceFor($participant->fresh()),
            'Karma must not be spent when the volunteer route is already earned.'
        );
    }

    /**
     * A study-level problem is caught BEFORE any karma is spent.
     *
     * This is the ordering that matters in blockingReason(): if a closed study
     * reached applyToPaidStudy(), the karma would go and the application would
     * not happen.
     */
    public function test_a_closed_paid_study_costs_no_karma(): void
    {
        $this->skipWithoutPaidApi();

        $participant = $this->participant();
        $unlock      = app(FreeToPaidUnlockService::class);
        $karma       = app(KarmaService::class);

        $participant->participantProfile->update([
            'volunteer_progress' => $unlock->cycleTarget(),
            'paid_used'          => 0,
        ]);

        $karma->earn($participant, KarmaSource::REFERRAL_SUCCESS);
        $before = $karma->balanceFor($participant->fresh());

        $study = $this->study([
            'incentive_type' => IncentiveType::CASH,
            'status'         => StudyStatus::CLOSED,
        ]);

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/apply")
            ->assertStatus(422);

        $this->assertSame($before, $karma->balanceFor($participant->fresh()));
        $this->assertDatabaseCount('study_participations', 0);
    }

    /** The button must say what it costs before it is pressed. */
    public function test_the_label_names_the_karma_cost(): void
    {
        $this->skipWithoutPaidApi();

        $participant = $this->participant();
        $unlock      = app(FreeToPaidUnlockService::class);
        $karma       = app(KarmaService::class);

        $participant->participantProfile->update(['volunteer_progress' => 0, 'paid_used' => 0]);

        while ($karma->balanceFor($participant->fresh()) < $unlock->karmaUnlockCost()) {
            $karma->earn($participant, KarmaSource::REFERRAL_SUCCESS);
        }

        $study = $this->study(['incentive_type' => IncentiveType::CASH]);

        $this->actingAs($participant)
            ->getJson("/api/v1/studies/{$study->id}/apply-status")
            ->assertStatus(200)
            ->assertJsonPath('data.can_apply', true)
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.paid.route', 'karma')
            ->assertJsonPath(
                'data.label',
                'Spend ' . $unlock->karmaUnlockCost() . ' Karma & Apply'
            );
    }

    private function skipWithoutPaidApi(): void
    {
        if (! method_exists(app(FreeToPaidUnlockService::class), 'eligibility')) {
            $this->markTestSkipped(
                'Member 3 paid-application API not on this branch — merge feature/payment-escrow.'
            );
        }
    }

    public function test_a_study_that_is_not_open_cannot_be_applied_to(): void
    {
        $participant = $this->participant();

        foreach ([StudyStatus::CLOSED, StudyStatus::PAUSED, StudyStatus::CANCELLED, StudyStatus::DRAFT] as $status) {
            $study = $this->study(['status' => $status]);

            $this->assertSame(
                'not_open',
                $this->service()->blockingReason($participant, $study),
                $status->value . ' should not accept applications'
            );

            $this->actingAs($participant)
                ->postJson("/api/v1/studies/{$study->id}/apply")
                ->assertStatus(422);
        }

        $this->assertDatabaseCount('study_participations', 0);
    }

    public function test_a_past_deadline_cannot_be_applied_to(): void
    {
        $participant = $this->participant();
        $study       = $this->study(['deadline' => now()->subDay()->toDateString()]);

        $this->assertSame('deadline_passed', $this->service()->blockingReason($participant, $study));

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/apply")
            ->assertStatus(422);
    }

    public function test_a_full_study_cannot_be_applied_to(): void
    {
        $study = $this->study(['slots' => 1]);

        // One person already committed — that is the single seat gone.
        $confirmed = $this->participant('taken@trybe.test');
        StudyParticipation::create([
            'study_id'       => $study->id,
            'participant_id' => $confirmed->id,
            'stage'          => PipelineStage::CONFIRMED,
        ]);

        $participant = $this->participant();

        $this->assertSame('full', $this->service()->blockingReason($participant, $study));

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/apply")
            ->assertStatus(422);
    }

    /**
     * An application is a request, not a booking.
     *
     * If `applied` rows consumed seats, ten applicants would close a
     * five-seat study before the researcher had read one of them.
     */
    public function test_an_applied_row_does_not_consume_a_seat(): void
    {
        $study = $this->study(['slots' => 2]);

        foreach (['a@trybe.test', 'b@trybe.test', 'c@trybe.test'] as $email) {
            $this->actingAs($this->participant($email))
                ->postJson("/api/v1/studies/{$study->id}/apply")
                ->assertStatus(201);
        }

        $this->assertSame(3, StudyParticipation::where('study_id', $study->id)->count());
        $this->assertSame(2, $this->service()->seatsRemaining($study->fresh()));
    }

    public function test_a_researcher_cannot_apply(): void
    {
        $researcher = User::create([
            'name' => 'Dr Anisa', 'email' => 'anisa@trybe.test',
            'password' => bcrypt('password'), 'role' => UserRole::RESEARCHER->value,
            'verification_status' => 'verified',
        ]);

        $study = $this->study();

        $this->actingAs($researcher)
            ->postJson("/api/v1/studies/{$study->id}/apply")
            ->assertStatus(403);
    }

    /**
     * Somebody who accepted an invitation never "applied", so telling them
     * they already did reads like a bug. Different reason, different wording.
     */
    public function test_an_existing_participation_blocks_a_later_apply(): void
    {
        $participant = $this->participant();
        $study       = $this->study();

        StudyParticipation::create([
            'study_id'       => $study->id,
            'participant_id' => $participant->id,
            'stage'          => PipelineStage::CONFIRMED,
        ]);

        $this->assertSame(
            'already_participating',
            $this->service()->blockingReason($participant, $study)
        );

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/apply")
            ->assertStatus(409);
    }

    // =================================================================
    // The handoff
    // =================================================================

    /**
     * The actual interface to Member 2's pipeline: her endpoint reads
     * study_participations by stage, so an application must show up there
     * with no change from her.
     */
    public function test_the_application_appears_in_the_pipeline_query(): void
    {
        $participant = $this->participant();
        $study       = $this->study();

        $this->actingAs($participant)->postJson("/api/v1/studies/{$study->id}/apply")->assertStatus(201);

        $rows = StudyParticipation::where('study_id', $study->id)
            ->where('stage', PipelineStage::APPLIED)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame($participant->id, $rows->first()->participant_id);
    }

    // =================================================================
    // Withdraw
    // =================================================================

    public function test_withdraw_only_works_while_still_applied(): void
    {
        $participant = $this->participant();
        $study       = $this->study();

        $this->actingAs($participant)->postJson("/api/v1/studies/{$study->id}/apply")->assertStatus(201);

        $this->actingAs($participant)
            ->deleteJson("/api/v1/studies/{$study->id}/apply")
            ->assertStatus(200);

        $this->assertDatabaseCount('study_participations', 0);

        // Re-apply, then let the researcher move it on.
        $this->actingAs($participant)->postJson("/api/v1/studies/{$study->id}/apply");

        StudyParticipation::where('study_id', $study->id)
            ->where('participant_id', $participant->id)
            ->update(['stage' => PipelineStage::CONFIRMED->value]);

        $this->actingAs($participant)
            ->deleteJson("/api/v1/studies/{$study->id}/apply")
            ->assertStatus(409);

        $this->assertSame(1, StudyParticipation::where('study_id', $study->id)->count());
    }

    // =================================================================
    // The button payload
    // =================================================================

    public function test_apply_status_reports_the_label_and_reason(): void
    {
        $participant = $this->participant();
        $paid        = $this->study(['incentive_type' => IncentiveType::CASH]);

        /*
        | An ineligible participant is refused on a paid study, and the LABEL
        | explains why. The exact reason depends on whether Member 3's rule is
        | merged — asserted as a set for the same reason as the test above.
        */
        $response = $this->actingAs($participant)
            ->getJson("/api/v1/studies/{$paid->id}/apply-status")
            ->assertStatus(200)
            ->assertJsonPath('data.can_apply', false)
            ->assertJsonPath('data.is_paid', true);

        $this->assertContains(
            $response->json('data.reason'),
            ['paid_not_yet_available', 'paid_locked', 'paid_no_slots']
        );

        $this->assertSame(
            StudyApplicationService::REASON_LABEL[$response->json('data.reason')],
            $response->json('data.label')
        );

        $volunteer = $this->study();

        $this->actingAs($participant)
            ->getJson("/api/v1/studies/{$volunteer->id}/apply-status")
            ->assertStatus(200)
            ->assertJsonPath('data.can_apply', true)
            ->assertJsonPath('data.label', 'Apply');
    }

    public function test_my_applications_lists_only_my_own(): void
    {
        $mine   = $this->participant();
        $theirs = $this->participant('other@trybe.test');
        $study  = $this->study();

        $this->actingAs($mine)->postJson("/api/v1/studies/{$study->id}/apply");
        $this->actingAs($theirs)->postJson("/api/v1/studies/{$study->id}/apply");

        $this->actingAs($mine)
            ->getJson('/api/v1/participants/me/applications')
            ->assertStatus(200)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.applications.0.study_id', $study->id);
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function service(): StudyApplicationService
    {
        return app(StudyApplicationService::class);
    }

    private function participant(string $email = 'sadia@trybe.test'): User
    {
        $user = User::create([
            'name'                => 'Sadia Rahman',
            'email'               => $email,
            'password'            => bcrypt('password'),
            'role'                => UserRole::PARTICIPANT->value,
            'verification_status' => 'unverified',
        ]);

        ParticipantProfile::create([
            'user_id'                 => $user->id,
            'age'                     => 24,
            'skills'                  => 'structured interview',
            'completed_studies_count' => 0,
        ]);

        return $user;
    }

    private function study(array $overrides = []): Study
    {
        $researcher = User::firstOrCreate(
            ['email' => 'researcher@trybe.test'],
            [
                'name' => 'Dr Karim', 'password' => bcrypt('password'),
                'role' => UserRole::RESEARCHER->value, 'verification_status' => 'verified',
            ]
        );

        return Study::create(array_merge([
            'researcher_id'    => $researcher->id,
            'title'            => 'Two-week sleep diary',
            'description'      => 'A volunteer diary study.',
            'category'         => 'Health',
            'method'           => 'online',
            'duration_minutes' => 15,
            'incentive_type'   => IncentiveType::VOLUNTEER,
            'compensation_amount' => 0,
            'slots'            => 10,
            'status'           => StudyStatus::OPEN,
            'deadline'         => now()->addDays(14)->toDateString(),
        ], $overrides));
    }
}
