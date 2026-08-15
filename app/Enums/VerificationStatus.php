<?php
namespace App\Enums;
enum VerificationStatus: string {
    case UNVERIFIED='unverified'; case PENDING='pending'; case VERIFIED='verified'; case REJECTED='rejected';
    public function label(): string {
        return match($this){ self::UNVERIFIED=>'Unverified', self::PENDING=>'Pending Review', self::VERIFIED=>'Verified', self::REJECTED=>'Rejected' };
    }
}
