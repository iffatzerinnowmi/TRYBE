@extends('layouts.app')

@section('title', $study->title)

@section('content')
<div class="wrap pb-20">

    @php
        $user = auth()->user();
        $isOwner = $study->isOwnedBy($user);
    @endphp

    <x-page-header eyebrow="{{ $study->category ?? 'Study' }}" title="{{ $study->title }}">
        @if ($isOwner)
            <x-btn href="{{ route('studies.edit', $study) }}" variant="ghost">Edit listing</x-btn>
        @else
            <x-btn href="{{ route('studies.index') }}" variant="ghost">← Back to studies</x-btn>
        @endif
    </x-page-header>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->has('apply'))
        <x-alert type="error" class="mb-6">{{ $errors->first('apply') }}</x-alert>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[1.6fr_1fr]">

        {{-- ================= LEFT ================= --}}
        <div class="space-y-6">
            <x-panel label="About this study" class="reveal">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge tone="{{ $study->incentive_type->requiresEscrow() ? 'ok' : 'neutral' }}">
                        {{ $study->incentive_type->label() }}
                        @if ($study->incentive_type->requiresEscrow() && (float) $study->compensation_amount > 0)
                            · ৳{{ number_format((float) $study->compensation_amount, 0) }}
                        @endif
                    </x-badge>
                    <x-badge tone="neutral">{{ ucfirst(str_replace('_', ' ', $study->method)) }}</x-badge>
                    @if ($study->duration_minutes)
                        <x-badge tone="neutral">{{ $study->duration_minutes }} min</x-badge>
                    @endif
                    <x-badge tone="{{ $study->status->value === 'open' ? 'ok' : 'neutral' }}">
                        {{ strtoupper($study->status->label()) }}
                    </x-badge>
                </div>

                @if ($study->irb_flagged)
                    <p class="mt-4 flex items-center gap-2 rounded-lg bg-flame/10 px-3 py-2 text-[12px] text-flame">
                        ⚠ No IRB / ethics document attached to this study yet.
                    </p>
                @endif

                <p class="mt-4 whitespace-pre-line text-[14.5px] leading-relaxed text-ink">
                    {{ $study->description }}
                </p>
            </x-panel>

            @if ($study->eligibility_criteria)
                <x-panel label="Who this is for" class="reveal reveal-d1">
                    <p class="whitespace-pre-line text-[13.5px] leading-relaxed text-dim">
                        {{ $study->eligibility_criteria }}
                    </p>
                </x-panel>
            @endif
        </div>

        {{-- ================= RIGHT ================= --}}
        <div class="space-y-6">
            <x-panel label="Details" class="reveal reveal-d1">
                <dl class="divide-y divide-line text-[13px]">
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-dim">Researcher</dt>
                        <dd class="text-right font-semibold text-ink">{{ $study->researcher->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-dim">Spots left</dt>
                        <dd class="text-right font-mono text-ink">{{ $study->spotsRemaining() }} of {{ $study->slots }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-dim">Applicants so far</dt>
                        <dd class="text-right font-mono text-ink">{{ $study->participations_count }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-dim">Deadline</dt>
                        <dd class="text-right text-ink">{{ $study->deadline?->format('M j, Y') ?? 'Rolling' }}</dd>
                    </div>
                </dl>

                @if ($user->role->value === 'participant')
                    <div class="mt-5">
                        @if ($myApplication)
                            <div class="rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-center text-[13px] font-semibold text-ok">
                                ✓ Applied — currently {{ strtolower($myApplication->stage->label()) }}
                            </div>
                        @elseif ($canApply)
                            <form method="POST" action="{{ route('studies.apply', $study) }}">
                                @csrf
                                <x-btn type="submit" class="w-full">Apply to this study</x-btn>
                            </form>
                        @else
                            <div class="rounded-xl border border-line bg-surface-soft px-4 py-3 text-[12.5px] text-dim">
                                {{ $blockReason }}
                            </div>
                        @endif
                    </div>
                @endif
            </x-panel>

            @if ($isOwner)
                <x-panel label="Manage" class="reveal reveal-d2">
                    <div class="flex flex-col gap-2.5">
                        <x-btn href="{{ route('studies.edit', $study) }}" variant="soft" size="sm">Edit listing</x-btn>
                        <form method="POST" action="{{ route('studies.destroy', $study) }}"
                              onsubmit="return confirm('Delete this listing? This can\'t be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="w-full rounded-xl border border-line-hi px-4 py-3 text-[13px] font-semibold
                                           text-danger transition hover:border-danger hover:bg-danger/10">
                                Delete listing
                            </button>
                        </form>
                    </div>
                </x-panel>
            @endif
        </div>
    </div>
</div>
@endsection
