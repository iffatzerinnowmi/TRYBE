{{--
    FEATURE — Verified Payment Escrow (Member 3)

    Expects $study to be in scope, with 'escrow' loaded (and ideally
    'payouts' too, to avoid an extra query). Add ONE line to
    resources/views/studies/show.blade.php, wherever the compensation /
    incentive details are shown:

        @include('studies.partials.escrow-summary', ['study' => $study])

    And in StudyController::show(), eager-load what this partial needs:

        $study->load('researcher', 'escrow', 'payouts');
--}}

@php
    $escrow = $study->escrow;
@endphp

@if ($escrow)
    @php
        $payouts = $study->payouts ?? collect();
        $statusCounts = $payouts->countBy(fn ($p) => $p->status->value);

        // Soonest pending payout that hasn't been manually confirmed yet —
        // that's the one closest to the 72h auto-confirm cutoff.
        $nextDeadline = $payouts
            ->filter(fn ($p) => $p->status === \App\Enums\PayoutStatus::PENDING && $p->confirmation_deadline_at)
            ->sortBy('confirmation_deadline_at')
            ->first()?->confirmation_deadline_at;
    @endphp

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Escrow &amp; Payments</h3>
        </div>

        <div class="px-6 py-5">
            {{-- Balance stat blocks --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Locked</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">
                        {{ number_format($escrow->total_amount, 2) }}
                    </p>
                </div>
                <div class="rounded-lg bg-green-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-green-700">Released</p>
                    <p class="mt-1 text-xl font-semibold text-green-800">
                        {{ number_format($escrow->released_amount, 2) }}
                    </p>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Refunded</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">
                        {{ number_format($escrow->refunded_amount, 2) }}
                    </p>
                </div>
                <div class="rounded-lg bg-indigo-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-indigo-700">Remaining</p>
                    <p class="mt-1 text-xl font-semibold text-indigo-800">
                        {{ number_format($escrow->remainingAmount(), 2) }}
                    </p>
                </div>
            </div>

            {{-- Participant payout status breakdown --}}
            @if ($statusCounts->isNotEmpty())
                <div class="mt-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Participant Payouts</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach (\App\Enums\PayoutStatus::cases() as $status)
                            @if ($statusCounts->get($status->value))
                                <span class="inline-flex items-center rounded-full bg-{{ $status->badgeColor() }}-100 px-3 py-1 text-xs font-medium text-{{ $status->badgeColor() }}-800">
                                    {{ $statusCounts->get($status->value) }} {{ $status->label() }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- 72-hour auto-confirm countdown --}}
            @if ($nextDeadline)
                <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm font-medium text-amber-800">
                        A payout is awaiting your confirmation — it auto-releases in
                        <span
                            x-data
                            data-escrow-countdown
                            data-deadline="{{ $nextDeadline->toIso8601String() }}"
                            class="font-semibold"
                        >calculating…</span>
                        if not confirmed manually.
                    </p>
                </div>

                {{-- Inlined rather than @push('scripts') so this doesn't depend on
                     the layout having a @stack('scripts') defined. @once keeps
                     it from being duplicated if this partial is ever rendered
                     twice on the same page. --}}
                @once
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            function tick() {
                                document.querySelectorAll('[data-escrow-countdown]').forEach(function (el) {
                                    var deadline = new Date(el.dataset.deadline).getTime();
                                    var diff = deadline - Date.now();

                                    if (diff <= 0) {
                                        el.textContent = 'any moment now';
                                        return;
                                    }

                                    var hours = Math.floor(diff / 3600000);
                                    var minutes = Math.floor((diff % 3600000) / 60000);
                                    var seconds = Math.floor((diff % 60000) / 1000);

                                    el.textContent = hours + 'h ' + minutes + 'm ' + seconds + 's';
                                });
                            }

                            tick();
                            setInterval(tick, 1000);
                        });
                    </script>
                @endonce
            @endif
        </div>
    </div>
@endif