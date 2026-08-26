<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PayoutMethod;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Study;
use App\Services\PaymentEscrowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API — Verified Payment Escrow ledger  (Member 3)
 */
class EscrowApiController extends Controller
{
    public function __construct(private PaymentEscrowService $escrow)
    {
    }

    /** GET /api/v1/studies/{study}/escrow */
    public function show(Request $request, Study $study): JsonResponse
    {
        $this->assertCanManage($request, $study);

        $study->loadMissing('escrow', 'payouts');
        $escrow = $study->escrow;

        if (! $escrow) {
            return response()->json([
                'data' => [
                    'study_id' => $study->id,
                    'has_escrow' => false,
                    'message' => 'This study does not use escrow (course credit / volunteer), or funds were never locked.',
                ],
            ], 200);
        }

        return response()->json([
            'data' => [
                'study_id' => $study->id,
                'has_escrow' => true,
                'status' => $escrow->status->value,
                'status_label' => $escrow->status->label(),
                'total_amount' => (float) $escrow->total_amount,
                'released_amount' => (float) $escrow->released_amount,
                'refunded_amount' => (float) $escrow->refunded_amount,
                'fee_charged' => (float) $escrow->fee_charged,
                'fee_percentage' => $escrow->fee_percentage,
                'remaining_amount' => $escrow->remainingAmount(),
                'locked_at' => $escrow->locked_at?->toIso8601String(),
                'refunded_at' => $escrow->refunded_at?->toIso8601String(),
                'refund_reason' => $escrow->refund_reason,
                'payouts' => $study->payouts->map(fn ($p) => [
                    'id' => $p->id,
                    'participant_id' => $p->participant_id,
                    'amount' => (float) $p->amount,
                    'method' => $p->method->value,
                    'status' => $p->status->value,
                    'attempts' => $p->attempts,
                ])->values(),
            ],
        ], 200);
    }

    /**
     * POST /api/v1/studies/{study}/escrow/refund
     * Restricted to admin — normal cancellation triggers refund automatically.
     */
    public function refund(Request $request, Study $study): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->role === UserRole::ADMIN, 403, 'Only an admin may trigger a manual refund.');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $escrow = $this->escrow->refund($study, $data['reason'] ?? 'Manual admin refund');

        if (! $escrow) {
            return response()->json(['message' => 'This study has no escrow to refund.'], 404);
        }

        return response()->json([
            'message' => 'Escrow refunded.',
            'data' => [
                'status' => $escrow->status->value,
                'refunded_amount' => (float) $escrow->refunded_amount,
                'fee_charged' => (float) $escrow->fee_charged,
            ],
        ], 200);
    }

    /** PATCH /api/v1/researchers/me/payout-settings */
    public function updatePayoutSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::RESEARCHER, 403, 'Only researchers have payout settings.');
        abort_unless($user->researcherProfile, 404, 'No researcher profile found for this account.');

        $data = $request->validate([
            'payout_method' => ['required', 'string', 'in:' . implode(',', array_map(fn ($c) => $c->value, PayoutMethod::cases()))],
            'payout_details' => ['nullable', 'array'],
        ]);

        $user->researcherProfile->update([
            'payout_method' => $data['payout_method'],
            'payout_details' => $data['payout_details'] ?? null,
        ]);

        return response()->json([
            'message' => 'Payout settings saved.',
            'data' => [
                'payout_method' => $user->researcherProfile->payout_method,
                'payout_details' => $user->researcherProfile->payout_details,
            ],
        ], 200);
    }

    private function assertCanManage(Request $request, Study $study): void
    {
        $actor = $request->user();
        $allowed = $actor->id === $study->researcher_id || $actor->role === UserRole::ADMIN;
        abort_unless($allowed, 403, 'You may not view this study\'s escrow.');
    }
}