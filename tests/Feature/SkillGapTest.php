<?php

namespace Tests\Feature;

use App\Enums\CredentialLevel;
use App\Enums\IncentiveType;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\ParticipantProfile;
use App\Models\SkillGapAdvice;
use App\Models\Study;
use App\Models\StudyMatchCriteria;
use App\Models\User;
use App\Services\SkillGapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The skill-gap coach.
 *
 * The whole suite runs with NO API key and Http faked, so it never touches
 * the network — and the deterministic half is proven to work entirely on its
 * own, which is the design claim.
 */
class SkillGapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ai.key' => null]);

        /*
        | Pin the wire format.
        |
        | services.ai.provider reads AI_PROVIDER from .env, and AiClient parses
        | a different response shape for each: `choices.0.message.content` for
        | the OpenAI-compatible layer, `candidates.0.content.parts.0.text` for
        | Google's native endpoint. The fakes below are OpenAI-shaped, so a
        | developer with AI_PROVIDER=gemini-native in their .env would watch
        | every advice test fail with "Provider returned an empty message" —
        | the parser looking in the right place for a shape that isn't there.
        |
        | A test that passes or fails depending on the machine's .env is worse
        | than no test, so the provider is fixed here rather than inherited.
        */
        config([
            'services.ai.provider' => 'gemini',
            'services.ai.base_url' => 'https://ai.test/v1',
        ]);

        Http::fake();
    }

    /**
     * THE test for the split: the analysis is computed with no AI whatsoever.
     */
    public function test_the_analysis_works_with_no_api_key_and_no_network(): void
    {
        $participant = $this->participantMissingOneSkill();

        $analysis = app(SkillGapService::class)->analyse($participant);

        $this->assertGreaterThan(0, $analysis['near_miss_count']);
        $this->assertNotEmpty($analysis['missing_skills']);
        $this->assertSame('ui testing', $analysis['missing_skills'][0]['skill']);

        Http::assertNothingSent();
    }

    /**
     * The near-miss band is [floor, threshold). Studies they already qualify
     * for, and studies they have no hope of, are both excluded — the useful
     * set is the ones just out of reach.
     */
    public function test_the_band_excludes_strong_matches_and_hopeless_ones(): void
    {
        $participant = $this->participantMissingOneSkill();
        $service     = app(SkillGapService::class);

        $floor     = (int) config('platform.feed.near_miss_floor');
        $threshold = (int) config('platform.matching.strong_threshold');

        foreach ($service->analyse($participant)['near_misses'] as $miss) {
            $this->assertGreaterThanOrEqual($floor, $miss['match_score']);
            $this->assertLessThan($threshold, $miss['match_score']);
        }
    }

    /** Missing skills are counted across the band, most blocking first. */
    public function test_missing_skills_are_counted_across_the_band(): void
    {
        $participant = $this->participantMissingOneSkill();

        // Two more studies needing the same skill.
        $this->study('Second', ['required_skills' => ['survey', 'ui testing']]);
        $this->study('Third',  ['required_skills' => ['survey', 'ui testing']]);

        $analysis = app(SkillGapService::class)->analyse($participant);

        $top = $analysis['missing_skills'][0];

        $this->assertSame('ui testing', $top['skill']);
        $this->assertGreaterThanOrEqual(2, $top['blocks_studies']);
    }

    /**
     * A brand-new participant matches nothing, so the band is empty through
     * no fault of the code. Telling them to "learn UI testing" would be
     * nonsense, so it branches instead.
     */
    public function test_a_cold_start_participant_gets_the_profile_branch(): void
    {
        $bare = User::create([
            'name'                => 'Brand New',
            'email'               => uniqid('new_', true) . '@example.com',
            'password'            => 'password',
            'role'                => UserRole::PARTICIPANT->value,
            'verification_status' => 'unverified',
        ]);

        ParticipantProfile::create(['user_id' => $bare->id]);

        $analysis = app(SkillGapService::class)->analyse($bare->fresh());

        $this->assertSame(0, $analysis['near_miss_count']);
        $this->assertSame('incomplete_profile', $analysis['reason']);
        $this->assertTrue(app(SkillGapService::class)->hasNothingToSay($analysis));
    }

    /** Same inputs must produce the same hash, or caching would never hold. */
    public function test_the_inputs_hash_is_stable_for_unchanged_inputs(): void
    {
        $participant = $this->participantMissingOneSkill();
        $service     = app(SkillGapService::class);

        $first  = $service->inputsHash($service->analyse($participant));
        $second = $service->inputsHash($service->analyse($participant));

        $this->assertSame($first, $second);
    }

    /**
     * THE RULE FROM THE PLAN: a GET must never reach the provider, or a page
     * load could block on a timeout plus a retry.
     */
    public function test_the_skill_gap_get_endpoint_makes_no_outbound_request(): void
    {
        $participant = $this->participantMissingOneSkill();

        $this->actingAs($participant)
            ->getJson('/api/v1/participants/me/skill-gap')
            ->assertOk()
            ->assertJsonPath('data.ai_available', false);

        Http::assertNothingSent();
    }

    /** With no key configured, the GET still returns the full analysis. */
    public function test_the_analysis_is_served_even_with_no_ai_configured(): void
    {
        $participant = $this->participantMissingOneSkill();

        $this->actingAs($participant)
            ->getJson('/api/v1/participants/me/skill-gap')
            ->assertOk()
            ->assertJsonPath('data.advice', null)
            ->assertJsonPath('data.nothing_to_say', false)
            ->assertJsonStructure(['data' => ['analysis' => ['missing_skills', 'near_misses']]]);
    }

    /** A provider failure keeps the previous advice and records why. */
    public function test_a_provider_failure_keeps_the_previous_advice(): void
    {
        config(['services.ai.key' => 'test-key']);

        $participant = $this->participantMissingOneSkill();

        SkillGapAdvice::create([
            'user_id'      => $participant->id,
            'analysis'     => ['near_miss_count' => 1],
            'advice'       => ['focus_skill' => 'Previously saved'],
            'inputs_hash'  => 'stale-hash',
            'generated_at' => now()->subDay(),
        ]);

        Http::fake(['*' => Http::response('', 503)]);

        $record = app(SkillGapService::class)->generate($participant);

        $this->assertSame('Previously saved', $record->advice['focus_skill']);
        $this->assertNotNull($record->last_error);
    }

    /** The model does not get to invent a skill we never sent. */
    public function test_an_invented_focus_skill_is_rejected(): void
    {
        config(['services.ai.key' => 'test-key']);

        $participant = $this->participantMissingOneSkill();

        Http::fake(['*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'focus_skill'    => 'underwater basket weaving',
                        'why_it_matters' => 'It is not in our data at all.',
                        // A valid step, so the rejection can only be about the
                        // out-of-scope skill and nothing else.
                        'steps'          => [['action' => 'Weave a basket', 'effort' => 'a day']],
                    ]),
                ],
            ]],
        ], 200)]);

        $record = app(SkillGapService::class)->generate($participant);

        $this->assertNull($record->advice);
        $this->assertStringContainsString('not in the analysis', $record->last_error);
    }

    /**
     * Advice with nothing to DO is not advice.
     *
     * This is the guard against the failure the coach actually shipped with:
     * a fluent paragraph that restated the gap the page had already printed
     * and left the reader no better off. A response carrying no step is
     * discarded, and the previous advice survives.
     */
    public function test_advice_with_no_actionable_steps_is_rejected(): void
    {
        config(['services.ai.key' => 'test-key']);

        $participant = $this->participantMissingOneSkill();

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'focus_skill'    => 'ui testing',
                'why_it_matters' => 'Improving UI testing would unlock more studies.',
                'steps'          => [],
            ])]]],
        ], 200)]);

        $record = app(SkillGapService::class)->generate($participant);

        $this->assertNull($record->advice);
        $this->assertStringContainsString('no actionable steps', $record->last_error);
    }

    /**
     * The model never gets to name the gap in free text.
     *
     * A "headline" field used to exist and was NOT validated against the
     * analysis, so a study topic could surface in a sentence about skills.
     * Only focus_skill — which IS checked — reaches the page now.
     */
    public function test_the_stored_advice_carries_no_unvalidated_headline(): void
    {
        config(['services.ai.key' => 'test-key']);

        $participant = $this->participantMissingOneSkill();

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'headline'       => 'Reading is holding you back',
                'focus_skill'    => 'ui testing',
                'why_it_matters' => 'Researchers need someone who can talk aloud while using a prototype.',
                'steps'          => [['action' => 'Narrate your way through an unfamiliar app', 'effort' => 'an hour']],
            ])]]],
        ], 200)]);

        $record = app(SkillGapService::class)->generate($participant);

        $this->assertNotNull($record->advice);
        $this->assertArrayNotHasKey('headline', $record->advice);
        $this->assertSame('ui testing', $record->advice['focus_skill']);
    }

    /** A well-formed response is stored and stamped. */
    public function test_a_valid_response_is_stored(): void
    {
        config(['services.ai.key' => 'test-key']);

        $participant = $this->participantMissingOneSkill();

        Http::fake(['*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'focus_skill'    => 'ui testing',
                        'why_it_matters' => 'Several studies need it.',
                        'steps'          => [['action' => 'Try a usability walkthrough', 'effort' => 'a weekend']],
                        'encouragement'  => 'You are close.',
                    ]),
                ],
            ]],
        ], 200)]);

        $record = app(SkillGapService::class)->generate($participant);

        $this->assertSame('ui testing', $record->advice['focus_skill']);
        $this->assertNotNull($record->generated_at);
        $this->assertNull($record->last_error);
    }

    /**
     * A provider failure on the FIRST EVER attempt must still be a soft
     * failure.
     *
     * inputs_hash is NOT NULL with no default, so a first-attempt failure
     * used to try to insert a row without it and MySQL rejected the whole
     * statement — turning "the AI is unreachable" into a 500.
     */
    public function test_a_first_ever_failure_stores_the_error_instead_of_throwing(): void
    {
        config(['services.ai.key' => 'test-key']);

        $participant = $this->participantMissingOneSkill();

        $this->assertDatabaseMissing('skill_gap_advice', ['user_id' => $participant->id]);

        Http::fake(['*' => Http::response('', 500)]);

        $record = app(SkillGapService::class)->generate($participant);

        $this->assertNull($record->advice);
        $this->assertNotNull($record->last_error);
        $this->assertSame('', $record->inputs_hash);
    }

    /**
     * Opening the page and THEN asking for advice must still call the AI.
     *
     * refreshAnalysis() runs on every GET and stamps a new record with the
     * current hash. If "is it current?" ignored whether any advice exists,
     * that stamp would suppress the very first generation for everybody who
     * loaded the page before pressing the button — which is everybody.
     */
    public function test_viewing_the_page_first_does_not_suppress_the_first_generation(): void
    {
        config(['services.ai.key' => 'test-key']);

        $participant = $this->participantMissingOneSkill();

        $service = app(SkillGapService::class);

        // This is what a page load does.
        $service->refreshAnalysis($participant);

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'focus_skill'    => 'ui testing',
                'why_it_matters' => 'Researchers need someone who can narrate a prototype walkthrough.',
                'steps'          => [['action' => 'Narrate your way through an unfamiliar app', 'effort' => 'an hour']],
            ])]]],
        ], 200)]);

        $record = $service->generate($participant->fresh());

        Http::assertSentCount(1);
        $this->assertSame('ui testing', $record->advice['focus_skill']);
    }

    /**
     * The native Gemini transport is parsed correctly too.
     *
     * Every other test pins the provider to the OpenAI-compatible shape. This
     * is the one that exercises the other branch — which matters, because it
     * is the branch actually in use: keys issued in Google's newer `AQ.`
     * format are rejected by the compatibility layer, so .env runs with
     * AI_PROVIDER=gemini-native.
     */
    public function test_the_native_gemini_response_shape_is_parsed(): void
    {
        config([
            'services.ai.key'      => 'test-key',
            'services.ai.provider' => 'gemini-native',
            'services.ai.base_url' => 'https://ai.test/v1beta',
        ]);

        $participant = $this->participantMissingOneSkill();

        Http::fake(['*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode([
                    'focus_skill'    => 'ui testing',
                    'why_it_matters' => 'Researchers need someone who can narrate a prototype walkthrough.',
                    'steps'          => [['action' => 'Narrate your way through an unfamiliar app', 'effort' => 'an hour']],
                ])]]],
            ]],
        ], 200)]);

        $record = app(SkillGapService::class)->generate($participant);

        $this->assertNotNull($record->advice);
        $this->assertSame('ui testing', $record->advice['focus_skill']);
        $this->assertNull($record->last_error);
    }

    /** Unchanged inputs must not spend a second API call. */
    public function test_unchanged_inputs_make_no_second_call(): void
    {
        config(['services.ai.key' => 'test-key']);

        $participant = $this->participantMissingOneSkill();

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'focus_skill'    => 'ui testing',
                'why_it_matters' => 'Several studies need it.',
                'steps'          => [['action' => 'Sit in on a usability session', 'effort' => 'an hour']],
            ])]]],
        ], 200)]);

        $service = app(SkillGapService::class);

        $service->generate($participant);
        Http::assertSentCount(1);

        $service->generate($participant->fresh());
        Http::assertSentCount(1);   // still 1 — the hash has not moved
    }

    // -----------------------------------------------------------------

    /**
     * A participant who matches on everything EXCEPT one required skill, so
     * they land in the near-miss band rather than above or below it.
     */
    private function participantMissingOneSkill(): User
    {
        $participant = $this->participant(['skills' => 'Survey']);

        $this->study('Needs UI testing', ['required_skills' => ['survey', 'ui testing']]);

        return $participant;
    }

    private function study(string $title, array $criteria = []): Study
    {
        $study = Study::create([
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
        ]);

        StudyMatchCriteria::create(array_merge([
            'study_id'          => $study->id,
            'age_min'           => 18,
            'age_max'           => 40,
            'credential_min'    => CredentialLevel::NONE->value,
            'required_skills'   => [],
            'availability_days' => 30,
        ], $criteria));

        return $study;
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
            'reliability_score'       => 60,
            'completed_studies_count' => 0,
            'credential_level'        => CredentialLevel::NONE->value,
            'last_active_week'        => now()->subDays(2),
        ], $profile));

        return $user->fresh();
    }
}
