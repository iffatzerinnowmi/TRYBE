<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The eligibility criteria that drive Smart Participant Matching.
 *
 * WHY THIS TABLE EXISTS
 * ---------------------
 * StudyMatchingService used to decide a study's criteria from a hardcoded
 * lookup table keyed by the study TITLE. Renaming a study changed who
 * matched it, and the numbers driving the score were not in the database at
 * all. This table is where they live now.
 *
 * It is a separate 1:1 table rather than columns on `studies` because
 * `studies` belongs to Member 2 and team rules forbid adding columns to
 * another member's table. `study_id` is unique, so it is a true 1:1.
 *
 * Every column is nullable. A study with no row here falls back to
 * config('platform.matching.defaults') — the only place a default number is
 * allowed to live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_match_criteria', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->unique()->constrained()->cascadeOnDelete();

            $t->unsignedTinyInteger('age_min')->nullable();
            $t->unsignedTinyInteger('age_max')->nullable();
            $t->string('location')->nullable();
            $t->string('credential_min')->nullable();          // App\Enums\CredentialLevel
            $t->json('required_skills')->nullable();           // ["python","ui testing"]
            $t->unsignedSmallInteger('availability_days')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_match_criteria');
    }
};
