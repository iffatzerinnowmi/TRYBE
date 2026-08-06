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
            'Home'    => '/dashboard',
            'Studies' => '/studies',
            'Profile' => '/profile',
        ],
        'researcher' => [
            'Dashboard'  => '/dashboard',
            'My studies' => '/studies',
            'Profile'    => '/profile',
        ],
        'admin' => [
            'Overview' => '/dashboard',
        ],
        'organization' => [
            'Dashboard'  => '/dashboard',
            'My studies' => '/studies',
            'Profile'    => '/profile',
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
