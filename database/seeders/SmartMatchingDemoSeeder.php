<?php

namespace Database\Seeders;

use App\Enums\CredentialLevel;
use App\Models\ParticipantProfile;
use App\Models\ResearcherProfile;
use App\Models\Study;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SmartMatchingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $researcher = User::create([
            'name' => 'Dr. Match Lab',
            'email' => 'matchlab@trybe.test',
            'email_verified_at' => now(),
            'password' => $password,
            'remember_token' => str()->random(10),
            'phone' => '01790000000',
            'role' => 'researcher',
            'location' => 'Dhaka, Bangladesh',
            'verification_status' => 'verified',
        ]);

        ResearcherProfile::create([
            'user_id' => $researcher->id,
            'title' => 'Research Fellow',
            'institution' => 'TRYBE Research Lab',
            'department' => 'Human-centered computing',
            'bio' => 'Dummy researcher used to demonstrate smart matching and invitations.',
            'institutional_email' => 'matchlab@trybe.test',
            'research_areas' => 'UX, Memory, Health, Language',
            'avg_rating' => 4.9,
            'ratings_count' => 52,
            'rating_distribution' => ['5' => 42, '4' => 8, '3' => 1, '2' => 1, '1' => 0],
            'followers_count' => 96,
        ]);

        $studies = [
            [
                'title' => 'Smartphone usability study in Dhaka',
                'description' => 'In-person usability sessions for participants in Dhaka who can test prototypes and give structured feedback.',
                'category' => 'Usability',
                'method' => 'in_person',
                'duration_minutes' => 45,
                'incentive_type' => 'cash',
                'compensation_amount' => 600,
                'slots' => 10,
                'status' => 'open',
                'participants_count' => 0,
                'irb_document_path' => 'irb-documents/smartphone-usability.pdf',
                'irb_flagged' => false,
                'irb_board' => 'TRYBE IRB',
                'irb_ref' => 'IRB-MATCH-001',
                'irb_valid_until' => '2026-12-31',
            ],
            [
                'title' => 'Memory recall survey for students',
                'description' => 'An online memory task for students comfortable with surveys and interviews.',
                'category' => 'Memory',
                'method' => 'online',
                'duration_minutes' => 30,
                'incentive_type' => 'voucher',
                'compensation_amount' => 300,
                'slots' => 20,
                'status' => 'open',
                'participants_count' => 0,
                'irb_document_path' => 'irb-documents/memory-recall.pdf',
                'irb_flagged' => false,
                'irb_board' => 'TRYBE IRB',
                'irb_ref' => 'IRB-MATCH-002',
                'irb_valid_until' => '2026-12-31',
            ],
            [
                'title' => 'Sleep and focus diary for young adults',
                'description' => 'A short diary study for people who are active recently and can log daily habits.',
                'category' => 'Health',
                'method' => 'online',
                'duration_minutes' => 20,
                'incentive_type' => 'cash',
                'compensation_amount' => 450,
                'slots' => 12,
                'status' => 'open',
                'participants_count' => 0,
                'irb_document_path' => 'irb-documents/sleep-diary.pdf',
                'irb_flagged' => false,
                'irb_board' => 'TRYBE IRB',
                'irb_ref' => 'IRB-MATCH-003',
                'irb_valid_until' => '2026-12-31',
            ],
        ];

        foreach ($studies as $studyData) {
            $studyData['researcher_id'] = $researcher->id;
            Study::create($studyData);
        }

        $this->participant([
            'name' => 'Ayesha Rahman',
            'email' => 'ayesha.match@trybe.test',
            'phone' => '01790000001',
            'location' => 'Dhaka, Bangladesh',
            'age' => 23,
            'skills' => 'Python, UI testing, Bangla',
            'completed' => 11,
            'current_streak_weeks' => 4,
            'last_active_week' => now()->subDays(3),
        ], $password);

        $this->participant([
            'name' => 'Nayeem Hasan',
            'email' => 'nayeem.match@trybe.test',
            'phone' => '01790000002',
            'location' => 'Dhaka, Bangladesh',
            'age' => 27,
            'skills' => 'Survey, Interviews, Bangla',
            'completed' => 8,
            'current_streak_weeks' => 2,
            'last_active_week' => now()->subDays(6),
        ], $password);

        $this->participant([
            'name' => 'Mim Akter',
            'email' => 'mim.match@trybe.test',
            'phone' => '01790000003',
            'location' => 'Chattogram, Bangladesh',
            'age' => 30,
            'skills' => 'Health, Wellness, Survey',
            'completed' => 14,
            'current_streak_weeks' => 5,
            'last_active_week' => now()->subDay(),
        ], $password);

        $this->participant([
            'name' => 'Fahim Ahmed',
            'email' => 'fahim.match@trybe.test',
            'phone' => '01790000004',
            'location' => 'Dhaka, Bangladesh',
            'age' => 25,
            'skills' => 'Remote sessions, Python, UI testing',
            'completed' => 18,
            'current_streak_weeks' => 6,
            'last_active_week' => now()->subDays(2),
            'credential_level' => CredentialLevel::GOLD->value,
        ], $password);

        $this->participant([
            'name' => 'Raisa Sultana',
            'email' => 'raisa.match@trybe.test',
            'phone' => '01790000005',
            'location' => 'Dhaka, Bangladesh',
            'age' => 21,
            'skills' => 'Reading, Bangla, English',
            'completed' => 5,
            'current_streak_weeks' => 1,
            'last_active_week' => now()->subDays(8),
        ], $password);

        $this->command?->info('Smart matching demo seeded: 1 researcher, 3 studies, and 5 participants.');
    }

    private function participant(array $data, string $password): void
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'email_verified_at' => now(),
            'password' => $password,
            'remember_token' => str()->random(10),
            'phone' => $data['phone'],
            'role' => 'participant',
            'location' => $data['location'],
            'verification_status' => 'unverified',
        ]);

        $completed = (int) ($data['completed'] ?? 0);

        ParticipantProfile::create([
            'user_id' => $user->id,
            'age' => $data['age'],
            'gender' => 'Prefer not to say',
            'occupation' => 'Student',
            'health_background' => 'None',
            'interests' => 'Research participation',
            'skills' => $data['skills'],
            'rel_attendance' => 90,
            'rel_completion' => 88,
            'rel_reviews' => 84,
            'reliability_score' => 88,
            'completed_studies_count' => $completed,
            'credential_level' => $data['credential_level'] ?? CredentialLevel::fromCompletions($completed)->value,
            'current_streak_weeks' => $data['current_streak_weeks'] ?? 0,
            'longest_streak_weeks' => max(1, (int) ($data['current_streak_weeks'] ?? 0)),
            'last_active_week' => $data['last_active_week'] ?? now()->subDays(5),
            'endorsement_count' => 1,
            'is_verified_participant' => false,
        ]);
    }
}
