@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
{{--
    API-DRIVEN PAGE — see participant/reliability.blade.php for the pattern.
    NotificationController passes nothing; everything below is fetched.

    ROLE-AWARE WORDING
    ------------------
    The heading is the ONLY place the role is read in Blade, and only because
    a heading cannot wait for a fetch without the page flashing the wrong
    words first. Everything else — which switches exist, which chips exist —
    still comes from the API, decided by NotificationService::typesFor().

    data-role is passed to the script for the empty-state sentence, so the
    page never has to guess.
--}}
@php
    $pageRole = auth()->user()?->role?->value ?? 'participant';

    $headline = match ($pageRole) {
        'researcher', 'organization' => 'Never miss a participant who applied.',
        'admin'                      => 'Nothing on the platform slips past you.',
        default                      => 'Never miss a study meant for you.',
    };

    $subline = match ($pageRole) {
        'researcher', 'organization' =>
            'Turn on browser notifications and TRYBE pings you the instant something matters — an application lands, a study fills its last seat, or your ethics document clears review.',
        'admin' =>
            'Turn on browser notifications and TRYBE pings you when a verification request arrives or a study is flagged for ethics review.',
        default =>
            'Turn on browser notifications and TRYBE pings you the instant something matters — an endorsement lands, your verification clears, or you level up a credential tier.',
    };
@endphp

<div class="wrap pb-16" id="notifications-page" data-role="{{ $pageRole }}">

    <x-page-header
        eyebrow="Notifications"
        :title="$headline"
        :subtitle="$subline" />

    {{-- ================= PUSH STRIP ================= --}}
    <div class="reveal relative my-6 flex flex-wrap items-center gap-5 overflow-hidden rounded-panel
                bg-gradient-to-br from-ink to-plum px-6 py-5 text-white
                shadow-[0_18px_44px_-22px_rgba(79,58,101,.7)]">

        <div class="pointer-events-none absolute -right-16 -top-24 h-60 w-60 rounded-full"
             style="background:radial-gradient(circle, rgba(223,240,234,.2), transparent 70%);"></div>

        <div id="push-icon"
             class="relative z-10 grid h-[52px] w-[52px] shrink-0 place-items-center rounded-[15px]
                    bg-white/15 text-[23px] transition">🔔</div>

        <div class="relative z-10 min-w-[200px] flex-1">
            <b id="push-title" class="block text-[15px]">Checking your browser…</b>
            <div class="mt-1.5 flex items-center gap-2">
                <span id="push-dot" class="h-2 w-2 rounded-full bg-steel"></span>
                <span id="push-state" class="font-mono text-[11.5px] text-white/65">—</span>
            </div>
        </div>

        <div class="relative z-10 flex flex-wrap gap-2.5">
            <button type="button" id="enable-btn"
                    class="whitespace-nowrap rounded-xl bg-mint px-[18px] py-[11px] text-[13px]
                           font-semibold text-ink transition hover:-translate-y-px
                           disabled:translate-y-0 disabled:opacity-45">
                Enable notifications
            </button>

            {{-- Was a form post. Now calls POST /api/v1/notifications/test. --}}
            <button type="button" id="test-btn"
                    class="whitespace-nowrap rounded-xl border border-white/30 bg-white/12
                           px-[18px] py-[11px] text-[13px] font-semibold text-white
                           transition hover:bg-white/20 disabled:opacity-45">
                Send a test
            </button>
        </div>
    </div>

    {{-- Shown only when the API reports VAPID keys are missing. --}}
    <div id="vapid-warning" class="mb-6 hidden rounded-xl bg-flame/10 px-4 py-3 text-[13px] text-flame">
        VAPID keys aren't set, so notifications are recorded and listed below but not
        pushed to the browser. Run <code class="font-mono">npx web-push generate-vapid-keys</code>
        and add the keys to <code class="font-mono">.env</code>.
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-[1.55fr_1fr]">

        {{-- ================= FEED ================= --}}
        <x-panel label="Your notifications" class="reveal reveal-d1">
            <x-slot:action><span id="total-label">—</span></x-slot:action>

            {{-- Chips are buttons now, but the filter still lives in the URL
                 (?filter=unread) via pushState, so a filtered view can still
                 be bookmarked and shared. --}}
            <div class="mb-[18px] flex flex-wrap gap-2" id="filter-chips"></div>

            <div id="feed">
                <p class="px-5 py-12 text-center text-[13.5px] text-dim">Loading your notifications…</p>
            </div>
        </x-panel>

        {{-- ================= PREFERENCES ================= --}}
        {{-- The note has to cover both kinds of row now: switches, and the
             locked ALWAYS ON rows a researcher sees. --}}
        <x-panel label="What to notify me about"
                 note="Switch one off and nothing is recorded for it — not stored, not pushed. A few are marked ALWAYS ON because TRYBE cannot run a study without them."
                 class="reveal reveal-d2">

            <div id="pref-list">
                <p class="py-6 text-center text-[13px] text-dim">Loading…</p>
            </div>

            <p id="pref-count" class="mt-4 text-center font-mono text-[11px] text-steel"></p>

            <div class="mt-[18px]">
                <x-btn type="button" id="save-prefs">Save preferences</x-btn>
            </div>
        </x-panel>
    </div>
</div>

{{-- Toast container. Filled by JS after any successful action. --}}
<div id="toast-slot"></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    if (!document.getElementById('notifications-page')) { return; }

    var API = '/api/v1/notifications';

    /* State the page keeps between renders. */
    var currentFilter = new URLSearchParams(window.location.search).get('filter') || 'all';
    var vapidKey = null;

    /* Set by Blade from the logged-in user, and confirmed by the API on every
       load. Used only for wording — never to decide what to show. */
    var pageRole = document.getElementById('notifications-page').dataset.role || 'participant';

    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* ---------------------------------------------------------------
       Toast — replaces the old session('status') flash message.
    --------------------------------------------------------------- */
    function toast(message) {
        var box = document.createElement('div');
        box.className = 'fixed right-6 top-[84px] z-[200] flex w-[320px] gap-3 rounded-[14px] border '
                      + 'border-line-hi bg-surface px-4 py-3.5 '
                      + 'shadow-[0_18px_40px_-18px_rgba(79,58,101,.5)]';
        box.innerHTML = '<span class="text-[18px]">🔔</span>'
                      + '<div><div class="text-[13px] font-semibold text-ink">TRYBE</div>'
                      + '<div class="mt-0.5 text-[12px] leading-relaxed text-dim">' + esc(message) + '</div></div>';

        document.getElementById('toast-slot').appendChild(box);

        setTimeout(function () {
            box.style.transition = 'opacity .3s, transform .3s';
            box.style.opacity = '0';
            box.style.transform = 'translateX(24px)';
            setTimeout(function () { box.remove(); }, 320);
        }, 4200);
    }

    /* ---------------------------------------------------------------
       Rendering
    --------------------------------------------------------------- */

    function renderChips(data) {
        var html = '';

        data.filters.forEach(function (chip) {
            var active = chip.key === data.filter;

            var cls = active
                ? 'border-plum bg-plum font-semibold text-white'
                : 'border-line-hi bg-surface text-ink hover:border-plum hover:text-plum';

            var count = (chip.count === null || chip.count === undefined)
                ? ''
                : '<span class="font-mono text-[10.5px] opacity-75">' + chip.count + '</span>';

            html += '<button type="button" data-filter="' + esc(chip.key) + '" '
                  +   'class="flex items-center gap-1.5 rounded-full border px-3.5 py-2 '
                  +   'text-[12.5px] transition ' + cls + '">'
                  +   esc(chip.label) + count
                  + '</button>';
        });

        document.getElementById('filter-chips').innerHTML = html;
    }

    function emptyMessage(filter) {
        if (filter === 'unread') { return "Nothing here — you're all caught up."; }
        if (filter !== 'all')    { return 'Nothing of this type yet.'; }

        if (pageRole === 'researcher' || pageRole === 'organization') {
            return 'Nothing yet. Click <b class="text-ink">Send a test</b> above, '
                 + 'or post a study and wait for your first application.';
        }

        if (pageRole === 'admin') {
            return 'Nothing yet. Click <b class="text-ink">Send a test</b> above, '
                 + 'or wait for a verification request to come in.';
        }

        return 'Nothing yet. Click <b class="text-ink">Send a test</b> above, or get a researcher to endorse you.';
    }

    function renderFeed(data) {
        document.getElementById('total-label').textContent = data.total + ' total';

        if (data.items.length === 0) {
            document.getElementById('feed').innerHTML =
                '<div class="px-5 py-12 text-center">'
              +   '<span class="mb-3 block text-[34px] opacity-50">🔕</span>'
              +   '<p class="text-[13.5px] text-dim">' + emptyMessage(data.filter) + '</p>'
              + '</div>';
            return;
        }

        var html = '';

        data.items.forEach(function (item) {
            var box = item.is_unread
                ? 'border-plum/30 bg-plum/[.045]'
                : 'border-line hover:border-line-hi';

            html +=
              '<div class="relative mb-[11px] flex gap-3.5 rounded-[15px] border p-4 transition '
            +      'hover:translate-x-0.5 last:mb-0 ' + box + '" data-row="' + item.id + '">'

            + (item.is_unread
                ? '<span class="absolute bottom-4 left-0 top-4 w-[3px] rounded-r-[3px] bg-plum"></span>'
                : '')

            +   '<span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl '
            +        'bg-surface-soft text-[18px]">' + esc(item.icon) + '</span>'

            +   '<div class="min-w-0 flex-1">'
            +     '<div class="flex flex-wrap items-center gap-2">'
            +       '<b class="text-[13.5px] text-ink">' + esc(item.title) + '</b>'
            +       (item.is_unread
                      ? '<span class="rounded-full bg-plum/12 px-2.5 py-0.5 font-mono text-[10px] text-plum">NEW</span>'
                      : '')
            +     '</div>'

            +     '<p class="mt-1 text-[13px] leading-relaxed text-dim">' + esc(item.body) + '</p>'

            +     (item.url
                    ? '<div class="mt-2"><a href="' + esc(item.url) + '" '
                    +   'class="inline-flex items-center rounded-full border border-plum/25 bg-plum/8 '
                    +   'px-3 py-1.5 font-mono text-[10.5px] text-plum transition hover:bg-plum/12">'
                    +   'Open</a></div>'
                    : '')

            +     '<div class="mt-2 flex flex-wrap items-center gap-2.5">'
            +       '<span class="font-mono text-[10.5px] text-steel">' + esc(item.created_ago) + '</span>'
            +       '<span class="rounded-full px-2.5 py-0.5 font-mono text-[10px] '
            +          (item.pushed ? 'bg-ok/12 text-ok' : 'bg-steel/20 text-steel') + '">'
            +         (item.pushed ? '📲 ' : '💾 ') + esc(item.push_result)
            +       '</span>'
            +     '</div>'
            +   '</div>'

            + (item.is_unread
                ? '<button type="button" data-read="' + item.id + '" '
                + 'class="self-start whitespace-nowrap rounded-[9px] border border-line-hi px-2.5 py-1.5 '
                + 'text-[11px] text-dim transition hover:border-plum hover:text-plum">Mark read</button>'
                : '')

            + '</div>';
        });

        document.getElementById('feed').innerHTML = html;
    }

    /*
    | Two kinds of row now:
    |
    |   pref.locked === false  a real switch, saved by "Save preferences"
    |   pref.locked === true   an ALWAYS ON row with no switch at all
    |
    | The locked rows are still LISTED. Hiding them would leave a researcher
    | unable to see that TRYBE is going to email them about applications —
    | "you cannot turn this off" is information, "this does not exist" is not.
    |
    | Locked rows carry no data-pref attribute, which is what keeps them out
    | of the save payload further down. That is deliberate: the API rejects
    | them with a 422, so not sending them is the difference between a clean
    | save and an error toast.
    |
    | Every Tailwind class below is a complete literal string. Building one by
    | concatenation ('bg-' + colour) produces a class Tailwind never compiled,
    | so the row would render unstyled.
    */
    function renderPrefs(data) {
        var html = '';

        data.preferences.forEach(function (pref) {

            var control = pref.locked
                ? '<span data-pref-locked="' + esc(pref.key) + '" '
                +      'class="shrink-0 rounded-full bg-ok/12 px-2.5 py-1 font-mono '
                +      'text-[9.5px] tracking-wide text-ok">ALWAYS ON</span>'

                : '<input type="checkbox" data-pref="' + esc(pref.key) + '" '
                +    (pref.enabled ? 'checked ' : '') + 'class="peer sr-only">'
                + '<span class="relative h-6 w-11 shrink-0 rounded-full bg-steel/45 transition '
                +      'peer-checked:bg-plum peer-checked:[&>span]:translate-x-5">'
                +   '<span class="absolute left-[3px] top-[3px] h-[18px] w-[18px] rounded-full bg-white transition"></span>'
                + '</span>';

            var openTag = pref.locked
                ? '<div class="flex items-center gap-3.5 border-b border-line py-[15px] last:border-none">'
                : '<label class="flex cursor-pointer items-center gap-3.5 border-b border-line py-[15px] last:border-none">';

            var closeTag = pref.locked ? '</div>' : '</label>';

            html +=
              openTag
            +   '<span class="grid h-[38px] w-[38px] shrink-0 place-items-center rounded-xl '
            +        'bg-surface-soft text-base">' + esc(pref.icon) + '</span>'
            +   '<span class="min-w-0 flex-1">'
            +     '<span class="block text-[13.5px] font-semibold text-ink">' + esc(pref.label) + '</span>'
            +     '<span class="mt-0.5 block text-[12px] leading-relaxed text-dim">' + esc(pref.desc) + '</span>'
            +   '</span>'
            +   control
            + closeTag;
        });

        document.getElementById('pref-list').innerHTML = html;

        /* If this role has nothing it may change — no switches at all — the
           save button would be a button that can only ever fail. Hide it. */
        var saveBtn = document.getElementById('save-prefs');
        saveBtn.style.display = document.querySelectorAll('[data-pref]').length ? '' : 'none';

        countPrefs();
    }

    function countPrefs() {
        var boxes  = document.querySelectorAll('[data-pref]');
        var locked = document.querySelectorAll('[data-pref-locked]').length;

        /* Locked rows count as enabled, because that is exactly what they
           are — on, permanently. */
        var on = locked;
        boxes.forEach(function (b) { if (b.checked) { on++; } });

        document.getElementById('pref-count').textContent =
            on + ' of ' + (boxes.length + locked) + ' enabled';
    }

    /* ---------------------------------------------------------------
       Loading
    --------------------------------------------------------------- */

    function load(filter, updateUrl) {
        currentFilter = filter;

        if (updateUrl) {
            var url = filter === 'all' ? '/notifications' : '/notifications?filter=' + filter;
            window.history.pushState({}, '', url);
        }

        return api.get(API + '?filter=' + encodeURIComponent(filter))
            .then(function (response) {
                var data = response.data;

                /* The API is the authority. Blade only supplied a first
                   guess so the heading did not flash the wrong words. */
                pageRole = data.role || pageRole;

                renderChips(data);
                renderFeed(data);
                renderPrefs(data);

                vapidKey = data.push.vapid_key;

                document.getElementById('vapid-warning').className = data.push.configured
                    ? 'mb-6 hidden rounded-xl bg-flame/10 px-4 py-3 text-[13px] text-flame'
                    : 'mb-6 rounded-xl bg-flame/10 px-4 py-3 text-[13px] text-flame';

                return data;
            })
            .catch(function (error) {
                document.getElementById('feed').innerHTML =
                    '<p class="px-5 py-12 text-center text-[13.5px] text-danger">'
                  + esc(error.message) + '</p>';
                throw error;
            });
    }

    /* ---------------------------------------------------------------
       Events — one delegated listener, because the rows are replaced
       on every render and directly-bound handlers would be lost.
    --------------------------------------------------------------- */

    document.getElementById('filter-chips').addEventListener('click', function (event) {
        var chip = event.target.closest('[data-filter]');
        if (chip) { load(chip.dataset.filter, true); }
    });

    document.getElementById('feed').addEventListener('click', function (event) {
        var button = event.target.closest('[data-read]');
        if (!button) { return; }

        button.disabled = true;

        api.post(API + '/' + button.dataset.read + '/read')
            .then(function () {
                load(currentFilter, false);
                if (window.trybeRefreshBell) { window.trybeRefreshBell(); }
            })
            .catch(function (error) {
                toast(error.message);
                button.disabled = false;
            });
    });

    document.getElementById('pref-list').addEventListener('change', countPrefs);

    document.getElementById('save-prefs').addEventListener('click', function () {
        var button = this;
        var payload = {};

        document.querySelectorAll('[data-pref]').forEach(function (box) {
            payload[box.dataset.pref] = box.checked;
        });

        button.disabled = true;

        api.post(API + '/preferences', payload)
            .then(function (response) { toast(response.message); })
            .catch(function (error) { toast(error.message); })
            .then(function () { button.disabled = false; });
    });

    document.getElementById('test-btn').addEventListener('click', function () {
        var button = this;
        button.disabled = true;

        api.post(API + '/test')
            .then(function (response) {
                toast(response.message);
                return load(currentFilter, false);
            })
            .then(function () {
                if (window.trybeRefreshBell) { window.trybeRefreshBell(); }
            })
            .catch(function (error) { toast(error.message); })
            .then(function () { button.disabled = false; });
    });

    /* Back/forward buttons still work, because the filter is in the URL. */
    window.addEventListener('popstate', function () {
        load(new URLSearchParams(window.location.search).get('filter') || 'all', false);
    });

    /* ---------------------------------------------------------------
       Push subscription.

       Unchanged in substance from the original — the browser's push API
       is the same either way. The only difference is that the endpoint
       it posts to is now /api/v1/..., and the VAPID key arrives from the
       API rather than being printed into the page by Blade.
    --------------------------------------------------------------- */

    var enableBtn = document.getElementById('enable-btn');
    var titleEl   = document.getElementById('push-title');
    var stateEl   = document.getElementById('push-state');
    var dotEl     = document.getElementById('push-dot');
    var iconEl    = document.getElementById('push-icon');

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw     = window.atob(base64);
        var output  = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) { output[i] = raw.charCodeAt(i); }
        return output;
    }

    function setState(title, state, dotClass, lit) {
        titleEl.textContent = title;
        stateEl.textContent = state;
        dotEl.className = 'h-2 w-2 rounded-full ' + dotClass;
        iconEl.style.background = lit ? 'rgba(123,224,174,.25)' : 'rgba(255,255,255,.15)';
    }

    function setupPush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            setState('Push is not supported in this browser', 'Unsupported', 'bg-danger', false);
            enableBtn.disabled = true;
            return;
        }

        if (!vapidKey) {
            setState('Push is not configured on the server yet', 'Waiting for VAPID keys', 'bg-flame', false);
            enableBtn.disabled = true;
            return;
        }

        var swReady = navigator.serviceWorker.register('/sw.js');

        function refresh() {
            if (Notification.permission === 'denied') {
                setState('Notifications are blocked for this site',
                         'Blocked — change it in your browser settings', 'bg-danger', false);
                enableBtn.disabled = true;
                return;
            }

            swReady.then(function (reg) { return reg.pushManager.getSubscription(); })
                   .then(function (sub) {
                if (sub) {
                    setState('Push notifications are on', 'Enabled on this browser', 'bg-ok', true);
                    enableBtn.textContent = 'Already enabled';
                    enableBtn.disabled = true;
                } else {
                    setState('Push notifications are off', 'Not enabled on this browser', 'bg-flame', false);
                }
            });
        }

        enableBtn.addEventListener('click', function () {
            enableBtn.disabled = true;
            setState('Waiting for your permission…', 'Asking the browser', 'bg-flame', false);

            Notification.requestPermission().then(function (permission) {
                if (permission !== 'granted') {
                    setState('Permission was not granted', 'Denied', 'bg-danger', false);
                    return;
                }

                return swReady.then(function (reg) {
                    return reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidKey)
                    });
                }).then(function (sub) {
                    var data = sub.toJSON();

                    // Goes through the shared helper, so CSRF and headers
                    // are handled the same way as every other call.
                    return api.post(API + '/subscribe', {
                        endpoint: data.endpoint,
                        keys: data.keys,
                        contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0]
                    });
                }).then(function () {
                    setState('Push notifications are on', 'Enabled on this browser', 'bg-ok', true);
                    enableBtn.textContent = 'Already enabled';
                    toast('Push enabled on this browser.');
                });
            }).catch(function (error) {
                setState('Something went wrong', String(error.message || error), 'bg-danger', false);
                enableBtn.disabled = false;
            });
        });

        refresh();
    }

    /* Push setup waits for the first load, because the VAPID key is part
       of that response. */
    load(currentFilter, false).then(setupPush).catch(function () {
        setState('Could not reach the server', 'Unknown', 'bg-danger', false);
        enableBtn.disabled = true;
    });
});
</script>
@endpush