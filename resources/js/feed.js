/*
|------------------------------------------------------------------------------
| TRYBE — Study Recommendation Feed + skill-gap coach  (Member 4)
|------------------------------------------------------------------------------
|
|     GET  /api/v1/participants/me/feed
|     GET  /api/v1/participants/me/feed/filters
|     GET  /api/v1/participants/me/skill-gap
|     POST /api/v1/participants/me/skill-gap/refresh
|
| Two independent panels. The coach failing never stops the feed rendering,
| and vice versa — they are separate fetches with separate catch blocks.
|
| Conventions §7: DOMContentLoaded wrapper, shared `api` helper (never
| forked), esc() on everything from the database, full Tailwind class names.
*/

document.addEventListener('DOMContentLoaded', function () {

    /* ------------------------------------------------------------------
       The dashboard preview.

       A compact version of the same feed, on the participant dashboard. It
       calls the SAME endpoint with limit=4, so the preview and the full page
       can never disagree about ranking — that is why the dashboard no longer
       calls StudyMatchingService directly.
       ------------------------------------------------------------------ */
    var preview = document.querySelector('[data-recommended-preview]');

    if (preview) {
        renderPreview(preview);
    }

    function renderPreview(box) {
        function escape(text) {
            return String(text === null || text === undefined ? '' : text)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        api.get('/api/v1/participants/me/feed?limit=4')
            .then(function (response) {
                var studies = response.data.studies || [];

                if (!studies.length) {
                    box.innerHTML = '<p class="py-3 text-[13.5px] text-dim">'
                                  + 'Nothing to recommend yet. Add skills to your profile and '
                                  + 'studies will start matching you.</p>';
                    return;
                }

                box.innerHTML = studies.map(function (s) {
                    var strong = s.strong_match;

                    var wrapper = strong
                        ? 'flex items-center gap-4 rounded-xl border border-plum/25 bg-plum/5 px-3.5 py-4'
                        : 'flex items-center gap-4 border-b border-line py-4 last:border-none last:pb-1';

                    var tone = strong
                        ? 'bg-plum/12 text-plum'
                        : 'bg-star/18 text-star';

                    return '<div class="' + wrapper + '">'
                        +    '<div class="flex h-[46px] w-[46px] shrink-0 items-center justify-center '
                        +         'rounded-xl border border-line bg-surface-soft text-lg">'
                        +      (s.incentive_type === 'volunteer' ? '🤝' : '💵')
                        +    '</div>'
                        +    '<div class="min-w-0 flex-1">'
                        +      '<div class="truncate text-[14.5px] font-semibold text-ink">'
                        +        escape(s.title) + '</div>'
                        +      '<div class="mt-1 flex flex-wrap items-center gap-2 text-[12px] text-steel">'
                        +        '<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-1 '
                        +             'font-mono text-[10.5px] tracking-wide ' + tone + '">Match '
                        +          escape(s.match_score) + '%</span>'
                        +        '<span>' + escape(s.method === 'in_person' ? 'In person' : 'Online')
                        +          ' · ' + escape(s.duration_minutes) + ' min</span>'
                        +      '</div>'
                        +    '</div>'
                        +    '<a href="' + escape(s.url) + '" class="rounded-lg bg-plum/12 px-3 py-1.5 '
                        +       'font-mono text-[11px] text-plum transition hover:bg-plum/20">View</a>'
                        +  '</div>';
                }).join('');
            })
            .catch(function (error) {
                box.innerHTML = '<p class="py-3 text-[13.5px] text-flame">'
                              + escape(error.message) + '</p>';
            });
    }

    /* ------------------------------------------------------------------
       The full feed page. Everything below no-ops elsewhere.
       ------------------------------------------------------------------ */
    var page = document.getElementById('feed-page');

    if (!page) {
        return;
    }

    var FEED_URL    = '/api/v1/participants/me/feed';
    var FILTERS_URL = '/api/v1/participants/me/feed/filters';
    var GAP_URL     = '/api/v1/participants/me/skill-gap';
    var REFRESH_URL = '/api/v1/participants/me/skill-gap/refresh';

    var list      = page.querySelector('[data-feed-list]');
    var countEl   = page.querySelector('[data-feed-count]');
    var topicBox  = page.querySelector('[data-topic-filters]');
    var clearBtn  = page.querySelector('[data-clear-filters]');

    var gapAnalysis = page.querySelector('[data-gap-analysis]');
    var gapAdvice   = page.querySelector('[data-gap-advice]');
    var gapActions  = page.querySelector('[data-gap-actions]');
    var gapAlert    = page.querySelector('[data-gap-alert]');

    var selectedTopics = [];

    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* Class strings written out in full — Tailwind scans for literal names,
       so anything built by joining strings would silently lose its style. */
    var BADGE_TONES = {
        expert:  'bg-plum/12 text-plum',
        gold:    'bg-star/18 text-star',
        ok:      'bg-ok/12 text-ok',
        neutral: 'bg-steel/18 text-steel',
        flame:   'bg-flame/12 text-flame',
    };

    var CHIP_ON  = 'cursor-pointer rounded-full border border-plum bg-plum/12 px-3 py-1 font-mono text-[10.5px] text-plum';
    var CHIP_OFF = 'cursor-pointer rounded-full border border-line bg-surface px-3 py-1 font-mono text-[10.5px] text-steel';

    function badge(tone, text) {
        var cls = BADGE_TONES[tone] || BADGE_TONES.neutral;

        return '<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-1 '
             + 'font-mono text-[10.5px] tracking-wide ' + cls + '">' + esc(text) + '</span>';
    }

    /* ==================================================================
       The feed
       ================================================================== */

    function currentFilters() {
        var params = new URLSearchParams();

        page.querySelectorAll('[data-filter]').forEach(function (el) {
            var key = el.dataset.filter;

            if (el.type === 'checkbox') {
                if (el.checked) { params.set(key, '1'); }
                return;
            }

            if (el.value) { params.set(key, el.value); }
        });

        selectedTopics.forEach(function (id) { params.append('topic_ids[]', id); });

        return params;
    }

    function studyCard(study) {
        var meta = [
            study.method === 'in_person' ? 'In person' : 'Online',
            study.duration_minutes ? study.duration_minutes + ' min' : null,
            study.incentive_type === 'volunteer'
                ? 'Volunteer'
                : (study.compensation_amount ? '৳' + study.compensation_amount : null),
            study.deadline ? 'Closes ' + study.deadline : null,
        ].filter(Boolean).join(' · ');

        var topics = (study.topics || []).map(function (t) {
            return badge('neutral', t.name);
        }).join(' ');

        var reasons = (study.match_reasons || []).slice(0, 2).join(' · ');

        // The breakdown is shown so an unexpected position is explainable
        // rather than mysterious — its three parts add up to feed_score.
        var b = study.feed_breakdown || {};
        var breakdown = ['match', 'recency', 'urgency'].filter(function (k) {
            return b[k];
        }).map(function (k) {
            return k + ' ' + b[k].contribution;
        }).join('  +  ');

        return '<div class="rounded-xl border border-line bg-surface px-4 py-3.5">'
            +    '<div class="flex flex-wrap items-start justify-between gap-3">'
            +      '<div class="min-w-0 flex-1">'
            +        '<a href="' + esc(study.url) + '" class="text-[14px] font-semibold text-ink hover:text-plum">'
            +          esc(study.title) + '</a>'
            +        '<div class="mt-0.5 font-mono text-[11px] text-steel">'
            +          esc(study.researcher_name || 'Researcher') + ' · ' + esc(meta) + '</div>'
            +      '</div>'
            +      badge(study.strong_match ? 'expert' : 'gold', study.match_score + '% match')
            +    '</div>'
            +    (topics ? '<div class="mt-2 flex flex-wrap gap-1.5">' + topics + '</div>' : '')
            +    (reasons ? '<div class="mt-2 text-[11.5px] text-dim">' + esc(reasons) + '</div>' : '')
            +    '<div class="mt-2 font-mono text-[10.5px] text-steel">'
            +      'feed score ' + esc(study.feed_score) + '   =   ' + esc(breakdown)
            +    '</div>'
            +  '</div>';
    }

    function loadFeed() {
        var qs = currentFilters().toString();

        api.get(FEED_URL + (qs ? '?' + qs : ''))
            .then(function (response) {
                var d = response.data;

                countEl.textContent = d.count
                    ? d.count + (d.count === 1 ? ' study' : ' studies') + ' for you'
                    : 'No studies match these filters.';

                list.innerHTML = d.count
                    ? d.studies.map(studyCard).join('')
                    : '<p class="text-[12.5px] text-dim">Nothing matches. Try clearing a filter.</p>';
            })
            .catch(function (error) {
                countEl.textContent = '';
                list.innerHTML = '<p class="text-[12.5px] text-flame">' + esc(error.message) + '</p>';
            });
    }

    function loadFilterOptions() {
        api.get(FILTERS_URL)
            .then(function (response) {
                var d = response.data;

                fill('[data-filter="incentive_type"]', d.incentive_types, 'value', 'label');
                fill('[data-filter="method"]', d.methods, 'value', 'label');
                fill('[data-filter="max_duration"]', d.durations, 'value', 'label');

                topicBox.innerHTML = (d.topics || []).map(function (t) {
                    return '<button type="button" class="' + CHIP_OFF + '" data-topic="' + esc(t.id) + '">'
                         + esc(t.name) + '</button>';
                }).join('') || '<span class="text-[12px] text-dim">No topics yet.</span>';
            })
            .catch(function () {
                topicBox.innerHTML = '<span class="text-[12px] text-flame">Could not load filters.</span>';
            });
    }

    function fill(selector, items, valueKey, labelKey) {
        var el = page.querySelector(selector);

        if (!el || !items) { return; }

        items.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item[valueKey];
            opt.textContent = item[labelKey];
            el.appendChild(opt);
        });
    }

    page.querySelectorAll('[data-filter]').forEach(function (el) {
        el.addEventListener('change', loadFeed);
    });

    topicBox.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-topic]');

        if (!btn) { return; }

        var id = Number(btn.dataset.topic);
        var at = selectedTopics.indexOf(id);

        if (at > -1) {
            selectedTopics.splice(at, 1);
            btn.className = CHIP_OFF;
        } else {
            selectedTopics.push(id);
            btn.className = CHIP_ON;
        }

        loadFeed();
    });

    clearBtn.addEventListener('click', function () {
        page.querySelectorAll('[data-filter]').forEach(function (el) {
            if (el.type === 'checkbox') { el.checked = false; } else { el.value = ''; }
        });

        selectedTopics = [];
        topicBox.querySelectorAll('[data-topic]').forEach(function (b) { b.className = CHIP_OFF; });

        loadFeed();
    });

    /* ==================================================================
       The skill-gap coach
       ================================================================== */

    var REASON_TEXT = {
        incomplete_profile: 'Add your skills and age to your profile and studies will start '
                          + 'matching you — there is not enough on it yet to find near misses.',
        no_near_misses:     'Nothing is close but out of reach right now. Either you already '
                          + 'qualify for what is open, or nothing open is in your area.',
    };

    function renderGap(d) {
        var a = d.analysis || {};

        if (d.nothing_to_say) {
            gapAnalysis.innerHTML = '<p class="text-[12.5px] text-dim">'
                + esc(REASON_TEXT[d.reason] || 'Nothing to analyse yet.') + '</p>';
            gapAdvice.innerHTML = '';
            gapActions.innerHTML = '';
            return;
        }

        var skills = (a.missing_skills || []).map(function (s) {
            return '<div class="flex items-center justify-between gap-3 border-b border-line py-1.5 last:border-none">'
                 +   '<span class="text-[12.5px] text-ink">' + esc(s.skill) + '</span>'
                 +   '<span class="font-mono text-[10.5px] text-steel">blocks ' + esc(s.blocks_studies) + '</span>'
                 + '</div>';
        }).join('');

        var misses = (a.near_misses || []).map(function (m) {
            return '<div class="flex items-center justify-between gap-3 py-1">'
                 +   '<a href="' + esc(m.url) + '" class="truncate text-[12.5px] text-ink hover:text-plum">'
                 +     esc(m.title) + '</a>'
                 +   '<span class="font-mono text-[10.5px] text-steel">'
                 +     esc(m.match_score) + '% · ' + esc(m.shortfall) + ' short</span>'
                 + '</div>';
        }).join('');

        gapAnalysis.innerHTML =
              '<p class="text-[13px] text-ink">'
            +   '<b>' + esc(a.near_miss_count) + '</b> stud' + (a.near_miss_count === 1 ? 'y' : 'ies')
            +   ' just out of reach.'
            + '</p>'
            + (skills
                ? '<div class="mt-3"><div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel mb-1">'
                  + 'Missing skills</div>' + skills + '</div>'
                : '')
            + (misses
                ? '<div class="mt-3"><div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel mb-1">'
                  + 'Near misses</div>' + misses + '</div>'
                : '');

        renderAdvice(d);
    }

    function renderAdvice(d) {
        var adv = d.advice;

        if (!adv) {
            gapAdvice.innerHTML = d.ai_available
                ? '<p class="rounded-lg bg-steel/12 px-3 py-2 text-[12.5px] text-dim">'
                  + 'No coaching generated yet. Use the button below.</p>'
                : '<p class="rounded-lg bg-steel/12 px-3 py-2 text-[12.5px] text-dim">'
                  + 'AI coaching is not configured, so only the analysis above is shown.</p>';
        } else {
            var steps = (adv.steps || []).map(function (s) {
                return '<li class="mt-1.5 text-[12.5px] text-ink">' + esc(s.action)
                     + (s.effort ? ' <span class="font-mono text-[10.5px] text-steel">('
                                   + esc(s.effort) + ')</span>' : '')
                     + '</li>';
            }).join('');

            gapAdvice.innerHTML =
                  '<div class="rounded-xl border border-plum/30 bg-plum/5 p-3.5">'
                /* The gap is named from focus_skill, which validate() has
                   already checked against our own analysis. The model never
                   supplies this line as free text. */
                +   '<div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">'
                +     'Focus</div>'
                +   '<div class="text-[13.5px] font-semibold capitalize text-plum">'
                +     esc(adv.focus_skill || adv.headline || '') + '</div>'
                +   '<p class="mt-1 text-[12.5px] text-dim">' + esc(adv.why_it_matters) + '</p>'
                +   (steps ? '<ul class="mt-2 list-disc pl-4">' + steps + '</ul>' : '')
                +   (adv.encouragement
                        ? '<p class="mt-2 text-[12px] text-ok">' + esc(adv.encouragement) + '</p>'
                        : '')
                +   '<p class="mt-2 font-mono text-[10px] text-steel">'
                +     (d.stale ? 'Based on an earlier profile — refresh to update. · ' : '')
                +     (d.generated_on ? 'Generated ' + esc(d.generated_on) : '')
                +   '</p>'
                + '</div>';
        }

        gapActions.innerHTML = d.ai_available
            ? '<button type="button" data-refresh-advice '
              + 'class="rounded-lg bg-plum/12 px-3 py-1.5 font-mono text-[11px] text-plum transition '
              + 'hover:bg-plum/20 disabled:cursor-not-allowed disabled:opacity-50">'
              + (d.advice ? 'Refresh advice' : 'Get advice') + '</button>'
            : '';
    }

    function loadGap() {
        api.get(GAP_URL)
            .then(function (response) { renderGap(response.data); })
            .catch(function (error) {
                gapAnalysis.innerHTML = '<p class="text-[12.5px] text-flame">'
                                      + esc(error.message) + '</p>';
            });
    }

    /* Delegated: the button is replaced on every render. This is the ONLY
       thing on the page that can trigger an outbound AI call. */
    gapActions.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-refresh-advice]');

        if (!btn) { return; }

        btn.disabled = true;
        btn.textContent = 'Thinking…';
        gapAlert.innerHTML = '';

        api.post(REFRESH_URL, {})
            .then(function (response) {
                if (!response.generated) {
                    gapAlert.innerHTML = '<p class="mt-2 rounded-lg bg-flame/10 px-3 py-2 '
                                       + 'text-[12px] text-flame">' + esc(response.message) + '</p>';
                }

                renderGap(response.data);
            })
            .catch(function (error) {
                gapAlert.innerHTML = '<p class="mt-2 rounded-lg bg-flame/10 px-3 py-2 '
                                   + 'text-[12px] text-flame">' + esc(error.message) + '</p>';
                loadGap();
            });
    });

    loadFilterOptions();
    loadFeed();
    loadGap();
});
