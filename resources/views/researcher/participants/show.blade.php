@extends('layouts.app')

@section('title', $participant->name)

@section('content')
<div class="wrap pb-20">
    <x-page-header eyebrow="Researcher · Participant profile" title="{{ $participant->name }}">
        <x-btn href="{{ route('studies.show', $study) }}" variant="ghost">Back to study</x-btn>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <x-panel label="Profile details" class="reveal">
            <div class="space-y-5">
                <div class="flex items-start gap-4">
                    <x-avatar :name="$participant->name" size="lg" />
                    <div>
                        <h2 class="font-display text-2xl font-semibold text-ink">{{ $participant->name }}</h2>
                        <p class="mt-1 text-[13.5px] text-dim">{{ $participant->location ?? 'No location set' }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-badge tone="plum">{{ $profile->credential_level->label() }}</x-badge>
                            <x-badge tone="{{ $profile->is_verified_participant ? 'ok' : 'neutral' }}">
                                {{ $profile->is_verified_participant ? 'Verified participant' : 'Not verified' }}
                            </x-badge>
                            <x-badge tone="gold">Reliability {{ $profile->reliability_score }}</x-badge>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Age</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">{{ $profile->age ?? '—' }}</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Occupation</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">{{ $profile->occupation ?? '—' }}</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Completed studies</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">{{ $profile->completed_studies_count }}</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Current streak</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">{{ $profile->current_streak_weeks }} weeks</div>
                    </div>
                </div>

                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Skills</div>
                    <p class="mt-2 text-[13.5px] leading-relaxed text-ink">{{ $profile->skills ?? '—' }}</p>
                </div>

                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Interests</div>
                    <p class="mt-2 text-[13.5px] leading-relaxed text-ink">{{ $profile->interests ?? '—' }}</p>
                </div>

                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Health background</div>
                    <p class="mt-2 text-[13.5px] leading-relaxed text-ink">{{ $profile->health_background ?? '—' }}</p>
                </div>
            </div>
        </x-panel>

        <div class="space-y-6">
            <x-panel label="Match with current study" class="reveal reveal-d1">
                <div class="space-y-3 text-[13.5px] text-dim">
                    <p>
                        <b class="text-ink">{{ $match['score'] }}/100</b> for {{ $study->title }}.
                    </p>

                    <div class="flex flex-wrap gap-2">
                        @forelse ($match['reasons'] as $reason)
                            <x-badge tone="plum">{{ $reason }}</x-badge>
                        @empty
                            <x-badge tone="neutral">No match reasons available</x-badge>
                        @endforelse
                    </div>

                    @if ($invited)
                        <x-badge tone="expert">Already invited</x-badge>
                    @else
                        <form method="POST" action="{{ route('researcher.studies.invite', [$study, $participant]) }}">
                            @csrf
                            <x-btn type="submit">Invite participant</x-btn>
                        </form>
                    @endif
                </div>
            </x-panel>

            <x-panel label="Recent activity" class="reveal reveal-d2">
                <div class="space-y-3">
                    @forelse ($participations as $participation)
                        <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-[13.5px] font-semibold text-ink">{{ $participation->study->title }}</div>
                                    <div class="font-mono text-[11px] text-steel">{{ $participation->stage->label() }}</div>
                                </div>
                                <div class="font-mono text-[10.5px] text-steel">{{ $participation->updated_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-[13.5px] text-dim">No participation history yet.</p>
                    @endforelse
                </div>
            </x-panel>
        </div>
    </div>
</div>
@endsection