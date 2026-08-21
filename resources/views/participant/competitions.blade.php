@extends('layouts.app')

@section('title', 'Saved competitions')

@section('content')
{{--
    API-DRIVEN PAGE  (Member 4 — saved competitions)
    ------------------------------------------------
    No Blade data. Everything is fetched by resources/js/competitions.js from:

        GET    /api/v1/participants/me/competitions
        DELETE /api/v1/competitions/{id}/save

    Ordered by soonest deadline first, undated last — the whole point of the
    page is what closes next.

    Closed competitions are NOT hidden here, unlike on the board. Something
    you saved slipping past its deadline is information you want; removing it
    silently would look like data loss.
--}}
<div class="wrap pb-20" id="saved-competitions-page">

    <x-page-header
        eyebrow="Participant · Saved competitions"
        title="What you're keeping an eye on."
        subtitle="Soonest deadline first. Registration happens on the organiser's own site — open the listing to enter." />

    <x-panel label="Saved" class="reveal reveal-d1">
        <p class="mb-3 text-[12.5px] text-dim" data-saved-count>Loading…</p>
        <div class="space-y-3" data-saved-list></div>
    </x-panel>

    <div class="mt-4" data-saved-alert></div>
</div>
@endsection
