<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One researcher -> one participant -> one study.
 *
 * Nothing outside StudyInvitationService writes this table.
 */
class StudyInvitation extends Model
{
    protected $fillable = [
        'study_id', 'participant_id', 'invited_by', 'status',
        'match_score_at_invite', 'match_reasons', 'notification_id',
        'responded_at', 'expires_at',
    ];

    protected $casts = [
        'status'                => InvitationStatus::class,
        'match_reasons'         => 'array',
        'match_score_at_invite' => 'integer',
        'responded_at'          => 'datetime',
        'expires_at'            => 'datetime',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(UserNotification::class, 'notification_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', InvitationStatus::PENDING);
    }

    /** Accepted or declined — the states that remove a candidate from the list. */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereIn('status', InvitationStatus::resolvedValues());
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
