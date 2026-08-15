@extends('layouts.app')

@section('title', 'Studies')

@section('content')
<div class="wrap pb-20">
    <x-page-header eyebrow="Studies" title="Open studies looking for participants.">
        <x-btn href="{{ route('dashboard') }}" variant="ghost">Back to dashboard</x-btn>
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($studies as $study)
            <x-panel class="reveal">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge tone="{{ $study->incentive_type->requiresEscrow() ? 'ok' : 'neutral' }}">
                        {{ $study->incentive_type->label() }}
                    </x-badge>
                    @if ($study->irb_flagged)
                        <x-badge tone="flame">IRB pending</x-badge>
                    @endif
                    @if (! is_null($study->match_score ?? null))
                        <x-badge tone="{{ $study->strong_match ? 'expert' : 'gold' }}">Match {{ $study->match_score }}%</x-badge>
                    @endif
                </div>

                <h2 class="mt-3 font-display text-[20px] font-semibold leading-snug text-ink">{{ $study->title }}</h2>
                <p class="mt-2 font-mono text-[11.5px] text-steel">{{ $study->researcher->name }} · {{ $study->duration_minutes }} min · {{ ucfirst(str_replace('_', ' ', $study->method)) }}</p>
                <p class="mt-3 line-clamp-3 text-[13.5px] leading-relaxed text-dim">{{ $study->description }}</p>

                @if (! empty($study->match_reasons ?? []))
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($study->match_reasons as $reason)
                            <x-badge tone="plum">{{ $reason }}</x-badge>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4 flex items-center justify-between gap-3">
                    <div class="font-mono text-[11px] text-steel">৳{{ number_format($study->compensation_amount, 0) }} · {{ $study->slots }} slots</div>
                    <x-btn href="{{ route('studies.show', $study) }}" variant="soft" size="sm">View details</x-btn>
                </div>
            </x-panel>
        @empty
            <p class="text-[13.5px] text-dim">No open studies right now.</p>
        @endforelse
    </div>
</div>
@endsection