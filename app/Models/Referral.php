<?php

namespace App\Models;

use App\Enums\ReferralStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nothing outside ReferralService writes this table.
 */
class Referral extends Model
{
    protected $fillable = [
        'referrer_id', 'referred_user_id', 'status', 'code_used',
        'signed_up_at', 'qualified_at', 'qualifying_study_id',
    ];

    protected $casts = [
        'status'       => ReferralStatus::class,
        'signed_up_at' => 'datetime',
        'qualified_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function qualifyingStudy(): BelongsTo
    {
        return $this->belongsTo(Study::class, 'qualifying_study_id');
    }

    public function scopeQualified(Builder $query): Builder
    {
        return $query->where('status', ReferralStatus::QUALIFIED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ReferralStatus::PENDING);
    }
}
