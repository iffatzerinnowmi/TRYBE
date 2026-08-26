<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('researcher_profiles', function (Blueprint $table) {
            // Nullable: a researcher who has never posted a paid study
            // may never set these. PaymentEscrowService::payoutMethodFor()
            // falls back to PayoutMethod::BANK_TRANSFER when null.
            $table->string('payout_method')->nullable();
            $table->json('payout_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('researcher_profiles', function (Blueprint $table) {
            $table->dropColumn(['payout_method', 'payout_details']);
        });
    }
};
