@extends('layouts.auth')

@section('title', 'Sign up')
@section('side-eyebrow', 'Create your account')

@section('side-headline')
    <span id="side-headline">Pick a role — the platform adapts to how you'll use it.</span>
@endsection

@section('side-sub')
    <span id="side-sub">Every account starts the same way: give a little before you take.
    Choose below to see what your account unlocks.</span>
@endsection

@section('side-foot')
    <div class="space-y-3">
        <div data-role-info="participant"
             class="flex items-start gap-3 rounded-xl border border-white/15 bg-white/5 p-3.5 transition">
            <span class="text-lg">🙋</span>
            <div>
                <div class="text-[13.5px] font-semibold text-white">Participant</div>
                <div class="text-[12px] text-white/60">Volunteer {{ $unlockTarget }} studies to unlock paid research.</div>
            </div>
        </div>

        <div data-role-info="researcher"
             class="flex items-start gap-3 rounded-xl border border-white/15 bg-white/5 p-3.5 transition">
            <span class="text-lg">🔬</span>
            <div>
                <div class="text-[13.5px] font-semibold text-white">Researcher</div>
                <div class="text-[12px] text-white/60">Submit institutional ID for a verified badge and full reach.</div>
            </div>
        </div>

        <div data-role-info="organization"
             class="flex items-start gap-3 rounded-xl border border-white/15 bg-white/5 p-3.5 transition">
            <span class="text-lg">🏛️</span>
            <div>
                <div class="text-[13.5px] font-semibold text-white">Organization</div>
                <div class="text-[12px] text-white/60">Verified partners get unlimited studies and priority matching.</div>
            </div>
        </div>
    </div>
@endsection

@section('content')
@php
    // If validation failed, keep the role the user had picked.
    $activeRole = old('role', $selectedRole->value);

    // Shared input styling, so every field looks the same.
    $input = 'w-full rounded-xl border bg-surface-soft px-4 py-3 text-sm text-ink
              outline-none transition placeholder:text-steel focus:border-plum focus:bg-surface';
@endphp

<div class="rounded-panel border border-line bg-surface p-7 shadow-soft sm:p-9">

    <div class="mb-6">
        <p class="font-mono text-[11px] uppercase tracking-[0.22em] text-steel">Sign up</p>
        <h2 class="mt-2.5 font-display text-[28px] font-semibold text-ink">Let's get you set up.</h2>
        <p class="mt-2 text-[13.5px] text-dim">
            Already have an account?
            <a href="/login" class="font-semibold text-plum hover:underline">Log in</a>
        </p>
    </div>

    @if ($errors->any())
        <x-alert type="error" class="mb-5">
            Please fix the {{ $errors->count() }} highlighted
            {{ $errors->count() === 1 ? 'field' : 'fields' }} below.
        </x-alert>
    @endif

    {{-- Role picker. These buttons only change the hidden input below —
         the server still decides what is valid. --}}
    <div class="mb-6 grid grid-cols-3 gap-2.5">
        @foreach (['participant' => '🙋 Participant', 'researcher' => '🔬 Researcher', 'organization' => '🏛️ Organization'] as $value => $label)
            <button type="button" data-role-btn="{{ $value }}"
                    class="rounded-xl border py-3 text-[12.5px] font-semibold transition
                           {{ $activeRole === $value
                              ? 'border-plum bg-plum text-white'
                              : 'border-line-hi text-ink hover:border-plum hover:text-plum' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- enctype is required because two roles upload a file. --}}
    <form method="POST" action="/signup" enctype="multipart/form-data" class="space-y-5">
        @csrf

        <input type="hidden" name="role" id="role" value="{{ $activeRole }}">

        {{-- ---------- shared fields ---------- --}}
        <div>
            <label for="name" class="mb-2 block text-[13px] font-semibold text-ink">
                Full name <span class="text-danger">*</span>
            </label>
            <input type="text" id="name" name="name" value="{{ old('name') }}"
                   placeholder="Iffat Zerin Nowmi" required
                   class="{{ $input }} {{ $errors->has('name') ? 'border-danger' : 'border-line-hi' }}">
            @error('name') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="email" class="mb-2 block text-[13px] font-semibold text-ink">
                    Email <span class="text-danger">*</span>
                </label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       placeholder="you@example.com" required
                       class="{{ $input }} {{ $errors->has('email') ? 'border-danger' : 'border-line-hi' }}">
                @error('email') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="phone" class="mb-2 block text-[13px] font-semibold text-ink">
                    Phone <span class="text-danger">*</span>
                </label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                       placeholder="+880 1XXX-XXXXXX" required
                       class="{{ $input }} {{ $errors->has('phone') ? 'border-danger' : 'border-line-hi' }}">
                @error('phone') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="password" class="mb-2 block text-[13px] font-semibold text-ink">
                    Password <span class="text-danger">*</span>
                </label>
                <input type="password" id="password" name="password" required
                       placeholder="••••••••"
                       class="{{ $input }} {{ $errors->has('password') ? 'border-danger' : 'border-line-hi' }}">

                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-steel/25">
                    <i id="pw-bar" class="block h-full w-0 rounded-full transition-all duration-300"></i>
                </div>

                @error('password') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-2 block text-[13px] font-semibold text-ink">
                    Confirm password <span class="text-danger">*</span>
                </label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                       placeholder="••••••••"
                       class="{{ $input }} border-line-hi">
                <p id="pw-match" class="mt-1.5 text-[12px]"></p>
            </div>
        </div>

        {{-- ---------- researcher only ---------- --}}
        <div data-role-group="researcher"
             class="space-y-5 rounded-xl border border-line bg-surface-soft p-5">
            <p class="font-mono text-[11px] uppercase tracking-[0.16em] text-steel">
                Researcher verification
            </p>

            <div>
                <label for="institutional_affiliation" class="mb-2 block text-[13px] font-semibold text-ink">
                    Institutional affiliation <span class="text-danger">*</span>
                </label>
                <input type="text" id="institutional_affiliation" name="institutional_affiliation"
                       value="{{ old('institutional_affiliation') }}"
                       placeholder="BRAC University, Dept. of CSE"
                       class="{{ $input }} {{ $errors->has('institutional_affiliation') ? 'border-danger' : 'border-line-hi' }}">
                @error('institutional_affiliation') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-[13px] font-semibold text-ink">
                    Credential document <span class="text-danger">*</span>
                    <span class="ml-1 font-normal text-steel">ID or letter, PDF/JPG</span>
                </label>

                <label for="credential_document"
                       class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-dashed
                              border-line-hi bg-surface px-4 py-7 text-center transition hover:border-plum">
                    <span class="text-2xl">📄</span>
                    <span class="text-[13px] text-dim"><b class="text-ink">Click to upload</b> or drag a file here</span>
                    <input type="file" id="credential_document" name="credential_document"
                           accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                </label>

                <p id="credential_document-name" class="mt-2 text-[12px] text-ok"></p>
                @error('credential_document') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- ---------- organization only ---------- --}}
        <div data-role-group="organization"
             class="space-y-5 rounded-xl border border-line bg-surface-soft p-5">
            <p class="font-mono text-[11px] uppercase tracking-[0.16em] text-steel">
                Organization details
            </p>

            <div>
                <label for="organization_name" class="mb-2 block text-[13px] font-semibold text-ink">
                    Organization name <span class="text-danger">*</span>
                </label>
                <input type="text" id="organization_name" name="organization_name"
                       value="{{ old('organization_name') }}" placeholder="BRAC University"
                       class="{{ $input }} {{ $errors->has('organization_name') ? 'border-danger' : 'border-line-hi' }}">
                @error('organization_name') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="organization_type" class="mb-2 block text-[13px] font-semibold text-ink">
                        Organization type <span class="text-danger">*</span>
                    </label>
                    <select id="organization_type" name="organization_type"
                            class="{{ $input }} {{ $errors->has('organization_type') ? 'border-danger' : 'border-line-hi' }}">
                        <option value="">Select type</option>
                        @foreach ($orgTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('organization_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('organization_type') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="location" class="mb-2 block text-[13px] font-semibold text-ink">
                        Location <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="location" name="location" value="{{ old('location') }}"
                           placeholder="Dhaka, Bangladesh"
                           class="{{ $input }} {{ $errors->has('location') ? 'border-danger' : 'border-line-hi' }}">
                    @error('location') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="mb-2 block text-[13px] font-semibold text-ink">
                    Registration documents <span class="text-danger">*</span>
                    <span class="ml-1 font-normal text-steel">PDF</span>
                </label>

                <label for="registration_documents"
                       class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-dashed
                              border-line-hi bg-surface px-4 py-7 text-center transition hover:border-plum">
                    <span class="text-2xl">📑</span>
                    <span class="text-[13px] text-dim"><b class="text-ink">Click to upload</b> or drag a file here</span>
                    <input type="file" id="registration_documents" name="registration_documents"
                           accept=".pdf" class="hidden">
                </label>

                <p id="registration_documents-name" class="mt-2 text-[12px] text-ok"></p>
                @error('registration_documents') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
            </div>
        </div>

        <x-btn type="submit" class="w-full">Create account</x-btn>
    </form>

    <p class="mt-6 text-center text-[11.5px] text-steel">
        By signing up you agree to TRYBE's Terms and Privacy Policy.
    </p>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var roleInput = document.getElementById('role');

    var copy = {
        participant: {
            h: "Pick a role — the platform adapts to how you'll use it.",
            s: "Every account starts the same way: give a little before you take. Choose below to see what your account unlocks."
        },
        researcher: {
            h: "Get verified, then get full reach.",
            s: "Free-tier researchers can post right away — verification just adds a trust badge and removes reach limits."
        },
        organization: {
            h: "Institutional accounts get priority matching.",
            s: "Once approved, you post unlimited studies under your organization's name with a Partner badge."
        }
    };

    function setRole(role) {
        roleInput.value = role;

        // Highlight the chosen button.
        document.querySelectorAll('[data-role-btn]').forEach(function (btn) {
            var on = btn.dataset.roleBtn === role;
            btn.classList.toggle('border-plum', on);
            btn.classList.toggle('bg-plum', on);
            btn.classList.toggle('text-white', on);
            btn.classList.toggle('border-line-hi', !on);
            btn.classList.toggle('text-ink', !on);
        });

        // Show only the field group that belongs to this role.
        document.querySelectorAll('[data-role-group]').forEach(function (group) {
            group.classList.toggle('hidden', group.dataset.roleGroup !== role);
        });

        // Highlight the matching card on the left panel.
        document.querySelectorAll('[data-role-info]').forEach(function (card) {
            var on = card.dataset.roleInfo === role;
            card.classList.toggle('bg-white/15', on);
            card.classList.toggle('border-white/40', on);
            card.classList.toggle('bg-white/5', !on);
            card.classList.toggle('border-white/15', !on);
        });

        var headline = document.getElementById('side-headline');
        var sub = document.getElementById('side-sub');
        if (headline) headline.textContent = copy[role].h;
        if (sub) sub.textContent = copy[role].s;
    }

    document.querySelectorAll('[data-role-btn]').forEach(function (btn) {
        btn.addEventListener('click', function () { setRole(btn.dataset.roleBtn); });
    });

    // Start on whatever role the server told us to show.
    setRole(roleInput.value);

    /* ---- file name preview ---- */
    ['credential_document', 'registration_documents'].forEach(function (id) {
        var input = document.getElementById(id);
        if (!input) return;
        input.addEventListener('change', function () {
            var label = document.getElementById(id + '-name');
            label.textContent = this.files[0] ? '✓ ' + this.files[0].name : '';
        });
    });

    /* ---- password strength (a hint only; the server enforces min 8) ---- */
    var pw = document.getElementById('password');
    var confirm = document.getElementById('password_confirmation');
    var bar = document.getElementById('pw-bar');
    var match = document.getElementById('pw-match');

    function strength() {
        var v = pw.value, score = 0;
        if (v.length >= 8) score++;
        if (/[A-Z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;

        bar.style.width = (score / 4 * 100) + '%';
        bar.style.background = score <= 1 ? '#B4453C'
                             : score === 2 ? '#E8945A'
                             : score === 3 ? '#E8B45A' : '#2F7D5B';
    }

    function checkMatch() {
        if (!confirm.value) { match.textContent = ''; return; }
        var ok = pw.value === confirm.value;
        match.textContent = ok ? '✓ Passwords match' : "Passwords don't match";
        match.className = 'mt-1.5 text-[12px] ' + (ok ? 'text-ok' : 'text-danger');
    }

    pw.addEventListener('input', function () { strength(); checkMatch(); });
    confirm.addEventListener('input', checkMatch);
})();
</script>
@endpush
