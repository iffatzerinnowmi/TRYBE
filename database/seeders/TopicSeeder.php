<?php

namespace Database\Seeders;

use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The shared research-topic vocabulary  (Member 4).
 *
 * Reference data, not user data — so this does not break the rule that only
 * DatabaseSeeder creates users. It creates no users at all.
 *
 * updateOrCreate on the slug means running it twice is harmless, which
 * matters on a shared database where somebody else may already have run it.
 *
 * The list deliberately covers the `category` values already used by
 * DatabaseSeeder, so MatchingTopicsSeeder can tag every existing study
 * without inventing topics nobody uses.
 */
class TopicSeeder extends Seeder
{
    public const TOPICS = [
        'Survey',
        'Usability',
        'Perception',
        'Cognition',
        'Memory',
        'Health',
        'Language',
        'Attention',
        'Sleep & Wellbeing',
        'Mobile',
        'Accessibility',
        'Reading',
    ];

    public function run(): void
    {
        foreach (self::TOPICS as $name) {
            Topic::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }

        $this->command?->info('Topics seeded: ' . Topic::count() . ' total.');
    }
}
