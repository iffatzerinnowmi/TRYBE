<?php

namespace App\Contracts;

use App\Models\PaymentPayout;

/**
 * FEATURE — Verified Payment Escrow (Member 3)
 *
 * The one seam between PaymentEscrowService and "actually moving money".
 * SimulatedPaymentGateway is the only implementation today (bound in
 * EscrowServiceProvider). Swapping in a real bank/mobile-money/gift-card
 * API later means implementing this interface and changing that one
 * binding — nothing in PaymentEscrowService, the observers, the console
 * commands, or any controller needs to change.
 */
interface PaymentGateway
{
    /**
     * Attempt to send a single payout.
     *
     * @return array{success: bool, reference: ?string, error: ?string}
     */
    public function send(PaymentPayout $payout): array;
}