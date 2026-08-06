{{--
    TRYBE navbar
    Used automatically by layouts/app.blade.php.

    Links use url('/...') instead of route('...') on purpose: the named routes
    do not exist until Sections 4 and 5, and url() never throws an error for a
    path that is not registered yet.
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
            'Profile'     => '/participant/profile',
            'Reliability' => '/participant/reliability',
            'Credentials' => '/participant/credentials',
            'Alerts'      => '/notifications',
        ],
        'researcher' => [
            'Dashboard'    => '/dashboard',
            'Profile'      => '/researcher/profile',
            'Verification' => '/verification',
            'Endorsements' => '/researcher/endorsements',
            'Alerts'       => '/notifications',
        ],
        'admin' => [
            'Overview' => '/dashboard',
            'Alerts'   => '/notifications',
        ],
        'organization' => [
            'Dashboard'    => '/dashboard',
            'Verification' => '/verification',
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
                         $navUnreadCount and $navUnread are supplied by
                         App\View\Composers\NotificationComposer, which attaches
                         them to this component on every page it renders.
                    ================================================================ --}}
                    <div class="relative" id="bell-wrap">
                        <button type="button" id="bell-btn" aria-label="Notifications"
                                class="relative grid h-10 w-10 place-items-center rounded-xl border
                                       border-line-hi bg-surface text-[17px] transition
                                       hover:-translate-y-px hover:border-plum">
                            🔔
                            @if ($navUnreadCount > 0)
                                <span id="bell-count"
                                      class="absolute -right-1.5 -top-1.5 grid h-5 min-w-[20px] place-items-center
                                             rounded-full border-2 border-mint bg-danger px-1
                                             font-mono text-[11px] text-white">
                                    {{ $navUnreadCount > 9 ? '9+' : $navUnreadCount }}
                                </span>
                            @endif
                        </button>

                        <div id="bell-dropdown"
                             class="invisible absolute right-0 top-[52px] z-[70] w-[380px] max-w-[calc(100vw-40px)]
                                    -translate-y-2 overflow-hidden rounded-[18px] border border-line
                                    bg-surface opacity-0 shadow-[0_24px_54px_-22px_rgba(79,58,101,.45)]
                                    transition-all duration-150">

                            <div class="flex items-center justify-between border-b border-line px-[18px] py-[15px]">
                                <b class="font-display text-base text-ink">Notifications</b>

                                @if ($navUnreadCount > 0)
                                    <form method="POST" action="/notifications/read">
                                        @csrf
                                        <button type="submit"
                                                class="text-xs font-semibold text-plum hover:underline">
                                            Mark all read
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <div class="max-h-[340px] overflow-y-auto">
                                @forelse ($navUnread as $item)
                                    <a href="{{ $item->url ?? '/notifications' }}"
                                       class="flex gap-3 border-b border-line bg-plum/5 px-[18px] py-3.5
                                              transition hover:bg-surface-soft">
                                        <span class="text-[17px]">{{ $item->icon }}</span>

                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[13px] font-semibold text-ink">{{ $item->title }}</span>
                                            <span class="mt-0.5 block text-[12px] leading-relaxed text-dim">
                                                {{ Str::limit($item->body, 90) }}
                                            </span>
                                            <span class="mt-1 block font-mono text-[10px] text-steel">
                                                {{ $item->created_at->diffForHumans() }}
                                            </span>
                                        </span>

                                        <span class="mt-1.5 h-[7px] w-[7px] shrink-0 rounded-full bg-plum"></span>
                                    </a>
                                @empty
                                    <p class="px-[18px] py-[34px] text-center text-[13px] text-dim">
                                        You're all caught up. 🎉
                                    </p>
                                @endforelse
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
/* Opens and closes the bell dropdown, and closes it when you click away. */
(function () {
    var btn = document.getElementById('bell-btn');
    var menu = document.getElementById('bell-dropdown');
    var wrap = document.getElementById('bell-wrap');

    if (!btn || !menu) return;

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
        if (!wrap.contains(e.target)) setOpen(false);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });
})();
</script>
@endauth
