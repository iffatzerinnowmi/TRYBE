@extends('layouts.app')

@section('title', $study->title)

@section('content')
<div class="wrap pb-20">
    <x-page-header eyebrow="Study details" title="{{ $study->title }}">
        <x-btn href="{{ route('dashboard') }}" variant="ghost">Back to dashboard</x-btn>
    </x-page-header>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <x-panel label="Overview" class="reveal">
            <div class="space-y-5">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge tone="{{ $study->status->value === 'open' ? 'ok' : 'neutral' }}">
                            {{ $study->status->label() }}
                        </x-badge>
                        <x-badge tone="{{ $study->incentive_type->requiresEscrow() ? 'ok' : 'neutral' }}">
                            {{ $study->incentive_type->label() }}
                        </x-badge>
                        @if ($study->irb_flagged)
                            <x-badge tone="flame">IRB pending</x-badge>
                        @endif
                    </div>

                    <p class="mt-3 text-[13.5px] leading-relaxed text-dim">{{ $study->description ?? 'No description provided.' }}</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Researcher</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">{{ $study->researcher->name }}</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Method</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">{{ ucfirst(str_replace('_', ' ', $study->method)) }}</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Duration</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">{{ $study->duration_minutes }} minutes</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Slots</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">{{ $study->slots }}</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Compensation</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">৳{{ number_format($study->compensation_amount, 0) }}</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Deadline</div>
                        <div class="mt-1 text-[13.5px] font-semibold text-ink">{{ $study->deadline?->format('M d, Y') ?? 'No deadline' }}</div>
                    </div>
                </div>

                <div class="rounded-xl border border-line bg-surface-soft p-4">
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Matching criteria</div>
                    <p class="mt-2 text-[13.5px] leading-relaxed text-ink">{{ $criteriaSummary }}</p>
                </div>
            </div>
        </x-panel>

        <div class="space-y-6">
            @if ($user->role?->value === 'participant')
                <x-panel label="Your match" class="reveal reveal-d1">
                    <div class="space-y-3 text-[13.5px] text-dim">
                        <p>Your score for this study is <b class="text-ink">{{ $match['score'] }}/100</b>.</p>

                        <div class="flex flex-wrap gap-2">
                            @forelse ($match['reasons'] as $reason)
                                <x-badge tone="plum">{{ $reason }}</x-badge>
                            @empty
                                <x-badge tone="neutral">No match reasons available</x-badge>
                            @endforelse
                        </div>

                        @if ($invite)
                            <x-alert type="info" class="mt-2">You have been invited to this study.</x-alert>

                            @if ($participation?->stage?->value === 'confirmed')
                                <x-alert type="success" class="mt-2">You already accepted this invite.</x-alert>
                            @elseif ($participation?->stage?->value === 'rejected')
                                <x-alert type="warning" class="mt-2">You previously declined this invite.</x-alert>
                            @else
                                <div class="mt-2 flex flex-wrap gap-3">
                                    <form method="POST" action="{{ route('participant.studies.invitation.accept', $study) }}">
                                        @csrf
                                        <x-btn type="submit">Accept invite</x-btn>
                                    </form>

                                    <form method="POST" action="{{ route('participant.studies.invitation.decline', $study) }}">
                                        @csrf
                                        <x-btn type="submit" variant="ghost">Decline</x-btn>
                                    </form>
                                </div>
                            @endif
                        @else
                            <p class="mt-2 text-[12.5px] text-dim">This study is visible to you, but you have not been invited yet.</p>
                        @endif

                        {{-- FEATURE — Participant Free-to-Paid Unlock Rule (Member 3) --}}
                        @if ($participation?->stage?->value === 'confirmed')
                            @if ($participation->completion_requested_at)
                                <x-alert type="info" class="mt-2">
                                    Marked as complete — waiting for the researcher to confirm.
                                </x-alert>
                            @else
                                <form method="POST" action="{{ route('participant.studies.complete.request', $study) }}" class="mt-2">
                                    @csrf
                                    <x-btn type="submit">Mark as complete</x-btn>
                                </form>
                            @endif
                        @elseif ($participation?->stage?->value === 'completed')
                            <x-alert type="success" class="mt-2">
                                You completed this study
                                @if ($study->incentive_type->value === 'volunteer')
                                    — it counts toward your paid-study unlock.
                                @endif
                            </x-alert>
                        @endif
                    </div>
                </x-panel>
            @else
                <x-panel label="Current participants" class="reveal reveal-d1">
                    <div class="space-y-3">
                        @forelse ($currentParticipants as $participation)
                            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface px-3.5 py-3">
                                <x-avatar :name="$participation->participant->name" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[13.5px] font-semibold text-ink">{{ $participation->participant->name }}</div>
                                    <div class="font-mono text-[11px] text-steel">
                                        {{ $participation->participant->location ?? 'Location not set' }}
                                        @if ($participation->participant->participantProfile)
                                            · {{ $participation->participant->participantProfile->credential_level instanceof \App\Enums\CredentialLevel ? $participation->participant->participantProfile->credential_level->label() : ucfirst((string) $participation->participant->participantProfile->credential_level) }}
                                        @endif
                                    </div>
                                </div>

                                <x-badge tone="expert">{{ $participation->stage->label() }}</x-badge>

                                {{-- FEATURE — Participant Free-to-Paid Unlock Rule (Member 3) --}}
                                @if ($participation->completion_requested_at)
                                    <div class="flex gap-2">
                                        <form method="POST" action="{{ route('researcher.studies.participants.approve', [$study, $participation->participant]) }}">
                                            @csrf
                                            <x-btn type="submit" class="!px-3 !py-1.5 !text-[12px]">Approve</x-btn>
                                        </form>
                                        <form method="POST" action="{{ route('researcher.studies.participants.decline', [$study, $participation->participant]) }}">
                                            @csrf
                                            <x-btn type="submit" variant="ghost" class="!px-3 !py-1.5 !text-[12px]">Decline</x-btn>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-[13.5px] text-dim">No current participants yet.</p>
                        @endforelse
                    </div>
                </x-panel>

                <x-panel label="Suggested participants" class="reveal reveal-d1">
                    <div class="space-y-3">
                        @forelse ($matchedParticipants as $match)
                            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface px-3.5 py-3">
                                <x-avatar :name="$match->user->name" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[13.5px] font-semibold text-ink">{{ $match->user->name }}</div>
                                    <div class="font-mono text-[11px] text-steel">
                                        {{ $match->age }} years · {{ $match->user->location ?? 'Location not set' }} ·
                                        {{ $match->credential_level instanceof \App\Enums\CredentialLevel ? $match->credential_level->label() : ucfirst((string) $match->credential_level) }}
                                    </div>
                                    <div class="mt-1 text-[11.5px] text-dim">{{ implode(' · ', $match->match_reasons ?? []) }}</div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-badge tone="{{ $match->strong_match ? 'expert' : 'gold' }}">{{ $match->match_score }}%</x-badge>
                                    <x-btn href="{{ route('researcher.studies.participants.show', [$study, $match->user]) }}" variant="ghost" size="sm">View profile</x-btn>
                                    @if ($match->invited)
                                        <x-badge tone="neutral">Invited</x-badge>
                                    @else
                                        <form method="POST" action="{{ route('researcher.studies.invite', [$study, $match->user]) }}">
                                            @csrf
                                            <x-btn type="submit" variant="soft" size="sm">Invite</x-btn>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-[13.5px] text-dim">No matched participants found yet.</p>
                        @endforelse
                    </div>
                </x-panel>
            @endif
        </div>
    </div>
</div>
@endsection