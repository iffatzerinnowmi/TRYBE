{{--
    Horizontal progress bar.

    <x-progress :value="$completed" :max="$goal" label="Progress to Gold" />

    :value and :max always come from the controller, so the bar is a picture
    of real database numbers rather than a decoration.
--}}

@props([
    'value' => 0,
    'max' => 100,
    'label' => null,
    'tone' => 'plum',   // plum | ok | flame
])

@php
    $max = max(1, (int) $max);
    $value = max(0, (int) $value);
    $percent = min(100, (int) round(($value / $max) * 100));

    $fills = [
        'plum'  => 'bg-plum',
        'ok'    => 'bg-ok',
        'flame' => 'bg-flame',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if ($label)
        <div class="mb-2 flex items-center justify-between text-[12.5px] text-dim">
            <span>{{ $label }}</span>
            <b class="font-mono text-ink">{{ $percent }}%</b>
        </div>
    @endif

    <div class="h-2.5 w-full overflow-hidden rounded-full bg-steel/25"
         role="progressbar"
         aria-valuenow="{{ $value }}"
         aria-valuemin="0"
         aria-valuemax="{{ $max }}">
        <div class="h-full rounded-full transition-[width] duration-700 ease-out {{ $fills[$tone] ?? $fills['plum'] }}"
             style="width: {{ $percent }}%"></div>
    </div>
</div>
