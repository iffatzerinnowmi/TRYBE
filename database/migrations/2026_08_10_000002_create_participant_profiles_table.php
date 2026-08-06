<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('participant_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // profile builder
            $t->unsignedTinyInteger('age')->nullable();
            $t->string('gender')->nullable();
            $t->string('occupation')->nullable();
            $t->text('health_background')->nullable();
            $t->text('interests')->nullable();
            $t->text('skills')->nullable();
            $t->string('linkedin')->nullable();
            $t->string('github')->nullable();
            // reliability (three weighted factors + cached score)
            $t->unsignedTinyInteger('rel_attendance')->default(0);
            $t->unsignedTinyInteger('rel_completion')->default(0);
            $t->unsignedTinyInteger('rel_reviews')->default(0);
            $t->unsignedTinyInteger('reliability_score')->default(0);
            // credential
            $t->unsignedInteger('completed_studies_count')->default(0);
            $t->string('credential_level')->default('none');
            // streaks
            $t->unsignedInteger('current_streak_weeks')->default(0);
            $t->unsignedInteger('longest_streak_weeks')->default(0);
            $t->date('last_active_week')->nullable();
            // endorsements
            $t->unsignedInteger('endorsement_count')->default(0);
            $t->boolean('is_verified_participant')->default(false);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('participant_profiles'); }
};
