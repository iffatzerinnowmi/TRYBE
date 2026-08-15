@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="wrap pb-20">

    <x-page-header eyebrow="Researcher · Home" title="Welcome back, {{ $user->name }}.">
        <x-btn href="/studies/create">+ Post a study</x-btn>
    </x-page-header>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="grid grid-cols-2 gap-4 py-2 md:grid-cols-4">
        <x-stat-card icon="📋" :value="$stats['active_listings']" label="ACTIVE LISTINGS" class="reveal" />
        <x-stat-card icon="🧑‍🤝‍🧑" :value="$stats['applicants']" label="APPLICANTS IN PIPELINE" class="reveal reveal-d1" />
        <x-stat-card icon="⭐" :value="number_format((float) $stats['avg_rating'], 1)" label="AVG RATING" class="reveal reveal-d2" />
        <x-stat-card icon="👥" :value="$stats['followers']" label="FOLLOWERS" class="reveal reveal-d3" />
    </div>

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-[1.6fr_1fr]">

        {{-- ================= LEFT ================= --}}
        <div class="space-y-6">

            <x-panel label="Your study listings" class="reveal reveal-d2">
                @forelse ($studies as $study)
                    @php
                        // The counts for this one study, keyed by stage.
                        $rows = $stageCounts->get($study->id, collect());
                        $total = $rows->sum('total');
                    @endphp

                    <div class="mb-4 rounded-xl border border-line p-4 last:mb-0">
                        <div class="flex items-center gap-3.5">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl
                                        border border-line bg-surface-soft text-lg">
                                {{ $study->incentive_type->requiresEscrow() ? '💵' : '🤝' }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[14.5px] font-semibold text-ink">{{ $study->title }}</div>
                                <div class="font-mono text-[11.5px] text-steel">
                                    {{ ucfirst(str_replace('_', ' ', $study->method)) }} ·
                                    {{ $study->slots }} slots ·
                                    {{ $study->incentive_type->label() }}
                                </div>
                            </div>

                            <x-badge tone="{{ match ($study->status->value) {
                                'open' => 'ok',
                                'draft' => 'neutral',
                                'pending_reach' => 'gold',
                                'full' => 'expert',
                                default => 'neutral',
                            } }}">
                                {{ strtoupper($study->status->label()) }}
                            </x-badge>

                            <x-btn href="{{ route('studies.show', $study) }}" variant="ghost" size="sm">View study</x-btn>
                        </div>

                        @if ($total > 0)
                            {{-- One bar, split by how many applicants sit at each stage. --}}
                            <div class="mt-3.5 flex h-2 overflow-hidden rounded-full">
                                @foreach ($rows as $row)
                                    <div class="{{ $stageStyles[$row->stage->value]['class'] ?? 'bg-steel' }}"
                                         style="width: {{ round($row->total / $total * 100, 2) }}%"></div>
                                @endforeach
                            </div>

                            <div class="mt-2.5 flex flex-wrap gap-3">
                                @foreach ($rows as $row)
                                    <span class="flex items-center gap-1.5 font-mono text-[10.5px] text-dim">
                                        <i class="h-2 w-2 rounded-full {{ $stageStyles[$row->stage->value]['class'] ?? 'bg-steel' }}"></i>
                                        {{ $stageStyles[$row->stage->value]['label'] ?? $row->stage->value }} {{ $row->total }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-3 text-[12.5px] text-dim">No applicants yet.</p>
                        @endif

                        @if ($study->irb_flagged)
                            <p class="mt-3 flex items-center gap-2 rounded-lg bg-flame/10 px-3 py-2
                                      text-[12px] text-flame">
                                ⚠ No IRB / ethics document attached — participants see a warning.
                            </p>
                        @endif

                        <div class="mt-4 rounded-xl border border-line bg-surface-soft p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-mono text-[10.5px] uppercase tracking-[0.14em] text-steel">
                                        Suggested participants
                                    </div>
                                    <p class="mt-1 text-[12.5px] text-dim">
                                        {{ $studyCriteria[$study->id] ?? 'Auto-matched from profile data.' }}
                                    </p>
                                </div>
                                <x-badge tone="plum">Top matches</x-badge>
                            </div>

                            <div class="mt-4 space-y-3">
                                @forelse ($studyMatches[$study->id] ?? collect() as $match)
                                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface px-3.5 py-3">
                                        <x-avatar :name="$match->user->name" size="sm" />
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-[13.5px] font-semibold text-ink">
                                                {{ $match->user->name }}
                                            </div>
                                            <div class="font-mono text-[11px] text-steel">
                                                {{ $match->age }} years · {{ $match->user->location ?? 'Location not set' }} ·
                                                {{ $match->credential_level instanceof \App\Enums\CredentialLevel
                                                    ? $match->credential_level->label()
                                                    : ucfirst((string) $match->credential_level) }}
                                            </div>
                                            <div class="mt-1 text-[11.5px] text-dim">
                                                {{ implode(' · ', $match->match_reasons ?? []) }}
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <x-badge tone="{{ $match->strong_match ? 'expert' : 'gold' }}">
                                                {{ $match->match_score }}%
                                            </x-badge>

                                            <x-btn href="{{ route('researcher.studies.participants.show', [$study, $match->user]) }}"
                                                   variant="ghost" size="sm">View profile</x-btn>

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
                                    <p class="text-[12.5px] text-dim">No strong matches yet for this study.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="py-3 text-[13.5px] text-dim">
                        You haven't posted a study yet.
                    </p>
                @endforelse
            </x-panel>

            <x-panel label="Recent applicant activity" class="reveal reveal-d3">
                @forelse ($activity as $item)
                    <div class="flex items-center gap-3.5 border-b border-line py-3.5 last:border-none last:pb-1">
                        <x-avatar :name="$item->participant->name" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] text-ink">
                                <b>{{ $item->participant->name }}</b>
                                is at {{ strtolower($item->stage->label()) }} on
                                {{ $item->study->title }}
                            </p>
                            <p class="font-mono text-[11px] text-steel">
                                {{ $item->updated_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="py-3 text-[13.5px] text-dim">Nothing has moved yet.</p>
                @endforelse
            </x-panel>
        </div>

        {{-- ================= RIGHT ================= --}}
        <div class="space-y-6">

            <x-panel label="Verification status" class="reveal reveal-d2">
                @php
                    $v = $user->verification_status->value;
                    $skin = match ($v) {
                        'verified' => ['border-ok/30 bg-ok/10', '✓', 'text-ok'],
                        'pending'  => ['border-flame/30 bg-flame/10', '⏳', 'text-flame'],
                        'rejected' => ['border-danger/30 bg-danger/10', '✕', 'text-danger'],
                        default    => ['border-line bg-surface-soft', '—', 'text-steel'],
                    };
                @endphp

                <div class="flex items-start gap-3.5 rounded-xl border p-4 {{ $skin[0] }}">
                    <span class="text-xl {{ $skin[2] }}">{{ $skin[1] }}</span>
                    <div>
                        <div class="text-[13.5px] font-semibold text-ink">
                            {{ $user->verification_status->label() }}
                        </div>
                        <p class="mt-1 text-[12.5px] leading-relaxed text-dim">
                            @if ($v === 'verified')
                                Institutional email and credentials confirmed. Full platform trust
                                and search visibility active.
                            @elseif ($v === 'pending')
                                An admin is reviewing your documents. You can post studies now, but
                                with limited reach until you're approved.
                            @elseif ($v === 'rejected')
                                Your last application was rejected. You can submit new documents.
                            @else
                                You haven't applied for verification yet.
                            @endif
                        </p>
                    </div>
                </div>
            </x-panel>

            @if ($profile)
                <x-panel label="Your public profile" class="reveal reveal-d3">
                    <dl class="divide-y divide-line text-[13px]">
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-dim">Institution</dt>
                            <dd class="text-right font-semibold text-ink">{{ $profile->institution ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-dim">Department</dt>
                            <dd class="text-right text-ink">{{ $profile->department ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-dim">Ratings</dt>
                            <dd class="text-right font-mono text-ink">
                                {{ number_format((float) $profile->avg_rating, 1) }} from {{ $profile->ratings_count }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-dim">Followers</dt>
                            <dd class="text-right font-mono text-ink">{{ $stats['followers'] }}</dd>
                        </div>
                    </dl>
                </x-panel>
            @endif
        </div>
    </div>
</div>
@endsection
