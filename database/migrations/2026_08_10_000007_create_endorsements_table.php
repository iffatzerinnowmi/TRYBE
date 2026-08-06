<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('endorsements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('participant_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('researcher_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('study_id')->nullable();
            $t->json('tags');
            $t->timestamps();
            $t->unique(['participant_id','researcher_id','study_id'], 'one_endorsement_per_session');
        });
    }
    public function down(): void { Schema::dropIfExists('endorsements'); }
};
