/*
|------------------------------------------------------------------------------
| TRYBE — candidate profile page  (Member 4)
|------------------------------------------------------------------------------
|
|     GET  /api/v1/studies/{study}/candidates/{user}
|     POST /api/v1/studies/{study}/invitations
|
| The factor table is rendered in full here rather than just the total,
| because the eight contributions add up to the match score. Being able to
| point at that on screen — instead of asking someone to trust one number —
| is the whole reason the API returns the breakdown.
|
| Conventions §7: DOMContentLoaded wrapper, shared `api` helper (never
| forked), esc() on everything from the database, full Tailwind class names.
*/

document.addEventListener('DOMContentLoaded', function () {

    var page = document.getElementById('candidate-profile-page');

    if (!page) {
        return;
    }

    var studyId = page.dataset.studyId;
    var userId  = page.dataset.userId;

    var SHOW_URL   = '/api/v1/studies/' + studyId + '/candidates/' + userId;
    var INVITE_URL = '/api/v1/studies/' + studyId + '/invitations';

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
        gold:    'bg-star/18 text-star',
        ok:      'bg-ok/12 text-ok',
        danger:  'bg-danger/12 text-danger',
        neutral: 'bg-steel/18 text-steel',
        expert:  'bg-plum/12 text-plum',
    };

    function badge(tone, text) {
        var cls = BADGE_TONES[tone] || BADGE_TONES.neutral;

        return '<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-1 '
             + 'font-mono text-[10.5px] tracking-wide ' + cls + '">' + esc(text) + '</span>';
    }

    function statCard(label, value) {
        return '<div class="rounded-xl border border-line bg-surface-soft p-3.5">'
            +    '<div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">'
            +      esc(label) + '</div>'
            +    '<div class="mt-1 text-[13.5px] font-semibold text-ink">' + esc(value) + '</div>'
            +  '</div>';
    }

    var FACTOR_LABELS = {
        topics:       'Topic overlap',
        age:          'Age',
        location:     'Location',
        skills:       'Skills',
        credential:   'Credential level',
        reliability:  'Reliability',
        availability: 'Availability',
        standing:     'Standing',
    };

    /* One row per applicable factor. Inapplicable factors are shown greyed
       with their reason, so "why is topics not counted?" is answerable
       without opening the database. */
    function factorRow(key, factor) {
        var label = FACTOR_LABELS[key] || key;

        if (!factor.applicable) {
            return '<div class="flex items-center justify-between gap-3 border-b border-line py-1.5 last:border-none">'
                +    '<span class="text-[12px] text-steel">' + esc(label) + '</span>'
                +    '<span class="font-mono text-[10.5px] text-steel">not set for this study</span>'
                +  '</div>';
        }

        return '<div class="flex items-center justify-between gap-3 border-b border-line py-1.5 last:border-none">'
            +    '<span class="text-[12px] text-ink">' + esc(label) + '</span>'
            +    '<span class="font-mono text-[10.5px] text-plum">'
            +      factor.sub_score + ' × ' + factor.effective_weight + '% = '
            +      factor.contribution
            +    '</span>'
            +  '</div>';
    }

    function inviteControl(data) {
        var invitation = data.invitation;

        if (invitation && invitation.status === 'pending') {
            return badge('neutral', 'Already invited');
        }

        if (invitation && invitation.status === 'accepted') {
            return badge('ok', 'Accepted your invitation');
        }

        if (invitation && invitation.status === 'declined') {
            return badge('danger', 'Declined your invitation');
        }

        if (!data.strong_match) {
            return '<p class="text-[12.5px] text-dim">Below the '
                 + esc(data.strong_threshold) + '% threshold, so they cannot be invited to this study.</p>';
        }

        return '<button type="button" data-invite '
             + 'class="rounded-lg bg-plum/12 px-3 py-1.5 font-mono text-[11px] text-plum transition '
             + 'hover:bg-plum/20 disabled:cursor-not-allowed disabled:opacity-50">Invite participant</button>';
    }

    function historyRow(item) {
        return '<div class="rounded-xl border border-line bg-surface-soft p-3.5">'
            +    '<div class="flex items-center justify-between gap-3">'
            +      '<div class="min-w-0">'
            +        '<div class="truncate text-[13.5px] font-semibold text-ink">'
            +          esc(item.study_title) + '</div>'
            +        '<div class="font-mono text-[11px] text-steel">' + esc(item.stage_label) + '</div>'
            +      '</div>'
            +      '<div class="font-mono text-[10.5px] text-steel">' + esc(item.completed_on || '') + '</div>'
            +    '</div>'
            +  '</div>';
    }

    function initials(name) {
        return String(name || 'TR')
            .replace(/^dr\.?\s*/i, '')
            .split(/\s+/).filter(Boolean).slice(0, 2)
            .map(function (p) { return p.charAt(0); }).join('').toUpperCase() || 'TR';
    }

    function render(data) {
        page.querySelector('[data-avatar]').innerHTML =
            '<span class="flex h-14 w-14 items-center justify-center rounded-full bg-plum/12 '
            + 'font-mono text-[16px] text-plum">' + esc(initials(data.name)) + '</span>';

        page.querySelector('[data-name]').textContent     = data.name;
        page.querySelector('[data-location]').textContent  = data.location || 'No location set';
        page.querySelector('[data-skills]').textContent    = data.skills || '—';
        page.querySelector('[data-interests]').textContent = data.interests || '—';

        page.querySelector('[data-profile-badges]').innerHTML =
              badge('plum', (data.credential_level || 'none').charAt(0).toUpperCase()
                          + (data.credential_level || 'none').slice(1))
            + badge(data.is_verified_participant ? 'ok' : 'neutral',
                    data.is_verified_participant ? 'Verified participant' : 'Not verified')
            + badge('gold', 'Reliability ' + data.reliability_score);

        page.querySelector('[data-profile-stats]').innerHTML =
              statCard('Age', data.age === null ? '—' : data.age)
            + statCard('Occupation', data.occupation || '—')
            + statCard('Completed studies', data.completed_studies_count)
            + statCard('Current streak', data.current_streak_weeks + ' weeks');

        page.querySelector('[data-match-headline]').innerHTML =
            '<b class="text-ink">' + esc(data.match_score) + '/100</b> for '
            + esc(data.study_title) + '.';

        var factorsHtml = '';
        Object.keys(data.factors).forEach(function (key) {
            factorsHtml += factorRow(key, data.factors[key]);
        });
        page.querySelector('[data-match-factors]').innerHTML = factorsHtml;

        page.querySelector('[data-match-reasons]').innerHTML =
            (data.match_reasons || []).length
                ? data.match_reasons.map(function (r) { return badge('plum', r); }).join('')
                : badge('neutral', 'No match reasons available');

        page.querySelector('[data-invite-block]').innerHTML = inviteControl(data);

        var history = page.querySelector('[data-history]');
        history.innerHTML = (data.participation_history || []).length
            ? data.participation_history.map(historyRow).join('')
            : '<p class="text-[13.5px] text-dim">No participation history yet.</p>';
    }

    function load() {
        api.get(SHOW_URL)
            .then(function (response) { render(response.data); })
            .catch(function (error) {
                page.querySelector('[data-match-headline]').innerHTML =
                    '<span class="text-flame">' + esc(error.message) + '</span>';
            });
    }

    page.addEventListener('click', function (event) {
        var button = event.target.closest('[data-invite]');

        if (!button) {
            return;
        }

        button.disabled = true;
        button.textContent = 'Sending…';

        api.post(INVITE_URL, { participant_id: Number(userId) })
            .then(function (response) {
                page.querySelector('[data-invite-alert]').innerHTML =
                    '<p class="mt-2 rounded-lg bg-ok/10 px-3 py-2 text-[12.5px] text-ok">'
                    + esc(response.message) + '</p>';
                load();
            })
            .catch(function (error) {
                button.disabled = false;
                button.textContent = 'Invite participant';
                page.querySelector('[data-invite-alert]').innerHTML =
                    '<p class="mt-2 rounded-lg bg-flame/10 px-3 py-2 text-[12.5px] text-flame">'
                    + esc(error.message) + '</p>';
            });
    });

    load();
});
