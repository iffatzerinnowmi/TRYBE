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
 * Three migrations add to this table from two branches, all guarded by
 * hasColumn(), so they coexist:
 *   add_role_columns_to_notification_preferences_table   (Member 1)
 *   add_notify_payments_to_notification_preferences_table (Member 3)
 *   add_notify_auction_to_notification_preferences_table  (Member 3)
 * The merge keeps every field from both sides.
 */
class NotificationPreference extends Model
{
    /*
    | DEFAULTS LIVE HERE, NOT ONLY ON THE COLUMNS.
    |
    | NotificationService::wants() does firstOrCreate() and reads the result
    | without re-reading it from the database. A row created that way carries
    | only user_id, so every notify_* attribute came back NULL, cast to false,
    | and the user was treated as having switched everything off — silently,
    | with no error and nothing in the log.
    |
    | DatabaseSeeder creates a preferences row for exactly one user, so in
    | practice that affected almost everybody and every feature that notifies.
    | $attributes gives the in-memory model the same defaults the columns have.
    */
    protected $attributes = [
        'notify_studies'      => true,
        'notify_verify'       => true,
        'notify_endorse'      => true,
        'notify_streak'       => true,
        'notify_credential'   => true,
        'notify_applications' => true,
        'notify_slots'        => true,
        'notify_irb'          => true,
        'notify_payments'     => true,
        'notify_auction'      => true,
    ];

    protected $fillable = [
        'user_id',

        // Participant-facing.
        'notify_studies',
        'notify_verify',
        'notify_endorse',
        'notify_streak',
        'notify_credential',

        // Researcher / organization facing (Member 1).
        'notify_applications',
        'notify_slots',
        'notify_irb',

        // Payments and auctions (Member 3).
        'notify_payments',
        'notify_auction',

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
        'notify_payments'     => 'boolean',
        'notify_auction'      => 'boolean',
        // 'notify_unlock'    => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
