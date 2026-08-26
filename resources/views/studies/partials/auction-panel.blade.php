{{--
    FEATURE — Limited Seat Auctions (Member 3)

    Expects $study and $user in scope. Renders nothing if the study isn't
    in auction mode. Include in resources/views/studies/show.blade.php:

        @include('studies.partials.auction-panel', ['study' => $study, 'user' => $user])

    Entirely API-driven by resources/js/seat-auction.js, which fetches
    GET /api/v1/studies/{study}/auction and toggles the participant/
    researcher blocks based on data-role.
--}}

@if ($study->auction_mode)
<x-panel label="Seat auction" class="reveal reveal-d2"
         data-auction-panel
         data-study-id="{{ $study->id }}"
         data-role="{{ $user->role->value }}">
    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full px-2.5 py-1 font-mono text-[10.5px]" data-auction-status-badge>—</span>
            <span class="text-[12px] text-steel" data-auction-meta>Loading…</span>
        </div>

        <div class="rounded-xl border border-line bg-surface-soft p-3.5 text-[12.5px] text-dim" data-auction-countdown-wrap>
            Closes in <b class="text-ink" data-auction-countdown>—</b>
        </div>

        {{-- Participant: apply button + my status --}}
        <div data-auction-participant-block class="hidden space-y-3">
            <div data-auction-my-status></div>
            <button type="button" data-auction-apply-btn
                    class="hidden w-full rounded-lg bg-plum px-3 py-2.5 text-[13px] font-semibold text-white transition hover:bg-plum/90">
                Apply for a seat
            </button>
            <p class="text-[11.5px] text-danger" data-auction-apply-note></p>
        </div>

        {{-- Researcher: manual close --}}
        <div data-auction-researcher-block class="hidden">
            <button type="button" data-auction-close-btn
                    class="rounded-lg bg-plum/12 px-3 py-1.5 font-mono text-[11px] text-plum">
                Close auction now
            </button>
        </div>

        <div>
            <div class="mb-2 font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Ranking</div>
            <div class="space-y-1.5" data-auction-ranking>Loading ranking…</div>
        </div>
    </div>
</x-panel>
@endif