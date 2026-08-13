<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Slot Scheduling scaffolding (Task 6). Real implementation should add
 * a Slot model and migration; this provides placeholder endpoints.
 */
class ScheduleApiController extends Controller
{
    public function index(Request $request, $studyId): JsonResponse
    {
        return response()->json(['data' => ['slots' => []]], 200);
    }

    public function store(Request $request, $studyId): JsonResponse
    {
        $slot = $request->only(['starts_at','ends_at','capacity']);
        return response()->json(['message' => 'Slot created (placeholder).', 'data' => $slot], 201);
    }

    public function book(Request $request, $studyId, $slotId): JsonResponse
    {
        return response()->json(['message' => 'Booked (placeholder).'], 200);
    }
}
