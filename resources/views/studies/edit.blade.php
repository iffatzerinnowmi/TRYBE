@extends('layouts.app')

@section('title', 'Edit study')

@section('content')
<div class="wrap pb-20">

    <x-page-header eyebrow="Study listing board" title="Edit listing."
        subtitle="Changes apply immediately — participants see the updated details next time they load the page." />

    <div class="max-w-3xl">
        <form method="POST" action="{{ route('studies.update', $study) }}"
              class="reveal space-y-5 rounded-panel border border-line bg-surface p-7 shadow-soft sm:p-9">
            @csrf
            @method('PUT')

            @php
                $statuses = collect(\App\Enums\StudyStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]);
            @endphp

            @include('studies._form', [
                'study' => $study,
                'methods' => $methods,
                'incentiveTypes' => $incentiveTypes,
                'statuses' => $statuses,
            ])

            <div class="flex items-center gap-3 pt-2">
                <x-btn type="submit">Save changes</x-btn>
                <x-btn href="{{ route('studies.show', $study) }}" variant="ghost">Cancel</x-btn>
            </div>
        </form>
    </div>
</div>
@endsection
