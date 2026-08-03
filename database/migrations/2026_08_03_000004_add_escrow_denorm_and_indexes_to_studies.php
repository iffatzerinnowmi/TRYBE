<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('studies', function (Blueprint $table): void {
            $table->string('escrow_provider_reference')->nullable()->after('escrow_locked_at');
            $table->string('escrow_status')->nullable()->after('escrow_provider_reference');
            $table->decimal('escrow_amount', 10, 2)->nullable()->after('escrow_status');
            $table->index(['incentive_type', 'escrow_locked_at'], 'studies_incentive_locked_idx');
            $table->index('escrow_status', 'studies_escrow_status_idx');
        });

        Schema::table('study_escrows', function (Blueprint $table): void {
            $table->index(['study_id', 'status'], 'study_escrows_study_status_idx');
            $table->unique('provider_reference', 'study_escrows_provider_ref_unique');
        });

        Schema::create('study_incentive_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('study_id')->constrained('studies')->cascadeOnDelete();
            $table->string('document_type');
            $table->string('storage_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_note')->nullable();
            $table->timestamps();

            $table->index(['study_id', 'document_type'], 'study_incentive_docs_study_type_idx');
        });

        Schema::create('study_incentive_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('study_id')->constrained('studies')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('incentive_type')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('happened_at')->nullable();
            $table->timestamps();

            $table->index(['study_id', 'event_type'], 'study_incentive_audits_study_event_idx');
            $table->index('happened_at', 'study_incentive_audits_happened_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_incentive_audits');
        Schema::dropIfExists('study_incentive_documents');

        Schema::table('study_escrows', function (Blueprint $table): void {
            $table->dropUnique('study_escrows_provider_ref_unique');
            $table->dropIndex('study_escrows_study_status_idx');
        });

        Schema::table('studies', function (Blueprint $table): void {
            $table->dropIndex('studies_incentive_locked_idx');
            $table->dropIndex('studies_escrow_status_idx');
            $table->dropColumn([
                'escrow_provider_reference',
                'escrow_status',
                'escrow_amount',
            ]);
        });
    }
};
