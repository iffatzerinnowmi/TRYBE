<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->boolean('notify_studies')->default(true);
            $t->boolean('notify_verify')->default(true);
            $t->boolean('notify_endorse')->default(true);
            $t->boolean('notify_streak')->default(true);
            $t->boolean('notify_credential')->default(true);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('notification_preferences'); }
};
