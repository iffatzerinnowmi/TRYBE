<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\CompetitionSave;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo listings for the Competition & Hackathon Board  (Member 4).
 *
 * THIS SEEDER CREATES NO USERS. It posts as whoever DatabaseSeeder already
 * made — a verified researcher and the organization account (decision.md §9).
 *
 * The spread is deliberate. The board is ordered newest-first and hides
 * anything past its deadline, so the data has to prove three things at a
 * glance: a listing closing within days renders in flame, a listing with no
 * deadline still appears, and a listing whose deadline has passed is absent
 * from the board while remaining on its poster's own list.
 */
class CompetitionSeeder extends Seeder
{
    private const LISTINGS = [
        [
            'name'         => 'BUET CSE Fest 2026 — Hackathon',
            'organizer'    => 'BUET Computer Club',
            'description'  => 'Thirty-six hours, four-person teams, open theme with a civic-tech track.',
            'external_url' => 'https://devpost.com/hackathons',
            'deadline_in'  => 4,          // closing soon — renders in flame
            'team_min'     => 2,
            'team_max'     => 4,
            'prize'        => '৳50,000 + internship interviews',
            'location'     => 'Dhaka',
            'posted_days_ago' => 1,
        ],
        [
            'name'         => 'MLH Global Hack Week',
            'organizer'    => 'Major League Hacking',
            'description'  => 'A week of beginner-friendly online challenges. Solo entries welcome.',
            'external_url' => 'https://mlh.io/seasons',
            'deadline_in'  => 21,
            'team_min'     => 1,
            'team_max'     => 4,
            'prize'        => 'Swag and mentorship',
            'location'     => 'Online',
            'posted_days_ago' => 3,
        ],
        [
            'name'         => 'BRACU Data Science Challenge',
            'organizer'    => 'BRAC University',
            'description'  => 'Predictive modelling on an anonymised public-health dataset.',
            'external_url' => 'https://www.kaggle.com/competitions',
            'deadline_in'  => 10,
            'team_min'     => 1,
            'team_max'     => 3,
            'prize'        => '৳30,000',
            'location'     => 'Online',
            'posted_days_ago' => 5,
        ],
        [
            'name'         => 'UX Research Case Competition',
            'organizer'    => 'Green Valley Research Lab',
            'description'  => 'Run a small usability study and present findings to a panel.',
            'external_url' => 'https://devpost.com/hackathons',
            'deadline_in'  => 30,
            'team_min'     => 2,
            'team_max'     => 5,
            'prize'        => 'Research assistantship',
            'location'     => 'Dhaka',
            'posted_days_ago' => 8,
        ],
        [
            // No deadline: proves an undated listing still shows on the board
            // and sorts last on the saved page rather than first.
            'name'         => 'Open Call — Accessibility Jam',
            'organizer'    => 'a11y Dhaka',
            'description'  => 'Rolling submissions, no fixed closing date.',
            'external_url' => 'https://devpost.com/hackathons',
            'deadline_in'  => null,
            'team_min'     => 1,
            'team_max'     => 3,
            'prize'        => 'Community showcase',
            'location'     => 'Online',
            'posted_days_ago' => 12,
        ],
        [
            // Already closed: must NOT appear on the board, but must still
            // appear under "Your listings" for whoever posted it.
            'name'         => 'Winter Robotics Sprint (closed)',
            'organizer'    => 'Dhaka Robotics Society',
            'description'  => 'Ran last month. Seeded so the board can be shown to exclude it.',
            'external_url' => 'https://devpost.com/hackathons',
            'deadline_in'  => -6,
            'team_min'     => 3,
            'team_max'     => 6,
            'prize'        => '৳20,000',
            'location'     => 'Dhaka',
            'posted_days_ago' => 40,
        ],
    ];

    public function run(): void
    {
        $posters = $this->posters();

        if ($posters->isEmpty()) {
            $this->command?->warn('No verified researcher or organization — run DatabaseSeeder first. Skipping.');

            return;
        }

        $created = 0;

        foreach (self::LISTINGS as $index => $spec) {
            $poster = $posters[$index % $posters->count()];

            $competition = Competition::updateOrCreate(
                ['name' => $spec['name']],
                [
                    'posted_by'    => $poster->id,
                    'organizer'    => $spec['organizer'],
                    'description'  => $spec['description'],
                    'external_url' => $spec['external_url'],
                    'deadline'     => $spec['deadline_in'] === null
                        ? null
                        : now()->addDays($spec['deadline_in'])->toDateString(),
                    'team_min'     => $spec['team_min'],
                    'team_max'     => $spec['team_max'],
                    'prize'        => $spec['prize'],
                    'location'     => $spec['location'],
                ]
            );

            // Backdate so "newest first" is visibly ordered rather than all
            // six sharing one timestamp. created_at is not fillable.
            $competition->created_at = now()->subDays($spec['posted_days_ago']);
            $competition->save();

            $created++;
        }

        $this->seedSaves();

        $this->command?->info("Competition board seeded: {$created} listings.");
        $this->command?->line('  One closes in 4 days (flame), one has no deadline,');
        $this->command?->line('  one is already closed and is hidden from the board.');
        $this->command?->line('  Open /competitions to see it.');
    }

    /** Accounts allowed to post: verified researchers and organizations. */
    private function posters()
    {
        return User::whereIn('role', [UserRole::RESEARCHER->value, UserRole::ORGANIZATION->value])
            ->where('verification_status', 'verified')
            ->orderBy('id')
            ->get();
    }

    /** Give a participant two saved listings so the saved page is not empty. */
    private function seedSaves(): void
    {
        // Preference order in PHP rather than a MySQL FIELD() expression —
        // the test suite runs on SQLite and FIELD() does not exist there.
        $preferred = ['complete.demo@trybe.test', 'sadia@trybe.test'];

        $participant = User::where('role', UserRole::PARTICIPANT->value)
            ->whereIn('email', $preferred)
            ->get()
            ->sortBy(fn (User $u) => array_search($u->email, $preferred))
            ->first()
            ?? User::where('role', UserRole::PARTICIPANT->value)->orderBy('id')->first();

        if (! $participant) {
            return;
        }

        // One closing soon and one far off, so the saved page's ordering is
        // visible rather than theoretical.
        $picks = Competition::whereNotNull('deadline')
            ->orderBy('deadline')
            ->take(2)
            ->pluck('id');

        foreach ($picks as $id) {
            CompetitionSave::firstOrCreate([
                'competition_id' => $id,
                'user_id'        => $participant->id,
            ]);
        }

        $this->command?->line('  Saved 2 listings for ' . $participant->email . '.');
    }
}
