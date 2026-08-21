<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completion claims  (Member 4)
 *
 * A participant saying "I filled in the study's form". NOT a completion.
 *
 * WHY THIS IS A SEPARATE TABLE AND NOT A STAGE
 * --------------------------------------------
 * study_participations.stage = 'completed' drives credential level, karma,
 * volunteer progress toward paid-study access, and referral qualification —
 * five consumers, all already wired. A participant who could set that value
 * themselves could mint all five by clicking a button.
 *
 * So the claim lives here, the researcher stays the verifier, and
 * StudyCompletionService never writes `stage`.
 *
 * form_url is stored ON THE ROW rather than read from config at display time,
 * so changing the configured form later never rewrites what a participant was
 * actually shown when they submitted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_completion_claims', function (Blueprint $t) {
            $t->id();

            $t->foreignId('study_id')->constrained()->cascadeOnDelete();
            $t->foreignId('participant_id')->constrained('users')->cascadeOnDelete();

            // App\Enums\CompletionClaimStatus
            $t->string('status')->default('submitted');

            $t->string('form_url')->nullable();
            $t->timestamp('opened_form_at')->nullable();
            $t->timestamp('submitted_at')->nullable();

            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $t->string('note', 500)->nullable();

            $t->timestamps();

            // One claim per person per study. Re-submitting updates this row;
            // it never creates a second. Enforced here rather than by an
            // if-statement, so two simultaneous requests cannot both insert.
            $t->unique(['study_id', 'participant_id']);

            // The researcher-facing query: "claims on my study, newest first".
            $t->index(['study_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_completion_claims');
    }
};
