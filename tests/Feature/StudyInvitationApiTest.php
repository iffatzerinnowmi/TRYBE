<?php

namespace Tests\Feature;

use App\Enums\CredentialLevel;
use App\Enums\IncentiveType;
use App\Enums\InvitationStatus;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\NotificationPreference;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\StudyInvitation;
use App\Models\StudyMatchCriteria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The invitation REST API and its state machine.
 */
class StudyInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_researcher_can_invite_a_strong_match(): void
    {
        [$researcher, $study] = $this->openStudy();
        $participant = $this->strongCandidate();

        $this->actingAs($researcher)
            ->postJson("/api/v1/studies/{$study->id}/invitations", [
                'participant_id' => $participant->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.participant_id', $participant->id);

        $this->assertDatabaseHas('study_invitations', [
            'study_id'       => $study->id,
            'participant_id' => $participant->id,
            'status'         => InvitationStatus::PENDING->value,
        ]);
    }

    /**
     * The bug the old implementation had: invitation state WAS the
     * notification row, so a participant with 'studies' notifications
     * switched off could not be invited at all. The invitation is now
     * authoritative and the notification is best-effort.
     */
    public function test_a_participant_with_notifications_off_still_gets_the_invitation(): void
    {
        [$researcher, $study] = $this->openStudy();
        $participant = $this->strongCandidate();

        NotificationPreference::updateOrCreate(
            ['user_id' => $participant->id],
            ['notify_studies' => false]
        );

        $this->actingAs($researcher)
            ->postJson("/api/v1/studies/{$study->id}/invitations", [
                'participant_id' => $participant->id,
            ])
            ->assertStatus(201);

        $invitation = StudyInvitation::firstWhere('participant_id', $participant->id);

        $this->assertNotNull($invitation, 'The invitation must exist regardless of notification settings.');
        $this->assertNull($invitation->notification_id, 'No notification should have been created.');
    }

    /** unique(study_id, participant_id) makes a double invite impossible. */
    public function test_inviting_the_same_participant_twice_returns_409(): void
    {
        [$researcher, $study] = $this->openStudy();
        $participant = $this->strongCandidate();

        $payload = ['participant_id' => $participant->id];

        $this->actingAs($researcher)
            ->postJson("/api/v1/studies/{$study->id}/invitations", $payload)
            ->assertStatus(201);

        $this->actingAs($researcher)
            ->postJson("/api/v1/studies/{$study->id}/invitations", $payload)
            ->assertStatus(409);

        $this->assertSame(1, StudyInvitation::where('participant_id', $participant->id)->count());
    }

    public function test_a_researcher_cannot_invite_to_someone_elses_study(): void
    {
        [, $study]   = $this->openStudy();
        $intruder    = $this->researcher();
        $participant = $this->strongCandidate();

        $this->actingAs($intruder)
            ->postJson("/api/v1/studies/{$study->id}/invitations", [
                'participant_id' => $participant->id,
            ])
            ->assertStatus(403);
    }

    public function test_a_weak_match_cannot_be_invited(): void
    {
        [$researcher, $study] = $this->openStudy();

        // Far outside the age band, no skills, no reliability.
        $weak = $this->participant('Weak Match', [
            'age'               => 70,
            'skills'            => '',
            'reliability_score' => 0,
            'last_active_week'  => null,
        ]);

        $this->actingAs($researcher)
            ->postJson("/api/v1/studies/{$study->id}/invitations", [
                'participant_id' => $weak->id,
            ])
            ->assertStatus(422);
    }

    public function test_a_participant_can_accept_and_the_invitation_records_it(): void
    {
        [$researcher, $study] = $this->openStudy();
        $participant = $this->strongCandidate();

        $invitation = $this->invite($researcher, $study, $participant);

        $this->actingAs($participant)
            ->patchJson("/api/v1/invitations/{$invitation->id}", ['status' => 'accepted'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'accepted');

        $this->assertSame(InvitationStatus::ACCEPTED, $invitation->fresh()->status);
        $this->assertNotNull($invitation->fresh()->responded_at);
    }

    /** A stale tab must not be able to answer twice. */
    public function test_responding_twice_returns_409(): void
    {
        [$researcher, $study] = $this->openStudy();
        $participant = $this->strongCandidate();
        $invitation  = $this->invite($researcher, $study, $participant);

        $this->actingAs($participant)
            ->patchJson("/api/v1/invitations/{$invitation->id}", ['status' => 'accepted'])
            ->assertStatus(200);

        $this->actingAs($participant)
            ->patchJson("/api/v1/invitations/{$invitation->id}", ['status' => 'declined'])
            ->assertStatus(409);

        $this->assertSame(InvitationStatus::ACCEPTED, $invitation->fresh()->status);
    }

    public function test_a_participant_cannot_answer_somebody_elses_invitation(): void
    {
        [$researcher, $study] = $this->openStudy();
        $participant = $this->strongCandidate();
        $stranger    = $this->strongCandidate();
        $invitation  = $this->invite($researcher, $study, $participant);

        $this->actingAs($stranger)
            ->patchJson("/api/v1/invitations/{$invitation->id}", ['status' => 'accepted'])
            ->assertStatus(403);
    }

    /**
     * Accepting removes the candidate from the researcher's list, and the
     * next best takes their place — the behaviour the feature describes.
     */
    public function test_accepting_frees_the_slot_for_the_next_best_candidate(): void
    {
        [$researcher, $study] = $this->openStudy();

        $first  = $this->strongCandidate(['reliability_score' => 95]);
        $second = $this->strongCandidate(['reliability_score' => 85]);

        $before = $this->actingAs($researcher)
            ->getJson("/api/v1/studies/{$study->id}/candidates?limit=1")
            ->json('data.candidates');

        $this->assertSame($first->id, $before[0]['user_id']);

        $invitation = $this->invite($researcher, $study, $first);

        $this->actingAs($first)
            ->patchJson("/api/v1/invitations/{$invitation->id}", ['status' => 'accepted'])
            ->assertStatus(200);

        $after = $this->actingAs($researcher)
            ->getJson("/api/v1/studies/{$study->id}/candidates?limit=1")
            ->json('data.candidates');

        $this->assertSame($second->id, $after[0]['user_id']);
    }

    public function test_a_researcher_can_withdraw_a_pending_invitation(): void
    {
        [$researcher, $study] = $this->openStudy();
        $participant = $this->strongCandidate();
        $invitation  = $this->invite($researcher, $study, $participant);

        $this->actingAs($researcher)
            ->deleteJson("/api/v1/invitations/{$invitation->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'withdrawn');
    }

    public function test_a_participant_only_sees_their_own_invitations(): void
    {
        [$researcher, $study] = $this->openStudy();
        $mine    = $this->strongCandidate();
        $theirs  = $this->strongCandidate();

        $this->invite($researcher, $study, $mine);
        $this->invite($researcher, $study, $theirs);

        $ids = $this->actingAs($mine)
            ->getJson('/api/v1/invitations')
            ->assertStatus(200)
            ->json('data.invitations.*.participant_id');

        $this->assertSame([$mine->id], array_unique($ids));
    }

    // -----------------------------------------------------------------

    private function invite(User $researcher, Study $study, User $participant): StudyInvitation
    {
        $this->actingAs($researcher)
            ->postJson("/api/v1/studies/{$study->id}/invitations", [
                'participant_id' => $participant->id,
            ])
            ->assertStatus(201);

        return StudyInvitation::where('study_id', $study->id)
            ->where('participant_id', $participant->id)
            ->firstOrFail();
    }

    /** @return array{0: User, 1: Study} */
    private function openStudy(): array
    {
        $researcher = $this->researcher();

        $study = Study::create([
            'researcher_id'       => $researcher->id,
            'title'               => 'Invitation test study',
            'category'            => 'survey',
            'method'              => 'online',
            'duration_minutes'    => 30,
            'incentive_type'      => IncentiveType::VOLUNTEER->value,
            'compensation_amount' => 0,
            'slots'               => 10,
            'status'              => StudyStatus::OPEN->value,
            'participants_count'  => 0,
        ]);

        StudyMatchCriteria::create([
            'study_id'          => $study->id,
            'age_min'           => 18,
            'age_max'           => 40,
            'credential_min'    => CredentialLevel::NONE->value,
            'required_skills'   => [],
            'availability_days' => 30,
        ]);

        return [$researcher, $study];
    }

    private function researcher(): User
    {
        return User::create([
            'name'                => 'Researcher ' . uniqid(),
            'email'               => uniqid('researcher_', true) . '@example.com',
            'password'            => 'password',
            'role'                => UserRole::RESEARCHER->value,
            'location'            => 'Dhaka, Bangladesh',
            'verification_status' => 'verified',
        ]);
    }

    private function strongCandidate(array $profile = []): User
    {
        return $this->participant('Strong ' . uniqid(), array_merge([
            'age'               => 25,
            'skills'            => 'Survey, Interviews',
            'reliability_score' => 90,
            'last_active_week'  => now()->subDays(2),
        ], $profile));
    }

    private function participant(string $name, array $profile = []): User
    {
        $user = User::create([
            'name'                => $name,
            'email'               => uniqid('participant_', true) . '@example.com',
            'password'            => 'password',
            'role'                => UserRole::PARTICIPANT->value,
            'location'            => 'Dhaka, Bangladesh',
            'verification_status' => 'unverified',
        ]);

        ParticipantProfile::create(array_merge([
            'user_id'                 => $user->id,
            'age'                     => 25,
            'skills'                  => 'Survey',
            'reliability_score'       => 80,
            'completed_studies_count' => 0,
            'credential_level'        => CredentialLevel::NONE->value,
            'last_active_week'        => now()->subDays(3),
        ], $profile));

        return $user->fresh();
    }
}
