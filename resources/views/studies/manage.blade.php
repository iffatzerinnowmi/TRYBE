@extends('layouts.app')

@section('title', 'My studies')

@section('content')
<div class="wrap pb-20">

    <x-page-header eyebrow="Study listing board" title="Your study listings.">
        <x-btn href="{{ route('studies.create') }}">+ Post a study</x-btn>
    </x-page-header>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    {{-- ================= STATUS FILTER ================= --}}
    <div class="reveal flex flex-wrap gap-2">
        <a href="{{ route('studies.index') }}"
           class="rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold transition
                  {{ $status === '' ? 'bg-plum text-white' : 'border border-line-hi text-dim hover:bg-steel/15' }}">
            All
        </a>
        @foreach ($statuses as $value => $label)
            <a href="{{ route('studies.index', ['status' => $value]) }}"
               class="rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold transition
                      {{ $status === $value ? 'bg-plum text-white' : 'border border-line-hi text-dim hover:bg-steel/15' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- ================= LISTINGS ================= --}}
    <x-panel class="mt-5 reveal reveal-d1">
        @forelse ($studies as $study)
            <div class="flex flex-wrap items-center gap-3.5 border-b border-line py-4 last:border-none last:pb-1">

                <div class="min-w-0 flex-1">
                    <a href="{{ route('studies.show', $study) }}"
                       class="truncate text-[14.5px] font-semibold text-ink hover:text-plum">
                        {{ $study->title }}
                    </a>
                    <div class="mt-1 font-mono text-[11.5px] text-steel">
                        {{ $study->participations_count }} applicants ·
                        {{ $study->spotsRemaining() }} of {{ $study->slots }} spots left ·
                        {{ $study->incentive_type->label() }}
                        @if ($study->deadline)
                            · due {{ $study->deadline->format('M j, Y') }}
                        @endif
                    </div>
                </div>

                <x-badge tone="{{ match ($study->status->value) {
                    'open' => 'ok',
                    'draft' => 'neutral',
                    'pending_reach' => 'gold',
                    'full' => 'expert',
                    'cancelled', 'closed' => 'danger',
                    default => 'neutral',
                } }}">
                    {{ strtoupper($study->status->label()) }}
                </x-badge>

                <div class="flex items-center gap-2">
                    <x-btn href="{{ route('studies.edit', $study) }}" variant="ghost" size="sm">Edit</x-btn>

                    <form method="POST" action="{{ route('studies.destroy', $study) }}"
                          onsubmit="return confirm('Delete this listing? This can\'t be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="rounded-xl border border-line-hi px-4 py-2.5 text-[13px] font-semibold
                                       text-danger transition hover:border-danger hover:bg-danger/10">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="py-3 text-[13.5px] text-dim">
                You haven't posted a study yet.
                <a href="{{ route('studies.create') }}" class="font-semibold text-plum">Post your first one →</a>
            </p>
        @endforelse
    </x-panel>

    @if ($studies->hasPages())
        <div class="mt-8 flex justify-center">
            {{ $studies->onEachSide(1)->links() }}
        </div>
    @endif
</div>
@endsection
