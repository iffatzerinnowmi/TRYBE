<?php

namespace App\Enums;

enum PayoutMethod: string
{
    case BKASH = 'bkash';
    case BANK_TRANSFER = 'bank_transfer';
    case MOBILE_MONEY = 'mobile_money';
    case GIFT_CARD = 'gift_card';

    public function label(): string
    {
        return match ($this) {
            self::BKASH => 'bKash',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::MOBILE_MONEY => 'Mobile Money',
            self::GIFT_CARD => 'Gift Card',
        };
    }
}