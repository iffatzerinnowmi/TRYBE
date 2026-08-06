<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * EXTERNAL API — Web Push (VAPID).
 *
 * This is the integration point with a third-party service. When we send, the
 * request goes to whichever push service the user's browser belongs to:
 *
 *   Chrome / Edge  ->  https://fcm.googleapis.com/fcm/send/...
 *   Firefox        ->  https://updates.push.services.mozilla.com/...
 *   Safari         ->  https://web.push.apple.com/...
 *
 * We never choose that URL. The browser hands it to us when the user clicks
 * "Enable notifications", and we store it as the subscription endpoint. VAPID
 * keys are how the push service verifies the request really came from TRYBE.
 *
 * If keys are not configured, every method degrades quietly: the notification
 * is still saved and still appears in the bell menu, it just isn't pushed.
 */
class WebPushService
{
    /** Is push actually wired up right now? */
    public function isConfigured(): bool
    {
        return (bool) config('webpush.enabled')
            && class_exists(WebPush::class);
    }

    /** The public key the browser needs in order to subscribe. */
    public function publicKey(): ?string
    {
        return config('webpush.public_key');
    }

    /**
     * Push one stored notification to every browser this user has registered.
     *
     * Returns a short human-readable result, which we save on the notification
     * row so the UI can show what happened rather than failing silently.
     */
    public function send(UserNotification $notification): string
    {
        if (! $this->isConfigured()) {
            return 'skipped: VAPID keys not configured';
        }

        $subscriptions = PushSubscription::where('user_id', $notification->user_id)->get();

        if ($subscriptions->isEmpty()) {
            return 'skipped: no browser subscribed';
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject'    => config('webpush.subject'),
                    'publicKey'  => config('webpush.public_key'),
                    'privateKey' => config('webpush.private_key'),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Web push could not start: ' . $e->getMessage());
            return 'failed: ' . $e->getMessage();
        }

        // What the service worker in the browser will receive.
        $payload = json_encode([
            'title' => $notification->icon . '  ' . $notification->title,
            'body'  => $notification->body,
            'url'   => $notification->url ?? url('/notifications'),
        ]);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint'        => $subscription->endpoint,
                    'publicKey'       => $subscription->public_key,
                    'authToken'       => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                $payload
            );
        }

        $sent = 0;
        $failed = 0;

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
                continue;
            }

            $failed++;

            // 404 / 410 mean the browser threw the subscription away.
            // Deleting it stops us retrying a dead endpoint forever.
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }

        PushSubscription::where('user_id', $notification->user_id)
            ->update(['last_used_at' => now()]);

        return "sent to {$sent} browser(s)" . ($failed ? ", {$failed} failed" : '');
    }
}
