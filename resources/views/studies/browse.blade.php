@extends('layouts.app')

@section('title', 'Studies')

@section('content')
<div class="wrap pb-20">

    <x-page-header eyebrow="Study listing board" title="Studies looking for people."
        subtitle="Browse open studies, filter by what fits you, and apply straight from here.">
    </x-page-header>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->has('apply'))
        <x-alert type="error" class="mb-6">{{ $errors->first('apply') }}</x-alert>
    @endif

    @if ($profile && ! $paidUnlocked)
        <x-panel class="mb-6 reveal">
            <x-progress :value="$completedCount" :max="$unlockTarget"
                label="Volunteer studies completed — {{ $unlockTarget - $completedCount }} more unlocks paid studies" />
        </x-panel>
    @endif

    {{-- ================= FILTERS ================= --}}
    <form method="GET" action="{{ route('studies.index') }}"
          class="reveal grid gap-3.5 rounded-panel border border-line bg-surface p-5 shadow-soft sm:grid-cols-2 lg:grid-cols-5">

        <div class="lg:col-span-2">
            <label class="mb-1.5 block text-[12px] font-semibold text-ink">Search</label>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Title, topic, keyword…"
                   class="w-full rounded-xl border border-line-hi bg-surface-soft px-3.5 py-2.5 text-sm text-ink
                          outline-none transition placeholder:text-steel focus:border-plum focus:bg-surface">
        </div>

        <div>
            <label class="mb-1.5 block text-[12px] font-semibold text-ink">Category</label>
            <select name="category" class="w-full rounded-xl border border-line-hi bg-surface-soft px-3.5 py-2.5
                                            text-sm text-ink outline-none transition focus:border-plum focus:bg-surface">
                <option value="">Any</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-[12px] font-semibold text-ink">Method</label>
            <select name="method" class="w-full rounded-xl border border-line-hi bg-surface-soft px-3.5 py-2.5
                                          text-sm text-ink outline-none transition focus:border-plum focus:bg-surface">
                <option value="">Any</option>
                <option value="online" @selected($filters['method'] === 'online')>Online</option>
                <option value="in_person" @selected($filters['method'] === 'in_person')>In person</option>
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-[12px] font-semibold text-ink">Compensation</label>
            <select name="incentive_type" class="w-full rounded-xl border border-line-hi bg-surface-soft px-3.5 py-2.5
                                                   text-sm text-ink outline-none transition focus:border-plum focus:bg-surface">
                <option value="">Any</option>
                <option value="cash" @selected($filters['incentive_type'] === 'cash')>Cash</option>
                <option value="voucher" @selected($filters['incentive_type'] === 'voucher')>Voucher</option>
                <option value="course_credit" @selected($filters['incentive_type'] === 'course_credit')>Course credit</option>
                <option value="volunteer" @selected($filters['incentive_type'] === 'volunteer')>Volunteer / unpaid</option>
            </select>
        </div>

        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
            <div class="flex-1">
                <label class="mb-1.5 block text-[12px] font-semibold text-ink">Sort by</label>
                <select name="sort" class="w-full rounded-xl border border-line-hi bg-surface-soft px-3.5 py-2.5
                                            text-sm text-ink outline-none transition focus:border-plum focus:bg-surface">
                    <option value="newest" @selected($filters['sort'] === 'newest')>Newest</option>
                    <option value="deadline" @selected($filters['sort'] === 'deadline')>Deadline soonest</option>
                    <option value="compensation" @selected($filters['sort'] === 'compensation')>Highest compensation</option>
                </select>
            </div>
            <x-btn type="submit" size="sm">Filter</x-btn>
            <x-btn href="{{ route('studies.index') }}" variant="ghost" size="sm">Reset</x-btn>
        </div>
    </form>

    {{-- ================= RESULTS ================= --}}
    <div class="mt-6 grid gap-5 md:grid-cols-3">
        @forelse ($studies as $study)
            @php $applied = $appliedStudyIds->contains($study->id); @endphp

            <x-panel class="reveal flex flex-col">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge tone="{{ $study->incentive_type->requiresEscrow() ? 'ok' : 'neutral' }}">
                        {{ $study->incentive_type->label() }}
                        @if ($study->incentive_type->requiresEscrow() && (float) $study->compensation_amount > 0)
                            · ৳{{ number_format((float) $study->compensation_amount, 0) }}
                        @endif
                    </x-badge>

                    @if ($study->category)
                        <x-badge tone="neutral">{{ $study->category }}</x-badge>
                    @endif

                    @if ($applied)
                        <x-badge tone="plum">Applied</x-badge>
                    @endif
                </div>

                <a href="{{ route('studies.show', $study) }}" class="mt-3 block">
                    <h3 class="font-display text-[18px] font-semibold leading-snug text-ink hover:text-plum">
                        {{ $study->title }}
                    </h3>
                </a>

                <p class="mt-2 font-mono text-[11.5px] text-steel">
                    {{ $study->researcher->name }} ·
                    {{ $study->duration_minutes ? $study->duration_minutes . ' min' : 'Duration TBD' }} ·
                    {{ ucfirst(str_replace('_', ' ', $study->method)) }}
                </p>

                <p class="mt-3 line-clamp-3 flex-1 text-[13.5px] leading-relaxed text-dim">
                    {{ $study->description }}
                </p>

                <div class="mt-4 flex items-center justify-between border-t border-line pt-3.5">
                    <span class="font-mono text-[11px] text-steel">
                        {{ $study->spotsRemaining() }} of {{ $study->slots }} spots left
                        @if ($study->deadline)
                            · due {{ $study->deadline->format('M j') }}
                        @endif
                    </span>
                    <x-btn href="{{ route('studies.show', $study) }}" variant="soft" size="sm">View</x-btn>
                </div>
            </x-panel>
        @empty
            <div class="md:col-span-3">
                <x-panel>
                    <p class="py-3 text-[13.5px] text-dim">
                        No studies match those filters right now — try widening your search.
                    </p>
                </x-panel>
            </div>
        @endforelse
    </div>

    @if ($studies->hasPages())
        <div class="mt-8 flex justify-center">
            {{ $studies->onEachSide(1)->links() }}
        </div>
    @endif
</div>
@endsection
