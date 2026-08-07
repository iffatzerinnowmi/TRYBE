<?php

namespace Database\Factories;

use App\Enums\IncentiveType;
use App\Enums\StudyStatus;
use App\Models\Study;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Study>
 */
class StudyFactory extends Factory
{
    protected $model = Study::class;

    public function definition(): array
    {
        $researcher = User::create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => fake()->regexify('[A-Za-z0-9]{10}'),
            'phone' => fake()->numerify('01#########'),
            'role' => 'researcher',
            'location' => fake()->city() . ', ' . fake()->country(),
            'verification_status' => 'verified',
        ]);

        return [
            'researcher_id' => $researcher->id,
            'title' => fake()->sentence(5),
            'description' => fake()->optional(0.9)->paragraph(),
            'category' => fake()->randomElement([
                'Survey',
                'Usability',
                'Memory',
                'Health',
                'Language',
                'Cognition',
            ]),
            'method' => fake()->randomElement(['online', 'in_person']),
            'duration_minutes' => fake()->numberBetween(10, 120),
            'incentive_type' => fake()->randomElement(array_map(
                fn(IncentiveType $type) => $type->value,
                IncentiveType::cases()
            )),
            'compensation_amount' => fake()->randomFloat(2, 0, 1500),
            'slots' => fake()->numberBetween(5, 100),
            'deadline' => fake()->optional(0.8)->dateTimeBetween('now', '+90 days'),
            'status' => fake()->randomElement(array_map(
                fn(StudyStatus $status) => $status->value,
                StudyStatus::cases()
            )),
            'participants_count' => fake()->numberBetween(0, 200),
            'irb_document_path' => fake()->optional(0.7)->regexify('irb-documents/[a-z0-9\-]{8,16}\.pdf'),
            'irb_flagged' => fake()->boolean(20),
            'irb_board' => fake()->optional(0.7)->randomElement([
                'BRAC University IRB',
                'DU IRB',
                'Independent Ethics Board',
            ]),
            'irb_ref' => fake()->optional(0.7)->regexify('IRB-2026-\d{3}'),
            'irb_valid_until' => fake()->optional(0.6)->date(),
        ];
    }
}
