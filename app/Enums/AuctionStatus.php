<?php

namespace App\Enums;

/**
 * FEATURE — Limited Seat Auctions (Member 3)
 *
 * studies.auction_status. Null means the study was never put in auction
 * mode at all — that is a distinct state from either of these two.
 *
 *   OPEN    accepting applications, closes automatically at auction_closes_at
 *           or once 3x the seats have applied, whichever comes first.
 *   CLOSED  seats decided — winners confirmed, everyone notified.
 */
enum AuctionStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::CLOSED => 'Closed',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::OPEN => 'blue',
            self::CLOSED => 'green',
        };
    }
}
