@extends('layouts.app')

@section('title', 'My studies')

@section('content')
{{--
    API-DRIVEN PAGE  (Member 4 — completing a study)
    ------------------------------------------------
    There is no Blade data in this file. Every study, stage, date and button
    state is fetched by resources/js/completion.js from:

        GET   /api/v1/participants/me/applications
        GET   /api/v1/studies/{id}/completion
        POST  /api/v1/studies/{id}/completion
        PATCH /api/v1/studies/{id}/completion/opened

    The controller behind this page passes nothing to the view — its only job
    is deciding whether the page exists at all.

    WHY THIS PAGE EXISTS: nothing previously showed a participant the studies
    they are actually IN. Invitations had a page; active participation did not.
--}}
<div class="wrap pb-20" id="my-studies-page">

    <x-page-header
        eyebrow="Participant · My studies"
        title="Everything you're part of."
        subtitle="Studies you've applied to, been confirmed for, and finished. When you've filled in a study's form, tell us here and the researcher will confirm it." />

    {{-- ACTIVE — the only group with an action on it. --}}
    <x-panel label="Active" class="reveal reveal-d1">
        <p class="mb-3 text-[12.5px] text-dim" data-active-caption>Loading…</p>
        <div class="space-y-3" data-active-list></div>
    </x-panel>

    {{-- WAITING — applied or screened; the researcher has not confirmed yet. --}}
    <x-panel label="Waiting on the researcher" class="reveal reveal-d2 mt-6">
        <div class="space-y-3" data-waiting-list>
            <p class="text-[12.5px] text-dim">Loading…</p>
        </div>
    </x-panel>

    {{-- DONE --}}
    <x-panel label="Completed" class="reveal reveal-d3 mt-6">
        <div class="space-y-3" data-done-list>
            <p class="text-[12.5px] text-dim">Loading…</p>
        </div>
    </x-panel>

    {{-- CLOSED — rejected or no-show. Shown, not hidden: a participant should
         be able to see why something disappeared. --}}
    <x-panel label="Closed" class="reveal reveal-d4 mt-6">
        <div class="space-y-3" data-closed-list>
            <p class="text-[12.5px] text-dim">Loading…</p>
        </div>
    </x-panel>

    <div class="mt-4" data-studies-alert></div>
</div>
@endsection
