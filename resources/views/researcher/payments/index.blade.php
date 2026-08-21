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

                <input type="hidden" name="payout_method" value="bkash">

                <div class="flex items-center gap-2 rounded-lg bg-[#00A651]/10 px-3 py-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-[#00A651] text-[10px] font-black text-white">S</span>
                    <span class="text-sm font-semibold text-[#00A651]">Payouts via SSLCommerz</span>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                        Mobile Number
                    </label>
                    <input type="text" name="payout_details[bkash_number]"
                           value="{{ old('payout_details.bkash_number', optional($profile)->payout_details['bkash_number'] ?? '') }}"
                           placeholder="01XXXXXXXXX"
                           pattern="01[3-9][0-9]{8}"
                           title="Enter an 11-digit Bangladeshi mobile number starting with 01"
                           required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-400">
                        Payouts are processed through SSLCommerz's sandbox checkout — no separate account setup needed for the demo.
                    </p>
                </div>

                @error('payout_details.bkash_number')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror

                <button type="submit" class="w-full rounded-lg bg-[#00A651] px-4 py-2 text-sm font-semibold text-white hover:bg-[#008a44]">
                    Save payout settings
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
                        <th class="py-2 pr-4">Transaction ID</th>
                        <th class="py-2 pr-4">Study</th>
                        <th class="py-2 pr-4">Participant</th>
                        <th class="py-2 pr-4">Amount (BDT)</th>
                        <th class="py-2 pr-4">Method</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payouts as $payout)
                        <tr class="border-b border-slate-100">
                            <td class="py-3 pr-4 font-mono text-xs text-slate-600">
                                {{ $payout->gateway_reference ?? '—' }}
                            </td>
                            <td class="py-3 pr-4">{{ $payout->study?->title }}</td>
                            <td class="py-3 pr-4">{{ $payout->participant?->name }}</td>
                            <td class="py-3 pr-4 font-semibold">{{ number_format($payout->amount, 2) }}</td>
                            <td class="py-3 pr-4">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#00A651]/10 px-2.5 py-1 text-xs font-bold text-[#00A651]">
                                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-[#00A651] text-[9px] font-black text-white">S</span>
                                    SSLCommerz
                                </span>
                            </td>
                            <td class="py-3 pr-4">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-800' => $payout->status->value === 'completed',
                                    'bg-amber-100 text-amber-800' => $payout->status->value === 'pending',
                                    'bg-blue-100 text-blue-800' => $payout->status->value === 'processing',
                                    'bg-red-100 text-red-800' => $payout->status->value === 'failed',
                                ])>
                                    {{ $payout->status->value === 'completed' ? 'Paid' : $payout->status->label() }}
                                </span>
                                @if ($payout->confirmed_by === 'system:auto-confirm')
                                    <p class="mt-0.5 text-[10px] text-slate-400">Auto-released after 72h</p>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-xs text-slate-500">
                                {{ $payout->processed_at?->format('d M Y, H:i') ?? ($payout->confirmation_deadline_at?->format('\d\u\e d M Y') ?? 'N/A') }}
                            </td>
                            <td class="py-3 pr-4">
                                @if ($payout->status->value === 'pending')
                                    <a href="{{ route('researcher.payouts.pay-via-sslcommerz', $payout) }}"
                                       class="inline-flex items-center gap-1.5 rounded-md bg-[#00A651] px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#008a44]">
                                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-white text-[9px] font-black text-[#00A651]">S</span>
                                        Pay with SSLCommerz
                                    </a>
                                @elseif ($payout->isRetryable())
                                    <a href="{{ route('researcher.payouts.pay-via-sslcommerz', $payout) }}"
                                       class="inline-flex items-center gap-1.5 rounded-md border border-[#00A651] px-3 py-2 text-xs font-bold text-[#00A651] hover:bg-[#00A651]/5">
                                        Retry with SSLCommerz
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-sm text-slate-500">No payouts yet.</td>
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