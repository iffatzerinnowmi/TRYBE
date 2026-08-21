<?php

namespace Tests\Feature;

use App\Enums\CompletionClaimStatus;
use App\Enums\CredentialLevel;
use App\Enums\IncentiveType;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\KarmaTransaction;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\StudyCompletionClaim;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\StudyCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Completing a study — participant side  (Member 4)
 *
 * The two tests that matter most are
 *   test_a_claim_never_changes_the_pipeline_stage
 *   test_a_claim_awards_no_karma_and_no_credential
 *
 * They encode the reason this feature is a claim rather than a completion.
 * If somebody later "simplifies" it by setting the stage directly, those two
 * fail loudly rather than silently handing participants free credentials,
 * karma and paid-study access.
 */
class StudyCompletionTest extends TestCase
{
    use RefreshDatabase;

    // =================================================================
    // The happy path
    // =================================================================

    public function test_a_confirmed_participant_can_claim_completion(): void
    {
        [$participant, $study] = $this->confirmedOn();

        $this->assertNull($this->service()->blockingReason($participant, $study));

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/completion", ['note' => 'All done.'])
            ->assertStatus(201)
            ->assertJsonPath('data.claim.status', CompletionClaimStatus::SUBMITTED->value);

        $this->assertDatabaseHas('study_completion_claims', [
            'study_id'       => $study->id,
            'participant_id' => $participant->id,
            'status'         => CompletionClaimStatus::SUBMITTED->value,
            'note'           => 'All done.',
        ]);
    }

    /**
     * THE test. A claim is a statement, not a completion.
     */
    public function test_a_claim_never_changes_the_pipeline_stage(): void
    {
        [$participant, $study] = $this->confirmedOn(PipelineStage::SCHEDULED);

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/completion")
            ->assertStatus(201);

        $this->assertDatabaseHas('study_participations', [
            'study_id'       => $study->id,
            'participant_id' => $participant->id,
            'stage'          => PipelineStage::SCHEDULED->value,
        ]);

        $this->assertDatabaseMissing('study_participations', [
            'study_id' => $study->id,
            'stage'    => PipelineStage::COMPLETED->value,
        ]);
    }

    /**
     * The consequence of the test above, asserted directly: none of the five
     * consumers of `completed` are triggered by a claim.
     */
    public function test_a_claim_awards_no_karma_and_no_credential(): void
    {
        [$participant, $study] = $this->confirmedOn();

        $levelBefore = $participant->participantProfile->credential_level;

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/completion")
            ->assertStatus(201);

        $this->assertSame(
            0,
            (int) KarmaTransaction::where('user_id', $participant->id)->sum('amount')
        );

        $this->assertSame(
            $levelBefore,
            $participant->participantProfile->fresh()->credential_level
        );
    }

    // =================================================================
    // Guards
    // =================================================================

    public function test_claiming_twice_is_rejected(): void
    {
        [$participant, $study] = $this->confirmedOn();

        $this->actingAs($participant)->postJson("/api/v1/studies/{$study->id}/completion")->assertStatus(201);
        $this->actingAs($participant)->postJson("/api/v1/studies/{$study->id}/completion")->assertStatus(409);

        $this->assertSame(1, StudyCompletionClaim::where('study_id', $study->id)->count());
    }

    public function test_an_applicant_cannot_claim_completion(): void
    {
        [$participant, $study] = $this->confirmedOn(PipelineStage::APPLIED);

        $this->assertSame('not_started', $this->service()->blockingReason($participant, $study));

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/completion")
            ->assertStatus(422);

        $this->assertDatabaseCount('study_completion_claims', 0);
    }

    public function test_someone_not_in_the_study_cannot_claim(): void
    {
        $participant = $this->participant('outsider@trybe.test');
        $study       = $this->study();

        $this->assertSame('not_in_study', $this->service()->blockingReason($participant, $study));

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/completion")
            ->assertStatus(403);
    }

    public function test_an_already_completed_study_cannot_be_claimed(): void
    {
        [$participant, $study] = $this->confirmedOn(PipelineStage::COMPLETED);

        $this->assertSame('already_completed', $this->service()->blockingReason($participant, $study));

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/completion")
            ->assertStatus(409);
    }

    public function test_a_researcher_cannot_claim(): void
    {
        [$participant, $study] = $this->confirmedOn();

        $this->actingAs($study->researcher)
            ->postJson("/api/v1/studies/{$study->id}/completion")
            ->assertStatus(403);
    }

    // =================================================================
    // Behaviour worth pinning down
    // =================================================================

    public function test_a_rejected_claim_can_be_resubmitted(): void
    {
        [$participant, $study] = $this->confirmedOn();
        $service = $this->service();

        $claim = $service->claim($participant, $study, 'first try');
        $service->markReviewed($claim, $study->researcher, CompletionClaimStatus::REJECTED);

        $this->assertNull($service->blockingReason($participant->fresh(), $study));

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/completion", ['note' => 'second try'])
            ->assertStatus(201);

        // Same row, reused — not a second claim.
        $this->assertSame(1, StudyCompletionClaim::where('study_id', $study->id)->count());

        $fresh = StudyCompletionClaim::where('study_id', $study->id)->first();

        $this->assertSame(CompletionClaimStatus::SUBMITTED, $fresh->status);
        $this->assertSame('second try', $fresh->note);

        // Re-claiming clears the old review, or it would look as though the
        // researcher had already seen this new statement.
        $this->assertNull($fresh->reviewed_at);
    }

    /**
     * The URL is frozen on the row, so changing the configured form later
     * never rewrites what the participant was actually shown.
     */
    public function test_the_form_url_is_frozen_on_the_claim(): void
    {
        [$participant, $study] = $this->confirmedOn();

        config(['platform.completion.form_url' => 'https://forms.gle/ORIGINAL']);

        $this->service()->claim($participant, $study);

        config(['platform.completion.form_url' => 'https://forms.gle/CHANGED']);

        $this->assertSame(
            'https://forms.gle/ORIGINAL',
            StudyCompletionClaim::where('study_id', $study->id)->value('form_url')
        );
    }

    /**
     * Recording that they followed the link is evidence, not a gate.
     */
    public function test_opened_form_at_is_not_required_before_submitting(): void
    {
        [$participant, $study] = $this->confirmedOn();

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/completion")
            ->assertStatus(201);

        $this->assertNull(
            StudyCompletionClaim::where('study_id', $study->id)->value('opened_form_at')
        );
    }

    public function test_marking_the_form_opened_stamps_the_claim(): void
    {
        [$participant, $study] = $this->confirmedOn();

        $this->actingAs($participant)
            ->patchJson("/api/v1/studies/{$study->id}/completion/opened")
            ->assertStatus(204);

        $this->assertNotNull(
            StudyCompletionClaim::where('study_id', $study->id)->value('opened_form_at')
        );
    }

    public function test_the_researcher_is_notified(): void
    {
        [$participant, $study] = $this->confirmedOn();

        $this->actingAs($participant)
            ->postJson("/api/v1/studies/{$study->id}/completion")
            ->assertStatus(201);

        $this->assertSame(
            1,
            UserNotification::where('user_id', $study->researcher_id)->count()
        );
    }

    public function test_the_researcher_can_list_claims_on_their_own_study(): void
    {
        [$participant, $study] = $this->confirmedOn();

        $this->service()->claim($participant, $study, 'done');

        $this->actingAs($study->researcher)
            ->getJson("/api/v1/studies/{$study->id}/completion-claims")
            ->assertStatus(200)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.claims.0.participant_id', $participant->id);
    }

    public function test_another_researcher_cannot_list_those_claims(): void
    {
        [$participant, $study] = $this->confirmedOn();
        $this->service()->claim($participant, $study);

        $stranger = User::create([
            'name' => 'Dr Stranger', 'email' => 'stranger@trybe.test',
            'password' => bcrypt('password'), 'role' => UserRole::RESEARCHER->value,
            'verification_status' => 'verified',
        ]);

        $this->actingAs($stranger)
            ->getJson("/api/v1/studies/{$study->id}/completion-claims")
            ->assertStatus(403);
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function service(): StudyCompletionService
    {
        return app(StudyCompletionService::class);
    }

    /** @return array{0: User, 1: Study} */
    private function confirmedOn(PipelineStage $stage = PipelineStage::CONFIRMED): array
    {
        $participant = $this->participant();
        $study       = $this->study();

        StudyParticipation::create([
            'study_id'       => $study->id,
            'participant_id' => $participant->id,
            'stage'          => $stage,
        ]);

        return [$participant, $study->fresh()];
    }

    private function participant(string $email = 'sadia@trybe.test'): User
    {
        $user = User::create([
            'name' => 'Sadia Rahman', 'email' => $email,
            'password' => bcrypt('password'), 'role' => UserRole::PARTICIPANT->value,
            'verification_status' => 'unverified',
        ]);

        ParticipantProfile::create([
            'user_id'                 => $user->id,
            'age'                     => 24,
            'skills'                  => 'structured interview',
            'completed_studies_count' => 0,
            'credential_level'        => CredentialLevel::NONE->value,
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
            'researcher_id'       => $researcher->id,
            'title'               => 'Two-week sleep diary',
            'description'         => 'A volunteer diary study.',
            'category'            => 'Health',
            'method'              => 'online',
            'duration_minutes'    => 15,
            'incentive_type'      => IncentiveType::VOLUNTEER,
            'compensation_amount' => 0,
            'slots'               => 10,
            'status'              => StudyStatus::OPEN,
            'deadline'            => now()->addDays(14)->toDateString(),
        ], $overrides));
    }
}
