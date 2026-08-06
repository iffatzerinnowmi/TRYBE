<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('study_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('researcher_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('participant_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedTinyInteger('stars');   // 1..5
            $t->text('comment')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('study_reviews'); }
};
