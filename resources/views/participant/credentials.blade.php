@extends('layouts.app')

@section('title', 'Credentials')

@section('content')
{{--
    API-DRIVEN PAGE — see participant/reliability.blade.php for the same
    pattern. No Blade data here: every value is fetched as JSON.

    This page calls TWO endpoints on load:
        GET .../credentials                    -> badge, ladder, progress
        GET .../credentials/completed-studies  -> the history list
    They run in parallel, so the page is not twice as slow.
--}}
<div class="wrap pb-20" id="credentials-page" data-user-id="{{ auth()->id() }}">

    <x-page-header
        eyebrow="Participant · Credentials"
        title="Your level is earned, not applied for."
        subtitle="Every study you complete is counted automatically. There is no form to fill in and no approval to wait on." />

    <div id="page-alert" class="mb-6 hidden rounded-xl px-4 py-3 text-[13px]"></div>

    <div class="grid items-start gap-6 lg:grid-cols-[1fr_1.4fr]">

        {{-- ================= LEFT: the badge ================= --}}
        <div class="space-y-6">

            <div class="reveal relative overflow-hidden rounded-panel bg-gradient-to-br from-ink to-plum
                        p-8 text-center text-white shadow-[0_20px_46px_-22px_rgba(79,58,101,.7)]">

                <div class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full"
                     style="background:radial-gradient(circle, rgba(223,240,234,.2), transparent 70%);"></div>

                <div class="relative">
                    <p class="font-mono text-[10.5px] uppercase tracking-[0.2em] text-steel">
                        Current credential
                    </p>

                    <div class="relative mx-auto mt-5 h-[180px] w-[180px]">
                        <svg width="180" height="180" class="-rotate-90">
                            <circle cx="90" cy="90" r="76" fill="none"
                                    stroke="rgba(255,255,255,.14)" stroke-width="12" />
                            <circle id="cred-ring" cx="90" cy="90" r="76" fill="none"
                                    stroke="#DFF0EA" stroke-width="12" stroke-linecap="round"
                                    stroke-dasharray="477.5" stroke-dashoffset="477.5"
                                    style="transition: stroke-dashoffset .7s ease-out" />
                        </svg>

                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span id="cred-emoji" class="text-[34px] leading-none">⚪</span>
                            <span id="cred-label" class="mt-2 font-display text-[22px] font-semibold text-white">—</span>
                            <span id="cred-count" class="font-mono text-[11px] text-steel">—</span>
                        </div>
                    </div>

                    <p id="cred-next" class="mt-5 text-[13.5px] leading-relaxed text-white/80">&nbsp;</p>

                    {{-- Was a <form method="POST">. Now a fetch() call. --}}
                    <button type="button" id="recalculate-btn"
                            class="mt-6 w-full rounded-xl bg-mint px-5 py-3 text-sm font-semibold text-ink
                                   transition hover:-translate-y-px disabled:cursor-not-allowed disabled:opacity-60">
                        Recalculate my level
                    </button>

                    <p class="mt-3 text-[11px] leading-relaxed text-white/50">
                        Normally this runs by itself when a researcher marks a session
                        complete. The button is here so you can watch it work.
                    </p>
                </div>
            </div>

            <x-panel label="How the rule works" class="reveal reveal-d1">
                <p class="text-[13px] leading-relaxed text-dim">
                    Your level is read straight off one number: how many
                    <b class="text-ink">study_participations</b> rows you have at stage
                    <b class="text-ink">completed</b> or <b class="text-ink">paid</b>.
                </p>
                <p class="mt-3 text-[13px] leading-relaxed text-dim">
                    Nothing else affects it — not karma, not your profile, not how long
                    you've been a member. That is deliberate: a credential nobody can
                    game is a credential researchers can trust.
                </p>
            </x-panel>
        </div>

        {{-- ================= RIGHT: ladder + history ================= --}}
        <div class="space-y-6">

            <x-panel label="The ladder" note="Thresholds come from the CredentialLevel enum." class="reveal reveal-d1">
                <div class="space-y-3" id="ladder-list">
                    <p class="py-4 text-center text-[13px] text-dim">Loading your level…</p>
                </div>
            </x-panel>

            <x-panel label="Studies that count towards your level" class="reveal reveal-d2">
                <x-slot:action><span id="counted-label">—</span></x-slot:action>

                <div id="completed-list">
                    <p class="py-3 text-[13.5px] text-dim">Loading…</p>
                </div>
            </x-panel>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    var page = document.getElementById('credentials-page');
    if (!page) { return; }

    var userId = page.dataset.userId;

    var SHOW_URL    = '/api/v1/participants/' + userId + '/credentials';
    var STUDIES_URL = SHOW_URL + '/completed-studies';
    var RECALC_URL  = SHOW_URL + '/recalculate';

    var RING_LENGTH = 2 * Math.PI * 76;   // matches r="76" on the svg circle

    /* Tier emoji is pure decoration, so it lives here rather than in the enum. */
    var EMOJI = { none: '⚪', bronze: '🥉', gold: '🥈', expert: '🏆' };

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
            bronze:  'bg-steel/20 text-ink',
            gold:    'bg-star/20 text-[#B4832E]',
            expert:  'bg-plum/15 text-plum',
            ok:      'bg-ok/12 text-ok',
            plum:    'bg-plum/12 text-plum',
            neutral: 'bg-steel/18 text-steel',
        };
        var cls = tones[tone] || tones.neutral;

        return '<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-1 '
             + 'font-mono text-[10.5px] tracking-wide ' + cls + '">' + esc(text) + '</span>';
    }

    /* Rebuild of x-progress, default plum tone. */
    function progress(value, max) {
        var pct = Math.min(100, Math.max(0, Math.round((value / Math.max(1, max)) * 100)));

        return '<div class="w-full">'
             +   '<div class="h-2.5 w-full overflow-hidden rounded-full bg-steel/25" '
             +        'role="progressbar" aria-valuenow="' + value + '" aria-valuemin="0" '
             +        'aria-valuemax="' + max + '">'
             +     '<div class="h-full rounded-full transition-[width] duration-700 ease-out bg-plum" '
             +          'style="width: ' + pct + '%"></div>'
             +   '</div>'
             + '</div>';
    }

    /* --- The badge ring and its caption --- */
    function renderBadge(data) {
        document.getElementById('cred-ring').setAttribute('stroke-dasharray', RING_LENGTH);
        document.getElementById('cred-ring').setAttribute(
            'stroke-dashoffset', RING_LENGTH * (1 - data.progress_percent / 100)
        );

        document.getElementById('cred-emoji').textContent = EMOJI[data.credential_level] || '⚪';
        document.getElementById('cred-label').textContent = data.credential_label;
        document.getElementById('cred-count').textContent = data.completed_studies_count + ' completed';

        var line = document.getElementById('cred-next');

        if (data.is_max_level) {
            line.textContent = 'You have reached the highest tier on the platform.';
        } else {
            var n = data.studies_to_next_level;
            line.innerHTML = '<b class="text-mint">' + n + '</b> more completed '
                           + (n === 1 ? 'study' : 'studies') + ' to reach '
                           + '<b class="text-mint">' + esc(data.next_level_label) + '</b>.';
        }
    }

    /* --- The three-rung ladder --- */
    function renderLadder(data) {
        var count = data.completed_studies_count;
        var html  = '';

        data.ladder.forEach(function (tier) {
            var isCurrent = tier.level === data.credential_level;
            var earned    = tier.unlocked;

            var box = isCurrent
                ? 'border-plum bg-plum/5'
                : (earned ? 'border-ok/30 bg-ok/5' : 'border-line opacity-60');

            var statusBadge = '';
            if (isCurrent)   { statusBadge = badge('plum', 'You are here'); }
            else if (earned) { statusBadge = badge('ok', 'Earned'); }

            html +=
              '<div class="flex items-start gap-4 rounded-xl border p-4 transition ' + box + '">'
            +   '<div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-surface-soft text-xl">'
            +     (EMOJI[tier.level] || '⚪')
            +   '</div>'
            +   '<div class="min-w-0 flex-1">'
            +     '<div class="flex flex-wrap items-center gap-2">'
            +       '<b class="text-[14.5px] text-ink">' + esc(tier.label) + '</b>'
            +       badge(tier.level, tier.min_completions + '+ studies')
            +       statusBadge
            +     '</div>'
            +     '<p class="mt-1.5 text-[13px] leading-relaxed text-dim">' + esc(tier.perk) + '</p>'
            +     (earned ? '' : '<div class="mt-3">' + progress(count, tier.min_completions) + '</div>')
            +   '</div>'
            + '</div>';
        });

        document.getElementById('ladder-list').innerHTML = html;
    }

    /* --- The history list, from the second endpoint --- */
    function renderCompleted(payload) {
        document.getElementById('counted-label').textContent = payload.count + ' counted';

        if (payload.count === 0) {
            document.getElementById('completed-list').innerHTML =
                '<p class="py-3 text-[13.5px] text-dim">'
              + 'No completed studies yet. Your first one earns you Bronze.</p>';
            return;
        }

        var html = '';

        payload.studies.forEach(function (session) {
            var meta = esc(session.researcher_name || 'Unknown researcher');
            if (session.completed_on) { meta += ' · ' + esc(session.completed_on); }

            html +=
              '<div class="flex items-center gap-3.5 border-b border-line py-3.5 last:border-none last:pb-1">'
            +   '<div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] '
            +        'bg-ok/10 text-[13px] text-ok">✓</div>'
            +   '<div class="min-w-0 flex-1">'
            +     '<div class="truncate text-[13.5px] font-semibold text-ink">'
            +       esc(session.title || 'Untitled study')
            +     '</div>'
            +     '<div class="font-mono text-[11px] text-steel">' + meta + '</div>'
            +   '</div>'
            +   badge(session.stage === 'paid' ? 'ok' : 'neutral', session.stage_label)
            + '</div>';
        });

        document.getElementById('completed-list').innerHTML = html;
    }

    function alertBox(message, ok) {
        var box = document.getElementById('page-alert');
        box.className = 'mb-6 rounded-xl px-4 py-3 text-[13px] '
                      + (ok ? 'bg-ok/10 text-ok' : 'bg-danger/10 text-danger');
        box.textContent = message;
    }

    function hideAlert() {
        document.getElementById('page-alert').className =
            'mb-6 hidden rounded-xl px-4 py-3 text-[13px]';
    }

    /* --- 1. First load: both endpoints at once --- */
    Promise.all([api.get(SHOW_URL), api.get(STUDIES_URL)])
        .then(function (responses) {
            renderBadge(responses[0].data);
            renderLadder(responses[0].data);
            renderCompleted(responses[1].data);
        })
        .catch(function (error) {
            document.getElementById('ladder-list').innerHTML =
                '<p class="py-4 text-center text-[13px] text-danger">Could not load your level.</p>';
            document.getElementById('completed-list').innerHTML = '';
            alertBox(error.message, false);
        });

    /* --- 2. Recalculate. The study list cannot change from a recount, so
             only the badge and ladder are repainted. --- */
    document.getElementById('recalculate-btn').addEventListener('click', function () {
        var button = this;

        button.disabled = true;
        button.textContent = 'Recalculating…';
        hideAlert();

        api.post(RECALC_URL)
            .then(function (response) {
                renderBadge(response.data);
                renderLadder(response.data);
                alertBox(response.message, true);
            })
            .catch(function (error) {
                alertBox(error.message, false);
            })
            .then(function () {
                button.disabled = false;
                button.textContent = 'Recalculate my level';
            });
    });
});
</script>
@endpush