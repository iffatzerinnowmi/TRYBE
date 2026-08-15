{{--
    Small pill label.

    <x-badge tone="ok">Completed</x-badge>
    <x-badge tone="gold">Gold</x-badge>
    <x-badge :tone="$profile->credential_level->value">{{ $profile->credential_level->label() }}</x-badge>

    Tones map onto the credential tiers (light -> dark = Bronze -> Expert)
    plus the shared status colours.
--}}

@props(['tone' => 'neutral'])

@php
    $tones = [
        // credential tiers
        'none'    => 'bg-steel/15 text-steel',
        'bronze'  => 'bg-steel/20 text-ink',
        'gold'    => 'bg-star/20 text-[#B4832E]',
        'expert'  => 'bg-plum/15 text-plum',

        // status
        'ok'        => 'bg-ok/12 text-ok',
        'flame'     => 'bg-flame/15 text-flame',
        'danger'    => 'bg-danger/12 text-danger',
        'plum'      => 'bg-plum/12 text-plum',
        'neutral'   => 'bg-steel/18 text-steel',
    ];
@endphp

<span {{ $attributes->merge([
        'class' => 'inline-block whitespace-nowrap rounded-full px-2.5 py-1
                    font-mono text-[10.5px] tracking-wide '
                   . ($tones[$tone] ?? $tones['neutral']),
    ]) }}>
    {{ $slot }}
</span>
