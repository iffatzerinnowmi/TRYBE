{{--
    White rounded card used for every block of content.

    <x-panel label="Your progress" note="Updated automatically">
        ...content...
    </x-panel>

    <x-panel label="Recent studies">
        <x-slot:action><a href="/studies">See all</a></x-slot:action>
        ...
    </x-panel>
--}}

@props([
    'label' => null,
    'note' => null,
    'action' => null,
])

<section {{ $attributes->merge([
        'class' => 'rounded-panel border border-line bg-surface p-6 shadow-soft sm:p-[26px]',
    ]) }}>

    @if ($label)
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-mono text-[11.5px] uppercase tracking-[0.2em] text-steel">
                {{ $label }}
            </h2>

            @if ($action)
                <div class="text-[12.5px] font-semibold text-plum">{{ $action }}</div>
            @endif
        </div>
    @endif

    @if ($note)
        <p class="-mt-1 mb-4 text-[13px] text-dim">{{ $note }}</p>
    @endif

    {{ $slot }}
</section>
