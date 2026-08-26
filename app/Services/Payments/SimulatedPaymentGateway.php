<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PayoutMethod;
use App\Models\PaymentPayout;

class SimulatedPaymentGateway implements PaymentGateway
{
    private const FAILURE_RATE = 0.15;

    public function send(PaymentPayout $payout): array
    {
        // Simulate a 15% chance of payment failure
        if ((mt_rand(1, 100) / 100) <= self::FAILURE_RATE) {
            return [
                'success' => false,
                'reference' => null,
                'error' => $this->simulatedError($payout->method),
            ];
        }

        // Simulate a successful payment
        return [
            'success' => true,
            'reference' => $this->reference($payout->method),
            'error' => null,
        ];
    }

    private function reference(?PayoutMethod $method): string
    {
        $prefix = match ($method) {
            PayoutMethod::BANK_TRANSFER => 'BNK',
            PayoutMethod::MOBILE_MONEY => 'BKS',
            default => 'PAY',
        };

        $random = strtoupper(
            substr(
                str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ0123456789'),
                0,
                7
            )
        );

        return $prefix . $random;
    }

    private function simulatedError(?PayoutMethod $method): string
    {
        return match ($method) {
            PayoutMethod::BANK_TRANSFER =>
                'Mock bank transfer failed. Please check the account details.',

            PayoutMethod::MOBILE_MONEY =>
                'Mock bKash payment failed. The payment could not be processed.',

            default =>
                'Mock payment failed.',
        };
    }
}