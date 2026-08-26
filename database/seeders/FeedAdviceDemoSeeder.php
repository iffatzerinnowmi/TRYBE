<?php

namespace Database\Seeders;

use App\Enums\CredentialLevel;
use App\Enums\IncentiveType;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\NotificationPreference;
use App\Models\ParticipantProfile;
use App\Models\SkillGapAdvice;
use App\Models\Study;
use App\Models\StudyMatchCriteria;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Services\StudyMatchingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A participant whose skill-gap advice has never been generated. (Member 4)
 *
 * WHY THIS EXISTS
 * ---------------
 * SkillGapService::generate() hashes the analysis inputs and refuses to spend
 * an API call when the hash has not moved:
 *
 *     if ($record->exists && $record->isCurrent($hash)) { return $record; }
 *
 * That is the cost control working exactly as designed — and it means a
 * participant whose advice you have already generated will never generate it
 * again in front of an audience. The button appears to do nothing.
 *
 * So: a fresh participant, with a DIFFERENT missing skill from the existing
 * demo accounts, and no stored advice at all. Clicking "Get advice" makes a
 * real, live call.
 *
 * IT ALSO CLEARS ITS OWN ADVICE ROW ON EVERY RUN, so re-running the seeder
 * resets the demo rather than leaving you in the same position an hour later.
 *
 * ONE DEVIATION FROM decision.md §9, DELIBERATE AND ANNOUNCED
 * ----------------------------------------------------------
 * §9 says only DatabaseSeeder creates users. This creates one participant and
 * three studies, for the same reason FeedDemoSeeder does: reusing an existing
 * account means either stealing somebody's demo or wiping advice that another
 * member may be relying on. Everything it makes is namespaced and idempotent.
 */
class FeedAdviceDemoSeeder extends Seeder
{
    private const EMAIL    = 'feed.live@trybe.test';
    private const NAME     = 'Tanisha Rahman';
    private const PASSWORD = 'password';

    /**
     * What she already has.
     *
     * Deliberately overlapping but not covering the studies below, so every
     * study is a NEAR MISS rather than a match or a hopeless case.
     */
    private const HER_SKILLS = 'usability testing, diary logging';

    /**
     * Three studies, each missing exactly one skill she does not have.
     *
     * The missing skills are all different from the existing demo accounts'
     * (`bilingual interviewing`, `think-aloud protocol`) so the model has a
     * genuinely new gap to write about and you can see fresh advice rather
     * than a cached-looking repeat.
     */
    private const STUDIES = [
        [
            'title'       => 'Eye-tracking pilot — reading on screens',
            'description' => 'Sit for a calibrated eye-tracking session while reading short articles.',
            'category'    => 'Perception',
            'skills'      => ['usability testing', 'eye-tracking calibration'],
            'duration'    => 45,
            'days_old'    => 2,
        ],
        [
            'title'       => 'Card-sorting study for a health portal',
            'description' => 'Group and label content cards, then explain your grouping.',
            'category'    => 'Usability',
            'skills'      => ['usability testing', 'card sorting'],
            'duration'    => 30,
            'days_old'    => 6,
        ],
        [
            'title'       => 'Two-week mood and activity diary',
            'description' => 'Short structured entries twice a day, with a weekly check-in call.',
            'category'    => 'Health',
            'skills'      => ['diary logging', 'structured interview'],
            'duration'    => 20,
            'days_old'    => 11,
        ],
    ];

    public function run(): void
    {
        $researcher = User::where('role', UserRole::RESEARCHER->value)
            ->where('verification_status', 'verified')
            ->orderBy('id')
            ->first();

        if (! $researcher) {
            $this->command?->warn('No verified researcher — run DatabaseSeeder first. Skipping.');

            return;
        }

        $user = $this->participant();

        $this->seedStudies($researcher);
        $this->seedHistory($user);

        /*
        | The point of the whole seeder: no stored advice, so the hash gate
        | has nothing to compare against and the next click makes a real call.
        | Cleared on every run so this is repeatable.
        */
        $deleted = SkillGapAdvice::where('user_id', $user->id)->delete();

        $this->report($user, $deleted);
    }

    // =================================================================

    private function participant(): User
    {
        $user = User::firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name'                => self::NAME,
                'phone'               => '01730000606',
                'password'            => Hash::make(self::PASSWORD),
                'role'                => UserRole::PARTICIPANT->value,
                'verification_status' => 'unverified',
                'location'            => 'Dhaka, Bangladesh',
            ]
        );

        ParticipantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'age'                     => 25,
                'gender'                  => 'Woman',
                'occupation'              => 'Design student',
                'interests'               => 'Usability, accessibility, health',
                'skills'                  => self::HER_SKILLS,
                'rel_attendance'          => 90,
                'rel_completion'          => 88,
                'rel_reviews'             => 84,
                'reliability_score'       => 88,
                'completed_studies_count' => 2,
                'credential_level'        => CredentialLevel::BRONZE->value,
                'last_active_week'        => now()->startOfWeek(),
                'endorsement_count'       => 1,
                'is_verified_participant' => false,
            ]
        );

        // Without this row NotificationService treats every notification as
        // switched off — see StudyCompletionService::ensurePreferences().
        NotificationPreference::firstOrCreate(['user_id' => $user->id]);

        return $user;
    }

    /** Three open studies with criteria that leave exactly one gap each. */
    private function seedStudies(User $researcher): void
    {
        foreach (self::STUDIES as $index => $spec) {
            $study = Study::updateOrCreate(
                ['title' => $spec['title']],
                [
                    'researcher_id'       => $researcher->id,
                    'description'         => $spec['description'],
                    'category'            => $spec['category'],
                    'method'              => 'online',
                    'duration_minutes'    => $spec['duration'],
                    'incentive_type'      => IncentiveType::VOLUNTEER,
                    'compensation_amount' => 0,
                    'slots'               => 20,
                    'deadline'            => now()->addDays(20 + $index * 5)->toDateString(),
                    'status'              => StudyStatus::OPEN,
                    'participants_count'  => 0,
                    'irb_document_path'   => 'irb-documents/feed-live-demo.pdf',
                    'irb_flagged'         => false,
                    'irb_board'           => 'BRAC University IRB',
                    'irb_ref'             => 'IRB-2026-3' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                ]
            );

            $study->created_at = now()->subDays($spec['days_old']);
            $study->save();

            StudyMatchCriteria::updateOrCreate(
                ['study_id' => $study->id],
                [
                    'age_min'           => 18,
                    'age_max'           => 45,
                    'location'          => null,     // keep age/location out of it
                    'credential_min'    => CredentialLevel::NONE->value,
                    'required_skills'   => $spec['skills'],
                    'availability_days' => 60,
                ]
            );
        }
    }

    /**
     * Two completed studies, so she has topic history and a real credential
     * level rather than scoring as a cold start.
     *
     * Uses CLOSED studies only, so this can never collide with the three
     * above — a participation on one of those would exclude it from her feed
     * and there would be no near miss left to analyse.
     */
    private function seedHistory(User $user): void
    {
        $closed = Study::where('status', StudyStatus::CLOSED)->orderBy('id')->take(2)->get();

        foreach ($closed as $i => $study) {
            StudyParticipation::updateOrCreate(
                ['study_id' => $study->id, 'participant_id' => $user->id],
                ['stage' => PipelineStage::COMPLETED, 'completed_at' => now()->subWeeks($i + 2)]
            );
        }
    }

    // =================================================================

    /**
     * Report, and CHECK ITS OWN WORK.
     *
     * Whether a study lands in the near-miss band depends on the whole
     * eight-factor engine against live data, so "it should be in the band" is
     * a prediction, not a fact. This runs the real matcher and prints what
     * actually happened — if the demo will not work, you find out here rather
     * than in front of an examiner.
     */
    private function report(User $user, int $deleted): void
    {
        $this->command?->newLine();
        $this->command?->info('Fresh feed / advice demo account created.');
        $this->command?->line('  Log in as   ' . self::EMAIL . '   /   ' . self::PASSWORD);
        $this->command?->line('  Her skills: ' . self::HER_SKILLS);

        if ($deleted) {
            $this->command?->line('  Cleared previous advice, so the next click makes a live call.');
        }

        $this->command?->newLine();

        $matching  = app(StudyMatchingService::class);
        $floor     = (int) config('platform.feed.near_miss_floor');
        $threshold = $matching->strongThreshold();

        $scored = $matching->recommendStudiesForParticipant($user, 50);

        $rows = $scored->map(function (Study $s) use ($floor, $threshold) {
            $score   = (float) $s->match_score;
            $missing = $s->match_factors['skills']['missing'] ?? [];

            return [
                Str::limit($s->title, 34),
                number_format($score, 1),
                $score >= $floor && $score < $threshold ? 'NEAR MISS' : ($score >= $threshold ? 'qualifies' : 'too far'),
                implode(', ', $missing) ?: '—',
            ];
        })->take(8)->all();

        $this->command?->table(['study', 'score', 'band', 'missing skills'], $rows ?: [['(none)', '', '', '']]);

        $inBand = $scored->filter(
            fn (Study $s) => $s->match_score >= $floor && $s->match_score < $threshold
                && ! empty($s->match_factors['skills']['missing'])
        )->count();

        if ($inBand > 0) {
            $this->command?->info("✓ {$inBand} study/studies are near misses with a real skill gap.");
            $this->command?->line('  Open /participant/feed and press "Get advice".');
        } else {
            $this->command?->warn('✗ No near miss with a skill gap — the coach will have nothing to say.');
            $this->command?->line('  Usually means the three studies scored too high or too low.');
            $this->command?->line('  Diagnose with:');
            $this->command?->line('      php artisan trybe:skillgap:explain --email=' . self::EMAIL);
        }

        $this->command?->newLine();
        $this->command?->warn(
            'decision.md §9: this seeder creates one participant and three studies. '
            . 'Reusing an existing account would mean wiping advice another member may '
            . 'be demoing. Tell the group.'
        );
    }
}
