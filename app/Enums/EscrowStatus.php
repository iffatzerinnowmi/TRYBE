<?php

namespace App\Enums;

/**
 * FEATURE — Verified Payment Escrow (Member 3)
 *
 * State of a study's PaymentEscrow row (one per cash/voucher study).
 *
 *   LOCKED              full compensation deposited, nothing released yet
 *   PARTIALLY_RELEASED  some payouts completed, balance remains
 *   RELEASED            fully paid out to participants
 *   REFUNDED            study cancelled — remaining balance returned
 *   FAILED              reserved for an escrow that could not be locked
 */
enum EscrowStatus: string
{
    case LOCKED = 'locked';
    case PARTIALLY_RELEASED = 'partially_released';
    case RELEASED = 'released';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::LOCKED => 'Locked',
            self::PARTIALLY_RELEASED => 'Partially Released',
            self::RELEASED => 'Released',
            self::REFUNDED => 'Refunded',
            self::FAILED => 'Failed',
        };
    }

    /** Tailwind color family for status badges — used by the Blade views. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::LOCKED => 'blue',
            self::PARTIALLY_RELEASED => 'amber',
            self::RELEASED => 'green',
            self::REFUNDED => 'slate',
            self::FAILED => 'red',
        };
    }
}