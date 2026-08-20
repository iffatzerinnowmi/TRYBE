<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;

/**
 * FEATURE — Notification centre.
 *
 * Everywhere else in TRYBE calls this one class. It does three things in
 * order, and never skips step one:
 *
 *   1. Check this type is meant for this user's ROLE, and that they want it.
 *   2. Write it to user_notifications.
 *   3. Ask WebPushService to deliver it to their browser.
 *
 * Because step 2 always happens, the bell menu is a complete history even
 * when push is off, blocked, or the person was offline.
 *
 * ------------------------------------------------------------------------
 * WHAT CHANGED, AND WHY
 * ------------------------------------------------------------------------
 * The five original types were all written for a participant. A researcher
 * was being shown "Streak reminders" and "Credential level-ups" — two
 * switches that could never fire on their account — and was shown nothing
 * about applications, recruitment or ethics review, which is the only
 * notification traffic a researcher actually has.
 *
 * So each type now declares three extra things:
 *
 *   'roles'     — who may receive it at all. A type is invisible to every
 *                 other role, and send() refuses to write it.
 *
 *   'always'    — operational notifications that cannot be muted. A
 *                 researcher does not get to switch off "a participant
 *                 applied to your study": the study would silently stall.
 *                 These render as a locked ALWAYS ON row, not a switch.
 *
 *   'overrides' — the same type can mean different things to different
 *                 roles. 'verify' to a researcher is "your documents were
 *                 approved"; to an admin it is "someone submitted documents".
 *                 Rather than inventing two types, one type re-words itself.
 *
 * The columns still exist for the 'always' types even though nothing reads
 * them today. That is deliberate: turning one of them into a real user
 * switch is a one-word edit here — 'always' => false — with no migration.
 */
class NotificationService
{
    public function __construct(private WebPushService $push) {}

    /**
     * Every notification type in TRYBE. 'column' must match a real column on
     * notification_preferences; that pairing is checked by nothing but us, so
     * adding one here means adding one there.
     */
    public const TYPES = [

        // ---------------- Shared, but worded per role ----------------

        'studies' => [
            'column' => 'notify_studies',
            'icon'   => '🔬',
            'label'  => 'New studies from researchers I follow',
            'desc'   => 'The moment a followed researcher opens a study.',
            'roles'  => ['participant', 'researcher', 'organization', 'admin'],
            'always' => false,
            'overrides' => [
                'researcher' => [
                    'label' => 'Study activity',
                    'desc'  => 'When a study you run opens, closes, or changes state.',
                ],
                'organization' => [
                    'label' => 'Study activity',
                    'desc'  => 'When a study your organization runs changes state.',
                ],
                'admin' => [
                    'label' => 'Platform study activity',
                    'desc'  => 'When studies open or close across TRYBE.',
                ],
            ],
        ],

        'verify' => [
            'column' => 'notify_verify',
            'icon'   => '✅',
            'label'  => 'Verification updates',
            'desc'   => 'When your verification is approved or needs attention.',
            'roles'  => ['participant', 'researcher', 'organization', 'admin'],
            'always' => false,
            'overrides' => [
                'admin' => [
                    'label' => 'Verification requests',
                    'desc'  => 'When a researcher or organization submits documents for review.',
                ],
            ],
        ],

        // ---------------- Participant only ----------------

        'endorse' => [
            'column' => 'notify_endorse',
            'icon'   => '⭐',
            'label'  => 'New endorsements',
            'desc'   => 'When a researcher endorses you, and when you unlock Verified Participant.',
            'roles'  => ['participant'],
            'always' => false,
        ],

        'streak' => [
            'column' => 'notify_streak',
            'icon'   => '🔥',
            'label'  => 'Streak reminders',
            'desc'   => 'A nudge before your weekly streak resets.',
            'roles'  => ['participant'],
            'always' => false,
        ],

        'credential' => [
            'column' => 'notify_credential',
            'icon'   => '🏅',
            'label'  => 'Credential level-ups',
            'desc'   => 'When you reach Bronze, Gold or Expert.',
            'roles'  => ['participant'],
            'always' => false,
        ],

        // ---------------- Researcher / organization only ----------------
        // All three are 'always': muting them would let a study stall
        // without the person who owns it ever being told.

        'applications' => [
            'column' => 'notify_applications',
            'icon'   => '📥',
            'label'  => 'New applications',
            'desc'   => 'When a participant applies to one of your studies. Always on — a study cannot run if you miss these.',
            'roles'  => ['researcher', 'organization'],
            'always' => true,
        ],

        'slots' => [
            'column' => 'notify_slots',
            'icon'   => '📊',
            'label'  => 'Recruitment progress',
            'desc'   => 'When a study fills its last seat, or its deadline is close with seats left.',
            'roles'  => ['researcher', 'organization'],
            'always' => true,
        ],

        'irb' => [
            'column' => 'notify_irb',
            'icon'   => '📄',
            'label'  => 'IRB & ethics review',
            'desc'   => 'When an ethics document is accepted, rejected, or a study is flagged.',
            'roles'  => ['researcher', 'organization', 'admin'],
            'always' => true,
            'overrides' => [
                'admin' => [
                    'label' => 'IRB flags',
                    'desc'  => 'When a study is flagged for ethics review.',
                ],
            ],
        ],
    ];

    // -----------------------------------------------------------------
    // Role resolution
    // -----------------------------------------------------------------

    /** A user's role as a plain string. Falls back to participant. */
    public static function roleOf(?User $user): string
    {
        return $user?->role?->value ?? 'participant';
    }

    /**
     * The types this user's role can receive, already re-worded for that
     * role. Key order is the order they appear on screen.
     *
     * This is the ONE place role visibility is decided. The API controller,
     * the preference panel, the filter chips and wants() all read it, so
     * they can never drift apart.
     */
    public static function typesFor(?User $user): array
    {
        $role  = self::roleOf($user);
        $types = [];

        foreach (self::TYPES as $key => $type) {
            if (! in_array($role, $type['roles'], true)) {
                continue;
            }

            // Merge the role-specific wording over the default wording.
            $types[$key] = array_merge($type, $type['overrides'][$role] ?? []);
        }

        return $types;
    }

    /** May this user receive this type at all? */
    public static function isVisibleTo(?User $user, string $type): bool
    {
        return array_key_exists($type, self::typesFor($user));
    }

    /** Is this type operational — always on, no switch? */
    public static function isLocked(string $type): bool
    {
        return (bool) (self::TYPES[$type]['always'] ?? false);
    }

    /** Types this user is allowed to switch on and off. */
    public static function controllableFor(?User $user): array
    {
        return array_keys(array_filter(
            self::typesFor($user),
            fn (array $type) => ! $type['always']
        ));
    }

    // -----------------------------------------------------------------
    // Sending
    // -----------------------------------------------------------------

    /**
     * Should this user be sent this type right now?
     *
     * Three gates, in order:
     *   1. the type exists
     *   2. it belongs to this user's role
     *   3. either it is 'always', or their switch is on
     */
    public function wants(User $user, string $type): bool
    {
        $definition = self::TYPES[$type] ?? null;

        if (! $definition) {
            Log::warning('NotificationService: unknown type "' . $type . '" was requested.');
            return false;
        }

        if (! self::isVisibleTo($user, $type)) {
            // Not an error, but almost always a mistake in the calling code —
            // for example sending 'streak' to a researcher. Logged rather
            // than thrown, so one wrong call can never break a page, but it
            // is never silent either. Check storage/logs/laravel.log.
            Log::warning(sprintf(
                'NotificationService: dropped type "%s" for user %d — not sent to role "%s".',
                $type,
                $user->id,
                self::roleOf($user)
            ));
            return false;
        }

        if ($definition['always']) {
            return true;
        }

        $prefs = $user->notificationPreference
            ?? NotificationPreference::firstOrCreate(['user_id' => $user->id]);

        return (bool) $prefs->{$definition['column']};
    }

    /**
     * Record a notification and try to push it.
     * Returns null when the user has this type switched off, or when the
     * type does not belong to their role.
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

    /**
     * The first type this user would actually receive right now.
     *
     * Used by the "Send a test" button, which used to hard-code 'studies'.
     * That worked for participants and quietly failed for anyone else, so
     * the type is chosen from the user's own role instead.
     */
    public function firstSendableType(User $user): ?string
    {
        foreach (array_keys(self::typesFor($user)) as $key) {
            if ($this->wants($user, $key)) {
                return $key;
            }
        }

        return null;
    }

    public function unreadCount(User $user): int
    {
        return UserNotification::where('user_id', $user->id)->unread()->count();
    }
}