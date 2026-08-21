<?php

namespace App\Console\Commands;

use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Services\SkillGapService;
use App\Services\StudyMatchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Reconcile the skill-gap panel against the studies it claims to come from.
 *                                                              (Member 4)
 *
 *     php artisan trybe:skillgap:explain --email=complete.demo@trybe.test
 *
 * WHY THIS EXISTS
 * ---------------
 * The coach aggregates missing skills across every study in the near-miss
 * band. The feed shows only the top few studies by score. So "the coach names
 * a skill I cannot find on any study I can see" is the expected experience,
 * not necessarily a bug — but the two ARE derived from one collection, so
 * they must reconcile exactly.
 *
 * This prints both sides: every scored study with its required skills and
 * what is missing, and then the aggregate the panel displays. If the totals
 * at the bottom do not match the rows above, that is a real bug and this
 * shows it in one screen.
 */
class ExplainSkillGap extends Command
{
    protected $signature = 'trybe:skillgap:explain
                            {--email= : The participant to explain}';

    protected $description = 'Show every study behind the skill-gap panel, and reconcile the totals';

    public function handle(StudyMatchingService $matching, SkillGapService $gap): int
    {
        $email = $this->option('email');

        $user = $email
            ? User::where('email', $email)->first()
            : null;

        if (! $user) {
            $this->error('Pass --email=someone@trybe.test');

            return self::FAILURE;
        }

        $profile = $user->participantProfile;

        if (! $profile) {
            $this->error("{$user->email} has no participant profile.");

            return self::FAILURE;
        }

        $floor     = (int) config('platform.feed.near_miss_floor');
        $threshold = $matching->strongThreshold();

        $this->newLine();
        $this->line("Participant : {$user->email}");
        $this->line('Their skills: ' . (implode(', ', $matching->normaliseSkillList($profile->skills)) ?: '(none)'));
        $this->line("Near-miss band: {$floor} – " . ($threshold - 1) . '  (>= ' . $threshold . ' means they already qualify)');
        $this->newLine();

        /*
        | Studies they are already IN are excluded from recommendations, so
        | they cannot appear in the coach either. Worth printing, because
        | "the gap disappeared after I seeded participations" is exactly the
        | confusion this command is meant to settle.
        */
        $inStudies = StudyParticipation::where('participant_id', $user->id)
            ->pluck('study_id');

        if ($inStudies->isNotEmpty()) {
            /*
            | Only the COMMITTED stages are excluded from recommendations —
            | confirmed, scheduled, completed, paid. `applied` and `screened`
            | are not, on the grounds that applying is a request rather than
            | a place in a study. So a study can appear both here and in the
            | table below, and that is correct rather than a leak.
            */
            $rows = StudyParticipation::with('study:id,title')
                ->where('participant_id', $user->id)
                ->get()
                ->map(function (StudyParticipation $p) {
                    $committed = in_array($p->stage->value, [
                        'confirmed', 'scheduled', 'completed', 'paid',
                    ], true);

                    return [
                        '#' . $p->study_id,
                        Str::limit($p->study?->title ?? '?', 40),
                        $p->stage->value,
                        $committed ? 'excluded from feed' : 'still recommended',
                    ];
                })->all();

            $this->line('Studies this participant is already in:');
            $this->table(['id', 'study', 'stage', 'effect'], $rows);
            $this->newLine();
        }

        // The same call the coach makes.
        $scored = $matching->recommendStudiesForParticipant($user, 50);

        $rows       = [];
        $aggregate  = [];
        $nearMisses = 0;

        $fixed   = (array) config('platform.feed.non_actionable_factors', []);
        $minLoss = (float) config('platform.feed.min_actionable_loss', 1.0);

        foreach ($scored as $study) {
            $factors  = $study->match_factors ?? [];
            $skills   = $factors['skills'] ?? [];
            $required = $skills['matched'] ?? [];
            $missing  = $skills['missing'] ?? [];

            $score  = (float) $study->match_score;
            $inBand = $score >= $floor && $score < $threshold;

            // Mirrors SkillGapService::analyse(). Points lost to things the
            // participant can actually change; age and location excluded.
            $loss  = 0.0;
            $worst = null;
            $worstLoss = -1.0;

            foreach ($factors as $key => $factor) {
                if (! ($factor['applicable'] ?? false) || in_array($key, $fixed, true)) {
                    continue;
                }

                $f = (100 - (float) ($factor['sub_score'] ?? 0))
                     * (float) ($factor['effective_weight'] ?? 0) / 100;

                $loss += $f;

                if ($f > $worstLoss) {
                    $worstLoss = $f;
                    $worst     = $key;
                }
            }

            $counts = $inBand && $loss >= $minLoss;

            if ($counts) {
                $nearMisses++;

                foreach ($missing as $skill) {
                    $aggregate[$skill] = ($aggregate[$skill] ?? 0) + 1;
                }
            }

            $rows[] = [
                $study->id,
                Str::limit($study->title, 26),
                number_format($score, 1),
                $counts
                    ? 'NEAR MISS'
                    : ($score >= $threshold
                        ? 'qualifies'
                        : ($inBand ? 'nothing actionable' : 'too far')),
                number_format($loss, 1),
                $worst ?? '—',
                implode(', ', $missing) ?: '—',
            ];
        }

        // Ordered the way the panel orders them: biggest closeable gap first.
        usort($rows, fn ($a, $b) => (float) $b[4] <=> (float) $a[4]);

        $this->table(
            ['id', 'study', 'score', 'band', 'closeable', 'worst factor', 'missing skills'],
            $rows ?: [['', '(no studies scored)', '', '', '', '', '']]
        );

        $this->line("Studies scored: " . count($rows) . " · in the near-miss band: {$nearMisses}");
        $this->newLine();

        // What the panel actually displays.
        $analysis = $gap->analyse($user);

        $panel = collect($analysis['missing_skills'] ?? [])
            ->map(fn ($r) => [$r['skill'], $r['blocks_studies']])
            ->all();

        $this->line('What the coach panel shows:');
        $this->table(['skill', 'blocks_studies'], $panel ?: [['(nothing)', '']]);

        // The reconciliation. These two are built from one collection, so a
        // mismatch is a genuine bug rather than a misunderstanding.
        arsort($aggregate);
        $mine = collect($aggregate)->map(fn ($n, $s) => [$s, $n])->values()->all();

        $matches = collect($panel)->sortBy(0)->values()->all()
                === collect($mine)->sortBy(0)->values()->all();

        $this->newLine();

        if ($matches) {
            $this->info('✓ Reconciles. The panel is the sum of the NEAR MISS rows above.');
            $this->line('  If a skill there is not on any study you can see in the feed, that is');
            $this->line('  because the feed shows your BEST matches and the coach analyses the');
            $this->line('  ones just out of reach — different, lower-ranked studies.');
        } else {
            $this->error('✗ MISMATCH between the rows above and the panel. This is a bug.');
            $this->line('  Computed here: ' . json_encode($mine));
            $this->line('  Panel shows  : ' . json_encode($panel));
        }

        return self::SUCCESS;
    }
}
