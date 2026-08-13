<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger of rewards actually granted.
 *
 * unique(user_id, type, milestone) IS THE LOAD-BEARING CONSTRAINT OF THE
 * WHOLE FEATURE. It is what lets ReferralService::sync() run as often as it
 * likes — on every page load, on every API call, from a scheduled command —
 * and still grant each reward exactly once.
 *
 * Without it, "rewards are applied automatically" quietly becomes "rewards
 * are applied repeatedly". Idempotency is enforced by the database, not by
 * an if-statement somebody might delete.
 *
 * granted_credential_level records WHAT was awarded. It is deliberately
 * stored as a level, not as "+1 tier": re-evaluating a relative bonus later
 * against a changed ladder would silently move an already-granted reward.
 *
 * Nothing here writes participant_profiles.credential_level — that column
 * has one writer, CredentialService. See ReferralService::grantedLevel().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_rewards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            $t->string('type');                    // App\Enums\ReferralRewardType
            $t->unsignedInteger('milestone');      // 3, 6, 9 … which threshold fired

            $t->string('granted_credential_level')->nullable();   // App\Enums\CredentialLevel
            $t->unsignedInteger('post_credits')->default(0);

            $t->foreignId('triggered_by_referral_id')->nullable()
                ->constrained('referrals')->nullOnDelete();

            $t->timestamps();

            $t->unique(['user_id', 'type', 'milestone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
