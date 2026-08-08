<?php

namespace App\Services;

use App\Enums\PipelineStage;
use App\Models\StudyParticipation;
use App\Models\StudyReview;
use App\Models\User;

/**
 * FEATURE — Participant Reliability Score
 *
 * A 0–100 number built from three weighted factors:
 *
 *   attendance  — did you turn up to sessions you booked?
 *   completion  — did you finish what you started?
 *   reviews     — how did researchers rate you afterwards?
 *
 * The weights live in config/platform.php, not in this file, so the balance
 * can be retuned without touching any logic.
 *
 * SINGLE WRITER: this class is the only place that writes rel_attendance,
 * rel_completion, rel_reviews and reliability_score. Controllers — web or
 * API — ask this service, they never update those columns themselves.
 */
class ReliabilityService
{
    /** Attendance: sessions attended vs sessions you were expected at. */
    public function attendanceFactor(User $user): array
    {
        $attended = StudyParticipation::where('participant_id', $user->id)
            ->whereIn('stage', [PipelineStage::COMPLETED, PipelineStage::PAID])
            ->count();

        $missed = StudyParticipation::where('participant_id', $user->id)
            ->where('stage', PipelineStage::NO_SHOW)
            ->count();

        $expected = $attended + $missed;

        return [
            'value'   => $expected === 0 ? 0 : (int) round($attended / $expected * 100),
            'hasData' => $expected > 0,
            'detail'  => $attended . ' attended · ' . $missed . ' missed',
        ];
    }

    /** Completion: finished vs everything you started and wasn't rejected from. */
    public function completionFactor(User $user): array
    {
        $finished = StudyParticipation::where('participant_id', $user->id)
            ->whereIn('stage', [PipelineStage::COMPLETED, PipelineStage::PAID])
            ->count();

        // Rejected applications aren't your fault, so they don't count against you.
        $started = StudyParticipation::where('participant_id', $user->id)
            ->where('stage', '!=', PipelineStage::REJECTED->value)
            ->count();

        return [
            'value'   => $started === 0 ? 0 : (int) round($finished / $started * 100),
            'hasData' => $started > 0,
            'detail'  => $finished . ' finished of ' . $started . ' started',
        ];
    }

    /** Reviews: the average star rating researchers gave you, as a percentage. */
    public function reviewsFactor(User $user): array
    {
        $reviews = StudyReview::byResearcher()
            ->where('participant_id', $user->id);

        $count = (clone $reviews)->count();
        $avg   = $count === 0 ? 0 : (float) (clone $reviews)->avg('stars');

        return [
            'value'   => (int) round($avg / 5 * 100),
            'hasData' => $count > 0,
            'detail'  => $count === 0
                ? 'No researcher reviews yet'
                : number_format($avg, 1) . ' stars from ' . $count . ' ' . ($count === 1 ? 'review' : 'reviews'),
        ];
    }

    /**
     * All three factors, their weights, and what each contributes to the total.
     * The reliability page renders straight from this array.
     */
    public function breakdown(User $user): array
    {
        $weights = config('platform.reliability_weights');

        $factors = [
            'attendance' => $this->attendanceFactor($user) + [
                'label' => 'Attendance & show-up rate',
                'desc'  => 'How often you show up to sessions you booked.',
            ],
            'completion' => $this->completionFactor($user) + [
                'label' => 'Study completion rate',
                'desc'  => 'Studies you finished vs. ones you started.',
            ],
            'reviews' => $this->reviewsFactor($user) + [
                'label' => 'Post-session reviews',
                'desc'  => 'Ratings researchers leave after your sessions.',
            ],
        ];

        foreach ($factors as $key => $factor) {
            $weight = (int) ($weights[$key] ?? 0);

            $factors[$key]['weight'] = $weight;
            $factors[$key]['contribution'] = round($factor['value'] * $weight / 100, 1);
        }

        return $factors;
    }

    /** The three contributions added together, rounded to a whole number. */
    public function score(User $user): int
    {
        $total = 0;

        foreach ($this->breakdown($user) as $factor) {
            $total += $factor['contribution'];
        }

        return (int) min(100, round($total));
    }

    /** Recalculate and save all four columns on the profile. */
    public function recalculate(User $user): array
    {
        $breakdown = $this->breakdown($user);

        $score = (int) min(100, round(
            $breakdown['attendance']['contribution']
            + $breakdown['completion']['contribution']
            + $breakdown['reviews']['contribution']
        ));

        $profile = $user->participantProfile;
        $before = $profile->reliability_score;

        $profile->update([
            'rel_attendance'    => $breakdown['attendance']['value'],
            'rel_completion'    => $breakdown['completion']['value'],
            'rel_reviews'       => $breakdown['reviews']['value'],
            'reliability_score' => $score,
        ]);

        return [
            'profile'   => $profile->fresh(),
            'breakdown' => $breakdown,
            'score'     => $score,
            'from'      => $before,
            'changed'   => $before !== $score,
        ];
    }

    // -----------------------------------------------------------------
    // ADMIN OVERRIDE  (added for the API layer)
    // -----------------------------------------------------------------

    /**
     * Work out a score from three raw 0–100 values using the configured
     * weights, without touching the database.
     *
     * Used by the admin override, and useful for "what if" previews.
     */
    public function scoreFromValues(int $attendance, int $completion, int $reviews): int
    {
        $weights = config('platform.reliability_weights');

        $total = $attendance * (int) ($weights['attendance'] ?? 0) / 100
               + $completion * (int) ($weights['completion'] ?? 0) / 100
               + $reviews    * (int) ($weights['reviews']    ?? 0) / 100;

        return (int) min(100, max(0, round($total)));
    }

    /**
     * Admin override — set one or more factors by hand, then recompute and
     * save the cached score from them.
     *
     * This exists so the weighting rule can be demonstrated live: change one
     * factor, watch the total move by exactly its weighted share. Any factor
     * left out of $values keeps its current stored value.
     *
     * Note this deliberately lives in the service, not the controller, so
     * that every write to the reliability columns still goes through one
     * class.
     *
     * @param  array<string,int|null>  $values  keys: rel_attendance, rel_completion, rel_reviews
     */
    public function overrideFactors(User $user, array $values): array
    {
        $profile = $user->participantProfile;
        $before  = $profile->reliability_score;

        $attendance = (int) ($values['rel_attendance'] ?? $profile->rel_attendance);
        $completion = (int) ($values['rel_completion'] ?? $profile->rel_completion);
        $reviews    = (int) ($values['rel_reviews']    ?? $profile->rel_reviews);

        $score = $this->scoreFromValues($attendance, $completion, $reviews);

        $profile->update([
            'rel_attendance'    => $attendance,
            'rel_completion'    => $completion,
            'rel_reviews'       => $reviews,
            'reliability_score' => $score,
        ]);

        return [
            'profile' => $profile->fresh(),
            'score'   => $score,
            'from'    => $before,
            'changed' => $before !== $score,
        ];
    }

    /** A plain-English band for the score, shown next to the gauge. */
    public function band(int $score): array
    {
        return match (true) {
            $score >= 90 => ['label' => 'Excellent', 'tone' => 'ok',
                             'msg' => 'You are prioritised for high-demand paid studies.'],
            $score >= 75 => ['label' => 'Strong', 'tone' => 'expert',
                             'msg' => 'Comfortably competitive for most limited-seat studies.'],
            $score >= 50 => ['label' => 'Building', 'tone' => 'gold',
                             'msg' => 'Attend and finish a few more sessions to climb.'],
            default      => ['label' => 'Needs work', 'tone' => 'flame',
                             'msg' => 'Complete sessions you book to raise your score.'],
        };
    }
}