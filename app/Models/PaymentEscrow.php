<?php

namespace App\Models;

use App\Enums\EscrowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FEATURE — Verified Payment Escrow (Member 3)
 *
 * One row per cash/voucher study. PaymentEscrowService is the only writer
 * — see that class for the full lock / release / refund lifecycle.
 */
class PaymentEscrow extends Model
{
    protected $fillable = [
        'study_id',
        'total_amount', 'released_amount', 'refunded_amount',
        'fee_charged', 'fee_percentage',
        'status', 'locked_at', 'refunded_at', 'refund_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'released_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'fee_charged' => 'decimal:2',
        'fee_percentage' => 'integer',
        'status' => EscrowStatus::class,
        'locked_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    /** Every payout raised against this study's escrow. */
    public function payouts(): HasMany
    {
        return $this->hasMany(PaymentPayout::class, 'study_id', 'study_id');
    }

    /**
     * What's left to release: total deposited, minus whatever has already
     * gone out to participants, minus whatever was refunded on cancellation.
     */
    public function remainingAmount(): float
    {
        return round(
            (float) $this->total_amount - (float) $this->released_amount - (float) $this->refunded_amount,
            2
        );
    }
}