<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FEATURE — Participant Free-to-Paid Unlock Rule (Member 3)
 *
 * Adds the missing step between "confirmed" and "completed": the
 * participant requests completion, the researcher approves or declines it.
 * We track the request separately from `stage` so `stage` only ever moves
 * to COMPLETED once a researcher has actually approved it — that's the
 * moment StudyParticipationObserver checks the free-to-paid unlock rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_participations', function (Blueprint $t) {
            if (! Schema::hasColumn('study_participations', 'completion_requested_at')) {
                $t->timestamp('completion_requested_at')->nullable()->after('stage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('study_participations', function (Blueprint $t) {
            if (Schema::hasColumn('study_participations', 'completion_requested_at')) {
                $t->dropColumn('completion_requested_at');
            }
        });
    }
};