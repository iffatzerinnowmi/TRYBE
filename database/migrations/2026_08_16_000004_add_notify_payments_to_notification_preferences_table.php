<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            // Read/written by NotificationService when
            // PaymentEscrowService::notifyResearcher() /
            // notifyParticipant() send a 'payments' category notification.
            $table->boolean('notify_payments')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('notify_payments');
        });
    }
};