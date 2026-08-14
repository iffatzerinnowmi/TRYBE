@extends('layouts.app')

@section('title', 'Karma Credits')

@section('content')
<div class="wrap pb-20" id="karma-page">

    <x-page-header
        eyebrow="Participant · Karma Credits"
        title="Earn credits by being an active member."
        subtitle="Complete studies, show up on time, leave reviews, and refer friends — karma tracks all of it automatically." />

    <div class="grid gap-6 lg:grid-cols-[1fr_1fr]">

        <div class="reveal rounded-panel bg-gradient-to-br from-plum to-ink p-6 text-white shadow-soft sm:p-[26px]">
            <p class="font-mono text-[11.5px] uppercase tracking-[0.2em] text-white/70">Your Karma Balance</p>

            <div class="mt-4 flex items-center gap-4">
                <div class="flex h-[52px] w-[52px] items-center justify-center rounded-full bg-white/15 text-2xl">⭐</div>
                <div>
                    <b class="block font-display text-4xl font-semibold" data-karma-balance>—</b>
                    <span class="text-[13px] text-white/70">Karma Credits</span>
                </div>
            </div>

            <div class="mt-5 border-t border-white/15 pt-5" data-spend-block>
                <p class="text-[12.5px] text-white/70">Loading spend options…</p>
            </div>
            <div data-spend-alert class="mt-3"></div>
        </div>

        <x-panel label="How to earn karma" class="reveal reveal-d1">
            <div class="divide-y divide-line" data-earn-rates>
                <p class="py-2 text-[13px] text-dim">Loading…</p>
            </div>
        </x-panel>
    </div>

    <x-panel label="Recent karma activity" class="reveal reveal-d2 mt-6">
        <div class="mb-4 flex justify-end">
            <a href="#" data-view-all-trigger class="text-[12.5px] font-semibold text-plum hover:underline">View All</a>
        </div>
        <div class="divide-y divide-line" data-karma-transactions>
            <p class="py-2 text-[13px] text-dim">Loading…</p>
        </div>
    </x-panel>
</div>
@endsection