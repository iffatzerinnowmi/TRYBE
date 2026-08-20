<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PaymentPayout;
use App\Models\Study;
use App\Services\PaymentEscrowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutApiController extends Controller
{
    public function __construct(private PaymentEscrowService $escrow)
    {
    }

    /** GET /api/v1/payouts/me */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::PARTICIPANT, 403, 'Only participants have payouts.');

        $payouts = PaymentPayout::where('participant_id', $user->id)
            ->with('study')
            ->latest('id')
            ->get();

        return response()->json(['data' => $payouts->map(fn ($p) => $this->payload($p))], 200);
    }

    /** GET /api/v1/studies/{study}/payouts */
    public function forStudy(Request $request, Study $study): JsonResponse
    {
        $this->assertCanManage($request, $study);

        $payouts = PaymentPayout::where('study_id', $study->id)
            ->with('participant')
            ->latest('id')
            ->get();

        return response()->json(['data' => $payouts->map(fn ($p) => $this->payload($p, includeParticipant: true))], 200);
    }

    /** GET /api/v1/payouts/{payout} */
    public function show(Request $request, PaymentPayout $payout): JsonResponse
    {
        $this->assertCanView($request, $payout);

        return response()->json(['data' => $this->payload($payout, includeParticipant: true)], 200);
    }

    /** POST /api/v1/payouts/{payout}/confirm */
    public function confirm(Request $request, PaymentPayout $payout): JsonResponse
    {
        $actor = $request->user();
        $isResearcher = $actor->id === $payout->study?->researcher_id;
        $isAdmin = $actor->role === UserRole::ADMIN;
        abort_unless($isResearcher || $isAdmin, 403, 'Only the study\'s researcher may confirm this payout.');

        if ($payout->status !== PayoutStatus::PENDING) {
            return response()->json([
                'message' => "This payout is already {$payout->status->label()} — nothing to confirm.",
                'data' => $this->payload($payout),
            ], 409);
        }

        $confirmed = $this->escrow->confirmAndRelease($payout, $isResearcher ? 'researcher' : 'admin');

        return response()->json([
            'message' => match ($confirmed->status) {
                PayoutStatus::COMPLETED => 'Payment released.',
                PayoutStatus::FAILED => 'Confirmed, but the payment gateway declined the transfer. It will retry automatically.',
                default => 'Payout is processing.',
            },
            'data' => $this->payload($confirmed),
        ], 200);
    }

    /** POST /api/v1/payouts/{payout}/retry */
    public function retry(Request $request, PaymentPayout $payout): JsonResponse
    {
        $actor = $request->user();
        $isResearcher = $actor->id === $payout->study?->researcher_id;
        $isAdmin = $actor->role === UserRole::ADMIN;
        abort_unless($isResearcher || $isAdmin, 403, 'Only the study\'s researcher may retry this payout.');

        if ($payout->status !== PayoutStatus::FAILED) {
            return response()->json([
                'message' => "This payout is {$payout->status->label()}, not failed — nothing to retry.",
                'data' => $this->payload($payout),
            ], 409);
        }

        if ($payout->attempts >= $payout->max_attempts) {
            return response()->json([
                'message' => "This payout already used all {$payout->max_attempts} attempts.",
                'data' => $this->payload($payout),
            ], 409);
        }

        $retried = $this->escrow->retryFailed($payout);

        return response()->json([
            'message' => $retried->status === PayoutStatus::COMPLETED ? 'Payment released.' : 'Retry attempted — gateway declined again.',
            'data' => $this->payload($retried),
        ], 200);
    }

    private function payload(PaymentPayout $payout, bool $includeParticipant = false): array
    {
        $data = [
            'id' => $payout->id,
            'study_id' => $payout->study_id,
            'study_title' => $payout->study?->title,
            'study_participation_id' => $payout->study_participation_id,
            'amount' => (float) $payout->amount,
            'method' => $payout->method->value,
            'method_label' => $payout->method->label(),
            'status' => $payout->status->value,
            'status_label' => $payout->status->label(),
            'attempts' => $payout->attempts,
            'max_attempts' => $payout->max_attempts,
            'next_retry_at' => $payout->next_retry_at?->toIso8601String(),
            'last_error' => $payout->last_error,
            'gateway_reference' => $payout->gateway_reference,
            'confirmed_by' => $payout->confirmed_by,
            'confirmed_at' => $payout->confirmed_at?->toIso8601String(),
            'confirmation_deadline_at' => $payout->confirmation_deadline_at?->toIso8601String(),
            'processed_at' => $payout->processed_at?->toIso8601String(),
            'failed_at' => $payout->failed_at?->toIso8601String(),
        ];

        if ($includeParticipant) {
            $data['participant_id'] = $payout->participant_id;
            $data['participant_name'] = $payout->participant?->name;
        }

        return $data;
    }

    private function assertCanManage(Request $request, Study $study): void
    {
        $actor = $request->user();
        $allowed = $actor->id === $study->researcher_id || $actor->role === UserRole::ADMIN;
        abort_unless($allowed, 403, 'You may not view this study\'s payouts.');
    }

    private function assertCanView(Request $request, PaymentPayout $payout): void
    {
        $actor = $request->user();
        $allowed = $actor->id === $payout->participant_id
            || $actor->id === $payout->study?->researcher_id
            || $actor->role === UserRole::ADMIN;
        abort_unless($allowed, 403, 'You may not view this payout.');
    }
}