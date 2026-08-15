{{--
    The heading block at the top of every inner page.

    <x-page-header eyebrow="Participant" title="Welcome back, {{ $user->name }}">
        <x-btn href="/studies">Browse studies</x-btn>
    </x-page-header>
--}}

@props([
    'eyebrow' => null,
    'title' => '',
    'subtitle' => null,
])

<header class="flex flex-wrap items-end justify-between gap-5 pb-5 pt-11">
    <div>
        @if ($eyebrow)
            <p class="font-mono text-xs uppercase tracking-[0.24em] text-steel">
                {{ $eyebrow }}
            </p>
        @endif

        <h1 class="mt-3 font-display text-[clamp(28px,4vw,40px)] font-semibold leading-[1.08] text-ink">
            {{ $title }}
        </h1>

        @if ($subtitle)
            <p class="mt-2.5 max-w-2xl text-sm text-dim">{{ $subtitle }}</p>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="flex items-center gap-2.5">{{ $slot }}</div>
    @endif
</header>
