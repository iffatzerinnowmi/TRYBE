{{--
    One tile in a dashboard stat row.

    <x-stat-card icon="✅" :value="$completedCount" label="Studies completed" />
    <x-stat-card icon="🔥" :value="$streak" label="Week streak">
        <x-badge tone="flame">On a roll</x-badge>
    </x-stat-card>

    $value must always come from the controller — never type a number here.
--}}

@props([
    'icon' => null,
    'value' => '—',
    'label' => '',
])

<div {{ $attributes->merge([
        'class' => 'rounded-card border border-line bg-surface p-5 shadow-soft
                    transition-transform duration-150 hover:-translate-y-0.5',
    ]) }}>

    @if ($icon)
        <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-[10px]
                    bg-surface-soft text-base">
            {{ $icon }}
        </div>
    @endif

    <b class="block font-display text-[28px] font-semibold leading-none text-ink">
        {{ $value }}
    </b>

    <div class="mt-2 font-mono text-[11.5px] tracking-wide text-steel">
        {{ $label }}
    </div>

    @if ($slot->isNotEmpty())
        <div class="mt-2.5">{{ $slot }}</div>
    @endif
</div>
