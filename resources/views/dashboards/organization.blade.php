@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="wrap pb-20">

    <x-page-header eyebrow="Organization · Home" title="{{ $user->organization_name ?? $user->name }}">
        <x-btn href="/studies/create">+ Post a study</x-btn>
    </x-page-header>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="grid grid-cols-3 gap-4 py-2">
        <x-stat-card icon="📋" :value="$stats['total_studies']" label="STUDIES POSTED" class="reveal" />
        <x-stat-card icon="🟢" :value="$stats['open_studies']" label="CURRENTLY OPEN" class="reveal reveal-d1" />
        <x-stat-card icon="🧑‍🤝‍🧑" :value="$stats['applicants']" label="TOTAL APPLICANTS" class="reveal reveal-d2" />
    </div>

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-[1.6fr_1fr]">

        <x-panel label="Your studies" class="reveal reveal-d2">
            @forelse ($studies as $study)
                <div class="flex items-center gap-3.5 border-b border-line py-3.5 last:border-none last:pb-1">
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[14px] font-semibold text-ink">{{ $study->title }}</div>
                        <div class="font-mono text-[11.5px] text-steel">
                            {{ $study->participations_count }} applicants · {{ $study->slots }} slots
                        </div>
                    </div>
                    <x-badge tone="{{ $study->status->value === 'open' ? 'ok' : 'neutral' }}">
                        {{ $study->status->label() }}
                    </x-badge>
                </div>
            @empty
                <p class="py-3 text-[13.5px] text-dim">
                    No studies posted under this organization yet.
                </p>
            @endforelse
        </x-panel>

        <div class="space-y-6">
            <x-panel label="Organization details" class="reveal reveal-d2">
                <dl class="divide-y divide-line text-[13px]">
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-dim">Type</dt>
                        <dd class="text-right text-ink">
                            {{ ucfirst(str_replace('_', ' ', (string) $user->organization_type)) ?: '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-dim">Location</dt>
                        <dd class="text-right text-ink">{{ $user->location ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-dim">Status</dt>
                        <dd>
                            <x-badge tone="{{ match ($user->verification_status->value) {
                                'verified' => 'ok', 'pending' => 'flame',
                                'rejected' => 'danger', default => 'neutral',
                            } }}">
                                {{ $user->verification_status->label() }}
                            </x-badge>
                        </dd>
                    </div>
                </dl>

                @if ($request && $request->rejection_reason)
                    <x-alert type="error" class="mt-4">{{ $request->rejection_reason }}</x-alert>
                @endif
            </x-panel>
        </div>
    </div>
</div>
@endsection
