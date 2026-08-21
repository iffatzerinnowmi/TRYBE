<?php

namespace Database\Seeders;

use App\Enums\CompletionClaimStatus;
use App\Enums\CredentialLevel;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\NotificationPreference;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\StudyCompletionClaim;
use App\Models\StudyParticipation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo data for "Complete a Study"  (Member 4).
 *
 * WHY IT IS NEEDED
 * ----------------
 * A study can only be claimed complete from stage `confirmed` or `scheduled`.
 * Every participation the other seeders create sits at `completed` — the sole
 * exception being one `no_show`. So out of the box the Complete button is
 * correctly disabled everywhere and the feature cannot be shown at all.
 *
 * WHAT IT BUILDS
 * --------------
 * One dedicated participant, placed on four different studies so that every
 * state of the button is visible on a single page without clicking around:
 *
 *   confirmed              -> button LIVE
 *   scheduled              -> button LIVE  (the other claimable stage)
 *   confirmed + a claim    -> "Submitted ✓ — awaiting confirmation"
 *   applied                -> "Not confirmed yet" (disabled)
 *   completed              -> "Completed ✓", and fills the Completed panel
 *
 * ONE DEVIATION FROM decision.md §9, DELIBERATE AND ANNOUNCED
 * ----------------------------------------------------------
 * §9 says only DatabaseSeeder creates users, and the reason is good: four
 * people seeding their own accounts turns one shared database into four
 * disconnected islands.
 *
 * This creates ONE participant, because the alternative is worse. Putting an
 * existing account at `confirmed` means either picking someone whose demo
 * belongs to another member, or rewinding a `completed` participation — and
 * completions drive credential level, karma, reliability and paid-study
 * progress, so rewinding one silently corrupts four other features.
 *
 * It is firstOrCreate on the email, never overwrites an existing account, and
 * prints what it did. Same pattern, and same declaration, as FeedDemoSeeder.
 */
class CompletionDemoSeeder extends Seeder
{
    private const EMAIL    = 'complete.demo@trybe.test';
    private const NAME     = 'Nabila Haque';
    private const PASSWORD = 'password';

    /** Stages we refuse to overwrite — see the class docblock. */
    private const PROTECTED_STAGES = [PipelineStage::COMPLETED, PipelineStage::PAID];

    public function run(): void
    {
        $user    = $this->participant();
        $studies = $this->openStudies();

        if ($studies->isEmpty()) {
            $this->command?->warn('No open studies found — run DatabaseSeeder first. Skipping.');

            return;
        }

        $plan = [
            [PipelineStage::CONFIRMED, false, 'button LIVE'],
            [PipelineStage::SCHEDULED, false, 'button LIVE (scheduled)'],
            [PipelineStage::CONFIRMED, true,  'claim already submitted'],
            [PipelineStage::APPLIED,   false, 'disabled — not confirmed yet'],
            [PipelineStage::COMPLETED, false, 'disabled — already complete'],
        ];

        $rows = [];

        foreach ($plan as $index => [$stage, $withClaim, $note]) {
            $study = $studies->get($index);

            // Fewer open studies than states: show what we can rather than
            // failing, and say which states are missing.
            if (! $study) {
                $this->command?->warn(
                    'Only ' . $studies->count() . ' open studies — "' . $note . '" not seeded.'
                );
                continue;
            }

            if (! $this->place($user, $study, $stage)) {
                continue;
            }

            if ($withClaim) {
                $this->claim($user, $study);
            }

            $rows[] = sprintf('  %-32s %-11s %s', Str::limit($study->title, 30), $stage->value, $note);
        }

        $this->report($rows);
    }

    // =================================================================

    /**
     * The demo participant. Created once; the profile is refreshed on every
     * run so the demo is reproducible, but the USER row is never rewritten —
     * an existing account keeps its password and its id, which matters if you
     * have already logged in as it.
     */
    private function participant(): User
    {
        $user = User::firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name'                => self::NAME,
                'phone'               => '01730000505',
                'password'            => Hash::make(self::PASSWORD),
                'role'                => UserRole::PARTICIPANT->value,
                'verification_status' => 'unverified',
                'location'            => 'Dhaka, Bangladesh',
            ]
        );

        ParticipantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'age'                     => 26,
                'gender'                  => 'Woman',
                'occupation'              => 'Postgraduate student',
                'interests'               => 'Usability, health, sleep research',
                'skills'                  => 'structured interview, diary logging',
                'rel_attendance'          => 88,
                'rel_completion'          => 84,
                'rel_reviews'             => 80,
                'reliability_score'       => 84,
                'completed_studies_count' => 1,
                'credential_level'        => CredentialLevel::BRONZE->value,
                'last_active_week'        => now()->startOfWeek(),
                'endorsement_count'       => 1,
                'is_verified_participant' => false,
            ]
        );

        /*
        | Give them a notification preferences row explicitly.
        |
        | NotificationService::wants() creates one via firstOrCreate and then
        | reads it WITHOUT re-reading from the database — so the notify_*
        | defaults, which live on the columns rather than on the model, come
        | back as NULL and every notification is treated as switched off.
        | Seeding the row here means the researcher actually gets told when
        | this participant claims completion.
        */
        NotificationPreference::firstOrCreate(['user_id' => $user->id]);

        return $user;
    }

    /**
     * Open studies, most recently posted first, so a fresh demo uses the
     * studies most likely to look sensible on screen.
     */
    private function openStudies()
    {
        return Study::where('status', StudyStatus::OPEN)
            ->orderByDesc('id')
            ->get()
            ->values();
    }

    /** Returns false when it declined to touch an existing row. */
    private function place(User $user, Study $study, PipelineStage $stage): bool
    {
        $existing = StudyParticipation::where('study_id', $study->id)
            ->where('participant_id', $user->id)
            ->first();

        if ($existing
            && in_array($existing->stage, self::PROTECTED_STAGES, true)
            && ! in_array($stage, self::PROTECTED_STAGES, true)) {
            $this->command?->warn(
                '  skipped "' . Str::limit($study->title, 30) . '" — already '
                . $existing->stage->value
            );

            return false;
        }

        StudyParticipation::updateOrCreate(
            ['study_id' => $study->id, 'participant_id' => $user->id],
            array_merge(
                ['stage' => $stage],
                $stage === PipelineStage::COMPLETED ? ['completed_at' => now()->subWeek()] : []
            )
        );

        return true;
    }

    private function claim(User $user, Study $study): void
    {
        StudyCompletionClaim::updateOrCreate(
            ['study_id' => $study->id, 'participant_id' => $user->id],
            [
                'status'         => CompletionClaimStatus::SUBMITTED,
                'form_url'       => config('platform.completion.form_url'),
                'opened_form_at' => now()->subMinutes(20),
                'submitted_at'   => now()->subMinutes(15),
                'note'           => 'Submitted the form — the last question was a little unclear.',
                'reviewed_at'    => null,
                'reviewed_by'    => null,
            ]
        );
    }

    private function report(array $rows): void
    {
        $this->command?->newLine();
        $this->command?->info('Completion demo seeded.');
        $this->command?->newLine();
        $this->command?->line('  Log in as   ' . self::EMAIL . '   /   ' . self::PASSWORD);
        $this->command?->line('  Then open   /participant/studies   (navbar → "My studies")');
        $this->command?->newLine();

        foreach ($rows as $row) {
            $this->command?->line($row);
        }

        $this->command?->newLine();
        $this->command?->warn(
            'decision.md §9 says only DatabaseSeeder creates users. This creates one '
            . 'participant on purpose, because the alternative is rewinding somebody\'s '
            . 'completed study — and completions drive credentials, karma, reliability '
            . 'and paid-study progress. Tell the group.'
        );
    }
}
