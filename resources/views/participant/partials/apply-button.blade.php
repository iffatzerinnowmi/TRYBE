{{--
    Apply to Study — the button  (Member 4)

    THIS PARTIAL SHIPS EMPTY ON PURPOSE.

    Every label, every disabled state and every reason arrives as JSON from
        GET  /api/v1/studies/{study}/apply-status
    and applying is
        POST /api/v1/studies/{study}/apply

    Rendered by resources/js/apply.js. The only Blade value is $study->id,
    which is identity rather than data — the same category as data-study-id on
    the candidates panel. Nothing is passed to this partial by a controller
    (decision.md §4).

    Drop it anywhere a study is shown:
        @include('participant.partials.apply-button', ['study' => $study])

    It renders nothing at all for anyone who is not a participant, so it is
    safe to include on a page a researcher also sees.
--}}
@auth
    @if (auth()->user()->role === \App\Enums\UserRole::PARTICIPANT)
        <div class="mt-3" data-apply-block data-study-id="{{ $study->id }}">
            <div data-apply-control>
                <button type="button" disabled
                        class="rounded-lg bg-steel/12 px-3.5 py-2 font-mono text-[11.5px] text-steel">
                    Checking…
                </button>
            </div>

            {{-- Reason, seats, and any error. Filled by apply.js. --}}
            <p class="mt-1.5 text-[12px] text-dim" data-apply-note></p>
        </div>
    @endif
@endauth
