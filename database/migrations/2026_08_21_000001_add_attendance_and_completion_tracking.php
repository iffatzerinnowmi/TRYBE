<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('study_participations', function (Blueprint $table) {
            $table->string('attendance_status')->default('pending')->after('completed_at');
        });

        Schema::table('studies', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('studies', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });

        Schema::table('study_participations', function (Blueprint $table) {
            $table->dropColumn('attendance_status');
        });
    }
};
