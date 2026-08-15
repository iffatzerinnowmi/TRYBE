@extends('layouts.app')

@section('title', 'Find your people')

@section('content')
<div class="wrap">

    {{-- ==================================================================
         HERO
         ================================================================== --}}
    <section class="grid items-center gap-12 pb-10 pt-14 lg:grid-cols-[1.15fr_1fr] lg:pt-20">

        <div>
            <span class="reveal inline-flex items-center gap-2.5 rounded-full border border-plum/20
                         bg-plum/10 px-3.5 py-1.5 font-mono text-xs uppercase tracking-[0.22em] text-plum">
                <span class="h-1.5 w-1.5 rounded-full bg-plum"></span>
                The contribution-economy research marketplace
            </span>

            <h1 class="reveal reveal-d1 mt-5 font-display text-[clamp(36px,5.4vw,60px)] font-semibold
                       leading-[1.04] tracking-[-0.02em] text-ink">
                Find your people.
                <em class="italic" style="background:linear-gradient(transparent 62%, rgba(149,173,190,.55) 62%);">Earn your access.</em>
            </h1>

            <p class="reveal reveal-d1 mt-5 max-w-[50ch] text-[17px] leading-[1.65] text-dim">
                TRYBE connects researchers, participants, and organizations in one place.
                Volunteer for {{ $unlockTarget }} studies, and paid research opens up —
                no shortcuts, no pay-to-play. Give a little, get access to a lot.
            </p>

            <div class="reveal reveal-d2 mt-7 flex flex-wrap gap-3">
                <x-btn href="/signup">Join as a participant</x-btn>
                <x-btn href="/signup?role=researcher" variant="ghost">Post a study</x-btn>
            </div>

            {{-- Numbers here are config + enum driven, never typed. --}}
            <div class="reveal reveal-d3 mt-8 flex flex-wrap gap-6">
                <div class="flex items-center gap-2 font-mono text-[12.5px] text-steel">
                    <b class="font-display text-[17px] font-semibold text-ink">{{ $unlockTarget }}</b>
                    volunteer studies to unlock paid access
                </div>
                <div class="flex items-center gap-2 font-mono text-[12.5px] text-steel">
                    <b class="font-display text-[17px] font-semibold text-ink">{{ $tiers->count() }}</b>
                    credential tiers to climb
                </div>
                <div class="flex items-center gap-2 font-mono text-[12.5px] text-steel">
                    <b class="font-display text-[17px] font-semibold text-ink">0%</b>
                    pay-to-skip-the-line
                </div>
            </div>
        </div>

        {{-- Signature widget: the unlock meter.
             Its length comes from config, so changing the platform rule
             changes this widget automatically. --}}
        <div class="reveal reveal-d2 relative overflow-hidden rounded-3xl bg-gradient-to-br from-ink to-plum
                    p-8 text-white shadow-[0_20px_46px_-22px_rgba(79,58,101,.7)]">

            <div class="pointer-events-none absolute -right-12 -top-16 h-56 w-56 rounded-full"
                 style="background:radial-gradient(circle, rgba(223,240,234,.2), transparent 70%);"></div>

            <div class="relative">
                <p class="font-mono text-[10.5px] uppercase tracking-[0.2em] text-steel">How unlocking works</p>

                <h2 class="mt-2.5 font-display text-[21px] font-semibold leading-snug text-white">
                    Complete volunteer studies, unlock paid ones.
                </h2>

                <div class="mt-6 flex gap-[7px]" id="uc-track"
                     data-total="{{ $unlockTarget }}"></div>

                <div class="mt-3.5 flex items-center justify-between">
                    <p class="font-mono text-[12.5px] text-steel" id="uc-count"></p>
                    <div class="flex gap-2">
                        <button type="button" id="uc-minus" aria-label="One fewer"
                                class="h-8 w-8 rounded-[9px] border border-white/25 bg-white/10 text-base
                                       leading-none text-white transition hover:bg-white/20">–</button>
                        <button type="button" id="uc-plus" aria-label="One more"
                                class="h-8 w-8 rounded-[9px] border border-white/25 bg-white/10 text-base
                                       leading-none text-white transition hover:bg-white/20">+</button>
                    </div>
                </div>

                <div id="uc-status" class="mt-5 rounded-[13px] px-4 py-3.5 text-[13px] leading-relaxed transition"></div>

                <p class="mt-4 text-[11.5px] leading-relaxed text-steel">
                    Try it — tap + a few times. This is exactly how it works on your real
                    dashboard, driven by your actual completion count.
                </p>
            </div>
        </div>
    </section>

    {{-- ==================================================================
         LIVE PLATFORM NUMBERS  (straight out of the database)
         ================================================================== --}}
    <section class="reveal grid grid-cols-2 gap-4 py-6 md:grid-cols-4">
        <x-stat-card icon="📋" :value="$stats['open_studies']" label="Studies open now" />
        <x-stat-card icon="🙋" :value="$stats['participants']" label="Participants" />
        <x-stat-card icon="🔬" :value="$stats['researchers']" label="Researchers" />
        <x-stat-card icon="✅" :value="$stats['completed']" label="Sessions completed" />
    </section>

    {{-- ==================================================================
         HOW IT WORKS
         ================================================================== --}}
    <section id="how" class="scroll-mt-24 py-16">
        <div class="reveal mb-10 max-w-[62ch]">
            <p class="font-mono text-xs uppercase tracking-[0.22em] text-steel">How it works</p>
            <h2 class="mt-3.5 font-display text-[clamp(26px,3.4vw,38px)] font-semibold leading-tight text-ink">
                Reciprocity is the whole model.
            </h2>
            <p class="mt-3.5 text-[15.5px] leading-[1.65] text-dim">
                Everyone gives before they take — participants volunteer first, researchers
                contribute to the community before their own listings get full reach. It keeps
                signups meaningful and studies well-run.
            </p>
        </div>

        <div class="grid gap-5 md:grid-cols-3">
            <x-panel class="reveal">
                <div class="mb-3.5 flex h-[42px] w-[42px] items-center justify-center rounded-xl
                            border border-line bg-surface-soft text-lg">🙋</div>
                <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-steel">For participants</p>
                <h3 class="mt-2.5 font-display text-[19px] font-semibold text-ink">Volunteer, then unlock</h3>
                <p class="mt-2 text-[13.5px] leading-relaxed text-dim">
                    Complete {{ $unlockTarget }} free studies and your account automatically opens
                    up to paid research — cash, vouchers, and course credit.
                </p>
            </x-panel>

            <x-panel class="reveal reveal-d1">
                <div class="mb-3.5 flex h-[42px] w-[42px] items-center justify-center rounded-xl
                            border border-line bg-surface-soft text-lg">🔬</div>
                <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-steel">For researchers</p>
                <h3 class="mt-2.5 font-display text-[19px] font-semibold text-ink">Contribute, then get reach</h3>
                <p class="mt-2 text-[13.5px] leading-relaxed text-dim">
                    Free-tier researchers complete {{ $unlockTarget }} other researchers' screener
                    forms before their own listing reaches the participant feed.
                </p>
            </x-panel>

            <x-panel class="reveal reveal-d2">
                <div class="mb-3.5 flex h-[42px] w-[42px] items-center justify-center rounded-xl
                            border border-line bg-surface-soft text-lg">🏅</div>
                <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-steel">For everyone</p>
                <h3 class="mt-2.5 font-display text-[19px] font-semibold text-ink">Karma keeps score</h3>
                <p class="mt-2 text-[13.5px] leading-relaxed text-dim">
                    Attendance, completions, and reviews earn karma credits — spend them on
                    priority placement or competition discounts.
                </p>
            </x-panel>
        </div>
    </section>

    {{-- ==================================================================
         OPEN STUDIES PREVIEW  (real rows)
         ================================================================== --}}
    @if ($openStudies->isNotEmpty())
        <section id="studies" class="scroll-mt-24 pb-16">
            <div class="reveal mb-8 max-w-[62ch]">
                <p class="font-mono text-xs uppercase tracking-[0.22em] text-steel">Open right now</p>
                <h2 class="mt-3.5 font-display text-[clamp(26px,3.4vw,38px)] font-semibold leading-tight text-ink">
                    Live studies looking for people.
                </h2>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                @foreach ($openStudies as $study)
                    <x-panel class="reveal">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge tone="{{ $study->incentive_type->requiresEscrow() ? 'ok' : 'neutral' }}">
                                {{ $study->incentive_type->label() }}
                            </x-badge>

                            @if ($study->irb_flagged)
                                <x-badge tone="flame">No IRB document</x-badge>
                            @endif
                        </div>

                        <h3 class="mt-3 font-display text-[18px] font-semibold leading-snug text-ink">
                            {{ $study->title }}
                        </h3>

                        <p class="mt-2 font-mono text-[11.5px] text-steel">
                            {{ $study->researcher->name }} · {{ $study->duration_minutes }} min ·
                            {{ ucfirst(str_replace('_', ' ', $study->method)) }}
                        </p>

                        <p class="mt-3 line-clamp-3 text-[13.5px] leading-relaxed text-dim">
                            {{ $study->description }}
                        </p>
                    </x-panel>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ==================================================================
         ROLES
         ================================================================== --}}
    <section id="roles" class="scroll-mt-24 pb-16">
        <div class="reveal mb-10 max-w-[62ch]">
            <p class="font-mono text-xs uppercase tracking-[0.22em] text-steel">Who it's for</p>
            <h2 class="mt-3.5 font-display text-[clamp(26px,3.4vw,38px)] font-semibold leading-tight text-ink">
                Three roles, one marketplace.
            </h2>
            <p class="mt-3.5 text-[15.5px] leading-[1.65] text-dim">
                Whichever seat you're in, TRYBE is built around the same idea: show up for the
                community, and the community opens up for you.
            </p>
        </div>

        <div class="grid gap-5 md:grid-cols-3">

            {{-- Participant --}}
            <div class="reveal rounded-panel border border-line bg-surface p-7 shadow-soft
                        transition-transform duration-200 hover:-translate-y-1">
                <p class="font-mono text-[11px] uppercase tracking-[0.14em] text-steel">Participant</p>
                <h3 class="mt-2.5 font-display text-[22px] font-semibold text-ink">Volunteer. Earn. Get paid.</h3>
                <p class="mt-2.5 text-[13.5px] leading-[1.65] text-dim">
                    Build a profile, get matched to relevant studies, and climb from Bronze to
                    Expert as you complete more research.
                </p>
                <ul class="mt-4 flex flex-col gap-2 text-[12.5px] text-ink">
                    <li class="flex gap-2"><span class="text-steel">—</span> Browse and apply to studies</li>
                    <li class="flex gap-2"><span class="text-steel">—</span> Track karma, streaks, and credential level</li>
                    <li class="flex gap-2"><span class="text-steel">—</span> Find buddies for paired studies and competitions</li>
                </ul>
            </div>

            {{-- Researcher --}}
            <div class="reveal reveal-d1 rounded-panel border border-line bg-surface p-7 shadow-soft
                        transition-transform duration-200 hover:-translate-y-1">
                <p class="font-mono text-[11px] uppercase tracking-[0.14em] text-steel">Researcher</p>
                <h3 class="mt-2.5 font-display text-[22px] font-semibold text-ink">Recruit trusted participants.</h3>
                <p class="mt-2.5 text-[13.5px] leading-[1.65] text-dim">
                    Post studies, screen applicants automatically, and manage your whole pipeline
                    without leaving the platform.
                </p>
                <ul class="mt-4 flex flex-col gap-2 text-[12.5px] text-ink">
                    <li class="flex gap-2"><span class="text-steel">—</span> Screener builder with conditional logic</li>
                    <li class="flex gap-2"><span class="text-steel">—</span> Visual pipeline: Applied → Completed → Paid</li>
                    <li class="flex gap-2"><span class="text-steel">—</span> Get verified for full platform trust</li>
                </ul>
            </div>

            {{-- Organization --}}
            <div class="reveal reveal-d2 rounded-panel bg-gradient-to-br from-ink to-plum p-7 text-white
                        shadow-[0_18px_40px_-20px_rgba(79,58,101,.7)]
                        transition-transform duration-200 hover:-translate-y-1">
                <p class="font-mono text-[11px] uppercase tracking-[0.14em] text-steel">Organization</p>
                <h3 class="mt-2.5 font-display text-[22px] font-semibold text-white">Recruit at institutional scale.</h3>
                <p class="mt-2.5 text-[13.5px] leading-[1.65] text-white/75">
                    Universities, labs, and companies get a branded profile, unlimited studies,
                    and priority participant matching.
                </p>
                <ul class="mt-4 flex flex-col gap-2 text-[12.5px] text-white/90">
                    <li class="flex gap-2"><span class="text-steel">—</span> Post unlimited studies under your name</li>
                    <li class="flex gap-2"><span class="text-steel">—</span> Invite researchers under your umbrella</li>
                    <li class="flex gap-2"><span class="text-steel">—</span> Run and manage competition listings</li>
                </ul>
            </div>
        </div>
    </section>

    {{-- ==================================================================
         CREDENTIAL LADDER  (looped from the CredentialLevel enum)
         ================================================================== --}}
    <section id="tiers" class="scroll-mt-24 pb-16">
        <div class="reveal mb-10 max-w-[62ch]">
            <p class="font-mono text-xs uppercase tracking-[0.22em] text-steel">Credential ladder</p>
            <h2 class="mt-3.5 font-display text-[clamp(26px,3.4vw,38px)] font-semibold leading-tight text-ink">
                The more you show up, the more opens up.
            </h2>
            <p class="mt-3.5 text-[15.5px] leading-[1.65] text-dim">
                Your credential level is calculated automatically from completed studies —
                no application, no waiting on approval.
            </p>
        </div>

        <div class="grid gap-[18px] md:grid-cols-3">
            @foreach ($tiers as $i => $tier)
                @php
                    // Each tier gets progressively darker: Bronze -> Gold -> Expert.
                    $skin = [
                        'bronze' => 'bg-surface border border-line text-ink',
                        'gold'   => 'bg-gradient-to-br from-[#6d6497] to-plum text-white border border-transparent',
                        'expert' => 'bg-gradient-to-br from-ink to-[#3c2c51] text-white border border-transparent',
                    ][$tier['key']];

                    $chipSkin = $tier['key'] === 'bronze' ? 'bg-steel text-white' : 'bg-white/20 text-white';
                    $muted    = $tier['key'] === 'bronze' ? 'text-steel' : 'text-white/65';
                    $body     = $tier['key'] === 'bronze' ? 'text-ink' : 'text-white/90';
                @endphp

                <div class="reveal reveal-d{{ $i + 1 }} rounded-panel p-6 {{ $skin }}">
                    <div class="mb-3.5 flex h-[38px] w-[38px] items-center justify-center rounded-xl
                                text-base {{ $chipSkin }}">
                        {{ $i + 1 }}
                    </div>

                    <p class="font-mono text-[11px] uppercase tracking-[0.14em] {{ $muted }}">
                        {{ $tier['name'] }}
                    </p>

                    <p class="mt-1.5 font-display text-[24px] font-semibold">
                        {{ $tier['min'] }}+ studies
                    </p>

                    <p class="mt-1.5 font-mono text-[12.5px] {{ $muted }}">{{ $tier['note'] }}</p>

                    <p class="mt-4 text-[13px] leading-[1.6] {{ $body }}">{{ $tier['perk'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ==================================================================
         FINAL CTA
         ================================================================== --}}
    <section class="pb-20">
        <div class="reveal relative overflow-hidden rounded-[26px] bg-gradient-to-br from-ink to-plum
                    px-8 py-14 text-center text-white shadow-[0_24px_54px_-24px_rgba(79,58,101,.7)] sm:px-11">

            <div class="pointer-events-none absolute -top-24 left-1/2 h-80 w-80 -translate-x-1/2 rounded-full"
                 style="background:radial-gradient(circle, rgba(223,240,234,.18), transparent 70%);"></div>

            <div class="relative">
                <h2 class="mx-auto max-w-[22ch] font-display text-[clamp(26px,3.6vw,40px)] font-semibold leading-tight">
                    Your first volunteer study is the only thing standing between you and paid research.
                </h2>

                <p class="mx-auto mt-4 max-w-[48ch] text-[15px] text-white/75">
                    Free to join. No fees to participate. Just show up.
                </p>

                <div class="mt-7 flex flex-wrap justify-center gap-3">
                    <a href="/signup"
                       class="inline-flex items-center rounded-xl bg-mint px-6 py-3.5 text-sm font-semibold
                              text-ink transition hover:-translate-y-px">
                        Create your account
                    </a>
                    <a href="#studies"
                       class="inline-flex items-center rounded-xl border border-white/30 bg-white/10 px-6 py-3.5
                              text-sm font-semibold text-white transition hover:bg-white/20">
                        See open studies
                    </a>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection

@push('scripts')
<script>
/* ---------------------------------------------------------------------------
   The unlock meter.
   It draws one segment per volunteer study required. That number is read from
   the data-total attribute, which the controller filled from
   config('platform.free_forms_to_unlock_paid') — so nothing here is hardcoded.
--------------------------------------------------------------------------- */
(function () {
    var track = document.getElementById('uc-track');
    if (!track) return;

    var total = parseInt(track.dataset.total, 10) || 0;
    var done = 0;

    // Build the segments.
    for (var i = 0; i < total; i++) {
        var seg = document.createElement('div');
        seg.className = 'relative h-2.5 flex-1 overflow-hidden rounded-full bg-white/15';
        seg.innerHTML = '<i class="absolute inset-0 origin-left scale-x-0 rounded-full bg-mint transition-transform duration-500"></i>';
        track.appendChild(seg);
    }

    var segments = Array.prototype.slice.call(track.children);
    var countEl = document.getElementById('uc-count');
    var statusEl = document.getElementById('uc-status');

    function paint() {
        segments.forEach(function (seg, i) {
            seg.firstChild.classList.toggle('scale-x-0', i >= done);
            seg.firstChild.classList.toggle('scale-x-100', i < done);
        });

        countEl.innerHTML = done + ' <span class="text-white/50">of</span> '
                          + '<b class="text-mint">' + total + '</b> completed';

        if (done >= total) {
            statusEl.className = 'mt-5 rounded-[13px] px-4 py-3.5 text-[13px] leading-relaxed transition '
                               + 'border border-mint/35 bg-mint/15 text-mint';
            statusEl.textContent = '🔓 Unlocked — paid studies are now open to you.';
        } else {
            var left = total - done;
            statusEl.className = 'mt-5 rounded-[13px] px-4 py-3.5 text-[13px] leading-relaxed transition '
                               + 'border border-white/15 bg-white/10 text-white/85';
            statusEl.textContent = '🔒 ' + left + ' more volunteer '
                                 + (left === 1 ? 'study' : 'studies') + ' to unlock paid access.';
        }
    }

    document.getElementById('uc-plus').addEventListener('click', function () {
        done = Math.min(total, done + 1);
        paint();
    });

    document.getElementById('uc-minus').addEventListener('click', function () {
        done = Math.max(0, done - 1);
        paint();
    });

    paint();
})();
</script>
@endpush
