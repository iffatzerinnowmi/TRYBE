<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TierApiController extends Controller
{
    public function myTier(Request $request): JsonResponse
    {
        $user = $request->user();

        // Placeholder: real logic reads researcher tier from DB or config.
        return response()->json(['data' => [
            'researcher_id' => $user->id,
            'tier' => 'free',
            'post_limit' => 3,
            'volunteer_requirement' => 0,
        ]], 200);
    }
}
