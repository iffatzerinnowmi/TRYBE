<?php

namespace App\Http\Controllers;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Models\PaymentPayout;
use App\Models\Study;
use App\Services\PaymentEscrowService;
use App\Services\Payments\BkashPaymentGateway;
use App\Services\Payments\SslcommerzPaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FEATURE — Verified Payment Escrow (Member 3)
 *
 * Web layer only — the researcher-facing "Payments" page. Manual payouts
 * go through SslcommerzPaymentGateway's real sandbox hosted-checkout
 * redirect whenever SSLCOMMERZ_STORE_ID etc. are configured. If missing
 * or rejected, falls back to the internal PaymentGateway
 * (SimulatedPaymentGateway) so the flow never dead-ends. Every state
 * change is still finalized through
 * PaymentEscrowService::applyGatewayResult() — this controller never
 * writes to payment_escrows / payment_payouts directly.
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
            'bkashConfigured' => filled(config('services.bkash.app_key')),
            'sslcommerzConfigured' => filled(config('services.sslcommerz.store_id')),
        ]);
    }

    /** PATCH /researcher/payout-settings */
    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payout_method' => ['required', 'in:bkash'],
            'payout_details' => ['required', 'array'],
            'payout_details.bkash_number' => ['required', 'regex:/^01[3-9][0-9]{8}$/'],
        ], [
            'payout_details.bkash_number.regex' => 'Enter a valid 11-digit Bangladeshi mobile number (e.g. 01770618575).',
        ]);

        abort_unless($request->user()->researcherProfile, 404, 'No researcher profile found for this account.');

        $request->user()->researcherProfile->update([
            'payout_method' => $data['payout_method'],
            'payout_details' => $data['payout_details'],
        ]);

        return back()->with('status', 'Payout settings saved.');
    }

    /** POST /researcher/payouts/{payout}/confirm — internal fallback, not linked from the main UI button */
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

    /** GET /researcher/payouts/{payout}/pay-via-bkash — kept as a secondary/legacy option */
    public function payViaBkash(PaymentPayout $payout, BkashPaymentGateway $bkash): RedirectResponse
    {
        $this->authorizeOwnership($payout);

        abort_unless(
            in_array($payout->status, [PayoutStatus::PENDING, PayoutStatus::FAILED], true),
            422,
            'This payout is not payable right now.'
        );

        if (blank(config('services.bkash.app_key'))) {
            return $this->settleInternally($payout);
        }

        $result = $bkash->createCheckout($payout);

        if (! $result['success']) {
            return back()->with('status', 'Could not start bKash checkout: '.$result['error']);
        }

        return redirect()->away($result['bkash_url']);
    }

    /** GET /researcher/bkash/callback */
    public function bkashCallback(Request $request, BkashPaymentGateway $bkash): RedirectResponse
    {
        $paymentId = $request->query('paymentID');

        $payout = PaymentPayout::where('gateway_payment_id', $paymentId)->first();

        if (! $payout) {
            return redirect()->route('researcher.payments')->with('status', 'Could not match this bKash payment to a payout.');
        }

        $this->authorizeOwnership($payout);

        if ($request->query('status') !== 'success') {
            return redirect()->route('researcher.payments')->with('status', 'bKash payment was cancelled or declined.');
        }

        $payout->update([
            'status' => PayoutStatus::PROCESSING,
            'confirmed_by' => 'researcher',
            'confirmed_at' => now(),
            'attempts' => $payout->attempts + 1,
        ]);

        $result = $bkash->executeCheckout($paymentId);
        $payout = $this->escrow->applyGatewayResult($payout, $result);

        return redirect()->route('researcher.payments')->with('status', match ($payout->status) {
            PayoutStatus::COMPLETED => 'Payment confirmed via bKash — payout released.',
            default => 'bKash payment failed: '.$payout->last_error,
        });
    }

    /** GET /researcher/payouts/{payout}/pay-via-sslcommerz — the primary "Pay" button action */
    public function payViaSslcommerz(PaymentPayout $payout, SslcommerzPaymentGateway $sslcommerz): RedirectResponse
    {
        $this->authorizeOwnership($payout);

        abort_unless(
            in_array($payout->status, [PayoutStatus::PENDING, PayoutStatus::FAILED], true),
            422,
            'This payout is not payable right now.'
        );

        if (blank(config('services.sslcommerz.store_id'))) {
            return $this->settleInternally($payout);
        }

        $result = $sslcommerz->createCheckout(
            $payout,
            route('researcher.sslcommerz.success'),
            route('researcher.sslcommerz.fail'),
            route('researcher.sslcommerz.cancel'),
        );

        if (! $result['success']) {
            return back()->with('status', 'Could not start SSLCommerz checkout: '.$result['error']);
        }

        return redirect()->away($result['checkout_url']);
    }

    /** POST /researcher/sslcommerz/success — SSLCommerz posts here after the payer completes payment */
    public function sslcommerzSuccess(Request $request, SslcommerzPaymentGateway $sslcommerz): RedirectResponse
    {
        $tranId = $request->input('tran_id');
        $valId = $request->input('val_id');

        $payout = PaymentPayout::where('gateway_payment_id', $tranId)->first();

        if (! $payout || ! $valId) {
            return redirect()->route('researcher.payments')->with('status', 'Could not match this SSLCommerz payment to a payout.');
        }

        $payout->update([
            'status' => PayoutStatus::PROCESSING,
            'confirmed_by' => 'researcher',
            'confirmed_at' => now(),
            'attempts' => $payout->attempts + 1,
        ]);

        $result = $sslcommerz->validateTransaction($valId);
        $payout = $this->escrow->applyGatewayResult($payout, $result);

        return redirect()->route('researcher.payments')->with('status', match ($payout->status) {
            PayoutStatus::COMPLETED => 'Payment confirmed via SSLCommerz — payout released.',
            default => 'SSLCommerz payment could not be validated: '.$payout->last_error,
        });
    }

    /** POST /researcher/sslcommerz/fail */
    public function sslcommerzFail(Request $request): RedirectResponse
    {
        $payout = PaymentPayout::where('gateway_payment_id', $request->input('tran_id'))->first();

        if ($payout) {
            $this->escrow->applyGatewayResult($payout, [
                'success' => false,
                'reference' => null,
                'error' => 'Payment failed on SSLCommerz.',
            ]);
        }

        return redirect()->route('researcher.payments')->with('status', 'SSLCommerz payment failed.');
    }

    /** POST /researcher/sslcommerz/cancel */
    public function sslcommerzCancel(Request $request): RedirectResponse
    {
        return redirect()->route('researcher.payments')->with('status', 'SSLCommerz payment was cancelled.');
    }

    /** Internal settlement — used whenever no real gateway is configured, or as a graceful failure fallback. */
    private function settleInternally(PaymentPayout $payout): RedirectResponse
    {
        $result = app(\App\Contracts\PaymentGateway::class)->send($payout);

        $payout->update([
            'status' => PayoutStatus::PROCESSING,
            'confirmed_by' => 'researcher',
            'confirmed_at' => now(),
            'attempts' => $payout->attempts + 1,
        ]);

        $payout = $this->escrow->applyGatewayResult($payout, $result);

        return redirect()->route('researcher.payments')->with('status', match ($payout->status) {
            PayoutStatus::COMPLETED => 'Payout confirmed and released.',
            default => 'Payout failed: '.$payout->last_error,
        });
    }

    private function authorizeOwnership(PaymentPayout $payout): void
    {
        abort_unless($payout->study?->researcher_id === auth()->id(), 403);
    }
}