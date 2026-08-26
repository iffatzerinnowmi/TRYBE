<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_payouts', function (Blueprint $table) {
            // Holds bKash's paymentID between checkout/create and the
            // callback that executes it — needed because unlike the
            // simulated gateway, a real bKash payment spans two separate
            // HTTP requests (redirect out, then callback in).
            $table->string('gateway_payment_id')->nullable()->after('gateway_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payment_payouts', function (Blueprint $table) {
            $table->dropColumn('gateway_payment_id');
        });
    }
};