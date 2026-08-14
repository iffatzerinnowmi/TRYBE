<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Services\StudyInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReRecruitApiController extends Controller
{
    public function __construct(private StudyInvitationService $invitations) {}

    /**
     * GET /api/v1/studies/{study}/rerecruit-candidates
     * Filters past participants by study attributes and topics.
     */
    public function candidates(Request $request, Study $study): JsonResponse
    {
        $this->invitations->assertOwner($study, $request->user());

        $filters = $request->validate([
            'topic_ids' => ['sometimes', 'array', 'max:10'],
            'topic_ids.*' => ['integer', 'exists:topics,id'],
            'method'    => ['sometimes', 'string', 'in:online,in_person'],
            'category'  => ['sometimes', 'string', 'max:100'],
            'completed_only' => ['sometimes', 'boolean'],
            'limit'     => ['sometimes', 'integer', 'between:1,200'],
        ]);

        $limit = (int) ($filters['limit'] ?? 50);

        $qb = StudyParticipation::query()
            ->selectRaw('study_participations.participant_id, COUNT(*) as participations_count, MAX(study_participations.updated_at) as last_participation_at')
            ->join('studies', 'study_participations.study_id', '=', 'studies.id')
            ->groupBy('study_participations.participant_id')
            ->orderByDesc('last_participation_at');

        if (! empty($filters['method'])) {
            $qb->where('studies.method', $filters['method']);
        }

        if (! empty($filters['category'])) {
            $qb->where('studies.category', $filters['category']);
        }

        if (! empty($filters['topic_ids'])) {
            $qb->join('study_topic', 'studies.id', '=', 'study_topic.study_id')
               ->whereIn('study_topic.topic_id', $filters['topic_ids']);
        }

        if (! empty($filters['completed_only'])) {
            $qb->whereNotNull('study_participations.completed_at');
        }

        $participantRows = $qb->limit($limit)->get();
        $participantIds = $participantRows->pluck('participant_id')->unique()->values()->all();

        $users = User::whereIn('id', $participantIds)
            ->with(['participantProfile:id,user_id,completed_studies_count'])
            ->get()
            ->keyBy('id');

        $candidates = collect($participantRows)->map(function ($row) use ($users) {
            $user = $users->get($row->participant_id);

            return [
                'participant_id' => $row->participant_id,
                'name' => $user?->name,
                'location' => $user?->location,
                'completed_studies_count' => $user?->participantProfile?->completed_studies_count ?? 0,
                'participations_count' => (int) $row->participations_count,
                'last_participation_at' => $row->last_participation_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data' => [
                'study_id' => $study->id,
                'count' => $candidates->count(),
                'candidates' => $candidates,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/studies/{study}/rerecruit
     * Body: { participant_ids: [1,2,3] }
     * Invites each participant using StudyInvitationService::invite.
     */
    public function inviteBulk(Request $request, Study $study): JsonResponse
    {
        $this->invitations->assertOwner($study, $request->user());

        $data = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1', 'max:200'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $invited = [];
        $errors = [];

        foreach (array_unique($data['participant_ids']) as $pid) {
            $participant = User::find($pid);

            if (! $participant) {
                $errors[$pid] = 'Not found';
                continue;
            }

            try {
                $inv = $this->invitations->invite($study, $participant, $request->user());
                $invited[] = $inv->id;
            } catch (\Throwable $e) {
                $errors[$pid] = $e->getMessage();
            }
        }

        return response()->json([
            'message' => 'Bulk invite processed.',
            'data' => [
                'study_id' => $study->id,
                'invited_ids' => $invited,
                'errors' => $errors,
                'seats_taken' => $this->invitations->seatsTaken($study),
            ],
        ], 200);
    }
}
