<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyIncentiveAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'study_id',
        'event_type',
        'incentive_type',
        'amount',
        'currency',
        'metadata',
        'happened_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'happened_at' => 'datetime',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }
}
