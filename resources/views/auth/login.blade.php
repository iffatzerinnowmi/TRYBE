@extends('layouts.auth')

@section('title', 'Log in')
@section('side-eyebrow', 'Welcome back')
@section('side-headline', 'Your streak, karma, and unlock progress are exactly where you left them.')
@section('side-sub', 'Log in to pick up your studies, track your credential level, and keep your reciprocity streak alive.')

@section('side-foot')
    {{-- These two numbers come from GET /api/v1/platform/stats, which is
         public so a logged-out visitor can read it. --}}
    <div class="flex gap-10">
        <div>
            <b id="stat-unlock" class="block font-display text-3xl font-semibold text-mint">—</b>
            <span class="mt-1 block font-mono text-[10.5px] uppercase tracking-[0.16em] text-steel">
                Studies to unlock paid
            </span>
        </div>
        <div>
            <b id="stat-tiers" class="block font-display text-3xl font-semibold text-mint">—</b>
            <span class="mt-1 block font-mono text-[10.5px] uppercase tracking-[0.16em] text-steel">
                Credential tiers
            </span>
        </div>
    </div>
@endsection

@section('content')
{{--
    API-DRIVEN PAGE
    ---------------
    AuthController::showLogin() passes nothing. The form does not post to a
    web route — it calls POST /api/v1/auth/login with fetch(). That endpoint
    starts the session, so every page afterwards is authenticated normally.
--}}
<div class="rounded-panel border border-line bg-surface p-7 shadow-soft sm:p-9">

    <div class="mb-6">
        <p class="font-mono text-[11px] uppercase tracking-[0.22em] text-steel">Log in</p>
        <h2 class="mt-2.5 font-display text-[28px] font-semibold text-ink">Good to see you.</h2>
        <p class="mt-2 text-[13.5px] text-dim">
            New to TRYBE?
            <a href="/signup" class="font-semibold text-plum hover:underline">Create an account</a>
        </p>
    </div>

    {{-- Filled by JS when the API rejects the login. --}}
    <div id="login-alert" class="mb-5 hidden rounded-xl px-4 py-3 text-[13px]"></div>

    {{-- No action and no method: the submit is intercepted below.
         The name attributes are still frozen — the API validates the same
         strings the old form did. --}}
    <form id="login-form" class="space-y-5">

        <div>
            <label for="email" class="mb-2 block text-[13px] font-semibold text-ink">
                Email address <span class="text-danger">*</span>
            </label>

            <input type="email" id="email" name="email"
                   placeholder="you@example.com"
                   autocomplete="email" required
                   class="w-full rounded-xl border border-line-hi bg-surface-soft px-4 py-3 text-sm text-ink
                          outline-none transition placeholder:text-steel
                          focus:border-plum focus:bg-surface">

            <p id="email-error" class="mt-1.5 hidden text-[12px] text-danger"></p>
        </div>

        <div>
            <label for="password" class="mb-2 block text-[13px] font-semibold text-ink">
                Password <span class="text-danger">*</span>
            </label>

            <div class="relative">
                <input type="password" id="password" name="password"
                       placeholder="••••••••"
                       autocomplete="current-password" required
                       class="w-full rounded-xl border border-line-hi bg-surface-soft px-4 py-3 pr-16 text-sm text-ink
                              outline-none transition placeholder:text-steel
                              focus:border-plum focus:bg-surface">

                <button type="button" id="pw-toggle"
                        class="absolute right-3 top-1/2 -translate-y-1/2 font-mono text-[10.5px]
                               tracking-wider text-steel transition hover:text-plum">
                    SHOW
                </button>
            </div>

            <p id="password-error" class="mt-1.5 hidden text-[12px] text-danger"></p>
        </div>

        <div class="flex items-center justify-between">
            <label class="flex cursor-pointer items-center gap-2 text-[13px] text-dim">
                <input type="checkbox" id="remember" name="remember" value="1"
                       class="h-4 w-4 rounded border-line-hi accent-plum">
                Remember me
            </label>

            <a href="#" class="text-[13px] text-plum hover:underline">Forgot password?</a>
        </div>

        <x-btn type="submit" class="w-full">Log in</x-btn>
    </form>

    <div class="my-6 flex items-center gap-3">
        <span class="h-px flex-1 bg-line"></span>
        <span class="font-mono text-[10.5px] uppercase tracking-[0.18em] text-steel">Or sign up as</span>
        <span class="h-px flex-1 bg-line"></span>
    </div>

    <div class="grid grid-cols-3 gap-2.5">
        <a href="/signup?role=participant"
           class="rounded-xl border border-line-hi py-2.5 text-center text-[12.5px] text-ink
                  transition hover:border-plum hover:text-plum">Participant</a>
        <a href="/signup?role=researcher"
           class="rounded-xl border border-line-hi py-2.5 text-center text-[12.5px] text-ink
                  transition hover:border-plum hover:text-plum">Researcher</a>
        <a href="/signup?role=organization"
           class="rounded-xl border border-line-hi py-2.5 text-center text-[12.5px] text-ink
                  transition hover:border-plum hover:text-plum">Organization</a>
    </div>

    <p class="mt-6 text-center text-[11.5px] text-steel">
        By logging in you agree to TRYBE's Terms and Privacy Policy.
    </p>
</div>
@endsection

@push('scripts')
<script>
function initializeLoginForm() {

    var form   = document.getElementById('login-form');
    var button = form.querySelector('button[type="submit"]');

    /* --- Show / hide the password. Cosmetic only. --- */
    document.getElementById('pw-toggle').addEventListener('click', function () {
        var input  = document.getElementById('password');
        var hidden = input.type === 'password';
        input.type = hidden ? 'text' : 'password';
        this.textContent = hidden ? 'HIDE' : 'SHOW';
    });

    /* --- Side-panel numbers, from the public endpoint --- */
    if (window.api) {
        window.api.get('/api/v1/platform/stats')
            .then(function (response) {
                document.getElementById('stat-unlock').textContent = response.data.unlock_target;
                document.getElementById('stat-tiers').textContent  = response.data.tier_count;
            })
            .catch(function () {
                // Decoration only. If it fails, logging in still works.
            });
    }

    /* --- Error display helpers --- */
    function showAlert(message) {
        var box = document.getElementById('login-alert');
        box.className = 'mb-5 rounded-xl bg-danger/10 px-4 py-3 text-[13px] text-danger';
        box.textContent = message;
    }

    function clearErrors() {
        document.getElementById('login-alert').className =
            'mb-5 hidden rounded-xl px-4 py-3 text-[13px]';

        ['email', 'password'].forEach(function (field) {
            var el = document.getElementById(field + '-error');
            el.className = 'mt-1.5 hidden text-[12px] text-danger';
            el.textContent = '';
        });
    }

    /* Laravel sends 422 with an errors object keyed by field name. */
    function showFieldErrors(errors) {
        Object.keys(errors).forEach(function (field) {
            var el = document.getElementById(field + '-error');
            if (el) {
                el.className = 'mt-1.5 text-[12px] text-danger';
                el.textContent = errors[field][0];
            }
        });
    }

    /* --- The login itself --- */
    form.addEventListener('submit', function (event) {
        event.preventDefault();   // stop the browser doing its own form post
        clearErrors();

        button.disabled = true;
        button.textContent = 'Logging in…';

        window.api.post('/api/v1/auth/login', {
            email:    document.getElementById('email').value,
            password: document.getElementById('password').value,
            remember: document.getElementById('remember').checked,
        })
            .then(function (response) {
                // The session cookie is already set by the time we get here.
                // The server decides where to go next, not this page.
                window.location.href = response.redirect_to;
            })
            .catch(function (error) {
                if (error.errors) {
                    showFieldErrors(error.errors);
                } else {
                    showAlert(error.message);
                }

                button.disabled = false;
                button.textContent = 'Log in';
            });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLoginForm, { once: true });
} else {
    initializeLoginForm();
}
</script>
@endpush