<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FEATURE — Incentive Variety Settings
 *
 * Cash / Voucher listings must have their compensation locked into escrow
 * before they go live, so we need somewhere to record that the lock
 * happened (and when). Course Credit listings must prove the credit is
 * real, so we need somewhere to store the institution's supporting
 * document — separate from the IRB / ethics document (that's Section 1's
 * feature, don't touch irb_document_path).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studies', function (Blueprint $t) {
            if (! Schema::hasColumn('studies', 'escrow_locked')) {
                $t->boolean('escrow_locked')->default(false)->after('compensation_amount');
            }
            if (! Schema::hasColumn('studies', 'escrow_locked_at')) {
                $t->timestamp('escrow_locked_at')->nullable()->after('escrow_locked');
            }
            if (! Schema::hasColumn('studies', 'course_credit_institution')) {
                $t->string('course_credit_institution')->nullable()->after('escrow_locked_at');
            }
            if (! Schema::hasColumn('studies', 'course_credit_document_path')) {
                $t->string('course_credit_document_path')->nullable()->after('course_credit_institution');
            }
        });
    }

    public function down(): void
    {
        Schema::table('studies', function (Blueprint $t) {
            $t->dropColumn([
                'escrow_locked',
                'escrow_locked_at',
                'course_credit_institution',
                'course_credit_document_path',
            ]);
        });
    }
};