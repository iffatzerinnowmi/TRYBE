<?php

namespace App\Models;

use App\Enums\SeatApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FEATURE — Limited Seat Auctions (Member 3)
 *
 * The only writer for this table is SeatAuctionService.
 */
class StudySeatApplication extends Model
{
    protected $fillable = [
        'study_id', 'participant_id', 'reliability_score_snapshot',
        'status', 'rank', 'applied_at', 'decided_at',
    ];

    protected $casts = [
        'status' => SeatApplicationStatus::class,
        'applied_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_id');
    }
}