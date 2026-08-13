/*
|------------------------------------------------------------------------------
| TRYBE — the participant's invitations page  (Member 4)
|------------------------------------------------------------------------------
|
|     GET   /api/v1/invitations
|     PATCH /api/v1/invitations/{id}   { status: 'accepted' | 'declined' }
|
| Accept and decline are one PATCH against one state machine, not two POST
| endpoints. The server refuses a second response with a 409, so a stale tab
| cannot double-accept.
|
| Conventions §7: DOMContentLoaded wrapper, shared `api` helper (never
| forked), esc() on everything from the database, full Tailwind class names.
*/

document.addEventListener('DOMContentLoaded', function () {

    var page = document.getElementById('invitations-page');

    if (!page) {
        return;
    }

    var pendingList  = page.querySelector('[data-pending-list]');
    var answeredList = page.querySelector('[data-answered-list]');
    var countLabel   = page.querySelector('[data-invitations-count]');
    var alertBox     = page.querySelector('[data-invitation-alert]');
    var refresh      = page.querySelector('[data-refresh-invitations]');

    var URL = '/api/v1/invitations';

    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    var BADGE_TONES = {
        ok:      'bg-ok/12 text-ok',
        danger:  'bg-danger/12 text-danger',
        neutral: 'bg-steel/18 text-steel',
        plum:    'bg-plum/12 text-plum',
    };

    var STATUS_TONES = {
        accepted:  'ok',
        declined:  'danger',
        withdrawn: 'neutral',
        expired:   'neutral',
        pending:   'plum',
    };

    function badge(tone, text) {
        var cls = BADGE_TONES[tone] || BADGE_TONES.neutral;

        return '<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-1 '
             + 'font-mono text-[10.5px] tracking-wide ' + cls + '">' + esc(text) + '</span>';
    }

    function alertMessage(text, tone) {
        var cls = tone === 'error' ? 'bg-flame/10 text-flame' : 'bg-ok/10 text-ok';

        alertBox.innerHTML = '<p class="mb-3 rounded-lg px-3 py-2 text-[12.5px] ' + cls + '">'
                           + esc(text) + '</p>';
    }

    function studyMeta(study) {
        if (!study) {
            return '';
        }

        var bits = [];

        if (study.category) { bits.push(study.category); }
        if (study.method) { bits.push(study.method === 'in_person' ? 'In person' : 'Online'); }
        if (study.duration_minutes) { bits.push(study.duration_minutes + ' min'); }
        if (study.deadline) { bits.push('Closes ' + study.deadline); }

        return bits.join(' · ');
    }

    function pendingCard(invitation) {
        var study   = invitation.study || {};
        var reasons = (invitation.match_reasons || []).join(' · ');

        return '<div class="rounded-xl border border-line bg-surface px-4 py-3.5">'
            +    '<div class="flex flex-wrap items-start justify-between gap-3">'
            +      '<div class="min-w-0 flex-1">'
            +        '<div class="text-[14px] font-semibold text-ink">' + esc(study.title) + '</div>'
            +        '<div class="mt-0.5 font-mono text-[11px] text-steel">'
            +          esc(study.researcher_name || 'Researcher') + ' · ' + esc(studyMeta(study))
            +        '</div>'
            +        (reasons
                        ? '<div class="mt-1.5 text-[11.5px] text-dim">Why you: ' + esc(reasons) + '</div>'
                        : '')
            +      '</div>'
            +      badge('plum', invitation.match_score_at_invite + '% match')
            +    '</div>'
            +    '<div class="mt-3 flex flex-wrap items-center gap-2">'
            +      '<button type="button" data-respond="' + esc(invitation.id) + '" data-status="accepted" '
            +        'class="rounded-lg bg-ok/12 px-3 py-1.5 font-mono text-[11px] text-ok transition '
            +        'hover:bg-ok/20 disabled:cursor-not-allowed disabled:opacity-50">Accept</button>'
            +      '<button type="button" data-respond="' + esc(invitation.id) + '" data-status="declined" '
            +        'class="rounded-lg bg-steel/15 px-3 py-1.5 font-mono text-[11px] text-steel transition '
            +        'hover:bg-steel/25 disabled:cursor-not-allowed disabled:opacity-50">Decline</button>'
            +      '<a href="' + esc(study.url || '#') + '" class="rounded-lg border border-line px-3 py-1.5 '
            +        'font-mono text-[11px] text-steel transition hover:border-plum hover:text-plum">View study</a>'
            +    '</div>'
            +  '</div>';
    }

    function answeredCard(invitation) {
        var study = invitation.study || {};

        return '<div class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface px-3.5 py-3">'
            +    '<div class="min-w-0 flex-1">'
            +      '<div class="truncate text-[13.5px] font-semibold text-ink">' + esc(study.title) + '</div>'
            +      '<div class="font-mono text-[11px] text-steel">'
            +        esc(study.researcher_name || 'Researcher')
            +      '</div>'
            +    '</div>'
            +    badge(STATUS_TONES[invitation.status] || 'neutral', invitation.status_label)
            +  '</div>';
    }

    function render(data) {
        var pending  = data.invitations.filter(function (i) { return i.can_respond; });
        var answered = data.invitations.filter(function (i) { return !i.can_respond; });

        countLabel.textContent = pending.length
            ? pending.length + (pending.length === 1 ? ' invitation waiting' : ' invitations waiting')
            : 'Nothing waiting on you right now.';

        pendingList.innerHTML = pending.length
            ? pending.map(pendingCard).join('')
            : '<p class="text-[12.5px] text-dim">No pending invitations. '
              + 'Researchers invite participants whose profile strongly matches their study.</p>';

        answeredList.innerHTML = answered.length
            ? answered.map(answeredCard).join('')
            : '<p class="text-[12.5px] text-dim">Nothing answered yet.</p>';
    }

    function load() {
        api.get(URL)
            .then(function (response) { render(response.data); })
            .catch(function (error) {
                pendingList.innerHTML = '<p class="text-[12.5px] text-flame">'
                                      + esc(error.message) + '</p>';
            });
    }

    /* Delegated: the buttons are replaced on every render. */
    pendingList.addEventListener('click', function (event) {
        var button = event.target.closest('[data-respond]');

        if (!button) {
            return;
        }

        // Disable BOTH buttons on the card — accepting and declining at once
        // is not a thing, and the second click would be a 409 anyway.
        var card = button.closest('div.rounded-xl');
        card.querySelectorAll('[data-respond]').forEach(function (b) { b.disabled = true; });
        button.textContent = 'Saving…';

        api.patch('/api/v1/invitations/' + button.dataset.respond, { status: button.dataset.status })
            .then(function (response) {
                alertMessage(response.message, 'success');
                load();
            })
            .catch(function (error) {
                alertMessage(error.message, 'error');
                load();
            });
    });

    if (refresh) {
        refresh.addEventListener('click', load);
    }

    load();
});
