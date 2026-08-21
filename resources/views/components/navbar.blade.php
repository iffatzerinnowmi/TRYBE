{{--
    TRYBE navbar
    Used automatically by layouts/app.blade.php.

    Links use url('/...') instead of route('...') on purpose: the named routes
    do not exist until Sections 4 and 5, and url() never throws an error for a
    path that is not registered yet.

    THE BELL IS API-DRIVEN
    ----------------------
    This component used to receive $navUnreadCount and $navUnread from
    App\View\Composers\NotificationComposer, which ran two queries on every
    page in the app. That composer is no longer registered — the bell now
    fetches GET /api/v1/notifications/unread-summary for itself.

    That matters for more than tidiness: the navbar appears on every page, so
    while it was fed by the server there was no page in TRYBE that was fully
    API-driven.
--}}

@php
    /**
     * Nav links change depending on who is logged in.
     * auth()->user() is null for a visitor, so ?->role is safe.
     */
    $role = auth()->user()?->role?->value;

    $links = match ($role) {
        'participant' => [
            'Home'        => '/dashboard',
            'Feed'        => '/participant/feed',
            'Profile'     => '/participant/profile',
            'Reliability' => '/participant/reliability',
            'Credentials' => '/participant/credentials',
            // Member 4 — active studies, competitions, invitations, referrals.
            'My studies'  => '/participant/studies',
            'Competitions' => '/competitions',
            'Saved'       => '/participant/competitions',
            'Invites'     => '/participant/invitations',
            'Refer'       => '/participant/referrals',
            'Alerts'      => '/notifications',
            //member3- karma credits
            'Karma' => '/participant/karma',
        ],
        'researcher' => [
            'Dashboard'    => '/dashboard',
            'Profile'      => '/researcher/profile',
            'Verification' => '/verification',
            'Endorsements' => '/researcher/endorsements',
            // Member 4 — competition board (post listings) and referrals.
            'Competitions' => '/competitions',
            'Refer'        => '/researcher/referrals',
            'Alerts'       => '/notifications',
        ],
        'admin' => [
            'Overview'     => '/dashboard',
            'Competitions' => '/competitions',
            'Alerts'       => '/notifications',
        ],
        'organization' => [
            'Dashboard'    => '/dashboard',
            'Verification' => '/verification',
            'Competitions' => '/competitions',
            'Alerts'       => '/notifications',
        ],
        default => [
            'How it works' => '/#how',
            "Who it's for" => '/#roles',
            'Credentials'  => '/#tiers',
        ],
    };

@endphp

<nav class="sticky top-0 z-50 border-b border-line backdrop-blur-md
            bg-gradient-to-b from-mint/95 to-mint/70">
    <div class="mx-auto max-w-[1240px] px-5 sm:px-7">
        <div class="flex h-[68px] items-center justify-between">

            {{-- Brand --}}
            <a href="/" class="flex items-center gap-3 font-display text-[22px] font-semibold text-ink">
                <span class="h-[11px] w-[11px] rounded-full bg-plum ring-4 ring-plum/15"></span>
                TRYBE
            </a>

            {{-- Desktop links --}}
            <div class="hidden items-center gap-1 md:flex">
                @foreach ($links as $label => $href)
                    @php $active = request()->is(ltrim($href, '/') ?: '/'); @endphp
                    <a href="{{ $href }}"
                       class="rounded-lg px-3.5 py-2.5 text-[13.5px] transition-colors
                              {{ $active
                                 ? 'bg-plum font-semibold text-white'
                                 : 'text-dim hover:bg-steel/15 hover:text-ink' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            {{-- Right side --}}
            <div class="flex items-center gap-2.5">
                @guest
                    <a href="/login"
                       class="hidden rounded-lg px-3.5 py-2.5 text-[13.5px] text-dim
                              transition-colors hover:bg-steel/15 hover:text-ink sm:block">
                        Log in
                    </a>
                    <x-btn href="/signup" size="sm">Get started</x-btn>
                @endguest

                @auth
                    <span class="hidden font-mono text-[11.5px] tracking-wide text-steel sm:block">
                        {{ auth()->user()->role->label() }}
                    </span>

                    {{-- ================= NOTIFICATION BELL =================
                         Empty on first paint. The script at the bottom fills
                         the badge and the dropdown from the API.
                    ================================================================ --}}
                    <div class="relative" id="bell-wrap">
                        <button type="button" id="bell-btn" aria-label="Notifications"
                                class="relative grid h-10 w-10 place-items-center rounded-xl border
                                       border-line-hi bg-surface text-[17px] transition
                                       hover:-translate-y-px hover:border-plum">
                            🔔
                            <span id="bell-count"
                                  class="absolute -right-1.5 -top-1.5 hidden h-5 min-w-[20px] place-items-center
                                         rounded-full border-2 border-mint bg-danger px-1
                                         font-mono text-[11px] text-white"></span>
                        </button>

                        <div id="bell-dropdown"
                             class="invisible absolute right-0 top-[52px] z-[70] w-[380px] max-w-[calc(100vw-40px)]
                                    -translate-y-2 overflow-hidden rounded-[18px] border border-line
                                    bg-surface opacity-0 shadow-[0_24px_54px_-22px_rgba(79,58,101,.45)]
                                    transition-all duration-150">

                            <div class="flex items-center justify-between border-b border-line px-[18px] py-[15px]">
                                <b class="font-display text-base text-ink">Notifications</b>

                                <button type="button" id="bell-read-all"
                                        class="hidden text-xs font-semibold text-plum hover:underline">
                                    Mark all read
                                </button>
                            </div>

                            <div class="max-h-[340px] overflow-y-auto" id="bell-list">
                                <p class="px-[18px] py-[34px] text-center text-[13px] text-dim">Loading…</p>
                            </div>

                            <div class="border-t border-line bg-surface-soft px-[18px] py-3 text-center">
                                <a href="/notifications"
                                   class="text-[12.5px] font-semibold text-plum hover:underline">
                                    View all notifications →
                                </a>
                            </div>
                        </div>
                    </div>

                    <x-avatar :name="auth()->user()->name" />

                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-line-hi px-3 py-2 text-[12.5px]
                                       text-dim transition-colors hover:border-ink hover:text-ink">
                            Log out
                        </button>
                    </form>
                @endauth

                {{-- Mobile hamburger --}}
                <button type="button" onclick="trybeToggleMenu()"
                        class="rounded-lg border border-line-hi p-2 text-ink md:hidden"
                        aria-label="Open menu">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Mobile dropdown --}}
        <div id="mobile-menu" class="hidden border-t border-line py-3 md:hidden">
            @foreach ($links as $label => $href)
                <a href="{{ $href }}"
                   class="block rounded-lg px-3 py-2.5 text-sm text-dim hover:bg-steel/15 hover:text-ink">
                    {{ $label }}
                </a>
            @endforeach

            @guest
                <a href="/login"
                   class="block rounded-lg px-3 py-2.5 text-sm text-dim hover:bg-steel/15 hover:text-ink">
                    Log in
                </a>
            @endguest
        </div>
    </div>
</nav>

@auth
<script>
/*
| The bell: opens and closes, and fetches its own unread data.
|
| DOMContentLoaded is required — resources/js/app.js loads as a module, which
| the browser defers until the HTML is parsed, so `api` does not exist before
| this event fires.
*/
document.addEventListener('DOMContentLoaded', function () {

    var btn     = document.getElementById('bell-btn');
    var menu    = document.getElementById('bell-dropdown');
    var wrap    = document.getElementById('bell-wrap');
    var badge   = document.getElementById('bell-count');
    var list    = document.getElementById('bell-list');
    var readAll = document.getElementById('bell-read-all');

    if (!btn || !menu) { return; }

    /* ---- open / close ---- */
    var open = false;

    function setOpen(next) {
        open = next;
        menu.classList.toggle('invisible', !open);
        menu.classList.toggle('opacity-0', !open);
        menu.classList.toggle('-translate-y-2', !open);
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(!open);
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) { setOpen(false); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { setOpen(false); }
    });

    /* ---- data ---- */
    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function render(data) {
        var count = data.unread_count;

        // The badge is grid, not block — hidden must be toggled, not replaced.
        badge.className = count > 0
            ? 'absolute -right-1.5 -top-1.5 grid h-5 min-w-[20px] place-items-center rounded-full '
              + 'border-2 border-mint bg-danger px-1 font-mono text-[11px] text-white'
            : 'absolute -right-1.5 -top-1.5 hidden h-5 min-w-[20px] place-items-center rounded-full '
              + 'border-2 border-mint bg-danger px-1 font-mono text-[11px] text-white';

        badge.textContent = count > 9 ? '9+' : count;

        readAll.className = count > 0
            ? 'text-xs font-semibold text-plum hover:underline'
            : 'hidden text-xs font-semibold text-plum hover:underline';

        if (data.items.length === 0) {
            list.innerHTML = '<p class="px-[18px] py-[34px] text-center text-[13px] text-dim">'
                           + "You're all caught up. 🎉</p>";
            return;
        }

        var html = '';

        data.items.forEach(function (item) {
            html +=
              '<a href="' + esc(item.url) + '" '
            +    'class="flex gap-3 border-b border-line bg-plum/5 px-[18px] py-3.5 '
            +    'transition hover:bg-surface-soft">'
            +   '<span class="text-[17px]">' + esc(item.icon) + '</span>'
            +   '<span class="min-w-0 flex-1">'
            +     '<span class="block text-[13px] font-semibold text-ink">' + esc(item.title) + '</span>'
            +     '<span class="mt-0.5 block text-[12px] leading-relaxed text-dim">' + esc(item.body) + '</span>'
            +     '<span class="mt-1 block font-mono text-[10px] text-steel">' + esc(item.created_ago) + '</span>'
            +   '</span>'
            +   '<span class="mt-1.5 h-[7px] w-[7px] shrink-0 rounded-full bg-plum"></span>'
            + '</a>';
        });

        list.innerHTML = html;
    }

    /* Exposed globally so the notifications page can refresh the bell after
       marking something read, without duplicating this code. */
    window.trybeRefreshBell = function () {
        return api.get('/api/v1/notifications/unread-summary')
            .then(function (response) { render(response.data); })
            .catch(function () {
                list.innerHTML = '<p class="px-[18px] py-[34px] text-center text-[13px] text-dim">'
                               + 'Could not load notifications.</p>';
            });
    };

    readAll.addEventListener('click', function () {
        this.disabled = true;

        api.post('/api/v1/notifications/read-all')
            .then(function () { return window.trybeRefreshBell(); })
            .catch(function () {})
            .then(function () { readAll.disabled = false; });
    });

    window.trybeRefreshBell();
});
</script>
@endauth