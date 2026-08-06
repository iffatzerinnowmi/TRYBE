<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * study_reviews holds both researcher_id and participant_id, but nothing
 * says WHICH of the two wrote the review.
 *
 * The reliability score only counts reviews written BY a researcher ABOUT a
 * participant, so we need to be able to tell the two directions apart.
 *
 * This column is nullable with a default, so every row that already exists
 * keeps working and no teammate's code has to change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_reviews', function (Blueprint $t) {
            if (! Schema::hasColumn('study_reviews', 'reviewer_role')) {
                // 'participant' = a participant rating a researcher (the existing rows)
                // 'researcher'  = a researcher rating a participant (feeds reliability)
                $t->string('reviewer_role')->default('participant')->after('participant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('study_reviews', function (Blueprint $t) {
            if (Schema::hasColumn('study_reviews', 'reviewer_role')) {
                $t->dropColumn('reviewer_role');
            }
        });
    }
};
