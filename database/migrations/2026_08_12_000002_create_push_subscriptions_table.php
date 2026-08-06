<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per browser that agreed to receive push notifications.
 *
 * The endpoint is a URL the browser vendor gave us (Chrome, Firefox and Edge
 * each run their own push service). The two keys are what let us encrypt the
 * message so only that browser can read it.
 *
 * A person can have several rows — laptop Chrome, phone Chrome, and so on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            $t->text('endpoint');

            // MySQL cannot put a unique index on a TEXT column, so we index a
            // 64-character SHA-256 of the endpoint instead. Same guarantee,
            // and it works on both MySQL and SQLite.
            $t->char('endpoint_hash', 64);
            $t->string('public_key');      // p256dh
            $t->string('auth_token');      // auth
            $t->string('content_encoding')->default('aesgcm');

            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();

            // Two browsers never share an endpoint, so this stops duplicates.
            $t->unique(['user_id', 'endpoint_hash'], 'one_subscription_per_browser');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
