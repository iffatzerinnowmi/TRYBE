@extends('layouts.app')

@section('title', 'Design check')

@section('content')
<div class="wrap pb-20">

    <x-page-header
        eyebrow="Section 2"
        title="Design system check"
        subtitle="If this page looks right, Tailwind, the TRYBE tokens and the seeded database are all working.">
        <x-btn href="/" variant="ghost" size="sm">Back home</x-btn>
    </x-page-header>

    {{-- 1. Real data from the database --------------------------------- --}}
    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        <x-stat-card icon="👥" :value="$stats['users']" label="Users in database" />
        <x-stat-card icon="🙋" :value="$stats['participants']" label="Participants" />
        <x-stat-card icon="🔬" :value="$stats['researchers']" label="Researchers" />
        <x-stat-card icon="📋" :value="$stats['studies']" label="Studies" />
    </div>

    @if ($stats['users'] === 0)
        <x-alert type="error" class="mb-6">
            No users found. Run <b>php artisan migrate:fresh --seed</b> before continuing.
        </x-alert>
    @else
        <x-alert type="success" class="mb-6">
            Database connected — {{ $stats['users'] }} users and {{ $stats['studies'] }} studies loaded from Section 1.
        </x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">

        {{-- 2. Seeded users, rendered through the components ------------ --}}
        <x-panel label="Live rows from the users table" class="reveal">
            <div class="divide-y divide-line">
                @foreach ($sampleUsers as $user)
                    <div class="flex items-center gap-3.5 py-3.5">
                        <x-avatar :name="$user->name" />

                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-ink">{{ $user->name }}</div>
                            <div class="truncate font-mono text-[11px] text-steel">{{ $user->email }}</div>
                        </div>

                        <x-badge tone="plum">{{ $user->role->label() }}</x-badge>

                        @if ($user->participantProfile)
                            <x-badge :tone="$user->participantProfile->credential_level->value">
                                {{ $user->participantProfile->credential_level->label() }}
                            </x-badge>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-panel>

        {{-- 3. Every component, so you can see them all at once ---------- --}}
        <div class="space-y-6">

            <x-panel label="Colour tokens" note="Light to dark maps onto Bronze, Gold, Expert." class="reveal reveal-d1">
                <div class="grid grid-cols-4 gap-2.5">
                    <div class="rounded-xl border border-line bg-mint p-4 text-center font-mono text-[10px] text-ink">mint</div>
                    <div class="rounded-xl bg-steel p-4 text-center font-mono text-[10px] text-white">steel</div>
                    <div class="rounded-xl bg-plum p-4 text-center font-mono text-[10px] text-white">plum</div>
                    <div class="rounded-xl bg-ink p-4 text-center font-mono text-[10px] text-white">ink</div>
                    <div class="rounded-xl bg-ok p-4 text-center font-mono text-[10px] text-white">ok</div>
                    <div class="rounded-xl bg-flame p-4 text-center font-mono text-[10px] text-white">flame</div>
                    <div class="rounded-xl bg-star p-4 text-center font-mono text-[10px] text-white">star</div>
                    <div class="rounded-xl bg-danger p-4 text-center font-mono text-[10px] text-white">danger</div>
                </div>
            </x-panel>

            <x-panel label="Badges" class="reveal reveal-d2">
                <div class="flex flex-wrap gap-2">
                    <x-badge tone="none">Unranked</x-badge>
                    <x-badge tone="bronze">Bronze</x-badge>
                    <x-badge tone="gold">Gold</x-badge>
                    <x-badge tone="expert">Expert</x-badge>
                    <x-badge tone="ok">Completed</x-badge>
                    <x-badge tone="flame">3 week streak</x-badge>
                    <x-badge tone="danger">Rejected</x-badge>
                </div>
            </x-panel>

            <x-panel label="Buttons" class="reveal reveal-d2">
                <div class="flex flex-wrap items-center gap-2.5">
                    <x-btn>Solid</x-btn>
                    <x-btn variant="ghost">Ghost</x-btn>
                    <x-btn variant="soft">Soft</x-btn>
                    <x-btn size="sm">Small</x-btn>
                </div>
            </x-panel>

            <x-panel label="Progress" class="reveal reveal-d3">
                <x-progress :value="$stats['studies']" :max="25" label="Studies towards Expert tier" />
                <div class="mt-4">
                    <x-progress :value="$stats['participants']" :max="$stats['users']" tone="ok" label="Share of users who are participants" />
                </div>
            </x-panel>

            <x-panel label="Typography" class="reveal reveal-d3">
                <p class="font-display text-3xl font-semibold text-ink">Fraunces — headings</p>
                <p class="mt-2 text-sm text-dim">Inter — body copy for everything you read in a sentence.</p>
                <p class="mt-2 font-mono text-[11.5px] uppercase tracking-[0.2em] text-steel">JetBrains Mono — labels</p>
            </x-panel>

        </div>
    </div>
</div>
@endsection
