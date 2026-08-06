@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="wrap pb-16">

    <x-page-header
        eyebrow="Notifications"
        title="Never miss a study meant for you."
        subtitle="Turn on browser notifications and TRYBE pings you the instant something matters — an endorsement lands, your verification clears, or you level up a credential tier." />

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

            <form method="POST" action="{{ route('notifications.test') }}">
                @csrf
                <button type="submit"
                        class="whitespace-nowrap rounded-xl border border-white/30 bg-white/12
                               px-[18px] py-[11px] text-[13px] font-semibold text-white
                               transition hover:bg-white/20">
                    Send a test
                </button>
            </form>
        </div>
    </div>

    @unless ($pushReady)
        <x-alert type="info" class="mb-6">
            VAPID keys aren't set, so notifications are recorded and listed below but not
            pushed to the browser. Run <code class="font-mono">npx web-push generate-vapid-keys</code>
            and add the keys to <code class="font-mono">.env</code>.
        </x-alert>
    @endunless

    <div class="grid items-start gap-6 lg:grid-cols-[1.55fr_1fr]">

        {{-- ================= FEED ================= --}}
        <x-panel label="Your notifications" class="reveal reveal-d1">
            <x-slot:action>{{ $total }} total</x-slot:action>

            {{-- Filters are links, so the filtering happens in the controller,
                 not in JavaScript. Bookmarkable, and works without JS. --}}
            <div class="mb-[18px] flex flex-wrap gap-2">
                @foreach ($filters as $key => $meta)
                    @php $active = $filter === $key; @endphp

                    <a href="{{ route('notifications.index', $key === 'all' ? [] : ['filter' => $key]) }}"
                       class="flex items-center gap-1.5 rounded-full border px-3.5 py-2 text-[12.5px] transition
                              {{ $active
                                 ? 'border-plum bg-plum font-semibold text-white'
                                 : 'border-line-hi bg-surface text-ink hover:border-plum hover:text-plum' }}">
                        {{ $meta['label'] }}

                        @isset($meta['count'])
                            <span class="font-mono text-[10.5px] opacity-75">{{ $meta['count'] }}</span>
                        @endisset
                    </a>
                @endforeach
            </div>

            @forelse ($items as $item)
                <div class="relative mb-[11px] flex gap-3.5 rounded-[15px] border p-4 transition
                            hover:translate-x-0.5 last:mb-0
                            {{ $item->isUnread()
                               ? 'border-plum/30 bg-plum/[.045]'
                               : 'border-line hover:border-line-hi' }}">

                    @if ($item->isUnread())
                        <span class="absolute bottom-4 left-0 top-4 w-[3px] rounded-r-[3px] bg-plum"></span>
                    @endif

                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl
                                 bg-surface-soft text-[18px]">{{ $item->icon }}</span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <b class="text-[13.5px] text-ink">{{ $item->title }}</b>
                            @if ($item->isUnread())
                                <span class="rounded-full bg-plum/12 px-2.5 py-0.5 font-mono text-[10px] text-plum">
                                    NEW
                                </span>
                            @endif
                        </div>

                        <p class="mt-1 text-[13px] leading-relaxed text-dim">{{ $item->body }}</p>

                        <div class="mt-2 flex flex-wrap items-center gap-2.5">
                            <span class="font-mono text-[10.5px] text-steel">
                                {{ $item->created_at->diffForHumans() }}
                            </span>

                            <span class="rounded-full px-2.5 py-0.5 font-mono text-[10px]
                                         {{ $item->pushed ? 'bg-ok/12 text-ok' : 'bg-steel/20 text-steel' }}">
                                {{ $item->pushed ? '📲 ' : '💾 ' }}{{ $item->push_result }}
                            </span>
                        </div>
                    </div>

                    @if ($item->isUnread())
                        <form method="POST" action="{{ route('notifications.readOne', $item) }}" class="self-start">
                            @csrf
                            <button type="submit"
                                    class="whitespace-nowrap rounded-[9px] border border-line-hi px-2.5 py-1.5
                                           text-[11px] text-dim transition hover:border-plum hover:text-plum">
                                Mark read
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="px-5 py-12 text-center">
                    <span class="mb-3 block text-[34px] opacity-50">🔕</span>
                    <p class="text-[13.5px] text-dim">
                        @if ($filter === 'unread')
                            Nothing here — you're all caught up.
                        @elseif ($filter !== 'all')
                            Nothing of this type yet.
                        @else
                            Nothing yet. Click <b class="text-ink">Send a test</b> above, or get a
                            researcher to endorse you.
                        @endif
                    </p>
                </div>
            @endforelse
        </x-panel>

        {{-- ================= PREFERENCES ================= --}}
        <x-panel label="What to notify me about"
                 note="Switch one off and nothing is recorded for it — not stored, not pushed."
                 class="reveal reveal-d2">

            <form method="POST" action="{{ route('notifications.preferences') }}">
                @csrf

                @foreach ($types as $key => $type)
                    <label class="flex cursor-pointer items-center gap-3.5 border-b border-line
                                  py-[15px] last:border-none">

                        <span class="grid h-[38px] w-[38px] shrink-0 place-items-center rounded-xl
                                     bg-surface-soft text-base">{{ $type['icon'] }}</span>

                        <span class="min-w-0 flex-1">
                            <span class="block text-[13.5px] font-semibold text-ink">{{ $type['label'] }}</span>
                            <span class="mt-0.5 block text-[12px] leading-relaxed text-dim">{{ $type['desc'] }}</span>
                        </span>

                        <input type="checkbox" name="{{ $key }}" value="1" data-pref
                               @checked((bool) $prefs->{$type['column']}) class="peer sr-only">

                        <span class="relative h-6 w-11 shrink-0 rounded-full bg-steel/45 transition
                                     peer-checked:bg-plum peer-checked:[&>span]:translate-x-5">
                            <span class="absolute left-[3px] top-[3px] h-[18px] w-[18px] rounded-full
                                         bg-white transition"></span>
                        </span>
                    </label>
                @endforeach

                <p id="pref-count" class="mt-4 text-center font-mono text-[11px] text-steel"></p>

                <div class="mt-[18px]">
                    <x-btn type="submit">Save preferences</x-btn>
                </div>
            </form>
        </x-panel>
    </div>
</div>

{{-- Flash message as a toast in the corner, matching the design. --}}
@if (session('status'))
    <div id="toast"
         class="fixed right-6 top-[84px] z-[200] flex w-[320px] gap-3 rounded-[14px] border
                border-line-hi bg-surface px-4 py-3.5
                shadow-[0_18px_40px_-18px_rgba(79,58,101,.5)]">
        <span class="text-[18px]">🔔</span>
        <div>
            <div class="text-[13px] font-semibold text-ink">TRYBE</div>
            <div class="mt-0.5 text-[12px] leading-relaxed text-dim">{{ session('status') }}</div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
/* ---- toast auto-dismiss ---- */
(function () {
    var toast = document.getElementById('toast');
    if (!toast) return;
    setTimeout(function () {
        toast.style.transition = 'opacity .3s, transform .3s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(24px)';
        setTimeout(function () { toast.remove(); }, 320);
    }, 4200);
})();

/* ---- live "N of 5 enabled" counter ---- */
(function () {
    var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-pref]'));
    var label = document.getElementById('pref-count');
    if (!boxes.length || !label) return;

    function update() {
        var on = boxes.filter(function (b) { return b.checked; }).length;
        label.textContent = on + ' of ' + boxes.length + ' enabled';
    }

    boxes.forEach(function (b) { b.addEventListener('change', update); });
    update();
})();

/* ---- push subscription ---- */
(function () {
    var enableBtn = document.getElementById('enable-btn');
    var titleEl   = document.getElementById('push-title');
    var stateEl   = document.getElementById('push-state');
    var dotEl     = document.getElementById('push-dot');
    var iconEl    = document.getElementById('push-icon');

    var VAPID_PUBLIC_KEY = @json($vapidKey);
    var CSRF = document.querySelector('meta[name="csrf-token"]').content;

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var output = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) { output[i] = raw.charCodeAt(i); }
        return output;
    }

    function setState(title, state, dotClass, lit) {
        titleEl.textContent = title;
        stateEl.textContent = state;
        dotEl.className = 'h-2 w-2 rounded-full ' + dotClass;
        iconEl.style.background = lit ? 'rgba(123,224,174,.25)' : 'rgba(255,255,255,.15)';
    }

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        setState('Push is not supported in this browser', 'Unsupported', 'bg-danger', false);
        enableBtn.disabled = true;
        return;
    }

    if (!VAPID_PUBLIC_KEY) {
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
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                });
            }).then(function (sub) {
                var data = sub.toJSON();

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
                setState('Push notifications are on', 'Enabled on this browser', 'bg-ok', true);
                enableBtn.textContent = 'Already enabled';
            });
        }).catch(function (error) {
            setState('Something went wrong', String(error.message || error), 'bg-danger', false);
            enableBtn.disabled = false;
        });
    });

    refresh();
})();
</script>
@endpush
