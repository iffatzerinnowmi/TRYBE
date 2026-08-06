@extends('layouts.app')

@section('title', 'Profile')

@section('content')
@php
    $input = 'w-full rounded-xl border bg-surface-soft px-4 py-3 text-sm text-ink
              outline-none transition placeholder:text-steel focus:border-plum focus:bg-surface';
@endphp

<div class="wrap pb-20">

    <x-page-header
        eyebrow="Researcher · Profile"
        title="The page participants read before they apply."
        subtitle="A complete profile earns more trust — and more qualified applicants. Everything here appears on your public researcher page." />

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

        <div class="space-y-6">

            <x-panel label="Account" class="reveal">
                <form method="POST" action="{{ route('researcher.profile.account') }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

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
                                   class="{{ $input }} border-line-hi">
                        </div>
                    </div>

                    <x-btn type="submit">Save account details</x-btn>
                </form>
            </x-panel>

            <x-panel label="Public profile"
                     note="This is what participants see on your researcher page."
                     class="reveal reveal-d1">

                <form method="POST" action="{{ route('researcher.profile.update') }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="title" class="mb-2 block text-[13px] font-semibold text-ink">Title / role</label>
                        <input type="text" id="title" name="title" data-strength
                               value="{{ old('title', $profile->title) }}"
                               placeholder="Assistant Professor" class="{{ $input }} border-line-hi">
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="institution" class="mb-2 block text-[13px] font-semibold text-ink">Institution</label>
                            <input type="text" id="institution" name="institution" data-strength
                                   value="{{ old('institution', $profile->institution) }}"
                                   placeholder="BRAC University" class="{{ $input }} border-line-hi">
                        </div>

                        <div>
                            <label for="department" class="mb-2 block text-[13px] font-semibold text-ink">Department</label>
                            <input type="text" id="department" name="department" data-strength
                                   value="{{ old('department', $profile->department) }}"
                                   placeholder="Computer Science & Engineering" class="{{ $input }} border-line-hi">
                        </div>
                    </div>

                    <div>
                        <label for="institutional_email" class="mb-2 block text-[13px] font-semibold text-ink">
                            Institutional email
                        </label>
                        <input type="email" id="institutional_email" name="institutional_email" data-strength
                               value="{{ old('institutional_email', $profile->institutional_email) }}"
                               placeholder="you@bracu.ac.bd"
                               class="{{ $input }} {{ $errors->has('institutional_email') ? 'border-danger' : 'border-line-hi' }}">
                        @error('institutional_email') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="bio" class="mb-2 block text-[13px] font-semibold text-ink">Short bio</label>
                        <textarea id="bio" name="bio" rows="4" data-strength
                                  placeholder="A couple of sentences on what you research and why participants should take part."
                                  class="{{ $input }} border-line-hi resize-y">{{ old('bio', $profile->bio) }}</textarea>
                    </div>

                    <div>
                        <label for="research_areas" class="mb-2 block text-[13px] font-semibold text-ink">
                            Research areas
                        </label>
                        <input type="text" id="research_areas" name="research_areas" data-strength
                               value="{{ old('research_areas', $profile->research_areas) }}"
                               placeholder="HCI, usability, cognition" class="{{ $input }} border-line-hi">
                        <p class="mt-1.5 text-[11.5px] text-steel">Separate with commas.</p>
                    </div>

                    <div>
                        <label for="linkedin" class="mb-2 block text-[13px] font-semibold text-ink">LinkedIn</label>
                        <input type="text" id="linkedin" name="linkedin" data-strength
                               value="{{ old('linkedin', $profile->linkedin) }}"
                               placeholder="linkedin.com/in/you" class="{{ $input }} border-line-hi">
                    </div>

                    <x-btn type="submit">Save profile</x-btn>
                </form>
            </x-panel>

            <x-panel label="Change password" class="reveal reveal-d2">
                <form method="POST" action="{{ route('researcher.profile.password') }}" class="space-y-5">
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

            <x-panel class="reveal reveal-d1">
                @php $r = 42; $c = 2 * M_PI * $r; @endphp

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
                            <b id="strength-pct" class="font-display text-[19px] font-semibold text-ink">{{ $strength }}%</b>
                            <span class="font-mono text-[9px] uppercase tracking-wide text-steel">Complete</span>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-display text-[17px] font-semibold text-ink">Profile completeness</h3>
                        <p id="strength-msg" class="mt-1 text-[12.5px] leading-relaxed text-dim"></p>
                    </div>
                </div>
            </x-panel>

            <div class="reveal reveal-d2 rounded-panel bg-gradient-to-br from-ink to-plum p-6 text-white
                        shadow-[0_18px_40px_-20px_rgba(79,58,101,.7)]">
                <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-steel">
                    How participants see you
                </p>

                <div class="mt-4 flex items-center gap-3.5">
                    <x-avatar :name="$user->name" size="lg" />
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="truncate text-[15px] font-semibold text-white">{{ $user->name }}</span>
                            @if ($user->verification_status->value === 'verified')
                                <span class="flex h-4 w-4 items-center justify-center rounded-full
                                             bg-mint text-[10px] text-ink" title="Verified">✓</span>
                            @endif
                        </div>
                        <div class="truncate font-mono text-[11px] text-steel">{{ $profile->title ?: 'Title / role' }}</div>
                        <div class="truncate text-[11.5px] text-white/60">
                            {{ $profile->institution ?: 'Institution' }}
                            @if ($profile->department) · {{ $profile->department }} @endif
                        </div>
                    </div>
                </div>

                <p class="mt-4 text-[12.5px] leading-relaxed text-white/75">
                    {{ $profile->bio ?: 'Your short bio will appear here for participants to read before they apply.' }}
                </p>

                @if (filled($profile->research_areas))
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach (array_slice(array_filter(array_map('trim', explode(',', $profile->research_areas))), 0, 8) as $area)
                            <span class="rounded-full bg-white/15 px-2.5 py-1 text-[11px] text-white">{{ $area }}</span>
                        @endforeach
                    </div>
                @endif

                <p class="mt-5 border-t border-white/15 pt-3 font-mono text-[10px] text-steel">
                    ◆ Your verified badge shows here once approved
                </p>
            </div>

            <x-panel label="Verification" class="reveal reveal-d3">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-[13px] text-dim">Current status</span>
                    <x-badge tone="{{ match ($user->verification_status->value) {
                        'verified' => 'ok', 'pending' => 'flame',
                        'rejected' => 'danger', default => 'neutral',
                    } }}">
                        {{ $user->verification_status->label() }}
                    </x-badge>
                </div>

                <div class="mt-4">
                    <x-btn href="{{ route('verification.index') }}" variant="ghost" size="sm" class="w-full">
                        Manage verification
                    </x-btn>
                </div>
            </x-panel>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
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
            value === 0 ? 'A fuller profile earns more trust — and more qualified applicants.' :
            value < 50  ? 'Good start. Add your institution and bio so participants know who you are.' :
            value < 100 ? "Nearly there. A couple more fields and your page is complete." :
                          'Complete. Participants have everything they need to trust your studies.';
    }

    inputs.forEach(function (el) {
        el.addEventListener('input', update);
        el.addEventListener('change', update);
    });

    update();
})();
</script>
@endpush
