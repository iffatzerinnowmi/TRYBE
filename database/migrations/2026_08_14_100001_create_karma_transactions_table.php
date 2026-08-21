<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger of every karma credit earned or spent. One row per event —
 * source, amount, timestamp — same shape as referral_rewards: the balance
 * is never a stored counter, it is always SUM(amount) over this table.
 *
 * `amount` is signed: positive rows are earns, negative rows are spends.
 * KarmaService is the ONLY writer to this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karma_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            $t->integer('amount');      // signed: +earn / -spend
            $t->string('source');       // App\Enums\KarmaSource
            $t->string('description')->nullable();

            $t->timestamps();

            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karma_transactions');
    }
};