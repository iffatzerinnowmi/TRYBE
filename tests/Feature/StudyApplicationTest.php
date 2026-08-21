<?php

namespace Tests\Feature;

use App\Enums\IncentiveType;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
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
     * The button for a paid study renders disabled, but that is a courtesy.
     * This asserts the server refuses even when a client posts directly.
     */
    public function test_a_paid_study_is_rejected_server_side(): void
    {
        $participant = $this->participant();
        $study       = $this->study(['incentive_type' => IncentiveType::CASH, 'compensation_amount' => 500]);

        $this->assertSame(
            'paid_not_yet_available',
            $this->service()->blockingReason($participant, $study)
        );

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/apply")
            ->assertStatus(422);

        $this->assertDatabaseCount('study_participations', 0);
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

        $this->actingAs($participant)
            ->getJson("/api/v1/studies/{$paid->id}/apply-status")
            ->assertStatus(200)
            ->assertJsonPath('data.can_apply', false)
            ->assertJsonPath('data.reason', 'paid_not_yet_available')
            ->assertJsonPath('data.label', 'Paid — coming soon');

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
