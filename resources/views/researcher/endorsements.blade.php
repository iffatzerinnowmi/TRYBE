@extends('layouts.app')

@section('title', 'Endorsements')

@section('content')
<div class="wrap pb-20">

    <x-page-header
        eyebrow="Researcher · Endorsements"
        title="Give credit where it's due."
        subtitle="After a session wraps, vouch for the participant with a couple of quick tags. Once {{ config('platform.endorsements_for_verified_badge') }} different researchers endorse someone, they earn the Verified Participant badge — so your endorsement genuinely counts." />

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @error('tags')
        <x-alert type="error" class="mb-6">{{ $message }}</x-alert>
    @enderror

    <div class="grid items-start gap-6 lg:grid-cols-[1.5fr_1fr]">

        {{-- ================= LEFT ================= --}}
        <div class="space-y-6">

            @if ($selected)
                <x-panel label="Endorse a participant"
                         note="You completed a session with this participant."
                         class="reveal">

                    <div class="mb-5 flex items-center gap-3.5 rounded-xl border border-line bg-surface-soft p-4">
                        <x-avatar :name="$selected->participant->name" size="lg" />
                        <div class="min-w-0">
                            <div class="text-[15px] font-semibold text-ink">
                                {{ $selected->participant->name }}
                            </div>
                            <div class="text-[12.5px] text-dim">
                                {{ $selected->stage->label() }} ·
                                “{{ $selected->study->title }}”
                                @if ($selected->completed_at)
                                    · {{ $selected->completed_at->diffForHumans() }}
                                @endif
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('researcher.endorsements.store') }}" id="endorse-form">
                        @csrf
                        <input type="hidden" name="participant_id" value="{{ $selected->participant_id }}">
                        <input type="hidden" name="study_id" value="{{ $selected->study_id }}">

                        <p class="mb-3.5 text-[13px] font-semibold text-dim">
                            Choose the tags that fit — pick up to {{ $maxTags }}:
                        </p>

                        {{-- Each tag is a real checkbox, styled as a chip. No
                             JavaScript is needed for the form to submit. --}}
                        <div class="flex flex-wrap gap-2.5">
                            @foreach ($allTags as $tag)
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="tags[]" value="{{ $tag }}"
                                           class="peer sr-only" data-tag>
                                    <span class="inline-block rounded-full border border-line-hi px-4 py-2
                                                 text-[13px] text-ink transition
                                                 peer-checked:border-plum peer-checked:bg-plum
                                                 peer-checked:text-white hover:border-plum">
                                        {{ $tag }}
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <p id="pick-note" class="mt-3.5 font-mono text-[11.5px] text-steel">
                            No tags selected yet.
                        </p>

                        <div class="mt-5 border-t border-line pt-5">
                            <x-btn type="submit" id="endorse-submit">Submit endorsement</x-btn>
                        </div>
                    </form>
                </x-panel>
            @else
                <x-panel label="Endorse a participant" class="reveal">
                    <p class="py-3 text-[13.5px] text-dim">
                        Nobody is waiting for your endorsement right now. Participants appear
                        here once you mark one of your sessions completed.
                    </p>
                </x-panel>
            @endif

            <x-panel label="Also awaiting your endorsement"
                     note="Other participants from your recently completed sessions."
                     class="reveal reveal-d1">

                @forelse ($pending->skip(1)->take(6) as $session)
                    <div class="flex items-center gap-3.5 border-b border-line py-3.5 last:border-none last:pb-1">
                        <x-avatar :name="$session->participant->name" size="sm" />

                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[13.5px] font-semibold text-ink">
                                {{ $session->participant->name }}
                            </div>
                            <div class="truncate font-mono text-[11px] text-steel">
                                “{{ $session->study->title }}”
                                @if ($session->completed_at)
                                    · {{ $session->completed_at->diffForHumans() }}
                                @endif
                            </div>
                        </div>

                        <x-btn variant="soft" size="sm"
                               href="{{ route('researcher.endorsements', ['participant' => $session->participant_id]) }}">
                            Endorse
                        </x-btn>
                    </div>
                @empty
                    <p class="py-3 text-[13.5px] text-dim">Nobody else in the queue.</p>
                @endforelse
            </x-panel>

            <x-panel label="Endorsements you've given" class="reveal reveal-d2">
                @forelse ($given as $endorsement)
                    <div class="flex flex-wrap items-center gap-3 border-b border-line py-3 last:border-none last:pb-1">
                        <span class="text-[13.5px] font-semibold text-ink">
                            {{ $endorsement->participant->name }}
                        </span>

                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($endorsement->tags as $tag)
                                <x-badge tone="plum">{{ $tag }}</x-badge>
                            @endforeach
                        </div>

                        <span class="ml-auto font-mono text-[11px] text-steel">
                            {{ $endorsement->created_at->diffForHumans() }}
                        </span>
                    </div>
                @empty
                    <p class="py-3 text-[13.5px] text-dim">You haven't endorsed anyone yet.</p>
                @endforelse
            </x-panel>
        </div>

        {{-- ================= RIGHT: their standing ================= --}}
        <div class="space-y-6">
            @if ($standing)
                @php
                    $r = 66;
                    $c = 2 * M_PI * $r;
                    $pct = min(100, $standing['count'] / max(1, $standing['required']) * 100);
                    $off = $c * (1 - $pct / 100);
                @endphp

                <div class="reveal reveal-d1 relative overflow-hidden rounded-panel bg-gradient-to-br
                            from-ink to-plum p-7 text-center text-white
                            shadow-[0_20px_46px_-22px_rgba(79,58,101,.7)]">

                    <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full"
                         style="background:radial-gradient(circle, rgba(223,240,234,.2), transparent 70%);"></div>

                    <div class="relative">
                        <p class="font-mono text-[10.5px] uppercase tracking-[0.2em] text-steel">
                            {{ explode(' ', $standing['participant']->name)[0] }}'s standing
                        </p>

                        <div class="relative mx-auto mt-5 h-[150px] w-[150px]">
                            <svg width="150" height="150" class="-rotate-90">
                                <circle cx="75" cy="75" r="{{ $r }}" fill="none"
                                        stroke="rgba(255,255,255,.14)" stroke-width="10" />
                                <circle cx="75" cy="75" r="{{ $r }}" fill="none"
                                        stroke="#DFF0EA" stroke-width="10" stroke-linecap="round"
                                        stroke-dasharray="{{ $c }}" stroke-dashoffset="{{ $off }}" />
                            </svg>

                            <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-center">
                                <div class="font-display text-[34px] font-semibold leading-none text-white">
                                    {{ $standing['count'] }}
                                </div>
                                <div class="font-mono text-[10.5px] text-steel">
                                    OF {{ $standing['required'] }}
                                </div>
                            </div>
                        </div>

                        <h3 class="mt-4 font-display text-[18px] font-semibold text-white">
                            @if ($standing['verified'])
                                Verified Participant
                            @elseif ($standing['remaining'] === 1)
                                1 endorsement to go
                            @else
                                {{ $standing['remaining'] }} endorsements to go
                            @endif
                        </h3>

                        <span class="mt-3 inline-block rounded-full px-3.5 py-1.5 font-mono
                                     text-[10.5px] uppercase tracking-wider
                                     {{ $standing['verified']
                                        ? 'bg-mint text-ink'
                                        : 'bg-white/15 text-white/70' }}">
                            ◆ Verified Participant —
                            {{ $standing['verified'] ? 'earned' : 'locked' }}
                        </span>

                        @if ($standing['tags']->isNotEmpty())
                            <div class="mt-5 border-t border-white/15 pt-4">
                                <p class="font-mono text-[10px] uppercase tracking-[0.16em] text-steel">
                                    Endorsements received
                                </p>
                                <div class="mt-2.5 flex flex-wrap justify-center gap-1.5">
                                    @foreach ($standing['tags'] as $tag => $times)
                                        <span class="rounded-full bg-white/15 px-2.5 py-1 text-[11px] text-white">
                                            {{ $tag }} @if ($times > 1) ×{{ $times }} @endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <x-panel label="How the badge works" class="reveal reveal-d2">
                <p class="text-[13px] leading-relaxed text-dim">
                    The counter is <b class="text-ink">distinct researchers</b>, not total
                    endorsements. Endorsing the same participant for three different
                    sessions still counts as one researcher.
                </p>
                <p class="mt-3 text-[13px] leading-relaxed text-dim">
                    That's what stops the badge being farmed, and it's why
                    <code class="font-mono text-[12px] text-plum">distinct('researcher_id')</code>
                    matters more than it looks.
                </p>
            </x-panel>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
/* Limits the picker to three tags and updates the note underneath.
   The server enforces the same limit — this is only a courtesy. */
(function () {
    var max = {{ $maxTags }};
    var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-tag]'));
    var note = document.getElementById('pick-note');

    if (!boxes.length || !note) return;

    function update() {
        var chosen = boxes.filter(function (b) { return b.checked; });

        boxes.forEach(function (b) {
            b.disabled = !b.checked && chosen.length >= max;
            b.parentElement.style.opacity = b.disabled ? 0.4 : 1;
        });

        note.textContent = chosen.length === 0
            ? 'No tags selected yet.'
            : chosen.length + ' of ' + max + ' selected: '
              + chosen.map(function (b) { return b.value; }).join(', ');
    }

    boxes.forEach(function (b) { b.addEventListener('change', update); });
    update();
})();
</script>
@endpush
