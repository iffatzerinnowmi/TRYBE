<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PaymentPayout extends Model
{
    protected $fillable = [
        'study_id', 'participant_id', 'study_participation_id',
        'amount', 'method', 'status',
        'attempts', 'max_attempts', 'next_retry_at', 'last_error', 'gateway_reference',
        'confirmed_by', 'confirmed_at', 'confirmation_deadline_at',
        'processed_at', 'failed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'method' => PayoutMethod::class,
        'status' => PayoutStatus::class,
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'confirmation_deadline_at' => 'datetime',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_id');
    }

    public function studyParticipation(): BelongsTo
    {
        return $this->belongsTo(StudyParticipation::class);
    }

    public function isRetryable(): bool
    {
        return $this->status === PayoutStatus::FAILED
            && $this->attempts < $this->max_attempts;
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('status', PayoutStatus::FAILED->value)
            ->whereColumn('attempts', '<', 'max_attempts')
            ->where(function (Builder $q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', Carbon::now());
            });
    }

    public function scopeDueForAutoConfirm(Builder $query): Builder
    {
        return $query->where('status', PayoutStatus::PENDING->value)
            ->whereNotNull('confirmation_deadline_at')
            ->where('confirmation_deadline_at', '<=', Carbon::now());
    }
}