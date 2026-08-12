<?php

namespace App\Http\Controllers;

/**
 * FEATURE — Notification centre + Web Push  (web entry point)
 *
 * API-DRIVEN PAGE
 * ---------------
 * No data is passed to the view. The feed, the filter chips, the preference
 * switches and the push status all come from:
 *
 *     GET  /api/v1/notifications?filter=...
 *     POST /api/v1/notifications/preferences
 *     POST /api/v1/notifications/subscribe
 *     POST /api/v1/notifications/unsubscribe
 *     POST /api/v1/notifications/test
 *     POST /api/v1/notifications/{notification}/read
 *     POST /api/v1/notifications/read-all
 *
 * The navbar bell uses GET /api/v1/notifications/unread-summary, which is
 * why App\View\Composers\NotificationComposer is no longer registered —
 * the navbar fetches its own data instead of being handed it.
 *
 * Filtering still lives in the URL (?filter=unread) so a filtered view stays
 * bookmarkable. The page reads it on load and updates it with pushState when
 * a chip is clicked, rather than reloading.
 */
class NotificationController extends Controller
{
    public function index()
    {
        return view('notifications.index');
    }
}