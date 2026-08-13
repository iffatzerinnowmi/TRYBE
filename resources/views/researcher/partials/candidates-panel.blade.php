{{--
    Smart Participant Matching — suggested candidates panel  (Member 4)

    THIS FILE SHIPS EMPTY ON PURPOSE.

    Every candidate, score, reason, criteria value and button state arrives
    as JSON from
        GET /api/v1/studies/{study}/candidates
        GET /api/v1/studies/{study}/match-criteria
        GET /api/v1/topics
    and is rendered by resources/js/candidates.js. Saving criteria is
        PUT /api/v1/studies/{study}/match-criteria

    The only Blade value here is $study->id, which is identity, not data —
    the same category as data-user-id on the reliability page. Nothing is
    passed to this partial from a controller.

    To prove it is API-driven during evaluation: open the Network tab,
    reload, and watch the JSON requests arrive after the HTML.
--}}
@php
    // Local style constants only — no data. Kept here so the editor matches
    // the inputs on the "post a study" form without duplicating long
    // class strings on every field.
    $input = 'w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink';
    $label = 'block font-mono text-[10px] uppercase tracking-[0.14em] text-steel mb-1';
@endphp

<div class="mt-4 rounded-xl border border-line bg-surface-soft p-4"
     data-candidates-panel
     data-study-id="{{ $study->id }}">

    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <div class="font-mono text-[10.5px] uppercase tracking-[0.14em] text-steel">
                Suggested participants
            </div>
            <p class="mt-1 text-[12.5px] text-dim" data-criteria-summary>Loading matches…</p>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <span class="font-mono text-[10.5px] text-steel" data-seat-count></span>
            <button type="button"
                    class="rounded-lg border border-line px-2.5 py-1 font-mono text-[10.5px] text-steel
                           transition hover:border-plum hover:text-plum"
                    data-edit-criteria>Edit criteria</button>
            <button type="button"
                    class="rounded-lg border border-line px-2.5 py-1 font-mono text-[10.5px] text-steel
                           transition hover:border-plum hover:text-plum"
                    data-refresh-candidates>Refresh</button>
        </div>
    </div>

    {{-- Shown only when the study has no study_match_criteria row, so the
         researcher knows the list is running on platform defaults rather
         than anything they chose. --}}
    <p class="mt-3 hidden rounded-lg bg-flame/10 px-3 py-2 text-[12px] text-flame"
       data-criteria-default>
        ⚠ No matching criteria set for this study yet — showing platform defaults.
        Use <b>Edit criteria</b> to tell the matcher who you actually need.
    </p>

    {{--
        THE CRITERIA EDITOR

        Eligibility criteria logically belong on the "post a study" form,
        which is Member 2's and does not collect them yet. This editor lets a
        researcher set them AFTER posting, with no change to her form.

        It is a plain <div>, not a <form>: there is no web POST route to
        submit to. The values are collected by JavaScript and sent as JSON to
        PUT /api/v1/studies/{study}/match-criteria.
    --}}
    <div class="mt-3 hidden rounded-xl border border-line bg-surface p-4" data-criteria-editor>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="{{ $label }}" for="age-min-{{ $study->id }}">Minimum age</label>
                <input type="number" min="0" max="120" class="{{ $input }}"
                       id="age-min-{{ $study->id }}" data-field="age_min" placeholder="Any">
            </div>

            <div>
                <label class="{{ $label }}" for="age-max-{{ $study->id }}">Maximum age</label>
                <input type="number" min="0" max="120" class="{{ $input }}"
                       id="age-max-{{ $study->id }}" data-field="age_max" placeholder="Any">
            </div>

            <div>
                <label class="{{ $label }}" for="location-{{ $study->id }}">Location contains</label>
                <input type="text" class="{{ $input }}"
                       id="location-{{ $study->id }}" data-field="location"
                       placeholder="Dhaka — leave blank for anywhere">
            </div>

            <div>
                <label class="{{ $label }}" for="credential-{{ $study->id }}">Minimum credential</label>
                <select class="{{ $input }}" id="credential-{{ $study->id }}" data-field="credential_min">
                    <option value="none">No minimum</option>
                    <option value="bronze">Bronze and above</option>
                    <option value="gold">Gold and above</option>
                    <option value="expert">Expert only</option>
                </select>
            </div>

            <div>
                <label class="{{ $label }}" for="skills-{{ $study->id }}">Required skills</label>
                <input type="text" class="{{ $input }}"
                       id="skills-{{ $study->id }}" data-field="required_skills"
                       placeholder="UI testing, Python — comma separated">
            </div>

            <div>
                <label class="{{ $label }}" for="availability-{{ $study->id }}">Active within (days)</label>
                <input type="number" min="1" max="365" class="{{ $input }}"
                       id="availability-{{ $study->id }}" data-field="availability_days"
                       placeholder="30">
            </div>
        </div>

        <div class="mt-3">
            <span class="{{ $label }}">Topics</span>
            <p class="mb-2 text-[11.5px] text-dim">
                Topics are the strongest signal in the score — a participant who has
                <b>completed</b> a study on the same topic ranks above one who only ticked the box.
            </p>
            <div class="flex flex-wrap gap-2" data-topic-options>
                <span class="text-[12px] text-dim">Loading topics…</span>
            </div>
        </div>

        <div data-criteria-alert></div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="button"
                    class="rounded-lg bg-plum/12 px-3 py-1.5 font-mono text-[11px] text-plum transition
                           hover:bg-plum/20 disabled:cursor-not-allowed disabled:opacity-50"
                    data-save-criteria>Save criteria</button>
            <button type="button"
                    class="rounded-lg border border-line px-3 py-1.5 font-mono text-[11px] text-steel
                           transition hover:border-plum hover:text-plum"
                    data-cancel-criteria>Cancel</button>
        </div>
    </div>

    <div class="mt-4 space-y-3" data-candidate-list>
        <p class="text-[12.5px] text-dim">Loading…</p>
    </div>
</div>
