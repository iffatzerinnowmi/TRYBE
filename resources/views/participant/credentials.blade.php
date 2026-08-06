@extends('layouts.app')

@section('title', 'Credentials')

@section('content')
<div class="wrap pb-20">

    <x-page-header
        eyebrow="Participant · Credentials"
        title="Your level is earned, not applied for."
        subtitle="Every study you complete is counted automatically. There is no form to fill in and no approval to wait on." />

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[1fr_1.4fr]">

        {{-- ================= LEFT: the badge ================= --}}
        <div class="space-y-6">

            <div class="reveal relative overflow-hidden rounded-panel bg-gradient-to-br from-ink to-plum
                        p-8 text-center text-white shadow-[0_20px_46px_-22px_rgba(79,58,101,.7)]">

                <div class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full"
                     style="background:radial-gradient(circle, rgba(223,240,234,.2), transparent 70%);"></div>

                <div class="relative">
                    <p class="font-mono text-[10.5px] uppercase tracking-[0.2em] text-steel">
                        Current credential
                    </p>

                    {{-- Progress ring. The dash offset is calculated in PHP from
                         the real completed count — no JavaScript decides it. --}}
                    @php
                        $radius = 76;
                        $circumference = 2 * M_PI * $radius;
                        $offset = $circumference * (1 - $progress / 100);
                    @endphp

                    <div class="relative mx-auto mt-5 h-[180px] w-[180px]">
                        <svg width="180" height="180" class="-rotate-90">
                            <circle cx="90" cy="90" r="{{ $radius }}" fill="none"
                                    stroke="rgba(255,255,255,.14)" stroke-width="12" />
                            <circle cx="90" cy="90" r="{{ $radius }}" fill="none"
                                    stroke="#DFF0EA" stroke-width="12" stroke-linecap="round"
                                    stroke-dasharray="{{ $circumference }}"
                                    stroke-dashoffset="{{ $offset }}" />
                        </svg>

                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-[34px] leading-none">
                                {{ ['none' => '⚪', 'bronze' => '🥉', 'gold' => '🥈', 'expert' => '🏆'][$current->value] }}
                            </span>
                            <span class="mt-2 font-display text-[22px] font-semibold text-white">
                                {{ $current->label() }}
                            </span>
                            <span class="font-mono text-[11px] text-steel">
                                {{ $count }} completed
                            </span>
                        </div>
                    </div>

                    @if ($next)
                        <p class="mt-5 text-[13.5px] leading-relaxed text-white/80">
                            <b class="text-mint">{{ $remaining }}</b>
                            more completed {{ $remaining === 1 ? 'study' : 'studies' }}
                            to reach <b class="text-mint">{{ $next->label() }}</b>.
                        </p>
                    @else
                        <p class="mt-5 text-[13.5px] leading-relaxed text-white/80">
                            You've reached the highest tier on the platform.
                        </p>
                    @endif

                    {{-- The button that runs the rule --}}
                    <form method="POST" action="{{ route('participant.credentials.recalculate') }}" class="mt-6">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-xl bg-mint px-5 py-3 text-sm font-semibold text-ink
                                       transition hover:-translate-y-px">
                            Recalculate my level
                        </button>
                    </form>

                    <p class="mt-3 text-[11px] leading-relaxed text-white/50">
                        Normally this runs by itself when a researcher marks a session
                        complete. The button is here so you can watch it work.
                    </p>
                </div>
            </div>

            <x-panel label="How the rule works" class="reveal reveal-d1">
                <p class="text-[13px] leading-relaxed text-dim">
                    Your level is read straight off one number: how many
                    <b class="text-ink">study_participations</b> rows you have at stage
                    <b class="text-ink">completed</b> or <b class="text-ink">paid</b>.
                </p>
                <p class="mt-3 text-[13px] leading-relaxed text-dim">
                    Nothing else affects it — not karma, not your profile, not how long
                    you've been a member. That is deliberate: a credential nobody can
                    game is a credential researchers can trust.
                </p>
            </x-panel>
        </div>

        {{-- ================= RIGHT: ladder + history ================= --}}
        <div class="space-y-6">

            <x-panel label="The ladder" note="Thresholds come from the CredentialLevel enum." class="reveal reveal-d1">
                <div class="space-y-3">
                    @foreach ($ladder as $rung)
                        @php
                            $level = $rung['level'];
                            $earned = $count >= $level->minCompletions();
                            $isCurrent = $current === $level;
                        @endphp

                        <div class="flex items-start gap-4 rounded-xl border p-4 transition
                                    {{ $isCurrent ? 'border-plum bg-plum/5'
                                       : ($earned ? 'border-ok/30 bg-ok/5' : 'border-line opacity-60') }}">

                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl
                                        bg-surface-soft text-xl">
                                {{ ['bronze' => '🥉', 'gold' => '🥈', 'expert' => '🏆'][$level->value] }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <b class="text-[14.5px] text-ink">{{ $level->label() }}</b>
                                    <x-badge :tone="$level->value">
                                        {{ $level->minCompletions() }}+ studies
                                    </x-badge>

                                    @if ($isCurrent)
                                        <x-badge tone="plum">You are here</x-badge>
                                    @elseif ($earned)
                                        <x-badge tone="ok">Earned</x-badge>
                                    @endif
                                </div>

                                <p class="mt-1.5 text-[13px] leading-relaxed text-dim">{{ $rung['perk'] }}</p>

                                @if (! $earned)
                                    <div class="mt-3">
                                        <x-progress :value="$count" :max="$level->minCompletions()" />
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-panel>

            <x-panel label="Studies that count towards your level" class="reveal reveal-d2">
                <x-slot:action>{{ $completed->count() }} counted</x-slot:action>

                @forelse ($completed as $session)
                    <div class="flex items-center gap-3.5 border-b border-line py-3.5 last:border-none last:pb-1">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px]
                                    bg-ok/10 text-[13px] text-ok">✓</div>

                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[13.5px] font-semibold text-ink">
                                {{ $session->study->title }}
                            </div>
                            <div class="font-mono text-[11px] text-steel">
                                {{ $session->study->researcher->name }}
                                @if ($session->completed_at)
                                    · {{ $session->completed_at->format('d M Y') }}
                                @endif
                            </div>
                        </div>

                        <x-badge tone="{{ $session->stage->value === 'paid' ? 'ok' : 'neutral' }}">
                            {{ $session->stage->label() }}
                        </x-badge>
                    </div>
                @empty
                    <p class="py-3 text-[13.5px] text-dim">
                        No completed studies yet. Your first one earns you Bronze.
                    </p>
                @endforelse
            </x-panel>
        </div>
    </div>
</div>
@endsection
