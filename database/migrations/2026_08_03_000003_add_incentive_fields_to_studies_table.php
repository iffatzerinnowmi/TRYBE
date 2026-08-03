<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('studies', function (Blueprint $table): void {
            $table->string('incentive_type')->default('volunteer_unpaid')->after('description');
            $table->decimal('incentive_amount', 10, 2)->nullable()->after('incentive_type');
            $table->string('currency', 3)->default('USD')->after('incentive_amount');
            $table->string('course_credit_document_path')->nullable()->after('currency');
            $table->string('course_credit_document_name')->nullable()->after('course_credit_document_path');
            $table->timestamp('escrow_locked_at')->nullable()->after('course_credit_document_name');
        });
    }

    public function down(): void
    {
        Schema::table('studies', function (Blueprint $table): void {
            $table->dropColumn([
                'incentive_type',
                'incentive_amount',
                'currency',
                'course_credit_document_path',
                'course_credit_document_name',
                'escrow_locked_at',
            ]);
        });
    }
};
