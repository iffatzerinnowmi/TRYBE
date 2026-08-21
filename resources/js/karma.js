document.addEventListener('DOMContentLoaded', function () {
    var page = document.getElementById('karma-page');
    if (!page) { return; }

    var balanceEl    = page.querySelector('[data-karma-balance]');
    var ratesEl      = page.querySelector('[data-earn-rates]');
    var listEl       = page.querySelector('[data-karma-transactions]');
    var spendBlockEl = page.querySelector('[data-spend-block]');
    var spendAlertEl = page.querySelector('[data-spend-alert]');
    var viewAllBtn   = page.querySelector('[data-view-all-trigger]');

    var currentBalance = 0;

    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function renderRates(rates) {
        ratesEl.innerHTML = rates.map(function (r) {
            return '<div class="flex items-center justify-between py-2.5">' +
                '<div class="flex items-center gap-2.5">' +
                    '<div class="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-soft">' + esc(r.icon) + '</div>' +
                    '<span class="text-[13.5px] text-ink">' + esc(r.label) + '</span></div>' +
                '<span class="font-mono text-[13px] font-semibold text-ok">+' + esc(r.points) + '</span></div>';
        }).join('');
    }

    function renderTransactions(transactions) {
        if (!transactions.length) {
            listEl.innerHTML = '<p class="py-4 text-[13px] text-dim">No karma activity yet.</p>';
            return;
        }
        listEl.innerHTML = transactions.map(function (t) {
            var isSpend = t.delta < 0;
            var amountClass = isSpend ? 'text-danger' : 'text-ok';
            var sign = isSpend ? '' : '+';
            return '<div class="flex items-center justify-between py-3.5">' +
                '<div class="flex items-center gap-3.5">' +
                    '<div class="flex h-[42px] w-[42px] items-center justify-center rounded-[10px] bg-surface-soft text-[18px] text-ok">' + esc(t.icon) + '</div>' +
                    '<div><h4 class="text-[14px] text-ink">' + esc(t.description) + '</h4>' +
                    '<span class="text-[12px] text-steel">' + esc(t.created_on) + '</span></div></div>' +
                '<div class="font-mono font-semibold ' + amountClass + '">' + sign + esc(t.delta) + '</div></div>';
        }).join('');
    }

    function renderSpendBlock(options) {
        if (!options.length) {
            spendBlockEl.innerHTML = '<p class="text-[12.5px] text-white/70">Nothing to spend karma on yet.</p>';
            return;
        }
        spendBlockEl.innerHTML = options.map(function (o) {
            return '<div class="flex flex-wrap items-center justify-between gap-3">' +
                '<span class="text-[13.5px]">' + esc(o.icon) + ' ' + esc(o.label) + '</span>' +
                '<div class="flex items-center gap-2">' +
                    '<input type="number" min="1" max="1000" value="25" class="w-20 rounded-lg border border-white/25 bg-white/10 px-2.5 py-1.5 text-[13px] text-white" data-spend-amount />' +
                    '<button type="button" data-spend-source="' + esc(o.source) + '" class="rounded-lg bg-white/20 px-3 py-1.5 text-[12.5px] font-semibold text-white transition hover:bg-white/30">Spend</button>' +
                '</div></div>';
        }).join('');

        var button = spendBlockEl.querySelector('[data-spend-source]');
        var input  = spendBlockEl.querySelector('[data-spend-amount]');

        button.addEventListener('click', function () {
            var amount = parseInt(input.value, 10);
            if (!amount || amount < 1) { showSpendAlert('Enter a valid amount.', true); return; }
            if (amount > currentBalance) { showSpendAlert('Not enough karma — you have ' + currentBalance + '.', true); return; }

            button.disabled = true;
            button.textContent = 'Spending…';

            api.post('/api/v1/karma/me/spend', { source: button.getAttribute('data-spend-source'), amount: amount })
                .then(function (res) {
                    currentBalance = res.data.balance;
                    balanceEl.textContent = currentBalance;
                    showSpendAlert('Spent ' + amount + ' karma.', false);
                    return api.get('/api/v1/karma/me/transactions');
                })
                .then(function (res) { renderTransactions(res.data); })
                .catch(function (e) { showSpendAlert(e.message, true); })
                .finally(function () { button.disabled = false; button.textContent = 'Spend'; });
        });
    }

    function showSpendAlert(message, isError) {
        spendAlertEl.innerHTML = '<p class="text-[12.5px] ' + (isError ? 'text-red-100' : 'text-white/90') + '">' + esc(message) + '</p>';
    }

    api.get('/api/v1/karma/me')
        .then(function (res) {
            var data = res.data;
            currentBalance = data.balance;
            balanceEl.textContent = data.balance;
            renderRates(data.rates);
            renderSpendBlock(data.spend_options || []);
            renderTransactions(data.transactions);
        })
        .catch(function (e) {
            balanceEl.textContent = '—';
            ratesEl.innerHTML = '<p class="py-2 text-[13px] text-danger">' + esc(e.message) + '</p>';
        });

    if (viewAllBtn) {
        viewAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            api.get('/api/v1/karma/me/transactions').then(function (res) { renderTransactions(res.data); });
        });
    }
});