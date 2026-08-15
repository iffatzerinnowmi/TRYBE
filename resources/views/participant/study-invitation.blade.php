@extends('layouts.app')

@section('title', 'Study Invitation')

@section('content')
<div class="wrap pb-16">
    <x-page-header eyebrow="Participant · Invitation" title="You've been invited to a study.">
        <x-btn href="{{ route('participant.dashboard') }}" variant="ghost">Back to dashboard</x-btn>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[1.45fr_1fr]">
        <x-panel label="Study details" class="reveal">
            <div class="space-y-4">
                <div>
                    <h2 class="font-display text-2xl font-semibold text-ink">{{ $study->title }}</h2>
                    <p class="mt-2 text-[13.5px] leading-relaxed text-dim">{{ $study->description }}</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Researcher</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">{{ $study->researcher->name }}</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Match score</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">{{ $match['score'] }}/100</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Duration</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">{{ $study->duration_minutes }} min</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Compensation</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">৳{{ number_format($study->compensation_amount, 0) }}</div>
                    </div>
                </div>

                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Why you're a match</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse ($match['reasons'] as $reason)
                            <x-badge tone="plum">{{ $reason }}</x-badge>
                        @empty
                            <x-badge tone="neutral">No match reasons available</x-badge>
                        @endforelse
                    </div>
                </div>
            </div>
        </x-panel>

        <x-panel label="Invitation response" class="reveal reveal-d1">
            <div class="space-y-4 text-[13.5px] text-dim">
                <p>
                    Review the study details and respond below. Accepting will add this study to your pipeline.
                </p>

                <div class="rounded-xl border border-line bg-surface-soft p-4">
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Criteria</div>
                    <p class="mt-2 leading-relaxed text-ink">{{ $criteriaSummary }}</p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('participant.studies.invitation.accept', $study) }}">
                        @csrf
                        <x-btn type="submit">Accept invite</x-btn>
                    </form>

                    <form method="POST" action="{{ route('participant.studies.invitation.decline', $study) }}">
                        @csrf
                        <x-btn type="submit" variant="ghost">Decline</x-btn>
                    </form>
                </div>
            </div>
        </x-panel>
    </div>
</div>
@endsection