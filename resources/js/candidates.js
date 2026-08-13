/*
|------------------------------------------------------------------------------
| TRYBE — Smart Participant Matching panel  (Member 4)
|------------------------------------------------------------------------------
|
| Renders the "Suggested participants" panel from the API. The Blade partial
| ships with no data; everything here comes from
|
|     GET  /api/v1/studies/{study}/candidates
|     POST /api/v1/studies/{study}/invitations
|
| THREE RULES THIS FILE FOLLOWS (team conventions §7)
|
|   1. DOMContentLoaded wrapper is required. app.js is loaded as a module,
|      which the browser defers until after the HTML is parsed. Without it,
|      `api` would not exist yet.
|
|   2. Everything from the database is escaped before it becomes innerHTML.
|      Participant names and study titles are user-supplied.
|
|   3. Tailwind class names are written out IN FULL inside lookup objects.
|      Tailwind scans source files for literal class names; a name built by
|      joining strings ('bg-' + tone) is never found and the style silently
|      goes missing.
|
| The shared api helper (resources/js/api.js) is Member 1's and is not forked
| here — it already handles the CSRF token, the Accept header and errors.
*/

document.addEventListener('DOMContentLoaded', function () {

    var panels = document.querySelectorAll('[data-candidates-panel]');

    if (!panels.length) {
        return;
    }

    /* ---- Escape database values before they become HTML. Not optional. ---- */
    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* ---- Rebuilds of the Blade components, so the markup stays identical ---- */

    var BADGE_TONES = {
        expert:  'bg-plum/12 text-plum',
        gold:    'bg-star/18 text-star',
        neutral: 'bg-steel/18 text-steel',
        ok:      'bg-ok/12 text-ok',
        danger:  'bg-danger/12 text-danger',
    };

    function badge(tone, text) {
        var cls = BADGE_TONES[tone] || BADGE_TONES.neutral;

        return '<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-1 '
             + 'font-mono text-[10.5px] tracking-wide ' + cls + '">' + esc(text) + '</span>';
    }

    function avatar(name) {
        var initials = String(name || 'TR')
            .replace(/^dr\.?\s*/i, '')
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(function (part) { return part.charAt(0); })
            .join('')
            .toUpperCase();

        return '<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full '
             + 'bg-plum/12 font-mono text-[11px] text-plum">' + esc(initials || 'TR') + '</span>';
    }

    /* Buttons are rebuilt rather than reused so the disabled state is honest. */
    function ghostLink(href, label) {
        return '<a href="' + esc(href) + '" class="rounded-lg border border-line px-2.5 py-1 '
             + 'font-mono text-[10.5px] text-steel transition hover:border-plum hover:text-plum">'
             + esc(label) + '</a>';
    }

    function inviteButton(userId) {
        return '<button type="button" data-invite="' + esc(userId) + '" '
             + 'class="rounded-lg bg-plum/12 px-2.5 py-1 font-mono text-[10.5px] text-plum '
             + 'transition hover:bg-plum/20 disabled:cursor-not-allowed disabled:opacity-50">'
             + 'Invite</button>';
    }

    /* The invitation block: a badge for anything already decided, a button
       only when there is nothing live. Mirrors InvitationStatus exactly. */
    function invitationControl(candidate) {
        var invitation = candidate.invitation;

        if (!invitation) {
            return inviteButton(candidate.user_id);
        }

        if (invitation.status === 'pending') {
            return badge('neutral', 'Invited');
        }

        if (invitation.status === 'accepted') {
            return badge('ok', 'Accepted');
        }

        if (invitation.status === 'declined') {
            return badge('danger', 'Declined');
        }

        // withdrawn or expired — they can be invited again
        return inviteButton(candidate.user_id);
    }

    function candidateRow(candidate) {
        var meta = [
            candidate.age ? candidate.age + ' years' : 'Age not set',
            candidate.location || 'Location not set',
            candidate.credential_level
                ? candidate.credential_level.charAt(0).toUpperCase() + candidate.credential_level.slice(1)
                : 'Unranked',
        ].join(' · ');

        var reasons = (candidate.match_reasons || []).join(' · ');

        return '<div class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface px-3.5 py-3">'
            +    avatar(candidate.name)
            +    '<div class="min-w-0 flex-1">'
            +      '<div class="truncate text-[13.5px] font-semibold text-ink">' + esc(candidate.name) + '</div>'
            +      '<div class="font-mono text-[11px] text-steel">' + esc(meta) + '</div>'
            +      (reasons ? '<div class="mt-1 text-[11.5px] text-dim">' + esc(reasons) + '</div>' : '')
            +    '</div>'
            +    '<div class="flex items-center gap-2">'
            +      badge(candidate.strong_match ? 'expert' : 'gold', candidate.match_score + '%')
            +      ghostLink(candidate.links.profile, 'View profile')
            +      invitationControl(candidate)
            +    '</div>'
            +  '</div>';
    }

    function message(text, tone) {
        var colour = tone === 'error' ? 'text-flame' : 'text-dim';

        return '<p class="text-[12.5px] ' + colour + '">' + esc(text) + '</p>';
    }

    /* ---- Topics are the same for every panel, so fetch them once and
           share the promise rather than once per study on the dashboard. ---- */
    var topicsPromise = null;

    function loadTopics() {
        if (!topicsPromise) {
            topicsPromise = api.get('/api/v1/topics').then(function (r) { return r.data; });
        }

        return topicsPromise;
    }

    /* ---- One panel per study on the dashboard ---- */
    panels.forEach(function (panel) {

        var studyId  = panel.dataset.studyId;
        var list     = panel.querySelector('[data-candidate-list]');
        var summary  = panel.querySelector('[data-criteria-summary]');
        var seats    = panel.querySelector('[data-seat-count]');
        var warning  = panel.querySelector('[data-criteria-default]');
        var refresh  = panel.querySelector('[data-refresh-candidates]');

        var editor       = panel.querySelector('[data-criteria-editor]');
        var editButton   = panel.querySelector('[data-edit-criteria]');
        var saveButton   = panel.querySelector('[data-save-criteria]');
        var cancelButton = panel.querySelector('[data-cancel-criteria]');
        var topicBox     = panel.querySelector('[data-topic-options]');
        var criteriaAlert = panel.querySelector('[data-criteria-alert]');

        var CANDIDATES_URL  = '/api/v1/studies/' + studyId + '/candidates';
        var INVITATIONS_URL = '/api/v1/studies/' + studyId + '/invitations';
        var CRITERIA_URL    = '/api/v1/studies/' + studyId + '/match-criteria';

        function render(data) {
            summary.textContent = data.criteria.summary;

            if (data.criteria.is_default) {
                warning.classList.remove('hidden');
            } else {
                warning.classList.add('hidden');
            }

            seats.textContent = data.slots > 0
                ? data.seats_taken + ' / ' + data.slots + ' seats'
                : '';

            if (!data.candidates.length) {
                list.innerHTML = message('No strong matches yet for this study.');
                return;
            }

            list.innerHTML = data.candidates.map(candidateRow).join('');
        }

        function load() {
            api.get(CANDIDATES_URL)
                .then(function (response) { render(response.data); })
                .catch(function (error) { list.innerHTML = message(error.message, 'error'); });
        }

        /* Event delegation: the Invite buttons do not exist at bind time,
           and they are replaced on every render. */
        list.addEventListener('click', function (event) {
            var button = event.target.closest('[data-invite]');

            if (!button) {
                return;
            }

            // A double click would be a 409 from the unique index. Stop it here.
            button.disabled = true;
            button.textContent = 'Sending…';

            api.post(INVITATIONS_URL, { participant_id: Number(button.dataset.invite) })
                .then(function () { load(); })
                .catch(function (error) {
                    button.disabled = false;
                    button.textContent = 'Invite';
                    list.insertAdjacentHTML('afterbegin',
                        '<p class="rounded-lg bg-flame/10 px-3 py-2 text-[12px] text-flame">'
                        + esc(error.message) + '</p>');
                });
        });

        if (refresh) {
            refresh.addEventListener('click', load);
        }

        /* ==============================================================
           The criteria editor
           ==============================================================
           Eligibility criteria belong on the "post a study" form, which is
           Member 2's and does not collect them yet. This lets a researcher
           set them after posting with no change to her form. */

        function field(name) {
            return editor.querySelector('[data-field="' + name + '"]');
        }

        function renderTopics(topics, selectedIds) {
            if (!topics.length) {
                topicBox.innerHTML = '<span class="text-[12px] text-dim">'
                                   + 'No topics seeded yet — run the TopicSeeder.</span>';
                return;
            }

            topicBox.innerHTML = topics.map(function (topic) {
                var checked = selectedIds.indexOf(topic.id) > -1;

                // Both class strings are written out in full — Tailwind never
                // sees a name built by joining strings.
                var cls = checked
                    ? 'cursor-pointer rounded-full border border-plum bg-plum/12 px-3 py-1 font-mono text-[10.5px] text-plum'
                    : 'cursor-pointer rounded-full border border-line bg-surface px-3 py-1 font-mono text-[10.5px] text-steel';

                return '<label class="' + cls + '">'
                     +   '<input type="checkbox" class="sr-only" data-topic="' + esc(topic.id) + '"'
                     +     (checked ? ' checked' : '') + '>'
                     +   esc(topic.name)
                     + '</label>';
            }).join('');
        }

        function openEditor() {
            criteriaAlert.innerHTML = '';

            api.get(CRITERIA_URL)
                .then(function (response) {
                    var c = response.data;

                    field('age_min').value           = c.age_min === null ? '' : c.age_min;
                    field('age_max').value           = c.age_max === null ? '' : c.age_max;
                    field('location').value          = c.location || '';
                    field('credential_min').value    = c.credential_min || 'none';
                    field('availability_days').value = c.availability_days === null ? '' : c.availability_days;

                    // Stored as normalised tokens; shown back as a readable list.
                    field('required_skills').value = (c.required_skills || []).join(', ');

                    var selected = (c.topics || []).map(function (t) { return t.id; });

                    return loadTopics().then(function (topics) {
                        renderTopics(topics, selected);
                    });
                })
                .catch(function (error) {
                    criteriaAlert.innerHTML =
                        '<p class="mt-3 rounded-lg bg-flame/10 px-3 py-2 text-[12px] text-flame">'
                        + esc(error.message) + '</p>';
                });

            editor.classList.remove('hidden');
        }

        function closeEditor() {
            editor.classList.add('hidden');
            criteriaAlert.innerHTML = '';
        }

        function numberOrNull(value) {
            var trimmed = String(value || '').trim();

            return trimmed === '' ? null : Number(trimmed);
        }

        function saveCriteria() {
            saveButton.disabled = true;
            saveButton.textContent = 'Saving…';

            var skills = String(field('required_skills').value || '')
                .split(',')
                .map(function (s) { return s.trim(); })
                .filter(function (s) { return s !== ''; });

            var topicIds = Array.prototype.slice
                .call(topicBox.querySelectorAll('[data-topic]'))
                .filter(function (input) { return input.checked; })
                .map(function (input) { return Number(input.dataset.topic); });

            /* PATCH, not PUT. The endpoint accepts both, but the shared
               api.js helper only exposes get/post/patch/delete and team
               rules say not to fork it. PUT stays available for Postman. */
            api.patch(CRITERIA_URL, {
                age_min:           numberOrNull(field('age_min').value),
                age_max:           numberOrNull(field('age_max').value),
                location:          String(field('location').value || '').trim() || null,
                credential_min:    field('credential_min').value || null,
                required_skills:   skills,
                availability_days: numberOrNull(field('availability_days').value),
                topic_ids:         topicIds,
            })
                .then(function (response) {
                    criteriaAlert.innerHTML =
                        '<p class="mt-3 rounded-lg bg-ok/10 px-3 py-2 text-[12px] text-ok">'
                        + esc(response.message) + '</p>';

                    saveButton.disabled = false;
                    saveButton.textContent = 'Save criteria';

                    // Re-rank immediately — the whole point of changing criteria.
                    load();
                })
                .catch(function (error) {
                    saveButton.disabled = false;
                    saveButton.textContent = 'Save criteria';

                    criteriaAlert.innerHTML =
                        '<p class="mt-3 rounded-lg bg-flame/10 px-3 py-2 text-[12px] text-flame">'
                        + esc(error.message) + '</p>';
                });
        }

        /* Toggling a topic chip: the checkbox is visually hidden, so the
           label's styling has to be swapped by hand. */
        topicBox.addEventListener('change', function (event) {
            var input = event.target.closest('[data-topic]');

            if (!input) {
                return;
            }

            input.parentElement.className = input.checked
                ? 'cursor-pointer rounded-full border border-plum bg-plum/12 px-3 py-1 font-mono text-[10.5px] text-plum'
                : 'cursor-pointer rounded-full border border-line bg-surface px-3 py-1 font-mono text-[10.5px] text-steel';
        });

        editButton.addEventListener('click', function () {
            if (editor.classList.contains('hidden')) {
                openEditor();
            } else {
                closeEditor();
            }
        });

        cancelButton.addEventListener('click', closeEditor);
        saveButton.addEventListener('click', saveCriteria);

        /* The list changes because of somebody else's action — a participant
           accepting. Refresh when the tab comes back to the foreground rather
           than polling: with several studies on one dashboard, an interval
           would be one request per study per tick. */
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                load();
            }
        });

        load();
    });
});
