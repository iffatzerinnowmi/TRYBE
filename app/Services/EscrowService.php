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

        $lockedAt = new DateTimeImmutable();

        return [
            'provider_reference' => $reference,
            'status' => 'locked',
            // return an ISO-8601 timestamp which is safe to persist/serialize
            'locked_at' => $lockedAt->format(DATE_ATOM),
            'amount' => $amount,
            'currency' => strtoupper($currency),
        ];
    }
}
