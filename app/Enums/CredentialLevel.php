<?php
namespace App\Enums;
enum CredentialLevel: string {
    case NONE='none'; case BRONZE='bronze'; case GOLD='gold'; case EXPERT='expert';
    public function label(): string {
        return match($this){ self::NONE=>'Unranked', self::BRONZE=>'Bronze', self::GOLD=>'Gold', self::EXPERT=>'Expert' };
    }
    public function minCompletions(): int {
        return match($this){ self::NONE=>0, self::BRONZE=>1, self::GOLD=>10, self::EXPERT=>25 };
    }
    public static function fromCompletions(int $count): self {
        return match(true){ $count>=25=>self::EXPERT, $count>=10=>self::GOLD, $count>=1=>self::BRONZE, default=>self::NONE };
    }
}
