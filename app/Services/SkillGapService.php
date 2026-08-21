<?php

namespace App\Services;

use App\Models\SkillGapAdvice;
use App\Models\Study;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * FEATURE — the skill-gap coach  (Member 4)
 *
 * THE SPLIT, WHICH IS THE WHOLE POINT
 * -----------------------------------
 * analyse()  is DETERMINISTIC. No network, no AI. It finds the studies a
 *            participant NARROWLY missed and works out exactly which factor
 *            cost them the points, using the per-factor contributions the
 *            matching engine already returns.
 *
 * The AI (AiClient, called from adviceFor()) never scores anything and never
 * decides which gap matters. It is handed a gap this class already
 * identified, and only writes the advice on how to close it — knowledge the
 * platform does not hold.
 *
 * If the model is wrong, unreachable, or returns nonsense, the analysis is
 * unaffected and still renders. That is why analyse() comes first and works
 * completely on its own.
 */
class SkillGapService
{
    public function __construct(
        private StudyMatchingService $matching,
        private AiClient $ai,
    ) {}

    // =================================================================
    // The deterministic half
    // =================================================================

    /**
     * Which studies did this participant NEARLY qualify for, and why not?
     *
     * The near-miss band is [near_miss_floor, strong_threshold). Above the
     * threshold they already qualify; far below it there is no realistic
     * advice to give. The interesting set is the studies just out of reach.
     */
    public function analyse(User $user): array
    {
        $floor     = (int) config('platform.feed.near_miss_floor');
        $threshold = $this->matching->strongThreshold();
        $maxSkills = (int) config('platform.feed.max_gap_skills');

        $profile = $user->participantProfile;

        $scored = $this->matching->recommendStudiesForParticipant($user, 50);

        $nearMisses = $scored->filter(
            fn (Study $s) => $s->match_score >= $floor && $s->match_score < $threshold
        )->values();

        $missingSkills = [];
        $missingTopics = [];
        $blockerCounts = [];

        foreach ($nearMisses as $study) {
            $factors = $study->match_factors ?? [];

            // Which applicable factor lost the most points on this study?
            $worst = null;
            $worstLoss = -1;

            foreach ($factors as $key => $factor) {
                if (! ($factor['applicable'] ?? false)) {
                    continue;
                }

                // Points left on the table by this factor.
                $loss = (100 - (float) ($factor['sub_score'] ?? 0))
                        * (float) ($factor['effective_weight'] ?? 0) / 100;

                if ($loss > $worstLoss) {
                    $worstLoss = $loss;
                    $worst = $key;
                }
            }

            if ($worst) {
                $blockerCounts[$worst] = ($blockerCounts[$worst] ?? 0) + 1;
            }

            foreach ($factors['skills']['missing'] ?? [] as $skill) {
                $missingSkills[$skill] = ($missingSkills[$skill] ?? 0) + 1;
            }

            // Topics on the study the participant has no history in.
            $studyTopics    = collect($study->getAttribute('topics') ?? []);
            $matchedHistory = collect($factors['topics']['matched_from_history'] ?? []);

            foreach ($studyTopics as $topic) {
                if (! $matchedHistory->contains((int) $topic['id'])) {
                    $name = $topic['name'];
                    $missingTopics[$name] = ($missingTopics[$name] ?? 0) + 1;
                }
            }
        }

        arsort($missingSkills);
        arsort($missingTopics);
        arsort($blockerCounts);

        return [
            'near_miss_count'  => $nearMisses->count(),
            'band'             => ['floor' => $floor, 'threshold' => $threshold],

            'biggest_blocker'  => array_key_first($blockerCounts),
            'blocker_counts'   => $blockerCounts,

            'missing_skills'   => collect($missingSkills)->take($maxSkills)
                ->map(fn ($count, $skill) => [
                    'skill'          => $skill,
                    'blocks_studies' => $count,
                ])->values()->all(),

            'missing_topics'   => collect($missingTopics)->take($maxSkills)
                ->map(fn ($count, $topic) => [
                    'topic'          => $topic,
                    'blocks_studies' => $count,
                ])->values()->all(),

            // Context for the model — no names, no emails, no ids.
            'current_skills'    => $this->matching->normaliseSkillList($profile?->skills),
            'credential_level'  => $profile?->credential_level?->value,
            'completed_studies' => (int) ($profile?->completed_studies_count ?? 0),

            // Near-miss studies, for the panel. Titles are shown to their own
            // owner only — this array is NOT sent to the AI.
            'near_misses' => $nearMisses->take(6)->map(fn (Study $s) => [
                'study_id'    => $s->id,
                'title'       => $s->title,
                'match_score' => $s->match_score,
                'shortfall'   => round($threshold - $s->match_score, 1),
                'url'         => route('studies.show', $s),
            ])->all(),

            // Why the coach might have nothing to say — see hasNothingToSay().
            'reason' => $this->emptyReason($nearMisses->count(), $profile),
        ];
    }

    /**
     * A stable fingerprint of the analysis inputs. If this has not changed,
     * the stored advice is still current and no API call is needed.
     *
     * Deliberately excludes near_misses (titles and urls change wording
     * without changing the underlying gap) and the reason string.
     */
    public function inputsHash(array $analysis): string
    {
        return hash('sha256', json_encode([
            $analysis['missing_skills'],
            $analysis['missing_topics'],
            $analysis['biggest_blocker'],
            $analysis['current_skills'],
            $analysis['credential_level'],
            $analysis['near_miss_count'],
        ]));
    }

    /**
     * Cold start and other empty cases. A brand-new participant matches
     * nothing, so the band is empty through no fault of the code — telling
     * them to "learn UI testing" would be nonsense. Branch instead.
     */
    private function emptyReason(int $nearMissCount, $profile): ?string
    {
        if ($nearMissCount > 0) {
            return null;
        }

        if (! $profile || blank($profile->skills) || $profile->age === null) {
            return 'incomplete_profile';
        }

        return 'no_near_misses';
    }

    public function hasNothingToSay(array $analysis): bool
    {
        return $analysis['near_miss_count'] === 0;
    }

    // =================================================================
    // The stored record
    // =================================================================

    /**
     * Refresh the deterministic analysis and store it. Never calls the AI.
     *
     * Safe to run on a GET: it does no network I/O.
     */
    public function refreshAnalysis(User $user): SkillGapAdvice
    {
        $analysis = $this->analyse($user);
        $hash     = $this->inputsHash($analysis);

        $record = SkillGapAdvice::firstOrNew(['user_id' => $user->id]);

        // Keep any existing advice, but mark it stale by leaving the old hash
        // to compare against. The controller reports `stale` from that.
        $record->fill([
            'analysis'    => $analysis,
            'inputs_hash' => $record->exists ? $record->inputs_hash : $hash,
        ]);

        // First ever record: nothing generated yet, so the hash IS current.
        if (! $record->exists) {
            $record->inputs_hash = $hash;
        }

        $record->save();

        return $record->fresh();
    }

    /** The hash the CURRENT analysis produces, for staleness comparison. */
    public function currentHashFor(User $user): string
    {
        return $this->inputsHash($this->analyse($user));
    }

    // =================================================================
    // The AI half — only ever reached from an explicit refresh
    // =================================================================

    /**
     * Generate advice. THE ONLY PATH THAT TOUCHES THE NETWORK.
     *
     * Never called from a GET endpoint — see SkillGapApiController. Failure
     * is always soft: the previous advice is kept and the error recorded.
     */
    public function generate(User $user): SkillGapAdvice
    {
        $analysis = $this->analyse($user);
        $hash     = $this->inputsHash($analysis);

        $record = SkillGapAdvice::firstOrNew(['user_id' => $user->id]);
        $record->fill(['analysis' => $analysis]);

        /*
        | A brand-new record has no inputs_hash, and the column is NOT NULL
        | with no default — so the failure path below used to insert without
        | it and MySQL rejected the whole row. The AI being unreachable then
        | surfaced as a 500 instead of the soft failure it is meant to be.
        |
        | It is seeded with the EMPTY STRING rather than $hash on purpose. A
        | real hash here would make isCurrent() true on the next run, so a
        | first attempt that failed would never be retried — the record would
        | sit for ever claiming to be up to date with no advice in it.
        */
        if (! $record->exists) {
            $record->inputs_hash = '';
        }

        // Nothing to advise on — store the analysis and stop. Sending an
        // empty gap to a model produces confident nonsense.
        if ($this->hasNothingToSay($analysis)) {
            $record->fill([
                'inputs_hash' => $hash,
                'advice'      => null,
                'last_error'  => null,
            ]);
            $record->save();

            return $record->fresh();
        }

        /*
        | Already current — do not spend a call.
        |
        | isCurrent() requires advice to be non-null as well as the hash to
        | match, which is what stops a page load from suppressing the first
        | generation: refreshAnalysis() stamps a new record with the current
        | hash on every GET, so a hash comparison alone would report "up to
        | date" for a record that has never held any advice.
        */
        if ($record->exists && $record->isCurrent($hash)) {
            $record->save();

            return $record->fresh();
        }

        $result = $this->ai->skillAdvice($analysis);

        if ($result === null) {
            $record->fill(['last_error' => $this->ai->lastError()]);
            $record->save();

            return $record->fresh();   // previous advice untouched
        }

        $record->fill([
            'advice'       => $result,
            'inputs_hash'  => $hash,
            'model'        => $this->ai->model(),
            'generated_at' => now(),
            'last_error'   => null,
        ]);
        $record->save();

        return $record->fresh();
    }
}
