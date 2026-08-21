@extends('layouts.app')

@section('title', 'Competitions')

@section('content')
{{--
    API-DRIVEN PAGE  (Member 4 — Competition & Hackathon Board)
    ----------------------------------------------------------
    No Blade data. Every listing, badge, deadline and button state is fetched
    by resources/js/competitions.js from:

        GET    /api/v1/competitions
        GET    /api/v1/competitions/mine
        POST   /api/v1/competitions
        DELETE /api/v1/competitions/{id}
        POST   /api/v1/competitions/{id}/save
        DELETE /api/v1/competitions/{id}/save

    The "Post a competition" panel is hidden by default and revealed by the
    JavaScript only when the API says can_post is true. The SERVER is what
    actually refuses a post — hiding the form is a courtesy, not a guard.
--}}
<div class="wrap pb-20" id="competitions-page">

    <x-page-header
        eyebrow="Competitions & hackathons"
        title="Things worth entering."
        subtitle="Posted by verified researchers and institutions. Open the listing to enter — registration happens on the organiser's own site. Save the ones you're interested in and keep an eye on the deadline." />

    {{-- Poster panel. Revealed by competitions.js when can_post is true. --}}
    <div class="hidden" data-post-panel>
        <x-panel label="Post a competition" class="reveal reveal-d1">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="comp-name">Name</label>
                    <input id="comp-name" type="text" data-field="name" maxlength="160"
                           class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink"
                           placeholder="BUET CSE Fest Hackathon 2026">
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="comp-url">Link to the competition</label>
                    <input id="comp-url" type="url" data-field="external_url" maxlength="2048"
                           class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink"
                           placeholder="https://devpost.com/...">
                    <p class="mt-1 text-[11.5px] text-steel">Must start with https://</p>
                </div>

                <div>
                    <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="comp-organizer">Organiser</label>
                    <input id="comp-organizer" type="text" data-field="organizer" maxlength="120"
                           class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink"
                           placeholder="Devpost · MLH · BRAC University">
                </div>

                <div>
                    <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="comp-deadline">Submission deadline</label>
                    <input id="comp-deadline" type="date" data-field="deadline"
                           class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink">
                </div>

                <div>
                    <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="comp-prize">Prize</label>
                    <input id="comp-prize" type="text" data-field="prize" maxlength="160"
                           class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink"
                           placeholder="৳50,000 + internship">
                </div>

                <div>
                    <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="comp-location">Location</label>
                    <input id="comp-location" type="text" data-field="location" maxlength="120"
                           class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink"
                           placeholder="Online · Dhaka">
                </div>

                <div>
                    <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="comp-team-min">Team size (min)</label>
                    <input id="comp-team-min" type="number" min="1" max="50" data-field="team_min"
                           class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink"
                           placeholder="Any">
                </div>

                <div>
                    <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="comp-team-max">Team size (max)</label>
                    <input id="comp-team-max" type="number" min="1" max="50" data-field="team_max"
                           class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink"
                           placeholder="Any">
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block font-mono text-[10px] uppercase tracking-[0.14em] text-steel" for="comp-description">Description</label>
                    <textarea id="comp-description" rows="3" data-field="description" maxlength="2000"
                              class="w-full rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink"
                              placeholder="What it is, who it's for, anything worth knowing before clicking through."></textarea>
                </div>
            </div>

            <div data-post-alert></div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="button" data-post-submit
                        class="rounded-lg bg-plum/12 px-3.5 py-2 font-mono text-[11.5px] text-plum transition
                               hover:bg-plum/20 disabled:cursor-not-allowed disabled:opacity-50">
                    Post competition
                </button>
                <span class="text-[12px] text-steel" data-post-status></span>
            </div>
        </x-panel>

        {{-- The poster's own listings, including closed ones. --}}
        <x-panel label="Your listings" class="reveal reveal-d2 mt-6">
            <div class="space-y-3" data-mine-list>
                <p class="text-[12.5px] text-dim">Loading…</p>
            </div>
        </x-panel>
    </div>

    <x-panel label="Open competitions" class="reveal reveal-d3 mt-6">
        <p class="mb-3 text-[12.5px] text-dim" data-board-count>Loading…</p>
        <div class="grid gap-3 md:grid-cols-2" data-board-list></div>
    </x-panel>

</div>
@endsection
