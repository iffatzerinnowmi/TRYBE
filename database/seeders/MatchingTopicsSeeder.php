<?php

namespace Database\Seeders;

use App\Enums\CredentialLevel;
use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Models\Study;
use App\Models\StudyInvitation;
use App\Models\StudyMatchCriteria;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo data for Smart Participant Matching  (Member 4).
 *
 * THIS SEEDER CREATES NO USERS AND NO STUDIES.
 *
 * It attaches to whatever DatabaseSeeder already made, which is the rule:
 * four people each seeding their own users produces four disconnected
 * islands in one shared database. Everything here is a tag, a criteria row
 * or an invitation hung off rows that already exist.
 *
 * Run TopicSeeder first — this one needs the vocabulary.
 */
class MatchingTopicsSeeder extends Seeder
{
    /**
     * Which topics a study category implies. Keyed by the lowercase
     * `category` values already present in the studies table.
     */
    private const CATEGORY_TOPICS = [
        'survey'     => ['Survey'],
        'usability'  => ['Usability', 'Mobile'],
        'perception' => ['Perception', 'Attention'],
        'cognition'  => ['Cognition', 'Attention'],
        'memory'     => ['Memory', 'Cognition'],
        'health'     => ['Health', 'Sleep & Wellbeing'],
        'language'   => ['Language', 'Reading'],
    ];

    /** Extra topics implied by words in the title or description. */
    private const KEYWORD_TOPICS = [
        'reading'    => 'Reading',
        'attention'  => 'Attention',
        'sleep'      => 'Sleep & Wellbeing',
        'font'       => 'Accessibility',
        'legibility' => 'Accessibility',
        'mobile'     => 'Mobile',
        'smartphone' => 'Mobile',
        'screen'     => 'Mobile',
        'bilingual'  => 'Language',
        'recall'     => 'Memory',
        'diary'      => 'Health',
    ];

    public function run(): void
    {
        $topics = Topic::all()->keyBy(fn ($t) => $t->slug);

        if ($topics->isEmpty()) {
            $this->command?->warn('No topics found — run TopicSeeder first. Skipping.');

            return;
        }

        $studies = Study::all();

        if ($studies->isEmpty()) {
            $this->command?->warn('No studies found — run DatabaseSeeder first. Skipping.');

            return;
        }

        $this->tagStudies($studies, $topics);
        $this->seedCriteria($studies);
        $this->tagParticipants($topics);
        $this->seedInvitations($studies);

        $this->command?->info(
            'Matching demo seeded: ' . DB::table('study_topic')->count() . ' study topics, '
            . StudyMatchCriteria::count() . ' criteria rows, '
            . DB::table('participant_topics')->count() . ' declared interests, '
            . StudyInvitation::count() . ' invitations.'
        );
    }

    /** Tag every existing study from its category, then its wording. */
    private function tagStudies($studies, $topics): void
    {
        foreach ($studies as $study) {
            $names = self::CATEGORY_TOPICS[Str::lower((string) $study->category)] ?? ['Survey'];

            $haystack = Str::lower($study->title . ' ' . $study->description);

            foreach (self::KEYWORD_TOPICS as $keyword => $topicName) {
                if (Str::contains($haystack, $keyword)) {
                    $names[] = $topicName;
                }
            }

            $ids = collect($names)->unique()
                ->map(fn ($name) => $topics->get(Str::slug($name))?->id)
                ->filter()
                ->take(3)
                ->values();

            foreach ($ids as $topicId) {
                DB::table('study_topic')->updateOrInsert(
                    ['study_id' => $study->id, 'topic_id' => $topicId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    /**
     * Give every study real criteria, so nothing in the demo falls back to
     * platform defaults. Values are derived from the study itself rather
     * than typed per title — the whole point of the refactor was that
     * criteria stop being a hardcoded lookup keyed by title.
     */
    private function seedCriteria($studies): void
    {
        foreach ($studies as $study) {
            $category = Str::lower((string) $study->category);
            $inPerson = $study->method === 'in_person';

            StudyMatchCriteria::updateOrCreate(
                ['study_id' => $study->id],
                [
                    'age_min' => 18,
                    'age_max' => in_array($category, ['usability', 'cognition'], true) ? 35 : 40,

                    // An in-person session needs somebody who can physically
                    // attend; an online one does not.
                    'location' => $inPerson ? 'Dhaka' : null,

                    'credential_min' => ($study->compensation_amount > 400)
                        ? CredentialLevel::BRONZE->value
                        : CredentialLevel::NONE->value,

                    'required_skills' => $this->skillsFor($category),

                    'availability_days' => $inPerson ? 21 : 30,
                ]
            );
        }
    }

    private function skillsFor(string $category): array
    {
        return match ($category) {
            'usability'  => ['ui testing', 'remote sessions'],
            'cognition'  => ['python', 'ui testing'],
            'health'     => ['health', 'survey'],
            'language'   => ['bangla', 'reading'],
            'memory'     => ['survey', 'interviews'],
            default      => ['survey'],
        };
    }

    /**
     * Give existing participants declared interests, derived from the free
     * text already on their profile so the demo data is self-consistent.
     */
    private function tagParticipants($topics): void
    {
        $participants = User::where('role', UserRole::PARTICIPANT->value)
            ->with('participantProfile')
            ->get();

        $all = $topics->values();

        foreach ($participants as $index => $user) {
            $profile = $user->participantProfile;

            if (! $profile) {
                continue;
            }

            $text  = Str::lower($profile->interests . ' ' . $profile->skills);
            $picks = collect();

            foreach ($topics as $topic) {
                if (Str::contains($text, Str::lower($topic->name))) {
                    $picks->push($topic->id);
                }
            }

            // Everyone gets at least two, rotated so the demo has variety
            // without any participant matching every single study.
            while ($picks->count() < 2 && $all->count() > 0) {
                $picks->push($all[($index + $picks->count()) % $all->count()]->id);
            }

            foreach ($picks->unique()->take(3) as $topicId) {
                DB::table('participant_topics')->updateOrInsert(
                    ['user_id' => $user->id, 'topic_id' => $topicId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    /**
     * A few invitations in mixed states, so the researcher dashboard shows
     * every button variant on first load: Invite, Invited, Accepted,
     * Declined.
     */
    private function seedInvitations($studies): void
    {
        $openStudy = $studies->firstWhere('status', \App\Enums\StudyStatus::OPEN)
            ?? $studies->first();

        if (! $openStudy) {
            return;
        }

        $participants = User::where('role', UserRole::PARTICIPANT->value)
            ->whereHas('participantProfile')
            ->orderBy('id')
            ->take(3)
            ->get();

        $states = [
            InvitationStatus::PENDING,
            InvitationStatus::ACCEPTED,
            InvitationStatus::DECLINED,
        ];

        foreach ($participants as $index => $participant) {
            $status = $states[$index] ?? InvitationStatus::PENDING;

            StudyInvitation::updateOrCreate(
                [
                    'study_id'       => $openStudy->id,
                    'participant_id' => $participant->id,
                ],
                [
                    'invited_by'            => $openStudy->researcher_id,
                    'status'                => $status,
                    'match_score_at_invite' => 70 + ($index * 6),
                    'match_reasons'         => ['Seeded demo invitation.'],
                    'responded_at'          => $status === InvitationStatus::PENDING ? null : now(),
                ]
            );
        }
    }
}
