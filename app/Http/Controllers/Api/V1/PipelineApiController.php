<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PipelineStage;
use App\Http\Controllers\Controller;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Enums\StudyStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineApiController extends Controller
{
    /**
     * Get all participants and their pipeline stages for a study.
     * Only the researcher or admin can view the pipeline.
     */
    public function index(Request $request, Study $study): JsonResponse
    {
        $user = $request->user();

        // Authorization: only researcher or admin can view the pipeline
        if (! $user || ($user->id !== $study->researcher_id && $user->role?->value !== 'admin')) {
            return response()->json(['message' => 'You are not authorized to view this pipeline.'], 403);
        }

        $rows = StudyParticipation::with('participant:id,name')
            ->where('study_id', $study->id)
            ->latest('updated_at')
            ->get()
            ->map(fn($p) => [
                'participant_id' => $p->participant_id,
                'name' => $p->participant?->name,
                'stage' => $p->stage instanceof PipelineStage ? $p->stage->value : (string) $p->stage,
                'stage_label' => $p->stage instanceof PipelineStage ? $p->stage->label() : (string) $p->stage,
                'attendance_status' => $p->attendance_status ?? 'pending',
                'updated_at' => $p->updated_at?->toIso8601String(),
            ]);

        return response()->json(['data' => [
            'study_id' => $study->id,
            'study_completed' => $study->completed_at !== null,
            'participants' => $rows,
        ]], 200);
    }

    /**
     * Get recruitment progress statistics for a study.
     * Shows participant counts by stage and overall completion metrics.
     */
    public function stats(Request $request, Study $study): JsonResponse
    {
        $user = $request->user();

        // Authorization: only researcher or admin can view the stats
        if (! $user || ($user->id !== $study->researcher_id && $user->role?->value !== 'admin')) {
            return response()->json(['message' => 'You are not authorized to view this pipeline.'], 403);
        }

        // Get counts for each stage
        $stageCounts = StudyParticipation::where('study_id', $study->id)
            ->selectRaw('stage, COUNT(*) as count')
            ->groupBy('stage')
            ->pluck('count', 'stage')
            ->toArray();

        // Initialize all stages with 0 count
        $stageBreakdown = [];
        foreach (PipelineStage::cases() as $stage) {
            $stageBreakdown[] = [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'count' => $stageCounts[$stage->value] ?? 0,
            ];
        }

        // Calculate progress metrics
        $totalParticipants = (int) array_sum(array_values($stageCounts));
        $completedCount = ($stageCounts[PipelineStage::COMPLETED->value] ?? 0) + 
                         ($stageCounts[PipelineStage::PAID->value] ?? 0);
        $paidCount = $stageCounts[PipelineStage::PAID->value] ?? 0;
        $rejectedCount = $stageCounts[PipelineStage::REJECTED->value] ?? 0;
        $noShowCount = $stageCounts[PipelineStage::NO_SHOW->value] ?? 0;

        $completionPercentage = $totalParticipants > 0 
            ? round(($completedCount / $totalParticipants) * 100, 2) 
            : 0;

        return response()->json([
            'data' => [
                'study_id' => $study->id,
                'summary' => [
                    'total_participants' => $totalParticipants,
                    'completed' => $completedCount,
                    'paid' => $paidCount,
                    'rejected' => $rejectedCount,
                    'no_show' => $noShowCount,
                    'completion_percentage' => $completionPercentage,
                ],
                'stage_breakdown' => $stageBreakdown,
            ],
        ], 200);
    }

    /**
     * Update a participant's pipeline stage.
     * Only the researcher or admin can modify the pipeline.
     */
    public function updateStage(Request $request, Study $study): JsonResponse
    {
        $user = $request->user();

        // Authorization: only researcher or admin can update the pipeline
        if (! $user || ($user->id !== $study->researcher_id && $user->role?->value !== 'admin')) {
            return response()->json(['message' => 'You are not authorized to modify this pipeline.'], 403);
        }

        $data = $request->validate([
            'participant_id' => ['required', 'integer', 'exists:users,id'],
            'stage' => ['required', 'string'],
        ]);

        // Validate that the stage is a valid PipelineStage
        try {
            $stage = PipelineStage::from($data['stage']);
        } catch (\ValueError) {
            return response()->json(['message' => 'Invalid pipeline stage.'], 422);
        }

        // Member 2 owns this column; this endpoint is the correct place to
        // mutate it. Use updateOrCreate to be idempotent.
        $participation = StudyParticipation::updateOrCreate(
            ['study_id' => $study->id, 'participant_id' => $data['participant_id']],
            ['stage' => $stage]
        );

        return response()->json([
            'message' => 'Stage updated.',
            'data' => [
                'participant_id' => $participation->participant_id,
                'stage' => $participation->stage->value,
                'stage_label' => $participation->stage->label(),
                'updated_at' => $participation->updated_at?->toIso8601String(),
            ],
        ], 200);
    }

    public function updateAttendance(Request $request, Study $study): JsonResponse
    {
        $this->authorizeResearcher($request, $study, 'modify');

        $data = $request->validate([
            'participant_id' => ['required', 'integer', 'exists:users,id'],
            'attendance_status' => ['required', 'in:participated,not_participated'],
        ]);

        $participation = StudyParticipation::where('study_id', $study->id)
            ->where('participant_id', $data['participant_id'])
            ->firstOrFail();

        $participation->attendance_status = $data['attendance_status'];
        $participation->stage = $data['attendance_status'] === 'not_participated'
            ? PipelineStage::NO_SHOW
            : ($study->completed_at ? PipelineStage::COMPLETED : $participation->stage);
        $participation->completed_at = $participation->stage === PipelineStage::COMPLETED ? now() : null;
        $participation->save();

        return response()->json(['message' => 'Attendance updated.', 'data' => [
            'participant_id' => $participation->participant_id,
            'attendance_status' => $participation->attendance_status,
            'stage' => $participation->stage->value,
        ]]);
    }

    public function completeStudy(Request $request, Study $study): JsonResponse
    {
        $this->authorizeResearcher($request, $study, 'modify');

        $completedAt = now();
        $study->update(['completed_at' => $completedAt, 'status' => StudyStatus::CLOSED]);

        $participants = StudyParticipation::with('participant:id,name')
            ->where('study_id', $study->id)
            ->where('attendance_status', 'participated')
            ->get();

        $participants->each(function (StudyParticipation $participation) use ($completedAt) {
            $participation->update([
                'stage' => PipelineStage::COMPLETED,
                'completed_at' => $completedAt,
            ]);
        });

        return response()->json(['message' => 'Study marked as completed.', 'data' => [
            'study_completed' => true,
            'participants_for_endorsement' => $participants->map(fn ($p) => [
                'participant_id' => $p->participant_id,
                'name' => $p->participant?->name,
            ])->values(),
        ]]);
    }

    private function authorizeResearcher(Request $request, Study $study, string $action): void
    {
        $user = $request->user();

        abort_unless($user && ($user->id === $study->researcher_id || $user->role?->value === 'admin'), 403,
            "You are not authorized to {$action} this pipeline.");
    }
}
