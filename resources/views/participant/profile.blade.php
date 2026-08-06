@extends('layouts.app')

@section('title', 'Profile')

@section('content')
@php
    $input = 'w-full rounded-xl border bg-surface-soft px-4 py-3 text-sm text-ink
              outline-none transition placeholder:text-steel focus:border-plum focus:bg-surface';
@endphp

<div class="wrap pb-20">

    <x-page-header
        eyebrow="Participant · Profile"
        title="Build the profile researchers match you on."
        subtitle="The more of your background you share, the smarter your study matches get — and the more you stand out to researchers reviewing applicants." />

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert type="error" class="mb-6">
            Please fix the {{ $errors->count() }} highlighted
            {{ $errors->count() === 1 ? 'field' : 'fields' }}.
        </x-alert>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[1.5fr_1fr]">

        {{-- ================= LEFT: the forms ================= --}}
        <div class="space-y-6">

            {{-- Account --}}
            <x-panel label="Account" class="reveal">
                <form method="POST" action="{{ route('participant.profile.account') }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <div class="flex items-center gap-4">
                        <x-avatar :name="$user->name" size="lg" />
                        <div>
                            <div class="text-[15px] font-semibold text-ink">{{ $user->name }}</div>
                            <div class="font-mono text-[11.5px] text-steel">
                                Your initials are used until photo uploads are added.
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="name" class="mb-2 block text-[13px] font-semibold text-ink">Full name</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                                   class="{{ $input }} {{ $errors->has('name') ? 'border-danger' : 'border-line-hi' }}">
                            @error('name') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="email" class="mb-2 block text-[13px] font-semibold text-ink">Email</label>
                            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}"
                                   class="{{ $input }} {{ $errors->has('email') ? 'border-danger' : 'border-line-hi' }}">
                            @error('email') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="phone" class="mb-2 block text-[13px] font-semibold text-ink">Phone</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}"
                                   class="{{ $input }} border-line-hi">
                        </div>

                        <div>
                            <label for="location" class="mb-2 block text-[13px] font-semibold text-ink">Location</label>
                            <input type="text" id="location" name="location"
                                   value="{{ old('location', $user->location) }}"
                                   placeholder="Dhaka, Bangladesh" class="{{ $input }} border-line-hi">
                        </div>
                    </div>

                    <x-btn type="submit">Save account details</x-btn>
                </form>
            </x-panel>

            {{-- Background --}}
            <x-panel label="Background"
                     note="Used by the matching engine, and shown to researchers when you apply."
                     class="reveal reveal-d1">

                <form method="POST" action="{{ route('participant.profile.update') }}" class="space-y-5"
                      id="profile-form">
                    @csrf
                    @method('PATCH')

                    <div class="grid gap-5 sm:grid-cols-3">
                        <div>
                            <label for="age" class="mb-2 block text-[13px] font-semibold text-ink">Age</label>
                            <input type="number" id="age" name="age" min="16" max="120" data-strength
                                   value="{{ old('age', $profile->age) }}"
                                   class="{{ $input }} {{ $errors->has('age') ? 'border-danger' : 'border-line-hi' }}">
                            @error('age') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="gender" class="mb-2 block text-[13px] font-semibold text-ink">Gender</label>
                            <select id="gender" name="gender" data-strength class="{{ $input }} border-line-hi">
                                <option value="">Select</option>
                                @foreach ($genders as $option)
                                    <option value="{{ $option }}"
                                        @selected(old('gender', $profile->gender) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="occupation" class="mb-2 block text-[13px] font-semibold text-ink">Occupation</label>
                            <input type="text" id="occupation" name="occupation" data-strength
                                   value="{{ old('occupation', $profile->occupation) }}"
                                   placeholder="Undergraduate, CSE" class="{{ $input }} border-line-hi">
                        </div>
                    </div>

                    <div>
                        <label for="health_background" class="mb-2 block text-[13px] font-semibold text-ink">
                            Health background
                            <span class="ml-1 font-normal text-steel">Only shared with studies that screen for it</span>
                        </label>
                        <textarea id="health_background" name="health_background" rows="3" data-strength
                                  placeholder="Any conditions, allergies or medications relevant to research eligibility. Write None if not applicable."
                                  class="{{ $input }} border-line-hi resize-y">{{ old('health_background', $profile->health_background) }}</textarea>
                    </div>

                    <div>
                        <label for="interests" class="mb-2 block text-[13px] font-semibold text-ink">
                            Academic & professional interests
                        </label>
                        <input type="text" id="interests" name="interests" data-strength
                               value="{{ old('interests', $profile->interests) }}"
                               placeholder="HCI, UX research, psychology" class="{{ $input }} border-line-hi">
                        <p class="mt-1.5 text-[11.5px] text-steel">Separate with commas.</p>
                    </div>

                    <div>
                        <label for="skills" class="mb-2 block text-[13px] font-semibold text-ink">Skills</label>
                        <input type="text" id="skills" name="skills" data-strength
                               value="{{ old('skills', $profile->skills) }}"
                               placeholder="Python, Bangla, UI testing" class="{{ $input }} border-line-hi">
                        <p class="mt-1.5 text-[11.5px] text-steel">Separate with commas.</p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="linkedin" class="mb-2 block text-[13px] font-semibold text-ink">LinkedIn</label>
                            <input type="text" id="linkedin" name="linkedin" data-strength
                                   value="{{ old('linkedin', $profile->linkedin) }}"
                                   placeholder="linkedin.com/in/you" class="{{ $input }} border-line-hi">
                        </div>

                        <div>
                            <label for="github" class="mb-2 block text-[13px] font-semibold text-ink">GitHub</label>
                            <input type="text" id="github" name="github" data-strength
                                   value="{{ old('github', $profile->github) }}"
                                   placeholder="github.com/you" class="{{ $input }} border-line-hi">
                        </div>
                    </div>

                    <x-btn type="submit">Save profile</x-btn>
                </form>
            </x-panel>

            {{-- Password --}}
            <x-panel label="Change password" class="reveal reveal-d2">
                <form method="POST" action="{{ route('participant.profile.password') }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="current_password" class="mb-2 block text-[13px] font-semibold text-ink">
                            Current password
                        </label>
                        <input type="password" id="current_password" name="current_password"
                               class="{{ $input }} {{ $errors->has('current_password') ? 'border-danger' : 'border-line-hi' }}">
                        @error('current_password') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="password" class="mb-2 block text-[13px] font-semibold text-ink">New password</label>
                            <input type="password" id="password" name="password"
                                   class="{{ $input }} {{ $errors->has('password') ? 'border-danger' : 'border-line-hi' }}">
                            @error('password') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-2 block text-[13px] font-semibold text-ink">
                                Confirm new password
                            </label>
                            <input type="password" id="password_confirmation" name="password_confirmation"
                                   class="{{ $input }} border-line-hi">
                        </div>
                    </div>

                    <x-btn type="submit" variant="ghost">Change password</x-btn>
                </form>
            </x-panel>
        </div>

        {{-- ================= RIGHT ================= --}}
        <div class="space-y-6">

            {{-- Strength ring --}}
            <x-panel class="reveal reveal-d1">
                @php
                    $r = 42;
                    $c = 2 * M_PI * $r;
                @endphp

                <div class="flex items-center gap-5">
                    <div class="relative h-[96px] w-[96px] shrink-0">
                        <svg width="96" height="96" class="-rotate-90">
                            <circle cx="48" cy="48" r="{{ $r }}" fill="none"
                                    stroke="rgba(149,173,190,.28)" stroke-width="8" />
                            <circle id="strength-ring" cx="48" cy="48" r="{{ $r }}" fill="none"
                                    stroke="#574F7D" stroke-width="8" stroke-linecap="round"
                                    stroke-dasharray="{{ $c }}"
                                    stroke-dashoffset="{{ $c * (1 - $strength / 100) }}"
                                    style="transition:stroke-dashoffset .5s cubic-bezier(.2,.7,.3,1)" />
                        </svg>

                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <b id="strength-pct" class="font-display text-[19px] font-semibold text-ink">
                                {{ $strength }}%
                            </b>
                            <span class="font-mono text-[9px] uppercase tracking-wide text-steel">Strength</span>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-display text-[17px] font-semibold text-ink">Profile strength</h3>
                        <p id="strength-msg" class="mt-1 text-[12.5px] leading-relaxed text-dim"></p>
                    </div>
                </div>
            </x-panel>

            {{-- Live preview --}}
            <div class="reveal reveal-d2 rounded-panel bg-gradient-to-br from-ink to-plum p-6 text-white
                        shadow-[0_18px_40px_-20px_rgba(79,58,101,.7)]">
                <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-steel">
                    How researchers see you
                </p>

                <div class="mt-4 flex items-center gap-3.5">
                    <x-avatar :name="$user->name" size="lg" />
                    <div class="min-w-0">
                        <div class="truncate text-[15px] font-semibold text-white">{{ $user->name }}</div>
                        <div class="truncate font-mono text-[11px] text-steel">
                            {{ $user->location ?: 'Location' }} · {{ $profile->occupation ?: 'Occupation' }}
                        </div>
                    </div>
                </div>

                <span class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5
                             font-mono text-[10.5px] uppercase tracking-wider text-white">
                    <span class="h-1.5 w-1.5 rounded-full bg-mint"></span>
                    {{ $profile->credential_level->label() }} ·
                    {{ $profile->completed_studies_count }} completed
                </span>

                @if (filled($profile->skills))
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach (array_slice(array_filter(array_map('trim', explode(',', $profile->skills))), 0, 8) as $skill)
                            <span class="rounded-full bg-white/15 px-2.5 py-1 text-[11px] text-white">{{ $skill }}</span>
                        @endforeach
                    </div>
                @endif

                <p class="mt-5 border-t border-white/15 pt-3 font-mono text-[10px] text-steel">
                    ◆ Shown to researchers when you apply to a study
                </p>
            </div>

            <x-panel label="Why this matters" class="reveal reveal-d3">
                <p class="text-[13px] leading-relaxed text-dim">
                    Researchers screen applicants on these fields. A blank occupation or
                    empty interests means you won't surface for studies looking for
                    exactly your background.
                </p>
                <p class="mt-3 text-[13px] leading-relaxed text-dim">
                    Health background is only shared with studies that screen for it, and
                    only after you apply.
                </p>
            </x-panel>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
/* Live profile strength. The saved figure comes from PHP; this just keeps the
   ring honest while you type, before you press Save. */
(function () {
    var inputs = Array.prototype.slice.call(document.querySelectorAll('[data-strength]'));
    var ring = document.getElementById('strength-ring');
    var pct = document.getElementById('strength-pct');
    var msg = document.getElementById('strength-msg');

    if (!inputs.length || !ring) return;

    var C = {{ 2 * M_PI * 42 }};

    function update() {
        var filled = inputs.filter(function (el) { return el.value.trim() !== ''; }).length;
        var value = Math.round(filled / inputs.length * 100);

        ring.style.strokeDashoffset = C * (1 - value / 100);
        pct.textContent = value + '%';

        msg.textContent =
            value === 0   ? 'Start filling in your details — a complete profile gets matched to far more studies.' :
            value < 50    ? 'Good start. Add your background and skills to unlock better matches.' :
            value < 100   ? "Looking strong. A couple more fields and you're fully matchable." :
                            "Complete. You're eligible for the widest pool of studies.";
    }

    inputs.forEach(function (el) {
        el.addEventListener('input', update);
        el.addEventListener('change', update);
    });

    update();
})();
</script>
@endpush
