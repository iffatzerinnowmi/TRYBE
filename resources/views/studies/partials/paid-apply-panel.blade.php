{{--
    FEATURE — Karma Credits alternate unlock (Feature B), on top of the
    volunteer/paid cycle (Feature A). Renders nothing for volunteer
    studies or for researchers. Expects $study and $user in scope.

    Include in resources/views/studies/show.blade.php:
        @include('studies.partials.paid-apply-panel', ['study' => $study, 'user' => $user])

    Entirely API-driven by resources/js/paid-application.js, which fetches
    GET /api/v1/studies/{study}/paid-application and posts to the same URL.
--}}

@if ($user->role?->value === 'participant' && $study->incentive_type->value !== 'volunteer')
<x-panel label="Paid application" class="reveal reveal-d1"
         data-paid-apply-panel data-study-id="{{ $study->id }}">
    <div class="space-y-3">
        <p class="text-[12.5px] text-dim" data-paid-apply-progress>Loading…</p>

        <button type="button" data-paid-apply-btn
                class="hidden w-full rounded-lg bg-plum px-3 py-2.5 text-[13px] font-semibold text-white transition hover:bg-plum/90 disabled:cursor-not-allowed disabled:opacity-50">
        </button>

        <p class="text-[11.5px] text-danger" data-paid-apply-note></p>
        <p class="text-[11.5px] text-ok" data-paid-apply-success></p>
    </div>
</x-panel>
@endif