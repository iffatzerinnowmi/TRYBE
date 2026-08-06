<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Account') · TRYBE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen">

<div class="grid min-h-screen lg:grid-cols-[0.95fr_1.05fr]">

    {{-- ============ LEFT: brand panel ============ --}}
    <aside class="relative hidden flex-col justify-between overflow-hidden
                  bg-gradient-to-br from-ink to-plum p-10 text-white lg:flex">

        <div class="pointer-events-none absolute -right-20 -top-24 h-80 w-80 rounded-full"
             style="background:radial-gradient(circle, rgba(223,240,234,.18), transparent 70%);"></div>

        <a href="/" class="relative flex items-center gap-3 font-display text-[22px] font-semibold text-white">
            <span class="h-[11px] w-[11px] rounded-full bg-mint"></span>
            TRYBE
        </a>

        <div class="relative max-w-[38ch]">
            <p class="font-mono text-[11px] uppercase tracking-[0.22em] text-steel">
                @yield('side-eyebrow', 'Welcome')
            </p>

            <h1 class="mt-4 font-display text-[clamp(26px,2.6vw,34px)] font-semibold leading-[1.18] text-white">
                @yield('side-headline')
            </h1>

            <p class="mt-4 text-[14.5px] leading-relaxed text-white/70">
                @yield('side-sub')
            </p>
        </div>

        <div class="relative">
            @yield('side-foot')
        </div>
    </aside>

    {{-- ============ RIGHT: the form ============ --}}
    <div class="flex items-center justify-center px-5 py-10 sm:px-8">
        <div class="w-full max-w-[520px]">

            {{-- Mobile brand, since the left panel is hidden on small screens --}}
            <a href="/" class="mb-7 flex items-center gap-2.5 font-display text-[20px] font-semibold text-ink lg:hidden">
                <span class="h-2.5 w-2.5 rounded-full bg-plum"></span>
                TRYBE
            </a>

            @yield('content')
        </div>
    </div>
</div>

@stack('scripts')

</body>
</html>
