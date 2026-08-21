<?php

namespace Tests\Feature;

use App\Enums\CredentialLevel;
use App\Enums\IncentiveType;
use App\Enums\InvitationStatus;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\StudyInvitation;
use App\Models\StudyMatchCriteria;
use App\Models\StudyParticipation;
use App\Models\Topic;
use App\Models\User;
use App\Services\StudyMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Smart Participant Matching — the ranking rules.
 */
class CandidateRankingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The headline claim of the whole payload: the eight factor
     * contributions add up to match_score. If this ever fails, the number on
     * screen has stopped being explainable.
     */
    public function test_factor_contributions_add_up_to_the_match_score(): void
    {
        $study = $this->study();
        $this->criteriaFor($study);

        $user = $this->participant('Adds Up', ['age' => 25, 'skills' => 'Python, UI testing']);

        $matching   = app(StudyMatchingService::class);
        $assessment = $matching->assessUserForStudy($user, $matching->criteriaForStudy($study));

        $sum = collect($assessment['factors'])->sum('contribution');

        $this->assertEqualsWithDelta($assessment['score'], round($sum, 1), 0.2);
    }

    /** Weights are a percentage split; if they stop totalling 100 the score is meaningless. */
    public function test_configured_weights_total_one_hundred(): void
    {
        $this->assertSame(100, array_sum(app(StudyMatchingService::class)->weights()));
    }

    /**
     * The instructor's requirement: topics of PAST studies feed the score.
     * Two identical participants, one of whom completed a study on the same
     * topic, must not score the same.
     */
    public function test_completing_a_study_on_the_topic_beats_merely_declaring_it(): void
    {
        $topic = Topic::create(['name' => 'Memory', 'slug' => 'memory']);

        $study = $this->study();
        $this->criteriaFor($study);
        DB::table('study_topic')->insert([
            'study_id' => $study->id, 'topic_id' => $topic->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $attributes = ['age' => 25, 'skills' => 'Python, UI testing'];

        $experienced = $this->participant('Has Done It', $attributes);
        $interested  = $this->participant('Says So', $attributes);

        // The experienced one actually completed a study tagged with the topic.
        $pastStudy = $this->study('Earlier memory study');
        DB::table('study_topic')->insert([
            'study_id' => $pastStudy->id, 'topic_id' => $topic->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        StudyParticipation::create([
            'study_id'       => $pastStudy->id,
            'participant_id' => $experienced->id,
            'stage'          => PipelineStage::COMPLETED->value,
            'completed_at'   => now(),
        ]);

        // The other only ticked the interest box.
        DB::table('participant_topics')->insert([
            'user_id' => $interested->id, 'topic_id' => $topic->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $matching = app(StudyMatchingService::class);
        $criteria = $matching->criteriaForStudy($study);

        $this->assertGreaterThan(
            $matching->assessUserForStudy($interested, $criteria)['score'],
            $matching->assessUserForStudy($experienced, $criteria)['score'],
            'Evidence (a completed study) should outweigh a declared interest.'
        );
    }

    /**
     * Skills is one of the five criteria the brief names, and the old
     * substring match ("Python" matching "Jython") is what this replaces.
     */
    public function test_skills_are_matched_on_whole_tokens_not_substrings(): void
    {
        $matching = app(StudyMatchingService::class);

        $this->assertSame(['python', 'ui testing'], $matching->normaliseSkillList('Python,  UI  testing '));

        $study = $this->study();
        $this->criteriaFor($study, ['required_skills' => ['python']]);

        $imposter = $this->participant('Jython Only', ['age' => 25, 'skills' => 'Jython']);
        $real     = $this->participant('Python Dev', ['age' => 25, 'skills' => 'Python']);

        $criteria = $matching->criteriaForStudy($study);

        $this->assertSame(0, $matching->assessUserForStudy($imposter, $criteria)['factors']['skills']['sub_score']);
        $this->assertSame(100, $matching->assessUserForStudy($real, $criteria)['factors']['skills']['sub_score']);
    }

    /**
     * A study with no topics must not score everybody zero on that factor —
     * the weight is redistributed and the total stays out of 100.
     */
    public function test_an_inapplicable_factor_is_marked_and_its_weight_redistributed(): void
    {
        $study = $this->study();
        $this->criteriaFor($study);   // no topics tagged

        $user       = $this->participant('No Topics', ['age' => 25, 'skills' => 'Python, UI testing']);
        $matching   = app(StudyMatchingService::class);
        $assessment = $matching->assessUserForStudy($user, $matching->criteriaForStudy($study));

        $this->assertFalse($assessment['factors']['topics']['applicable']);
        $this->assertSame(0.0, $assessment['factors']['topics']['contribution']);

        // Still reconciles, and still out of 100.
        $this->assertEqualsWithDelta(
            $assessment['score'],
            round(collect($assessment['factors'])->sum('contribution'), 1),
            0.2
        );
        $this->assertLessThanOrEqual(100, $assessment['score']);
    }

    /** Pre-existing behaviour, kept: someone already in the pipeline is not a candidate. */
    public function test_confirmed_participants_are_removed_from_suggested_matches(): void
    {
        $study = $this->study();
        $this->criteriaFor($study);

        $confirmed   = $this->participant('Already In', ['age' => 25]);
        $replacement = $this->participant('Next Best', ['age' => 25]);

        StudyParticipation::create([
            'study_id'       => $study->id,
            'participant_id' => $confirmed->id,
            'stage'          => PipelineStage::CONFIRMED->value,
        ]);

        $ids = app(StudyMatchingService::class)
            ->rankParticipantsForStudy($study, 5)
            ->pluck('user_id')->all();

        $this->assertNotContains($confirmed->id, $ids);
        $this->assertContains($replacement->id, $ids);
    }

    /**
     * The behaviour the feature specification describes: an invited but
     * undecided candidate STAYS on the list showing "Invited". Only an
     * answered invitation removes them, which is what makes the next-best
     * candidate appear.
     */
    public function test_pending_invitees_stay_but_answered_ones_drop_off(): void
    {
        $study = $this->study();
        $this->criteriaFor($study);

        $pending  = $this->participant('Thinking', ['age' => 25]);
        $accepted = $this->participant('Said Yes', ['age' => 25]);
        $declined = $this->participant('Said No', ['age' => 25]);

        foreach ([
            [$pending, InvitationStatus::PENDING],
            [$accepted, InvitationStatus::ACCEPTED],
            [$declined, InvitationStatus::DECLINED],
        ] as [$user, $status]) {
            StudyInvitation::create([
                'study_id'       => $study->id,
                'participant_id' => $user->id,
                'invited_by'     => $study->researcher_id,
                'status'         => $status,
            ]);
        }

        $ids = app(StudyMatchingService::class)
            ->rankParticipantsForStudy($study, 10)
            ->pluck('user_id')->all();

        $this->assertContains($pending->id, $ids, 'A pending invitee should still be listed as Invited.');
        $this->assertNotContains($accepted->id, $ids);
        $this->assertNotContains($declined->id, $ids);
    }

    /**
     * The refactor's central claim: criteria come from the database, so
     * changing the row changes who matches. Under the old hardcoded lookup
     * this was impossible to test at all.
     */
    public function test_criteria_come_from_the_database_not_the_study_title(): void
    {
        $study    = $this->study();
        $matching = app(StudyMatchingService::class);

        $this->assertTrue($matching->criteriaForStudy($study)['is_default']);

        StudyMatchCriteria::create([
            'study_id' => $study->id,
            'age_min'  => 40,
            'age_max'  => 50,
            'location' => 'Chattogram',
        ]);

        $criteria = $matching->criteriaForStudy($study);

        $this->assertFalse($criteria['is_default']);
        $this->assertSame(40, $criteria['age_min']);
        $this->assertSame('Chattogram', $criteria['location']);
    }

    // -----------------------------------------------------------------

    private function study(string $title = 'Test study'): Study
    {
        return Study::create([
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
    }

    private function criteriaFor(Study $study, array $overrides = []): StudyMatchCriteria
    {
        return StudyMatchCriteria::create(array_merge([
            'study_id'          => $study->id,
            'age_min'           => 18,
            'age_max'           => 40,
            'location'          => null,
            'credential_min'    => CredentialLevel::NONE->value,
            'required_skills'   => [],
            'availability_days' => 30,
        ], $overrides));
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
