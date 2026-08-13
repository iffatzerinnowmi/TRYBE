<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free paid-post credits earned by researchers who refer other researchers.
 *
 * A LEDGER, NOT A BALANCE COLUMN.
 *
 * A running total on researcher_profiles would drift, could not be audited,
 * and would be a column on a table this feature does not own. The balance is
 * SUM(delta), which is always right and always explainable: +1 earned,
 * -1 spent.
 *
 * NOTE: this table currently only ever EARNS. The thing that would spend a
 * credit — free-tier researcher post limits — is Member 3's tier system and
 * does not exist yet. When it does, it should write a -1 row here rather
 * than tracking its own count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('researcher_post_credits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            $t->integer('delta');       // +1 earned, -1 spent
            $t->string('reason');       // App\Enums\PostCreditReason

            $t->foreignId('referral_reward_id')->nullable()
                ->constrained('referral_rewards')->nullOnDelete();

            $t->timestamps();

            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('researcher_post_credits');
    }
};
