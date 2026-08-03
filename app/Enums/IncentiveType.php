<?php

namespace App\Enums;

enum IncentiveType: string
{
    case CASH = 'cash';
    case VOUCHER = 'voucher';
    case COURSE_CREDIT = 'course_credit';
    case VOLUNTEER_UNPAID = 'volunteer_unpaid';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::VOUCHER => 'Voucher',
            self::COURSE_CREDIT => 'Course Credit',
            self::VOLUNTEER_UNPAID => 'Volunteer / Unpaid',
        };
    }

    public function requiresAmount(): bool
    {
        return in_array($this, [self::CASH, self::VOUCHER], true);
    }

    public function requiresEscrow(): bool
    {
        return $this->requiresAmount();
    }

    public function requiresDocumentation(): bool
    {
        return $this === self::COURSE_CREDIT;
    }

    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
