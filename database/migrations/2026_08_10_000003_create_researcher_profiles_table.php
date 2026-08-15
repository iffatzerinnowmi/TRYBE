<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('researcher_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('title')->nullable();
            $t->string('institution')->nullable();
            $t->string('department')->nullable();
            $t->text('bio')->nullable();
            $t->string('linkedin')->nullable();
            $t->string('institutional_email')->nullable();
            $t->text('research_areas')->nullable();            // comma-separated
            // public-profile aggregates (denormalised for display)
            $t->decimal('avg_rating', 3, 2)->default(0);
            $t->unsignedInteger('ratings_count')->default(0);
            $t->json('rating_distribution')->nullable();       // {"5":86,"4":11,...} percentages
            $t->unsignedInteger('followers_count')->default(0);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('researcher_profiles'); }
};
