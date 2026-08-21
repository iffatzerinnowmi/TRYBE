<?php
namespace App\Services\Payments;
use App\Models\PaymentPayout;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FEATURE — Verified Payment Escrow (Member 3), SSLCommerz integration
 *
 * SSLCommerz's Hosted Checkout: create a "session" via a form POST, get
 * back a GatewayPageURL to redirect the browser to. After the payer
 * completes (or cancels) on SSLCommerz's page, it POSTs the result back
 * to our success/fail/cancel URLs — we must server-to-server *validate*
 * that val_id against SSLCommerz's validation API before trusting it,
 * since the browser POST alone is spoofable.
 */
class SslcommerzPaymentGateway
{
    /**
     * @return array{success: bool, checkout_url: ?string, error: ?string}
     */
    public function createCheckout(PaymentPayout $payout, string $successUrl, string $failUrl, string $cancelUrl): array
    {
        $tranId = 'PAYOUT-'.$payout->id.'-'.now()->timestamp;

        try {
            $response = Http::asForm()->post(config('services.sslcommerz.session_url'), [
                'store_id' => config('services.sslcommerz.store_id'),
                'store_passwd' => config('services.sslcommerz.store_password'),
                'total_amount' => number_format((float) $payout->amount, 2, '.', ''),
                'currency' => 'BDT',
                'tran_id' => $tranId,
                'success_url' => $successUrl,
                'fail_url' => $failUrl,
                'cancel_url' => $cancelUrl,
                'cus_name' => $payout->participant?->name ?? 'Study Participant',
                'cus_email' => $payout->participant?->email ?? 'participant@example.com',
                'cus_add1' => 'Dhaka',
                'cus_city' => 'Dhaka',
                'cus_postcode' => '1000',
                'cus_country' => 'Bangladesh',
                'cus_phone' => '01700000000',
                'shipping_method' => 'NO',
                'product_name' => 'Study Payout #'.$payout->id,
                'product_category' => 'Service',
                'product_profile' => 'general',
            ]);

            $data = $response->json();

            if (($data['status'] ?? null) !== 'SUCCESS' || empty($data['GatewayPageURL'])) {
                Log::warning('SSLCommerz session create failed.', ['payout_id' => $payout->id, 'body' => $response->body()]);

                return [
                    'success' => false,
                    'checkout_url' => null,
                    'error' => $data['failedreason'] ?? 'SSLCommerz could not start the checkout.',
                ];
            }

            $payout->update(['gateway_payment_id' => $tranId]);

            return [
                'success' => true,
                'checkout_url' => $data['GatewayPageURL'],
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('SSLCommerz session create threw.', ['payout_id' => $payout->id, 'error' => $e->getMessage()]);

            return ['success' => false, 'checkout_url' => null, 'error' => 'SSLCommerz is temporarily unavailable.'];
        }
    }

    /**
     * @return array{success: bool, reference: ?string, error: ?string}
     */
    public function validateTransaction(string $valId): array
    {
        try {
            $response = Http::get(config('services.sslcommerz.validation_url'), [
                'val_id' => $valId,
                'store_id' => config('services.sslcommerz.store_id'),
                'store_passwd' => config('services.sslcommerz.store_password'),
                'format' => 'json',
            ]);

            $data = $response->json();
            $status = $data['status'] ?? null;

            if (in_array($status, ['VALID', 'VALIDATED'], true)) {
                return [
                    'success' => true,
                    'reference' => $data['bank_tran_id'] ?? $data['tran_id'] ?? $valId,
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'reference' => null,
                'error' => 'SSLCommerz validation status: '.($status ?? 'unknown'),
            ];
        } catch (\Throwable $e) {
            Log::error('SSLCommerz validation threw.', ['val_id' => $valId, 'error' => $e->getMessage()]);

            return ['success' => false, 'reference' => null, 'error' => 'SSLCommerz is temporarily unavailable.'];
        }
    }
}