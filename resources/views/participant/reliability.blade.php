@extends('layouts.app')

@section('title', 'Reliability')

@section('content')
<div class="wrap pb-20">

    <x-page-header
        eyebrow="Participant · Reliability"
        title="Show up, and your score speaks for you."
        subtitle="Three weighted factors, all measured from what you actually did on the platform. A higher score moves you up the queue when a study has more applicants than seats." />

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[1.4fr_1fr]">

        {{-- ================= LEFT: the breakdown ================= --}}
        <div class="space-y-6">

            <x-panel label="How your score is built"
                     note="Each factor is measured from your real sessions, then multiplied by its weight."
                     class="reveal">

                @foreach ($breakdown as $key => $factor)
                    <div class="border-b border-line py-5 first:pt-1 last:border-none last:pb-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <b class="text-[14px] text-ink">{{ $factor['label'] }}</b>
                                <x-badge tone="neutral">{{ $factor['weight'] }}% weight</x-badge>
                            </div>

                            <span class="font-display text-[22px] font-semibold text-ink">
                                {{ $factor['value'] }}
                            </span>
                        </div>

                        <p class="mt-1 text-[12.5px] text-dim">{{ $factor['desc'] }}</p>

                        <div class="mt-3">
                            <x-progress
                                :value="$factor['value']"
                                :max="100"
                                :tone="$key === 'attendance' ? 'ok' : ($key === 'reviews' ? 'flame' : 'plum')" />
                        </div>

                        <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2">
                            <span class="font-mono text-[11px] {{ $factor['hasData'] ? 'text-steel' : 'text-flame' }}">
                                {{ $factor['detail'] }}
                            </span>

                            <span class="font-mono text-[11px] text-plum">
                                {{ $factor['value'] }} × {{ $factor['weight'] }}%
                                = {{ $factor['contribution'] }} points
                            </span>
                        </div>
                    </div>
                @endforeach

                <div class="mt-5 flex items-center justify-between rounded-xl bg-surface-soft px-4 py-3.5">
                    <span class="text-[13px] font-semibold text-ink">Total</span>
                    <span class="font-mono text-[14px] text-plum">
                        {{ collect($breakdown)->sum('contribution') }} / 100
                    </span>
                </div>
            </x-panel>

            {{-- Seat auction, ranked against real participants --}}
            <x-panel label="Where it gets you — seat auctions" class="reveal reveal-d1">
                <p class="mb-4 text-[13px] leading-relaxed text-dim">
                    When a paid study has fewer seats than applicants, seats go to the
                    highest reliability scores rather than whoever clicked first. Here is
                    where you would land against
                    <b class="text-ink">{{ $pool->count() - 1 }}</b> other real participants
                    for a study with <b class="text-ink">{{ $seats }}</b> seats.
                </p>

                <div class="mb-4 rounded-xl px-4 py-3 text-[13px]
                            {{ $wonSeat ? 'bg-ok/10 text-ok' : 'bg-steel/15 text-dim' }}">
                    ◆ You rank <b>#{{ $rank }}</b> —
                    {{ $wonSeat ? "you'd win a seat." : 'not in the seats yet.' }}
                </div>

                @foreach ($pool as $i => $row)
                    <div class="mb-2 flex items-center gap-3.5 rounded-xl border px-4 py-3 last:mb-0
                                {{ $row['you'] ? 'border-plum bg-plum/5' : 'border-line' }}">
                        <span class="w-8 font-mono text-[12px] {{ $i < $seats ? 'text-ok' : 'text-steel' }}">
                            #{{ $i + 1 }}
                        </span>

                        <span class="min-w-0 flex-1 truncate text-[13.5px] {{ $row['you'] ? 'font-semibold text-plum' : 'text-ink' }}">
                            {{ $row['name'] }}
                        </span>

                        @if ($i < $seats)
                            <x-badge tone="ok">Seat</x-badge>
                        @endif

                        <span class="font-mono text-[13px] text-ink">{{ $row['score'] }}</span>
                    </div>
                @endforeach
            </x-panel>
        </div>

        {{-- ================= RIGHT: the gauge ================= --}}
        <div class="space-y-6">

            <div class="reveal reveal-d1 relative overflow-hidden rounded-panel bg-gradient-to-br from-ink to-plum
                        p-8 text-center text-white shadow-[0_20px_46px_-22px_rgba(79,58,101,.7)]">

                <div class="pointer-events-none absolute -left-16 -top-20 h-64 w-64 rounded-full"
                     style="background:radial-gradient(circle, rgba(223,240,234,.2), transparent 70%);"></div>

                <div class="relative">
                    <p class="font-mono text-[10.5px] uppercase tracking-[0.2em] text-steel">
                        Reliability score
                    </p>

                    @php
                        $r = 76;
                        $c = 2 * M_PI * $r;
                        $off = $c * (1 - min(100, max(0, $score)) / 100);
                    @endphp

                    <div class="relative mx-auto mt-5 h-[180px] w-[180px]">
                        <svg width="180" height="180" class="-rotate-90">
                            <circle cx="90" cy="90" r="{{ $r }}" fill="none"
                                    stroke="rgba(255,255,255,.14)" stroke-width="13" />
                            <circle cx="90" cy="90" r="{{ $r }}" fill="none"
                                    stroke="#DFF0EA" stroke-width="13" stroke-linecap="round"
                                    stroke-dasharray="{{ $c }}" stroke-dashoffset="{{ $off }}" />
                        </svg>

                        <div class="absolute left-1/2 top-1/2 flex -translate-x-1/2 -translate-y-1/2 items-baseline gap-1 whitespace-nowrap">
                            <span class="font-display text-[48px] font-semibold leading-none text-white">
                                {{ $score }}
                            </span>
                            <span class="font-mono text-[12px] text-steel">/ 100</span>
                        </div>
                    </div>

                    <span class="mt-4 inline-block rounded-full bg-white/15 px-3.5 py-1.5
                                 font-mono text-[11px] uppercase tracking-wider text-white">
                        {{ $band['label'] }}
                    </span>

                    <p class="mt-3 text-[13px] leading-relaxed text-white/75">{{ $band['msg'] }}</p>

                    <form method="POST" action="{{ route('participant.reliability.recalculate') }}" class="mt-6">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-xl bg-mint px-5 py-3 text-sm font-semibold text-ink
                                       transition hover:-translate-y-px">
                            Recalculate my score
                        </button>
                    </form>
                </div>
            </div>

            <x-panel label="The weights" class="reveal reveal-d2">
                <p class="mb-4 text-[13px] leading-relaxed text-dim">
                    These live in <code class="font-mono text-[12px] text-plum">config/platform.php</code>,
                    not in the calculation itself. Change one and both the maths and the
                    labels above update together.
                </p>

                <dl class="divide-y divide-line text-[13px]">
                    @foreach ($weights as $name => $weight)
                        <div class="flex justify-between py-2.5">
                            <dt class="text-dim">{{ ucfirst($name) }}</dt>
                            <dd class="font-mono text-ink">{{ $weight }}%</dd>
                        </div>
                    @endforeach
                    <div class="flex justify-between py-2.5">
                        <dt class="font-semibold text-ink">Total</dt>
                        <dd class="font-mono {{ array_sum($weights) === 100 ? 'text-ok' : 'text-danger' }}">
                            {{ array_sum($weights) }}%
                        </dd>
                    </div>
                </dl>
            </x-panel>

            <x-panel label="Saved on your profile" class="reveal reveal-d2">
                <p class="text-[12.5px] leading-relaxed text-dim">
                    The four columns below are what other pages read. They only change
                    when a recalculation runs — the page above always shows live maths,
                    so if they disagree, you have unsaved changes.
                </p>

                <dl class="mt-3 divide-y divide-line text-[13px]">
                    <div class="flex justify-between py-2"><dt class="text-dim">rel_attendance</dt>
                        <dd class="font-mono text-ink">{{ $profile->rel_attendance }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-dim">rel_completion</dt>
                        <dd class="font-mono text-ink">{{ $profile->rel_completion }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-dim">rel_reviews</dt>
                        <dd class="font-mono text-ink">{{ $profile->rel_reviews }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="font-semibold text-ink">reliability_score</dt>
                        <dd class="font-mono text-plum">{{ $profile->reliability_score }}</dd></div>
                </dl>
            </x-panel>
        </div>
    </div>
</div>
@endsection
