<?php
namespace App\Enums;
enum IncentiveType: string {
    case CASH='cash'; case VOUCHER='voucher'; case COURSE_CREDIT='course_credit'; case VOLUNTEER='volunteer';
    public function label(): string {
        return match($this){ self::CASH=>'Cash', self::VOUCHER=>'Voucher', self::COURSE_CREDIT=>'Course Credit', self::VOLUNTEER=>'Volunteer / Unpaid' };
    }
    public function requiresEscrow(): bool { return in_array($this,[self::CASH,self::VOUCHER]); }

    /** Course credit is the only type that needs an uploaded document. */
    public function requiresDocumentation(): bool { return $this === self::COURSE_CREDIT; }

    /** Short helper text shown under each option on the "post a study" form. */
    public function description(): string {
        return match($this){
            self::CASH => 'A fixed amount paid to each participant through the platform.',
            self::VOUCHER => 'A digital gift card sent to each participant on completion.',
            self::COURSE_CREDIT => 'Academic credit awarded by your institution instead of money.',
            self::VOLUNTEER => 'No compensation — participants take part for free.',
        };
    }
}
