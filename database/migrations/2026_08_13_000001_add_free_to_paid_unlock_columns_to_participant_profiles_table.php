<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_profiles', function (Blueprint $t) {
            if (! Schema::hasColumn('participant_profiles', 'free_studies_completed')) {
                $t->unsignedInteger('free_studies_completed')->default(0)->after('completed_studies_count');
            }
            if (! Schema::hasColumn('participant_profiles', 'paid_studies_unlocked')) {
                $t->boolean('paid_studies_unlocked')->default(false)->after('free_studies_completed');
            }
            if (! Schema::hasColumn('participant_profiles', 'paid_studies_unlocked_at')) {
                $t->timestamp('paid_studies_unlocked_at')->nullable()->after('paid_studies_unlocked');
            }
        });
    }

    public function down(): void
    {
        Schema::table('participant_profiles', function (Blueprint $t) {
            foreach (['free_studies_completed', 'paid_studies_unlocked', 'paid_studies_unlocked_at'] as $c) {
                if (Schema::hasColumn('participant_profiles', $c)) $t->dropColumn($c);
            }
        });
    }
};