/*
|------------------------------------------------------------------------------
| TRYBE — "you were invited by X" on the signup page  (Member 4)
|------------------------------------------------------------------------------
|
|     GET /api/v1/referrals/validate/{code}    (public — a guest calls this)
|
| Read-only. It adds no input to the signup form and changes no field name,
| so the frozen-field rule in AuthController still holds. The referral itself
| is attributed from a cookie by ReferralAttributionObserver, not from
| anything on this page.
*/

document.addEventListener('DOMContentLoaded', function () {

    var banner = document.getElementById('referral-banner');

    if (!banner) {
        return;
    }

    var params = new URLSearchParams(window.location.search);
    var code   = params.get('ref');

    if (!code) {
        return;
    }

    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    api.get('/api/v1/referrals/validate/' + encodeURIComponent(code))
        .then(function (response) {
            banner.innerHTML =
                '<p class="mb-4 rounded-xl bg-plum/10 px-4 py-3 text-[13px] text-plum">'
                + '<b>' + esc(response.data.referrer_name) + '</b> invited you to TRYBE.'
                + '</p>';
            banner.classList.remove('hidden');
        })
        .catch(function () {
            // An invalid or expired code is not worth an error message on a
            // signup page — just show nothing.
        });
});
