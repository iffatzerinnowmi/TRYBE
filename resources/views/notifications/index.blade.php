@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="wrap pb-20">

    <x-page-header
        eyebrow="Notifications · Web Push"
        title="Never miss a study meant for you."
        subtitle="Turn on browser notifications and TRYBE pings you the instant something matters — an endorsement lands, your verification clears, or you level up a credential tier.">
        @if ($unread > 0)
            <form method="POST" action="{{ route('notifications.read') }}">
                @csrf
                <x-btn type="submit" variant="ghost" size="sm">Mark all read ({{ $unread }})</x-btn>
            </form>
        @endif
    </x-page-header>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[1.5fr_1fr]">

        {{-- ================= LEFT ================= --}}
        <div class="space-y-6">

            {{-- Enable push --}}
            <x-panel label="Enable push"
                     note="Give your browser permission once — that's all it takes."
                     class="reveal">

                <div class="flex items-center gap-4 rounded-xl border border-line bg-surface-soft p-5">
                    <div id="bell" class="flex h-12 w-12 items-center justify-center rounded-2xl
                                          bg-surface text-2xl transition">🔔</div>

                    <div class="flex-1">
                        <div id="push-title" class="text-[14.5px] font-semibold text-ink">
                            Checking your browser…
                        </div>
                        <div class="mt-1 flex items-center gap-2">
                            <span id="push-dot" class="h-2 w-2 rounded-full bg-steel"></span>
                            <span id="push-state" class="font-mono text-[11.5px] text-steel">—</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2.5">
                    <x-btn id="enable-btn">Enable notifications</x-btn>

                    <form method="POST" action="{{ route('notifications.test') }}">
                        @csrf
                        <x-btn type="submit" variant="ghost">Send a test</x-btn>
                    </form>
                </div>

                @unless ($pushReady)
                    <x-alert type="info" class="mt-4">
                        VAPID keys aren't set yet, so notifications are recorded and shown
                        below but not pushed to the browser. Run
                        <code class="font-mono">php artisan trybe:vapid</code> to switch
                        push on — see the Section 7 README.
                    </x-alert>
                @endunless

                <p id="push-fineprint" class="mt-3 text-[11.5px] leading-relaxed text-steel">
                    Your browser will ask for permission. Push works on localhost and on
                    HTTPS — on a plain http:// address other than localhost, browsers
                    block it.
                </p>
            </x-panel>

            {{-- Preferences --}}
            <x-panel label="What to notify me about"
                     note="Each of these maps to a TRYBE feature."
                     class="reveal reveal-d1">

                <form method="POST" action="{{ route('notifications.preferences') }}">
                    @csrf

                    @foreach ($types as $key => $type)
                        @php $on = (bool) $prefs->{$type['column']}; @endphp

                        <label class="flex cursor-pointer items-center gap-4 border-b border-line
                                      py-4 last:border-none">

                            <span class="flex h-10 w-10 shrink-0 items-center justify-center
                                         rounded-xl bg-surface-soft text-lg">{{ $type['icon'] }}</span>

                            <span class="min-w-0 flex-1">
                                <span class="block text-[13.5px] font-semibold text-ink">
                                    {{ $type['label'] }}
                                </span>
                                <span class="mt-0.5 block text-[12.5px] leading-relaxed text-dim">
                                    {{ $type['desc'] }}
                                </span>
                            </span>

                            {{-- A real checkbox, drawn as a toggle switch. --}}
                            <input type="checkbox" name="{{ $key }}" value="1"
                                   @checked($on) class="peer sr-only">

                            <span class="relative h-6 w-11 shrink-0 rounded-full bg-steel/40 transition
                                         peer-checked:bg-plum
                                         peer-checked:[&>span]:translate-x-5">
                                <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white
                                             transition"></span>
                            </span>
                        </label>
                    @endforeach

                    <div class="mt-5">
                        <x-btn type="submit">Save preferences</x-btn>
                    </div>
                </form>
            </x-panel>

            {{-- History --}}
            <x-panel label="Your notifications" class="reveal reveal-d2">
                <x-slot:action>{{ $items->count() }} recent</x-slot:action>

                @forelse ($items as $item)
                    <div class="flex gap-3.5 rounded-xl border p-4 mb-2.5 last:mb-0
                                {{ $item->isUnread() ? 'border-plum/30 bg-plum/5' : 'border-line' }}">

                        <span class="text-xl">{{ $item->icon }}</span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <b class="text-[13.5px] text-ink">{{ $item->title }}</b>
                                @if ($item->isUnread())
                                    <x-badge tone="plum">New</x-badge>
                                @endif
                            </div>

                            <p class="mt-1 text-[13px] leading-relaxed text-dim">{{ $item->body }}</p>

                            <p class="mt-1.5 font-mono text-[10.5px] text-steel">
                                {{ $item->created_at->diffForHumans() }} ·
                                {{ $item->pushed ? '📲 ' : '💾 ' }}{{ $item->push_result }}
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="py-3 text-[13.5px] text-dim">
                        Nothing yet. Click <b>Send a test</b> above, or get a researcher
                        to endorse you.
                    </p>
                @endforelse
            </x-panel>
        </div>

        {{-- ================= RIGHT ================= --}}
        <div class="space-y-6">

            <x-panel label="Registered browsers" class="reveal reveal-d1">
                @forelse ($subscriptions as $subscription)
                    <div class="border-b border-line py-3 last:border-none last:pb-1">
                        <div class="text-[13px] font-semibold text-ink">
                            {{ str_contains($subscription->endpoint, 'fcm.googleapis') ? 'Chrome / Edge'
                               : (str_contains($subscription->endpoint, 'mozilla') ? 'Firefox'
                               : (str_contains($subscription->endpoint, 'apple') ? 'Safari' : 'Browser')) }}
                        </div>
                        <div class="truncate font-mono text-[10.5px] text-steel">
                            {{ Str::limit($subscription->endpoint, 52) }}
                        </div>
                        <div class="font-mono text-[10.5px] text-steel">
                            Added {{ $subscription->created_at->diffForHumans() }}
                        </div>
                    </div>
                @empty
                    <p class="py-3 text-[13px] text-dim">
                        No browser has subscribed yet. Click <b>Enable notifications</b>.
                    </p>
                @endforelse
            </x-panel>

            <x-panel label="How this works" class="reveal reveal-d2">
                <ol class="space-y-3 text-[12.5px] leading-relaxed text-dim">
                    <li><b class="text-ink">1.</b> You click Enable. The browser asks
                        permission and, if you agree, gives us a private URL called an
                        <b class="text-ink">endpoint</b>.</li>
                    <li><b class="text-ink">2.</b> We store that endpoint in
                        <code class="font-mono text-[11px] text-plum">push_subscriptions</code>.</li>
                    <li><b class="text-ink">3.</b> When something happens, Laravel encrypts a
                        message and posts it to that endpoint — Google's, Mozilla's or
                        Apple's push service, depending on your browser.</li>
                    <li><b class="text-ink">4.</b> <code class="font-mono text-[11px] text-plum">/sw.js</code>,
                        a background script, receives it and shows the notification —
                        even with TRYBE closed.</li>
                </ol>

                <p class="mt-4 rounded-xl bg-surface-soft px-3.5 py-3 text-[11.5px] leading-relaxed text-dim">
                    This is the <b class="text-ink">Web Push standard (VAPID)</b> — free,
                    supported by every major browser, and not Firebase.
                </p>
            </x-panel>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var enableBtn = document.getElementById('enable-btn');
    var titleEl   = document.getElementById('push-title');
    var stateEl   = document.getElementById('push-state');
    var dotEl     = document.getElementById('push-dot');
    var bellEl    = document.getElementById('bell');

    var VAPID_PUBLIC_KEY = @json($vapidKey);
    var CSRF = document.querySelector('meta[name="csrf-token"]').content;

    /* The key arrives as base64url text; the browser wants raw bytes. */
    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var output = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) { output[i] = raw.charCodeAt(i); }
        return output;
    }

    function setState(title, state, colour) {
        titleEl.textContent = title;
        stateEl.textContent = state;
        dotEl.className = 'h-2 w-2 rounded-full ' + colour;
    }

    /* ---- 1. Is push even possible here? ---- */
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        setState('Push is not supported in this browser', 'Unsupported', 'bg-danger');
        enableBtn.disabled = true;
        return;
    }

    if (!VAPID_PUBLIC_KEY) {
        setState('Push is not configured on the server yet',
                 'Waiting for VAPID keys', 'bg-flame');
        enableBtn.disabled = true;
        return;
    }

    /* ---- 2. Register the background script ---- */
    var swReady = navigator.serviceWorker.register('/sw.js');

    function refresh() {
        if (Notification.permission === 'denied') {
            setState('Notifications are blocked for this site',
                     'Blocked — change it in your browser settings', 'bg-danger');
            enableBtn.disabled = true;
            return;
        }

        swReady.then(function (registration) {
            return registration.pushManager.getSubscription();
        }).then(function (subscription) {
            if (subscription) {
                setState('Push notifications are on', 'Enabled for this browser', 'bg-ok');
                bellEl.classList.add('bg-ok/10');
                enableBtn.textContent = 'Already enabled';
                enableBtn.disabled = true;
            } else {
                setState('Push notifications are off', 'Not enabled yet', 'bg-steel');
            }
        });
    }

    /* ---- 3. Ask permission, subscribe, tell Laravel ---- */
    enableBtn.addEventListener('click', function () {
        enableBtn.disabled = true;
        setState('Waiting for your permission…', 'Asking the browser', 'bg-flame');

        Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                setState('Permission was not granted', 'Denied', 'bg-danger');
                return;
            }

            return swReady.then(function (registration) {
                return registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                });
            }).then(function (subscription) {
                var data = subscription.toJSON();

                return fetch(@json(route('notifications.subscribe')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        endpoint: data.endpoint,
                        keys: data.keys,
                        contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0]
                    })
                });
            }).then(function () {
                setState('Push notifications are on', 'Enabled for this browser', 'bg-ok');
                bellEl.classList.add('bg-ok/10');
                enableBtn.textContent = 'Already enabled';
            });
        }).catch(function (error) {
            setState('Something went wrong', String(error.message || error), 'bg-danger');
            enableBtn.disabled = false;
        });
    });

    refresh();
})();
</script>
@endpush
