/*
|------------------------------------------------------------------------------
| TRYBE — "what this study requires, and which of it you meet"  (Member 4)
|------------------------------------------------------------------------------
|
|     GET /api/v1/studies/{id}/requirements
|
| WHY THIS PANEL EXISTS
| ---------------------
| The skill-gap coach names skills a participant is missing. With nothing on
| screen showing what a study actually ASKS for, that reads like it could have
| been invented. This shows both sides of the same diff in one place:
| study_match_criteria.required_skills on the left, the participant's own
| profile skills on the right, ticks and crosses between them.
|
| It decides nothing. Every met/unmet flag is computed server-side by the same
| StudyMatchingService that produces the score, so this panel and the score
| beside it can never disagree.
|
| Conventions §7: DOMContentLoaded wrapper, shared `api` helper (never forked),
| esc() on everything from the database, full Tailwind class names.
*/

document.addEventListener('DOMContentLoaded', function () {

    var blocks = document.querySelectorAll('[data-requirements-block]');

    if (!blocks.length) { return; }

    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* A met requirement is green with a tick; an unmet one is amber with a
       cross. Not red — an unmet requirement is information, not an error, and
       colouring it like a failure reads as a rebuke. */
    function row(label, met, detail) {
        return ''
            + '<li class="flex items-start gap-2 py-1">'
            +   '<span class="mt-[1px] font-mono text-[12px] '
            +     (met ? 'text-ok">✓' : 'text-flame">✗')
            +   '</span>'
            +   '<span class="text-[12.5px] ' + (met ? 'text-ink' : 'text-dim') + '">'
            +     esc(label)
            +     (detail ? ' <span class="text-steel">' + esc(detail) + '</span>' : '')
            +   '</span>'
            + '</li>';
    }

    blocks.forEach(function (block) {

        var studyId = block.getAttribute('data-study-id');
        var body    = block.querySelector('[data-requirements-body]');

        function render(d) {
            var html = '';

            /* Headline: how many of the required skills this person has. This
               is the number to point at in a demo. */
            if (d.skills.total > 0) {
                html += '<div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">'
                      + 'Required skills · you have ' + d.skills.met_count + ' of ' + d.skills.total
                      + '</div>';

                html += '<ul class="mt-1.5">';
                d.skills.required.forEach(function (s) {
                    html += row(s.skill, s.met, s.met ? '' : 'you don\'t have this yet');
                });
                html += '</ul>';
            } else {
                html += '<p class="text-[12.5px] text-dim">'
                      + 'This study lists no required skills.</p>';
            }

            /* The other published criteria. Only shown when the researcher
               actually set them — a null location is not a requirement. */
            var other = '';

            if (d.criteria.age_range) {
                other += row('Age ' + d.criteria.age_range, d.criteria.age_met);
            }
            if (d.criteria.location) {
                other += row('Based in ' + d.criteria.location, d.criteria.location_met);
            }
            if (d.criteria.credential_min && d.criteria.credential_min !== 'none') {
                other += row('Credential: ' + d.criteria.credential_min + ' or above',
                             d.criteria.credential_met);
            }

            if (other) {
                html += '<div class="mt-3 font-mono text-[10px] uppercase tracking-[0.14em] text-steel">'
                      + 'Other requirements</div><ul class="mt-1.5">' + other + '</ul>';
            }

            /* Your own skills, so the comparison is visible rather than
               implied. Without this the crosses look like an accusation. */
            if (d.skills.your_skills && d.skills.your_skills.length) {
                html += '<p class="mt-3 text-[11.5px] text-steel">Your skills: '
                      + esc(d.skills.your_skills.join(', ')) + '</p>';
            } else {
                html += '<p class="mt-3 text-[11.5px] text-flame">'
                      + 'Your profile lists no skills — add some and your match will improve.</p>';
            }

            /* Be honest when the researcher never set criteria. Presenting
               platform defaults as though somebody chose them is misleading. */
            if (d.is_default) {
                html += '<p class="mt-2 rounded-lg bg-steel/12 px-2.5 py-1.5 text-[11.5px] text-dim">'
                      + 'The researcher hasn\'t set criteria for this study, so these are '
                      + 'platform defaults.</p>';
            }

            html += '<p class="mt-3 font-mono text-[10.5px] text-steel">Your match: '
                  + esc(d.match_score) + '%'
                  + (d.strong_match ? ' · strong match' : '')
                  + '</p>';

            body.innerHTML = html;
        }

        api.get('/api/v1/studies/' + encodeURIComponent(studyId) + '/requirements')
            .then(function (response) { render(response.data); })
            .catch(function (error) {
                /* Researchers get a 403 here by design — hide rather than
                   shout. */
                block.classList.add('hidden');
                if (error && error.status !== 403) {
                    body.textContent = error.message;
                }
            });
    });
});
