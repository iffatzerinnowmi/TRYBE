<?php

namespace App\Enums;

/**
 * Where a single referral stands.
 *
 * FLAGGED is a status rather than a boolean so it goes through the same enum
 * discipline as everything else, and so a flagged referral still SHOWS on
 * the page with a reason instead of vanishing and looking like a bug.
 */
enum ReferralStatus: string
{
    case PENDING   = 'pending';     // signed up, has not qualified yet
    case QUALIFIED = 'qualified';   // did the qualifying thing
    case FLAGGED   = 'flagged';     // suspected abuse — excluded from counts

    public function label(): string
    {
        return match ($this) {
            self::PENDING   => 'Signed up',
            self::QUALIFIED => 'Completed a study',
            self::FLAGGED   => 'Under review',
        };
    }

    /** Only these count towards a milestone. */
    public static function countableValues(): array
    {
        return [self::QUALIFIED->value];
    }
}
