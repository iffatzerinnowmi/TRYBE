<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Study;
use App\Models\StudyParticipation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineApiController extends Controller
{
    public function index(Request $request, Study $study): JsonResponse
    {
        $rows = StudyParticipation::with('participant:id,name')
            ->where('study_id', $study->id)
            ->latest('updated_at')
            ->get()
            ->map(fn($p) => [
                'participant_id' => $p->participant_id,
                'name' => $p->participant?->name,
                'stage' => $p->stage instanceof \App\Enums\PipelineStage ? $p->stage->value : (string) $p->stage,
                'updated_at' => $p->updated_at?->toIso8601String(),
            ]);

        return response()->json(['data' => ['study_id' => $study->id, 'participants' => $rows]], 200);
    }

    public function updateStage(Request $request, Study $study): JsonResponse
    {
        $data = $request->validate([
            'participant_id' => ['required', 'integer', 'exists:users,id'],
            'stage' => ['required', 'string'],
        ]);

        // Member 2 owns this column; this endpoint is the correct place to
        // mutate it. Use updateOrCreate to be idempotent.
        StudyParticipation::updateOrCreate(
            ['study_id' => $study->id, 'participant_id' => $data['participant_id']],
            ['stage' => $data['stage']]
        );

        return response()->json(['message' => 'Stage updated.'], 200);
    }
}
