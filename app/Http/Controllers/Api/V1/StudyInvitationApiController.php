<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Study;
use App\Models\StudyInvitation;
use App\Models\User;
use App\Services\StudyInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API — Researcher invitations  (Member 4, graded feature)
 *
 * No logic here. Guard, call StudyInvitationService, shape JSON.
 *
 * REST SHAPE
 * ----------
 * Invitations are nested under a study for CREATE and LIST, because an
 * invitation belongs to exactly one study. They are top-level for SHOW,
 * UPDATE and DELETE, because the id is globally unique and a participant
 * reading their own invitations has no reason to know the study id first.
 *
 * Accept and decline are one PATCH with a status field rather than two POST
 * endpoints, so there is one state machine with one place to guard the
 * transition.
 */
class StudyInvitationApiController extends Controller
{
    public function __construct(private StudyInvitationService $invitations) {}

    /**
     * GET /api/v1/studies/{study}/invitations
     * Every invitation for a study, any status. Researcher only.
     */
    public function index(Request $request, Study $study): JsonResponse
    {
        $this->invitations->assertOwner($study, $request->user());

        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(array_column(InvitationStatus::cases(), 'value'))],
        ]);

        $invitations = StudyInvitation::with('participant:id,name,location')
            ->where('study_id', $study->id)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->get();

        return response()->json([
            'data' => [
                'study_id'    => $study->id,
                'study_title' => $study->title,
                'slots'       => (int) $study->slots,
                'seats_taken' => $this->invitations->seatsTaken($study),
                'count'       => $invitations->count(),
                'invitations' => $invitations->map(fn ($i) => $this->payload($i))->all(),
            ],
        ], 200);
    }

    /**
     * POST /api/v1/studies/{study}/invitations
     * Body: { "participant_id": 12 }
     *
     * 201 on success. 409 if they already have a live invitation — the
     * unique index on (study_id, participant_id) makes a duplicate
     * impossible, so a double-clicked button cannot create two.
     */
    public function store(Request $request, Study $study): JsonResponse
    {
        $data = $request->validate([
            'participant_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $participant = User::findOrFail($data['participant_id']);

        $invitation = $this->invitations->invite($study, $participant, $request->user());

        return response()->json([
            'message'    => 'Invitation sent to ' . $participant->name . '.',
            'data'       => $this->payload($invitation->load('participant:id,name,location')),
            'seats_taken' => $this->invitations->seatsTaken($study),
        ], 201);
    }

    /**
     * GET /api/v1/invitations
     * The signed-in participant's own invitations.
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->role === UserRole::PARTICIPANT, 403,
            'Only participants receive study invitations.');

        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(array_column(InvitationStatus::cases(), 'value'))],
        ]);

        $invitations = StudyInvitation::with(['study:id,title,description,category,method,duration_minutes,incentive_type,compensation_amount,slots,deadline,status,researcher_id', 'study.researcher:id,name', 'inviter:id,name'])
            ->where('participant_id', $user->id)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->get();

        return response()->json([
            'data' => [
                'user_id'       => $user->id,
                'count'         => $invitations->count(),
                'pending_count' => $invitations->where('status', InvitationStatus::PENDING)->count(),
                'invitations'   => $invitations->map(fn ($i) => $this->payload($i, true))->all(),
            ],
        ], 200);
    }

    /**
     * GET /api/v1/invitations/{invitation}
     * Readable by the participant it belongs to, or the researcher who sent it.
     */
    public function show(Request $request, StudyInvitation $invitation): JsonResponse
    {
        $this->assertCanView($request->user(), $invitation);

        $invitation->load(['study:id,title,status,slots,researcher_id', 'study.researcher:id,name', 'participant:id,name,location', 'inviter:id,name']);

        return response()->json(['data' => $this->payload($invitation, true)], 200);
    }

    /**
     * PATCH /api/v1/invitations/{invitation}
     * Body: { "status": "accepted" | "declined" }   — participant only.
     */
    public function update(Request $request, StudyInvitation $invitation): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(InvitationStatus::participantResponses())],
        ]);

        $updated = $this->invitations->respond(
            $invitation,
            $request->user(),
            InvitationStatus::from($data['status'])
        );

        $updated->load(['study:id,title,status,slots,researcher_id', 'study.researcher:id,name', 'inviter:id,name']);

        return response()->json([
            'message' => $updated->status === InvitationStatus::ACCEPTED
                ? 'You accepted the invitation to "' . $updated->study?->title . '".'
                : 'You declined the invitation to "' . $updated->study?->title . '".',

            // False while Member 2's PipelineWriter is unimplemented: the
            // acceptance is recorded but has not reached her pipeline yet.
            'pipeline_available' => $this->invitations->pipelineAvailable(),

            'data' => $this->payload($updated, true),
        ], 200);
    }

    /**
     * DELETE /api/v1/invitations/{invitation}
     * Researcher withdraws a pending invitation.
     *
     * Returns 200 with the updated resource rather than 204, because the
     * page needs the new status to re-render the button.
     */
    public function destroy(Request $request, StudyInvitation $invitation): JsonResponse
    {
        $updated = $this->invitations->withdraw($invitation, $request->user());

        return response()->json([
            'message' => 'Invitation withdrawn.',
            'data'    => $this->payload($updated->load('participant:id,name,location')),
        ], 200);
    }

    // -----------------------------------------------------------------

    private function payload(StudyInvitation $invitation, bool $withStudy = false): array
    {
        $data = [
            'id'                    => $invitation->id,
            'study_id'              => $invitation->study_id,
            'participant_id'        => $invitation->participant_id,
            'participant_name'      => $invitation->participant?->name,
            'invited_by'            => $invitation->invited_by,
            'inviter_name'          => $invitation->inviter?->name,
            'status'                => $invitation->status->value,
            'status_label'          => $invitation->status->label(),
            'match_score_at_invite' => (int) $invitation->match_score_at_invite,
            'match_reasons'         => $invitation->match_reasons ?? [],
            'invited_at'            => $invitation->created_at?->toIso8601String(),
            'responded_at'          => $invitation->responded_at?->toIso8601String(),
            'expires_at'            => $invitation->expires_at?->toIso8601String(),
            'can_respond'           => $invitation->isOpen(),
        ];

        if ($withStudy && $invitation->study) {
            $study = $invitation->study;

            $data['study'] = [
                'id'                  => $study->id,
                'title'               => $study->title,
                'description'         => $study->description,
                'category'            => $study->category,
                'method'              => $study->method,
                'duration_minutes'    => $study->duration_minutes,
                'incentive_type'      => $study->incentive_type?->value,
                'compensation_amount' => $study->compensation_amount,
                'slots'               => (int) $study->slots,
                'deadline'            => $study->deadline?->format('d M Y'),
                'status'              => $study->status?->value,
                'researcher_name'     => $study->researcher?->name,
                'url'                 => route('studies.show', $study),
            ];
        }

        return $data;
    }

    private function assertCanView(User $actor, StudyInvitation $invitation): void
    {
        $allowed = $actor->id === $invitation->participant_id
            || $actor->id === $invitation->invited_by
            || $actor->role === UserRole::ADMIN;

        abort_unless($allowed, 403, 'You may not view this invitation.');
    }
}
