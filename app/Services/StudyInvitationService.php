<?php

namespace App\Services;
use App\Services\FreeToPaidUnlockService;

use App\Contracts\PipelineWriter;
use App\Enums\InvitationStatus;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Study;
use App\Models\StudyInvitation;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;

/**
 * FEATURE — Researcher invitations  (Member 4, graded feature)
 *
 * The only class in the codebase that writes study_invitations.
 *
 * WHAT IT DELIBERATELY DOES NOT WRITE
 * -----------------------------------
 *   user_notifications   -> goes through NotificationService (Member 1), so
 *                           the participant's notification preferences and
 *                           web push are both respected. The old controller
 *                           wrote that table directly and skipped both.
 *
 *   study_participations -> goes through the PipelineWriter contract
 *                           (Member 2). See App\Contracts\PipelineWriter.
 *
 *   studies.status       -> read only. When a study fills up, flipping it to
 *                           FULL is Member 2's call, not ours. We refuse the
 *                           acceptance with a 409 and leave her column alone.
 */
class StudyInvitationService
{
    public function __construct(
        private StudyMatchingService $matching,
        private NotificationService $notifications,
        private PipelineWriter $pipeline,
        private FreeToPaidUnlockService $freeToPaidUnlock,
    ) {}

    // =================================================================
    // Researcher actions
    // =================================================================

    /**
     * Send an invitation.
     *
     * Aborts 403 / 422 / 409 rather than returning a failure object, so the
     * controller stays a thin adapter and bootstrap/app.php renders it as
     * JSON automatically for api/* routes.
     */
    public function invite(Study $study, User $participant, User $researcher): StudyInvitation
    {
        $this->assertOwner($study, $researcher);
        $this->assertInvitable($study, $participant, $researcher);

        $criteria   = $this->matching->criteriaForStudy($study);
        $assessment = $this->matching->assessUserForStudy($participant, $criteria);

        if ($assessment['score'] < $this->matching->strongThreshold()) {
            abort(422, 'That participant is not a strong enough match for this study ('
                . $assessment['score'] . '/' . $this->matching->strongThreshold() . ').');
        }

        $invitation = DB::transaction(function () use ($study, $participant, $researcher, $assessment) {
            $existing = StudyInvitation::where('study_id', $study->id)
                ->where('participant_id', $participant->id)
                ->lockForUpdate()
                ->first();

            // Already live — a double click, or a second researcher session.
            if ($existing && in_array($existing->status, [InvitationStatus::PENDING, InvitationStatus::ACCEPTED], true)) {
                abort(409, $existing->status === InvitationStatus::ACCEPTED
                    ? 'That participant has already accepted an invitation to this study.'
                    : 'That participant already has a pending invitation to this study.');
            }

            $payload = [
                'study_id'              => $study->id,
                'participant_id'        => $participant->id,
                'invited_by'            => $researcher->id,
                'status'                => InvitationStatus::PENDING,
                'match_score_at_invite' => (int) round($assessment['score']),
                'match_reasons'         => $assessment['reasons'],
                'responded_at'          => null,
            ];

            // Declined or withdrawn invitations are re-opened rather than
            // duplicated, so unique(study_id, participant_id) still holds.
            if ($existing) {
                $existing->fill($payload)->save();

                return $existing->fresh();
            }

            return StudyInvitation::create($payload);
        });

        $this->notifyParticipant($invitation, $study, $participant, $researcher);

        return $invitation->fresh();
    }

    /** Researcher takes back a pending invitation. */
    public function withdraw(StudyInvitation $invitation, User $researcher): StudyInvitation
    {
        abort_unless($invitation->invited_by === $researcher->id
            || $invitation->study?->researcher_id === $researcher->id, 403,
            'You may not withdraw this invitation.');

        abort_unless($invitation->isOpen(), 409,
            'Only a pending invitation can be withdrawn. This one is ' . $invitation->status->label() . '.');

        $invitation->update([
            'status'       => InvitationStatus::WITHDRAWN,
            'responded_at' => now(),
        ]);

        return $invitation->fresh();
    }

    // =================================================================
    // Participant actions
    // =================================================================

    /**
     * Accept or decline.
     *
     * Runs inside a transaction with the invitation row locked, because two
     * tabs racing on the last seat of a study is a real scenario.
     */
    public function respond(StudyInvitation $invitation, User $participant, InvitationStatus $to): StudyInvitation
    {
        abort_unless($invitation->participant_id === $participant->id, 403,
            'This invitation does not belong to you.');

        abort_unless(in_array($to->value, InvitationStatus::participantResponses(), true), 422,
            'You can only accept or decline an invitation.');

        return DB::transaction(function () use ($invitation, $participant, $to) {
            $locked = StudyInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            abort_unless($locked->isOpen(), 409,
                'This invitation has already been ' . $locked->status->label() . '.');

            $study = $locked->study;

            if ($to === InvitationStatus::ACCEPTED) {
                $this->assertSeatAvailable($study);
            }

            $locked->update([
                'status'       => $to,
                'responded_at' => now(),
            ]);

            if ($to === InvitationStatus::ACCEPTED) {
                // Member 2's column, written through Member 2's service.
                $this->pipeline->confirm($study, $participant);
            }

            $this->markNotificationRead($locked);
            $this->notifyResearcher($locked, $study, $participant, $to);

            return $locked->fresh();
        });
    }

    // =================================================================
    // Reads
    // =================================================================

    /**
     * Invitations for a study keyed by participant id.
     * Bulk lookup, so the candidates endpoint never runs a query per row.
     */
    public function statusFor(Study $study, iterable $userIds): array
    {
        $ids = collect($userIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return StudyInvitation::where('study_id', $study->id)
            ->whereIn('participant_id', $ids)
            ->get()
            ->keyBy('participant_id')
            ->all();
    }

    /** Whether the pipeline hand-off is wired up yet, for the API to report. */
    public function pipelineAvailable(): bool
    {
        return $this->pipeline->isAvailable();
    }

    /**
     * Seats already taken on a study. Read-only — we never write slots or status.
     *
     * Counts distinct people, because someone can be both confirmed in the
     * pipeline and holding an accepted invitation; that is one seat, not two.
     * Accepted invitations are counted even when the pipeline writer is
     * unavailable, so capacity is still enforced while Member 2's service is
     * outstanding.
     */
    public function seatsTaken(Study $study): int
    {
        $inPipeline = DB::table('study_participations')
            ->where('study_id', $study->id)
            ->whereIn('stage', [
                PipelineStage::CONFIRMED->value,
                PipelineStage::SCHEDULED->value,
                PipelineStage::COMPLETED->value,
                PipelineStage::PAID->value,
            ])
            ->pluck('participant_id');

        $accepted = StudyInvitation::where('study_id', $study->id)
            ->where('status', InvitationStatus::ACCEPTED)
            ->pluck('participant_id');

        return $inPipeline->merge($accepted)->unique()->count();
    }

    // =================================================================
    // Guards
    // =================================================================

    public function assertOwner(Study $study, User $researcher): void
    {
        abort_unless($study->researcher_id === $researcher->id, 403,
            'You may only manage candidates for your own studies.');
    }

    /**
     * Everything that can stop an invitation before scoring.
     *
     * The three cross-module guards each read another member's data and each
     * sit behind a config flag, so they can be switched off until that
     * member's feature ships. Each is a single method returning a reason or
     * null — when the owning member exposes a real service, swap the body and
     * nothing else moves.
     */
    private function assertInvitable(Study $study, User $participant, User $researcher): void
    {
        abort_unless($participant->role === UserRole::PARTICIPANT, 422,
            'Only participant accounts can be invited to a study.');

        abort_unless($participant->participantProfile, 422,
            'That participant has not completed their profile yet.');

        abort_unless($study->status === StudyStatus::OPEN, 422,
            'You can only invite participants to an open study.');

        foreach ([
            $this->paidStudyBlocker($study, $participant),
            $this->auctionModeBlocker($study),
            $this->unverifiedResearcherBlocker($researcher),
        ] as $reason) {
            if ($reason !== null) {
                abort(422, $reason);
            }
        }
    }

  /**
     * Member 3 — free-to-paid unlock rule. A participant who has not
     * completed enough free (volunteer) studies cannot apply to a paid one.
     */
    private function paidStudyBlocker(Study $study, User $participant): ?string
    {
        if (! config('platform.matching.guards.block_paid_study_for_locked_participant')) {
            return null;
        }

        if (! $study->incentive_type?->requiresEscrow()) {
            return null;
        }

        if ($this->unlock->canApplyToStudy($participant, $study)) {
            return null;
        }

        $progress = $this->unlock->progress($participant);

        return 'That participant has not unlocked paid studies yet ('
            . $progress['count'] . ' of ' . $progress['target'] . ' free studies completed).';
    }

    
    /**
     * Member 3 — limited seat auctions award seats by reliability rank, not
     * first-come. An invitation that confirms a seat immediately would defeat
     * that, so invitations are refused while auction mode is on.
     *
     * The flag does not exist on `studies` yet. Reading it defensively means
     * this guard is inert today and becomes live the moment Member 3 adds the
     * column, with no change here.
     */
    private function auctionModeBlocker(Study $study): ?string
    {
        if (! config('platform.matching.guards.block_invite_when_auction_mode')) {
            return null;
        }

        if (! $study->getAttribute('auction_mode')) {
            return null;
        }

        return 'This study is in auction mode — seats are awarded by reliability rank, not by invitation.';
    }

    /**
     * Member 1 — unverified researchers have limited reach. Off by default,
     * because the brief says they may still post; turn it on if the group
     * decides invitations count as reach.
     */
    private function unverifiedResearcherBlocker(User $researcher): ?string
    {
        if (! config('platform.matching.guards.require_verified_researcher')) {
            return null;
        }

        if ($researcher->verification_status === VerificationStatus::VERIFIED) {
            return null;
        }

        return 'Your researcher account must be verified before you can invite participants.';
    }

    /** Read slots; never write them. */
    private function assertSeatAvailable(Study $study): void
    {
        $slots = (int) $study->slots;

        if ($slots <= 0) {
            return;   // unlimited / not tracked
        }

        abort_if($this->seatsTaken($study) >= $slots, 409,
            'This study is already full.');
    }

    // =================================================================
    // Notifications — always via NotificationService
    // =================================================================

    /**
     * The notification is best-effort; the invitation is authoritative.
     *
     * NotificationService returns null when the participant has 'studies'
     * notifications switched off. The old implementation stored the
     * invitation AS the notification, which made such a participant
     * impossible to invite at all.
     */
    private function notifyParticipant(
        StudyInvitation $invitation,
        Study $study,
        User $participant,
        User $researcher
    ): void {
        $notification = $this->notifications->send(
            $participant,
            'studies',
            'Invitation: ' . $study->title,
            $researcher->name . ' invited you to "' . $study->title
                . '" because your profile is a strong match ('
                . $invitation->match_score_at_invite . '/100).',
            route('participant.invitations')
        );

        if ($notification) {
            $invitation->update(['notification_id' => $notification->id]);
        }
    }

    private function notifyResearcher(
        StudyInvitation $invitation,
        Study $study,
        User $participant,
        InvitationStatus $to
    ): void {
        $researcher = $invitation->inviter;

        if (! $researcher) {
            return;
        }

        $this->notifications->send(
            $researcher,
            'studies',
            $to === InvitationStatus::ACCEPTED
                ? 'Invitation accepted: ' . $study->title
                : 'Invitation declined: ' . $study->title,
            $participant->name . ' ' . $to->label() . ' your invitation to "' . $study->title . '".',
            route('researcher.dashboard')
        );
    }

    private function markNotificationRead(StudyInvitation $invitation): void
    {
        if (! $invitation->notification_id) {
            return;
        }

        // Targeted by key rather than through the relation, so this is one
        // unambiguous UPDATE on one row.
        UserNotification::whereKey($invitation->notification_id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
