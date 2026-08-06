<?php

namespace App\View\Composers;

use App\Models\UserNotification;
use Illuminate\View\View;

/**
 * The navbar bell appears on every page, so it needs the unread count on
 * every page. Rather than making all twelve controllers pass it, a View
 * Composer attaches the data to the navbar component wherever it renders.
 *
 * Registered in app/Providers/AppServiceProvider.php.
 */
class NotificationComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        if (! $user) {
            $view->with(['navUnreadCount' => 0, 'navUnread' => collect()]);
            return;
        }

        // The four newest unread, for the dropdown.
        $unread = UserNotification::where('user_id', $user->id)
            ->unread()
            ->latest('id')
            ->take(4)
            ->get();

        $view->with([
            'navUnreadCount' => UserNotification::where('user_id', $user->id)->unread()->count(),
            'navUnread'      => $unread,
        ]);
    }
}
