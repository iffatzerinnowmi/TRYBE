@extends('layouts.app')

@section('title', 'Post a study')

@section('content')
<div class="wrap pb-20">

    <x-page-header eyebrow="Study listing board" title="Post a new study."
        subtitle="Fill in the details below — it goes live on the board as soon as you publish." />

    <div class="max-w-3xl">
        <form method="POST" action="{{ route('studies.store') }}"
              class="reveal space-y-5 rounded-panel border border-line bg-surface p-7 shadow-soft sm:p-9">
            @csrf

            @include('studies._form', ['methods' => $methods, 'incentiveTypes' => $incentiveTypes])

            <div class="flex items-center gap-3 pt-2">
                <x-btn type="submit">Publish listing</x-btn>
                <x-btn href="{{ route('studies.index') }}" variant="ghost">Cancel</x-btn>
            </div>
        </form>
    </div>
</div>
@endsection
