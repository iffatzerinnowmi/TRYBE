<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Topics a participant has declared an interest in.
 *
 * Deliberately NOT a column on participant_profiles: that table and its
 * free-text `interests` field belong to Member 1's profile builder, and
 * free text cannot be joined or indexed.
 *
 * Only DECLARED interests live here. Topics derived from studies the
 * participant actually completed are computed live from study_topic, so
 * there is no cache to invalidate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_topics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['user_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_topics');
    }
};
