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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudyDetailViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_researcher_can_see_current_participants_on_study_detail_page(): void
    {
        $researcher = $this->makeResearcher();
        $study = $this->makeStudy($researcher);
        $participant = $this->makeParticipant('Accepted Participant');

        StudyParticipation::create([
            'study_id' => $study->id,
            'participant_id' => $participant->id,
            'stage' => PipelineStage::CONFIRMED->value,
        ]);

        $response = $this->actingAs($researcher)->get(route('studies.show', $study));

        $response->assertOk();
        $response->assertSee('Current participants');
        $response->assertSee('Accepted Participant');
    }

    private function makeResearcher(): User
    {
        return User::create([
            'name' => 'Researcher ' . uniqid(),
            'email' => uniqid('researcher_', true) . '@example.com',
            'password' => 'password',
            'role' => UserRole::RESEARCHER->value,
            'location' => 'Dhaka, Bangladesh',
            'verification_status' => 'verified',
        ]);
    }

    private function makeStudy(User $researcher): Study
    {
        return Study::create([
            'researcher_id' => $researcher->id,
            'title' => 'Memory recall survey for students',
            'category' => 'survey',
            'method' => 'online',
            'duration_minutes' => 30,
            'incentive_type' => IncentiveType::VOLUNTEER->value,
            'compensation_amount' => 0,
            'slots' => 10,
            'status' => StudyStatus::OPEN->value,
            'participants_count' => 0,
        ]);
    }

    private function makeParticipant(string $name): User
    {
        $user = User::create([
            'name' => $name,
            'email' => uniqid('participant_', true) . '@example.com',
            'password' => 'password',
            'role' => UserRole::PARTICIPANT->value,
            'location' => 'Dhaka, Bangladesh',
            'verification_status' => 'verified',
        ]);

        ParticipantProfile::create([
            'user_id' => $user->id,
            'age' => 24,
            'gender' => 'Prefer not to say',
            'occupation' => 'Student',
            'health_background' => null,
            'interests' => 'Research, surveys, psychology',
            'skills' => 'Survey responses, interviews, Bangla',
            'linkedin' => null,
            'github' => null,
            'rel_attendance' => 90,
            'rel_completion' => 90,
            'rel_reviews' => 90,
            'reliability_score' => 90,
            'completed_studies_count' => 3,
            'credential_level' => 'none',
            'current_streak_weeks' => 2,
            'longest_streak_weeks' => 2,
            'last_active_week' => now(),
            'endorsement_count' => 0,
            'is_verified_participant' => true,
        ]);

        return $user;
    }
}
