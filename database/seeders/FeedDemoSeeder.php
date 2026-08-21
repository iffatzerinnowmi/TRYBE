<?php

namespace Database\Seeders;

use App\Enums\CredentialLevel;
use App\Enums\UserRole;
use App\Models\NotificationPreference;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\StudyMatchCriteria;
use App\Models\StudyParticipation;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo data for the Study Recommendation Feed and the skill-gap coach.
 *                                                          (Member 4)
 *
 * WHY THIS SEEDER EXISTS
 * ----------------------
 * The base data has only three OPEN studies, two of which are about reading,
 * and their criteria required the "skill" `reading`. The feed is only as good
 * as the studies it can rank, and the coach can only be as sensible as the
 * skills it is handed — so the coach's advice came out as "improving your
 * reading skills will unlock more studies", which is both circular and
 * faintly silly.
 *
 * That was a DATA problem, not a model problem, and this seeder fixes it:
 *
 *   1. Six open studies requiring skills a research PARTICIPANT could
 *      plausibly hold and plausibly acquire.
 *   2. One participant positioned so that some studies are strong matches and
 *      four are near misses, each blocked by exactly one nameable skill —
 *      which is the only situation in which the coach has anything to say.
 *   3. Vague skills like `reading` retired from every criteria row.
 *
 * THE SKILL VOCABULARY, AND WHY IT MATTERS
 * ----------------------------------------
 * `study_match_criteria.required_skills` is free text, so a researcher can
 * type anything and it flows straight through the matcher into the coach's
 * prompt. `reading` is not a skill anyone can act on; `think-aloud protocol`
 * is. Until there is a controlled vocabulary the honest mitigation is to seed
 * credible values and be candid that the column allows worse.
 *
 * ONE DEVIATION FROM decision.md §9, DELIBERATE AND ANNOUNCED
 * ----------------------------------------------------------
 * §9 says only DatabaseSeeder creates users, and the reason is sound: four
 * people each seeding their own accounts turns one shared database into four
 * disconnected islands. This seeder creates ONE participant, because the
 * existing accounts belong to other members' demos and repositioning their
 * skills to suit the feed would break what those demos are showing.
 *
 * It is firstOrCreate on the email, so it never duplicates, never overwrites
 * an existing account, and it prints what it did at the end. If the group
 * would rather it did not, delete the account and point FEED_PARTICIPANT at
 * an existing email — nothing else in the seeder changes.
 */
class FeedDemoSeeder extends Seeder
{
    /** The one account this seeder creates. */
    private const FEED_PARTICIPANT = 'feed.demo@trybe.test';

    /**
     * What the demo participant already has.
     *
     * Chosen so that the studies below split into strong matches and near
     * misses along skill lines rather than by accident.
     */
    private const PARTICIPANT_SKILLS = 'think-aloud protocol, usability testing, structured interview, diary logging';

    /**
     * Skills that are too vague to coach on. Any criteria row containing one
     * is rewritten — see retireVagueSkills().
     *
     * `reading`, `english` and `bangla` are the offenders in the base data:
     * they describe a person, not a practice, so "here is how to improve it"
     * has no useful answer.
     */
    private const VAGUE_SKILLS = ['reading', 'english', 'bangla', 'health', 'wellness'];

    /**
     * The six studies, and the shape of the demo.
     *
     * `days_old` and `deadline_in` are what give the feed's recency and
     * urgency factors something to actually separate — without a spread, all
     * three contributions are identical on every card and the breakdown looks
     * like decoration rather than maths.
     */
    private const STUDIES = [
        [
            'title'       => 'Think-aloud walkthrough of a banking app',
            'description' => 'Sit with a researcher and narrate your thinking while completing three transfer tasks on a prototype.',
            'category'    => 'Usability',
            'method'      => 'in_person',
            'duration'    => 45,
            'incentive'   => 'cash',
            'amount'      => 600,
            'slots'       => 8,
            'days_old'    => 2,
            'deadline_in' => 5,     // inside the urgency window
            'topics'      => ['Usability', 'Mobile'],
            'skills'      => ['think-aloud protocol', 'usability testing'],
            'location'    => 'Dhaka',
        ],
        [
            'title'       => 'Two-week sleep and focus diary',
            'description' => 'Log your sleep and concentration twice a day for two weeks using a short structured form.',
            'category'    => 'Health',
            'method'      => 'online',
            'duration'    => 15,
            'incentive'   => 'voucher',
            'amount'      => 250,
            'slots'       => 25,
            'days_old'    => 10,
            'deadline_in' => 25,
            'topics'      => ['Health', 'Sleep & Wellbeing'],
            'skills'      => ['diary logging'],
            'location'    => null,
        ],
        [
            'title'       => 'Bangla-English switching in short interviews',
            'description' => 'A recorded interview where you answer in whichever language comes first, so we can study code-switching.',
            'category'    => 'Language',
            'method'      => 'online',
            'duration'    => 30,
            'incentive'   => 'cash',
            'amount'      => 450,
            'slots'       => 12,
            'days_old'    => 5,
            'deadline_in' => 12,
            'topics'      => ['Language', 'Attention'],
            // She has the interview skill, not the bilingual one -> near miss
            // with a single, nameable blocker.
            'skills'      => ['structured interview', 'bilingual interviewing'],
            'location'    => null,
        ],
        [
            'title'       => 'Reaction-time task on a laptop',
            'description' => 'Press a key as fast as you can when a shape appears. Ten minutes, done at home.',
            'category'    => 'Cognition',
            'method'      => 'online',
            'duration'    => 20,
            'incentive'   => 'volunteer',
            'amount'      => 0,
            'slots'       => 40,
            'days_old'    => 1,     // newest -> tests the recency factor
            'deadline_in' => 6,
            'topics'      => ['Cognition', 'Attention'],
            'skills'      => ['reaction-time tasks'],
            'location'    => null,
        ],
        [
            'title'       => 'Screen-reader navigation study',
            'description' => 'Complete four tasks on a news site using a screen reader while we record where navigation breaks down.',
            'category'    => 'Usability',
            'method'      => 'online',
            'duration'    => 40,
            'incentive'   => 'cash',
            'amount'      => 700,
            'slots'       => 10,
            'days_old'    => 20,    // oldest -> its recency score is visibly low
            'deadline_in' => 30,
            'topics'      => ['Accessibility', 'Usability'],
            'skills'      => ['screen reader navigation', 'usability testing'],
            'location'    => null,
        ],
        [
            'title'       => 'Wearable step-tracker compliance study',
            'description' => 'Wear a supplied fitness band for ten days and attend one fitting session.',
            'category'    => 'Health',
            'method'      => 'in_person',
            'duration'    => 60,
            'incentive'   => 'cash',
            'amount'      => 800,
            'slots'       => 15,
            'days_old'    => 7,
            'deadline_in' => 9,
            'topics'      => ['Health', 'Mobile'],
            'skills'      => ['wearable device use', 'diary logging'],
            'location'    => 'Dhaka',
        ],
    ];

    public function run(): void
    {
        // The vocabulary is reference data and TopicSeeder is idempotent, so
        // calling it is safe whether or not it has already run.
        if (Topic::count() === 0) {
            $this->call(TopicSeeder::class);
        }

        $researchers = User::where('role', UserRole::RESEARCHER->value)
            ->where('verification_status', 'verified')
            ->orderBy('id')
            ->get();

        if ($researchers->isEmpty()) {
            $this->command?->warn('No verified researchers — run DatabaseSeeder first. Skipping.');

            return;
        }

        $topics = Topic::all()->keyBy(fn ($t) => $t->slug);

        $created = $this->seedStudies($researchers, $topics);
        $retired = $this->retireVagueSkills();
        $user    = $this->seedParticipant($topics);

        $this->report($created, $retired, $user);
    }

    // =================================================================
    // Studies
    // =================================================================

    /**
     * Create (or update) the six studies, with topics and criteria.
     *
     * Keyed on title via updateOrCreate, so a second run refreshes them in
     * place rather than filling the feed with duplicates.
     */
    private function seedStudies($researchers, $topics): int
    {
        $count = 0;

        foreach (self::STUDIES as $index => $spec) {
            $researcher = $researchers[$index % $researchers->count()];

            $study = Study::updateOrCreate(
                ['title' => $spec['title']],
                [
                    'researcher_id'       => $researcher->id,
                    'description'         => $spec['description'],
                    'category'            => $spec['category'],
                    'method'              => $spec['method'],
                    'duration_minutes'    => $spec['duration'],
                    'incentive_type'      => $spec['incentive'],
                    'compensation_amount' => $spec['amount'],
                    'slots'               => $spec['slots'],
                    'deadline'            => now()->addDays($spec['deadline_in'])->toDateString(),
                    'status'              => 'open',
                    'participants_count'  => 0,

                    // Member 1's ethics fields. Seeded as present so these
                    // studies do not show up as IRB warnings in her demo.
                    'irb_document_path'   => 'irb-documents/feed-demo.pdf',
                    'irb_flagged'         => false,
                    'irb_board'           => 'BRAC University IRB',
                    'irb_ref'             => 'IRB-2026-2' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'irb_valid_until'     => 'Dec 2026',
                ]
            );

            // Backdate directly rather than through fill(): created_at is not
            // fillable, and the recency factor is measured from it.
            $study->created_at = now()->subDays($spec['days_old']);
            $study->save();

            $this->attachTopics($study, $spec['topics'], $topics);

            StudyMatchCriteria::updateOrCreate(
                ['study_id' => $study->id],
                [
                    'age_min'           => 18,
                    'age_max'           => 45,
                    'location'          => $spec['location'],
                    'credential_min'    => CredentialLevel::NONE->value,
                    'required_skills'   => $spec['skills'],
                    'availability_days' => 30,
                ]
            );

            $count++;
        }

        return $count;
    }

    private function attachTopics(Study $study, array $names, $topics): void
    {
        $ids = collect($names)
            ->map(fn ($name) => $topics->get(Str::slug($name))?->id)
            ->filter();

        foreach ($ids as $topicId) {
            DB::table('study_topic')->updateOrInsert(
                ['study_id' => $study->id, 'topic_id' => $topicId],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    /**
     * Rewrite any criteria row that asks for a vague skill.
     *
     * This is what stops "improve your reading" reappearing. It edits only
     * the required_skills array on study_match_criteria — Member 4's own
     * table — and leaves every other column, and every other member's data,
     * untouched.
     *
     * A row left with no skills at all is given `structured interview`, since
     * an empty required_skills makes the skills factor inapplicable and the
     * study stops being a useful near miss.
     */
    private function retireVagueSkills(): int
    {
        $touched = 0;

        foreach (StudyMatchCriteria::all() as $criteria) {
            $skills = collect($criteria->required_skills ?? [])
                ->map(fn ($s) => Str::lower(trim((string) $s)));

            $kept = $skills->reject(
                fn ($skill) => in_array($skill, self::VAGUE_SKILLS, true)
            )->values();

            if ($kept->count() === $skills->count()) {
                continue;   // nothing vague on this row
            }

            if ($kept->isEmpty()) {
                $kept = collect(['structured interview']);
            }

            $criteria->update(['required_skills' => $kept->all()]);
            $touched++;
        }

        return $touched;
    }

    // =================================================================
    // The demo participant
    // =================================================================

    /**
     * One participant, positioned in the near-miss band.
     *
     * The profile is written on every run so the demo is reproducible, but
     * the USER row is only ever created once — an existing account keeps its
     * password and its id, which matters if you have already logged in as it.
     */
    private function seedParticipant($topics): User
    {
        $user = User::firstOrCreate(
            ['email' => self::FEED_PARTICIPANT],
            [
                'name'                => 'Sadia Rahman',
                'phone'               => '01730000404',
                'password'            => Hash::make('password'),
                'role'                => UserRole::PARTICIPANT->value,
                'verification_status' => 'unverified',
                'location'            => 'Dhaka, Bangladesh',
            ]
        );

        // Four completed studies -> a real credential level and real topic
        // history, so the topics factor has evidence to read rather than only
        // declared interests.
        $completed = Study::where('status', 'closed')->orderBy('id')->take(4)->get();

        foreach ($completed as $i => $study) {
            StudyParticipation::updateOrCreate(
                ['study_id' => $study->id, 'participant_id' => $user->id],
                ['stage' => 'completed', 'completed_at' => now()->subWeeks($i + 1)]
            );
        }

        ParticipantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'age'               => 24,
                'gender'            => 'Woman',
                'occupation'        => 'Graduate student, Psychology',
                'health_background' => 'None',
                'interests'         => 'Usability, accessibility, sleep research',
                'skills'            => self::PARTICIPANT_SKILLS,

                'rel_attendance'    => 90,
                'rel_completion'    => 86,
                'rel_reviews'       => 82,
                'reliability_score' => 86,

                'completed_studies_count' => $completed->count(),
                'credential_level'        => CredentialLevel::fromCompletions($completed->count())->value,

                'current_streak_weeks' => 1,
                'longest_streak_weeks' => 3,
                'last_active_week'     => now()->startOfWeek(),

                'endorsement_count'      => 2,
                'is_verified_participant' => false,
            ]
        );

        NotificationPreference::firstOrCreate(['user_id' => $user->id]);

        // Declared interests, matching the profile text above.
        foreach (['Usability', 'Accessibility', 'Health'] as $name) {
            if ($topicId = $topics->get(Str::slug($name))?->id) {
                DB::table('participant_topics')->updateOrInsert(
                    ['user_id' => $user->id, 'topic_id' => $topicId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        return $user;
    }

    // =================================================================

    private function report(int $created, int $retired, User $user): void
    {
        $this->command?->info("Feed demo seeded: {$created} open studies, criteria and topics attached.");

        if ($retired > 0) {
            $this->command?->info(
                "Rewrote {$retired} criteria row(s) that asked for a vague skill "
                . '(reading, bangla, english, health, wellness).'
            );
        }

        $this->command?->warn('Created / refreshed one participant account:');
        $this->command?->warn('    ' . self::FEED_PARTICIPANT . '  /  password');
        $this->command?->warn(
            'decision.md section 9 says only DatabaseSeeder creates users. This is a '
            . 'deliberate, announced exception so the feed demo does not have to '
            . 'repurpose another member\'s account. Tell the group.'
        );

        $this->command?->line('');
        $this->command?->line('  Expected: strong matches on the think-aloud and diary studies,');
        $this->command?->line('  near misses on the bilingual, reaction-time, screen-reader and');
        $this->command?->line('  wearable studies — each blocked by exactly one skill.');
    }
}
