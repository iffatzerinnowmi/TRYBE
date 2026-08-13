@extends('layouts.app')

@section('title', 'Endorsements')

@section('content')
{{--
    API-DRIVEN PAGE — same pattern as participant/credentials.blade.php.

    There is no Blade data on this page. Every name, tag, number and sentence
    below arrives as JSON:

        GET  /api/v1/endorsements/pending              -> queue, given, limits
        GET  /api/v1/participants/{id}/endorsements    -> one person's standing
        POST /api/v1/endorsements                      -> record one

    Two things are different from the read-only pages you converted before:

    1. This page WRITES. The submit button is a fetch(), not a form. A 422
       from the API is shown next to the tag picker instead of reloading.

    2. Choosing a different person in the queue re-fetches only the standing
       panel. Nothing navigates. The old ?participant= link reloaded the
       whole page; it still works as a deep link, but it is now read once on
       load and never used again.
--}}
<div class="wrap pb-20" id="endorsements-page">

    <x-page-header
        eyebrow="Researcher · Endorsements"
        title="Give credit where it's due."
        subtitle="After a session wraps, vouch for the participant with a couple of quick tags. Once enough different researchers endorse someone, they earn the Verified Participant badge — so your endorsement genuinely counts." />

    <p class="-mt-2 mb-6 font-mono text-[11.5px] text-steel">
        Badge threshold: <span id="required-inline">—</span> distinct researchers.
    </p>

    <div id="page-alert" class="mb-6 hidden rounded-xl px-4 py-3 text-[13px]"></div>

    <div class="grid items-start gap-6 lg:grid-cols-[1.5fr_1fr]">

        {{-- ================= LEFT ================= --}}
        <div class="space-y-6">

            <x-panel label="Endorse a participant"
                     note="You completed a session with this participant."
                     class="reveal">

                <p id="endorse-loading" class="py-3 text-[13.5px] text-dim">
                    Loading your queue…
                </p>

                <p id="endorse-empty" class="hidden py-3 text-[13.5px] text-dim">
                    Nobody is waiting for your endorsement right now. Participants appear
                    here once you mark one of your sessions completed.
                </p>

                <div id="endorse-form" class="hidden">

                    <div class="mb-5 flex items-center gap-3.5 rounded-xl border border-line bg-surface-soft p-4">
                        <span id="selected-avatar"
                              class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-full
                                     bg-gradient-to-br from-plum to-steel text-[20px] font-semibold text-white"></span>
                        <div class="min-w-0">
                            <div id="selected-name" class="text-[15px] font-semibold text-ink"></div>
                            <div id="selected-meta" class="text-[12.5px] text-dim"></div>
                        </div>
                    </div>

                    <p class="mb-3.5 text-[13px] font-semibold text-dim">
                        Choose the tags that fit — pick up to <span id="max-tags">—</span>:
                    </p>

                    <div id="tag-list" class="flex flex-wrap gap-2.5"></div>

                    <p id="pick-note" class="mt-3.5 font-mono text-[11.5px] text-steel">
                        No tags selected yet.
                    </p>

                    <p id="form-error" class="mt-3 hidden rounded-lg bg-danger/10 px-3 py-2 text-[12.5px] text-danger"></p>

                    <div class="mt-5 border-t border-line pt-5">
                        <button type="button" id="endorse-submit" disabled
                                class="rounded-xl bg-plum px-5 py-3 text-sm font-semibold text-white transition
                                       hover:-translate-y-px disabled:cursor-not-allowed disabled:opacity-50">
                            Submit endorsement
                        </button>
                    </div>
                </div>
            </x-panel>

            <x-panel label="Also awaiting your endorsement"
                     note="Other participants from your recently completed sessions."
                     class="reveal reveal-d1">
                <div id="queue-list">
                    <p class="py-3 text-[13.5px] text-dim">Loading…</p>
                </div>
            </x-panel>

            <x-panel label="Endorsements you've given" class="reveal reveal-d2">
                <div id="given-list">
                    <p class="py-3 text-[13.5px] text-dim">Loading…</p>
                </div>
            </x-panel>
        </div>

        {{-- ================= RIGHT: their standing ================= --}}
        <div class="space-y-6">

            {{-- The wrapper carries the id rather than <x-panel>, because a
                 component may or may not forward stray attributes. --}}
            <div id="standing-placeholder">
                <x-panel label="Standing" class="reveal reveal-d1">
                    <p class="py-3 text-[13.5px] text-dim">
                        Pick someone from your queue to see how close they are to the badge.
                    </p>
                </x-panel>
            </div>

            {{-- Deliberately NOT a .reveal element. Reveal starts at opacity 0
                 and waits for the element to scroll into view; something that
                 begins life hidden never triggers the observer and would stay
                 invisible forever after we un-hide it. --}}
            <div id="standing-card"
                 class="relative hidden overflow-hidden rounded-panel bg-gradient-to-br
                        from-ink to-plum p-7 text-center text-white
                        shadow-[0_20px_46px_-22px_rgba(79,58,101,.7)]">

                <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full"
                     style="background:radial-gradient(circle, rgba(223,240,234,.2), transparent 70%);"></div>

                <div class="relative">
                    <p id="standing-owner" class="font-mono text-[10.5px] uppercase tracking-[0.2em] text-steel">
                        &nbsp;
                    </p>

                    <div class="relative mx-auto mt-5 h-[150px] w-[150px]">
                        <svg width="150" height="150" class="-rotate-90">
                            <circle cx="75" cy="75" r="66" fill="none"
                                    stroke="rgba(255,255,255,.14)" stroke-width="10" />
                            <circle id="standing-ring" cx="75" cy="75" r="66" fill="none"
                                    stroke="#DFF0EA" stroke-width="10" stroke-linecap="round"
                                    stroke-dasharray="414.69" stroke-dashoffset="414.69"
                                    style="transition: stroke-dashoffset .7s ease-out" />
                        </svg>

                        <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-center">
                            <div id="standing-count"
                                 class="font-display text-[34px] font-semibold leading-none text-white">—</div>
                            <div id="standing-of" class="font-mono text-[10.5px] text-steel">—</div>
                        </div>
                    </div>

                    <h3 id="standing-headline" class="mt-4 font-display text-[18px] font-semibold text-white">&nbsp;</h3>

                    <span id="standing-badge"
                          class="mt-3 inline-block rounded-full bg-white/15 px-3.5 py-1.5 font-mono
                                 text-[10.5px] uppercase tracking-wider text-white/70"></span>

                    <div id="standing-tagwrap" class="mt-5 hidden border-t border-white/15 pt-4">
                        <p class="font-mono text-[10px] uppercase tracking-[0.16em] text-steel">
                            Endorsements received
                        </p>
                        <div id="standing-tags" class="mt-2.5 flex flex-wrap justify-center gap-1.5"></div>
                    </div>
                </div>
            </div>

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
document.addEventListener('DOMContentLoaded', function () {

    var page = document.getElementById('endorsements-page');
    if (!page) { return; }

    /* ------------------------------------------------------------------
       Endpoints
    ------------------------------------------------------------------ */
    var PENDING_URL = '/api/v1/endorsements/pending';
    var STORE_URL   = '/api/v1/endorsements';

    function standingUrl(participantId) {
        return '/api/v1/participants/' + participantId + '/endorsements';
    }

    var RING_LENGTH = 2 * Math.PI * 66;   // matches r="66" on the svg circle

    /* ------------------------------------------------------------------
       Everything the page knows lives here. Nothing is read back out of
       the DOM, so there is one copy of the truth.
    ------------------------------------------------------------------ */
    var state = {
        pending:  [],
        given:    [],
        tags:     [],
        maxTags:  0,
        required: 0,
        selected: null,   // one row from state.pending
        chosen:   [],     // tags ticked right now
        done:     false   // true once this selection has been endorsed
    };

    function $(id) { return document.getElementById(id); }

    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* Rebuild of x-badge. Full class strings only — Tailwind cannot find a
       class name that was assembled by joining pieces together. */
    function badge(tone, text) {
        var tones = {
            ok:      'bg-ok/12 text-ok',
            plum:    'bg-plum/12 text-plum',
            neutral: 'bg-steel/18 text-steel'
        };
        var cls = tones[tone] || tones.neutral;

        return '<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-1 '
             + 'font-mono text-[10.5px] tracking-wide ' + cls + '">' + esc(text) + '</span>';
    }

    /* Rebuild of x-avatar, small size. Initials come from the API. */
    function avatar(initials) {
        return '<span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full '
             + 'bg-gradient-to-br from-plum to-steel text-[12px] font-semibold text-white">'
             + esc(initials) + '</span>';
    }

    /* ------------------------------------------------------------------
       Banners
    ------------------------------------------------------------------ */
    function alertBox(message, ok) {
        var box = $('page-alert');
        box.className = 'mb-6 rounded-xl px-4 py-3 text-[13px] '
                      + (ok ? 'bg-ok/10 text-ok' : 'bg-danger/10 text-danger');
        box.textContent = message;
    }

    function hideAlert() {
        $('page-alert').className = 'mb-6 hidden rounded-xl px-4 py-3 text-[13px]';
    }

    function formError(message) {
        var box = $('form-error');
        box.textContent = message;
        box.classList.remove('hidden');
    }

    function hideFormError() {
        $('form-error').classList.add('hidden');
    }

    /* A 422 carries field-by-field errors; anything else carries a message. */
    function errorText(error) {
        if (error && error.errors) {
            for (var field in error.errors) {
                if (error.errors[field] && error.errors[field].length) {
                    return error.errors[field][0];
                }
            }
        }
        return (error && error.message) ? error.message : 'Something went wrong.';
    }

    /* ------------------------------------------------------------------
       The tag picker
    ------------------------------------------------------------------ */
    function renderTags() {
        var atLimit = state.chosen.length >= state.maxTags;
        var html = '';

        state.tags.forEach(function (tag) {
            var isChosen = state.chosen.indexOf(tag) > -1;
            var locked   = state.done || (!isChosen && atLimit);

            var cls = isChosen
                ? 'border-plum bg-plum text-white'
                : (locked
                    ? 'border-line text-steel opacity-40 cursor-not-allowed'
                    : 'border-line-hi text-ink hover:border-plum cursor-pointer');

            html += '<button type="button" data-tag="' + esc(tag) + '"'
                  + (locked ? ' disabled' : '')
                  + ' class="inline-block rounded-full border px-4 py-2 text-[13px] transition '
                  + cls + '">' + esc(tag) + '</button>';
        });

        $('tag-list').innerHTML = html;

        $('pick-note').textContent = state.chosen.length === 0
            ? 'No tags selected yet.'
            : state.chosen.length + ' of ' + state.maxTags + ' selected: '
              + state.chosen.join(', ');

        $('endorse-submit').disabled = state.done || state.chosen.length === 0;
    }

    $('tag-list').addEventListener('click', function (event) {
        var button = event.target.closest('[data-tag]');
        if (!button || state.done) { return; }

        var tag = button.dataset.tag;
        var at  = state.chosen.indexOf(tag);

        if (at > -1) {
            state.chosen.splice(at, 1);
        } else if (state.chosen.length < state.maxTags) {
            state.chosen.push(tag);
        }

        hideFormError();
        renderTags();
    });

    /* ------------------------------------------------------------------
       The three lists
    ------------------------------------------------------------------ */
    function renderSelected() {
        if (!state.selected) {
            $('endorse-form').classList.add('hidden');
            $('endorse-empty').classList.remove('hidden');
            return;
        }

        var session = state.selected;

        $('endorse-empty').classList.add('hidden');
        $('endorse-form').classList.remove('hidden');

        $('selected-avatar').textContent = session.initials;
        $('selected-name').textContent   = session.participant_name;

        var meta = session.stage_label + ' · “' + session.study_title + '”';
        if (session.completed_ago) { meta += ' · ' + session.completed_ago; }
        $('selected-meta').textContent = meta;

        $('endorse-submit').textContent = 'Submit endorsement';
        renderTags();
    }

    function renderQueue() {
        var others = state.pending.filter(function (session) {
            return !state.selected || session.participation_id !== state.selected.participation_id;
        }).slice(0, 6);

        if (others.length === 0) {
            $('queue-list').innerHTML =
                '<p class="py-3 text-[13.5px] text-dim">Nobody else in the queue.</p>';
            return;
        }

        var html = '';

        others.forEach(function (session) {
            var meta = '“' + esc(session.study_title) + '”';
            if (session.completed_ago) { meta += ' · ' + esc(session.completed_ago); }

            html +=
              '<div class="flex items-center gap-3.5 border-b border-line py-3.5 last:border-none last:pb-1">'
            +   avatar(session.initials)
            +   '<div class="min-w-0 flex-1">'
            +     '<div class="truncate text-[13.5px] font-semibold text-ink">'
            +       esc(session.participant_name)
            +     '</div>'
            +     '<div class="truncate font-mono text-[11px] text-steel">' + meta + '</div>'
            +   '</div>'
            +   '<button type="button" data-pick="' + session.participation_id + '" '
            +     'class="rounded-lg bg-plum/10 px-3 py-1.5 text-[12.5px] font-semibold '
            +     'text-plum transition hover:bg-plum/20">Endorse</button>'
            + '</div>';
        });

        $('queue-list').innerHTML = html;
    }

    $('queue-list').addEventListener('click', function (event) {
        var button = event.target.closest('[data-pick]');
        if (!button) { return; }

        select(parseInt(button.dataset.pick, 10));
    });

    function renderGiven() {
        if (state.given.length === 0) {
            $('given-list').innerHTML =
                '<p class="py-3 text-[13.5px] text-dim">You haven\'t endorsed anyone yet.</p>';
            return;
        }

        var html = '';

        state.given.forEach(function (entry) {
            var chips = '';
            entry.tags.forEach(function (tag) { chips += badge('plum', tag); });

            html +=
              '<div class="flex flex-wrap items-center gap-3 border-b border-line py-3 last:border-none last:pb-1">'
            +   '<span class="text-[13.5px] font-semibold text-ink">'
            +     esc(entry.participant_name)
            +   '</span>'
            +   '<div class="flex flex-wrap gap-1.5">' + chips + '</div>'
            +   '<span class="ml-auto font-mono text-[11px] text-steel">'
            +     esc(entry.given_ago)
            +   '</span>'
            + '</div>';
        });

        $('given-list').innerHTML = html;
    }

    /* ------------------------------------------------------------------
       The standing panel — its own endpoint, its own render
    ------------------------------------------------------------------ */
    function renderStanding(data) {
        $('standing-placeholder').classList.add('hidden');
        $('standing-card').classList.remove('hidden');

        $('standing-owner').textContent    = data.first_name + '\u2019s standing';
        $('standing-count').textContent    = data.count;
        $('standing-of').textContent       = 'OF ' + data.required;
        $('standing-headline').textContent = data.headline;

        $('standing-ring').setAttribute(
            'stroke-dashoffset', RING_LENGTH * (1 - data.progress_percent / 100)
        );

        var pill = $('standing-badge');
        pill.textContent = data.badge_text;
        pill.className = 'mt-3 inline-block rounded-full px-3.5 py-1.5 font-mono '
                       + 'text-[10.5px] uppercase tracking-wider '
                       + (data.verified ? 'bg-mint text-ink' : 'bg-white/15 text-white/70');

        if (data.tags.length === 0) {
            $('standing-tagwrap').classList.add('hidden');
            return;
        }

        var chips = '';

        data.tags.forEach(function (entry) {
            chips += '<span class="rounded-full bg-white/15 px-2.5 py-1 text-[11px] text-white">'
                   + esc(entry.tag)
                   + (entry.times > 1 ? ' ×' + entry.times : '')
                   + '</span>';
        });

        $('standing-tags').innerHTML = chips;
        $('standing-tagwrap').classList.remove('hidden');
    }

    function loadStanding(participantId) {
        return api.get(standingUrl(participantId))
            .then(function (response) { renderStanding(response.data); })
            .catch(function (error) { alertBox(errorText(error), false); });
    }

    /* ------------------------------------------------------------------
       Selecting somebody
    ------------------------------------------------------------------ */
    function select(participationId) {
        var found = null;

        state.pending.forEach(function (session) {
            if (session.participation_id === participationId) { found = session; }
        });

        state.selected = found || state.pending[0] || null;
        state.chosen = [];
        state.done = false;

        hideAlert();
        hideFormError();
        renderSelected();
        renderQueue();

        if (state.selected) {
            loadStanding(state.selected.participant_id);
        }
    }

    /* ------------------------------------------------------------------
       Loading
    ------------------------------------------------------------------ */
    function applyQueue(data) {
        state.pending  = data.pending;
        state.given    = data.given;
        state.tags     = data.available_tags;
        state.maxTags  = data.max_tags;
        state.required = data.required;

        $('required-inline').textContent = data.required;
        $('max-tags').textContent = data.max_tags;
        $('endorse-loading').classList.add('hidden');
    }

    /* Deep link: /researcher/endorsements?participant=7 still works. It is
       read once here and never again — after this, picking someone changes
       nothing in the address bar and reloads nothing. */
    function requestedParticipantId() {
        var raw = new URLSearchParams(window.location.search).get('participant');
        return raw ? parseInt(raw, 10) : null;
    }

    api.get(PENDING_URL)
        .then(function (response) {
            applyQueue(response.data);

            var wanted = requestedParticipantId();
            var first  = state.pending[0] || null;

            if (wanted) {
                state.pending.forEach(function (session) {
                    if (session.participant_id === wanted) { first = session; }
                });
            }

            select(first ? first.participation_id : -1);
            renderGiven();
        })
        .catch(function (error) {
            $('endorse-loading').textContent = 'Could not load your queue.';
            $('queue-list').innerHTML = '';
            $('given-list').innerHTML = '';
            alertBox(errorText(error), false);
        });

    /* ------------------------------------------------------------------
       Submitting — the only write on this page
    ------------------------------------------------------------------ */
    $('endorse-submit').addEventListener('click', function () {
        if (!state.selected || state.chosen.length === 0 || state.done) { return; }

        var button = this;

        button.disabled = true;
        button.textContent = 'Submitting…';
        hideAlert();
        hideFormError();

        api.post(STORE_URL, {
            participant_id: state.selected.participant_id,
            study_id:       state.selected.study_id,
            tags:           state.chosen
        })
        .then(function (response) {
            state.done = true;

            renderStanding(response.data);
            alertBox(response.message, true);

            button.textContent = 'Endorsed ✓';
            button.disabled = true;
            renderTags();

            // Refresh the queue and the given list so the session that was
            // just endorsed disappears. The form above stays on the person
            // you endorsed, so you can watch the ring you just moved.
            return api.get(PENDING_URL).then(function (fresh) {
                applyQueue(fresh.data);
                renderQueue();
                renderGiven();
            });
        })
        .catch(function (error) {
            formError(errorText(error));
            button.textContent = 'Submit endorsement';
            button.disabled = state.chosen.length === 0;
        });
    });
});
</script>
@endpush