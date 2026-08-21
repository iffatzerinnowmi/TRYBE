<?php

namespace App\Observers;

use App\Enums\KarmaSource;
use App\Models\StudyReview;
use App\Services\KarmaService;
use Illuminate\Support\Facades\Log;

class StudyReviewObserver
{
    public function __construct(private KarmaService $karma) {}

    public function created(StudyReview $review): void
    {
        if ($review->reviewer_role !== 'participant') {
            return;
        }

        try {
            $this->karma->earn(
                $review->participant,
                KarmaSource::REVIEW_LEFT,
                'Left a review for "'.($review->study?->title ?? 'a study').'"'
            );
        } catch (\Throwable $e) {
            Log::warning('Karma award for review failed.', [
                'review_id' => $review->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}