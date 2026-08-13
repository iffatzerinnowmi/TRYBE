<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who referred whom, and whether it counted yet.
 *
 * The four fields the specification names are all here under those names:
 * referrer id, referred user id, signup date, qualifying completion date.
 *
 * referred_user_id IS UNIQUE. A person can be referred exactly once, ever,
 * by exactly one referrer. That single constraint kills the most obvious
 * abuse: A refers B, then B is re-attributed to C for a second payout.
 *
 * code_used is a frozen copy rather than a foreign key, so historical rows
 * still show which code was used if somebody rotates theirs later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();

            $t->string('status')->default('pending');     // App\Enums\ReferralStatus
            $t->string('code_used', 16);

            $t->timestamp('signed_up_at');
            $t->timestamp('qualified_at')->nullable();

            $t->foreignId('qualifying_study_id')->nullable()
                ->constrained('studies')->nullOnDelete();

            $t->timestamps();

            $t->index(['referrer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
