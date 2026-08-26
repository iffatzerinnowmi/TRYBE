<?php

namespace App\Observers;

use App\Enums\PipelineStage;
use App\Models\StudyParticipation;
use App\Services\PaymentEscrowService;
use Illuminate\Support\Facades\Log;

class StudyParticipationPayoutObserver
{
    public function __construct(private PaymentEscrowService $escrow) {}

    public function updated(StudyParticipation $participation): void { $this->payIfJustCompleted($participation); }
    public function created(StudyParticipation $participation): void { $this->payIfJustCompleted($participation); }

    private function payIfJustCompleted(StudyParticipation $participation): void
    {
        if ($participation->stage !== PipelineStage::COMPLETED) {
            return;
        }

        if ($participation->wasRecentlyCreated === false && ! $participation->wasChanged('stage')) {
            return;
        }

        try {
            $this->escrow->createPayoutForCompletion($participation);
        } catch (\Throwable $e) {
            Log::warning('Payout creation for study completion failed.', [
                'participation_id' => $participation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}