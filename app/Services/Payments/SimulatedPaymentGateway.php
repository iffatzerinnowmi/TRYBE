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
        if ((mt_rand(1, 100) / 100) <= self::FAILURE_RATE) {
            return [
                'success' => false,
                'reference' => null,
                'error' => $this->simulatedError($payout->method),
            ];
        }

        return [
            'success' => true,
            'reference' => $this->reference($payout->method),
            'error' => null,
        ];
    }

    private function reference(?PayoutMethod $method): string
    {
        $prefix = match ($method) {
            PayoutMethod::BANK_TRANSFER => 'BT',
            PayoutMethod::MOBILE_MONEY => 'MM',
            PayoutMethod::GIFT_CARD => 'GC',
            default => 'PO',
        };

        return $prefix.'-'.strtoupper(bin2hex(random_bytes(4)));
    }

    private function simulatedError(?PayoutMethod $method): string
    {
        return match ($method) {
            PayoutMethod::BANK_TRANSFER => 'Bank rejected the transfer — account details could not be verified.',
            PayoutMethod::MOBILE_MONEY => 'Mobile money provider timed out processing the request.',
            PayoutMethod::GIFT_CARD => 'Gift card provider is temporarily out of inventory for this denomination.',
            default => 'The payment provider declined the transfer.',
        };
    }
}