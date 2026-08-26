<?php

namespace App\Enums;

/**
 * FEATURE — Limited Seat Auctions (Member 3)
 *
 * study_seat_applications.status.
 *
 *   APPLIED  the auction hasn't closed yet — still in the running.
 *   WON      ranked inside the seat count when the auction closed. A real
 *            study_participations row (CONFIRMED) is created for these via
 *            PipelineWriter, in addition to this row.
 *   LOST     ranked outside the seat count. Nothing is written to
 *            study_participations for these.
 */
enum SeatApplicationStatus: string
{
    case APPLIED = 'applied';
    case WON = 'won';
    case LOST = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::APPLIED => 'Applied',
            self::WON => 'Won a seat',
            self::LOST => 'Not selected',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::APPLIED => 'blue',
            self::WON => 'green',
            self::LOST => 'slate',
        };
    }
}