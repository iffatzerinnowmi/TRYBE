<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('study_participations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained()->cascadeOnDelete();
            $t->foreignId('participant_id')->constrained('users')->cascadeOnDelete();
            $t->string('stage')->default('applied');   // App\Enums\PipelineStage
            $t->timestamp('completed_at')->nullable();  // set when stage = completed
            $t->timestamps();
            $t->unique(['study_id','participant_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('study_participations'); }
};
