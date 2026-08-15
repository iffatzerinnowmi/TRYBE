<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('verification_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role');
            $t->string('institutional_email')->nullable();
            $t->string('institutional_affiliation')->nullable();
            $t->string('credential_document_path')->nullable();
            $t->string('organization_name')->nullable();
            $t->string('organization_type')->nullable();
            $t->string('registration_documents_path')->nullable();
            $t->string('status')->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('verification_requests'); }
};
