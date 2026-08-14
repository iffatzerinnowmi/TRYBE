<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Session notes & tagging scaffolding (Task 7). A production version would
 * persist notes and tags to dedicated tables; this is a placeholder API.
 */
class NotesApiController extends Controller
{
    public function index(Request $request, User $participant): JsonResponse
    {
        return response()->json(['data' => ['notes' => []]], 200);
    }

    public function store(Request $request, User $participant): JsonResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ]);

        return response()->json(['message' => 'Note saved (placeholder).', 'data' => $data], 201);
    }
}
