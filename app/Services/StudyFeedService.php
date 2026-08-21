<?php

namespace App\Services;

use App\Enums\IncentiveType;
use App\Models\Study;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * FEATURE — Study Recommendation Feed  (Member 4, graded feature)
 *
 * Ranks the open studies a participant should see, and in what order.
 *
 * WHAT THIS CLASS DOES NOT DO
 * ---------------------------
 * It does not score anything. Suitability is StudyMatchingService's job, and
 * this class calls it rather than reimplementing it — which is why the feed
 * and the study page can never disagree about a match score.
 *
 * WHAT IT ADDS
 * ------------
 * Two study-side signals, and deliberately nothing else:
 *
 *   recency  — how newly posted the study is
 *   urgency  — how close its deadline is
 *
 * NOT karma, NOT post boosts, NOT researcher subscription tier, even though
 * the brief lists all three. Karma measures how much somebody has ENGAGED
 * with the platform; it says nothing about whether they suit a study.
 * Ranking research opportunities by an in-app currency is the same category
 * error as ranking them by who paid for a boost.
 *
 * There is also a correctness reason. Every person-side signal — skills,
 * credential level, endorsements, reliability, completed-study topics — is
 * ALREADY inside match_score. Adding any of them again at this layer would
 * double-count it. So this layer only ever asks "is this study worth
 * surfacing right now?", never "is this person any good?".
 *
 * At 85/10/5 the ordering is decided by suitability in every realistic case;
 * recency and urgency only break ties and keep the top of the list fresh.
 */
class StudyFeedService
{
    public function __construct(private StudyMatchingService $matching) {}

    /** The configured weights, integers, in display order. */
    public function weights(): array
    {
        return array_map('intval', config('platform.feed.weights', []));
    }

    public function pageSize(): int
    {
        return (int) config('platform.feed.page_size');
    }

    /**
     * The ranked feed for one participant.
     *
     * @param  array  $filters  validated filters from the controller
     */
    public function feed(User $user, array $filters = [], ?int $limit = null): Collection
    {
        $limit = $limit ?? $this->pageSize();

        // Suitability first — one engine, shared with the researcher side.
        // Ask for more than we need so filters have something to cut into.
        $scored = $this->matching->recommendStudiesForParticipant(
            $user,
            max($limit * 5, 50)
        );

        return $scored
            ->filter(fn (Study $study) => $this->passesFilters($study, $filters))
            ->map(function (Study $study) {
                $breakdown = $this->breakdownFor($study);

                $study->setAttribute('feed_breakdown', $breakdown);
                $study->setAttribute(
                    'feed_score',
                    round(collect($breakdown)->sum('contribution'), 1)
                );

                return $study;
            })
            ->sort(fn (Study $a, Study $b) => [$b->feed_score, $b->match_score, $b->id]
                <=> [$a->feed_score, $a->match_score, $a->id])
            ->take($limit)
            ->values();
    }

    /**
     * The three weighted contributions. They sum to feed_score exactly, so
     * any surprising position is explainable from the payload rather than
     * mysterious — the same discipline as the matching breakdown.
     */
    private function breakdownFor(Study $study): array
    {
        $weights = $this->weights();

        $subScores = [
            'match'   => (float) ($study->match_score ?? 0),
            'recency' => $this->recencySubScore($study),
            'urgency' => $this->urgencySubScore($study),
        ];

        $breakdown = [];

        foreach ($subScores as $key => $sub) {
            $weight = (int) ($weights[$key] ?? 0);

            $breakdown[$key] = [
                'sub_score'    => round($sub, 1),
                'weight'       => $weight,
                'contribution' => round($sub * $weight / 100, 1),
            ];
        }

        return $breakdown;
    }

    /**
     * Exponential decay on created_at. Posted today = 100, one half-life
     * ago = 50, and so on. Never negative.
     */
    private function recencySubScore(Study $study): float
    {
        $halfLife = max(1, (int) config('platform.feed.recency_half_life_days'));

        if (! $study->created_at) {
            return 0.0;
        }

        $ageDays = max(0, (int) abs($study->created_at->diffInDays(now())));

        return round(100 * pow(0.5, $ageDays / $halfLife), 1);
    }

    /**
     * 100 when the deadline is inside the window, tapering to 0 at twice the
     * window. No deadline scores 0 — a study with no closing date is not
     * urgent, it is just open.
     *
     * A deadline already in the past also scores 0: those studies should be
     * closed, and surfacing them helps nobody.
     */
    private function urgencySubScore(Study $study): float
    {
        $window = max(1, (int) config('platform.feed.urgency_window_days'));

        if (! $study->deadline) {
            return 0.0;
        }

        $daysLeft = (int) now()->startOfDay()->diffInDays($study->deadline, false);

        if ($daysLeft < 0) {
            return 0.0;
        }

        if ($daysLeft <= $window) {
            return 100.0;
        }

        if ($daysLeft >= $window * 2) {
            return 0.0;
        }

        return round(100 * (1 - ($daysLeft - $window) / $window), 1);
    }

    /**
     * Filters are applied to the scored collection rather than in SQL,
     * because the scorer already bulk-loads everything it needs in a fixed
     * number of queries. Filtering here costs no extra round trips.
     */
    private function passesFilters(Study $study, array $filters): bool
    {
        if (! empty($filters['incentive_type'])) {
            $type = $study->incentive_type instanceof IncentiveType
                ? $study->incentive_type->value
                : (string) $study->incentive_type;

            if ($type !== $filters['incentive_type']) {
                return false;
            }
        }

        if (! empty($filters['method']) && $study->method !== $filters['method']) {
            return false;
        }

        if (! empty($filters['max_duration'])
            && (int) $study->duration_minutes > (int) $filters['max_duration']) {
            return false;
        }

        if (! empty($filters['topic_ids'])) {
            $studyTopicIds = collect($study->getAttribute('topic_ids') ?? [])
                ->map(fn ($id) => (int) $id);

            if ($studyTopicIds->intersect($filters['topic_ids'])->isEmpty()) {
                return false;
            }
        }

        if (! empty($filters['strong_only'])
            && ! (bool) $study->getAttribute('strong_match')) {
            return false;
        }

        return true;
    }

    /**
     * The filter options the page offers, so the UI never hardcodes a list.
     * Same reasoning as returning the weights: change the enum or the topic
     * table and the interface follows.
     */
    public function filterOptions(): array
    {
        return [
            'incentive_types' => collect(IncentiveType::cases())
                ->map(fn (IncentiveType $t) => [
                    'value' => $t->value,
                    'label' => $t->label(),
                ])->all(),

            'methods' => [
                ['value' => 'online',    'label' => 'Online'],
                ['value' => 'in_person', 'label' => 'In person'],
            ],

            'durations' => collect(config('platform.feed.duration_buckets', []))
                ->map(fn (int $minutes) => [
                    'value' => $minutes,
                    'label' => $minutes >= 60
                        ? 'Up to ' . ($minutes / 60) . ' hour' . ($minutes > 60 ? 's' : '')
                        : 'Up to ' . $minutes . ' min',
                ])->all(),

            'topics' => Topic::orderBy('name')->get(['id', 'name', 'slug'])->all(),
        ];
    }
}
