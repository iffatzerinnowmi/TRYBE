<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every notification TRYBE sends is written here first, then pushed.
 *
 * Storing it means the bell menu still has a history even if the browser
 * refused the push, the user was offline, or push was never enabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Matches the notification_preferences columns:
            // studies | verify | endorse | streak | credential
            $t->string('type');

            $t->string('icon', 8)->default('🔔');
            $t->string('title');
            $t->text('body');
            $t->string('url')->nullable();

            // Was it actually delivered to a browser, and did that work?
            $t->boolean('pushed')->default(false);
            $t->string('push_result')->nullable();

            $t->timestamp('read_at')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
