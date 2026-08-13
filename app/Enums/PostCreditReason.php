<?php

namespace App\Enums;

/** Why a row exists in the researcher_post_credits ledger. */
enum PostCreditReason: string
{
    case REFERRAL_EARNED = 'referral_earned';
    case POST_SPENT      = 'post_spent';
    case ADMIN_ADJUST    = 'admin_adjust';

    public function label(): string
    {
        return match ($this) {
            self::REFERRAL_EARNED => 'Earned from a referral',
            self::POST_SPENT      => 'Spent on a paid post',
            self::ADMIN_ADJUST    => 'Adjusted by an admin',
        };
    }
}
