<?php

namespace App\Enums;

/**
 * FEATURE — Verified Payment Escrow (Member 3)
 *
 * State machine for a single PaymentPayout row, entirely owned by
 * PaymentEscrowService:
 *
 *   PENDING -> PROCESSING -> COMPLETED
 *                         \-> FAILED -> (retry) -> PROCESSING -> ...
 *
 * PENDING is also the state auto-confirm (72h) and manual "Confirm" both
 * act on. FAILED is what "Retry" acts on, bounded by attempts < max_attempts.
 */
enum PayoutStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
        };
    }

    /** Tailwind color family for status badges — used by the Blade views. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::PENDING => 'amber',
            self::PROCESSING => 'blue',
            self::COMPLETED => 'green',
            self::FAILED => 'red',
        };
    }
}