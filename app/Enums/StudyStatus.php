<?php
namespace App\Enums;
enum StudyStatus: string {
    case DRAFT='draft'; case PENDING_REACH='pending_reach'; case OPEN='open'; case PAUSED='paused'; case FULL='full'; case CLOSED='closed'; case CANCELLED='cancelled';
    public function label(): string {
        return match($this){ self::DRAFT=>'Draft', self::PENDING_REACH=>'Pending Reach', self::OPEN=>'Open', self::PAUSED=>'Paused', self::FULL=>'Full', self::CLOSED=>'Closed', self::CANCELLED=>'Cancelled' };
    }
}
