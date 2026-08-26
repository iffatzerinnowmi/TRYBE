document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-auction-panel]').forEach(function (panel) {
        var studyId = panel.dataset.studyId;
        var role = panel.dataset.role;
        var url = '/api/v1/studies/' + studyId + '/auction';

        var statusBadge      = panel.querySelector('[data-auction-status-badge]');
        var metaEl           = panel.querySelector('[data-auction-meta]');
        var countdownWrap    = panel.querySelector('[data-auction-countdown-wrap]');
        var countdownEl      = panel.querySelector('[data-auction-countdown]');
        var participantBlock = panel.querySelector('[data-auction-participant-block]');
        var researcherBlock  = panel.querySelector('[data-auction-researcher-block]');
        var myStatusEl       = panel.querySelector('[data-auction-my-status]');
        var applyBtn         = panel.querySelector('[data-auction-apply-btn]');
        var applyNoteEl      = panel.querySelector('[data-auction-apply-note]');
        var closeBtn         = panel.querySelector('[data-auction-close-btn]');
        var rankingEl        = panel.querySelector('[data-auction-ranking]');

        var closesAt = null;
        var timer = null;

        function esc(text) {
            return String(text === null || text === undefined ? '' : text)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function badgeTone(status) {
            if (status === 'open') return 'bg-ok/12 text-ok';
            return 'bg-steel/18 text-steel';
        }

        function renderRanking(rows) {
            if (!rows.length) {
                rankingEl.innerHTML = '<p class="text-[12.5px] text-dim">No applications yet.</p>';
                return;
            }

            rankingEl.innerHTML = rows.map(function (row) {
                var winClass = row.wins_seat ? 'border-ok/40 bg-ok/8' : 'border-line bg-surface';
                var pillHtml;

                if (row.status === 'won') {
                    pillHtml = '<span class="rounded-full bg-ok/15 px-2 py-0.5 font-mono text-[10px] text-ok">Won</span>';
                } else if (row.status === 'lost') {
                    pillHtml = '<span class="rounded-full bg-steel/15 px-2 py-0.5 font-mono text-[10px] text-steel">Not selected</span>';
                } else if (row.wins_seat) {
                    pillHtml = '<span class="rounded-full bg-ok/15 px-2 py-0.5 font-mono text-[10px] text-ok">In the lead</span>';
                } else {
                    pillHtml = '<span class="rounded-full bg-steel/15 px-2 py-0.5 font-mono text-[10px] text-steel">Applied</span>';
                }

                return '<div class="flex items-center justify-between rounded-lg border ' + winClass + ' px-3 py-2">' +
                    '<div class="flex items-center gap-2.5">' +
                        '<span class="font-mono text-[11px] text-steel">#' + esc(row.rank) + '</span>' +
                        '<span class="text-[13px] text-ink">' + esc(row.name) + '</span>' +
                    '</div>' +
                    '<div class="flex items-center gap-2">' +
                        '<span class="font-mono text-[12px] text-dim">' + esc(row.reliability_score) + '</span>' +
                        pillHtml +
                    '</div>' +
                '</div>';
            }).join('');
        }

        function renderMyStatus(my) {
            if (!my) {
                myStatusEl.innerHTML = '<p class="text-[12.5px] text-dim">You haven\'t applied yet.</p>';
                return;
            }

            var tone = my.status === 'won' ? 'text-ok' : (my.status === 'lost' ? 'text-steel' : 'text-plum');
            myStatusEl.innerHTML =
                '<p class="text-[13px] font-semibold ' + tone + '">' + esc(my.status_label) +
                (my.rank ? ' — ranked #' + esc(my.rank) : '') + '</p>' +
                '<p class="text-[11.5px] text-steel">Your reliability score at the time you applied: ' + esc(my.reliability_score) + '</p>';
        }

        function tick() {
            if (!closesAt) { countdownEl.textContent = '—'; return; }
            var diff = closesAt - Date.now();
            if (diff <= 0) { countdownEl.textContent = 'closing…'; return; }
            var h = Math.floor(diff / 3600000);
            var m = Math.floor((diff % 3600000) / 60000);
            var s = Math.floor((diff % 60000) / 1000);
            countdownEl.textContent = h + 'h ' + m + 'm ' + s + 's';
        }

        function render(data) {
            statusBadge.textContent = data.status_label || 'Not running';
            statusBadge.className = 'rounded-full px-2.5 py-1 font-mono text-[10.5px] ' + badgeTone(data.status);
            metaEl.textContent = data.applications_count + ' applied · ' + data.seats + ' seat' +
                (data.seats === 1 ? '' : 's') + ' · fills at ' + data.fill_target;

            if (data.status === 'open' && data.closes_at) {
                closesAt = new Date(data.closes_at).getTime();
                countdownWrap.classList.remove('hidden');
                if (!timer) { tick(); timer = setInterval(tick, 1000); }
            } else {
                countdownWrap.classList.add('hidden');
                if (timer) { clearInterval(timer); timer = null; }
            }

            renderRanking(data.ranking);

            if (role === 'participant') {
                participantBlock.classList.remove('hidden');
                renderMyStatus(data.my_application);

                var canApply = data.status === 'open' && !data.my_application;
                if (applyBtn) { applyBtn.classList.toggle('hidden', !canApply); }

                if (applyNoteEl) {
                    applyNoteEl.textContent = data.status === 'closed' ? 'This auction has closed.' : '';
                }
            } else {
                researcherBlock.classList.remove('hidden');
                if (closeBtn) { closeBtn.classList.toggle('hidden', data.status !== 'open'); }
            }
        }

        function load() {
            api.get(url)
                .then(function (res) { render(res.data); })
                .catch(function (e) { metaEl.textContent = e.message; });
        }

        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                applyBtn.disabled = true;
                applyBtn.textContent = 'Applying…';

                api.post(url + '/apply')
                    .then(function (res) { render(res.data.data); })
                    .catch(function (e) { applyNoteEl.textContent = e.message; })
                    .finally(function () {
                        applyBtn.disabled = false;
                        applyBtn.textContent = 'Apply for a seat';
                    });
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                if (!confirm('Close this auction now and award seats?')) { return; }
                closeBtn.disabled = true;

                api.post(url + '/close')
                    .then(function (res) { render(res.data.data); })
                    .catch(function (e) { alert(e.message); })
                    .finally(function () { closeBtn.disabled = false; });
            });
        }

        load();
        // Light polling so results appear without a manual page refresh.
        setInterval(load, 20000);
    });
});