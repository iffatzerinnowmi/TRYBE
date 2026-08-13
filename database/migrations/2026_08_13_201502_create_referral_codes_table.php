<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FEATURE — Referral system  (Member 4)
 *
 * One shareable code per user.
 *
 * WHY A TABLE AND NOT A COLUMN ON users
 * -------------------------------------
 * Team rules: do not add a column to the users table without asking first,
 * and two migrations both touching users will fail. A 1:1 table with a
 * unique user_id is the same thing without the conversation, and without
 * risking a collision with somebody else's users migration.
 *
 * Codes are created lazily on first request rather than at signup, so no
 * backfill is needed for the accounts already on the shared database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('code', 16)->unique();

            // Link opened, before any signup. Display only — grants nothing,
            // so there is no incentive to farm it.
            $t->unsignedInteger('visits')->default(0);

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};
