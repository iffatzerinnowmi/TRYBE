<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Models\Study;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationService;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * API — Notification centre and Web Push  (Member 1, graded feature)
 *
 * Like the other API controllers, this holds no rules. Which types exist,
 * which role may receive them, and whether a given user wants one all live in
 * NotificationService. Delivery lives in WebPushService. This file decides
 * only how the answer is SHAPED for the screen.
 *
 * TWO CONSUMERS
 * -------------
 *   1. The notifications page — feed, filters, preferences, push setup.
 *   2. The navbar bell — on EVERY page, via unreadSummary().
 *
 * The bell is why unreadSummary() is deliberately small: it runs on every
 * page load in the app, so it returns a count and four rows, nothing more.
 * Do not add fields to it without thinking about that cost.
 *
 * ROLE AWARENESS
 * --------------
 * Every list this controller builds — the filter chips AND the preference
 * switches — comes from NotificationService::typesFor($user), never from the
 * full TYPES constant. That is the only reason a researcher no longer sees a
 * "Streak reminders" switch that could never fire.
 *
 * The feed itself is deliberately NOT role-filtered. If an account's role was
 * changed, or a notification predates a rule change, it stays readable. The
 * rules govern what is SENT and what is OFFERED, not what history you may see.
 */
class NotificationApiController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
        private WebPushService $push,
    ) {}

    /**
     * GET /api/v1/notifications?filter=all|unread|studies|verify|...
     *
     * Everything the notifications page needs, in one call.
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $prefs = $this->prefsFor($user);

        // The types THIS role can receive, already re-worded for that role.
        $types = NotificationService::typesFor($user);

        $base = UserNotification::where('user_id', $user->id);

        $total  = (clone $base)->count();
        $unread = (clone $base)->unread()->count();

        // A hand-typed filter must never produce an error page, so anything
        // unrecognised falls back to 'all'. Note this accepts every type,
        // not just this role's — see the class comment about history.
        $allowed = array_merge(['all', 'unread'], array_keys(NotificationService::TYPES));
        $filter  = in_array($request->query('filter'), $allowed, true)
            ? $request->query('filter')
            : 'all';

        $items = (clone $base)
            ->when($filter === 'unread', fn ($q) => $q->unread())
            ->when(
                ! in_array($filter, ['all', 'unread'], true),
                fn ($q) => $q->where('type', $filter)
            )
            ->latest('id')
            ->take(30)
            ->get();

        // The filter chips. Built from this role's types, so a researcher is
        // not offered a Streaks chip that can only ever be empty.
        $filters = [
            ['key' => 'all',    'label' => 'All',    'count' => $total],
            ['key' => 'unread', 'label' => 'Unread', 'count' => $unread],
        ];

        foreach ($types as $key => $type) {
            $filters[] = [
                'key'   => $key,
                'label' => $type['icon'] . ' ' . $this->shortLabel($key),
                'count' => null,
            ];
        }

        // The preference rows. 'locked' types render as ALWAYS ON instead of
        // a switch — they are still listed, because hiding them would leave
        // the researcher unable to see what TRYBE will send them.
        $preferences = [];

        foreach ($types as $key => $type) {
            $locked = (bool) $type['always'];

            $preferences[] = [
                'key'     => $key,
                'icon'    => $type['icon'],
                'label'   => $type['label'],
                'desc'    => $type['desc'],
                'locked'  => $locked,
                'enabled' => $locked ? true : (bool) $prefs->{$type['column']},
            ];
        }

        return response()->json([
            'data' => [
                // The page uses this for its empty-state wording, and it is
                // useful evidence in Postman that the split is real.
                'role'        => NotificationService::roleOf($user),

                'filter'      => $filter,
                'filters'     => $filters,
                'total'       => $total,
                'unread'      => $unread,
                'items'       => $items->map(fn ($item) => $this->item($item, $user)),
                'preferences' => $preferences,

                // Push setup. The public key is meant to be public — the
                // browser needs it in order to subscribe at all.
                'push' => [
                    'configured' => $this->push->isConfigured(),
                    'vapid_key'  => $this->push->publicKey(),
                ],
            ],
        ], 200);
    }

    /**
     * GET /api/v1/notifications/unread-summary
     *
     * The navbar bell. Called on every page, so it stays deliberately small.
     */
    public function unreadSummary(Request $request): JsonResponse
    {
        $user = $request->user();

        $unread = UserNotification::where('user_id', $user->id)
            ->unread()
            ->latest('id')
            ->take(4)
            ->get();

        return response()->json([
            'data' => [
                'unread_count' => UserNotification::where('user_id', $user->id)->unread()->count(),
                'items' => $unread->map(fn ($item) => [
                    'id'          => $item->id,
                    'icon'        => $item->icon,
                    'title'       => $item->title,
                    'body'        => Str::limit($item->body, 90),
                    'url'         => $item->url ?? '/notifications',
                    'created_ago' => $item->created_at?->diffForHumans(),
                ]),
            ],
        ], 200);
    }

    /**
     * POST /api/v1/notifications/preferences
     *
     * A partial update: send only the switches you are changing. Anything
     * left out keeps its current value.
     *
     * Two things are refused rather than ignored, because silently dropping
     * a field is how a bug survives a demo:
     *
     *   - a type that belongs to another role  ('streak' from a researcher)
     *   - a type that is 'always'              ('applications')
     *
     * Both come back as 422 with the offending keys named.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only these may be changed by this account.
        $controllable = NotificationService::controllableFor($user);

        // Anything sent that is not in that list is a mistake worth naming.
        // Underscore-prefixed keys (_token, _method) belong to the framework,
        // not to us, so they are skipped rather than reported.
        $sent = array_filter(
            array_keys($request->all()),
            fn ($key) => ! str_starts_with((string) $key, '_')
        );

        $rejected = array_values(array_diff($sent, $controllable));

        if ($rejected !== []) {
            return response()->json([
                'message' => 'These cannot be changed on a '
                    . NotificationService::roleOf($user) . ' account: '
                    . implode(', ', $rejected) . '.',
            ], 422);
        }

        $rules = [];

        foreach ($controllable as $key) {
            $rules[$key] = ['sometimes', 'boolean'];
        }

        $values = $request->validate($rules);

        if (empty($values)) {
            return response()->json([
                'message' => 'Send at least one preference to change.',
            ], 422);
        }

        $prefs  = $this->prefsFor($user);
        $update = [];

        foreach ($values as $key => $on) {
            $update[NotificationService::TYPES[$key]['column']] = (bool) $on;
        }

        $prefs->update($update);

        return response()->json([
            'message' => 'Notification preferences saved.',
            'data'    => ['saved' => count($update)],
        ], 200);
    }

    /**
     * POST /api/v1/notifications/subscribe
     *
     * The browser calls this after the user grants permission. The endpoint
     * and keys are generated by the browser's own push service — we only
     * store them so WebPushService knows where to deliver.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'        => ['required', 'string'],
            'keys.p256dh'     => ['required', 'string'],
            'keys.auth'       => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        PushSubscription::updateOrCreate(
            [
                'user_id'       => $request->user()->id,
                'endpoint_hash' => PushSubscription::hashFor($data['endpoint']),
            ],
            [
                'endpoint'         => $data['endpoint'],
                'public_key'       => $data['keys']['p256dh'],
                'auth_token'       => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aesgcm',
                'last_used_at'     => now(),
            ]
        );

        return response()->json(['message' => 'Browser subscribed to push.'], 200);
    }

    /**
     * POST /api/v1/notifications/unsubscribe
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hashFor($data['endpoint']))
            ->delete();

        return response()->json(['message' => 'Browser unsubscribed.'], 200);
    }

    /**
     * POST /api/v1/notifications/test
     *
     * Sends yourself one, to prove the whole chain works: role check,
     * preference check, database write, then the external push service.
     *
     * The type used to be hard-coded to 'studies', which meant a researcher
     * pressing this button tested a participant's notification type. It now
     * asks the service which type this account would actually receive.
     */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $this->notifications->firstSendableType($user);

        if (! $type) {
            return response()->json([
                'message' => 'Every notification type on this account is switched off, so nothing was sent. Turn one on and try again.',
                'sent'    => false,
            ], 200);
        }

        $label = NotificationService::typesFor($user)[$type]['label'];

        $notification = $this->notifications->send(
            $user,
            $type,
            'Test notification from TRYBE',
            'Sent as "' . $label . '" — the type your ' . NotificationService::roleOf($user)
                . ' account is set up to receive. If you can see this in your browser, push is working end to end.',
            url('/notifications')
        );

        if (! $notification) {
            return response()->json([
                'message' => 'That type is switched off, so nothing was sent.',
                'sent'    => false,
            ], 200);
        }

        return response()->json([
            'message' => 'Test sent as "' . $label . '" — ' . $notification->push_result . '.',
            'sent'    => true,
            'data'    => $this->item($notification, $user),
        ], 200);
    }

    /**
     * POST /api/v1/notifications/{notification}/read
     */
    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless(
            $notification->user_id === $request->user()->id,
            403,
            'That notification is not yours.'
        );

        $notification->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Marked as read.',
            'data'    => ['unread' => $this->notifications->unreadCount($request->user())],
        ], 200);
    }

    /**
     * POST /api/v1/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $count = UserNotification::where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => $count === 0
                ? 'Nothing to mark — you were already caught up.'
                : 'All notifications marked as read.',
            'data'    => ['marked' => $count, 'unread' => 0],
        ], 200);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /** One notification, shaped for display. */
    private function item(UserNotification $item, User $user): array
    {
        return [
            'id'          => $item->id,
            'type'        => $item->type,
            'icon'        => $item->icon,
            'title'       => $item->title,
            'body'        => $item->body,
            'url'         => $this->effectiveUrl($item, $user),
            'is_unread'   => $item->isUnread(),
            'pushed'      => (bool) $item->pushed,
            'push_result' => $item->push_result,
            'created_ago' => $item->created_at?->diffForHumans(),
        ];
    }

    /**
     * Study invitations are stored with a generic url, but a participant
     * should land on the study itself. Resolved here so the page does not
     * have to know the rule.
     */
    private function effectiveUrl(UserNotification $item, User $user): ?string
    {
        if (
            $item->type === 'studies'
            && Str::startsWith($item->title, 'Invitation: ')
            && $user->role?->value === 'participant'
        ) {
            $study = Study::query()
                ->where('title', Str::after($item->title, 'Invitation: '))
                ->first();

            if ($study) {
                return route('studies.show', $study);
            }
        }

        return $item->url;
    }

    /** Short names for the filter chips — the full labels are too long there. */
    private function shortLabel(string $key): string
    {
        return match ($key) {
            'studies'      => 'Studies',
            'verify'       => 'Verification',
            'endorse'      => 'Endorsements',
            'streak'       => 'Streaks',
            'credential'   => 'Credentials',
            'applications' => 'Applications',
            'slots'        => 'Recruitment',
            'irb'          => 'IRB',
            default        => ucfirst($key),
        };
    }

    /** Every user should have a preferences row; create one if they don't. */
    private function prefsFor(User $user)
    {
        return $user->notificationPreference
            ?? $user->notificationPreference()->create([]);
    }
}