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
                                {{-- FEATURE — Verified Payment Escrow (Member 3) --}}
                @include('studies.partials.escrow-summary', ['study' => $study])

                {{-- Matching criteria (Member 4)... --}}

                {{-- Matching criteria (Member 4). API-driven: filled by
                     resources/js/study-match.js from the matching endpoints. --}}
                <div class="rounded-xl border border-line bg-surface-soft p-4"
                     data-study-criteria data-study-id="{{ $study->id }}">
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Matching criteria</div>
                    <p class="mt-2 text-[13.5px] leading-relaxed text-ink" data-criteria-summary>Loading…</p>
                </div>
            </div>
        </x-panel>

        <div class="space-y-6">
            {{-- FEATURE — Limited Seat Auctions (Member 3) --}}
            @include('studies.partials.auction-panel', ['study' => $study, 'user' => $user])
            @include('studies.partials.paid-apply-panel', ['study' => $study, 'user' => $user])

            @if ($user->role?->value === 'participant')
                <x-panel label="Available sessions" class="reveal reveal-d1" data-slot-panel data-slot-type="participant" data-study-id="{{ $study->id }}">
                    <div class="space-y-3" data-slot-list>
                        <p class="text-[12.5px] text-dim">Loading available sessions…</p>
                    </div>
                    <div class="mt-3 text-[12px] text-steel" data-slot-status></div>
                </x-panel>

                <x-panel label="Your match" class="reveal reveal-d2">
                    <div class="space-y-3 text-[13.5px] text-dim"
                         data-study-match data-study-id="{{ $study->id }}">
                        <p data-match-summary>Loading your match…</p>
                        <div class="flex flex-wrap gap-2" data-match-reasons></div>
                        <div data-invitation-block></div>
                    </div>
                </x-panel>
            @else
                <x-panel label="Study schedule" class="reveal reveal-d1" data-slot-panel data-slot-type="researcher" data-study-id="{{ $study->id }}">
                    <form class="space-y-3" data-slot-form>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="slot-start-{{ $study->id }}">Start</label>
                                <input id="slot-start-{{ $study->id }}" type="datetime-local" name="starts_at" class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink">
                            </div>
                            <div>
                                <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="slot-end-{{ $study->id }}">End</label>
                                <input id="slot-end-{{ $study->id }}" type="datetime-local" name="ends_at" class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink">
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="slot-capacity-{{ $study->id }}">Capacity</label>
                            <input id="slot-capacity-{{ $study->id }}" type="number" name="capacity" min="1" max="50" value="1" class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink">
                        </div>
                        <button type="button" data-create-slot class="rounded-lg bg-plum/12 px-3 py-1.5 font-mono text-[11px] text-plum">Add slot</button>
                        <div class="text-[12px] text-steel" data-slot-status></div>
                    </form>

                    <div class="mt-4 space-y-3" data-slot-list>
                        <p class="text-[12.5px] text-dim">Loading slots…</p>
                    </div>
                </x-panel>

                <x-panel label="Current participants" class="reveal reveal-d2">
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
                            </div>
                        @empty
                            <p class="text-[13.5px] text-dim">No current participants yet.</p>
                        @endforelse
                    </div>
                </x-panel>

                <x-panel label="Suggested participants" class="reveal reveal-d3">
                    @include('researcher.partials.candidates-panel', ['study' => $study])
                </x-panel>
            @endif
        </div>
    </div>
</div>
@endsection