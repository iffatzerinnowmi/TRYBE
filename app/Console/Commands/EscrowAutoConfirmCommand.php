<?php

namespace App\Console\Commands;

use App\Services\PaymentEscrowService;
use Illuminate\Console\Command;

/**
 * FEATURE — Verified Payment Escrow (Member 3)
 *
 * Protects the participant: if the researcher never confirms a payout,
 * this confirms and releases it on their behalf once
 * confirmation_deadline_at (default 72h, config('platform.escrow.payout_confirmation_hours'))
 * has passed.
 *
 * RUN:  php artisan escrow:auto-confirm
 */
class EscrowAutoConfirmCommand extends Command
{
    protected $signature = 'escrow:auto-confirm';

    protected $description = 'Auto-confirm and release payouts whose 72h researcher-confirmation window has passed';

    public function handle(PaymentEscrowService $escrow): int
    {
        $count = $escrow->autoConfirmDue();

        $this->info($count === 0
            ? 'No payouts were due for auto-confirmation.'
            : "Auto-confirmed and processed {$count} payout(s).");

        return self::SUCCESS;
    }
}