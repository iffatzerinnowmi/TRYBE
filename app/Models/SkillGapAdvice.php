<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One current skill-gap record per participant, replaced in place.
 * Written only by SkillGapService.
 */
class SkillGapAdvice extends Model
{
    protected $table = 'skill_gap_advice';

    protected $fillable = [
        'user_id', 'analysis', 'advice', 'inputs_hash',
        'model', 'generated_at', 'last_error',
    ];

    protected $casts = [
        'analysis'     => 'array',
        'advice'       => 'array',
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** True when the stored advice was generated from the current analysis. */
    public function isCurrent(string $hash): bool
    {
        return $this->advice !== null && $this->inputs_hash === $hash;
    }
}
