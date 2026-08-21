<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "I'm interested in this one."  (Member 4)
 *
 * The unique index is the whole concurrency story: saving twice is impossible
 * at the database level, so the toggle is idempotent without an exists()
 * check that two fast clicks could race past.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_saves', function (Blueprint $t) {
            $t->id();

            $t->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
            | Reserved, no interface yet.
            |
            | The plan proposed a "looking for a team" signal — one boolean
            | that lets the board say "4 other people are looking for a team
            | for this", which is the platform's whole premise. It was cut
            | from this build for time, not on merit.
            |
            | The column ships now because adding one later means another
            | migration on a shared database, and the group has agreed to
            | avoid those. It defaults to false and nothing reads it yet.
            */
            $t->boolean('looking_for_team')->default(false);

            $t->timestamps();

            $t->unique(['competition_id', 'user_id']);

            // "My saved competitions, soonest deadline first" joins on this.
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_saves');
    }
};
