{{--
    Complete a Study — the button  (Member 4)

    THIS PARTIAL SHIPS EMPTY ON PURPOSE.

    The label, the disabled state, the reason and the form URL all arrive from
        GET   /api/v1/studies/{study}/completion
    and the actions are
        POST  /api/v1/studies/{study}/completion            "I submitted it"
        PATCH /api/v1/studies/{study}/completion/opened     followed the link

    Rendered by resources/js/completion.js. The only Blade value is
    $study->id, which is identity rather than data (decision.md §4).

    THE MODAL IS NOT IN THIS FILE. There is exactly one modal per page, built
    by the JavaScript and appended to <body>. Rendering it per-button would
    duplicate ids across the twelve cards on the My studies page, and duplicate
    ids break both label/for pairs and focus management.

    Use:
        @include('participant.partials.complete-button', ['study' => $study])

    Renders nothing for anyone who is not a participant, so it is safe on a
    page a researcher also sees.
--}}
@auth
    @if (auth()->user()->role === \App\Enums\UserRole::PARTICIPANT)
        <div class="mt-3" data-completion-block
             data-study-id="{{ $study->id }}"
             data-study-title="{{ $study->title }}">

            <div data-completion-control>
                <button type="button" disabled
                        class="rounded-lg bg-steel/12 px-3.5 py-2 font-mono text-[11.5px] text-steel">
                    Checking…
                </button>
            </div>

            <p class="mt-1.5 text-[12px] text-dim" data-completion-note></p>
        </div>
    @endif
@endauth
