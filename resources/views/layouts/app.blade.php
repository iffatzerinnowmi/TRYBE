<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Each page sets its own title with @section('title', 'Log in') --}}
    <title>@yield('title', 'Find your people') · TRYBE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

    {{-- Vite compiles resources/css/app.css into the finished stylesheet. --}}
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex flex-col">

    {{-- A controller can hide the navbar with ->with('showNav', false) --}}
    @if ($showNav ?? true)
        <x-navbar />
    @endif

    <main class="flex-1">
        @yield('content')
    </main>

    @if ($showFooter ?? true)
        <x-footer />
    @endif

    <script>
        /* ------------------------------------------------------------------
           1. Scroll reveal
           Watches every .reveal element and adds .is-visible once it enters
           the screen, which triggers the fade-up defined in app.css.
        ------------------------------------------------------------------ */
        document.addEventListener('DOMContentLoaded', function () {
            var items = document.querySelectorAll('.reveal');

            if (!('IntersectionObserver' in window)) {
                items.forEach(function (el) { el.classList.add('is-visible'); });
                return;
            }

            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12 });

            items.forEach(function (el) { observer.observe(el); });
        });

        /* ------------------------------------------------------------------
           2. Mobile menu toggle
        ------------------------------------------------------------------ */
        function trybeToggleMenu() {
            var menu = document.getElementById('mobile-menu');
            if (menu) { menu.classList.toggle('hidden'); }
        }
    </script>

    {{-- Individual pages add their own scripts with @push('scripts') --}}
    @stack('scripts')

</body>
</html>
