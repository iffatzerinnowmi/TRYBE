<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('studies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('researcher_id')->constrained('users')->cascadeOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('category')->nullable();
            $t->string('method')->default('online');            // online | in_person
            $t->unsignedInteger('duration_minutes')->nullable();
            $t->string('incentive_type')->default('volunteer'); // App\Enums\IncentiveType
            $t->decimal('compensation_amount', 8, 2)->default(0);
            $t->unsignedInteger('slots')->default(0);
            $t->date('deadline')->nullable();
            $t->string('status')->default('open');              // App\Enums\StudyStatus
            $t->unsignedInteger('participants_count')->default(0); // anonymised count for public profile
            // IRB / ethics (Member 1)
            $t->string('irb_document_path')->nullable();
            $t->boolean('irb_flagged')->default(true);          // true until a document is attached
            $t->string('irb_board')->nullable();
            $t->string('irb_ref')->nullable();
            $t->string('irb_valid_until')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('studies'); }
};
