document.addEventListener('DOMContentLoaded', function () {
    const panels = document.querySelectorAll('[data-slot-panel]');

    if (!panels.length) {
        return;
    }

    panels.forEach(function (panel) {
        const studyId = panel.dataset.studyId;
        const slotType = panel.dataset.slotType;
        const list = panel.querySelector('[data-slot-list]');
        const status = panel.querySelector('[data-slot-status]');
        const createBtn = panel.querySelector('[data-create-slot]');

        function renderSlots(slots) {
            if (!slots.length) {
                list.innerHTML = '<p class="text-[12.5px] text-dim">No slots scheduled yet.</p>';
                return;
            }

            list.innerHTML = slots.map(function (slot) {
                const starts = new Date(slot.starts_at).toLocaleString();
                const ends = new Date(slot.ends_at).toLocaleString();
                const button = slot.available
                    ? '<button type="button" data-book-slot="' + slot.id + '" class="rounded-lg bg-plum/12 px-2.5 py-1 font-mono text-[10.5px] text-plum">Book slot</button>'
                    : '<span class="font-mono text-[10.5px] text-flame">Full</span>';

                return '<div class="rounded-xl border border-line bg-surface p-3">'
                    + '<div class="flex items-center justify-between gap-3">'
                    + ' <div><div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Session</div>'
                    + ' <div class="mt-1 text-[13px] font-semibold text-ink">' + starts + '</div>'
                    + ' <div class="text-[11.5px] text-dim">to ' + ends + '</div></div>'
                    + ' <div class="text-right"><div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Seats</div>'
                    + ' <div class="mt-1 text-[13px] font-semibold text-ink">' + slot.remaining + '/' + slot.capacity + '</div></div>'
                    + '</div>'
                    + '<div class="mt-3 flex justify-end">' + button + '</div>'
                    + '</div>';
            }).join('');
        }

        function loadSlots() {
            if (!list) {
                return;
            }

            list.innerHTML = '<p class="text-[12.5px] text-dim">Loading slots…</p>';
            api.get('/api/v1/studies/' + studyId + '/slots')
                .then(function (res) {
                    renderSlots(res.data.slots || []);
                })
                .catch(function (err) {
                    list.innerHTML = '<p class="text-[12.5px] text-flame">' + (err.message || 'Could not load slots.') + '</p>';
                });
        }

        if (slotType === 'researcher' && createBtn) {
            createBtn.addEventListener('click', function () {
                const form = panel.querySelector('[data-slot-form]');

                if (!form) {
                    status.textContent = 'The slot form is missing.';
                    return;
                }

                const startsInput = form.querySelector('[name="starts_at"]');
                const endsInput = form.querySelector('[name="ends_at"]');
                const capacityInput = form.querySelector('[name="capacity"]');
                const startsAt = startsInput ? startsInput.value : '';
                const endsAt = endsInput ? endsInput.value : '';
                const capacity = capacityInput ? capacityInput.value : '';

                if (!startsAt || !endsAt || !capacity) {
                    status.textContent = 'Please complete the slot details.';
                    return;
                }

                status.textContent = 'Saving slot…';

                api.post('/api/v1/studies/' + studyId + '/slots', {
                    starts_at: startsAt,
                    ends_at: endsAt,
                    capacity: Number(capacity),
                })
                    .then(function () {
                        if (typeof form.reset === 'function') {
                            form.reset();
                        }
                        status.textContent = 'Slot created.';
                        loadSlots();
                    })
                    .catch(function (err) {
                        status.textContent = err.message || 'Could not create slot.';
                    });
            });
        }

        list.addEventListener('click', function (event) {
            const button = event.target.closest('[data-book-slot]');
            if (!button || slotType !== 'participant') {
                return;
            }

            const slotId = button.dataset.bookSlot;
            button.disabled = true;
            button.textContent = 'Booking…';

            api.post('/api/v1/studies/' + studyId + '/slots/' + slotId + '/book', {})
                .then(function () {
                    status.textContent = 'Slot booked successfully.';
                    loadSlots();
                })
                .catch(function (err) {
                    button.disabled = false;
                    button.textContent = 'Book slot';
                    status.textContent = err.message || 'Booking failed.';
                });
        });

        loadSlots();
    });
});
