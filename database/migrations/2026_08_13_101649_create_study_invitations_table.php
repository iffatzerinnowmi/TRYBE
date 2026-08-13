<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A researcher's invitation to a matched participant.
 *
 * WHAT THIS REPLACES
 * ------------------
 * Invitation state used to be inferred from a user_notifications row looked
 * up by the string 'Invitation: ' . $study->title. Renaming a study orphaned
 * every invitation for it, the same person could be invited twice, and there
 * was nowhere to record declined / withdrawn / who sent it / when.
 *
 * unique(study_id, participant_id) makes a double invite impossible at the
 * database level rather than by a title-string guess.
 *
 * notification_id is NULLABLE ON PURPOSE. NotificationService returns null
 * when the participant has 'studies' notifications switched off — the
 * invitation must still exist. The old code created the notification AS the
 * invitation, which made such a participant impossible to invite at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained()->cascadeOnDelete();
            $t->foreignId('participant_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('invited_by')->constrained('users')->cascadeOnDelete();

            $t->string('status')->default('pending');          // App\Enums\InvitationStatus
            $t->unsignedTinyInteger('match_score_at_invite')->default(0);
            $t->json('match_reasons')->nullable();

            $t->foreignId('notification_id')->nullable()
                ->constrained('user_notifications')->nullOnDelete();

            $t->timestamp('responded_at')->nullable();
            $t->timestamp('expires_at')->nullable();

            $t->timestamps();

            $t->unique(['study_id', 'participant_id']);
            $t->index(['study_id', 'status']);
            $t->index(['participant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_invitations');
    }
};
