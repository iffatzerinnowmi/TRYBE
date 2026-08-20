<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per user. Every column is a boolean switch, and every column name
 * matches a 'column' key in NotificationService::TYPES — that pairing is the
 * whole contract, so if you add a column here you must add the type there,
 * and the other way round.
 *
 * The first five are participant-facing. The last three were added for
 * researchers and organizations in
 * 2026_08_21_000001_add_role_columns_to_notification_preferences_table.
 */
class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',

        // Participant-facing.
        'notify_studies',
        'notify_verify',
        'notify_endorse',
        'notify_streak',
        'notify_credential',

        // Researcher / organization facing.
        'notify_applications',
        'notify_slots',
        'notify_irb',

        // MEMBER 3 — uncomment BOTH this line and the cast below once the
        // notify_unlock migration has actually run on trybe_shared. Leaving
        // it listed before the column exists causes a "Column not found" on
        // every save, so the two must be switched on together.
        // 'notify_unlock',
    ];

    protected $casts = [
        'notify_studies'      => 'boolean',
        'notify_verify'       => 'boolean',
        'notify_endorse'      => 'boolean',
        'notify_streak'       => 'boolean',
        'notify_credential'   => 'boolean',
        'notify_applications' => 'boolean',
        'notify_slots'        => 'boolean',
        'notify_irb'          => 'boolean',
        // 'notify_unlock'    => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}