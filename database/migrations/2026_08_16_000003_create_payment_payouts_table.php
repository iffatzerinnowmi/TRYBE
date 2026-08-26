<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_payouts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('study_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('users')->cascadeOnDelete();

            // Enforced unique at the DB level so
            // PaymentEscrowService::createPayoutForCompletion() is safe to
            // call more than once for the same participation (see its
            // docblock — it relies on this constraint for idempotency).
            $table->foreignId('study_participation_id')->unique()->constrained()->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('method');
            $table->string('status')->default('pending');

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('last_error')->nullable();
            $table->string('gateway_reference')->nullable();

            $table->string('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('confirmation_deadline_at')->nullable();

            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            // Backs PaymentEscrowController's "active studies with escrows"
            // query and PaymentPayout::scopeRetryable() / scopeDueForAutoConfirm().
            $table->index(['study_id', 'status']);
            $table->index(['status', 'confirmation_deadline_at']);
            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_payouts');
    }
};