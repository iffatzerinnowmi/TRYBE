/*
|------------------------------------------------------------------------------
| TRYBE — Refer a Friend  (Member 4)
|------------------------------------------------------------------------------
|
|     GET  /api/v1/referrals/me
|     POST /api/v1/referrals/me/code       { rotate: true }
|     GET  /api/v1/researchers/me/post-credits
|
| One file serves both the participant and researcher pages; the researcher
| version is marked with data-researcher and adds the credit ledger.
|
| Conventions §7: DOMContentLoaded wrapper, shared `api` helper (never
| forked), esc() on everything from the database, full Tailwind class names.
*/

document.addEventListener('DOMContentLoaded', function () {

    var page = document.getElementById('referrals-page');

    if (!page) {
        return;
    }

    var isResearcher = page.hasAttribute('data-researcher');

    var shareUrlEl = page.querySelector('[data-share-url]');
    var codeEl     = page.querySelector('[data-code]');
    var visitsEl   = page.querySelector('[data-visits]');
    var alertEl    = page.querySelector('[data-referral-alert]');
    var messagesEl = page.querySelector('[data-share-messages]');

    var labelEl    = page.querySelector('[data-progress-label]');
    var barEl      = page.querySelector('[data-progress-bar]');
    var detailEl   = page.querySelector('[data-progress-detail]');
    var previewEl  = page.querySelector('[data-reward-preview]');
    var rewardsEl  = page.querySelector('[data-rewards-list]');
    var referredEl = page.querySelector('[data-referred-list]');

    var balanceEl = page.querySelector('[data-credit-balance]');
    var noteEl    = page.querySelector('[data-credit-note]');
    var ledgerEl  = page.querySelector('[data-credit-ledger]');

    var copyButton   = page.querySelector('[data-copy-link]');
    var rotateButton = page.querySelector('[data-rotate-code]');

    var currentUrl = '';

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
        plum:    'bg-plum/12 text-plum',
        neutral: 'bg-steel/18 text-steel',
        flame:   'bg-flame/12 text-flame',
    };

    var STATUS_TONES = {
        qualified: 'ok',
        pending:   'neutral',
        flagged:   'flame',
    };

    function badge(tone, text) {
        var cls = BADGE_TONES[tone] || BADGE_TONES.neutral;

        return '<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-1 '
             + 'font-mono text-[10.5px] tracking-wide ' + cls + '">' + esc(text) + '</span>';
    }

    function notify(text, tone) {
        var cls = tone === 'error' ? 'bg-flame/10 text-flame' : 'bg-ok/10 text-ok';

        alertEl.innerHTML = '<p class="rounded-lg px-3 py-2 text-[12.5px] ' + cls + '">'
                          + esc(text) + '</p>';
    }

    /* ---- Share messages ------------------------------------------------ */

    function renderMessages(messages) {
        var rows = [];

        function row(label, text) {
            return '<div class="rounded-xl border border-line bg-surface-soft p-3">'
                +    '<div class="flex items-center justify-between gap-2">'
                +      '<span class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">'
                +        esc(label) + '</span>'
                +      '<button type="button" class="rounded-lg border border-line px-2 py-0.5 '
                +        'font-mono text-[10px] text-steel transition hover:border-plum hover:text-plum" '
                +        'data-copy-message>Copy</button>'
                +    '</div>'
                +    '<p class="mt-1.5 text-[12.5px] leading-relaxed text-ink" data-message-body>'
                +      esc(text) + '</p>'
                +  '</div>';
        }

        rows.push(row('WhatsApp', messages.whatsapp));
        rows.push(row('SMS', messages.sms));

        if (messages.email) {
            rows.push(row('Email — ' + messages.email.subject, messages.email.body));
        }

        messagesEl.innerHTML = rows.join('');
    }

    /* ---- Progress ------------------------------------------------------ */

    function renderProgress(data) {
        var p = data.progress;

        labelEl.textContent = p.label;
        barEl.style.width   = p.percent + '%';

        var bits = [];

        if (p.pending_count) {
            bits.push(p.pending_count + ' signed up but not qualified yet');
        }

        if (p.flagged_count) {
            bits.push(p.flagged_count + ' under review');
        }

        if (!p.reward_eligible) {
            bits.push('This account type does not unlock a referral reward.');
        } else if (p.remaining > 0) {
            bits.push(p.remaining + ' more to go');
        }

        detailEl.textContent = bits.join(' · ');

        if (previewEl && data.reward_preview) {
            var preview = data.reward_preview;
            var text    = preview.description || '—';

            if (preview.would_grant) {
                text += ' Next grant: ' + preview.would_grant + '.';
            }

            previewEl.textContent = text;
        }

        renderRewards(data.rewards || []);
    }

    function renderRewards(rewards) {
        if (!rewardsEl) {
            return;
        }

        if (!rewards.length) {
            rewardsEl.innerHTML = '';
            return;
        }

        rewardsEl.innerHTML = rewards.map(function (reward) {
            // `applied: false` means the reward is recorded but
            // CredentialService has not honoured it as a floor yet. Shown
            // rather than hidden — pretending it landed would be worse.
            var pending = reward.applied === false;

            return '<div class="rounded-xl border border-line bg-surface p-3">'
                +    '<div class="flex flex-wrap items-center justify-between gap-2">'
                +      '<span class="text-[13px] font-semibold text-ink">'
                +        esc(reward.type_label) + '</span>'
                +      badge(pending ? 'neutral' : 'ok',
                             pending ? 'Awaiting credential sync' : 'Unlocked')
                +    '</div>'
                +    '<p class="mt-1 font-mono text-[11px] text-steel">'
                +      'At ' + esc(reward.milestone) + ' referrals · ' + esc(reward.granted_on)
                +      (reward.granted_credential_level
                            ? ' · ' + esc(reward.granted_credential_level)
                            : '')
                +      (reward.post_credits ? ' · +' + esc(reward.post_credits) + ' post credit' : '')
                +    '</p>'
                +  '</div>';
        }).join('');
    }

    /* ---- Referred users ------------------------------------------------ */

    function referredRow(person) {
        var meta = person.status === 'qualified'
            ? 'Qualified ' + esc(person.qualified_on || '')
                + (person.qualifying_study ? ' · ' + esc(person.qualifying_study) : '')
            : 'Signed up ' + esc(person.signed_up_on || '');

        // A mixed-role pair is recorded and shown, but pays nothing. Saying
        // so stops "3 of 3 and no reward" from looking like a bug.
        var note = person.counts_towards_reward
            ? ''
            : '<div class="mt-1 text-[11.5px] text-dim">Different account type — does not count towards your reward.</div>';

        return '<div class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface px-3.5 py-3">'
            +    '<div class="min-w-0 flex-1">'
            +      '<div class="truncate text-[13.5px] font-semibold text-ink">' + esc(person.name) + '</div>'
            +      '<div class="font-mono text-[11px] text-steel">' + meta + '</div>'
            +      note
            +    '</div>'
            +    badge(STATUS_TONES[person.status] || 'neutral', person.status_label)
            +  '</div>';
    }

    function renderReferred(people) {
        referredEl.innerHTML = people.length
            ? people.map(referredRow).join('')
            : '<p class="text-[12.5px] text-dim">Nobody has signed up through your link yet.</p>';
    }

    /* ---- Researcher credit ledger -------------------------------------- */

    function loadCredits() {
        if (!isResearcher || !ledgerEl) {
            return;
        }

        api.get('/api/v1/researchers/me/post-credits')
            .then(function (response) {
                var data = response.data;

                balanceEl.textContent = data.balance;
                noteEl.textContent    = data.spending_note || '';

                ledgerEl.innerHTML = data.ledger.length
                    ? data.ledger.map(function (row) {
                        var sign = row.delta > 0 ? '+' : '';

                        return '<div class="flex items-center justify-between gap-3 border-b border-line py-2 last:border-none">'
                            +    '<span class="text-[12.5px] text-ink">' + esc(row.reason_label) + '</span>'
                            +    '<span class="font-mono text-[11px] text-steel">'
                            +      esc(row.created_on) + ' · ' + sign + esc(row.delta)
                            +    '</span>'
                            +  '</div>';
                    }).join('')
                    : '<p class="text-[12.5px] text-dim">No movements yet.</p>';
            })
            .catch(function (error) {
                ledgerEl.innerHTML = '<p class="text-[12.5px] text-flame">' + esc(error.message) + '</p>';
            });
    }

    /* ---- Load ---------------------------------------------------------- */

    function render(data) {
        currentUrl = data.share_url;

        shareUrlEl.textContent = data.share_url;
        codeEl.textContent     = data.code;
        visitsEl.textContent   = data.visits;

        renderMessages(data.share_messages || {});
        renderProgress(data);
        renderReferred(data.referred_users || []);
    }

    function load() {
        api.get('/api/v1/referrals/me')
            .then(function (response) {
                render(response.data);
                loadCredits();
            })
            .catch(function (error) {
                shareUrlEl.textContent = 'Could not load your referral link.';
                notify(error.message, 'error');
            });
    }

    /* ---- Copying -------------------------------------------------------
       navigator.clipboard is unavailable on plain http, which is exactly
       what the demo runs on (http://127.0.0.1:8000). The execCommand
       fallback is the difference between the demo working and not. */

    function copyText(text, button) {
        function done() {
            var original = button.textContent;
            button.textContent = 'Copied';
            setTimeout(function () { button.textContent = original; }, 1500);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                fallbackCopy(text, done);
            });

            return;
        }

        fallbackCopy(text, done);
    }

    function fallbackCopy(text, done) {
        var area = document.createElement('textarea');

        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.left = '-9999px';

        document.body.appendChild(area);
        area.select();

        try {
            document.execCommand('copy');
            done();
        } catch (e) {
            notify('Could not copy automatically — select the link and copy it.', 'error');
        }

        document.body.removeChild(area);
    }

    copyButton.addEventListener('click', function () {
        if (currentUrl) {
            copyText(currentUrl, copyButton);
        }
    });

    messagesEl.addEventListener('click', function (event) {
        var button = event.target.closest('[data-copy-message]');

        if (!button) {
            return;
        }

        var body = button.closest('div').parentElement.querySelector('[data-message-body]');

        if (body) {
            copyText(body.textContent, button);
        }
    });

    rotateButton.addEventListener('click', function () {
        rotateButton.disabled = true;

        api.post('/api/v1/referrals/me/code', { rotate: true })
            .then(function (response) {
                notify(response.message, 'success');
                rotateButton.disabled = false;
                load();
            })
            .catch(function (error) {
                rotateButton.disabled = false;
                notify(error.message, 'error');
            });
    });

    load();
});
