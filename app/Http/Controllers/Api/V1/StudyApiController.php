<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudyRequest;
use App\Models\Study;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudyApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Study::query()->with('researcher');

        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $s = '%' . $request->input('search') . '%';
            $q->where(fn($qb) => $qb->where('title', 'like', $s)->orWhere('description', 'like', $s));
        }

        $studies = $q->latest('id')->paginate(20);

        return response()->json(['data' => $studies], 200);
    }

    public function show(Study $study): JsonResponse
    {
        $study->load('researcher');
        return response()->json(['data' => $study], 200);
    }

    public function store(StoreStudyRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Delegate to existing web controller's logic where possible by
        // reusing much of the same fields. Keeping this minimal here.
        $study = Study::create([
            'researcher_id' => auth()->id(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'method' => $data['method'],
            'duration_minutes' => $data['duration_minutes'],
            'slots' => $data['slots'],
            'deadline' => $data['deadline'] ?? null,
            'incentive_type' => $data['incentive_type'],
            'compensation_amount' => $data['compensation_amount'] ?? 0,
            'status' => 'draft',
        ]);

        return response()->json(['message' => 'Study created.', 'data' => $study], 201);
    }

    public function update(Request $request, Study $study): JsonResponse
    {
        $this->authorize('update', $study);

        $attributes = $request->only(['title','description','category','method','duration_minutes','slots','deadline','status']);

        $study->update($attributes);

        return response()->json(['message' => 'Study updated.', 'data' => $study->fresh()], 200);
    }

    public function destroy(Study $study): JsonResponse
    {
        $this->authorize('delete', $study);
        $study->delete();

        return response()->json(['message' => 'Study deleted.'], 200);
    }
}
