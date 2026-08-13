@extends('layouts.app')

@section('title', 'Participant profile')

@section('content')
{{--
    API-DRIVEN PAGE  (Member 4 — Smart Participant Matching)
    --------------------------------------------------------
    There is no Blade data in this file. Every name, attribute, score, factor
    and button state is fetched by resources/js/participant-profile.js from:

        GET  /api/v1/studies/{study}/candidates/{user}
        POST /api/v1/studies/{study}/invitations

    The only server values are the two route ids on the wrapper below. That
    is identity, not feature data — it tells the page WHO and WHICH STUDY to
    ask about. Everything it then displays comes from the API.

    The controller behind this page passes nothing to the view; it only
    decides whether the page exists at all.
--}}
<div class="wrap pb-20"
     id="candidate-profile-page"
     data-study-id="{{ $study->id }}"
     data-user-id="{{ $participant->id }}">

    <x-page-header eyebrow="Researcher · Participant profile" title="Candidate profile">
        <x-btn href="{{ route('studies.show', $study) }}" variant="ghost">Back to study</x-btn>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <x-panel label="Profile details" class="reveal">
            <div class="space-y-5">
                <div class="flex items-start gap-4">
                    <div data-avatar></div>
                    <div>
                        <h2 class="font-display text-2xl font-semibold text-ink" data-name>Loading…</h2>
                        <p class="mt-1 text-[13.5px] text-dim" data-location></p>
                        <div class="mt-3 flex flex-wrap gap-2" data-profile-badges></div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2" data-profile-stats></div>

                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Skills</div>
                    <p class="mt-2 text-[13.5px] leading-relaxed text-ink" data-skills>—</p>
                </div>

                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Interests</div>
                    <p class="mt-2 text-[13.5px] leading-relaxed text-ink" data-interests>—</p>
                </div>
            </div>
        </x-panel>

        <div class="space-y-6">
            <x-panel label="Match with current study" class="reveal reveal-d1">
                <div class="space-y-3 text-[13.5px] text-dim">
                    <p data-match-headline>Loading match…</p>

                    {{-- The eight weighted factors. Their contributions add up
                         to the match score, so the arithmetic is checkable on
                         screen rather than taken on trust. --}}
                    <div class="space-y-2" data-match-factors></div>

                    <div class="flex flex-wrap gap-2" data-match-reasons></div>

                    <div data-invite-block></div>
                    <div data-invite-alert></div>
                </div>
            </x-panel>

            <x-panel label="Recent activity" class="reveal reveal-d2">
                <div class="space-y-3" data-history>
                    <p class="text-[13.5px] text-dim">Loading…</p>
                </div>
            </x-panel>
        </div>
    </div>
</div>
@endsection
