@extends('layouts.app')

@section('title', 'Reliability')

@section('content')
{{--
    API-DRIVEN PAGE
    ---------------
    There is no Blade data in this file. Every {{ }} that used to print a
    score, a factor or a name has been replaced by an empty element with an
    id, filled by the script at the bottom from:

        GET /api/v1/participants/{user}/reliability

    The only server value here is the logged-in user's own id, on the wrapper
    div below. That is identity, not feature data — it tells the page WHO to
    ask about. Everything it then displays comes from the API.
--}}
<div class="wrap pb-20" id="reliability-page" data-user-id="{{ auth()->id() }}">

    <x-page-header
        eyebrow="Participant · Reliability"
        title="Show up, and your score speaks for you."
        subtitle="Three weighted factors, all measured from what you actually did on the platform. A higher score moves you up the queue when a study has more applicants than seats." />

    {{-- Filled by JS after a recalculate, or on failure. --}}
    <div id="page-alert" class="mb-6 hidden rounded-xl px-4 py-3 text-[13px]"></div>

    <div class="grid items-start gap-6 lg:grid-cols-[1.4fr_1fr]">

        {{-- ================= LEFT: the breakdown ================= --}}
        <div class="space-y-6">

            <x-panel label="How your score is built"
                     note="Each factor is measured from your real sessions, then multiplied by its weight."
                     class="reveal">

                <div id="factor-list">
                    <p class="py-6 text-center text-[13px] text-dim">Loading your score…</p>
                </div>

                <div class="mt-5 flex items-center justify-between rounded-xl bg-surface-soft px-4 py-3.5">
                    <span class="text-[13px] font-semibold text-ink">Total</span>
                    <span class="font-mono text-[14px] text-plum">
                        <span id="breakdown-total">—</span> / 100
                    </span>
                </div>
            </x-panel>

            {{-- Seat auction, ranked against real participants --}}
            <x-panel label="Where it gets you — seat auctions" class="reveal reveal-d1">
                <p class="mb-4 text-[13px] leading-relaxed text-dim">
                    When a paid study has fewer seats than applicants, seats go to the
                    highest reliability scores rather than whoever clicked first. Here is
                    where you would land against
                    <b class="text-ink" id="auction-rivals">—</b> other real participants
                    for a study with <b class="text-ink" id="auction-seats">—</b> seats.
                </p>

                <div id="auction-banner" class="mb-4 hidden rounded-xl px-4 py-3 text-[13px]"></div>

                <div id="auction-pool"></div>
            </x-panel>
        </div>

        {{-- ================= RIGHT: the gauge ================= --}}
        <div class="space-y-6">

            <div class="reveal reveal-d1 relative overflow-hidden rounded-panel bg-gradient-to-br from-ink to-plum
                        p-8 text-center text-white shadow-[0_20px_46px_-22px_rgba(79,58,101,.7)]">

                <div class="pointer-events-none absolute -left-16 -top-20 h-64 w-64 rounded-full"
                     style="background:radial-gradient(circle, rgba(223,240,234,.2), transparent 70%);"></div>

                <div class="relative">
                    <p class="font-mono text-[10.5px] uppercase tracking-[0.2em] text-steel">
                        Reliability score
                    </p>

                    <div class="relative mx-auto mt-5 h-[180px] w-[180px]">
                        <svg width="180" height="180" class="-rotate-90">
                            <circle cx="90" cy="90" r="76" fill="none"
                                    stroke="rgba(255,255,255,.14)" stroke-width="13" />
                            <circle id="gauge-arc" cx="90" cy="90" r="76" fill="none"
                                    stroke="#DFF0EA" stroke-width="13" stroke-linecap="round"
                                    stroke-dasharray="477.5" stroke-dashoffset="477.5"
                                    style="transition: stroke-dashoffset .7s ease-out" />
                        </svg>

                        <div class="absolute left-1/2 top-1/2 flex -translate-x-1/2 -translate-y-1/2 items-baseline gap-1 whitespace-nowrap">
                            <span id="score-value" class="font-display text-[48px] font-semibold leading-none text-white">
                                —
                            </span>
                            <span class="font-mono text-[12px] text-steel">/ 100</span>
                        </div>
                    </div>

                    <span id="band-label"
                          class="mt-4 inline-block rounded-full bg-white/15 px-3.5 py-1.5
                                 font-mono text-[11px] uppercase tracking-wider text-white">
                        —
                    </span>

                    <p id="band-message" class="mt-3 text-[13px] leading-relaxed text-white/75">&nbsp;</p>

                    {{--
                        This was a <form method="POST">. It is now a plain button:
                        the click calls the API with fetch() and repaints the page
                        from the JSON response. No page reload.
                    --}}
                    <button type="button" id="recalculate-btn"
                            class="mt-6 w-full rounded-xl bg-mint px-5 py-3 text-sm font-semibold text-ink
                                   transition hover:-translate-y-px disabled:cursor-not-allowed disabled:opacity-60">
                        Recalculate my score
                    </button>
                </div>
            </div>

            <x-panel label="The weights" class="reveal reveal-d2">
                <p class="mb-4 text-[13px] leading-relaxed text-dim">
                    These live in <code class="font-mono text-[12px] text-plum">config/platform.php</code>,
                    not in the calculation itself. Change one and both the maths and the
                    labels above update together.
                </p>

                <dl class="divide-y divide-line text-[13px]" id="weights-list"></dl>
            </x-panel>

            <x-panel label="Saved on your profile" class="reveal reveal-d2">
                <p class="text-[12.5px] leading-relaxed text-dim">
                    The four columns below are what other pages read. They only change
                    when a recalculation runs — the page above always shows live maths,
                    so if they disagree, you have unsaved changes.
                </p>

                <dl class="mt-3 divide-y divide-line text-[13px]">
                    <div class="flex justify-between py-2"><dt class="text-dim">rel_attendance</dt>
                        <dd class="font-mono text-ink" id="stored-attendance">—</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-dim">rel_completion</dt>
                        <dd class="font-mono text-ink" id="stored-completion">—</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-dim">rel_reviews</dt>
                        <dd class="font-mono text-ink" id="stored-reviews">—</dd></div>
                    <div class="flex justify-between py-2"><dt class="font-semibold text-ink">reliability_score</dt>
                        <dd class="font-mono text-plum" id="stored-score">—</dd></div>
                </dl>
            </x-panel>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
/*
| ---------------------------------------------------------------------------
| Reliability page — all rendering happens here, from API JSON.
| ---------------------------------------------------------------------------
|
| DOMContentLoaded is required. resources/js/app.js is loaded as a module,
| which the browser defers until after the HTML is parsed. Without this
| wrapper, `api` would not exist yet.
*/
document.addEventListener('DOMContentLoaded', function () {

    var page = document.getElementById('reliability-page');
    if (!page) { return; }

    var userId = page.dataset.userId;

    var SHOW_URL      = '/api/v1/participants/' + userId + '/reliability';
    var RECALC_URL    = SHOW_URL + '/recalculate';

    var GAUGE_LENGTH  = 2 * Math.PI * 76;   // matches r="76" on the svg circle

    /* --- Escape anything that came from the database before it becomes HTML.
           Participant names are user-supplied, so this is not optional. --- */
    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* --- Rebuilds of the Blade components, so the markup stays identical.
           Class strings are written out in full: Tailwind scans this file for
           literal class names, and a name built by joining strings would
           never be found, so the style would silently go missing. --- */

    function badge(tone, text) {
        var tones = {
            ok:      'bg-ok/12 text-ok',
            neutral: 'bg-steel/18 text-steel',
        };
        var cls = tones[tone] || tones.neutral;

        return '<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-1 '
             + 'font-mono text-[10.5px] tracking-wide ' + cls + '">' + esc(text) + '</span>';
    }

    function progress(value, tone) {
        var fills = {
            plum:  'bg-plum',
            ok:    'bg-ok',
            flame: 'bg-flame',
        };
        var fill = fills[tone] || fills.plum;
        var pct  = Math.min(100, Math.max(0, Math.round(value)));

        return '<div class="w-full">'
             +   '<div class="h-2.5 w-full overflow-hidden rounded-full bg-steel/25" '
             +        'role="progressbar" aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100">'
             +     '<div class="h-full rounded-full transition-[width] duration-700 ease-out ' + fill + '" '
             +          'style="width: ' + pct + '%"></div>'
             +   '</div>'
             + '</div>';
    }

    /* --- The three factor rows --- */
    function renderFactors(data) {
        var toneFor = { attendance: 'ok', reviews: 'flame', completion: 'plum' };
        var html = '';

        Object.keys(data.factors).forEach(function (column) {
            var f    = data.factors[column];
            var tone = toneFor[f.key] || 'plum';
            var detailColour = f.has_data ? 'text-steel' : 'text-flame';

            html +=
              '<div class="border-b border-line py-5 first:pt-1 last:border-none last:pb-1">'
            +   '<div class="flex flex-wrap items-center justify-between gap-2">'
            +     '<div class="flex items-center gap-2.5">'
            +       '<b class="text-[14px] text-ink">' + esc(f.label) + '</b>'
            +       badge('neutral', f.weight + '% weight')
            +     '</div>'
            +     '<span class="font-display text-[22px] font-semibold text-ink">' + f.live_value + '</span>'
            +   '</div>'
            +   '<p class="mt-1 text-[12.5px] text-dim">' + esc(f.description) + '</p>'
            +   '<div class="mt-3">' + progress(f.live_value, tone) + '</div>'
            +   '<div class="mt-2.5 flex flex-wrap items-center justify-between gap-2">'
            +     '<span class="font-mono text-[11px] ' + detailColour + '">' + esc(f.detail) + '</span>'
            +     '<span class="font-mono text-[11px] text-plum">'
            +       f.live_value + ' × ' + f.weight + '% = ' + f.live_contribution + ' points'
            +     '</span>'
            +   '</div>'
            + '</div>';
        });

        document.getElementById('factor-list').innerHTML = html;
        document.getElementById('breakdown-total').textContent = data.live_total;
    }

    /* --- Seat auction panel --- */
    function renderAuction(auction) {
        document.getElementById('auction-rivals').textContent = auction.rivals;
        document.getElementById('auction-seats').textContent  = auction.seats;

        var banner = document.getElementById('auction-banner');
        banner.className = 'mb-4 rounded-xl px-4 py-3 text-[13px] '
                         + (auction.won_seat ? 'bg-ok/10 text-ok' : 'bg-steel/15 text-dim');
        banner.innerHTML = '◆ You rank <b>#' + auction.rank + '</b> — '
                         + (auction.won_seat ? 'you&rsquo;d win a seat.' : 'not in the seats yet.');

        var html = '';

        auction.pool.forEach(function (row, i) {
            var inSeat     = i < auction.seats;
            var rowClasses = row.you ? 'border-plum bg-plum/5' : 'border-line';
            var rankColour = inSeat ? 'text-ok' : 'text-steel';
            var nameClass  = row.you ? 'font-semibold text-plum' : 'text-ink';

            html +=
              '<div class="mb-2 flex items-center gap-3.5 rounded-xl border px-4 py-3 last:mb-0 ' + rowClasses + '">'
            +   '<span class="w-8 font-mono text-[12px] ' + rankColour + '">#' + (i + 1) + '</span>'
            +   '<span class="min-w-0 flex-1 truncate text-[13.5px] ' + nameClass + '">' + esc(row.name) + '</span>'
            +   (inSeat ? badge('ok', 'Seat') : '')
            +   '<span class="font-mono text-[13px] text-ink">' + row.score + '</span>'
            + '</div>';
        });

        document.getElementById('auction-pool').innerHTML = html;
    }

    /* --- Gauge, band, weights table, stored columns --- */
    function renderSummary(data) {
        var score = Math.min(100, Math.max(0, data.reliability_score));

        document.getElementById('gauge-arc').setAttribute('stroke-dasharray', GAUGE_LENGTH);
        document.getElementById('gauge-arc').setAttribute(
            'stroke-dashoffset', GAUGE_LENGTH * (1 - score / 100)
        );

        document.getElementById('score-value').textContent   = data.reliability_score;
        document.getElementById('band-label').textContent    = data.band.label;
        document.getElementById('band-message').textContent  = data.band.message;

        var weightHtml  = '';
        var weightTotal = 0;

        Object.keys(data.weights).forEach(function (name) {
            var w = data.weights[name];
            weightTotal += w;

            weightHtml +=
              '<div class="flex justify-between py-2.5">'
            +   '<dt class="text-dim">' + esc(name.charAt(0).toUpperCase() + name.slice(1)) + '</dt>'
            +   '<dd class="font-mono text-ink">' + w + '%</dd>'
            + '</div>';
        });

        weightHtml +=
          '<div class="flex justify-between py-2.5">'
        +   '<dt class="font-semibold text-ink">Total</dt>'
        +   '<dd class="font-mono ' + (weightTotal === 100 ? 'text-ok' : 'text-danger') + '">'
        +     weightTotal + '%'
        +   '</dd>'
        + '</div>';

        document.getElementById('weights-list').innerHTML = weightHtml;

        document.getElementById('stored-attendance').textContent = data.stored.rel_attendance;
        document.getElementById('stored-completion').textContent = data.stored.rel_completion;
        document.getElementById('stored-reviews').textContent    = data.stored.rel_reviews;
        document.getElementById('stored-score').textContent      = data.stored.reliability_score;
    }

    function renderAll(data) {
        renderSummary(data);
        renderFactors(data);
        renderAuction(data.auction);
    }

    /* --- The status strip at the top of the page --- */
    function alert(message, ok) {
        var box = document.getElementById('page-alert');
        box.className = 'mb-6 rounded-xl px-4 py-3 text-[13px] '
                      + (ok ? 'bg-ok/10 text-ok' : 'bg-danger/10 text-danger');
        box.textContent = message;
    }

    function hideAlert() {
        document.getElementById('page-alert').className = 'mb-6 hidden rounded-xl px-4 py-3 text-[13px]';
    }

    /* --- 1. First load: fetch and paint --- */
    api.get(SHOW_URL)
        .then(function (response) {
            renderAll(response.data);
        })
        .catch(function (error) {
            document.getElementById('factor-list').innerHTML =
                '<p class="py-6 text-center text-[13px] text-danger">Could not load your score.</p>';
            alert(error.message, false);
        });

    /* --- 2. Recalculate: same page, no reload --- */
    document.getElementById('recalculate-btn').addEventListener('click', function () {
        var button = this;

        button.disabled = true;
        button.textContent = 'Recalculating…';
        hideAlert();

        api.post(RECALC_URL)
            .then(function (response) {
                renderAll(response.data);
                alert(response.message, true);
            })
            .catch(function (error) {
                alert(error.message, false);
            })
            .then(function () {
                button.disabled = false;
                button.textContent = 'Recalculate my score';
            });
    });
});
</script>
@endpush