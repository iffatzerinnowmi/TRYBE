@extends('layouts.auth')

@section('title', 'Log in')
@section('side-eyebrow', 'Welcome back')
@section('side-headline', 'Your streak, karma, and unlock progress are exactly where you left them.')
@section('side-sub', 'Log in to pick up your studies, track your credential level, and keep your reciprocity streak alive.')

@section('side-foot')
    <div class="flex gap-10">
        <div>
            <b class="block font-display text-3xl font-semibold text-mint">{{ $unlockTarget }}</b>
            <span class="mt-1 block font-mono text-[10.5px] uppercase tracking-[0.16em] text-steel">
                Studies to unlock paid
            </span>
        </div>
        <div>
            <b class="block font-display text-3xl font-semibold text-mint">{{ $tierCount }}</b>
            <span class="mt-1 block font-mono text-[10.5px] uppercase tracking-[0.16em] text-steel">
                Credential tiers
            </span>
        </div>
    </div>
@endsection

@section('content')
<div class="rounded-panel border border-line bg-surface p-7 shadow-soft sm:p-9">

    <div class="mb-6">
        <p class="font-mono text-[11px] uppercase tracking-[0.22em] text-steel">Log in</p>
        <h2 class="mt-2.5 font-display text-[28px] font-semibold text-ink">Good to see you.</h2>
        <p class="mt-2 text-[13.5px] text-dim">
            New to TRYBE?
            <a href="/signup" class="font-semibold text-plum hover:underline">Create an account</a>
        </p>
    </div>

    {{-- Any message put in the session, e.g. after logging out. --}}
    @if (session('status'))
        <x-alert type="success" class="mb-5">{{ session('status') }}</x-alert>
    @endif

    {{-- The "wrong credentials" banner. AuthController puts this message on
         the 'email' key, so @error('email') catches both a bad format and a
         failed login attempt. --}}
    @error('email')
        <x-alert type="error" class="mb-5">{{ $message }}</x-alert>
    @enderror

    {{-- action="/login" and the name attributes are fixed. Do not rename. --}}
    <form method="POST" action="/login" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="mb-2 block text-[13px] font-semibold text-ink">
                Email address <span class="text-danger">*</span>
            </label>

            <input type="email" id="email" name="email"
                   value="{{ old('email') }}"
                   placeholder="you@example.com"
                   autocomplete="email" required
                   class="w-full rounded-xl border bg-surface-soft px-4 py-3 text-sm text-ink
                          outline-none transition placeholder:text-steel
                          focus:border-plum focus:bg-surface
                          {{ $errors->has('email') ? 'border-danger' : 'border-line-hi' }}">
        </div>

        <div>
            <label for="password" class="mb-2 block text-[13px] font-semibold text-ink">
                Password <span class="text-danger">*</span>
            </label>

            <div class="relative">
                <input type="password" id="password" name="password"
                       placeholder="••••••••"
                       autocomplete="current-password" required
                       class="w-full rounded-xl border bg-surface-soft px-4 py-3 pr-16 text-sm text-ink
                              outline-none transition placeholder:text-steel
                              focus:border-plum focus:bg-surface
                              {{ $errors->has('password') ? 'border-danger' : 'border-line-hi' }}">

                <button type="button" id="pw-toggle"
                        class="absolute right-3 top-1/2 -translate-y-1/2 font-mono text-[10.5px]
                               tracking-wider text-steel transition hover:text-plum">
                    SHOW
                </button>
            </div>

            @error('password')
                <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <label class="flex cursor-pointer items-center gap-2 text-[13px] text-dim">
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}
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
/* Show / hide the password. Purely cosmetic — the real check is server-side. */
document.getElementById('pw-toggle').addEventListener('click', function () {
    var input = document.getElementById('password');
    var hidden = input.type === 'password';
    input.type = hidden ? 'text' : 'password';
    this.textContent = hidden ? 'HIDE' : 'SHOW';
});
</script>
@endpush
