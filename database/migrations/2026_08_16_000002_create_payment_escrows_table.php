<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_escrows', function (Blueprint $table) {
            $table->id();

            // One escrow per study — PaymentEscrowService::lockFunds()
            // uses updateOrCreate(['study_id' => ...], ...), so this must
            // stay unique.
            $table->foreignId('study_id')->unique()->constrained()->cascadeOnDelete();

            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('released_amount', 10, 2)->default(0);
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->decimal('fee_charged', 10, 2)->default(0);
            $table->unsignedTinyInteger('fee_percentage')->default(10);

            $table->string('status')->default('locked');

            $table->timestamp('locked_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_escrows');
    }
};
