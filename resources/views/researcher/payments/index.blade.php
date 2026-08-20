@extends('layouts.app')

@section('title', 'Payments')

@section('content')
<div class="wrap pb-20">
    <x-page-header eyebrow="Researcher" title="Payments & Payouts">
        <x-btn href="{{ route('dashboard') }}" variant="ghost">Back to dashboard</x-btn>
    </x-page-header>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        {{-- Active studies with escrow --}}
        <x-panel label="Active studies">
            <div class="space-y-4">
                @forelse ($studies as $study)
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <a href="{{ route('studies.show', $study) }}" class="font-semibold text-slate-900 hover:underline">
                                    {{ $study->title }}
                                </a>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ ucfirst($study->status->value) }} · ৳{{ number_format($study->compensation_amount, 0) }} compensation
                                </p>
                            </div>

                            @if ($study->escrow)
                                <span class="inline-flex items-center rounded-full bg-{{ $study->escrow->status->badgeColor() }}-100 px-3 py-1 text-xs font-medium text-{{ $study->escrow->status->badgeColor() }}-800">
                                    {{ $study->escrow->status->label() }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                                    No escrow
                                </span>
                            @endif
                        </div>

                        @if ($study->escrow)
                            <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                <div>
                                    <p class="text-slate-500">Locked</p>
                                    <p class="font-semibold text-slate-900">{{ number_format($study->escrow->total_amount, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-slate-500">Released</p>
                                    <p class="font-semibold text-green-700">{{ number_format($study->escrow->released_amount, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-slate-500">Remaining</p>
                                    <p class="font-semibold text-indigo-700">{{ number_format($study->escrow->remainingAmount(), 2) }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No active cash/voucher studies yet.</p>
                @endforelse
            </div>
        </x-panel>

        {{-- Payout settings --}}
        <x-panel label="Payout settings">
            <form method="POST" action="{{ route('researcher.payout-settings.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Method</label>
                    <select name="payout_method" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($payoutMethods as $method)
                            <option value="{{ $method->value }}" @selected(optional($profile)->payout_method === $method)>
                                {{ $method->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Account details</label>
                    <input type="text" name="payout_details[account]"
                           value="{{ optional($profile)->payout_details['account'] ?? '' }}"
                           placeholder="Account / wallet number"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Save settings
                </button>
            </form>
        </x-panel>
    </div>

    {{-- Payout history --}}
    <x-panel label="Payout history" class="mt-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">Study</th>
                        <th class="py-2 pr-4">Participant</th>
                        <th class="py-2 pr-4">Amount</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Attempts</th>
                        <th class="py-2 pr-4">Deadline</th>
                        <th class="py-2 pr-4"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payouts as $payout)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-4">{{ $payout->study?->title }}</td>
                            <td class="py-2 pr-4">{{ $payout->participant?->name }}</td>
                            <td class="py-2 pr-4">{{ number_format($payout->amount, 2) }}</td>
                            <td class="py-2 pr-4">
                                <span class="inline-flex items-center rounded-full bg-{{ $payout->status->badgeColor() }}-100 px-2.5 py-0.5 text-xs font-medium text-{{ $payout->status->badgeColor() }}-800">
                                    {{ $payout->status->label() }}
                                </span>
                            </td>
                            <td class="py-2 pr-4">{{ $payout->attempts }}/{{ $payout->max_attempts }}</td>
                            <td class="py-2 pr-4 text-xs text-slate-500">
                                {{ $payout->confirmation_deadline_at?->format('M d, H:i') ?? '—' }}
                            </td>
                            <td class="py-2 pr-4">
                                @if ($payout->status->value === 'pending')
                                    <form method="POST" action="{{ route('researcher.payouts.confirm', $payout) }}">
                                        @csrf
                                        <button class="text-xs font-semibold text-indigo-600 hover:underline">Confirm</button>
                                    </form>
                                @elseif ($payout->isRetryable())
                                    <form method="POST" action="{{ route('researcher.payouts.retry', $payout) }}">
                                        @csrf
                                        <button class="text-xs font-semibold text-amber-600 hover:underline">Retry</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-sm text-slate-500">No payouts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $payouts->links() }}
        </div>
    </x-panel>
</div>
@endsection