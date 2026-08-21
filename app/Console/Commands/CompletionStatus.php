<?php

namespace App\Console\Commands;

use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\Study;
use App\Models\StudyCompletionClaim;
use App\Models\StudyParticipation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * "Who can complete a study right now, and if nobody, why not?"  (Member 4)
 *
 * Written as a command rather than a tinker snippet for two reasons: tinker
 * one-liners need shell escaping that differs between PowerShell, cmd and
 * bash — and half the time the escaping is the bug — and this needs running
 * more than once.
 *
 *   php artisan trybe:completion:status
 *   php artisan trybe:completion:status --fix
 *   php artisan trybe:completion:status --fix --email=sadia@trybe.test
 */
class CompletionStatus extends Command
{
    protected $signature = 'trybe:completion:status
                            {--fix : Put a participant on an open study at `confirmed`}
                            {--email= : Which participant to use with --fix}';

    protected $description = 'Show who can claim study completion, and why the button may be hidden';

    public function handle(): int
    {
        $this->newLine();

        if (! $this->preflight()) {
            return self::FAILURE;
        }

        $this->stageBreakdown();
        $ready = $this->readyToComplete();
        $this->claims();

        if ($this->option('fix')) {
            $this->fix();

            return self::SUCCESS;
        }

        if ($ready === 0) {
            $this->newLine();
            $this->warn('Nobody can complete a study right now.');
            $this->line('  Every seeded participation is at `completed`, and a study can only be');
            $this->line('  claimed from `confirmed` or `scheduled`. Fix it with:');
            $this->newLine();
            $this->line('      php artisan trybe:completion:status --fix');
        }

        return self::SUCCESS;
    }

    // =================================================================

    /** The two things that make the button vanish even when data is right. */
    private function preflight(): bool
    {
        if (! Schema::hasTable('study_completion_claims')) {
            $this->error('The study_completion_claims table does not exist.');
            $this->line('  Run:  php artisan migrate');

            return false;
        }

        $this->line('<fg=green>✓</> study_completion_claims table exists');

        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            $this->warn('✗ public/build/manifest.json is missing — the front end was never built.');
            $this->line('  completion.js will not load and no button can appear. Run:  npm run build');
        } elseif (! str_contains((string) file_get_contents($manifest), 'completion')) {
            $this->warn('✗ The built bundle predates completion.js. Run:  npm run build');
        } else {
            $this->line('<fg=green>✓</> front end built and includes completion.js');
        }

        $this->newLine();

        return true;
    }

    private function stageBreakdown(): void
    {
        $rows = StudyParticipation::selectRaw('stage, COUNT(*) as total')
            ->groupBy('stage')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                $r->stage instanceof PipelineStage ? $r->stage->value : (string) $r->stage,
                $r->total,
                in_array((string) ($r->stage instanceof PipelineStage ? $r->stage->value : $r->stage),
                    (array) config('platform.completion.claimable_stages'), true) ? 'claimable' : '',
            ])->all();

        $this->line('Participations by stage:');
        $this->table(['stage', 'count', ''], $rows ?: [['(none)', 0, '']]);
    }

    private function readyToComplete(): int
    {
        $stages = (array) config('platform.completion.claimable_stages');

        $rows = StudyParticipation::with(['participant:id,name,email', 'study:id,title,status'])
            ->whereIn('stage', $stages)
            ->get()
            ->filter(fn ($p) => $p->participant && $p->study)
            ->map(fn ($p) => [
                $p->participant->email,
                \Illuminate\Support\Str::limit($p->study->title, 34),
                $p->stage->value,
                StudyCompletionClaim::where('study_id', $p->study_id)
                    ->where('participant_id', $p->participant_id)
                    ->exists() ? 'claim submitted' : 'button LIVE',
            ])->values()->all();

        $this->newLine();
        $this->line('Participants who can claim completion:');
        $this->table(['email', 'study', 'stage', 'state'], $rows ?: [['(nobody)', '', '', '']]);

        return count($rows);
    }

    private function claims(): void
    {
        $count = StudyCompletionClaim::count();

        $this->line("Completion claims on record: {$count}");
    }

    /**
     * Put somebody on an open study at `confirmed`.
     *
     * Chooses a study they are NOT already in, so it can never rewind an
     * existing participation — demoting somebody's completed study to make a
     * button appear would quietly corrupt credential level, karma and
     * reliability, which all count completions.
     */
    private function fix(): void
    {
        $email = $this->option('email');

        $user = $email
            ? User::where('email', $email)->first()
            : User::where('role', UserRole::PARTICIPANT->value)
                ->whereHas('participantProfile')
                ->orderBy('id')
                ->first();

        if (! $user) {
            $this->error('No such participant.');

            return;
        }

        if ($user->role !== UserRole::PARTICIPANT) {
            $this->error("{$user->email} is not a participant.");

            return;
        }

        $study = Study::where('status', StudyStatus::OPEN)
            ->whereDoesntHave('participations', fn ($q) => $q->where('participant_id', $user->id))
            ->orderBy('id')
            ->first();

        if (! $study) {
            $this->error("{$user->email} is already in every open study. Try --email with someone else.");

            return;
        }

        StudyParticipation::updateOrCreate(
            ['study_id' => $study->id, 'participant_id' => $user->id],
            ['stage' => PipelineStage::CONFIRMED]
        );

        $this->newLine();
        $this->info('Done.');
        $this->line("  Log in as   {$user->email}   (password: password)");
        $this->line("  Study       {$study->title}");
        $this->line('  Then open   /participant/studies   → the "Active" panel');
    }
}
