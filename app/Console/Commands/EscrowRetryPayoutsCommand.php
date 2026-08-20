<?php

namespace App\Console\Commands;

use App\Services\PaymentEscrowService;
use Illuminate\Console\Command;

/**
 * FEATURE — Verified Payment Escrow (Member 3)
 *
 * Retries every FAILED payout whose backoff window has passed, up to
 * max_attempts. Also sweeps for completed participations that never got a
 * payout row because the escrow balance was short at the time.
 *
 * RUN:  php artisan escrow:retry-payouts
 */
class EscrowRetryPayoutsCommand extends Command
{
    protected $signature = 'escrow:retry-payouts';

    protected $description = 'Retry failed payouts that are due for another attempt, and create any missing payouts';

    public function handle(PaymentEscrowService $escrow): int
    {
        $retried = $escrow->retryDue();

        $this->info($retried === 0
            ? 'No failed payouts were due for retry.'
            : "Retried {$retried} failed payout(s).");

        return self::SUCCESS;
    }
}