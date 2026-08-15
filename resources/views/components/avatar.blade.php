{{--
    <x-avatar :name="$user->name" />
    <x-avatar :name="$user->name" size="lg" />

    The initials come from the User model's initials() method when a User is
    passed, otherwise they are worked out from the plain name string.
--}}

@props([
    'name' => '',
    'size' => 'md',
])

@php
    $sizes = [
        'sm' => 'h-8 w-8 text-[12px]',
        'md' => 'h-[34px] w-[34px] text-[14px]',
        'lg' => 'h-14 w-14 text-[20px]',
    ];

    $clean = preg_replace('/^dr\.?\s*/i', '', trim($name));
    $parts = array_values(array_filter(preg_split('/\s+/', $clean)));

    $initials = $parts
        ? strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''))
        : 'TR';
@endphp

<span {{ $attributes->merge([
        'class' => 'inline-flex shrink-0 items-center justify-center rounded-full
                    bg-gradient-to-br from-plum to-steel font-semibold text-white '
                   . ($sizes[$size] ?? $sizes['md']),
    ]) }}>
    {{ $initials }}
</span>
