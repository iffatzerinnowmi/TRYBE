<?php

namespace Database\Seeders;

use App\Enums\PipelineStage;
use App\Enums\ReferralStatus;
use App\Enums\UserRole;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\ResearcherPostCredit;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Database\Seeder;

/**
 * Demo data for the referral system  (Member 4).
 *
 * THIS SEEDER CREATES NO USERS.
 *
 * It attaches to whoever DatabaseSeeder already made — four people each
 * seeding their own users produces four disconnected islands in one shared
 * database.
 *
 * It seeds every state the page can show, so a single screenshot proves the
 * whole feature:
 *
 *   - a participant mid-progress (2 of 3)                 <- the interesting one
 *   - a participant at 3 of 3 with the tier reward granted <- proves the reward fires
 *   - a referral still pending
 *   - a flagged referral                                   <- abuse handling is real, not theoretical
 *   - a researcher -> researcher pair with a post credit
 *   - a mixed-role pair that pays nothing                  <- proves counts_towards_reward renders
 *
 * Codes are generated through ReferralService rather than inserted as
 * literals, so the seeder exercises the real path.
 */
class ReferralSeeder extends Seeder
{
    /**
     * Completions this seeder had to add so a referral could qualify.
     * Reported at the end, because study_participations belongs to Member 2
     * and adding rows to it on the shared database changes other people's
     * numbers.
     */
    private array $manufactured = [];

    public function run(): void
    {
        $referrals = app(ReferralService::class);

        $participants = User::where('role', UserRole::PARTICIPANT->value)
            ->whereHas('participantProfile')
            ->orderBy('id')
            ->get();

        $researchers = User::where('role', UserRole::RESEARCHER->value)
            ->orderBy('id')
            ->get();

        if ($participants->count() < 5 || $researchers->count() < 2) {
            $this->command?->warn(
                'ReferralSeeder needs at least 5 participants and 2 researchers — run DatabaseSeeder first. Skipping.'
            );

            return;
        }

        // Everyone who matters gets a code, through the real generator.
        foreach ($participants->take(3)->merge($researchers->take(2)) as $user) {
            $referrals->codeFor($user);
        }

        /* -----------------------------------------------------------------
         | Participant A — mid-progress. Two qualified, one still pending.
         | ----------------------------------------------------------------- */
        $referrerA = $participants[0];

        $this->link($referrerA, $participants[1], true);
        $this->link($referrerA, $participants[2], true);
        $this->link($referrerA, $participants[3], false);   // signed up, not qualified

        /* -----------------------------------------------------------------
         | Participant B — pushed all the way to the threshold, so sync()
         | actually grants the tier reward and the demo shows an unlocked
         | one rather than only a progress bar.
         |
         | Counts SUCCESSFUL links, because referred_user_id is unique and
         | anyone already referred above is silently skipped. Looping a fixed
         | number of times would quietly land short.
         | ----------------------------------------------------------------- */
        $referrerB = $participants[1];
        $required  = (int) config('platform.referrals.required_to_unlock');
        $linked    = 0;

        foreach ($participants as $candidate) {
            if ($linked >= $required) {
                break;
            }

            if ($this->link($referrerB, $candidate, true)) {
                $linked++;
            }
        }

        if ($linked < $required) {
            $this->command?->warn(
                'Only ' . $linked . ' of ' . $required . ' referrals could be seeded for the '
                . 'reward demo — DatabaseSeeder does not have enough unreferred participants.'
            );
        }

        /* -----------------------------------------------------------------
         | A flagged referral, so the abuse handling is visible on the page
         | rather than only described in the report.
         | ----------------------------------------------------------------- */
        $flagged = $participants->last();

        if ($flagged && ! Referral::where('referred_user_id', $flagged->id)->exists()) {
            Referral::create([
                'referrer_id'      => $referrerA->id,
                'referred_user_id' => $flagged->id,
                'status'           => ReferralStatus::FLAGGED,
                'code_used'        => $referrals->codeFor($referrerA)->code,
                'signed_up_at'     => now()->subDays(4),
            ]);
        }

        /* -----------------------------------------------------------------
         | Researcher -> researcher, which earns a free post credit.
         | ----------------------------------------------------------------- */
        $this->link($researchers[0], $researchers[1], true);

        /* -----------------------------------------------------------------
         | A mixed pair: recorded and shown, but pays nothing.
         | ----------------------------------------------------------------- */
        $mixedTarget = $participants->first(function (User $p) {
            return ! Referral::where('referred_user_id', $p->id)->exists();
        });

        if ($mixedTarget) {
            $this->link($researchers[0], $mixedTarget, true);
        }

        /* -----------------------------------------------------------------
         | Now run the real sync. Rewards are granted by the service, not
         | inserted by hand — so if the reward logic is broken, the seeded
         | demo is visibly broken too rather than papering over it.
         | ----------------------------------------------------------------- */
        foreach (Referral::select('referrer_id')->distinct()->pluck('referrer_id') as $id) {
            if ($user = User::find($id)) {
                $referrals->sync($user);
            }
        }

        $this->command?->info(
            'Referrals seeded: ' . Referral::count() . ' referrals, '
            . ReferralReward::count() . ' rewards, '
            . ResearcherPostCredit::count() . ' credit movements.'
        );

        if ($this->manufactured) {
            $this->command?->warn(
                'This seeder added ' . count($this->manufactured) . ' study completion(s) so referrals '
                . 'could qualify. study_participations is Member 2\'s table and this also moves '
                . 'Member 1\'s credential and reliability numbers — tell the group:'
            );

            foreach ($this->manufactured as $line) {
                $this->command?->warn('    ' . $line);
            }
        }
    }

    /**
     * Record a referral, and give the referred user a qualifying action if
     * they are meant to have one.
     *
     * Qualification is DERIVED by the service, so this seeds the underlying
     * evidence (a completed participation / a posted study) rather than
     * setting status = qualified directly. That way the seeder tests the
     * real code path.
     */
    private function link(User $referrer, User $referred, bool $shouldQualify): bool
    {
        if ($referrer->id === $referred->id) {
            return false;
        }

        if (Referral::where('referred_user_id', $referred->id)->exists()) {
            return false;   // referred_user_id is unique — one referrer per person
        }

        Referral::create([
            'referrer_id'      => $referrer->id,
            'referred_user_id' => $referred->id,
            'status'           => ReferralStatus::PENDING,
            'code_used'        => app(ReferralService::class)->codeFor($referrer)->code,
            'signed_up_at'     => now()->subDays(random_int(5, 30)),
        ]);

        if (! $shouldQualify) {
            return true;
        }

        if ($referred->role === UserRole::RESEARCHER) {
            // A researcher qualifies by posting a study, and every seeded
            // researcher already has some. Nothing to add.
            return true;
        }

        // A participant qualifies by COMPLETING a study. If they already
        // have a completion, use it — that is the whole point of attaching
        // to existing data rather than manufacturing it.
        if ($this->alreadyCompletedSomething($referred)) {
            return true;
        }

        /*
        | ---------------------------------------------------------------
        | HEADS-UP: this is the one place this seeder writes a column it
        | does not own — study_participations.stage belongs to Member 2's
        | pipeline.
        |
        | On the SHARED database that matters: adding a completion changes
        | the participant's credential count and reliability inputs, which
        | are Member 1's numbers. So it is done only as a last resort, and
        | every row it adds is named in the console output so nobody has to
        | discover it from a changed demo.
        |
        | If the group would rather it never happened, set
        | TRYBE_REFERRAL_SEED_COMPLETIONS=false in .env and the seeder will
        | seed referrals without forcing any of them to qualify.
        | ---------------------------------------------------------------
        */
        if (! env('TRYBE_REFERRAL_SEED_COMPLETIONS', true)) {
            return true;
        }

        $study = Study::inRandomOrder()->first();

        if (! $study) {
            return true;
        }

        StudyParticipation::updateOrCreate(
            ['study_id' => $study->id, 'participant_id' => $referred->id],
            [
                'stage'        => PipelineStage::COMPLETED->value,
                'completed_at' => now()->subDays(random_int(1, 20)),
            ]
        );

        $this->manufactured[] = $referred->name . ' -> "' . $study->title . '"';

        return true;
    }

    /** Do they already have a completion we can lean on? */
    private function alreadyCompletedSomething(User $user): bool
    {
        return StudyParticipation::where('participant_id', $user->id)
            ->whereIn('stage', [PipelineStage::COMPLETED->value, PipelineStage::PAID->value])
            ->exists();
    }
}
