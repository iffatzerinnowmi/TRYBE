document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-paid-apply-panel]').forEach(function (panel) {
        var studyId  = panel.dataset.studyId;
        var url      = '/api/v1/studies/' + studyId + '/paid-application';

        var progress = panel.querySelector('[data-paid-apply-progress]');
        var btn      = panel.querySelector('[data-paid-apply-btn]');
        var note     = panel.querySelector('[data-paid-apply-note]');
        var success  = panel.querySelector('[data-paid-apply-success]');

        function render(data) {
            progress.textContent =
                data.volunteer_progress + ' of ' + data.volunteer_target + ' volunteer studies · ' +
                data.paid_used + ' of ' + data.paid_slots + ' paid slots used · ' +
                data.karma_balance + ' Karma';

            btn.classList.remove('hidden');
            btn.disabled = false;

            if (data.can_apply_free) {
                btn.textContent = 'Apply';
                note.textContent = '';
            } else if (data.can_apply_karma) {
                btn.textContent = 'Spend ' + data.karma_cost + ' Karma & Apply';
                note.textContent = '';
            } else {
                btn.textContent = 'Apply';
                btn.disabled = true;
                note.textContent = data.paid_used >= data.paid_slots
                    ? 'No paid application slots left this cycle.'
                    : 'Complete ' + (data.volunteer_target - data.volunteer_progress) +
                      ' more volunteer studies, or earn ' + (data.karma_cost - data.karma_balance) + ' more Karma.';
            }
        }

        function load() {
            window.api.get(url)
                .then(function (res) { render(res.data); })
                .catch(function () { progress.textContent = 'Could not load application status.'; });
        }

        btn.addEventListener('click', function () {
            btn.disabled = true;
            note.textContent = '';
            success.textContent = '';

            window.api.post(url)
                .then(function (res) {
                    success.textContent = res.message;
                    load();
                })
                .catch(function (e) {
                    note.textContent = e.message;
                    btn.disabled = false;
                });
        });

        load();
    });
});