<?php
namespace App\Enums;
enum IncentiveType: string {
    case CASH='cash'; case VOUCHER='voucher'; case COURSE_CREDIT='course_credit'; case VOLUNTEER='volunteer';
    public function label(): string {
        return match($this){ self::CASH=>'Cash', self::VOUCHER=>'Voucher', self::COURSE_CREDIT=>'Course Credit', self::VOLUNTEER=>'Volunteer / Unpaid' };
    }
    public function requiresEscrow(): bool { return in_array($this,[self::CASH,self::VOUCHER]); }
}
