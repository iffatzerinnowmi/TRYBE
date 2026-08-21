@extends('layouts.app')

@section('title', 'Your feed')

@section('content')
{{--
    API-DRIVEN PAGE  (Member 4 — Study Recommendation Feed)
    -------------------------------------------------------
    There is no Blade data in this file. Every study, score, filter option and
    piece of advice is fetched by resources/js/feed.js from:

        GET  /api/v1/participants/me/feed
        GET  /api/v1/participants/me/feed/filters
        GET  /api/v1/participants/me/skill-gap
        POST /api/v1/participants/me/skill-gap/refresh

    The controller behind this page passes nothing to the view — its only job
    is deciding whether the page exists at all.

    Note the filter controls are a plain <div>, not a <form>: there is no web
    POST route to submit to. Changing a filter refetches the API.
--}}
<div class="wrap pb-20" id="feed-page">

    <x-page-header
        eyebrow="Participant · Your feed"
        title="Studies picked for you."
        subtitle="Ranked by how well you actually fit — skills, credential level, endorsements and the topics you have already worked in. Freshness only breaks ties." />

    <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">

        {{-- ---------------------------------------------------------------
             The feed
             --------------------------------------------------------------- --}}
        <div>
            <x-panel label="Recommended studies" class="reveal">

                {{-- Filters. Options come from the API so adding a topic or an
                     incentive type updates this list with no JS change. --}}
                <div class="mb-4 rounded-xl border border-line bg-surface-soft p-3.5">
                    <div class="flex flex-wrap items-end gap-3" data-filters>
                        <div>
                            <label class="block font-mono text-[10px] uppercase tracking-[0.14em] text-steel mb-1"
                                   for="filter-incentive">Compensation</label>
                            <select id="filter-incentive" data-filter="incentive_type"
                                    class="rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink">
                                <option value="">Any</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-mono text-[10px] uppercase tracking-[0.14em] text-steel mb-1"
                                   for="filter-method">Format</label>
                            <select id="filter-method" data-filter="method"
                                    class="rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink">
                                <option value="">Any</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-mono text-[10px] uppercase tracking-[0.14em] text-steel mb-1"
                                   for="filter-duration">Duration</label>
                            <select id="filter-duration" data-filter="max_duration"
                                    class="rounded-lg border border-line-hi bg-surface px-3 py-2 text-[13px] text-ink">
                                <option value="">Any</option>
                            </select>
                        </div>

                        <label class="flex items-center gap-2 pb-2 text-[12.5px] text-dim">
                            <input type="checkbox" data-filter="strong_only" class="rounded border-line-hi">
                            Strong matches only
                        </label>

                        <button type="button"
                                class="ml-auto rounded-lg border border-line px-2.5 py-1.5 font-mono text-[10.5px] text-steel
                                       transition hover:border-plum hover:text-plum"
                                data-clear-filters>Clear</button>
                    </div>

                    <div class="mt-3">
                        <span class="block font-mono text-[10px] uppercase tracking-[0.14em] text-steel mb-1.5">Topics</span>
                        <div class="flex flex-wrap gap-2" data-topic-filters>
                            <span class="text-[12px] text-dim">Loading…</span>
                        </div>
                    </div>
                </div>

                <p class="mb-3 text-[12.5px] text-dim" data-feed-count>Loading your feed…</p>

                <div class="space-y-3" data-feed-list></div>
            </x-panel>
        </div>

        {{-- ---------------------------------------------------------------
             The skill-gap coach
             --------------------------------------------------------------- --}}
        <div>
            <x-panel label="Close the gap" class="reveal reveal-d1">
                <p class="mb-3 text-[12.5px] text-dim">
                    Studies you <b>narrowly</b> missed, and what is standing in the way.
                </p>

                <div data-gap-analysis>
                    <p class="text-[12.5px] text-dim">Loading…</p>
                </div>

                <div class="mt-4" data-gap-advice></div>

                <div class="mt-4 flex flex-wrap items-center gap-2" data-gap-actions></div>

                <div data-gap-alert></div>
            </x-panel>
        </div>
    </div>
</div>
@endsection
