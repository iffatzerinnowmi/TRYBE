<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which topics a study is about.
 *
 * This pivot is what makes "the topics of a participant's past studies"
 * answerable: study_participations (completed) -> studies -> study_topic.
 * Nothing is cached, so nothing can go stale when a participation completes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_topic', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained()->cascadeOnDelete();
            $t->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['study_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_topic');
    }
};
