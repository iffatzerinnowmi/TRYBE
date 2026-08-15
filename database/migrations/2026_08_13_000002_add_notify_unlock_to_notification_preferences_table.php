<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $t) {
            if (! Schema::hasColumn('notification_preferences', 'notify_unlock')) {
                $t->boolean('notify_unlock')->default(true)->after('notify_credential');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $t) {
            if (Schema::hasColumn('notification_preferences', 'notify_unlock')) $t->dropColumn('notify_unlock');
        });
    }
};