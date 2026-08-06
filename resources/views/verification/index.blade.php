@extends('layouts.app')

@section('title', 'Verification')

@section('content')
@php
    $input = 'w-full rounded-xl border bg-surface-soft px-4 py-3 text-sm text-ink
              outline-none transition placeholder:text-steel focus:border-plum focus:bg-surface';

    $status = $user->verification_status->value;

    $skin = match ($status) {
        'verified' => ['border-ok/30 bg-ok/10', '✓', 'text-ok'],
        'pending'  => ['border-flame/30 bg-flame/10', '⏳', 'text-flame'],
        'rejected' => ['border-danger/30 bg-danger/10', '✕', 'text-danger'],
        default    => ['border-line bg-surface-soft', '—', 'text-steel'],
    };
@endphp

<div class="wrap pb-20">

    <x-page-header
        eyebrow="{{ $user->role->label() }} · Verification"
        title="Prove who you are, once."
        subtitle="Unverified accounts can still post studies, but with limited reach. A verified badge means participants can trust your listings on sight." />

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert type="error" class="mb-6">
            Please fix the {{ $errors->count() }} highlighted
            {{ $errors->count() === 1 ? 'field' : 'fields' }}.
        </x-alert>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[1.4fr_1fr]">

        <div class="space-y-6">

            {{-- Current status --}}
            <x-panel label="Current status" class="reveal">
                <div class="flex items-start gap-4 rounded-xl border p-5 {{ $skin[0] }}">
                    <span class="text-2xl {{ $skin[2] }}">{{ $skin[1] }}</span>

                    <div class="min-w-0">
                        <div class="text-[15px] font-semibold text-ink">
                            {{ $user->verification_status->label() }}
                        </div>

                        <p class="mt-1.5 text-[13px] leading-relaxed text-dim">
                            @if ($status === 'verified')
                                Your credentials were confirmed. The badge is live on your public
                                profile and your listings reach the full participant feed.
                            @elseif ($status === 'pending')
                                An admin is reviewing your documents. You'll get a notification the
                                moment there's a decision — nothing else to do for now.
                            @elseif ($status === 'rejected')
                                Your last application wasn't approved. Read the reason below, then
                                submit again with corrected documents.
                            @else
                                You haven't applied yet. Submitting takes about a minute.
                            @endif
                        </p>

                        @if ($latest?->rejection_reason && $status === 'rejected')
                            <div class="mt-3 rounded-xl border border-danger/25 bg-surface px-4 py-3">
                                <p class="font-mono text-[10.5px] uppercase tracking-[0.16em] text-steel">
                                    Reason given
                                </p>
                                <p class="mt-1.5 text-[13px] text-ink">{{ $latest->rejection_reason }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </x-panel>

            {{-- The form, only when it makes sense --}}
            @if ($canApply)
                <x-panel label="{{ $status === 'rejected' ? 'Apply again' : 'Apply for verification' }}"
                         note="Documents are stored privately and only visible to platform admins."
                         class="reveal reveal-d1">

                    <form method="POST" action="{{ route('verification.store') }}"
                          enctype="multipart/form-data" class="space-y-5">
                        @csrf

                        @if ($user->role->value === 'researcher')
                            <div>
                                <label for="institutional_email" class="mb-2 block text-[13px] font-semibold text-ink">
                                    Institutional email <span class="text-danger">*</span>
                                </label>
                                <input type="email" id="institutional_email" name="institutional_email"
                                       value="{{ old('institutional_email', $latest?->institutional_email ?? $user->email) }}"
                                       placeholder="you@bracu.ac.bd"
                                       class="{{ $input }} {{ $errors->has('institutional_email') ? 'border-danger' : 'border-line-hi' }}">
                                @error('institutional_email') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="institutional_affiliation" class="mb-2 block text-[13px] font-semibold text-ink">
                                    Institutional affiliation <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="institutional_affiliation" name="institutional_affiliation"
                                       value="{{ old('institutional_affiliation', $latest?->institutional_affiliation) }}"
                                       placeholder="BRAC University, Dept. of CSE"
                                       class="{{ $input }} {{ $errors->has('institutional_affiliation') ? 'border-danger' : 'border-line-hi' }}">
                                @error('institutional_affiliation') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-[13px] font-semibold text-ink">
                                    Credential document <span class="text-danger">*</span>
                                    <span class="ml-1 font-normal text-steel">Staff ID or letter · PDF/JPG/PNG</span>
                                </label>

                                <label for="credential_document"
                                       class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border
                                              border-dashed border-line-hi bg-surface px-4 py-8 text-center
                                              transition hover:border-plum">
                                    <span class="text-2xl">📄</span>
                                    <span class="text-[13px] text-dim">
                                        <b class="text-ink">Click to upload</b> or drag a file here
                                    </span>
                                    <input type="file" id="credential_document" name="credential_document"
                                           accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                                </label>

                                <p id="credential_document-name" class="mt-2 text-[12px] text-ok"></p>
                                @error('credential_document') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        @if ($user->role->value === 'organization')
                            <div>
                                <label for="organization_name" class="mb-2 block text-[13px] font-semibold text-ink">
                                    Organization name <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="organization_name" name="organization_name"
                                       value="{{ old('organization_name', $user->organization_name) }}"
                                       class="{{ $input }} {{ $errors->has('organization_name') ? 'border-danger' : 'border-line-hi' }}">
                                @error('organization_name') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="organization_type" class="mb-2 block text-[13px] font-semibold text-ink">
                                    Organization type <span class="text-danger">*</span>
                                </label>
                                <select id="organization_type" name="organization_type"
                                        class="{{ $input }} {{ $errors->has('organization_type') ? 'border-danger' : 'border-line-hi' }}">
                                    <option value="">Select type</option>
                                    @foreach ($orgTypes as $value => $label)
                                        <option value="{{ $value }}"
                                            @selected(old('organization_type', $user->organization_type) === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('organization_type') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-[13px] font-semibold text-ink">
                                    Registration documents <span class="text-danger">*</span>
                                    <span class="ml-1 font-normal text-steel">PDF</span>
                                </label>

                                <label for="registration_documents"
                                       class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border
                                              border-dashed border-line-hi bg-surface px-4 py-8 text-center
                                              transition hover:border-plum">
                                    <span class="text-2xl">📑</span>
                                    <span class="text-[13px] text-dim">
                                        <b class="text-ink">Click to upload</b> or drag a file here
                                    </span>
                                    <input type="file" id="registration_documents" name="registration_documents"
                                           accept=".pdf" class="hidden">
                                </label>

                                <p id="registration_documents-name" class="mt-2 text-[12px] text-ok"></p>
                                @error('registration_documents') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <x-btn type="submit">
                            {{ $status === 'rejected' ? 'Resubmit for review' : 'Submit for review' }}
                        </x-btn>
                    </form>
                </x-panel>
            @else
                <x-panel label="Apply for verification" class="reveal reveal-d1">
                    <p class="py-3 text-[13.5px] leading-relaxed text-dim">
                        @if ($status === 'pending')
                            You already have a request in the queue, so there's nothing to submit.
                            You'll be notified as soon as an admin decides.
                        @else
                            You're already verified. If your institution changes, contact an admin.
                        @endif
                    </p>
                </x-panel>
            @endif

            {{-- History --}}
            <x-panel label="Your applications" class="reveal reveal-d2">
                @forelse ($history as $request)
                    <div class="border-b border-line py-4 last:border-none last:pb-1">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <x-badge tone="{{ match ($request->status->value) {
                                'verified' => 'ok', 'pending' => 'flame',
                                'rejected' => 'danger', default => 'neutral',
                            } }}">
                                {{ $request->status->label() }}
                            </x-badge>

                            <span class="font-mono text-[11px] text-steel">
                                Submitted {{ $request->created_at->format('d M Y, H:i') }}
                            </span>

                            @if ($request->reviewed_at)
                                <span class="font-mono text-[11px] text-steel">
                                    · Reviewed by {{ $request->reviewer->name ?? 'admin' }}
                                    {{ $request->reviewed_at->diffForHumans() }}
                                </span>
                            @endif
                        </div>

                        <p class="mt-1.5 text-[13px] text-dim">
                            {{ $request->institutional_affiliation
                               ?? $request->organization_name
                               ?? '—' }}
                        </p>

                        @if ($request->rejection_reason)
                            <p class="mt-1.5 text-[12.5px] text-danger">{{ $request->rejection_reason }}</p>
                        @endif
                    </div>
                @empty
                    <p class="py-3 text-[13.5px] text-dim">
                        You haven't submitted a verification request yet.
                    </p>
                @endforelse
            </x-panel>
        </div>

        {{-- ================= RIGHT ================= --}}
        <div class="space-y-6">

            <x-panel label="What verification changes" class="reveal reveal-d1">
                <div class="space-y-3.5">
                    <div class="flex gap-3">
                        <span class="text-ok">✓</span>
                        <p class="text-[13px] leading-relaxed text-dim">
                            A <b class="text-ink">verified badge</b> on your public profile and beside
                            every study you post.
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <span class="text-ok">✓</span>
                        <p class="text-[13px] leading-relaxed text-dim">
                            <b class="text-ink">Full reach</b> — your listings appear across the whole
                            participant feed instead of a limited slice.
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <span class="text-ok">✓</span>
                        <p class="text-[13px] leading-relaxed text-dim">
                            Higher placement in <b class="text-ink">researcher search results</b>.
                        </p>
                    </div>
                </div>
            </x-panel>

            <x-panel label="How review works" class="reveal reveal-d2">
                <ol class="space-y-3 text-[12.5px] leading-relaxed text-dim">
                    <li><b class="text-ink">1.</b> You submit your affiliation and a credential document.</li>
                    <li><b class="text-ink">2.</b> Your status becomes <b class="text-ink">Pending</b> and
                        the request joins the admin queue.</li>
                    <li><b class="text-ink">3.</b> An admin opens your document and approves or rejects.</li>
                    <li><b class="text-ink">4.</b> You get a notification either way. If rejected, the
                        reason appears here and you can apply again.</li>
                </ol>

                <p class="mt-4 rounded-xl bg-surface-soft px-3.5 py-3 text-[11.5px] leading-relaxed text-dim">
                    Rejection isn't final. Most are down to an unreadable scan or a document
                    that doesn't show your name and institution together.
                </p>
            </x-panel>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
['credential_document', 'registration_documents'].forEach(function (id) {
    var input = document.getElementById(id);
    if (!input) return;

    input.addEventListener('change', function () {
        var label = document.getElementById(id + '-name');
        if (label) {
            label.textContent = this.files[0] ? '✓ ' + this.files[0].name : '';
        }
    });
});
</script>
@endpush
