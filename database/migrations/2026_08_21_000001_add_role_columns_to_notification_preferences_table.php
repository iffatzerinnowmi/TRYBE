<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEMBER 1 — role-aware notification preferences.
 *
 * The original notification_preferences table held five columns, all of them
 * written for a PARTICIPANT: studies, verification, endorsements, streaks and
 * credential level-ups. A researcher has no streak and no Bronze/Gold/Expert
 * ladder, so two of those five switches did nothing on their account, and the
 * three things a researcher actually cares about had nowhere to live.
 *
 * This migration adds those three.
 *
 * ------------------------------------------------------------------------
 * WHY THERE IS NO ->after() IN THIS FILE
 * ------------------------------------------------------------------------
 * Member 3's unlock migration adds its column with ->after('notify_credential').
 * If two migrations both try to position themselves relative to the same
 * anchor, whichever runs second can fail depending on what the first one did.
 *
 * Column ORDER inside a MySQL table has no effect on Eloquent — it reads by
 * name, never by position. So the safe move is to not ask for a position at
 * all. These three columns are simply appended, and Member 3's migration is
 * free to anchor itself wherever it likes, in either order.
 *
 * ------------------------------------------------------------------------
 * WHY EVERY COLUMN IS GUARDED
 * ------------------------------------------------------------------------
 * Schema::hasColumn() means this file can be run twice, or run on a branch
 * where a teammate already added one of these names, without throwing
 * "Duplicate column name". On a database four people share, that guard is
 * worth the three extra lines.
 */
return new class extends Migration
{
    /** The columns this migration owns. Nothing else touches them. */
    private array $columns = [
        'notify_applications',
        'notify_slots',
        'notify_irb',
    ];

    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                if (! Schema::hasColumn('notification_preferences', $column)) {
                    // Default true: an existing researcher row gets these
                    // switched on automatically, with no backfill query.
                    $table->boolean($column)->default(true);
                }
            }
        });
    }

    public function down(): void
    {
        // Drop only what exists, and only what this migration added, so a
        // rollback can never take Member 3's column with it.
        $present = array_filter(
            $this->columns,
            fn (string $column) => Schema::hasColumn('notification_preferences', $column)
        );

        if ($present === []) {
            return;
        }

        Schema::table('notification_preferences', function (Blueprint $table) use ($present) {
            $table->dropColumn(array_values($present));
        });
    }
};