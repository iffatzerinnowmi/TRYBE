<?php

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;

class EscrowService
{
    /**
     * Locks cash/voucher funds before study publication.
     * Replace with a real payment provider integration.
     */
    public function lockFunds(float $amount, string $currency): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Escrow amount must be greater than zero.');
        }

        $reference = sprintf('escrow_%s_%s', strtolower($currency), bin2hex(random_bytes(6)));

        return [
            'provider_reference' => $reference,
            'status' => 'locked',
            'locked_at' => new DateTimeImmutable(),
        ];
    }
}
