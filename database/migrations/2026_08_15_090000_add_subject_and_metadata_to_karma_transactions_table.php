<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KarmaTransaction's $fillable expects subject_type/subject_id (a morph
 * to whatever earned the row) and metadata (a free-form note). Some of
 * these may already exist depending on how your local
 * karma_transactions migration was originally written — so each column
 * is added individually, guarded by hasColumn(), instead of via
 * nullableMorphs() which adds both subject columns in one shot and fails
 * if even one of them is already there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karma_transactions', function (Blueprint $t) {
            if (! Schema::hasColumn('karma_transactions', 'subject_type')) {
                $t->string('subject_type')->nullable();
            }

            if (! Schema::hasColumn('karma_transactions', 'subject_id')) {
                $t->unsignedBigInteger('subject_id')->nullable();
            }

            if (! Schema::hasColumn('karma_transactions', 'metadata')) {
                $t->json('metadata')->nullable();
            }
        });

        // Index the morph pair only if both columns exist and the index isn't there already.
        if (Schema::hasColumn('karma_transactions', 'subject_type')
            && Schema::hasColumn('karma_transactions', 'subject_id')) {
            Schema::table('karma_transactions', function (Blueprint $t) {
                $t->index(['subject_type', 'subject_id'], 'karma_transactions_subject_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('karma_transactions', function (Blueprint $t) {
            if (Schema::hasColumn('karma_transactions', 'metadata')) {
                $t->dropColumn('metadata');
            }
        });
    }
};