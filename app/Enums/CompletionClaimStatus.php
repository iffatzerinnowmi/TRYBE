<?php

namespace App\Enums;

/**
 * The life of a completion claim  (Member 4)
 *
 * SUBMITTED is the only value this feature ever writes. ACCEPTED and REJECTED
 * are written when the researcher acts — see StudyCompletionService::markReviewed(),
 * which Member 2's pipeline calls.
 *
 * Note what is absent: there is no COMPLETED. Completion is a pipeline stage,
 * not a claim status, and conflating the two is exactly the mistake this
 * separation exists to prevent.
 */
enum CompletionClaimStatus: string
{
    case SUBMITTED = 'submitted';
    case ACCEPTED  = 'accepted';
    case REJECTED  = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Awaiting confirmation',
            self::ACCEPTED  => 'Confirmed by the researcher',
            self::REJECTED  => 'Not accepted',
        };
    }

    /** Is this claim still the participant's most recent word on the matter? */
    public function isOpen(): bool
    {
        return $this === self::SUBMITTED;
    }

    /** A rejected claim may be submitted again; the other two may not. */
    public function allowsResubmit(): bool
    {
        return $this === self::REJECTED;
    }
}
