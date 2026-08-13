@extends('layouts.app')

@section('title', 'Refer a Friend')

@section('content')
{{--
    API-DRIVEN PAGE  (Member 4 — Referral system)
    ---------------------------------------------
    There is no Blade data in this file. The code, link, progress, referred
    users and rewards are all fetched by resources/js/referrals.js from:

        GET  /api/v1/referrals/me
        POST /api/v1/referrals/me/code
        POST /api/v1/referrals/me/sync

    The controller behind this page passes nothing to the view — its only
    job is deciding whether the page exists at all.

    There is no web POST route anywhere in this feature. Generating a code
    is an API call.
--}}
<div class="wrap pb-20" id="referrals-page">

    <x-page-header
        eyebrow="Participant · Refer a Friend"
        title="Bring people in, move up a tier."
        subtitle="Share your link. When your referred friends actually complete a study, you skip straight to the next credential tier — no waiting for the completion count." />

    <div class="grid gap-6 lg:grid-cols-[1.3fr_1fr]">

        {{-- ---------------------------------------------------------------
             Your link
             --------------------------------------------------------------- --}}
        <x-panel label="Your referral link" class="reveal">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <code class="flex-1 select-all truncate rounded-xl border border-line bg-surface-soft
                                 px-3.5 py-3 font-mono text-[13px] text-ink"
                          data-share-url>Loading…</code>

                    <button type="button"
                            class="rounded-lg bg-plum/12 px-3 py-2 font-mono text-[11px] text-plum transition
                                   hover:bg-plum/20 disabled:cursor-not-allowed disabled:opacity-50"
                            data-copy-link>Copy</button>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <span class="font-mono text-[11px] text-steel">
                        Code <b class="text-ink" data-code>—</b>
                    </span>
                    <span class="font-mono text-[11px] text-steel">
                        <b class="text-ink" data-visits>0</b> link opens
                    </span>
                    <button type="button"
                            class="rounded-lg border border-line px-2.5 py-1 font-mono text-[10.5px] text-steel
                                   transition hover:border-plum hover:text-plum"
                            data-rotate-code>Regenerate</button>
                </div>

                <div data-referral-alert></div>

                {{-- Pre-written messages, built server-side so the threshold in
                     the copy comes from config rather than a string in JS. --}}
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Ready to send</div>
                    <div class="mt-2 space-y-2" data-share-messages></div>
                </div>
            </div>
        </x-panel>

        {{-- ---------------------------------------------------------------
             Progress
             --------------------------------------------------------------- --}}
        <x-panel label="Progress" class="reveal reveal-d1">
            <div class="space-y-4">
                <p class="text-[14px] text-ink" data-progress-label>Loading…</p>

                <div class="w-full">
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-steel/25"
                         role="progressbar" aria-valuemin="0" aria-valuemax="100"
                         data-progress-bar-outer>
                        <div class="h-full rounded-full bg-plum transition-[width] duration-700 ease-out"
                             style="width: 0%" data-progress-bar></div>
                    </div>
                </div>

                <p class="text-[12.5px] text-dim" data-progress-detail></p>

                <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Your reward</div>
                    <p class="mt-1.5 text-[13px] text-ink" data-reward-preview>—</p>
                </div>

                <div data-rewards-list></div>
            </div>
        </x-panel>
    </div>

    {{-- -------------------------------------------------------------------
         Who you referred
         ------------------------------------------------------------------- --}}
    <x-panel label="People you referred" class="reveal reveal-d2 mt-6">
        <p class="mb-3 text-[12px] text-dim">
            Names are shortened — these are people who did not ask to be on a list.
        </p>
        <div class="space-y-3" data-referred-list>
            <p class="text-[12.5px] text-dim">Loading…</p>
        </div>
    </x-panel>

</div>
@endsection
