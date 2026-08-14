<?php

namespace App\Models;

use App\Enums\KarmaSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One row per karma movement. The balance is SUM(amount) — never a stored
 * total. Nothing outside App\Services\KarmaService writes this table.
 */
class KarmaTransaction extends Model
{
    protected $fillable = [
        'user_id', 'source', 'amount', 'subject_type', 'subject_id', 'metadata',
    ];

    protected $casts = [
        'source'   => KarmaSource::class,
        'amount'   => 'integer',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The StudyParticipation, StudyReview, or Referral that earned this row, if any. */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}