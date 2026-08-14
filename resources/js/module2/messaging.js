document.addEventListener('DOMContentLoaded', function () {
    console.log('module2.messaging loaded');

    document.querySelectorAll('[data-candidates-panel]').forEach(panel => {
        const studyId = panel.dataset.studyId;

        const openBtn = panel.querySelector('[data-bulk-message-open]');
        const panelEl = panel.querySelector('[data-bulk-messaging-panel]');
        const closeBtn = panel.querySelector('[data-bulk-message-close]');
        const sendBtn = panel.querySelector('[data-bulk-message-send]');
        const statusEl = panel.querySelector('[data-bulk-message-status]');

        if (! openBtn || ! panelEl || ! sendBtn) return;

        openBtn.addEventListener('click', (e) => {
            panelEl.classList.remove('hidden');
            panelEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', () => panelEl.classList.add('hidden'));
        }

        sendBtn.addEventListener('click', async () => {
            statusEl.textContent = 'Sending…';

            const stage = panelEl.querySelector('[data-field="stage"]').value;
            const subject = panelEl.querySelector('[data-field="subject"]').value.trim();
            const body = panelEl.querySelector('[data-field="body"]').value.trim();

            if (! stage || ! subject || ! body) {
                statusEl.textContent = 'Please fill stage, subject and message.';
                return;
            }

            try {
                const res = await api.post(`/api/v1/studies/${studyId}/messages`, { stage, subject, body });
                statusEl.textContent = `Queued — ${res.sent ?? 0} messages.`;
                // Optionally hide after success
                setTimeout(() => panelEl.classList.add('hidden'), 1200);
            } catch (err) {
                console.error(err);
                if (err instanceof ApiError) {
                    statusEl.textContent = err.message;
                } else {
                    statusEl.textContent = 'Could not send messages.';
                }
            }
        });
    });
});
