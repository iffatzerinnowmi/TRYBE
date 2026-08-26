<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_profiles', function (Blueprint $t) {
            if (! Schema::hasColumn('participant_profiles', 'total_volunteer_count')) {
                $t->unsignedInteger('total_volunteer_count')->default(0)->after('paid_studies_unlocked_at');
            }
            if (! Schema::hasColumn('participant_profiles', 'volunteer_progress')) {
                $t->unsignedTinyInteger('volunteer_progress')->default(0)->after('total_volunteer_count');
            }
            if (! Schema::hasColumn('participant_profiles', 'paid_used')) {
                $t->unsignedTinyInteger('paid_used')->default(0)->after('volunteer_progress');
            }
        });
    }

    public function down(): void
    {
        Schema::table('participant_profiles', function (Blueprint $t) {
            foreach (['total_volunteer_count', 'volunteer_progress', 'paid_used'] as $c) {
                if (Schema::hasColumn('participant_profiles', $c)) $t->dropColumn($c);
            }
        });
    }
};