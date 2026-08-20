<?php

namespace App\Enums;

/**
 * FEATURE — Verified Payment Escrow (Member 3)
 *
 * How a researcher's payouts are actually delivered to participants.
 * PaymentPayout::$method and ResearcherProfile::$payout_method both cast
 * to this. Referenced by SimulatedPaymentGateway (reference-number prefix
 * + simulated error text) and PaymentEscrowService::payoutMethodFor().
 */
enum PayoutMethod: string
{
    case BANK_TRANSFER = 'bank_transfer';
    case MOBILE_MONEY = 'mobile_money';
    case GIFT_CARD = 'gift_card';

    public function label(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'Bank Transfer',
            self::MOBILE_MONEY => 'Mobile Money',
            self::GIFT_CARD => 'Gift Card',
        };
    }
}