<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\CompetitionSave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Competition & Hackathon Board  (Member 4)
 *
 * The two tests that carry the design:
 *
 *   test_a_non_https_url_is_rejected      — the only link check we make, and
 *                                           it is about safety rather than
 *                                           editorial judgement
 *   test_a_participant_cannot_post        — posting is curated, which is what
 *                                           replaces link moderation
 */
class CompetitionBoardTest extends TestCase
{
    use RefreshDatabase;

    // =================================================================
    // Who may post
    // =================================================================

    public function test_a_verified_researcher_can_post_a_listing(): void
    {
        $this->actingAs($this->researcher())
            ->postJson('/api/v1/competitions', $this->payload())
            ->assertStatus(201);

        $this->assertDatabaseHas('competitions', ['name' => 'Test Hackathon']);
    }

    public function test_a_verified_organization_can_post_a_listing(): void
    {
        $org = $this->user(UserRole::ORGANIZATION, 'org@trybe.test', 'verified');

        $this->actingAs($org)
            ->postJson('/api/v1/competitions', $this->payload())
            ->assertStatus(201);
    }

    public function test_an_admin_can_post_without_being_verified(): void
    {
        $admin = $this->user(UserRole::ADMIN, 'admin@trybe.test', 'unverified');

        $this->actingAs($admin)
            ->postJson('/api/v1/competitions', $this->payload())
            ->assertStatus(201);
    }

    /**
     * Regression: verification_status is CAST to VerificationStatus, so
     * comparing it to the string 'verified' is always false. That bug hid the
     * posting panel from every verified researcher, silently. The verified
     * cases above are what catch it; this is the other side of the boundary.
     */
    public function test_an_unverified_researcher_cannot_post(): void
    {
        $pending = $this->user(UserRole::RESEARCHER, 'pending@trybe.test', 'pending');

        $this->actingAs($pending)
            ->postJson('/api/v1/competitions', $this->payload())
            ->assertStatus(403);

        $this->assertDatabaseCount('competitions', 0);
    }

    /** Posting is curated — that is what stands in for link moderation. */
    public function test_a_participant_cannot_post(): void
    {
        $this->actingAs($this->participant())
            ->postJson('/api/v1/competitions', $this->payload())
            ->assertStatus(403);

        $this->assertDatabaseCount('competitions', 0);
    }

    // =================================================================
    // Links
    // =================================================================

    /**
     * We do not check that a link points at a real competition. We DO check
     * the scheme: a stored `javascript:` URL executes in the session of
     * whoever clicks it, which is stored XSS rather than a bad link.
     */
    public function test_a_javascript_url_is_rejected(): void
    {
        $this->actingAs($this->researcher())
            ->postJson('/api/v1/competitions', $this->payload([
                'external_url' => 'javascript:alert(document.cookie)',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('external_url');
    }

    public function test_a_non_https_url_is_rejected(): void
    {
        $this->actingAs($this->researcher())
            ->postJson('/api/v1/competitions', $this->payload([
                'external_url' => 'http://devpost.com/insecure',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('external_url');
    }

    // =================================================================
    // The board
    // =================================================================

    public function test_the_board_hides_competitions_past_their_deadline(): void
    {
        $this->competition(['name' => 'Still open', 'deadline' => now()->addDays(5)->toDateString()]);
        $this->competition(['name' => 'Long gone',  'deadline' => now()->subDay()->toDateString()]);

        $response = $this->actingAs($this->participant())
            ->getJson('/api/v1/competitions')
            ->assertStatus(200);

        $names = collect($response->json('data.competitions'))->pluck('name');

        $this->assertTrue($names->contains('Still open'));
        $this->assertFalse($names->contains('Long gone'));
    }

    /**
     * A listing with no deadline cannot be known to have closed, so hiding it
     * because a field is blank would silently drop it.
     */
    public function test_a_competition_with_no_deadline_still_appears(): void
    {
        $this->competition(['name' => 'Rolling', 'deadline' => null]);

        $response = $this->actingAs($this->participant())->getJson('/api/v1/competitions');

        $this->assertSame('Rolling', $response->json('data.competitions.0.name'));
    }

    public function test_the_board_is_ordered_newest_first(): void
    {
        $old = $this->competition(['name' => 'Older']);
        $old->created_at = now()->subDays(10);
        $old->save();

        $new = $this->competition(['name' => 'Newer']);
        $new->created_at = now();
        $new->save();

        $names = collect(
            $this->actingAs($this->participant())
                ->getJson('/api/v1/competitions')
                ->json('data.competitions')
        )->pluck('name')->all();

        $this->assertSame(['Newer', 'Older'], $names);
    }

    /** A poster still sees their own closed listings. */
    public function test_a_poster_sees_their_own_closed_listings(): void
    {
        $researcher = $this->researcher();

        $this->competition([
            'name'      => 'Long gone',
            'deadline'  => now()->subDay()->toDateString(),
            'posted_by' => $researcher->id,
        ]);

        $this->actingAs($researcher)
            ->getJson('/api/v1/competitions/mine')
            ->assertStatus(200)
            ->assertJsonPath('data.count', 1);
    }

    // =================================================================
    // Saving
    // =================================================================

    public function test_a_participant_can_save_and_unsave(): void
    {
        $participant = $this->participant();
        $competition = $this->competition();

        $this->actingAs($participant)
            ->postJson("/api/v1/competitions/{$competition->id}/save")
            ->assertStatus(200)
            ->assertJsonPath('data.saved', true);

        $this->assertDatabaseCount('competition_saves', 1);

        $this->actingAs($participant)
            ->deleteJson("/api/v1/competitions/{$competition->id}/save")
            ->assertStatus(200)
            ->assertJsonPath('data.saved', false);

        $this->assertDatabaseCount('competition_saves', 0);
    }

    /** Saving twice is not an error the user should have to understand. */
    public function test_saving_twice_creates_one_row(): void
    {
        $participant = $this->participant();
        $competition = $this->competition();

        $this->actingAs($participant)->postJson("/api/v1/competitions/{$competition->id}/save");
        $this->actingAs($participant)->postJson("/api/v1/competitions/{$competition->id}/save")
            ->assertStatus(200);

        $this->assertSame(1, CompetitionSave::count());
    }

    public function test_a_researcher_cannot_save(): void
    {
        $competition = $this->competition();

        $this->actingAs($this->researcher())
            ->postJson("/api/v1/competitions/{$competition->id}/save")
            ->assertStatus(403);
    }

    /**
     * The saved page exists to answer "what closes next", so undated
     * listings must sort LAST rather than first.
     */
    public function test_saved_list_is_ordered_by_soonest_deadline_with_undated_last(): void
    {
        $participant = $this->participant();

        $far     = $this->competition(['name' => 'Far',     'deadline' => now()->addDays(30)->toDateString()]);
        $soon    = $this->competition(['name' => 'Soon',    'deadline' => now()->addDays(2)->toDateString()]);
        $undated = $this->competition(['name' => 'Undated', 'deadline' => null]);

        foreach ([$far, $soon, $undated] as $c) {
            CompetitionSave::create(['competition_id' => $c->id, 'user_id' => $participant->id]);
        }

        $names = collect(
            $this->actingAs($participant)
                ->getJson('/api/v1/participants/me/competitions')
                ->json('data.competitions')
        )->pluck('name')->all();

        $this->assertSame(['Soon', 'Far', 'Undated'], $names);
    }

    /**
     * A saved listing going past its deadline is information, not clutter.
     * Dropping it silently would look like data loss.
     */
    public function test_a_closed_competition_stays_on_the_saved_list(): void
    {
        $participant = $this->participant();
        $closed      = $this->competition(['name' => 'Gone', 'deadline' => now()->subDay()->toDateString()]);

        CompetitionSave::create(['competition_id' => $closed->id, 'user_id' => $participant->id]);

        $this->actingAs($participant)
            ->getJson('/api/v1/participants/me/competitions')
            ->assertStatus(200)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.competitions.0.closed', true);
    }

    // =================================================================
    // Looking for a team
    // =================================================================

    /**
     * Saving is private. Only ticking the box publishes your name — and it
     * is a separate call, so it can never happen as a side effect of
     * bookmarking something.
     */
    public function test_saving_does_not_publish_you_by_default(): void
    {
        $participant = $this->participant();
        $competition = $this->competition();

        $this->actingAs($participant)
            ->postJson("/api/v1/competitions/{$competition->id}/save")
            ->assertJsonPath('data.looking_for_team', false);

        $other = $this->user(UserRole::PARTICIPANT, 'other.p@trybe.test', 'unverified');

        $this->actingAs($other)
            ->getJson("/api/v1/competitions/{$competition->id}/teammates")
            ->assertStatus(200)
            ->assertJsonPath('data.count', 0);
    }

    public function test_only_people_who_opted_in_appear(): void
    {
        $looking = $this->participant();
        $quiet   = $this->user(UserRole::PARTICIPANT, 'quiet@trybe.test', 'unverified');
        $viewer  = $this->user(UserRole::PARTICIPANT, 'viewer@trybe.test', 'unverified');

        $competition = $this->competition();

        $this->actingAs($looking)
            ->postJson("/api/v1/competitions/{$competition->id}/save", ['looking_for_team' => true]);

        $this->actingAs($quiet)
            ->postJson("/api/v1/competitions/{$competition->id}/save");

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/competitions/{$competition->id}/teammates")
            ->assertStatus(200);

        $names = collect($response->json('data.people'))->pluck('name');

        $this->assertSame(1, $response->json('data.count'));
        $this->assertTrue($names->contains($looking->name));
    }

    /** Un-ticking removes you immediately — no cached "recently looking". */
    public function test_unticking_removes_you_from_the_list(): void
    {
        $participant = $this->participant();
        $viewer      = $this->user(UserRole::PARTICIPANT, 'viewer2@trybe.test', 'unverified');
        $competition = $this->competition();

        $this->actingAs($participant)
            ->postJson("/api/v1/competitions/{$competition->id}/save", ['looking_for_team' => true]);

        $this->actingAs($participant)
            ->patchJson("/api/v1/competitions/{$competition->id}/save", ['looking_for_team' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.looking_for_team', false);

        $this->actingAs($viewer)
            ->getJson("/api/v1/competitions/{$competition->id}/teammates")
            ->assertJsonPath('data.count', 0);
    }

    /** Unsaving removes the row, and therefore the signal with it. */
    public function test_unsaving_removes_the_signal(): void
    {
        $participant = $this->participant();
        $viewer      = $this->user(UserRole::PARTICIPANT, 'viewer3@trybe.test', 'unverified');
        $competition = $this->competition();

        $this->actingAs($participant)
            ->postJson("/api/v1/competitions/{$competition->id}/save", ['looking_for_team' => true]);

        $this->actingAs($participant)
            ->deleteJson("/api/v1/competitions/{$competition->id}/save");

        $this->actingAs($viewer)
            ->getJson("/api/v1/competitions/{$competition->id}/teammates")
            ->assertJsonPath('data.count', 0);
    }

    /**
     * Contact details are reciprocal.
     *
     * Without this, a participant could save every listing on the board, call
     * the teammates endpoint once each, and collect every address on the
     * platform while disclosing nothing of their own.
     */
    public function test_email_is_hidden_from_a_viewer_who_is_not_looking(): void
    {
        $looking = $this->participant();
        $lurker  = $this->user(UserRole::PARTICIPANT, 'lurker@trybe.test', 'unverified');

        $competition = $this->competition();

        $this->actingAs($looking)
            ->postJson("/api/v1/competitions/{$competition->id}/save", ['looking_for_team' => true]);

        // Saved, but did not tick the box.
        $this->actingAs($lurker)->postJson("/api/v1/competitions/{$competition->id}/save");

        $this->actingAs($lurker)
            ->getJson("/api/v1/competitions/{$competition->id}/teammates")
            ->assertStatus(200)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.you_are_listed', false)
            ->assertJsonPath('data.people.0.name', $looking->name)
            ->assertJsonPath('data.people.0.email', null);
    }

    public function test_email_is_shown_once_both_sides_are_looking(): void
    {
        $looking = $this->participant();
        $viewer  = $this->user(UserRole::PARTICIPANT, 'seeker@trybe.test', 'unverified');

        $competition = $this->competition();

        foreach ([$looking, $viewer] as $user) {
            $this->actingAs($user)
                ->postJson("/api/v1/competitions/{$competition->id}/save", ['looking_for_team' => true]);
        }

        $this->actingAs($viewer)
            ->getJson("/api/v1/competitions/{$competition->id}/teammates")
            ->assertStatus(200)
            ->assertJsonPath('data.you_are_listed', true)
            ->assertJsonPath('data.people.0.email', $looking->email);
    }

    /** Un-ticking must revoke the view as well as the listing. */
    public function test_unticking_also_stops_you_seeing_other_emails(): void
    {
        $looking = $this->participant();
        $viewer  = $this->user(UserRole::PARTICIPANT, 'exseeker@trybe.test', 'unverified');

        $competition = $this->competition();

        foreach ([$looking, $viewer] as $user) {
            $this->actingAs($user)
                ->postJson("/api/v1/competitions/{$competition->id}/save", ['looking_for_team' => true]);
        }

        $this->actingAs($viewer)
            ->patchJson("/api/v1/competitions/{$competition->id}/save", ['looking_for_team' => false]);

        $this->actingAs($viewer)
            ->getJson("/api/v1/competitions/{$competition->id}/teammates")
            ->assertJsonPath('data.people.0.email', null);
    }

    /** The flag needs a save to hang off — it cannot be set in isolation. */
    public function test_the_flag_cannot_be_set_without_saving_first(): void
    {
        $competition = $this->competition();

        $this->actingAs($this->participant())
            ->patchJson("/api/v1/competitions/{$competition->id}/save", ['looking_for_team' => true])
            ->assertStatus(404);
    }

    /** "2 looking" should mean two people you could team up with, not you. */
    public function test_the_count_on_the_board_excludes_yourself(): void
    {
        $me    = $this->participant();
        $other = $this->user(UserRole::PARTICIPANT, 'other2@trybe.test', 'unverified');

        $competition = $this->competition();

        foreach ([$me, $other] as $user) {
            $this->actingAs($user)
                ->postJson("/api/v1/competitions/{$competition->id}/save", ['looking_for_team' => true]);
        }

        $this->actingAs($me)
            ->getJson('/api/v1/competitions')
            ->assertJsonPath('data.competitions.0.teammates_count', 1)
            ->assertJsonPath('data.competitions.0.looking_for_team', true);
    }

    // =================================================================
    // Ownership
    // =================================================================

    public function test_a_researcher_cannot_delete_someone_elses_listing(): void
    {
        $mine   = $this->competition();
        $other  = $this->user(UserRole::RESEARCHER, 'other@trybe.test', 'verified');

        $this->actingAs($other)
            ->deleteJson("/api/v1/competitions/{$mine->id}")
            ->assertStatus(403);

        $this->assertDatabaseCount('competitions', 1);
    }

    public function test_a_poster_can_delete_their_own_listing(): void
    {
        $researcher  = $this->researcher();
        $competition = $this->competition(['posted_by' => $researcher->id]);

        $this->actingAs($researcher)
            ->deleteJson("/api/v1/competitions/{$competition->id}")
            ->assertStatus(200);

        $this->assertDatabaseCount('competitions', 0);
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'         => 'Test Hackathon',
            'external_url' => 'https://devpost.com/hackathons',
            'organizer'    => 'Devpost',
            'deadline'     => now()->addDays(10)->toDateString(),
        ], $overrides);
    }

    private function user(UserRole $role, string $email, string $status): User
    {
        return User::create([
            'name' => ucfirst($role->value), 'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role->value, 'verification_status' => $status,
        ]);
    }

    private function researcher(): User
    {
        return User::where('email', 'researcher@trybe.test')->first()
            ?? $this->user(UserRole::RESEARCHER, 'researcher@trybe.test', 'verified');
    }

    private function participant(): User
    {
        return User::where('email', 'sadia@trybe.test')->first()
            ?? $this->user(UserRole::PARTICIPANT, 'sadia@trybe.test', 'unverified');
    }

    private function competition(array $overrides = []): Competition
    {
        return Competition::create(array_merge([
            'posted_by'    => $this->researcher()->id,
            'name'         => 'Some Hackathon',
            'organizer'    => 'Devpost',
            'external_url' => 'https://devpost.com/hackathons',
            'deadline'     => now()->addDays(14)->toDateString(),
        ], $overrides));
    }
}
