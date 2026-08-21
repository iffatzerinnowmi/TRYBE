<?php

namespace App\Services;

use App\Enums\CompletionClaimStatus;
use App\Enums\PipelineStage;
use App\Enums\UserRole;
use App\Models\NotificationPreference;
use App\Models\Study;
use App\Models\StudyCompletionClaim;
use App\Models\StudyParticipation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FEATURE — Complete a Study, participant side  (Member 4)
 *
 * THE ONE RULE THIS CLASS EXISTS TO ENFORCE
 * -----------------------------------------
 * A participant CANNOT mark their own study complete.
 *
 * study_participations.stage = 'completed' is not a label. It drives:
 *
 *     CredentialService            Bronze -> Gold -> Expert
 *     KarmaService                 +10 karma per completed study
 *     FreeToPaidUnlockService      progress toward paid-study access
 *     ReferralService              whether a referred user counts as qualified
 *     StudyMatchingService         topic history and reliability
 *
 * Five consumers, all already wired. A self-service completion button would
 * be a self-service credential, karma and paid-access printer.
 *
 * So this class writes a CLAIM — "the participant says they submitted the
 * form" — timestamps it, tells the researcher, and stops. The researcher
 * remains the verifier, and their confirmation goes through Member 2's
 * pipeline, not through here.
 *
 * WHAT WE CANNOT DO, STATED PLAINLY
 * ---------------------------------
 * We cannot verify a Google Form submission. Forms provides no callback
 * without an Apps Script webhook, and even then the response is not tied to
 * our user id. opened_form_at records that they followed the link; it is
 * evidence, not proof, and it is deliberately not a precondition.
 */
class StudyCompletionService
{
    public function __construct(private NotificationService $notifications) {}

    /** Reason code → HTTP status, in one place so the controller maps nothing. */
    public const REASON_STATUS = [
        'not_in_study'      => 403,
        'not_started'       => 422,
        'already_completed' => 409,
        'not_eligible'      => 422,
        'already_submitted' => 409,
    ];

    public const REASON_MESSAGE = [
        'not_in_study'      => 'You are not part of this study.',
        'not_started'       => 'The researcher has not confirmed you for this study yet.',
        'already_completed' => 'This study is already marked complete.',
        'not_eligible'      => 'This study is no longer open to you.',
        'already_submitted' => 'You have already told us you submitted the form.',
    ];

    public const REASON_LABEL = [
        'not_in_study'      => 'Not in this study',
        'not_started'       => 'Not confirmed yet',
        'already_completed' => 'Completed ✓',
        'not_eligible'      => 'Unavailable',
        'already_submitted' => 'Submitted ✓',
    ];

    // =================================================================
    // Configuration
    // =================================================================

    public function formUrl(): string
    {
        return (string) config('platform.completion.form_url');
    }

    public function noteMax(): int
    {
        return (int) config('platform.completion.note_max');
    }

    /**
     * Stages a study can be claimed complete from.
     *
     * In config because "should `scheduled` count, or only `confirmed`?" is a
     * product question, not a code one — and an examiner asking to change it
     * should be a one-line edit.
     */
    public function claimableStages(): array
    {
        return array_map(
            fn ($s) => PipelineStage::from($s),
            (array) config('platform.completion.claimable_stages', [])
        );
    }

    // =================================================================
    // The decision
    // =================================================================

    /**
     * Why this participant may NOT claim completion, or null if they may.
     *
     * Pure — no writes, no exceptions, no HTTP. This is what makes the rules
     * testable before a controller exists, and it is the single source the
     * button, the endpoint and the tests all read.
     */
    public function blockingReason(User $participant, Study $study): ?string
    {
        $participation = $this->participationFor($participant, $study);

        if (! $participation) {
            return 'not_in_study';
        }

        $stage = $participation->stage;

        if (in_array($stage, [PipelineStage::COMPLETED, PipelineStage::PAID], true)) {
            return 'already_completed';
        }

        if (in_array($stage, [PipelineStage::REJECTED, PipelineStage::NO_SHOW], true)) {
            return 'not_eligible';
        }

        if (! in_array($stage, $this->claimableStages(), true)) {
            return 'not_started';
        }

        $claim = $this->claimFor($participant, $study);

        if ($claim && ! $claim->status->allowsResubmit()) {
            return 'already_submitted';
        }

        return null;
    }

    public function canClaim(User $participant, Study $study): bool
    {
        return $this->blockingReason($participant, $study) === null;
    }

    /** Everything the button and the modal need, so the JavaScript decides nothing. */
    public function status(User $participant, Study $study): array
    {
        $reason        = $this->blockingReason($participant, $study);
        $claim         = $this->claimFor($participant, $study);
        $participation = $this->participationFor($participant, $study);

        return [
            'study_id'     => $study->id,
            'study_title'  => $study->title,
            'stage'        => $participation?->stage?->value,
            'can_complete' => $reason === null,
            'reason'       => $reason,
            'message'      => $reason ? self::REASON_MESSAGE[$reason] : null,
            'label'        => $reason ? self::REASON_LABEL[$reason] : 'Complete study',

            // Only handed out when they are actually allowed to act on it.
            'form_url'     => $reason === null ? $this->formUrl() : null,
            'note_max'     => $this->noteMax(),

            'claim'        => $claim?->toPayload(),

            // A rejected claim can be sent again, and the UI should say so
            // rather than silently offering the button a second time.
            'resubmitting' => (bool) ($claim && $claim->status->allowsResubmit()),
        ];
    }

    // =================================================================
    // Writes
    // =================================================================

    /**
     * Record the claim and tell the researcher.
     *
     * The whole thing is one transaction: if the notification blows up, the
     * claim is not left half-written. The notification itself is additionally
     * wrapped, because a dead notification channel must never stop a
     * participant finishing a study.
     */
    public function claim(User $participant, Study $study, ?string $note = null): StudyCompletionClaim
    {
        $reason = $this->blockingReason($participant, $study);

        if ($reason !== null) {
            abort(self::REASON_STATUS[$reason], self::REASON_MESSAGE[$reason]);
        }

        $claim = DB::transaction(function () use ($participant, $study, $note) {
            /*
            | updateOrCreate on the unique pair, so a re-submitted rejected
            | claim reuses its row. Re-claiming clears the previous review:
            | this is a new statement, and leaving the old reviewed_at in
            | place would make it look like the researcher had already seen it.
            */
            $claim = StudyCompletionClaim::updateOrCreate(
                ['study_id' => $study->id, 'participant_id' => $participant->id],
                [
                    'status'       => CompletionClaimStatus::SUBMITTED,
                    'form_url'     => $this->formUrl(),
                    'submitted_at' => now(),
                    'note'         => $note ? mb_substr($note, 0, $this->noteMax()) : null,
                    'reviewed_at'  => null,
                    'reviewed_by'  => null,
                ]
            );

            return $claim->fresh();
        });

        $this->notifyResearcher($participant, $study, $claim);

        return $claim;
    }

    /**
     * Stamp that they followed the link.
     *
     * Deliberately separate from claim(), and deliberately not required
     * before it. Gating the confirm button on "you must open the form first"
     * is trivially defeated and punishes anyone who opened the form
     * yesterday. We record it as evidence instead of pretending it is a gate.
     */
    public function markFormOpened(User $participant, Study $study): void
    {
        $reason = $this->blockingReason($participant, $study);

        // Only meaningful while they could actually claim.
        if ($reason !== null && $reason !== 'already_submitted') {
            return;
        }

        StudyCompletionClaim::updateOrCreate(
            ['study_id' => $study->id, 'participant_id' => $participant->id],
            [
                'status'         => CompletionClaimStatus::SUBMITTED,
                'form_url'       => $this->formUrl(),
                'opened_form_at' => now(),
            ]
        );
    }

    /**
     * Called by the RESEARCHER side when a claim is accepted or rejected.
     *
     * This is the seam for Member 2. It writes only my column: it does NOT
     * touch study_participations.stage. Her completeStudy() sets the stage;
     * this records that the claim was dealt with, so the participant sees
     * closure rather than a claim sitting at "awaiting confirmation" for ever.
     */
    public function markReviewed(
        StudyCompletionClaim $claim,
        User $researcher,
        CompletionClaimStatus $status
    ): StudyCompletionClaim {
        abort_if(
            $status === CompletionClaimStatus::SUBMITTED,
            500,
            'markReviewed() expects accepted or rejected.'
        );

        $claim->update([
            'status'      => $status,
            'reviewed_at' => now(),
            'reviewed_by' => $researcher->id,
        ]);

        return $claim->fresh();
    }

    // =================================================================
    // Reads
    // =================================================================

    /** The participant's own claims. */
    public function claimsFor(User $participant): Collection
    {
        return StudyCompletionClaim::with('study:id,title,status,incentive_type')
            ->where('participant_id', $participant->id)
            ->latest('id')
            ->get();
    }

    /**
     * Claims on one study, for its researcher.
     *
     * This exists so Member 2's pipeline can show a "claimed complete" marker
     * without joining my table. She calls it when she is ready; until then it
     * changes nothing of hers.
     */
    public function claimsForStudy(Study $study): Collection
    {
        return StudyCompletionClaim::with('participant:id,name')
            ->where('study_id', $study->id)
            ->latest('submitted_at')
            ->get();
    }

    // =================================================================

    private function participationFor(User $participant, Study $study): ?StudyParticipation
    {
        return StudyParticipation::where('study_id', $study->id)
            ->where('participant_id', $participant->id)
            ->first();
    }

    private function claimFor(User $participant, Study $study): ?StudyCompletionClaim
    {
        return StudyCompletionClaim::where('study_id', $study->id)
            ->where('participant_id', $participant->id)
            ->first();
    }

    /**
     * Tell the study's researcher.
     *
     * NOTE ON THE TYPE. NotificationService gates every type on a column in
     * notification_preferences, which is Member 1's table. There is no
     * pipeline / completion type yet, so this borrows the closest existing
     * one via config rather than adding a column to her schema. When she adds
     * a proper type, this becomes a one-line .env change.
     *
     * Failure here is swallowed on purpose: a participant must not be blocked
     * from finishing a study because a notification channel is down.
     */
    private function notifyResearcher(User $participant, Study $study, StudyCompletionClaim $claim): void
    {
        try {
            $researcher = $study->researcher;

            if (! $researcher) {
                return;
            }

            $this->ensurePreferences($researcher);

            $this->notifications->send(
                $researcher,
                (string) config('platform.completion.notification_type', 'studies'),
                'A participant completed a study',
                $participant->name . ' says they submitted the form for "' . $study->title . '".',
                route('studies.show', $study)
            );
        } catch (\Throwable $e) {
            Log::warning('Completion-claim notification failed.', [
                'claim_id' => $claim->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Make sure the user has a notification_preferences row, hydrated.
     *
     * WHY THIS IS NEEDED — a sharp edge in NotificationService, not a choice.
     *
     * wants() does:
     *
     *     $prefs = $user->notificationPreference
     *           ?? NotificationPreference::firstOrCreate(['user_id' => $user->id]);
     *     return (bool) $prefs->{$column};
     *
     * firstOrCreate() inserts a row with ONLY user_id. Every notify_* default
     * lives on the database column, not on the model, so the object it hands
     * back has notify_studies = NULL — which casts to false. The user is then
     * treated as having switched every notification off, and send() silently
     * returns null.
     *
     * This matters beyond the tests: DatabaseSeeder creates a preferences row
     * for exactly one user (Iffat). No researcher has one. So without this,
     * a completion claim would notify nobody, in production, with no error.
     *
     * We create the row and RE-READ it, so the database defaults are actually
     * loaded. The row was going to be created by wants() anyway — this only
     * ensures it is read back rather than used blind.
     *
     * The real fix belongs in Member 1's code: either $attributes defaults on
     * the NotificationPreference model, or ->fresh() after firstOrCreate in
     * wants(). Raised in the handoff; this stays until then.
     */
    private function ensurePreferences(User $user): void
    {
        if ($user->notificationPreference) {
            return;
        }

        NotificationPreference::firstOrCreate(['user_id' => $user->id]);

        $user->unsetRelation('notificationPreference');
        $user->load('notificationPreference');
    }

    /** Guard used by the controller. Here so the rules live together. */
    public function assertParticipant(?User $user): User
    {
        abort_unless(
            $user && $user->role === UserRole::PARTICIPANT,
            403,
            'Only participants can complete studies.'
        );

        abort_if(
            ! $user->participantProfile,
            404,
            'No participant profile found for this account.'
        );

        return $user;
    }
}
