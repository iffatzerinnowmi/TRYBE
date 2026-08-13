<?php

namespace App\Models;

use App\Enums\PostCreditReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per movement. The balance is SUM(delta) — never a stored total.
 */
class ResearcherPostCredit extends Model
{
    protected $fillable = ['user_id', 'delta', 'reason', 'referral_reward_id'];

    protected $casts = [
        'delta'  => 'integer',
        'reason' => PostCreditReason::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(ReferralReward::class, 'referral_reward_id');
    }
}
