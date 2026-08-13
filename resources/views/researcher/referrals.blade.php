@extends('layouts.app')

@section('title', 'Referrals & post credits')

@section('content')
{{--
    API-DRIVEN PAGE  (Member 4 — Referral system, researcher side)
    --------------------------------------------------------------
    No Blade data. Everything comes from:

        GET  /api/v1/referrals/me
        POST /api/v1/referrals/me/code
        GET  /api/v1/researchers/me/post-credits

    Same page shell as the participant version, different reward: a
    researcher who refers another researcher earns a free paid-post credit.
--}}
<div class="wrap pb-20" id="referrals-page" data-researcher>

    <x-page-header
        eyebrow="Researcher · Referrals"
        title="Refer a researcher, earn a free post."
        subtitle="When a researcher you referred posts their first study, you earn a free paid-post credit." />

    <div class="grid gap-6 lg:grid-cols-[1.3fr_1fr]">

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

                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Ready to send</div>
                    <div class="mt-2 space-y-2" data-share-messages></div>
                </div>
            </div>
        </x-panel>

        <x-panel label="Progress" class="reveal reveal-d1">
            <div class="space-y-4">
                <p class="text-[14px] text-ink" data-progress-label>Loading…</p>

                <div class="w-full">
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-steel/25"
                         role="progressbar" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full rounded-full bg-plum transition-[width] duration-700 ease-out"
                             style="width: 0%" data-progress-bar></div>
                    </div>
                </div>

                <p class="text-[12.5px] text-dim" data-progress-detail></p>

                <div class="rounded-xl border border-line bg-surface-soft p-3.5">
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-steel">Post credits</div>
                    <p class="mt-1 font-display text-[26px] font-semibold text-ink" data-credit-balance>0</p>
                    <p class="mt-1 text-[12px] text-dim" data-credit-note></p>
                </div>

                <div data-rewards-list></div>
            </div>
        </x-panel>
    </div>

    <x-panel label="Researchers you referred" class="reveal reveal-d2 mt-6">
        <div class="space-y-3" data-referred-list>
            <p class="text-[12.5px] text-dim">Loading…</p>
        </div>
    </x-panel>

    <x-panel label="Credit ledger" class="reveal reveal-d3 mt-6">
        <p class="mb-3 text-[12px] text-dim">
            A ledger, not a stored balance — the total is the sum of these movements,
            so it can always be checked.
        </p>
        <div class="space-y-2" data-credit-ledger>
            <p class="text-[12.5px] text-dim">Loading…</p>
        </div>
    </x-panel>

</div>
@endsection
