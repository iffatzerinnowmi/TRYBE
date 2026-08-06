{{--
    Button / link button.

    <x-btn href="/signup">Get started</x-btn>          -> renders an <a>
    <x-btn type="submit">Save</x-btn>                  -> renders a <button>
    <x-btn variant="ghost" size="sm">Cancel</x-btn>

    variant: solid (default) | ghost | soft
    size:    md (default)    | sm
--}}

@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'solid',
    'size' => 'md',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-xl font-semibold
             whitespace-nowrap transition-all duration-150 cursor-pointer';

    $sizes = [
        'sm' => 'px-4 py-2.5 text-[13px]',
        'md' => 'px-5 py-3.5 text-sm',
    ];

    $variants = [
        'solid' => 'bg-ink text-white hover:bg-plum hover:-translate-y-px shadow-soft',
        'ghost' => 'border border-line-hi text-ink hover:bg-steel/15',
        'soft'  => 'bg-surface-soft border border-line-hi text-ink hover:bg-ink hover:text-white hover:border-ink',
    ];

    $classes = trim($base . ' ' . ($sizes[$size] ?? $sizes['md']) . ' ' . ($variants[$variant] ?? $variants['solid']));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
