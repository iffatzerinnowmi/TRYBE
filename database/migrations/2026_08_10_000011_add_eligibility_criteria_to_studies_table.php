<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The listing board needs somewhere to show "who this study is for" —
 * the original studies table didn't have a column for it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('studies', function (Blueprint $t) {
            $t->text('eligibility_criteria')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('studies', function (Blueprint $t) {
            $t->dropColumn('eligibility_criteria');
        });
    }
};
