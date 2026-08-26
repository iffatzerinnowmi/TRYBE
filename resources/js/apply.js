/*
|------------------------------------------------------------------------------
| TRYBE — Apply to Study, volunteer studies only  (Member 4)
|------------------------------------------------------------------------------
|
|     GET    /api/v1/studies/{id}/apply-status
|     POST   /api/v1/studies/{id}/apply
|     DELETE /api/v1/studies/{id}/apply        (withdraw)
|
| THIS FILE DECIDES NOTHING.
|
| Whether the button is enabled, what it says, and why it is disabled all come
| from apply-status. There is no copy of the rules here — not even the button
| label — because a second copy is a second thing to keep in step, and the
| study page and the feed card would eventually disagree.
|
| The disabled state is a courtesy, not a guard. The server refuses a paid
| study, a closed study or a duplicate with 422/409 whatever this file sends.
|
| Conventions §7: DOMContentLoaded wrapper, shared `api` helper (never forked),
| esc() on everything from the database, full Tailwind class names.
*/

document.addEventListener('DOMContentLoaded', function () {

    var blocks = document.querySelectorAll('[data-apply-block]');

    if (!blocks.length) {
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

    var ENABLED  = 'rounded-lg bg-plum/12 px-3.5 py-2 font-mono text-[11.5px] text-plum '
                 + 'transition hover:bg-plum/20 disabled:cursor-not-allowed disabled:opacity-50';
    var DISABLED = 'rounded-lg bg-steel/12 px-3.5 py-2 font-mono text-[11.5px] text-steel '
                 + 'cursor-not-allowed';
    var LINKISH  = 'font-mono text-[11px] text-steel underline underline-offset-2 '
                 + 'transition hover:text-flame';

    /* Each block is one study. Several can share a page — the feed renders one
       per card — so everything is scoped to its own element rather than to
       document-level selectors. */
    blocks.forEach(function (block) {

        var studyId = block.getAttribute('data-study-id');
        var control = block.querySelector('[data-apply-control]');
        var note    = block.querySelector('[data-apply-note]');

        var STATUS_URL = '/api/v1/studies/' + encodeURIComponent(studyId) + '/apply-status';
        var APPLY_URL  = '/api/v1/studies/' + encodeURIComponent(studyId) + '/apply';

        function render(d) {
            var html = '';

            if (d.can_apply) {
                html = '<button type="button" data-apply class="' + ENABLED + '">'
                     + esc(d.label) + '</button>';
            } else {
                html = '<button type="button" disabled class="' + DISABLED + '">'
                     + esc(d.label) + '</button>';

                if (d.can_withdraw) {
                    html += ' <button type="button" data-withdraw class="' + LINKISH + '">'
                          + 'Withdraw</button>';
                }
            }

            control.innerHTML = html;

            /* One line of explanation, in priority order: why you cannot
               apply, then your paid-study standing, then seats, then nothing.

               The paid line comes from Member 3's eligibility payload and is
               not recomputed here — the same numbers her unlock page shows,
               so the two can never disagree. */
            var paid = d.paid;

            if (d.message) {
                note.textContent = d.message
                    + (paid ? '  (' + paid.volunteer_progress + '/' + paid.volunteer_target
                              + ' volunteer · ' + paid.karma_balance + ' Karma)' : '');
            } else if (paid) {
                note.textContent = paid.volunteer_progress + '/' + paid.volunteer_target
                    + ' volunteer studies · ' + paid.karma_balance + ' Karma · '
                    + (paid.paid_slots - paid.paid_used) + ' paid application'
                    + ((paid.paid_slots - paid.paid_used) === 1 ? '' : 's') + ' left'
                    + (paid.route === 'karma'
                          ? ' — this one costs ' + paid.karma_cost + ' Karma'
                          : '');
            } else if (d.seats_remaining !== null && d.seats_remaining !== undefined) {
                note.textContent = d.seats_remaining + ' place'
                                 + (d.seats_remaining === 1 ? '' : 's') + ' left';
            } else {
                note.textContent = '';
            }
        }

        function load() {
            api.get(STATUS_URL)
                .then(function (response) { render(response.data); })
                .catch(function (error) {
                    /* A researcher viewing the page gets a 403 here. That is
                       expected, not an error worth shouting about — hide the
                       control rather than showing a red message. */
                    block.classList.add('hidden');
                    if (error && error.status !== 403) {
                        note.textContent = error.message;
                    }
                });
        }

        function busy(button, text) {
            button.disabled = true;
            button.textContent = text;
        }

        /* Delegated: the button is replaced on every render, so a handler
           bound directly to it would be lost after the first click. */
        control.addEventListener('click', function (event) {
            var applyBtn    = event.target.closest('[data-apply]');
            var withdrawBtn = event.target.closest('[data-withdraw]');

            if (applyBtn) {
                busy(applyBtn, 'Applying…');
                note.textContent = '';

                api.post(APPLY_URL, {})
                    .then(function (response) {
                        render(response.data.status);
                        note.textContent = response.message;
                    })
                    .catch(function (error) {
                        note.textContent = error.message;
                        /* Refetch rather than guessing: a 409 usually means
                           the server knows something this page does not. */
                        load();
                    });

                return;
            }

            if (withdrawBtn) {
                busy(withdrawBtn, 'Withdrawing…');
                note.textContent = '';

                api.delete(APPLY_URL)
                    .then(function (response) {
                        render(response.data.status);
                        note.textContent = response.message;
                    })
                    .catch(function (error) {
                        note.textContent = error.message;
                        load();
                    });
            }
        });

        load();
    });
});
