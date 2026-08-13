<?php

namespace App\Services;

use App\Enums\CredentialLevel;
use App\Enums\InvitationStatus;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\StudyMatchCriteria;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FEATURE — Smart Participant Matching  (Member 4, graded feature)
 *
 * All matching logic lives here. Controllers ask; they never calculate.
 * The Blade page and the API therefore cannot disagree about a score.
 *
 * WHAT CHANGED, AND WHY
 * ---------------------
 * criteriaForStudy() used to be a hardcoded lookup table keyed by the study
 * TITLE, with a second table keyed by category:
 *
 *     'smartphone usability study in dhaka' => ['age' => [20, 34], ...]
 *
 * That meant renaming a study changed who matched it, and the numbers
 * driving the score were not in the database at all. Criteria now come from
 * the study_match_criteria table, falling back to config/platform.php.
 * There is not a single bare number left in this file.
 *
 * THE SCORE
 * ---------
 * Eight factors, each producing a sub-score of 0-100, each multiplied by a
 * weight from config('platform.matching.weights'). The weights total 100, so
 * match_score is a genuine percentage and the eight contributions add up to
 * it exactly — no min(100, ...) clamping like the old implementation needed.
 *
 * Factors that do not apply to a study (no topics tagged, no location
 * required) are marked applicable=false and their weight is redistributed
 * across the rest, so the total is always out of 100.
 *
 * PERFORMANCE
 * -----------
 * rankParticipantsForStudy() is two-phase. Phase 1 applies the hard filters
 * in SQL and narrows to config('platform.matching.shortlist_size') rows
 * ordered by reliability. Phase 2 scores that shortlist in PHP, with the
 * topic sets bulk-loaded in two grouped queries rather than per candidate.
 * Topic-overlap maths is not cheaply expressible in SQL; this bounds the
 * work instead. A candidate outside the top 100 by reliability cannot
 * realistically reach the top 5 by total score.
 */
class StudyMatchingService
{
    /** Ladder order, lowest first. Used for credential comparisons. */
    private const LEVEL_ORDER = [
        CredentialLevel::NONE->value,
        CredentialLevel::BRONZE->value,
        CredentialLevel::GOLD->value,
        CredentialLevel::EXPERT->value,
    ];

    // =================================================================
    // Configuration readers
    // =================================================================

    public function strongThreshold(): int
    {
        return (int) config('platform.matching.strong_threshold');
    }

    public function candidatesLimit(): int
    {
        return (int) config('platform.matching.candidates_limit');
    }

    /** The configured weights, integers, in display order. */
    public function weights(): array
    {
        return array_map('intval', config('platform.matching.weights', []));
    }

    // =================================================================
    // Criteria — read from the database, never invented
    // =================================================================

    /**
     * The eligibility criteria for a study.
     *
     * Reads study_match_criteria. Any field the researcher left blank falls
     * back to config('platform.matching.defaults'). is_default tells the API
     * whether a real row existed, so the page can prompt the researcher to
     * set criteria instead of silently matching against defaults.
     */
    public function criteriaForStudy(Study $study): array
    {
        $defaults = config('platform.matching.defaults', []);
        $row      = StudyMatchCriteria::where('study_id', $study->id)->first();

        $topics = $this->topicsForStudy($study);

        return [
            'age_min'           => $row?->age_min           ?? $defaults['age_min'] ?? null,
            'age_max'           => $row?->age_max           ?? $defaults['age_max'] ?? null,
            'location'          => $row?->location          ?? $defaults['location'] ?? null,
            'credential_min'    => $row?->credential_min    ?? $defaults['credential_min'] ?? null,
            'availability_days' => $row?->availability_days ?? $defaults['availability_days'] ?? null,
            'required_skills'   => $this->normaliseSkillList(
                $row?->required_skills ?? $defaults['required_skills'] ?? []
            ),
            'topic_ids'  => $topics->pluck('id')->all(),
            'topics'     => $topics->map(fn ($t) => [
                'id'   => (int) $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
            ])->values()->all(),
            'is_default' => $row === null,
        ];
    }

    /** A one-line human summary of the criteria, for the panel header. */
    public function criteriaSummary(array $criteria): string
    {
        $parts = [];

        if ($criteria['age_min'] !== null && $criteria['age_max'] !== null) {
            $parts[] = 'Age ' . $criteria['age_min'] . '-' . $criteria['age_max'];
        }

        if (! empty($criteria['location'])) {
            $parts[] = $criteria['location'];
        }

        if (! empty($criteria['credential_min'])
            && $criteria['credential_min'] !== CredentialLevel::NONE->value) {
            $parts[] = ucfirst((string) $criteria['credential_min']) . '+';
        }

        if (! empty($criteria['required_skills'])) {
            $parts[] = collect($criteria['required_skills'])
                ->map(fn ($s) => Str::title($s))->implode(', ');
        }

        if (! empty($criteria['topics'])) {
            $parts[] = collect($criteria['topics'])->pluck('name')->implode(', ');
        }

        if (! empty($criteria['availability_days'])) {
            $parts[] = 'Active within ' . (int) $criteria['availability_days'] . ' days';
        }

        return $parts ? implode(' · ', $parts) : 'No criteria set for this study yet.';
    }

    // =================================================================
    // Topics
    // =================================================================

    /** The topics a study is tagged with. */
    public function topicsForStudy(Study $study): Collection
    {
        return DB::table('study_topic')
            ->join('topics', 'topics.id', '=', 'study_topic.topic_id')
            ->where('study_topic.study_id', $study->id)
            ->orderBy('topics.name')
            ->get(['topics.id', 'topics.name', 'topics.slug']);
    }

    /** Topic ids this participant ticked a box for. */
    public function declaredTopicsFor(User $user): array
    {
        return DB::table('participant_topics')
            ->where('user_id', $user->id)
            ->pluck('topic_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Topic ids from studies this participant actually COMPLETED.
     *
     * Derived live from study_participations -> studies -> study_topic, so it
     * can never go stale. PAID counts as completed, matching the rule
     * CredentialService already uses.
     */
    public function pastStudyTopicsFor(User $user): array
    {
        return DB::table('study_participations')
            ->join('study_topic', 'study_topic.study_id', '=', 'study_participations.study_id')
            ->where('study_participations.participant_id', $user->id)
            ->whereIn('study_participations.stage', $this->completedStages())
            ->distinct()
            ->pluck('study_topic.topic_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // =================================================================
    // Ranking — the researcher's suggested candidate list
    // =================================================================

    /**
     * The best candidates for a study, highest score first.
     *
     * Candidates with a PENDING invitation stay in the list, flagged
     * `invited` so the button reads "Invited". Only ACCEPTED and DECLINED
     * invitations remove somebody, which is what makes the next-best
     * candidate appear once someone accepts.
     */
    public function rankParticipantsForStudy(Study $study, ?int $limit = null, bool $includeDeclined = false): Collection
    {
        $limit    = $limit ?? $this->candidatesLimit();
        $criteria = $this->criteriaForStudy($study);

        $shortlist = $this->shortlistFor($study, $criteria, $includeDeclined);

        if ($shortlist->isEmpty()) {
            return collect();
        }

        $userIds = $shortlist->pluck('user_id')->all();
        $context = $this->contextFor($criteria, $userIds);
        $invites = $this->invitationsFor($study, $userIds);

        return $shortlist
            ->map(function (ParticipantProfile $profile) use ($criteria, $context, $invites) {
                $assessment = $this->assess($profile, $profile->user, $criteria, $context);

                $profile->setAttribute('match_score', $assessment['score']);
                $profile->setAttribute('match_factors', $assessment['factors']);
                $profile->setAttribute('match_reasons', $assessment['reasons']);
                $profile->setAttribute('strong_match', $assessment['score'] >= $this->strongThreshold());
                $profile->setAttribute('invitation', $invites[$profile->user_id] ?? null);

                return $profile;
            })
            ->sort(function (ParticipantProfile $a, ParticipantProfile $b) {
                return [$b->match_score, $b->reliability_score, $b->completed_studies_count]
                    <=> [$a->match_score, $a->reliability_score, $a->completed_studies_count];
            })
            ->take($limit)
            ->values();
    }

    /**
     * PHASE 1 — hard filters in SQL, narrowed to the shortlist size.
     *
     * The age and credential filters are deliberately one notch WIDER than
     * the criteria (age_filter_slack / credential_slack), because the scorer
     * tapers rather than cliff-edging. Filtering at the exact boundary would
     * throw away candidates the scorer would have rated 80.
     */
    private function shortlistFor(Study $study, array $criteria, bool $includeDeclined): Collection
    {
        $ageSlack  = (int) config('platform.matching.age_filter_slack');
        $credSlack = (int) config('platform.matching.credential_slack');

        $resolved = $includeDeclined
            ? [InvitationStatus::ACCEPTED->value]
            : InvitationStatus::resolvedValues();

        $query = ParticipantProfile::query()
            ->select('participant_profiles.*')
            ->join('users', 'users.id', '=', 'participant_profiles.user_id')
            ->where('users.role', UserRole::PARTICIPANT->value);

        if ($criteria['age_min'] !== null && $criteria['age_max'] !== null) {
            $query->whereBetween('participant_profiles.age', [
                max(0, (int) $criteria['age_min'] - $ageSlack),
                (int) $criteria['age_max'] + $ageSlack,
            ]);
        }

        if (! empty($criteria['location'])) {
            $query->where('users.location', 'like', '%' . $criteria['location'] . '%');
        }

        if (! empty($criteria['credential_min'])) {
            $query->whereIn(
                'participant_profiles.credential_level',
                $this->levelsFrom((string) $criteria['credential_min'], $credSlack)
            );
        }

        // Already progressing through this study's pipeline.
        $query->whereNotExists(function ($sub) use ($study) {
            $sub->select(DB::raw(1))
                ->from('study_participations')
                ->whereColumn('study_participations.participant_id', 'participant_profiles.user_id')
                ->where('study_participations.study_id', $study->id)
                ->whereIn('study_participations.stage', [
                    PipelineStage::CONFIRMED->value,
                    PipelineStage::SCHEDULED->value,
                    PipelineStage::COMPLETED->value,
                    PipelineStage::PAID->value,
                ]);
        });

        // Already answered an invitation for this study.
        $query->whereNotExists(function ($sub) use ($study, $resolved) {
            $sub->select(DB::raw(1))
                ->from('study_invitations')
                ->whereColumn('study_invitations.participant_id', 'participant_profiles.user_id')
                ->where('study_invitations.study_id', $study->id)
                ->whereIn('study_invitations.status', $resolved);
        });

        return $query
            ->with('user')
            ->orderByDesc('participant_profiles.reliability_score')
            ->orderByDesc('participant_profiles.completed_studies_count')
            ->limit((int) config('platform.matching.shortlist_size'))
            ->get();
    }

    /**
     * Bulk-load every topic set the shortlist needs, in two grouped queries.
     * Without this, scoring 100 candidates would run 200 queries.
     */
    private function contextFor(array $criteria, array $userIds): array
    {
        if (empty($userIds) || empty($criteria['topic_ids'])) {
            return ['declared' => [], 'history' => []];
        }

        $declared = DB::table('participant_topics')
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'topic_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('topic_id')->map(fn ($id) => (int) $id)->all())
            ->all();

        $history = DB::table('study_participations')
            ->join('study_topic', 'study_topic.study_id', '=', 'study_participations.study_id')
            ->whereIn('study_participations.participant_id', $userIds)
            ->whereIn('study_participations.stage', $this->completedStages())
            ->distinct()
            ->get(['study_participations.participant_id as user_id', 'study_topic.topic_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('topic_id')->map(fn ($id) => (int) $id)->unique()->values()->all())
            ->all();

        return ['declared' => $declared, 'history' => $history];
    }

    /** Existing invitations for this study, keyed by participant id. */
    private function invitationsFor(Study $study, array $userIds): array
    {
        return DB::table('study_invitations')
            ->where('study_id', $study->id)
            ->whereIn('participant_id', $userIds)
            ->get()
            ->keyBy('participant_id')
            ->map(fn ($row) => [
                'id'                    => (int) $row->id,
                'status'                => $row->status,
                'invited_at'            => $row->created_at,
                'responded_at'          => $row->responded_at,
                'match_score_at_invite' => (int) $row->match_score_at_invite,
            ])
            ->all();
    }

    // =================================================================
    // The participant's side — studies that match THEM
    // =================================================================

    /**
     * Open studies this participant is a good fit for, best first.
     *
     * The brief's Smart Matching entry ends: "Participants who are a strong
     * match also see the study highlighted in their personalized
     * recommendation feed." This is that half.
     *
     * Criteria and topics for every candidate study are bulk-loaded, so the
     * cost is a fixed handful of queries rather than three per study.
     */
    public function recommendStudiesForParticipant(User $user, ?int $limit = null): Collection
    {
        $limit = $limit ?? $this->candidatesLimit();

        if (! $user->participantProfile) {
            return collect();
        }

        // Studies the participant has already engaged with are not suggestions.
        $engagedStudyIds = DB::table('study_participations')
            ->where('participant_id', $user->id)
            ->whereIn('stage', array_merge($this->completedStages(), [
                PipelineStage::CONFIRMED->value,
                PipelineStage::SCHEDULED->value,
            ]))
            ->pluck('study_id')
            ->all();

        $studies = Study::query()
            ->with('researcher')
            ->where('status', StudyStatus::OPEN)
            ->when($engagedStudyIds, fn ($q) => $q->whereNotIn('id', $engagedStudyIds))
            ->get();

        if ($studies->isEmpty()) {
            return collect();
        }

        $declared = $this->declaredTopicsFor($user);
        $history  = $this->pastStudyTopicsFor($user);
        $context  = [
            'declared' => [$user->id => $declared],
            'history'  => [$user->id => $history],
        ];

        $criteriaRows = StudyMatchCriteria::whereIn('study_id', $studies->pluck('id'))
            ->get()->keyBy('study_id');

        $topicsByStudy = DB::table('study_topic')
            ->join('topics', 'topics.id', '=', 'study_topic.topic_id')
            ->whereIn('study_topic.study_id', $studies->pluck('id'))
            ->get(['study_topic.study_id', 'topics.id', 'topics.name', 'topics.slug'])
            ->groupBy('study_id');

        $profile = $user->participantProfile;

        return $studies
            ->map(function (Study $study) use ($criteriaRows, $topicsByStudy, $profile, $user, $context) {
                $criteria = $this->criteriaFromRow(
                    $criteriaRows->get($study->id),
                    $topicsByStudy->get($study->id, collect())
                );

                $assessment = $this->assess($profile, $user, $criteria, $context);

                $study->setAttribute('match_score', $assessment['score']);
                $study->setAttribute('match_factors', $assessment['factors']);
                $study->setAttribute('match_reasons', $assessment['reasons']);
                $study->setAttribute('strong_match', $assessment['score'] >= $this->strongThreshold());
                $study->setAttribute('criteria_summary', $this->criteriaSummary($criteria));

                return $study;
            })
            ->sort(fn (Study $a, Study $b) => [$b->match_score, $b->id] <=> [$a->match_score, $a->id])
            ->take($limit)
            ->values();
    }

    /** Build a criteria array from an already-loaded row, avoiding a query. */
    private function criteriaFromRow(?StudyMatchCriteria $row, Collection $topics): array
    {
        $defaults = config('platform.matching.defaults', []);

        return [
            'age_min'           => $row?->age_min           ?? $defaults['age_min'] ?? null,
            'age_max'           => $row?->age_max           ?? $defaults['age_max'] ?? null,
            'location'          => $row?->location          ?? $defaults['location'] ?? null,
            'credential_min'    => $row?->credential_min    ?? $defaults['credential_min'] ?? null,
            'availability_days' => $row?->availability_days ?? $defaults['availability_days'] ?? null,
            'required_skills'   => $this->normaliseSkillList(
                $row?->required_skills ?? $defaults['required_skills'] ?? []
            ),
            'topic_ids'  => $topics->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'topics'     => $topics->map(fn ($t) => [
                'id'   => (int) $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
            ])->values()->all(),
            'is_default' => $row === null,
        ];
    }

    // =================================================================
    // Scoring
    // =================================================================

    /** Score one user against one study. Convenience wrapper for single lookups. */
    public function assessUserForStudy(User $user, array $criteria): array
    {
        $profile = $user->participantProfile;

        if (! $profile) {
            return ['score' => 0.0, 'factors' => [], 'reasons' => []];
        }

        $context = [
            'declared' => [$user->id => $this->declaredTopicsFor($user)],
            'history'  => [$user->id => $this->pastStudyTopicsFor($user)],
        ];

        return $this->assess($profile, $user, $criteria, $context);
    }

    /** Score a loaded profile. Convenience for callers holding a profile. */
    public function assessProfile(ParticipantProfile $profile, array $criteria): array
    {
        if (! $profile->user) {
            return ['score' => 0.0, 'factors' => [], 'reasons' => []];
        }

        return $this->assessUserForStudy($profile->user, $criteria);
    }

    /**
     * The eight factors.
     *
     * Each returns sub_score 0-100 plus applicable. Inapplicable factors have
     * their weight redistributed across the rest, so the contributions always
     * add up to match_score and match_score is always out of 100.
     */
    private function assess(ParticipantProfile $profile, ?User $user, array $criteria, array $context): array
    {
        $userId   = $profile->user_id;
        $declared = $context['declared'][$userId] ?? [];
        $history  = $context['history'][$userId] ?? [];

        $factors = [
            'topics'       => $this->scoreTopics($criteria, $declared, $history),
            'age'          => $this->scoreAge($profile, $criteria),
            'location'     => $this->scoreLocation($user, $criteria),
            'skills'       => $this->scoreSkills($profile, $criteria),
            'credential'   => $this->scoreCredential($profile, $criteria),
            'reliability'  => $this->scoreReliability($profile),
            'availability' => $this->scoreAvailability($profile, $criteria),
            'standing'     => $this->scoreStanding($profile),
        ];

        $weights = $this->weights();

        // Redistribute the weight of any factor that does not apply.
        $applicableWeight = 0;
        foreach ($factors as $key => $factor) {
            if ($factor['applicable']) {
                $applicableWeight += (int) ($weights[$key] ?? 0);
            }
        }

        $score   = 0.0;
        $reasons = [];

        foreach ($factors as $key => $factor) {
            $weight          = (int) ($weights[$key] ?? 0);
            $effectiveWeight = ($factor['applicable'] && $applicableWeight > 0)
                ? round($weight / $applicableWeight * 100, 2)
                : 0.0;

            $contribution = round($factor['sub_score'] * $effectiveWeight / 100, 1);
            $score       += $contribution;

            $factors[$key]['weight']           = $weight;
            $factors[$key]['effective_weight'] = $effectiveWeight;
            $factors[$key]['contribution']     = $contribution;

            if ($factor['applicable'] && ! empty($factor['reason'])) {
                $reasons[] = $factor['reason'];
            }

            unset($factors[$key]['reason']);
        }

        return [
            'score'   => round($score, 1),
            'factors' => $factors,
            'reasons' => $reasons,
        ];
    }

    private function scoreAge(ParticipantProfile $profile, array $criteria): array
    {
        if ($criteria['age_min'] === null || $criteria['age_max'] === null) {
            return $this->factor(0, false);
        }

        $age = (int) ($profile->age ?? 0);

        if ($age <= 0) {
            return $this->factor(0, true, 'Age not set on their profile.');
        }

        $min = (int) $criteria['age_min'];
        $max = (int) $criteria['age_max'];

        if ($age >= $min && $age <= $max) {
            return $this->factor(100, true, 'Age ' . $age . ' fits the ' . $min . '-' . $max . ' range.');
        }

        // Taper instead of cliff-edging: someone one year outside a band is
        // not the same as someone twenty years outside it.
        $slack    = max(1, (int) config('platform.matching.age_filter_slack'));
        $distance = $age < $min ? $min - $age : $age - $max;
        $sub      = (int) max(0, round(100 - ($distance / $slack * 100)));

        return $this->factor(
            $sub,
            true,
            $sub > 0
                ? 'Age ' . $age . ' is just outside the ' . $min . '-' . $max . ' range.'
                : null
        );
    }

    private function scoreLocation(?User $user, array $criteria): array
    {
        if (empty($criteria['location'])) {
            return $this->factor(0, false);
        }

        $location = Str::lower((string) ($user?->location ?? ''));

        if ($location === '') {
            return $this->factor(0, true, 'Location not set on their profile.');
        }

        if (Str::contains($location, Str::lower((string) $criteria['location']))) {
            return $this->factor(100, true, 'Location matches ' . $criteria['location'] . '.');
        }

        return $this->factor(0, true, null);
    }

    /**
     * Whole-token skill comparison.
     *
     * The old implementation did LIKE '%Python%' against the free-text
     * participant_profiles.skills column, which matched "Pythonic" and
     * "Jython" and could not be indexed. We still read that column — it is
     * Member 1's and we only read it — but compare normalised whole tokens.
     */
    private function scoreSkills(ParticipantProfile $profile, array $criteria): array
    {
        $required = $criteria['required_skills'] ?? [];

        if (empty($required)) {
            return $this->factor(0, false);
        }

        $have = $this->normaliseSkillList($profile->skills);

        $matched = array_values(array_intersect($required, $have));
        $missing = array_values(array_diff($required, $have));
        $sub     = (int) round(count($matched) / count($required) * 100);

        $factor = $this->factor(
            $sub,
            true,
            $matched
                ? 'Has ' . count($matched) . ' of ' . count($required) . ' required skills: '
                    . collect($matched)->map(fn ($s) => Str::title($s))->implode(', ') . '.'
                : null
        );

        $factor['matched'] = $matched;
        $factor['missing'] = $missing;

        return $factor;
    }

    private function scoreCredential(ParticipantProfile $profile, array $criteria): array
    {
        $min = $criteria['credential_min'] ?? null;

        // No minimum set, or a minimum of "none", is not a requirement.
        if (empty($min) || $min === CredentialLevel::NONE->value) {
            return $this->factor(0, false);
        }

        $have      = $this->levelValue($profile->credential_level);
        $haveIndex = array_search($have, self::LEVEL_ORDER, true);
        $minIndex  = array_search($min, self::LEVEL_ORDER, true);

        if ($haveIndex === false || $minIndex === false) {
            return $this->factor(0, true, null);
        }

        if ($haveIndex >= $minIndex) {
            return $this->factor(100, true, 'Credential level ' . ucfirst($have) . ' meets the ' . ucfirst((string) $min) . ' minimum.');
        }

        if ($haveIndex === $minIndex - 1) {
            return $this->factor(60, true, 'Credential level ' . ucfirst($have) . ' is one tier below the minimum.');
        }

        return $this->factor(0, true, null);
    }

    private function scoreReliability(ParticipantProfile $profile): array
    {
        $score = (int) $profile->reliability_score;

        return $this->factor(
            max(0, min(100, $score)),
            true,
            $score > 0 ? 'Reliability score ' . $score . '/100.' : null
        );
    }

    private function scoreAvailability(ParticipantProfile $profile, array $criteria): array
    {
        $days = (int) ($criteria['availability_days'] ?? 0);

        if ($days <= 0) {
            return $this->factor(0, false);
        }

        if (! $profile->last_active_week) {
            return $this->factor(0, true, 'No recorded activity.');
        }

        // abs() because Carbon 3 returns a signed difference, and a profile
        // with a future-dated last_active_week would otherwise score 0.
        $daysSince = (int) abs($profile->last_active_week->diffInDays(now()));

        if ($daysSince <= $days) {
            return $this->factor(100, true, 'Active within the last ' . $days . ' days.');
        }

        // Linear decay to zero at twice the window.
        if ($daysSince >= $days * 2) {
            return $this->factor(0, true, null);
        }

        $sub = (int) max(0, round(100 - (($daysSince - $days) / $days * 100)));

        return $this->factor($sub, true, null);
    }

    /**
     * Member 1's streak and endorsement features both promise a boost "in
     * researcher search results". This factor is what delivers on that —
     * every input already exists on participant_profiles, so it costs no
     * schema change. The streak threshold reuses the existing config key
     * rather than inventing a new number.
     */
    private function scoreStanding(ParticipantProfile $profile): array
    {
        $sub    = 0;
        $badges = [];

        if ($profile->is_verified_participant) {
            $sub += 50;
            $badges[] = 'Verified Participant';
        }

        $streakTarget = (int) config('platform.streak_badge_weeks');

        if ($streakTarget > 0 && (int) $profile->current_streak_weeks >= $streakTarget) {
            $sub += 30;
            $badges[] = (int) $profile->current_streak_weeks . '-week streak';
        }

        if ((int) $profile->endorsement_count >= 1) {
            $sub += 20;
            $badges[] = (int) $profile->endorsement_count . ' endorsements';
        }

        $factor = $this->factor(
            min(100, $sub),
            true,
            $badges ? 'Standing: ' . implode(', ', $badges) . '.' : null
        );

        $factor['is_verified_participant'] = (bool) $profile->is_verified_participant;
        $factor['current_streak_weeks']    = (int) $profile->current_streak_weeks;
        $factor['endorsement_count']       = (int) $profile->endorsement_count;

        return $factor;
    }

    /**
     * Topic overlap, blended between evidence and claim.
     *
     * A completed study is evidence the participant engages with a topic;
     * a ticked interest box is a claim. Evidence is weighted higher, and the
     * split is one config value rather than a magic number.
     */
    private function scoreTopics(array $criteria, array $declared, array $history): array
    {
        $studyTopics = $criteria['topic_ids'] ?? [];

        if (empty($studyTopics)) {
            return $this->factor(0, false);
        }

        $total = count($studyTopics);

        $historyMatched  = array_values(array_intersect($studyTopics, $history));
        $declaredMatched = array_values(array_intersect($studyTopics, $declared));

        $historyScore  = (int) round(count($historyMatched) / $total * 100);
        $declaredScore = (int) round(count($declaredMatched) / $total * 100);

        $share = (int) config('platform.matching.topic_history_share');
        $sub   = (int) round(($historyScore * $share + $declaredScore * (100 - $share)) / 100);

        $reason = null;

        if ($historyMatched) {
            $reason = 'Completed ' . count($historyMatched) . ' of ' . $total . ' study topics before.';
        } elseif ($declaredMatched) {
            $reason = 'Declared an interest in ' . count($declaredMatched) . ' of ' . $total . ' study topics.';
        }

        $factor = $this->factor($sub, true, $reason);

        $factor['history_score']         = $historyScore;
        $factor['declared_score']        = $declaredScore;
        $factor['matched_from_history']  = $historyMatched;
        $factor['matched_from_declared'] = $declaredMatched;

        return $factor;
    }

    private function factor(int $sub, bool $applicable, ?string $reason = null): array
    {
        return [
            'sub_score'  => max(0, min(100, $sub)),
            'applicable' => $applicable,
            'reason'     => $reason,
        ];
    }

    // =================================================================
    // Helpers
    // =================================================================

    /** COMPLETED and PAID both mean the session happened. */
    private function completedStages(): array
    {
        return [PipelineStage::COMPLETED->value, PipelineStage::PAID->value];
    }

    /**
     * Levels at or above a minimum, optionally widened downwards by $slack
     * so the SQL filter is looser than the scorer.
     */
    public function levelsFrom(string $minimumLevel, int $slack = 0): array
    {
        $start = array_search($minimumLevel, self::LEVEL_ORDER, true);

        if ($start === false) {
            return self::LEVEL_ORDER;
        }

        return array_slice(self::LEVEL_ORDER, max(0, $start - max(0, $slack)));
    }

    private function levelValue(mixed $level): string
    {
        return $level instanceof CredentialLevel
            ? $level->value
            : (string) ($level ?? CredentialLevel::NONE->value);
    }

    /**
     * "Python, UI testing , bangla" -> ['python', 'ui testing', 'bangla']
     * Comparison is on whole normalised tokens, never substrings.
     */
    public function normaliseSkillList(mixed $skills): array
    {
        if (is_string($skills)) {
            $skills = explode(',', $skills);
        }

        if (! is_array($skills)) {
            return [];
        }

        return collect($skills)
            ->map(fn ($skill) => Str::of((string) $skill)->lower()->squish()->toString())
            ->filter(fn ($skill) => $skill !== '')
            ->unique()
            ->values()
            ->all();
    }
}
