<?php

namespace App\Models;

use App\Enums\CredentialLevel;
use App\Enums\ReferralRewardType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    protected $fillable = [
        'user_id', 'type', 'milestone',
        'granted_credential_level', 'post_credits', 'triggered_by_referral_id',
    ];

    protected $casts = [
        'type'                     => ReferralRewardType::class,
        'granted_credential_level' => CredentialLevel::class,
        'milestone'                => 'integer',
        'post_credits'             => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function triggeredByReferral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'triggered_by_referral_id');
    }
}
