<?php

namespace App\Console\Commands;

use App\Services\SeatAuctionService;
use Illuminate\Console\Command;

/**
 * FEATURE — Limited Seat Auctions (Member 3)
 *
 * Closes every open auction whose 48h deadline has passed (the 3x-slots
 * fill trigger already closes an auction immediately inside
 * SeatAuctionService::apply(), so this command only ever has to catch the
 * time-based trigger).
 *
 * RUN: php artisan auction:close-due
 */
class AuctionCloseDueCommand extends Command
{
    protected $signature = 'auction:close-due';

    protected $description = 'Close seat auctions whose 48h deadline has passed and award seats by reliability';

    public function handle(SeatAuctionService $auctions): int
    {
        $count = $auctions->closeAllDue();

        $this->info($count === 0
            ? 'No seat auctions were due to close.'
            : "Closed {$count} seat auction(s) and awarded seats.");

        return self::SUCCESS;
    }
}