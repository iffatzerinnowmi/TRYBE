<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyEscrow extends Model
{
    use HasFactory;

    protected $table = 'study_escrows';

    protected $fillable = [
        'study_id',
        'amount',
        'currency',
        'status',
        'provider_reference',
        'locked_at',
        'released_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'locked_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }
}
