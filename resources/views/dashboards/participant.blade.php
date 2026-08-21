@extends('layouts.app')

@section('title', 'Home')

@section('content')
<div class="wrap pb-20">

    <x-page-header eyebrow="Participant · Home" title="Welcome back, {{ explode(' ', $user->name)[0] }}.">
        <x-btn href="{{ route('studies.index') }}">Browse studies</x-btn>
    </x-page-header>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    {{-- ---------- headline numbers, all from the profile row ---------- --}}
    <div class="grid grid-cols-2 gap-4 py-2 md:grid-cols-5">
        <x-stat-card icon="📊" :value="$profile->reliability_score" label="RELIABILITY SCORE" class="reveal" />

        <x-stat-card icon="🥉" :value="$profile->credential_level->label()" label="CREDENTIAL LEVEL" class="reveal reveal-d1">
            <x-badge :tone="$profile->credential_level->value">
                {{ $profile->completed_studies_count }} completed
            </x-badge>
        </x-stat-card>

        <x-stat-card icon="🔥" :value="$profile->current_streak_weeks" label="WEEK STREAK" class="reveal reveal-d2">
            @if ($profile->current_streak_weeks > 0)
                <x-badge tone="flame">Active this week</x-badge>
            @else
                <x-badge tone="neutral">No streak yet</x-badge>
            @endif
        </x-stat-card>

        <x-stat-card icon="🏅" :value="$profile->endorsement_count" label="ENDORSEMENTS" class="reveal reveal-d3">
            <x-badge :tone="$profile->is_verified_participant ? 'ok' : 'neutral'">
                {{ $profile->is_verified_participant
                   ? 'Verified participant'
                   : config('platform.endorsements_for_verified_badge') - $profile->endorsement_count . ' to go' }}
            </x-badge>
        </x-stat-card>

        <x-stat-card icon="⭐" :value="$karmaBalance" label="KARMA CREDITS" class="reveal reveal-d3">
            <a href="{{ route('participant.karma') }}" class="text-[12px] font-semibold text-plum hover:underline">
                View activity →
            </a>
        </x-stat-card>
    </div>

    {{-- ---------- unlock progress ---------- --}}
    <div class="reveal reveal-d2 my-6 flex flex-wrap items-center gap-6 rounded-panel border border-line
                bg-surface px-7 py-5 shadow-soft">
        <div class="min-w-[240px] flex-1">
            <b class="text-ink">{{ min($completedCount, $unlockTarget) }} of {{ $unlockTarget }}</b>
            <span class="text-ink"> volunteer studies completed</span>
            <p class="mt-1 text-[13px] text-dim">
                @if ($unlockRemaining > 0)
                    Complete {{ $unlockRemaining }} more to unlock paid studies.
                @else
                    Paid studies are unlocked for your account.
                @endif
            </p>
        </div>

        <div class="flex min-w-[220px] flex-[2] gap-1.5">
            @for ($i = 0; $i < $unlockTarget; $i++)
                <div class="h-2.5 flex-1 rounded-full {{ $i < $completedCount ? 'bg-ok' : 'bg-steel/25' }}"></div>
            @endfor
        </div>

        <div class="whitespace-nowrap font-mono text-[12.5px] text-plum">
            {{ $completedCount }} / {{ $unlockTarget }}
        </div>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-[1.6fr_1fr]">

        {{-- ================= LEFT COLUMN ================= --}}
        <div class="space-y-6">

            {{-- Recommended for you (Member 4). API-driven: the partial
                 ships empty and resources/js/feed.js fills it from
                 GET /api/v1/participants/me/feed?limit=4 --}}
            @include('participant.partials.recommended-panel')

            {{-- Their own applications --}}
            <x-panel label="Your applications" class="reveal reveal-d3">
                @forelse ($applications as $application)
                    <div class="mb-2.5 flex items-center gap-3 rounded-xl border border-line px-3.5 py-3 last:mb-0">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[13.5px] font-semibold text-ink">
                                {{ $application->study->title }}
                            </div>
                            <div class="font-mono text-[11.5px] text-steel">
                                {{ $application->study->researcher->name }}
                            </div>
                        </div>

                        <x-badge tone="{{ match ($application->stage->value) {
                            'completed', 'paid' => 'ok',
                            'no_show', 'rejected' => 'danger',
                            'screened' => 'gold',
                            'confirmed', 'scheduled' => 'expert',
                            default => 'neutral',
                        } }}">
                            {{ $application->stage->label() }}
                        </x-badge>
                    </div>
                @empty
                    <p class="py-3 text-[13.5px] text-dim">
                        You haven't applied to any studies yet.
                    </p>
                @endforelse
            </x-panel>
        </div>

        {{-- ================= RIGHT COLUMN ================= --}}
        <div class="space-y-6">

            {{-- Progress to the next credential tier --}}
            <x-panel label="Credential progress" class="reveal reveal-d2">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-soft text-2xl">
                        {{ ['none' => '⚪', 'bronze' => '🥉', 'gold' => '🥈', 'expert' => '🏆'][$profile->credential_level->value] }}
                    </div>
                    <div>
                        <div class="font-display text-xl font-semibold text-ink">
                            {{ $profile->credential_level->label() }}
                        </div>
                        <div class="font-mono text-[11.5px] text-steel">
                            {{ $profile->completed_studies_count }} studies completed
                        </div>
                    </div>
                </div>

                @if ($next)
                    <div class="mt-5">
                        <x-progress
                            :value="$profile->completed_studies_count"
                            :max="$next->minCompletions()"
                            :label="'Towards ' . $next->label()" />

                        <p class="mt-3 text-[12.5px] text-dim">
                            {{ max(0, $next->minCompletions() - $profile->completed_studies_count) }}
                            more completed {{ ($next->minCompletions() - $profile->completed_studies_count) === 1 ? 'study' : 'studies' }}
                            to reach {{ $next->label() }}.
                        </p>
                    </div>
                @else
                    <p class="mt-5 text-[12.5px] text-dim">
                        You've reached the top tier. Nothing left to climb.
                    </p>
                @endif
            </x-panel>

            {{-- Badge wall --}}
            <x-panel label="Badges" class="reveal reveal-d3">
                <div class="grid grid-cols-3 gap-2.5">
                    @foreach ($badges as $badge)
                        <div class="rounded-[13px] border p-3.5 text-center
                                    {{ $badge['earned']
                                       ? 'border-plum bg-plum/5'
                                       : 'border-line opacity-40' }}">
                            <div class="text-xl">{{ $badge['icon'] }}</div>
                            <div class="mt-1.5 font-mono text-[9.5px] uppercase tracking-wide text-dim">
                                {{ $badge['name'] }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="mt-4 text-[12px] leading-relaxed text-dim">
                    Badges light up on their own as your completed count, streak and
                    endorsements grow — there is nothing to claim.
                </p>
            </x-panel>

            {{-- Reliability breakdown --}}
            <x-panel label="Reliability breakdown" class="reveal reveal-d3">
                <div class="space-y-4">
                    <x-progress :value="$profile->rel_attendance" :max="100" label="Attendance" tone="ok" />
                    <x-progress :value="$profile->rel_completion" :max="100" label="Completion" />
                    <x-progress :value="$profile->rel_reviews" :max="100" label="Researcher reviews" tone="flame" />
                </div>
            </x-panel>
        </div>
    </div>
</div>
@endsection
