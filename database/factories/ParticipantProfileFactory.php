<?php

namespace Database\Factories;

use App\Enums\CredentialLevel;
use App\Models\ParticipantProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<ParticipantProfile>
 */
class ParticipantProfileFactory extends Factory
{
    protected $model = ParticipantProfile::class;

    public function definition(): array
    {
        $completedStudiesCount = fake()->numberBetween(0, 30);

        $user = User::create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => fake()->regexify('[A-Za-z0-9]{10}'),
            'phone' => fake()->numerify('01#########'),
            'role' => 'participant',
            'location' => fake()->city() . ', ' . fake()->country(),
            'verification_status' => 'unverified',
        ]);

        return [
            'user_id' => $user->id,
            'age' => fake()->numberBetween(18, 55),
            'gender' => fake()->randomElement(['Woman', 'Man', 'Non-binary', 'Prefer not to say']),
            'occupation' => fake()->randomElement([
                'Undergraduate student',
                'Graduate student',
                'Teacher',
                'Software engineer',
                'Research assistant',
                'Freelancer',
            ]),
            'health_background' => fake()->optional(0.65)->sentence(),
            'interests' => fake()->randomElement([
                'UX research, psychology, mobile apps',
                'Health studies, wellness, fitness',
                'Language, reading, education',
                'Technology, AI, product design',
            ]),
            'skills' => fake()->randomElement([
                'Survey responses, interviews, basic data entry',
                'Python, spreadsheets, usability testing',
                'Bangla, English, documentation',
                'Remote sessions, feedback, prototype testing',
            ]),
            'linkedin' => fake()->optional(0.45)->url(),
            'github' => fake()->optional(0.35)->url(),
            'rel_attendance' => fake()->numberBetween(60, 100),
            'rel_completion' => fake()->numberBetween(60, 100),
            'rel_reviews' => fake()->numberBetween(50, 100),
            'reliability_score' => fake()->numberBetween(55, 100),
            'completed_studies_count' => $completedStudiesCount,
            'credential_level' => CredentialLevel::fromCompletions($completedStudiesCount)->value,
            'current_streak_weeks' => fake()->numberBetween(0, 10),
            'longest_streak_weeks' => fake()->numberBetween(0, 20),
            'last_active_week' => fake()->optional(0.85)->dateTimeBetween('-12 weeks', 'now'),
            'endorsement_count' => fake()->numberBetween(0, 8),
            'is_verified_participant' => fake()->boolean(20),
        ];
    }
}
