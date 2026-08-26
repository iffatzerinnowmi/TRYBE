<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Enums\EscrowStatus;
use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Enums\PipelineStage;
use App\Models\PaymentEscrow;
use App\Models\PaymentPayout;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FEATURE — Verified Payment Escrow (Member 3)
 *
 * The only writer for payment_escrows and payment_payouts.
 *
 *   Study created (cash/voucher)    -> lockFunds()
 *   Participation reaches COMPLETED -> createPayoutForCompletion()
 *   Payout confirmed (manual/72h)   -> confirmAndRelease() -> applyGatewayResult()
 *   Payout failed                   -> retryFailed() -> applyGatewayResult()
 *   bKash checkout callback         -> applyGatewayResult() directly
 *   Study cancelled                 -> refund()
 */
class PaymentEscrowService
{
    public function __construct(
        private PaymentGateway $gateway,
        private NotificationService $notifications,
    ) {
    }

    public function lockFunds(Study $study): bool
    {
        try {
            PaymentEscrow::updateOrCreate(
                ['study_id' => $study->id],
                [
                    'total_amount' => $study->compensation_amount,
                    'released_amount' => 0,
                    'refunded_amount' => 0,
                    'fee_charged' => 0,
                    'fee_percentage' => (int) config('platform.escrow.cancellation_fee_percent', 10),
                    'status' => EscrowStatus::LOCKED,
                    'locked_at' => now(),
                ]
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Escrow lock failed.', [
                'study_id' => $study->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function createPayoutForCompletion(StudyParticipation $participation): ?PaymentPayout
    {
        $study = $participation->study()->with('escrow', 'researcher.researcherProfile')->first();

        if (! $study || ! $study->incentive_type->requiresEscrow()) {
            return null;
        }

        $escrow = $study->escrow;

        if (! $escrow || $escrow->status === EscrowStatus::REFUNDED) {
            return null;
        }

        if (PaymentPayout::where('study_participation_id', $participation->id)->exists()) {
            return null;
        }

        $confirmationHours = (int) config('platform.escrow.payout_confirmation_hours', 72);
        $maxAttempts = (int) config('platform.escrow.payout_max_attempts', 3);

        $payout = PaymentPayout::create([
            'study_id' => $study->id,
            'participant_id' => $participation->participant_id,
            'study_participation_id' => $participation->id,
            'amount' => $study->compensation_amount,
            'method' => $this->payoutMethodFor($study->researcher),
            'status' => PayoutStatus::PENDING,
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'confirmation_deadline_at' => now()->addHours($confirmationHours),
        ]);

        $this->notifyResearcher(
            $study->researcher,
            'A payout is ready to confirm',
            'A participant completed "'.$study->title.'" — confirm the payout via bKash or it auto-releases in '.$confirmationHours.'h.',
            route('researcher.payments')
        );

        return $payout;
    }

    /**
     * Move a pending payout to processing and attempt to send it through
     * the bound gateway (SimulatedPaymentGateway). Used by the 72h
     * escrow:auto-confirm command's unattended path — NOT by the manual
     * "Pay via bKash" button, which goes through BkashPaymentGateway's
     * createCheckout()/executeCheckout() redirect flow instead and calls
     * applyGatewayResult() directly once bKash's callback comes back.
     */
    public function confirmAndRelease(PaymentPayout $payout, string $confirmedBy = 'researcher'): PaymentPayout
    {
        return DB::transaction(function () use ($payout, $confirmedBy) {
            $payout->update([
                'status' => PayoutStatus::PROCESSING,
                'confirmed_by' => $confirmedBy,
                'confirmed_at' => now(),
                'attempts' => $payout->attempts + 1,
            ]);

            return $this->attemptSend($payout);
        });
    }

    public function retryFailed(PaymentPayout $payout): PaymentPayout
    {
        if (! $payout->isRetryable()) {
            return $payout;
        }

        return DB::transaction(function () use ($payout) {
            $payout->update([
                'status' => PayoutStatus::PROCESSING,
                'attempts' => $payout->attempts + 1,
            ]);

            return $this->attemptSend($payout);
        });
    }

    /** Calls the bound gateway (simulated), then finalizes via applyGatewayResult(). */
    private function attemptSend(PaymentPayout $payout): PaymentPayout
    {
        return $this->applyGatewayResult($payout, $this->gateway->send($payout));
    }

    /**
     * Finalizes a payout given an already-known gateway result. Used
     * internally by attemptSend() (simulated/auto-release path), and
     * directly by ResearcherPaymentController::bkashCallback() for the
     * real bKash flow, where the result only becomes available on a
     * separate request (the callback) rather than synchronously.
     */
    public function applyGatewayResult(PaymentPayout $payout, array $result): PaymentPayout
    {
        if ($result['success']) {
            $payout->update([
                'status' => PayoutStatus::COMPLETED,
                'gateway_reference' => $result['reference'],
                'last_error' => null,
                'processed_at' => now(),
            ]);

            $payout->study()->first()?->escrow?->increment('released_amount', $payout->amount);

            $payout->studyParticipation()->first()?->update(['stage' => PipelineStage::PAID]);

            $this->notifyParticipant(
                $payout->participant,
                'Payment released',
                'Your payout of ৳'.number_format((float) $payout->amount, 0).' has been sent via bKash.',
                route('participant.karma')
            );

            return $payout->fresh();
        }

        $backoffMinutes = (int) config('platform.escrow.payout_retry_backoff_minutes', 30);

        $payout->update([
            'status' => PayoutStatus::FAILED,
            'last_error' => $result['error'],
            'failed_at' => now(),
            'next_retry_at' => $payout->attempts < $payout->max_attempts
                ? now()->addMinutes($backoffMinutes)
                : null,
        ]);

        $this->notifyResearcher(
            $payout->study()->first()?->researcher,
            'A payout failed',
            'A payout failed: '.$result['error'],
            route('researcher.payments')
        );

        return $payout->fresh();
    }

    public function refund(Study $study, string $reason): ?PaymentEscrow
    {
        $escrow = $study->escrow ?? $study->escrow()->first();

        if (! $escrow || in_array($escrow->status, [EscrowStatus::REFUNDED, EscrowStatus::FAILED], true)) {
            return $escrow;
        }

        $remaining = $escrow->remainingAmount();
        $fee = round($remaining * ($escrow->fee_percentage / 100), 2);
        $refundAmount = max(0, $remaining - $fee);

        $escrow->update([
            'refunded_amount' => $escrow->refunded_amount + $refundAmount,
            'fee_charged' => $escrow->fee_charged + $fee,
            'status' => EscrowStatus::REFUNDED,
            'refunded_at' => now(),
            'refund_reason' => $reason,
        ]);

        $this->notifyResearcher(
            $study->researcher,
            'Escrow refunded',
            'Your escrow balance of ৳'.number_format($refundAmount, 0).' was refunded after cancelling "'.$study->title.'" (a '.$escrow->fee_percentage.'% fee applied).',
            route('researcher.payments')
        );

        return $escrow->fresh();
    }

    public function autoConfirmDue(): int
    {
        $count = 0;

        PaymentPayout::dueForAutoConfirm()->each(function (PaymentPayout $payout) use (&$count) {
            $this->confirmAndRelease($payout, confirmedBy: 'system:auto-confirm');
            $count++;
        });

        return $count;
    }

    public function retryDue(): int
    {
        $count = 0;

        PaymentPayout::retryable()->each(function (PaymentPayout $payout) use (&$count) {
            $this->retryFailed($payout);
            $count++;
        });

        return $count;
    }

    public function payoutMethodFor(?User $researcher): PayoutMethod
    {
        $configured = $researcher?->researcherProfile?->payout_method;

        if ($configured instanceof PayoutMethod) {
            return $configured;
        }

        if (is_string($configured)) {
            return PayoutMethod::tryFrom($configured) ?? PayoutMethod::BKASH;
        }

        return PayoutMethod::BKASH;
    }

    private function notifyResearcher(?User $researcher, string $title, string $body, ?string $url = null): void
    {
        if (! $researcher) {
            return;
        }

        try {
            $this->notifications->send($researcher, 'payments', $title, $body, $url);
        } catch (\Throwable $e) {
            Log::warning('Escrow notification (researcher) failed.', ['error' => $e->getMessage()]);
        }
    }

    private function notifyParticipant(?User $participant, string $title, string $body, ?string $url = null): void
    {
        if (! $participant) {
            return;
        }

        try {
            $this->notifications->send($participant, 'payments', $title, $body, $url);
        } catch (\Throwable $e) {
            Log::warning('Escrow notification (participant) failed.', ['error' => $e->getMessage()]);
        }
    }
}