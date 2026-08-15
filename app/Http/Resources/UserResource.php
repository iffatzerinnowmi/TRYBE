<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'role'                => $this->role?->value,
            'location'            => $this->location,
            'verification_status' => $this->verification_status?->value,
            'avatar_path'         => $this->avatar_path,
            'organization_name'   => $this->organization_name,
            'organization_type'   => $this->organization_type,
            'created_at'          => $this->created_at?->toIso8601String(),

            // Only appear when the controller has loaded them.
            'participant_profile' => $this->whenLoaded('participantProfile', fn () => [
                'age'                     => $this->participantProfile->age,
                'gender'                  => $this->participantProfile->gender,
                'occupation'              => $this->participantProfile->occupation,
                'rel_attendance'          => $this->participantProfile->rel_attendance,
                'rel_completion'          => $this->participantProfile->rel_completion,
                'rel_reviews'             => $this->participantProfile->rel_reviews,
                'reliability_score'       => $this->participantProfile->reliability_score,
                'completed_studies_count' => $this->participantProfile->completed_studies_count,
                'credential_level'        => $this->participantProfile->credential_level?->value,
                'current_streak_weeks'    => $this->participantProfile->current_streak_weeks,
                'longest_streak_weeks'    => $this->participantProfile->longest_streak_weeks,
                'endorsement_count'       => $this->participantProfile->endorsement_count,
                'is_verified_participant' => $this->participantProfile->is_verified_participant,
            ]),

            'researcher_profile' => $this->whenLoaded('researcherProfile', fn () => [
                'title'               => $this->researcherProfile->title,
                'institution'         => $this->researcherProfile->institution,
                'department'          => $this->researcherProfile->department,
                'bio'                 => $this->researcherProfile->bio,
                'institutional_email' => $this->researcherProfile->institutional_email,
                'research_areas'      => $this->researcherProfile->research_areas,
                'avg_rating'          => (float) $this->researcherProfile->avg_rating,
                'ratings_count'       => $this->researcherProfile->ratings_count,
                'followers_count'     => $this->researcherProfile->followers_count,
            ]),
        ];
    }
}
