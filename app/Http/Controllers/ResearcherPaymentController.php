<?php

namespace App\Http\Controllers;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Models\PaymentPayout;
use App\Models\Study;
use App\Services\PaymentEscrowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FEATURE — Verified Payment Escrow (Member 3)
 *
 * Web layer only — the researcher-facing "Payments" page. Every state
 * change (confirm, retry, settings) is delegated to PaymentEscrowService;
 * this controller never writes to payment_escrows / payment_payouts
 * directly, same rule the API side (EscrowApiController /
 * PayoutApiController) already follows.
 */
class ResearcherPaymentController extends Controller
{
    public function __construct(private PaymentEscrowService $escrow)
    {
    }

    /** GET /researcher/payments */
    public function index(): View
    {
        $user = auth()->user();

        $studies = Study::query()
            ->where('researcher_id', $user->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->with('escrow')
            ->latest('id')
            ->get();

        $payouts = PaymentPayout::query()
            ->whereHas('study', fn ($q) => $q->where('researcher_id', $user->id))
            ->with(['study', 'participant'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('researcher.payments.index', [
            'studies' => $studies,
            'payouts' => $payouts,
            'profile' => $user->researcherProfile,
            'payoutMethods' => PayoutMethod::cases(),
        ]);
    }

    /** PATCH /researcher/payout-settings */
    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payout_method' => [
                'required',
                'in:' . implode(',', array_map(fn ($case) => $case->value, PayoutMethod::cases())),
            ],
            'payout_details' => ['nullable', 'array'],
            'payout_details.*' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless($request->user()->researcherProfile, 404, 'No researcher profile found for this account.');

        $request->user()->researcherProfile->update([
            'payout_method' => $data['payout_method'],
            'payout_details' => $data['payout_details'] ?? null,
        ]);

        return back()->with('status', 'Payout settings saved.');
    }

    /** POST /researcher/payouts/{payout}/confirm */
    public function confirmPayout(PaymentPayout $payout): RedirectResponse
    {
        $this->authorizeOwnership($payout);

        abort_unless($payout->status === PayoutStatus::PENDING, 422, 'This payout is not awaiting confirmation.');

        $payout = $this->escrow->confirmAndRelease($payout);

        return back()->with('status', match ($payout->status) {
            PayoutStatus::COMPLETED => 'Payout confirmed and released.',
            PayoutStatus::FAILED => 'Payout confirmed, but the transfer failed — it will retry automatically.',
            default => 'Payout confirmation is being processed.',
        });
    }

    /** POST /researcher/payouts/{payout}/retry */
    public function retryPayout(PaymentPayout $payout): RedirectResponse
    {
        $this->authorizeOwnership($payout);

        abort_unless($payout->isRetryable(), 422, 'This payout is not eligible for a retry right now.');

        $payout = $this->escrow->retryFailed($payout);

        return back()->with('status', match ($payout->status) {
            PayoutStatus::COMPLETED => 'Retry succeeded — payout released.',
            PayoutStatus::FAILED => 'Retry attempted, but the transfer failed again.',
            default => 'Retry is being processed.',
        });
    }

    /** A researcher may only act on payouts belonging to their own studies. */
    private function authorizeOwnership(PaymentPayout $payout): void
    {
        abort_unless($payout->study?->researcher_id === auth()->id(), 403);
    }
}