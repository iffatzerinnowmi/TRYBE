/*
|------------------------------------------------------------------------------
| TRYBE — the study detail page's matching panels  (Member 4)
|------------------------------------------------------------------------------
|
| Two independent bits of the study page, both API-driven:
|
|   [data-study-criteria]  the "Matching criteria" summary — shown to everyone
|   [data-study-match]     "Your match" + accept/decline — participants only
|
|     GET   /api/v1/participants/me/matched-studies
|     GET   /api/v1/invitations
|     PATCH /api/v1/invitations/{id}
|
| The participant's own score comes from the matched-studies endpoint rather
| than a per-study one, because that endpoint already scores every open study
| against them with the same service — one call instead of a new endpoint
| that would duplicate it.
|
| Conventions §7: DOMContentLoaded wrapper, shared `api` helper (never
| forked), esc() on everything from the database, full Tailwind class names.
*/

document.addEventListener('DOMContentLoaded', function () {

    var criteriaBox = document.querySelector('[data-study-criteria]');
    var matchBox    = document.querySelector('[data-study-match]');

    if (!criteriaBox && !matchBox) {
        return;
    }

    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    var BADGE_TONES = {
        plum:    'bg-plum/12 text-plum',
        ok:      'bg-ok/12 text-ok',
        danger:  'bg-danger/12 text-danger',
        neutral: 'bg-steel/18 text-steel',
    };

    function badge(tone, text) {
        var cls = BADGE_TONES[tone] || BADGE_TONES.neutral;

        return '<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-1 '
             + 'font-mono text-[10.5px] tracking-wide ' + cls + '">' + esc(text) + '</span>';
    }

    /* ------------------------------------------------------------------
       Matching criteria — the researcher-owned endpoint when they own the
       study, otherwise whatever the participant's own match reports.
       ------------------------------------------------------------------ */
    if (criteriaBox) {
        var studyId = criteriaBox.dataset.studyId;
        var target  = criteriaBox.querySelector('[data-criteria-summary]');

        api.get('/api/v1/studies/' + studyId + '/match-criteria')
            .then(function (response) {
                target.textContent = response.data.summary;
            })
            .catch(function () {
                // A participant is not allowed to read a study's criteria
                // endpoint — that is the researcher's view of their own
                // study. Fall back to the summary on their own match.
                api.get('/api/v1/participants/me/matched-studies?limit=50')
                    .then(function (response) {
                        var mine = response.data.studies.filter(function (s) {
                            return String(s.study_id) === String(studyId);
                        })[0];

                        target.textContent = mine
                            ? mine.criteria_summary
                            : 'Matching criteria are not published for this study.';
                    })
                    .catch(function () {
                        target.textContent = 'Matching criteria unavailable.';
                    });
            });
    }

    /* ------------------------------------------------------------------
       Your match + invitation response
       ------------------------------------------------------------------ */
    if (matchBox) {
        var myStudyId  = matchBox.dataset.studyId;
        var summaryEl  = matchBox.querySelector('[data-match-summary]');
        var reasonsEl  = matchBox.querySelector('[data-match-reasons]');
        var inviteEl   = matchBox.querySelector('[data-invitation-block]');

        function renderInvitation(invitation) {
            if (!invitation) {
                inviteEl.innerHTML =
                    '<p class="mt-2 text-[12.5px] text-dim">This study is visible to you, '
                    + 'but you have not been invited yet.</p>';
                return;
            }

            if (invitation.status === 'accepted') {
                inviteEl.innerHTML = badge('ok', 'You accepted this invitation');
                return;
            }

            if (invitation.status === 'declined') {
                inviteEl.innerHTML = badge('danger', 'You declined this invitation');
                return;
            }

            if (invitation.status !== 'pending') {
                inviteEl.innerHTML = badge('neutral', invitation.status_label);
                return;
            }

            inviteEl.innerHTML =
                  '<p class="mb-2 rounded-lg bg-plum/10 px-3 py-2 text-[12.5px] text-plum">'
                +   'You have been invited to this study.'
                + '</p>'
                + '<div class="flex flex-wrap gap-2">'
                +   '<button type="button" data-respond="' + esc(invitation.id) + '" data-status="accepted" '
                +     'class="rounded-lg bg-ok/12 px-3 py-1.5 font-mono text-[11px] text-ok transition '
                +     'hover:bg-ok/20 disabled:cursor-not-allowed disabled:opacity-50">Accept invite</button>'
                +   '<button type="button" data-respond="' + esc(invitation.id) + '" data-status="declined" '
                +     'class="rounded-lg bg-steel/15 px-3 py-1.5 font-mono text-[11px] text-steel transition '
                +     'hover:bg-steel/25 disabled:cursor-not-allowed disabled:opacity-50">Decline</button>'
                + '</div>';
        }

        function loadInvitation() {
            api.get('/api/v1/invitations')
                .then(function (response) {
                    var mine = response.data.invitations.filter(function (i) {
                        return String(i.study_id) === String(myStudyId);
                    })[0];

                    renderInvitation(mine || null);
                })
                .catch(function () { renderInvitation(null); });
        }

        function loadMatch() {
            api.get('/api/v1/participants/me/matched-studies?limit=50')
                .then(function (response) {
                    var mine = response.data.studies.filter(function (s) {
                        return String(s.study_id) === String(myStudyId);
                    })[0];

                    if (!mine) {
                        summaryEl.textContent =
                            'No live match score for this study — you may already be taking part in it.';
                        return;
                    }

                    summaryEl.innerHTML = 'Your score for this study is <b class="text-ink">'
                                        + esc(mine.match_score) + '/100</b>.';

                    reasonsEl.innerHTML = (mine.match_reasons || []).length
                        ? mine.match_reasons.map(function (r) { return badge('plum', r); }).join('')
                        : badge('neutral', 'No match reasons available');
                })
                .catch(function (error) {
                    summaryEl.innerHTML = '<span class="text-flame">' + esc(error.message) + '</span>';
                });
        }

        matchBox.addEventListener('click', function (event) {
            var button = event.target.closest('[data-respond]');

            if (!button) {
                return;
            }

            matchBox.querySelectorAll('[data-respond]').forEach(function (b) { b.disabled = true; });
            button.textContent = 'Saving…';

            api.patch('/api/v1/invitations/' + button.dataset.respond, { status: button.dataset.status })
                .then(function () { loadInvitation(); loadMatch(); })
                .catch(function (error) {
                    inviteEl.innerHTML =
                        '<p class="rounded-lg bg-flame/10 px-3 py-2 text-[12.5px] text-flame">'
                        + esc(error.message) + '</p>';
                });
        });

        loadMatch();
        loadInvitation();
    }
});
