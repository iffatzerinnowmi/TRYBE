<?php

namespace App\Enums;

/**
 * The lifecycle of a researcher's invitation to a participant.
 *
 * This exists so that "the participant said no" stops being recorded as
 * PipelineStage::REJECTED, which means "the researcher rejected this
 * applicant" and made the pipeline counts on the researcher dashboard wrong.
 * They are different events and now have different storage.
 */
enum InvitationStatus: string
{
    case PENDING   = 'pending';     // sent, awaiting the participant
    case ACCEPTED  = 'accepted';    // participant said yes
    case DECLINED  = 'declined';    // participant said no
    case WITHDRAWN = 'withdrawn';   // researcher took it back
    case EXPIRED   = 'expired';     // passed expires_at without an answer

    public function label(): string
    {
        return match ($this) {
            self::PENDING   => 'Pending',
            self::ACCEPTED  => 'Accepted',
            self::DECLINED  => 'Declined',
            self::WITHDRAWN => 'Withdrawn',
            self::EXPIRED   => 'Expired',
        };
    }

    /** Still awaiting an answer — the only state a participant may respond to. */
    public function isOpen(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Statuses that take a candidate off the researcher's suggested list.
     *
     * PENDING is deliberately absent: an invited-but-undecided candidate
     * stays on the list showing an "Invited" badge, which is the behaviour
     * the feature specification describes.
     */
    public static function resolvedValues(): array
    {
        return [self::ACCEPTED->value, self::DECLINED->value];
    }

    /** The states a participant is allowed to move a pending invitation to. */
    public static function participantResponses(): array
    {
        return [self::ACCEPTED->value, self::DECLINED->value];
    }
}
