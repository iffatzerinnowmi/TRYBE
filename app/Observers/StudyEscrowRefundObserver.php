<?php

namespace App\Observers;

use App\Enums\StudyStatus;
use App\Models\Study;
use App\Services\PaymentEscrowService;
use Illuminate\Support\Facades\Log;

/**
 * FEATURE — Verified Payment Escrow (Member 3)
 *
 * studies.status is written from several places (StudyController web
 * flow, StudyApiController::update, an admin panel later), and "cancel a
 * study" is not this feature's endpoint to own. So rather than add a
 * refund call inside someone else's controller, this observer watches the
 * model itself and refunds whatever remains in escrow the moment status
 * flips to CANCELLED, no matter which code path did it.
 */
class StudyEscrowRefundObserver
{
    public function __construct(private PaymentEscrowService $escrow)
    {
    }

    public function updated(Study $study): void
    {
        if ($study->status !== StudyStatus::CANCELLED) {
            return;
        }

        if (! $study->wasChanged('status')) {
            return;
        }

        try {
            $this->escrow->refund($study, 'Study cancelled');
        } catch (\Throwable $e) {
            Log::warning('Escrow refund on study cancellation failed.', [
                'study_id' => $study->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}