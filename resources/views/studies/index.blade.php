@extends('layouts.app')

@section('title', 'Studies')

@section('content')
<div class="wrap pb-20">
    <x-page-header eyebrow="Studies" title="Open studies looking for participants.">
        <x-btn href="{{ route('dashboard') }}" variant="ghost">Back to dashboard</x-btn>
    </x-page-header>

    <x-panel class="mb-6">
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Filter studies</div>
                    <p class="mt-1 text-[12px] text-dim">Search by topic, format, or reward type.</p>
                </div>
                @if (request()->hasAny(['q', 'category', 'method', 'incentive_type']))
                    <a href="{{ route('studies.index') }}" class="font-mono text-[11px] text-steel underline-offset-2 hover:underline">Clear filters</a>
                @endif
            </div>

            <form method="GET" action="{{ route('studies.index') }}" class="grid gap-3 md:grid-cols-4">
                <div>
                    <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="filter-q">Search</label>
                    <input id="filter-q" type="text" name="q" value="{{ old('q', $filters['q']) }}" placeholder="Topic, title, keyword" class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink">
                </div>

                <div>
                    <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="filter-category">Category</label>
                    <select id="filter-category" name="category" class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" {{ old('category', $filters['category']) === $category ? 'selected' : '' }}>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="filter-method">Method</label>
                    <select id="filter-method" name="method" class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink">
                        <option value="">All methods</option>
                        @foreach ($methods as $method)
                            <option value="{{ $method }}" {{ old('method', $filters['method']) === $method ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $method)) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="filter-incentive">Reward</label>
                    <select id="filter-incentive" name="incentive_type" class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink">
                        <option value="">Any reward</option>
                        @foreach ($incentiveTypes as $type)
                            <option value="{{ $type }}" {{ old('incentive_type', $filters['incentive_type']) === $type ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-4 flex justify-end">
                    <button type="submit" class="rounded-lg bg-plum/12 px-4 py-2 font-mono text-[11px] text-plum transition hover:bg-plum/20">Apply filters</button>
                </div>
            </form>
        </div>
    </x-panel>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($studies as $study)
            <x-panel class="reveal">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge tone="{{ $study->incentive_type->requiresEscrow() ? 'ok' : 'neutral' }}">
                        {{ $study->incentive_type->label() }}
                    </x-badge>
                    @if ($study->irb_flagged)
                        <x-badge tone="flame">IRB pending</x-badge>
                    @endif
                     @if ($study->auction_mode)
                        <x-badge tone="plum">🎯 Seat auction</x-badge>
                    @endif
                    @if (! is_null($study->match_score ?? null))
                        <x-badge tone="{{ $study->strong_match ? 'expert' : 'gold' }}">Match {{ $study->match_score }}%</x-badge>
                    @endif
                </div>

                <h2 class="mt-3 font-display text-[20px] font-semibold leading-snug text-ink">{{ $study->title }}</h2>
                <p class="mt-2 font-mono text-[11.5px] text-steel">{{ $study->researcher->name }} · {{ $study->duration_minutes }} min · {{ ucfirst(str_replace('_', ' ', $study->method)) }}</p>
                <p class="mt-3 line-clamp-3 text-[13.5px] leading-relaxed text-dim">{{ $study->description }}</p>

                @if (! empty($study->match_reasons ?? []))
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($study->match_reasons as $reason)
                            <x-badge tone="plum">{{ $reason }}</x-badge>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4 flex items-center justify-between gap-3">
                    <div class="font-mono text-[11px] text-steel">৳{{ number_format($study->compensation_amount, 0) }} · {{ $study->slots }} slots</div>
                    <x-btn href="{{ route('studies.show', $study) }}" variant="soft" size="sm">View details</x-btn>
                </div>
            </x-panel>
        @empty
            <p class="text-[13.5px] text-dim">No open studies match the selected filters.</p>
        @endforelse
    </div>
</div>
@endsection