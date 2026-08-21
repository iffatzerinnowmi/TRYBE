<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('google_calendar_token')->nullable();
            $table->text('google_calendar_refresh_token')->nullable();
            $table->timestamp('google_calendar_token_expires_at')->nullable();
        });

        Schema::table('study_slot_bookings', function (Blueprint $table) {
            $table->string('google_calendar_event_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('study_slot_bookings', function (Blueprint $table) {
            $table->dropColumn('google_calendar_event_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'google_calendar_token',
                'google_calendar_refresh_token',
                'google_calendar_token_expires_at',
            ]);
        });
    }
};
