<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minimal scaffolding for the Screener Survey Builder (Task 5).
 * Implementation note: detailed question types and storage can be added
 * later with dedicated models and migrations. For now the API exposes
 * placeholder endpoints so the frontend can be wired up.
 */
class ScreenerApiController extends Controller
{
    public function index(Request $request, $studyId): JsonResponse
    {
        return response()->json(['data' => ['questions' => []]], 200);
    }

    public function store(Request $request, $studyId): JsonResponse
    {
        // Accepts a JSON payload describing questions; not persisted yet.
        $payload = $request->input('questions', []);

        return response()->json(['message' => 'Saved (placeholder).', 'data' => ['questions' => $payload]], 201);
    }

    public function show(Request $request, $studyId, $questionId): JsonResponse
    {
        return response()->json(['data' => ['id' => $questionId, 'type' => 'text', 'label' => 'Placeholder question']], 200);
    }

    public function update(Request $request, $studyId, $questionId): JsonResponse
    {
        return response()->json(['message' => 'Updated (placeholder).'], 200);
    }

    public function destroy(Request $request, $studyId, $questionId): JsonResponse
    {
        return response()->json(['message' => 'Deleted (placeholder).'], 200);
    }
}
