@extends('layouts.app')

@section('title', 'Invitations')

@section('content')
{{--
    API-DRIVEN PAGE  (Member 4 — Smart Participant Matching)
    --------------------------------------------------------
    There is no Blade data in this file. Every invitation, study title and
    button state is fetched by the script at the bottom from:

        GET   /api/v1/invitations
        PATCH /api/v1/invitations/{invitation}

    The controller behind this page passes nothing to the view — its only job
    is deciding whether the page exists at all.

    The old flow had two web POST routes for accept and decline. Both are
    gone; there is one PATCH endpoint and one state machine behind it.
--}}
<div class="wrap pb-20" id="invitations-page">

    <x-page-header
        eyebrow="Participant · Invitations"
        title="Researchers who want you specifically."
        subtitle="These are direct invitations, not open listings. A researcher looked at how your profile matched their study and asked for you by name." />

    <x-panel label="Pending invitations" class="reveal reveal-d1">
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="text-[12.5px] text-dim" data-invitations-count>Loading…</p>
            <button type="button"
                    class="rounded-lg border border-line px-2.5 py-1 font-mono text-[10.5px] text-steel
                           transition hover:border-plum hover:text-plum"
                    data-refresh-invitations>Refresh</button>
        </div>

        <div data-invitation-alert></div>

        <div class="space-y-3" data-pending-list>
            <p class="text-[12.5px] text-dim">Loading…</p>
        </div>
    </x-panel>

    <x-panel label="Answered" class="reveal reveal-d2 mt-6">
        <div class="space-y-3" data-answered-list></div>
    </x-panel>

</div>
@endsection

{{-- The rendering lives in resources/js/invitations.js, imported by app.js,
     so it is bundled and minified rather than inlined in the page. --}}
