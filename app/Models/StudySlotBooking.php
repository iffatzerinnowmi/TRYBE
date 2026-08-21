<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudySlotBooking extends Model
{
    protected $fillable = [
        'study_id',
        'study_slot_id',
        'participant_id',
        'status',
        'booked_at',
        'google_calendar_event_id',
    ];

    protected $casts = [
        'booked_at' => 'datetime',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(StudySlot::class, 'study_slot_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_id');
    }
}
