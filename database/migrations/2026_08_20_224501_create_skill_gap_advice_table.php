<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FEATURE — Study Recommendation Feed: the skill-gap coach  (Member 4)
 *
 * WHY A TABLE AND NOT Cache::remember()
 * -------------------------------------
 * 1. The demo survives a dead network. The last generated advice is on disk,
 *    so if the AI provider is unreachable the page still shows real advice
 *    and says when it was produced.
 * 2. inputs_hash is the invalidation rule, and it is deterministic and
 *    explainable: hash the analysis payload, and if the hash matches what is
 *    stored the advice is still current and no API call happens. That is a
 *    better answer than "it's cached for an hour".
 * 3. It survives php artisan cache:clear, which somebody will run at the
 *    worst possible moment.
 *
 * `analysis` is the DETERMINISTIC half — near-miss studies and the exact
 * missing skills, computed entirely by StudyMatchingService. It is rendered
 * whether or not the AI ever runs, which is why it is NOT nullable while
 * `advice` is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_gap_advice', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Computed by us. Always present.
            $t->json('analysis');

            // Returned by the model, already validated into our own shape.
            // Null until the AI has successfully run at least once.
            $t->json('advice')->nullable();

            $t->string('inputs_hash', 64)->index();

            $t->string('model')->nullable();
            $t->timestamp('generated_at')->nullable();
            $t->string('last_error')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_gap_advice');
    }
};
