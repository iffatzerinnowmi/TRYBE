<?php

namespace App\Enums;

/**
 * Every reason karma can move, in either direction. KarmaService is the
 * only writer to karma_transactions, and every write it makes carries one
 * of these — so "what is this row for" is always a fixed, closed list.
 */
enum KarmaSource: string
{
    // ---- Earn ----
    case STUDY_COMPLETED  = 'study_completed';
    case SESSION_ON_TIME  = 'session_on_time';
    case REVIEW_LEFT      = 'review_left';
    case REFERRAL_SUCCESS = 'referral_success';

    // ---- Spend ----
    case PRIORITY_PLACEMENT_BOOST = 'priority_placement_boost'; // researchers
    case COMPETITION_FEE_DISCOUNT = 'competition_fee_discount'; // participants/teams
     case PAID_APPLICATION_UNLOCK  = 'paid_application_unlock';  // NEW — Feature B

    case MANUAL_ADJUST = 'manual_adjustment';

    public function isEarn(): bool
    {
        return in_array($this, [
            self::STUDY_COMPLETED,
            self::SESSION_ON_TIME,
            self::REVIEW_LEFT,
            self::REFERRAL_SUCCESS,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::STUDY_COMPLETED          => 'Study Completed',
            self::SESSION_ON_TIME          => 'Session Attended',
            self::REVIEW_LEFT              => 'Post-Session Review',
            self::REFERRAL_SUCCESS         => 'Successful Referral',
            self::PRIORITY_PLACEMENT_BOOST => 'Priority Placement Boost',
            self::COMPETITION_FEE_DISCOUNT => 'Competition Fee Discount',
            self::PAID_APPLICATION_UNLOCK  => 'Paid Application Unlock',

            self::MANUAL_ADJUST            => 'Adjustment',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::STUDY_COMPLETED          => '✓',
            self::SESSION_ON_TIME          => '✓',
            self::REVIEW_LEFT              => '★',
            self::REFERRAL_SUCCESS         => '👥',
            self::PRIORITY_PLACEMENT_BOOST => '↑',
            self::COMPETITION_FEE_DISCOUNT => '🎟️',
            self::PAID_APPLICATION_UNLOCK  => '🔓',
            self::MANUAL_ADJUST            => '•',
        };
    }
}