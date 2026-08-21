{{--
    What this study requires, and which of it you meet  (Member 4)

    THIS PARTIAL SHIPS EMPTY ON PURPOSE.

    Every requirement, tick and cross comes from
        GET /api/v1/studies/{study}/requirements
    which reads study_match_criteria and diffs it against the participant's
    own profile. Rendered by resources/js/requirements.js.

    WHY IT EXISTS: the skill-gap coach names skills a participant is missing,
    and with nothing on screen showing what a study actually asks for, that
    looks like it could have been invented. This shows both sides of the diff
    in the same place — required skills, and whether this person has each one.

    Renders nothing for anyone who is not a participant.
--}}
@auth
    @if (auth()->user()->role === \App\Enums\UserRole::PARTICIPANT)
        <div data-requirements-block data-study-id="{{ $study->id }}">
            <p class="text-[12.5px] text-dim" data-requirements-body>Loading requirements…</p>
        </div>
    @endif
@endauth
