<?php

namespace Tests\Feature;

use App\Enums\CredentialLevel;
use App\Enums\IncentiveType;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Team convention §4: "No controller passes data to a view."
 *
 * This asserts it mechanically for the pages this feature owns, so the rule
 * cannot quietly rot back in. Cheap to run, and it demonstrates the rule to
 * an examiner without them having to read four controllers.
 *
 * Route models ($study, $participant) are allowed through: they tell the
 * page WHICH record to ask the API about. That is identity, not feature
 * data — the same category as data-user-id on the reliability page.
 */
class ApiDrivenPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_invitations_page_passes_no_data_to_its_view(): void
    {
        $participant = $this->participant();

        $response = $this->actingAs($participant)->get('/participant/invitations');

        $response->assertOk();
        $this->assertSame([], $this->viewData($response));
    }

    public function test_the_candidate_profile_page_passes_only_route_models(): void
    {
        $researcher  = $this->researcher();
        $participant = $this->participant();
        $study       = $this->study($researcher);

        $response = $this->actingAs($researcher)
            ->get("/researcher/studies/{$study->id}/participants/{$participant->id}");

        $response->assertOk();

        // Only the two route models — no $profile, $match or $participations.
        $this->assertSame(['study', 'participant'], array_keys($this->viewData($response)));
    }

    /** The three web POST routes this feature used to have are gone. */
    public function test_the_old_invitation_post_routes_no_longer_exist(): void
    {
        $names = collect(app('router')->getRoutes())->map(fn ($r) => $r->getName())->filter();

        $this->assertNotContains('researcher.studies.invite', $names);
        $this->assertNotContains('participant.studies.invitation.accept', $names);
        $this->assertNotContains('participant.studies.invitation.decline', $names);
    }

    // -----------------------------------------------------------------

    /** View data minus the keys Laravel and the layout inject themselves. */
    private function viewData($response): array
    {
        $data = $response->original->getData();

        unset($data['errors'], $data['obLevel'], $data['__env'], $data['app']);

        return $data;
    }

    private function study(User $researcher): Study
    {
        return Study::create([
            'researcher_id'       => $researcher->id,
            'title'               => 'View data test study',
            'category'            => 'survey',
            'method'              => 'online',
            'duration_minutes'    => 30,
            'incentive_type'      => IncentiveType::VOLUNTEER->value,
            'compensation_amount' => 0,
            'slots'               => 5,
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
            'user_id'          => $user->id,
            'age'              => 25,
            'credential_level' => CredentialLevel::NONE->value,
        ]);

        return $user->fresh();
    }
}
