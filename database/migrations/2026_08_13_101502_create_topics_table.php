<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FEATURE — Smart Participant Matching  (Member 4)
 *
 * The shared research-topic vocabulary. Studies are tagged with topics
 * (study_topic) and participants declare an interest in them
 * (participant_topics); the matching engine scores the overlap.
 *
 * The slug is the join key, so a display name can be corrected without
 * silently breaking every match that referenced it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
