<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudySlot extends Model
{
    protected $fillable = [
        'study_id',
        'starts_at',
        'ends_at',
        'capacity',
        'booked_count',
        'last_reminder_sent_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(StudySlotBooking::class);
    }

    public function remainingSeats(): int
    {
        return max(0, $this->capacity - $this->bookings()->where('status', 'booked')->count());
    }

    public function isAvailable(): bool
    {
        return $this->remainingSeats() > 0 && $this->starts_at->isFuture();
    }
}
