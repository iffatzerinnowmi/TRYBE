{{--
    Recommended for you — dashboard preview  (Member 4)

    THIS FILE SHIPS EMPTY ON PURPOSE.

    It fetches GET /api/v1/participants/me/feed?limit=4, which is the SAME
    endpoint and the same ranking as the full feed page. The dashboard preview
    and the feed can therefore never disagree — by construction, not by
    discipline.

    WHAT THIS REPLACED
    ------------------
    ParticipantDashboardController used to call
    StudyMatchingService::recommendStudiesForParticipant() and pass
    $recommended into the view. That broke team rule 4 ("no controller passes
    data to a view"), and it was my service being called, so it was my mess.

    Nothing is passed to this partial from a controller.
--}}
<x-panel label="Recommended for you" class="reveal reveal-d2">
    <x-slot:action>
        <a href="{{ route('participant.feed') }}">See all →</a>
    </x-slot:action>

    <div data-recommended-preview>
        <p class="py-3 text-[13.5px] text-dim">Loading recommendations…</p>
    </div>
</x-panel>
