/*
|------------------------------------------------------------------------------
| TRYBE — Competition & Hackathon Board  (Member 4)
|------------------------------------------------------------------------------
|
|     GET    /api/v1/competitions                    the board
|     GET    /api/v1/competitions/mine               my own listings
|     POST   /api/v1/competitions                    post one
|     DELETE /api/v1/competitions/{id}               remove one
|     POST   /api/v1/competitions/{id}/save          save
|     DELETE /api/v1/competitions/{id}/save          unsave
|     GET    /api/v1/participants/me/competitions    my saved list
|
| TWO SAFETY RULES, BOTH ABOUT OUTBOUND LINKS
| -------------------------------------------
| 1. Every external link carries rel="noopener noreferrer". Without noopener,
|    the page we open gets a handle on this window through window.opener and
|    can navigate it somewhere else.
| 2. The destination host is printed next to the button. A reader should be
|    able to see where a link goes before pressing it — that is the cheapest
|    honest substitute for link moderation, which we do not do.
|
| The URL itself is escaped like any other database value: it is attacker-
| controllable text, and the server has already refused anything that is not
| https://.
|
| Conventions §7: DOMContentLoaded wrapper, shared `api` helper (never forked),
| esc() on everything from the database, full Tailwind class names.
*/

document.addEventListener('DOMContentLoaded', function () {

    var board = document.getElementById('competitions-page');
    var saved = document.getElementById('saved-competitions-page');

    if (!board && !saved) { return; }

    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    var LINK   = 'inline-block rounded-lg bg-plum/12 px-3 py-1.5 font-mono text-[11px] text-plum '
               + 'transition hover:bg-plum/20';
    var SAVE   = 'rounded-lg border border-line px-3 py-1.5 font-mono text-[11px] text-steel '
               + 'transition hover:border-plum hover:text-plum';
    var SAVED  = 'rounded-lg border border-plum/40 bg-plum/10 px-3 py-1.5 font-mono text-[11px] text-plum '
               + 'transition hover:bg-plum/20';
    var DANGER = 'rounded-lg border border-line px-3 py-1.5 font-mono text-[11px] text-steel '
               + 'transition hover:border-flame hover:text-flame';

    /* The deadline line — the most useful thing on the card after the name. */
    function deadlineLine(c) {
        if (!c.deadline) {
            return '<span class="font-mono text-[10.5px] text-steel">No deadline given</span>';
        }

        if (c.closed) {
            return '<span class="font-mono text-[10.5px] text-steel">Closed · '
                 + esc(c.deadline_label) + '</span>';
        }

        var tone = c.closing_soon ? 'text-flame' : 'text-steel';
        var left = c.days_left === 0
            ? 'Closes today'
            : (c.days_left === 1 ? '1 day left' : c.days_left + ' days left');

        return '<span class="font-mono text-[10.5px] ' + tone + '">'
             + esc(left) + ' · ' + esc(c.deadline_label) + '</span>';
    }

    function meta(c) {
        var bits = [];

        if (c.organizer) { bits.push(esc(c.organizer)); }
        if (c.location)  { bits.push(esc(c.location)); }
        if (c.team_size) { bits.push(esc(c.team_size)); }
        if (c.prize)     { bits.push(esc(c.prize)); }

        return bits.length
            ? '<div class="mt-1 font-mono text-[10.5px] uppercase tracking-[0.14em] text-steel">'
              + bits.join(' · ') + '</div>'
            : '';
    }

    /* rel="noopener noreferrer" is not optional here — see the header. */
    function externalLink(c, label) {
        return '<a href="' + esc(c.external_url) + '" target="_blank" rel="noopener noreferrer" '
             + 'class="' + LINK + '">' + esc(label || 'Open competition') + ' ↗</a>'
             + (c.destination
                 ? ' <span class="font-mono text-[10.5px] text-steel">' + esc(c.destination) + '</span>'
                 : '');
    }

    function postedBy(c) {
        if (!c.posted_by) { return ''; }

        return '<p class="mt-2 text-[11.5px] text-steel">Posted by ' + esc(c.posted_by)
             + (c.poster_verified ? ' <span class="text-ok">✓</span>' : '')
             + (c.posted_on ? ' · ' + esc(c.posted_on) : '')
             + '</p>';
    }

    /*
       The teammate row. Only rendered once a competition is saved, because
       the flag is meaningless otherwise — and because publishing your name
       should be a separate, deliberate act from bookmarking something.

       The count excludes you, so "2 looking for a team" means two people you
       could actually team up with.
    */
    function teammateRow(c) {
        if (!c.saved) {
            return c.teammates_count
                ? '<p class="mt-2 text-[11.5px] text-steel">'
                  + c.teammates_count + ' looking for a team — save it to join them.</p>'
                : '';
        }

        var checked = c.looking_for_team ? ' checked' : '';

        return ''
            + '<div class="mt-3 rounded-lg bg-steel/8 px-3 py-2">'
            +   '<label class="flex cursor-pointer items-center gap-2 text-[12px] text-ink">'
            +     '<input type="checkbox" data-team="' + esc(c.competition_id) + '"' + checked + '>'
            +     '<span>I\'m looking for a team</span>'
            +   '</label>'
            /* State the trade at the point of consent, not in a policy page.
               Ticking this publishes your name and email to the handful of
               other people looking at this same listing — and is the only
               way to see theirs. */
            +   '<p class="mt-1 text-[11px] leading-snug text-steel">'
            +     'Shares your name and email with others looking for a team on this '
            +     'listing, and lets you see theirs.'
            +   '</p>'
            +   (c.teammates_count
                    ? '<button type="button" data-teammates="' + esc(c.competition_id) + '" '
                      + 'class="mt-1 font-mono text-[10.5px] text-plum underline underline-offset-2">'
                      + c.teammates_count + ' other'
                      + (c.teammates_count === 1 ? '' : 's')
                      + ' looking for a team</button>'
                    : '<p class="mt-1 font-mono text-[10.5px] text-steel">'
                      + 'Nobody else is looking yet.</p>')
            +   '<div class="mt-2 hidden" data-teammate-list="' + esc(c.competition_id) + '"></div>'
            + '</div>';
    }

    function teammatePerson(p) {
        return ''
            + '<div class="border-t border-line py-1.5 first:border-none">'
            +   '<div class="flex flex-wrap items-baseline gap-2">'
            +     '<span class="text-[12.5px] text-ink">' + esc(p.name) + '</span>'
            +     (p.credential_level
                      ? '<span class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">'
                        + esc(p.credential_level) + '</span>'
                      : '')
            +     (p.skills && p.skills.length
                      ? '<span class="text-[11.5px] text-dim">' + esc(p.skills.join(', ')) + '</span>'
                      : '')
            +   '</div>'
            /* mailto rather than plain text: the whole point is to make
               contact easy once both sides have opted in. */
            +   (p.email
                    ? '<a href="mailto:' + esc(p.email) + '" '
                      + 'class="font-mono text-[11px] text-plum underline underline-offset-2">'
                      + esc(p.email) + '</a>'
                    : '')
            + '</div>';
    }

    /* Shared by the board and the saved page: toggle the flag, expand the
       list. Both pages render the same card, so both get this for free. */
    function wireTeammates(root, reload) {
        root.addEventListener('change', function (event) {
            var box = event.target.closest('[data-team]');

            if (!box) { return; }

            var id = box.getAttribute('data-team');

            box.disabled = true;

            api.patch('/api/v1/competitions/' + encodeURIComponent(id) + '/save',
                      { looking_for_team: box.checked })
                .then(function () { reload(); })
                .catch(function () { box.checked = !box.checked; box.disabled = false; });
        });

        root.addEventListener('click', function (event) {
            var button = event.target.closest('[data-teammates]');

            if (!button) { return; }

            var id  = button.getAttribute('data-teammates');
            var box = root.querySelector('[data-teammate-list="' + id + '"]');

            if (!box.classList.contains('hidden')) {
                box.classList.add('hidden');
                return;
            }

            box.classList.remove('hidden');
            box.innerHTML = '<p class="text-[11.5px] text-steel">Loading…</p>';

            api.get('/api/v1/competitions/' + encodeURIComponent(id) + '/teammates')
                .then(function (response) {
                    var d      = response.data;
                    var people = d.people || [];

                    if (!people.length) {
                        box.innerHTML = '<p class="text-[11.5px] text-steel">'
                                      + 'Nobody else is looking yet.</p>';
                        return;
                    }

                    /* Say WHY the addresses are missing rather than quietly
                       omitting them — otherwise it reads as a broken page. */
                    box.innerHTML = people.map(teammatePerson).join('')
                        + (d.you_are_listed
                              ? ''
                              : '<p class="mt-2 border-t border-line pt-2 text-[11px] text-steel">'
                                + 'Tick "I\'m looking for a team" to see their email — '
                                + 'and to let them see yours.</p>');
                })
                .catch(function (error) {
                    box.innerHTML = '<p class="text-[11.5px] text-flame">' + esc(error.message) + '</p>';
                });
        });
    }

    function card(c, options) {
        options = options || {};

        return ''
            + '<div class="rounded-xl border ' + (c.closed ? 'border-line' : 'border-line')
            +      ' bg-surface-soft p-4" data-competition="' + esc(c.competition_id) + '">'

            +   '<div class="text-[14px] font-semibold text-ink">' + esc(c.name) + '</div>'
            +   meta(c)
            +   '<div class="mt-1.5">' + deadlineLine(c) + '</div>'

            +   (c.description
                    ? '<p class="mt-2 text-[12.5px] leading-relaxed text-dim">'
                      + esc(c.description) + '</p>'
                    : '')

            +   '<div class="mt-3 flex flex-wrap items-center gap-2">'
            +     externalLink(c)
            +     (options.canSave
                      ? '<button type="button" data-save="' + esc(c.competition_id) + '" '
                        + 'class="' + (c.saved ? SAVED : SAVE) + '">'
                        + (c.saved ? 'Saved ✓' : 'Save') + '</button>'
                      : '')
            +     (options.canUnsave
                      ? '<button type="button" data-unsave="' + esc(c.competition_id) + '" '
                        + 'class="' + DANGER + '">Remove</button>'
                      : '')
            +     (options.canDelete
                      ? '<button type="button" data-delete="' + esc(c.competition_id) + '" '
                        + 'class="' + DANGER + '">Delete</button>'
                      : '')
            +   '</div>'

            +   (options.showTeam ? teammateRow(c) : '')
            +   (options.hidePoster ? '' : postedBy(c))
            + '</div>';
    }

    function empty(message) {
        return '<p class="text-[12.5px] text-dim">' + esc(message) + '</p>';
    }

    /* =========================================================================
       THE BOARD
       ========================================================================= */

    if (board) {
        var boardList  = board.querySelector('[data-board-list]');
        var boardCount = board.querySelector('[data-board-count]');
        var postPanel  = board.querySelector('[data-post-panel]');
        var mineList   = board.querySelector('[data-mine-list]');
        var postStatus = board.querySelector('[data-post-status]');
        var postAlert  = board.querySelector('[data-post-alert]');

        var canSave = false;

        function loadBoard() {
            api.get('/api/v1/competitions')
                .then(function (response) {
                    var d = response.data;

                    /* The server decides who may post. Revealing the form is a
                       convenience — POST /competitions refuses regardless. */
                    if (d.can_post) {
                        postPanel.classList.remove('hidden');
                        loadMine();
                    }

                    /* Saving is participants-only, and the server says so.
                       Inferring it from can_post would offer the button to an
                       unverified researcher, who can do neither. */
                    canSave = !!d.can_save;

                    boardCount.textContent = d.count
                        ? d.count + ' open competition' + (d.count === 1 ? '' : 's') + ', newest first'
                        : '';

                    boardList.innerHTML = d.competitions.length
                        ? d.competitions.map(function (c) {
                              return card(c, { canSave: canSave, showTeam: canSave });
                          }).join('')
                        : empty('No competitions posted yet.');
                })
                .catch(function (error) {
                    boardList.innerHTML = empty(error.message);
                });
        }

        function loadMine() {
            api.get('/api/v1/competitions/mine')
                .then(function (response) {
                    var list = response.data.competitions;

                    mineList.innerHTML = list.length
                        ? list.map(function (c) {
                              return card(c, { canDelete: true, hidePoster: true });
                          }).join('')
                        : empty('You have not posted any competitions yet.');
                })
                .catch(function (error) {
                    mineList.innerHTML = empty(error.message);
                });
        }

        /* Delegated: cards are replaced on every reload, so handlers bound
           directly to a button would be lost after the first refresh. */
        board.addEventListener('click', function (event) {
            var saveBtn   = event.target.closest('[data-save]');
            var deleteBtn = event.target.closest('[data-delete]');

            if (saveBtn) {
                var id   = saveBtn.getAttribute('data-save');
                var isOn = saveBtn.textContent.indexOf('Saved') === 0;
                var url  = '/api/v1/competitions/' + encodeURIComponent(id) + '/save';

                saveBtn.disabled = true;

                (isOn ? api.delete(url) : api.post(url, {}))
                    .then(function () { loadBoard(); })
                    .catch(function (error) {
                        saveBtn.disabled = false;
                        boardCount.textContent = error.message;
                    });

                return;
            }

            if (deleteBtn) {
                if (!window.confirm('Remove this listing? Anyone who saved it will lose it.')) {
                    return;
                }

                deleteBtn.disabled = true;

                api.delete('/api/v1/competitions/' + encodeURIComponent(deleteBtn.getAttribute('data-delete')))
                    .then(function () { loadMine(); loadBoard(); })
                    .catch(function (error) {
                        deleteBtn.disabled = false;
                        mineList.innerHTML = empty(error.message);
                    });
            }
        });

        var submit = board.querySelector('[data-post-submit]');

        if (submit) {
            submit.addEventListener('click', function () {
                var body = {};

                board.querySelectorAll('[data-field]').forEach(function (input) {
                    var value = input.value.trim();
                    if (value !== '') { body[input.getAttribute('data-field')] = value; }
                });

                submit.disabled = true;
                postStatus.textContent = 'Posting…';
                postAlert.innerHTML = '';

                api.post('/api/v1/competitions', body)
                    .then(function (response) {
                        postStatus.textContent = response.message;

                        board.querySelectorAll('[data-field]').forEach(function (i) { i.value = ''; });

                        loadMine();
                        loadBoard();
                    })
                    .catch(function (error) {
                        postStatus.textContent = '';
                        postAlert.innerHTML =
                            '<p class="mt-3 rounded-lg bg-flame/10 px-3 py-2 text-[12.5px] text-flame">'
                            + esc(error.message) + '</p>';
                    })
                    .finally(function () { submit.disabled = false; });
            });
        }

        wireTeammates(board, loadBoard);

        loadBoard();
    }

    /* =========================================================================
       THE SAVED LIST
       ========================================================================= */

    if (saved) {
        var savedList  = saved.querySelector('[data-saved-list]');
        var savedCount = saved.querySelector('[data-saved-count]');
        var savedAlert = saved.querySelector('[data-saved-alert]');

        function loadSaved() {
            api.get('/api/v1/participants/me/competitions')
                .then(function (response) {
                    var d = response.data;

                    savedCount.textContent = d.count
                        ? d.count + ' saved · soonest deadline first'
                        : '';

                    savedList.innerHTML = d.competitions.length
                        ? d.competitions.map(function (c) {
                              return card(c, { canUnsave: true, showTeam: true });
                          }).join('')
                        : empty('Nothing saved yet. Open the competition board and save the ones you like.');
                })
                .catch(function (error) {
                    savedList.innerHTML = empty(error.message);
                });
        }

        saved.addEventListener('click', function (event) {
            var button = event.target.closest('[data-unsave]');

            if (!button) { return; }

            button.disabled = true;

            api.delete('/api/v1/competitions/'
                       + encodeURIComponent(button.getAttribute('data-unsave')) + '/save')
                .then(function () { loadSaved(); })
                .catch(function (error) {
                    button.disabled = false;
                    savedAlert.innerHTML =
                        '<p class="rounded-lg bg-flame/10 px-3 py-2 text-[12.5px] text-flame">'
                        + esc(error.message) + '</p>';
                });
        });

        wireTeammates(saved, loadSaved);

        loadSaved();
    }
});
