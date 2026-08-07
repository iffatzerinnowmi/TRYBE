<?php

namespace App\Services;

use App\Enums\CredentialLevel;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\UserNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StudyMatchingService
{
    public function __construct(private int $strongThreshold = 70) {}

    public function strongThreshold(): int
    {
        return $this->strongThreshold;
    }

    public function criteriaForStudy(Study $study): array
    {
        $title = Str::of($study->title)->lower()->trim()->toString();
        $category = Str::of($study->category ?? '')->lower()->trim()->toString();

        $titleRules = [
            'smartphone usability study in dhaka' => [
                'age' => [20, 34],
                'locations' => ['Dhaka'],
                'skills' => ['UI testing', 'Python', 'Remote sessions'],
                'credential_min' => CredentialLevel::BRONZE->value,
                'availability_days' => 21,
            ],
            'memory recall survey for students' => [
                'age' => [18, 30],
                'locations' => ['Dhaka', 'Chattogram'],
                'skills' => ['Survey', 'Interviews', 'Bangla'],
                'credential_min' => CredentialLevel::NONE->value,
                'availability_days' => 30,
            ],
            'sleep and focus diary for young adults' => [
                'age' => [21, 35],
                'locations' => ['Dhaka', 'Chattogram'],
                'skills' => ['Health', 'Wellness', 'Survey'],
                'credential_min' => CredentialLevel::BRONZE->value,
                'availability_days' => 14,
            ],
        ];

        if (isset($titleRules[$title])) {
            return $titleRules[$title];
        }

        $categoryRules = [
            'survey' => [
                'age' => [18, 40],
                'locations' => [],
                'skills' => ['Survey', 'Interviews'],
                'credential_min' => CredentialLevel::NONE->value,
                'availability_days' => 30,
            ],
            'usability' => [
                'age' => [18, 35],
                'locations' => ['Dhaka'],
                'skills' => ['UI testing', 'Remote sessions', 'Python'],
                'credential_min' => CredentialLevel::BRONZE->value,
                'availability_days' => 21,
            ],
            'memory' => [
                'age' => [18, 40],
                'locations' => [],
                'skills' => ['Survey', 'Interviews'],
                'credential_min' => CredentialLevel::NONE->value,
                'availability_days' => 30,
            ],
            'health' => [
                'age' => [18, 40],
                'locations' => ['Dhaka', 'Chattogram'],
                'skills' => ['Health', 'Wellness', 'Survey'],
                'credential_min' => CredentialLevel::BRONZE->value,
                'availability_days' => 14,
            ],
            'language' => [
                'age' => [18, 35],
                'locations' => [],
                'skills' => ['Bangla', 'English', 'Reading'],
                'credential_min' => CredentialLevel::NONE->value,
                'availability_days' => 21,
            ],
            'cognition' => [
                'age' => [18, 35],
                'locations' => ['Dhaka'],
                'skills' => ['Python', 'UI testing', 'Remote sessions'],
                'credential_min' => CredentialLevel::BRONZE->value,
                'availability_days' => 21,
            ],
        ];

        return $categoryRules[$category] ?? [
            'age' => [18, 40],
            'locations' => [],
            'skills' => [],
            'credential_min' => CredentialLevel::NONE->value,
            'availability_days' => 30,
        ];
    }

    public function rankParticipantsForStudy(Study $study, int $limit = 5): Collection
    {
        $criteria = $this->criteriaForStudy($study);

        $query = ParticipantProfile::query()
            ->with('user')
            ->whereHas('user', fn(Builder $builder) => $builder->where('role', UserRole::PARTICIPANT->value));

        $query->whereDoesntHave('user.participations', function (Builder $builder) use ($study) {
            $builder->where('study_id', $study->id)
                ->where('stage', PipelineStage::CONFIRMED->value);
        });

        if (! empty($criteria['age'])) {
            [$minAge, $maxAge] = $criteria['age'];
            $query->whereBetween('age', [$minAge, $maxAge]);
        }

        if (! empty($criteria['locations'])) {
            $locations = array_values(array_filter($criteria['locations']));

            $query->whereHas('user', function (Builder $builder) use ($locations) {
                $builder->where(function (Builder $locationQuery) use ($locations) {
                    foreach ($locations as $location) {
                        $locationQuery->orWhere('location', 'like', '%' . $location . '%');
                    }
                });
            });
        }

        if (! empty($criteria['skills'])) {
            $skills = array_values(array_filter($criteria['skills']));

            $query->where(function (Builder $skillQuery) use ($skills) {
                foreach ($skills as $skill) {
                    $skillQuery->orWhere('skills', 'like', '%' . $skill . '%');
                }
            });
        }

        if (! empty($criteria['credential_min'])) {
            $query->whereIn('credential_level', $this->levelsFrom($criteria['credential_min']));
        }

        if (! empty($criteria['availability_days'])) {
            $query->where(function (Builder $availabilityQuery) use ($criteria) {
                $availabilityQuery->whereNull('last_active_week')
                    ->orWhereDate('last_active_week', '>=', now()->subDays((int) $criteria['availability_days']));
            });
        }

        return $query->get()
            ->map(function (ParticipantProfile $profile) use ($criteria) {
                $match = $this->assessProfile($profile, $criteria);

                $profile->setAttribute('match_score', $match['score']);
                $profile->setAttribute('match_reasons', $match['reasons']);
                $profile->setAttribute('strong_match', $match['score'] >= $this->strongThreshold());

                return $profile;
            })
            ->sort(function (ParticipantProfile $left, ParticipantProfile $right) {
                return [$right->match_score, $right->reliability_score, $right->completed_studies_count]
                    <=> [$left->match_score, $left->reliability_score, $left->completed_studies_count];
            })
            ->take($limit)
            ->values();
    }

    public function recommendStudiesForParticipant(User $user, int $limit = 6): Collection
    {
        $completedStudyIds = StudyParticipation::where('participant_id', $user->id)->pluck('study_id');

        return Study::query()
            ->with('researcher')
            ->where('status', StudyStatus::OPEN)
            ->whereNotIn('id', $completedStudyIds)
            ->get()
            ->map(function (Study $study) use ($user) {
                $criteria = $this->criteriaForStudy($study);
                $match = $this->assessUserForStudy($user, $criteria);

                $study->setAttribute('match_score', $match['score']);
                $study->setAttribute('match_reasons', $match['reasons']);
                $study->setAttribute('strong_match', $match['score'] >= $this->strongThreshold());

                return $study;
            })
            ->filter(fn(Study $study) => $study->match_score > 0)
            ->sort(function (Study $left, Study $right) {
                return [$right->match_score, $right->id] <=> [$left->match_score, $left->id];
            })
            ->take($limit)
            ->values();
    }

    public function assessProfile(ParticipantProfile $profile, array $criteria): array
    {
        $user = $profile->user;

        return $this->assessUserProfile(
            $profile,
            $user,
            $criteria
        );
    }

    public function assessUserForStudy(User $user, array $criteria): array
    {
        $profile = $user->participantProfile;

        if (! $profile) {
            return ['score' => 0, 'reasons' => []];
        }

        return $this->assessUserProfile($profile, $user, $criteria);
    }

    public function criteriaSummary(array $criteria): string
    {
        $parts = [];

        if (! empty($criteria['age'])) {
            $parts[] = 'Age ' . $criteria['age'][0] . '–' . $criteria['age'][1];
        }

        if (! empty($criteria['locations'])) {
            $parts[] = 'Location: ' . implode(', ', $criteria['locations']);
        }

        if (! empty($criteria['skills'])) {
            $parts[] = 'Skills: ' . implode(', ', $criteria['skills']);
        }

        if (! empty($criteria['credential_min'])) {
            $parts[] = 'Credential: ' . ucfirst($criteria['credential_min']) . '+';
        }

        if (! empty($criteria['availability_days'])) {
            $parts[] = 'Active within ' . (int) $criteria['availability_days'] . ' days';
        }

        return implode(' · ', $parts);
    }

    public function invitationUrl(Study $study): string
    {
        return route('studies.show', $study);
    }

    public function invitationTitle(Study $study): string
    {
        return 'Invitation: ' . $study->title;
    }

    public function hasInvitation(User $participant, Study $study): bool
    {
        return UserNotification::query()
            ->where('user_id', $participant->id)
            ->where('type', 'studies')
            ->where('title', $this->invitationTitle($study))
            ->exists();
    }

    private function assessUserProfile(ParticipantProfile $profile, ?User $user, array $criteria): array
    {
        $score = 0;
        $reasons = [];
        $age = (int) ($profile->age ?? 0);
        $skillsText = Str::lower((string) $profile->skills);
        $locationText = Str::lower((string) ($user?->location ?? ''));
        $credential = $profile->credential_level instanceof CredentialLevel
            ? $profile->credential_level->value
            : (string) $profile->credential_level;

        if (! empty($criteria['age'])) {
            [$minAge, $maxAge] = $criteria['age'];

            if ($age >= $minAge && $age <= $maxAge) {
                $score += 25;
                $reasons[] = 'Age ' . $age . ' fits the range.';
            }
        }

        if (! empty($criteria['locations']) && $locationText !== '') {
            foreach ($criteria['locations'] as $location) {
                if (Str::contains($locationText, Str::lower($location))) {
                    $score += 20;
                    $reasons[] = 'Location matches ' . $location . '.';
                    break;
                }
            }
        }

        if (! empty($criteria['skills'])) {
            $matchedSkills = [];

            foreach ($criteria['skills'] as $skill) {
                if (Str::contains($skillsText, Str::lower($skill))) {
                    $matchedSkills[] = $skill;
                }
            }

            if ($matchedSkills) {
                $score += min(30, count($matchedSkills) * 10);
                $reasons[] = 'Skills match: ' . implode(', ', $matchedSkills) . '.';
            }
        }

        if (! empty($criteria['credential_min'])) {
            $allowedLevels = $this->levelsFrom($criteria['credential_min']);

            if (in_array($credential, $allowedLevels, true)) {
                $score += 15;
                $reasons[] = 'Credential level is suitable.';
            }
        }

        if (! empty($criteria['availability_days'])) {
            $recentCutoff = now()->subDays((int) $criteria['availability_days']);

            if ($profile->last_active_week && $profile->last_active_week->greaterThanOrEqualTo($recentCutoff)) {
                $score += 10;
                $reasons[] = 'Recently active.';
            }
        }

        $score += min(20, (int) round(((int) $profile->reliability_score) * 0.2));

        return [
            'score' => min(100, $score),
            'reasons' => $reasons,
        ];
    }

    private function levelsFrom(string $minimumLevel): array
    {
        $order = [
            CredentialLevel::NONE->value,
            CredentialLevel::BRONZE->value,
            CredentialLevel::GOLD->value,
            CredentialLevel::EXPERT->value,
        ];

        $start = array_search($minimumLevel, $order, true);

        return $start === false ? $order : array_slice($order, $start);
    }
}
