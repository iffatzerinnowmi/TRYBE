<?php
namespace App\Enums;
enum UserRole: string {
    case ADMIN='admin'; case RESEARCHER='researcher'; case PARTICIPANT='participant'; case ORGANIZATION='organization';
    public function label(): string {
        return match($this){ self::ADMIN=>'Admin', self::RESEARCHER=>'Researcher', self::PARTICIPANT=>'Participant', self::ORGANIZATION=>'Organization' };
    }
}
