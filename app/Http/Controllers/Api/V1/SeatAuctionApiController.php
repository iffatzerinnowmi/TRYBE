<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Study;
use App\Services\SeatAuctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API — Limited Seat Auctions (Member 3)
 *
 * No scoring or ranking logic lives here — SeatAuctionService is the only
 * place that touches studies.auction_* / study_seat_applications, so the
 * Blade page and this API can never disagree.
 */
class SeatAuctionApiController extends Controller
{
    public function __construct(private SeatAuctionService $auctions) {}

    /**
     * GET /api/v1/studies/{study}/auction
     *
     * Viewable by anyone logged in — participants need it to decide whether
     * to apply, the owning researcher needs it to watch results come in.
     */
    public function show(Request $request, Study $study): JsonResponse
    {
        return response()->json([
            'data' => $this->auctions->payload($study, $request->user()),
        ], 200);
    }

    /**
     * POST /api/v1/studies/{study}/auction/apply
     *
     * Participant applies for a seat. Ranking happens later, when the
     * auction closes — this just records the application with a
     * reliability snapshot.
     */
    public function apply(Request $request, Study $study): JsonResponse
    {
        $this->auctions->apply($study, $request->user());

        return response()->json([
            'message' => 'Application submitted. Seats are awarded by reliability score when the auction closes.',
            'data'    => $this->auctions->payload($study->fresh(), $request->user()),
        ], 201);
    }

    /**
     * POST /api/v1/studies/{study}/auction/close
     *
     * Manual early close — the study's own researcher, or an admin. In
     * normal operation the auction closes itself via the deadline/fill
     * trigger; this is mainly for demos and support.
     */
    public function close(Request $request, Study $study): JsonResponse
    {
        $actor = $request->user();

        abort_unless(
            $actor->role === UserRole::ADMIN || $actor->id === $study->researcher_id,
            403,
            'Only the study\'s researcher or an admin may close this auction.'
        );

        abort_unless((bool) $study->auction_mode, 422, 'This study is not running a seat auction.');
        abort_unless($study->auction_status?->value === 'open', 422, 'This auction is already closed.');

        $this->auctions->closeAuction($study, 'manual');

        return response()->json([
            'message' => 'Auction closed and seats awarded.',
            'data'    => $this->auctions->payload($study->fresh(), $actor),
        ], 200);
    }
}