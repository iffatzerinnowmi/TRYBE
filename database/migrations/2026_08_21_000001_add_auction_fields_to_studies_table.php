<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FEATURE — Limited Seat Auctions (Member 3)
 *
 * Adds the auction fields to `studies`. Read defensively already by
 * StudyInvitationService::auctionModeBlocker() — that guard has been inert
 * until this column existed, and goes live the moment this migration runs.
 *
 *   auction_mode        researcher opted in (only offered when the study is
 *                        eligible — see SeatAuctionService::isEligible()).
 *   auction_status       null            -> auction mode is off
 *                        'open'          -> accepting applications
 *                        'closed'        -> seats have been decided
 *   auction_opened_at    when the listing went live in auction mode.
 *   auction_closes_at    auction_opened_at + config('platform.auction.duration_hours').
 *                        Whichever of the deadline / 3x-slots trigger comes
 *                        first closes the auction — see
 *                        SeatAuctionService::closeAuction().
 *   auction_closed_at    when it actually closed (may be earlier than
 *                        auction_closes_at if the 3x-slots trigger fired
 *                        first).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('studies', function (Blueprint $table) {
            $table->boolean('auction_mode')->default(false)->after('escrow_locked_at');
            $table->string('auction_status')->nullable()->after('auction_mode');
            $table->timestamp('auction_opened_at')->nullable()->after('auction_status');
            $table->timestamp('auction_closes_at')->nullable()->after('auction_opened_at');
            $table->timestamp('auction_closed_at')->nullable()->after('auction_closes_at');

            $table->index(['auction_mode', 'auction_status']);
        });
    }

    public function down(): void
    {
        Schema::table('studies', function (Blueprint $table) {
            $table->dropIndex(['auction_mode', 'auction_status']);
            $table->dropColumn([
                'auction_mode', 'auction_status', 'auction_opened_at',
                'auction_closes_at', 'auction_closed_at',
            ]);
        });
    }
};