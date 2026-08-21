<?php

namespace Tests\Feature;

use App\Enums\CredentialLevel;
use App\Enums\IncentiveType;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\StudyMatchCriteria;
use App\Models\User;
use App\Services\StudyFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Study Recommendation Feed — ranking rules.
 */
class FeedRankingTest extends TestCase
{
    use RefreshDatabase;

    /** The weights are a percentage split; if they stop totalling 100 the score is meaningless. */
    public function test_configured_feed_weights_total_one_hundred(): void
    {
        $this->assertSame(100, array_sum(app(StudyFeedService::class)->weights()));
    }

    /** The three contributions must add up to feed_score, or the number is not explainable. */
    public function test_breakdown_contributions_add_up_to_the_feed_score(): void
    {
        $participant = $this->participant();
        $this->openStudy('Adds up');

        $studies = app(StudyFeedService::class)->feed($participant);

        $this->assertNotEmpty($studies);

        foreach ($studies as $study) {
            $sum = collect($study->feed_breakdown)->sum('contribution');

            $this->assertEqualsWithDelta($study->feed_score, round($sum, 1), 0.2);
        }
    }

    /**
     * The headline behaviour: suitability decides the order. A brand-new but
     * badly matched study must not outrank an older, well-matched one.
     */
    public function test_suitability_beats_freshness(): void
    {
        $participant = $this->participant(['skills' => 'Survey, Interviews']);

        $goodOld = $this->openStudy('Good match, older', [
            'created_at' => now()->subDays(60),
        ], ['required_skills' => ['survey', 'interviews']]);

        $badNew = $this->openStudy('Poor match, brand new', [
            'created_at' => now(),
        ], ['required_skills' => ['welding', 'scuba diving'], 'age_min' => 60, 'age_max' => 70]);

        $order = app(StudyFeedService::class)->feed($participant)
            ->pluck('id')->all();

        $this->assertLessThan(
            array_search($badNew->id, $order, true),
            array_search($goodOld->id, $order, true),
            'A better match must outrank a fresher but worse one.'
        );
    }

    /** Between equally-matched studies, the newer one should surface first. */
    public function test_freshness_breaks_ties_between_equal_matches(): void
    {
        $participant = $this->participant();

        $older = $this->openStudy('Older', ['created_at' => now()->subDays(30)]);
        $newer = $this->openStudy('Newer', ['created_at' => now()]);

        $order = app(StudyFeedService::class)->feed($participant)->pluck('id')->all();

        $this->assertLessThan(
            array_search($older->id, $order, true),
            array_search($newer->id, $order, true)
        );
    }

    /**
     * Karma, boosts and researcher tier are deliberately not read. This test
     * exists so a later "helpful" addition cannot quietly reintroduce them.
     */
    public function test_no_karma_boost_or_tier_value_can_affect_the_ordering(): void
    {
        $participant = $this->participant();
        $this->openStudy('One');
        $this->openStudy('Two');

        $before = app(StudyFeedService::class)->feed($participant)->pluck('id')->all();

        // Give the participant a large karma balance. Nothing should move.
        \App\Models\KarmaTransaction::create([
            'user_id' => $participant->id,
            'amount'  => 5000,
            'source'  => \App\Enums\KarmaSource::MANUAL_ADJUST->value,
        ]);

        $after = app(StudyFeedService::class)->feed($participant->fresh())->pluck('id')->all();

        $this->assertSame($before, $after);

        $weights = app(StudyFeedService::class)->weights();
        $this->assertArrayNotHasKey('karma', $weights);
        $this->assertArrayNotHasKey('boost', $weights);
        $this->assertArrayNotHasKey('tier', $weights);
    }

    /** The feed endpoint must never make an outbound HTTP call. */
    public function test_the_feed_endpoint_makes_no_outbound_request(): void
    {
        Http::fake();

        $participant = $this->participant();
        $this->openStudy('No network');

        $this->actingAs($participant)
            ->getJson('/api/v1/participants/me/feed')
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_researcher_has_no_feed(): void
    {
        $this->actingAs($this->researcher())
            ->getJson('/api/v1/participants/me/feed')
            ->assertStatus(403);
    }

    // -----------------------------------------------------------------

    private function openStudy(string $title, array $attributes = [], array $criteria = []): Study
    {
        $study = Study::create(array_merge([
            'researcher_id'       => $this->researcher()->id,
            'title'               => $title,
            'category'            => 'survey',
            'method'              => 'online',
            'duration_minutes'    => 30,
            'incentive_type'      => IncentiveType::VOLUNTEER->value,
            'compensation_amount' => 0,
            'slots'               => 10,
            'status'              => StudyStatus::OPEN->value,
            'participants_count'  => 0,
        ], $attributes));

        // created_at is guarded, so set it explicitly when the test asks.
        if (isset($attributes['created_at'])) {
            $study->created_at = $attributes['created_at'];
            $study->save();
        }

        StudyMatchCriteria::create(array_merge([
            'study_id'          => $study->id,
            'age_min'           => 18,
            'age_max'           => 40,
            'credential_min'    => CredentialLevel::NONE->value,
            'required_skills'   => [],
            'availability_days' => 30,
        ], $criteria));

        return $study->fresh();
    }

    private function researcher(): User
    {
        return User::create([
            'name'                => 'Researcher ' . uniqid(),
            'email'               => uniqid('r_', true) . '@example.com',
            'password'            => 'password',
            'role'                => UserRole::RESEARCHER->value,
            'verification_status' => 'verified',
        ]);
    }

    private function participant(array $profile = []): User
    {
        $user = User::create([
            'name'                => 'Participant ' . uniqid(),
            'email'               => uniqid('p_', true) . '@example.com',
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
            'last_active_week'        => now()->subDays(2),
        ], $profile));

        return $user->fresh();
    }
}
