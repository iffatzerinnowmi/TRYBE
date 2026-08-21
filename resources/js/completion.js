/*
|------------------------------------------------------------------------------
| TRYBE — completing a study, participant side  (Member 4)
|------------------------------------------------------------------------------
|
|     GET   /api/v1/participants/me/applications         the My studies page
|     GET   /api/v1/studies/{id}/completion               button + modal state
|     POST  /api/v1/studies/{id}/completion               "I submitted the form"
|     PATCH /api/v1/studies/{id}/completion/opened        followed the link
|
| THIS FILE DECIDES NOTHING. Whether the button is live, what it says, why it
| is disabled and which form URL to show all come from the API. There is not a
| second copy of the rules here — not even the button label.
|
| ONE MODAL PER PAGE. It is built once and appended to <body>, not rendered per
| button: the My studies page can show a dozen studies, and a dozen copies of
| the same element means duplicate ids, broken label/for pairs and focus
| landing in the wrong dialog.
|
| Conventions §7: DOMContentLoaded wrapper, shared `api` helper (never forked),
| esc() on everything from the database, full Tailwind class names.
*/

document.addEventListener('DOMContentLoaded', function () {

    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    var LIVE     = 'rounded-lg bg-plum/12 px-3.5 py-2 font-mono text-[11.5px] text-plum '
                 + 'transition hover:bg-plum/20 disabled:cursor-not-allowed disabled:opacity-50';
    var DEAD     = 'rounded-lg bg-steel/12 px-3.5 py-2 font-mono text-[11.5px] text-steel '
                 + 'cursor-not-allowed';
    var PRIMARY  = 'rounded-lg bg-plum/12 px-3.5 py-2 font-mono text-[11.5px] text-plum '
                 + 'transition hover:bg-plum/20 disabled:cursor-not-allowed disabled:opacity-50';
    var QUIET    = 'rounded-lg border border-line px-3.5 py-2 font-mono text-[11.5px] text-steel '
                 + 'transition hover:border-plum hover:text-plum';

    /* =========================================================================
       THE MODAL
       ========================================================================= */

    var modal = {
        root: null,
        studyId: null,
        trigger: null,     // what opened it, so focus can go back there
        onDone: null,      // called after a successful claim
    };

    function buildModal() {
        var el = document.createElement('div');

        el.setAttribute('data-completion-modal', '');
        el.className = 'fixed inset-0 z-[100] hidden items-center justify-center p-4';

        el.innerHTML =
              /* The blurred backdrop. Clicking it closes — same as Esc. */
              '<div class="absolute inset-0 bg-ink/60 backdrop-blur-sm" data-modal-backdrop></div>'

            + '<div class="relative w-full max-w-md rounded-2xl border border-line bg-surface p-5 shadow-2xl"'
            +      ' role="dialog" aria-modal="true" aria-labelledby="completion-modal-title">'

            +   '<div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Complete study</div>'
            +   '<h2 class="mt-1 text-[16px] font-semibold text-ink" id="completion-modal-title"></h2>'

            +   '<p class="mt-3 text-[13px] leading-relaxed text-dim">'
            +     'Open the study\'s form, fill it in, then come back and confirm below. '
            +     'The researcher checks your answers before it counts as complete.'
            +   '</p>'

            +   '<div class="mt-4">'
            +     '<a href="#" target="_blank" rel="noopener noreferrer" data-modal-form '
            +        'class="' + PRIMARY + ' inline-block">Open the form ↗</a>'
            +   '</div>'

            +   '<div class="mt-4">'
            +     '<label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" '
            +            'for="completion-note">Anything the researcher should know? (optional)</label>'
            +     '<textarea id="completion-note" data-modal-note rows="3" '
            +       'class="w-full rounded-lg border border-line-hi bg-surface-soft px-3 py-2 text-[13px] text-ink" '
            +       'placeholder="Optional"></textarea>'
            +     '<div class="mt-1 text-right font-mono text-[10.5px] text-steel" data-modal-count></div>'
            +   '</div>'

            +   '<div class="mt-2 text-[12px] text-flame" data-modal-error></div>'

            +   '<div class="mt-4 flex flex-wrap items-center justify-end gap-2">'
            +     '<button type="button" class="' + QUIET + '" data-modal-cancel>Cancel</button>'
            +     '<button type="button" class="' + PRIMARY + '" data-modal-confirm>'
            +       'I\'ve submitted the form</button>'
            +   '</div>'

            + '</div>';

        document.body.appendChild(el);

        el.querySelector('[data-modal-backdrop]').addEventListener('click', closeModal);
        el.querySelector('[data-modal-cancel]').addEventListener('click', closeModal);

        /* Following the link is recorded but never required. Gating the
           confirm button on "you must open the form first" is trivially
           defeated and punishes anyone who opened it yesterday. */
        el.querySelector('[data-modal-form]').addEventListener('click', function () {
            if (modal.studyId) {
                api.patch('/api/v1/studies/' + encodeURIComponent(modal.studyId) + '/completion/opened', {})
                    .catch(function () { /* evidence, not a gate — never block the link */ });
            }
        });

        el.querySelector('[data-modal-note]').addEventListener('input', updateCount);
        el.querySelector('[data-modal-confirm]').addEventListener('click', confirmClaim);

        /* Esc closes, and Tab is trapped inside the panel. A modal you cannot
           leave with the keyboard is a broken modal. */
        el.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal();
                return;
            }

            if (event.key !== 'Tab') { return; }

            var focusable = el.querySelectorAll(
                'a[href], button:not([disabled]), textarea, input, select'
            );

            if (!focusable.length) { return; }

            var first = focusable[0];
            var last  = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        modal.root = el;
        return el;
    }

    function updateCount() {
        var note  = modal.root.querySelector('[data-modal-note]');
        var count = modal.root.querySelector('[data-modal-count]');
        var max   = parseInt(note.getAttribute('maxlength') || '280', 10);

        count.textContent = note.value.length + ' / ' + max;
    }

    function openModal(studyId, title, formUrl, noteMax, trigger, onDone) {
        var el = modal.root || buildModal();

        modal.studyId = studyId;
        modal.trigger = trigger || null;
        modal.onDone  = onDone || null;

        el.querySelector('#completion-modal-title').textContent = title || 'This study';
        el.querySelector('[data-modal-form]').setAttribute('href', formUrl || '#');
        el.querySelector('[data-modal-error]').textContent = '';

        var note = el.querySelector('[data-modal-note]');
        note.value = '';
        note.setAttribute('maxlength', String(noteMax || 280));
        updateCount();

        var confirm = el.querySelector('[data-modal-confirm]');
        confirm.disabled = false;
        confirm.textContent = "I've submitted the form";

        el.classList.remove('hidden');
        el.classList.add('flex');

        /* Stop the page behind from scrolling while the modal is up. */
        document.body.classList.add('overflow-hidden');

        el.querySelector('[data-modal-form]').focus();
    }

    function closeModal() {
        if (!modal.root) { return; }

        modal.root.classList.add('hidden');
        modal.root.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');

        if (modal.trigger) {
            modal.trigger.focus();
        }

        modal.studyId = null;
        modal.trigger = null;
        modal.onDone  = null;
    }

    function confirmClaim() {
        var el      = modal.root;
        var button  = el.querySelector('[data-modal-confirm]');
        var note    = el.querySelector('[data-modal-note]').value.trim();
        var errorEl = el.querySelector('[data-modal-error]');
        var studyId = modal.studyId;
        var done    = modal.onDone;

        button.disabled = true;
        button.textContent = 'Sending…';
        errorEl.textContent = '';

        api.post('/api/v1/studies/' + encodeURIComponent(studyId) + '/completion',
                 note ? { note: note } : {})
            .then(function (response) {
                closeModal();
                if (done) { done(response); }
            })
            .catch(function (error) {
                errorEl.textContent = error.message;
                button.disabled = false;
                button.textContent = "I've submitted the form";
            });
    }

    /* =========================================================================
       THE BUTTON — one per [data-completion-block]
       ========================================================================= */

    function initBlock(block) {
        if (block.getAttribute('data-completion-ready')) { return; }
        block.setAttribute('data-completion-ready', '1');

        var studyId = block.getAttribute('data-study-id');
        var title   = block.getAttribute('data-study-title') || 'This study';
        var control = block.querySelector('[data-completion-control]');
        var note    = block.querySelector('[data-completion-note]');

        var URL = '/api/v1/studies/' + encodeURIComponent(studyId) + '/completion';

        function render(d) {
            if (d.can_complete) {
                control.innerHTML = '<button type="button" data-open-completion class="' + LIVE + '">'
                                  + esc(d.label) + '</button>';
            } else {
                control.innerHTML = '<button type="button" disabled class="' + DEAD + '">'
                                  + esc(d.label) + '</button>';
            }

            if (d.claim && d.claim.status === 'submitted') {
                note.textContent = 'Submitted ' + (d.claim.submitted_on || '') + ' — awaiting confirmation.';
            } else if (d.claim && d.claim.status === 'rejected') {
                note.textContent = 'The researcher did not accept the last submission. You can send it again.';
            } else if (d.message) {
                note.textContent = d.message;
            } else {
                note.textContent = '';
            }

            block._state = d;
        }

        function load() {
            api.get(URL)
                .then(function (response) { render(response.data); })
                .catch(function (error) {
                    /* A researcher on the study page gets a 403 here. That is
                       expected, not an error worth shouting about. */
                    block.classList.add('hidden');
                    if (error && error.status !== 403) {
                        note.textContent = error.message;
                    }
                });
        }

        control.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-open-completion]');

            if (!trigger) { return; }

            var state = block._state || {};

            openModal(
                studyId,
                state.study_title || title,
                state.form_url,
                state.note_max,
                trigger,
                function (response) {
                    render(response.data.status);
                    note.textContent = response.message;
                }
            );
        });

        load();
    }

    document.querySelectorAll('[data-completion-block]').forEach(initBlock);

    /* =========================================================================
       THE "MY STUDIES" PAGE
       ========================================================================= */

    var page = document.getElementById('my-studies-page');

    if (!page) { return; }

    var lists = {
        active:  page.querySelector('[data-active-list]'),
        waiting: page.querySelector('[data-waiting-list]'),
        done:    page.querySelector('[data-done-list]'),
        closed:  page.querySelector('[data-closed-list]'),
    };

    var caption = page.querySelector('[data-active-caption]');
    var alertEl = page.querySelector('[data-studies-alert]');

    /* Stage → which panel. Kept here rather than scattered through render()
       so adding a stage is one line. */
    var GROUP = {
        confirmed: 'active',
        scheduled: 'active',
        applied:   'waiting',
        screened:  'waiting',
        completed: 'done',
        paid:      'done',
        rejected:  'closed',
        no_show:   'closed',
    };

    var STAGE_LABEL = {
        applied: 'Applied', screened: 'Screened', confirmed: 'Confirmed',
        scheduled: 'Scheduled', completed: 'Completed', paid: 'Paid',
        no_show: 'No show', rejected: 'Not selected',
    };

    function card(row, withButton) {
        return ''
            + '<div class="rounded-xl border border-line bg-surface-soft p-3.5">'
            +   '<div class="flex flex-wrap items-start justify-between gap-2">'
            +     '<div class="min-w-0">'
            +       '<a href="' + esc(row.url) + '" class="text-[13.5px] font-semibold text-ink '
            +          'underline-offset-2 hover:underline">' + esc(row.title) + '</a>'
            +       '<div class="mt-1 font-mono text-[10.5px] uppercase tracking-[0.14em] text-steel">'
            +         esc(STAGE_LABEL[row.stage] || row.stage)
            +         (row.applied_on ? ' · applied ' + esc(row.applied_on) : '')
            +       '</div>'
            +     '</div>'
            +   '</div>'
            +   (withButton
                    ? '<div data-completion-block data-study-id="' + esc(row.study_id) + '" '
                      + 'data-study-title="' + esc(row.title) + '">'
                      + '<div class="mt-3" data-completion-control></div>'
                      + '<p class="mt-1.5 text-[12px] text-dim" data-completion-note></p>'
                      + '</div>'
                    : '')
            + '</div>';
    }

    function renderAll(rows) {
        var buckets = { active: [], waiting: [], done: [], closed: [] };

        rows.forEach(function (row) {
            var group = GROUP[row.stage] || 'waiting';
            buckets[group].push(card(row, group === 'active'));
        });

        Object.keys(lists).forEach(function (key) {
            lists[key].innerHTML = buckets[key].length
                ? buckets[key].join('')
                : '<p class="text-[12.5px] text-dim">Nothing here yet.</p>';
        });

        caption.textContent = buckets.active.length
            ? buckets.active.length + ' study' + (buckets.active.length === 1 ? '' : ' studies')
              + ' you can complete when you\'re ready.'
            : 'No active studies right now.';

        /* The cards were just created, so their completion blocks still need
           wiring. initBlock() guards against being run twice. */
        page.querySelectorAll('[data-completion-block]').forEach(initBlock);
    }

    api.get('/api/v1/participants/me/applications')
        .then(function (response) { renderAll(response.data.applications || []); })
        .catch(function (error) {
            alertEl.innerHTML = '<p class="rounded-lg bg-flame/10 px-3 py-2 text-[12.5px] text-flame">'
                              + esc(error.message) + '</p>';
        });
});
