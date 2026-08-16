<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\UserNotification;

/**
 * FEATURE — Notification centre.
 *
 * Everywhere else in TRYBE calls this one class. It does three things in
 * order, and never skips step one:
 *
 *   1. Check the user actually wants this type of notification.
 *   2. Write it to user_notifications.
 *   3. Ask WebPushService to deliver it to their browser.
 *
 * Because step 2 always happens, the bell menu is a complete history even
 * when push is off, blocked, or the person was offline.
 */
class NotificationService
{
    public function __construct(private WebPushService $push) {}

    /** The five types, matching the columns on notification_preferences. */
    public const TYPES = [
        'studies' => [
            'column' => 'notify_studies',
            'icon'   => '🔬',
            'label'  => 'New studies from researchers I follow',
            'desc'   => 'The moment a followed researcher opens a study.',
        ],
        'verify' => [
            'column' => 'notify_verify',
            'icon'   => '✅',
            'label'  => 'Verification updates',
            'desc'   => 'When your verification is approved or needs attention.',
        ],
        'endorse' => [
            'column' => 'notify_endorse',
            'icon'   => '⭐',
            'label'  => 'New endorsements',
            'desc'   => 'When a researcher endorses you, and when you unlock Verified Participant.',
        ],
        'streak' => [
            'column' => 'notify_streak',
            'icon'   => '🔥',
            'label'  => 'Streak reminders',
            'desc'   => 'A nudge before your weekly streak resets.',
        ],
        'credential' => [
            'column' => 'notify_credential',
            'icon'   => '🏅',
            'label'  => 'Credential level-ups',
            'desc'   => 'When you reach Bronze, Gold or Expert.',
        ],
        'unlock' => [
            'column' => 'notify_unlock',
            'icon'   => '🔓',
            'label'  => 'Paid study access unlocked',
            'desc'   => 'When you complete enough free studies to apply to paid ones.',
        ],
    ];

    /** Has this user switched this type on? Defaults to yes. */
    public function wants(User $user, string $type): bool
    {
        $column = self::TYPES[$type]['column'] ?? null;

        if (! $column) {
            return false;
        }

        $prefs = $user->notificationPreference
            ?? NotificationPreference::firstOrCreate(['user_id' => $user->id]);

        return (bool) $prefs->{$column};
    }

    /**
     * Record a notification and try to push it.
     * Returns null when the user has this type switched off.
     */
    public function send(
        User $user,
        string $type,
        string $title,
        string $body,
        ?string $url = null
    ): ?UserNotification {

        if (! $this->wants($user, $type)) {
            return null;
        }

        $notification = UserNotification::create([
            'user_id' => $user->id,
            'type'    => $type,
            'icon'    => self::TYPES[$type]['icon'],
            'title'   => $title,
            'body'    => $body,
            'url'     => $url,
        ]);

        $result = $this->push->send($notification);

        $notification->update([
            'pushed'      => str_starts_with($result, 'sent'),
            'push_result' => $result,
        ]);

        return $notification->fresh();
    }

    public function unreadCount(User $user): int
    {
        return UserNotification::where('user_id', $user->id)->unread()->count();
    }
}
