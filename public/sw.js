/* =============================================================================
   TRYBE service worker
   -----------------------------------------------------------------------------
   A service worker is a script the browser keeps running in the background,
   separate from any tab. That is what allows a notification to arrive even
   when TRYBE is closed.

   This file must sit in the PUBLIC folder, at /sw.js, because a service worker
   can only control pages at or below its own path.
   ============================================================================= */

// Take control straight away instead of waiting for every tab to close.
self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

/* -----------------------------------------------------------------------------
   A push arrived from the browser's push service.
   The payload is the JSON that WebPushService encrypted and sent.
----------------------------------------------------------------------------- */
self.addEventListener('push', function (event) {
    var data = { title: 'TRYBE', body: 'You have a new notification.', url: '/notifications' };

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/favicon.ico',
            badge: '/favicon.ico',
            data: { url: data.url || '/notifications' },
            tag: 'trybe-notification'
        })
    );
});

/* -----------------------------------------------------------------------------
   The user clicked the notification: focus an open TRYBE tab if there is one,
   otherwise open a new one at the notification's URL.
----------------------------------------------------------------------------- */
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var target = (event.notification.data && event.notification.data.url) || '/notifications';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
            for (var i = 0; i < list.length; i++) {
                if ('focus' in list[i]) {
                    list[i].navigate(target);
                    return list[i].focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(target);
            }
        })
    );
});
