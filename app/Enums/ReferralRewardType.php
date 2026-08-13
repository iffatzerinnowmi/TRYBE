<?php

namespace App\Enums;

/**
 * The two rewards the specification promises.
 *
 * Which one applies is decided by the REFERRER's role, and it only fires
 * when the referred user has the same role — a researcher referring a
 * participant earns neither, and the API says so rather than silently
 * counting to three and paying nothing.
 */
enum ReferralRewardType: string
{
    case CREDENTIAL_TIER_UNLOCK = 'credential_tier_unlock';   // participant -> participant
    case RESEARCHER_POST_CREDIT = 'researcher_post_credit';   // researcher  -> researcher

    public function label(): string
    {
        return match ($this) {
            self::CREDENTIAL_TIER_UNLOCK => 'Credential tier unlock',
            self::RESEARCHER_POST_CREDIT => 'Free paid-post credit',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CREDENTIAL_TIER_UNLOCK => 'Skip straight to the next credential tier without waiting for the completion count.',
            self::RESEARCHER_POST_CREDIT => 'One free paid study post.',
        };
    }

    /** The reward a user of this role can earn, or null if none. */
    public static function forRole(?UserRole $role): ?self
    {
        return match ($role) {
            UserRole::PARTICIPANT => self::CREDENTIAL_TIER_UNLOCK,
            UserRole::RESEARCHER  => self::RESEARCHER_POST_CREDIT,
            default               => null,
        };
    }
}
