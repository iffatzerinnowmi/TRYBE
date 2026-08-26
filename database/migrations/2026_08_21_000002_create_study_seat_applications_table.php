<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FEATURE — Limited Seat Auctions (Member 3)
 *
 * One row per participant who applies to an auction-mode study. This is
 * deliberately its OWN table rather than reusing study_participations:
 *
 *   - study_participations.stage is Member 2's single-writer column (via
 *     PipelineWriter). An applicant who loses the auction never becomes a
 *     pipeline row at all, so there's nothing there to write.
 *   - A winner still gets a real pipeline row — SeatAuctionService calls
 *     PipelineWriter::confirm() for winners only — but the auction's own
 *     bookkeeping (who applied, their score at the moment of applying, who
 *     won) lives here, undisturbed by whatever the pipeline does next.
 *
 * reliability_score_snapshot is captured AT APPLICATION TIME, so two
 * applicants who applied on different days are compared on a documented,
 * reproducible number rather than whatever the score happens to read when
 * the auction closes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('study_seat_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('reliability_score_snapshot')->default(0);

            $table->string('status')->default('applied'); // App\Enums\SeatApplicationStatus
            $table->unsignedInteger('rank')->nullable();   // final rank once the auction closes

            $table->timestamp('applied_at');
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->unique(['study_id', 'participant_id']);
            $table->index(['study_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_seat_applications');
    }
};