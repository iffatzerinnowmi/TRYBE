<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Study;
use App\Services\FreeToPaidUnlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API — paid-study applications gated by the volunteer/Karma unlock cycle
 * (Feature A + Feature B, Member 3).
 *
 * All the real logic — eligibility, route selection, Karma spend, counter
 * updates — lives in FreeToPaidUnlockService::applyToPaidStudy(). This
 * controller only authorises the caller and shapes the response.
 */
class PaidApplicationApiController extends Controller
{
    public function __construct(private FreeToPaidUnlockService $unlock) {}

    /**
     * GET /api/v1/studies/{study}/paid-application
     *
     * Eligibility snapshot the frontend uses to pick the button state:
     * standard Apply, Spend N Karma & Apply, or disabled.
     */
    public function eligibility(Request $request, Study $study): JsonResponse
    {
        $this->assertParticipant($request);

        return response()->json([
            'data' => $this->unlock->eligibility($request->user()),
        ], 200);
    }

    /**
     * POST /api/v1/studies/{study}/paid-application
     *
     * Applies to the study, spending a free or Karma-bought slot.
     */
    public function apply(Request $request, Study $study): JsonResponse
    {
        $this->assertParticipant($request);

        $result = $this->unlock->applyToPaidStudy($request->user(), $study);

        $message = $result['route'] === 'karma'
            ? 'Applied — ' . $this->unlock->karmaUnlockCost() . ' Karma spent to unlock this slot.'
            : 'Applied using a free unlocked slot.';

        return response()->json(['message' => $message, 'data' => $result], 201);
    }

    private function assertParticipant(Request $request): void
    {
        abort_unless(
            $request->user()->role === UserRole::PARTICIPANT,
            403,
            'Only participants can apply to studies.'
        );
    }
}