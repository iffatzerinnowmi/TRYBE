<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('follows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('researcher_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['follower_id','researcher_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('follows'); }
};
