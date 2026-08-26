<?php

namespace App\Services;

use App\Enums\IncentiveType;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * FEATURE — Apply to Study, VOLUNTEER studies only  (Member 4)
 *
 * WHAT AN APPLICATION IS
 * ----------------------
 * A row in study_participations at stage `applied`. That is all. The column
 * has always defaulted to 'applied', so creating one is the table's designed
 * entry point rather than an intervention in anyone's state machine.
 *
 * This feature therefore adds NO table and NO column. An earlier draft of the
 * plan invented two, and both existed only to track the paid-study allowance —
 * which is Member 3's, not mine.
 *
 * WHAT THIS CLASS DELIBERATELY DOES NOT DO
 * ----------------------------------------
 * 1. It never CHANGES a stage. `applied → screened → confirmed` belongs to
 *    Member 2's PipelineApiController. Single-writer is preserved where it
 *    actually matters: on transitions.
 *
 * 2. It knows nothing about karma, the five-study unlock, the two-application
 *    cycle or escrow. Paid studies are refused outright here (§ paidGate) and
 *    that refusal is the single line that changes when Member 3's eligibility
 *    surface lands.
 *
 * 3. It does not add apply() to the PipelineWriter interface. Adding a method
 *    to a PHP interface makes every implementing class fatal until it
 *    implements it, and PipelineService is Member 2's file. That would break at
 *    merge time with a fatal error rather than a merge conflict, so nothing
 *    would warn anybody.
 */
class StudyApplicationService
{
    /**
     * Seat counting is delegated, not reimplemented.
     *
     * StudyInvitationService already defines "how many seats are taken" and
     * the invitation flow enforces capacity with it. Two definitions of full
     * would eventually disagree, and the bug would look like a phantom seat.
     */
    public function __construct(
        private StudyInvitationService $invitations,

        /*
        | Member 3's service. It owns the ENTIRE paid-study rule: the
        | volunteer progress bar, the karma cost, the two-application cycle
        | and the reset. This class never reimplements any of it — for a paid
        | study it asks eligibility() and then hands the write to
        | applyToPaidStudy(), which creates the participation, spends the
        | karma and moves the counter inside one transaction.
        |
        | Injected rather than resolved inline so the dependency is visible in
        | the constructor and can be faked in a test.
        */
        private FreeToPaidUnlockService $unlock,
    ) {}

    /**
     * Has Member 3's paid-application API landed on this branch yet?
     *
     * The methods below live on `feature/payment-escrow`, which is not merged
     * everywhere at the time of writing. Rather than block, this class checks
     * for them and falls back to refusing paid studies exactly as it did
     * before — so the same code is correct on a branch that has the merge and
     * one that does not, and starts working the moment the merge lands with
     * no edit here.
     *
     * Same seam pattern as PipelineWriter: depend on the interface, degrade
     * gracefully when the implementation is absent.
     */
    private function paidApplicationsAvailable(): bool
    {
        return method_exists($this->unlock, 'eligibility')
            && method_exists($this->unlock, 'applyToPaidStudy');
    }

    /**
     * Reason code → HTTP status. Kept in one place so the controller does no
     * mapping of its own and the tests can assert on the code rather than on
     * a sentence somebody might reword.
     */
    public const REASON_STATUS = [
        'paid_not_yet_available' => 422,
        'paid_no_slots'          => 403,
        'paid_locked'            => 403,
        'not_open'               => 422,
        'deadline_passed'        => 422,
        'full'                   => 422,
        'already_applied'        => 409,
        'already_participating'  => 409,
    ];

    public const REASON_MESSAGE = [
        'paid_not_yet_available' => 'Paid studies are not open for applications yet.',
        'paid_no_slots'          => 'You have used both paid applications for this cycle. '
                                    . 'Complete more volunteer studies to unlock two more.',
        'paid_locked'            => 'Complete more volunteer studies, or earn more Karma, to apply.',
        'not_open'               => 'This study is not accepting applications.',
        'deadline_passed'        => 'The deadline for this study has passed.',
        'full'                   => 'This study is full.',
        'already_applied'        => 'You have already applied to this study.',
        // Deliberately different wording from already_applied: somebody who
        // accepted an invitation never "applied", and telling them they did
        // reads like a bug rather than an explanation.
        'already_participating'  => 'You are already part of this study.',
    ];

    /** Button labels come from the server so two pages cannot disagree. */
    public const REASON_LABEL = [
        'paid_not_yet_available' => 'Paid — coming soon',
        'paid_no_slots'          => 'No paid applications left',
        'paid_locked'            => 'Locked — volunteer or spend Karma',
        'not_open'               => 'Not accepting applications',
        'deadline_passed'        => 'Deadline passed',
        'full'                   => 'Study is full',
        'already_applied'        => 'Applied ✓',
        'already_participating'  => 'You are in this study',
    ];

    // =================================================================
    // The decision
    // =================================================================

    /**
     * Why this participant may NOT apply, or null if they may.
     *
     * Pure: no writes, no exceptions, no HTTP. This is what makes the rules
     * testable before a controller exists, and it is the single source the
     * button, the endpoint and the tests all read.
     *
     * Order matters. State the participant cannot change (paid, closed, past
     * deadline) comes before state they can (already applied), so the message
     * they see is the one that is actually actionable.
     */
    public function blockingReason(User $participant, Study $study): ?string
    {
        /*
        | PAID STUDIES
        |
        | The study-level checks below (open, deadline, seats, duplicate)
        | apply to paid studies too, so this only decides the PAID-SPECIFIC
        | part and then falls through to the shared checks.
        */
        if (! $this->isVolunteer($study)) {
            if (! $this->paidApplicationsAvailable()) {
                return 'paid_not_yet_available';
            }

            $eligibility = $this->unlock->eligibility($participant);

            // Out of slots and short of the target are different problems
            // with different answers, so they are different messages.
            if (! $eligibility['can_apply']) {
                return $eligibility['paid_used'] >= $eligibility['paid_slots']
                    ? 'paid_no_slots'
                    : 'paid_locked';
            }
        }

        if ($study->status !== StudyStatus::OPEN) {
            return 'not_open';
        }

        if ($study->deadline && $study->deadline->isPast()) {
            return 'deadline_passed';
        }

        $existing = $this->participationFor($participant, $study);

        if ($existing) {
            return $existing->stage === PipelineStage::APPLIED
                ? 'already_applied'
                : 'already_participating';
        }

        if ($this->seatsRemaining($study) <= 0) {
            return 'full';
        }

        return null;
    }

    public function canApply(User $participant, Study $study): bool
    {
        return $this->blockingReason($participant, $study) === null;
    }

    /**
     * Everything the button needs, so the JavaScript decides nothing.
     */
    public function status(User $participant, Study $study): array
    {
        $reason    = $this->blockingReason($participant, $study);
        $existing  = $this->participationFor($participant, $study);
        $remaining = $this->seatsRemaining($study);
        $isPaid    = ! $this->isVolunteer($study);

        $paid = $isPaid && $this->paidApplicationsAvailable()
            ? $this->unlock->eligibility($participant)
            : null;

        /*
        | The label has to say what the button will COST. "Apply" on a study
        | that silently spends 50 karma is a dark pattern — the price belongs
        | in the label, not in a tooltip or a confirm dialog.
        */
        $label = match (true) {
            $reason !== null              => self::REASON_LABEL[$reason],
            $paid && $paid['route'] === 'karma'
                => 'Spend ' . $paid['karma_cost'] . ' Karma & Apply',
            default                       => 'Apply',
        };

        return [
            'study_id'        => $study->id,
            'can_apply'       => $reason === null,
            'already_applied' => $existing !== null,
            'stage'           => $existing?->stage?->value,
            'reason'          => $reason,
            'message'         => $reason ? self::REASON_MESSAGE[$reason] : null,
            'label'           => $label,
            'can_withdraw'    => $this->canWithdraw($participant, $study),

            // Null rather than a number when the study has no cap, so the UI
            // can say nothing instead of inventing "0 seats left".
            'seats_remaining' => $study->slots > 0 ? $remaining : null,

            'is_paid' => $isPaid,

            /*
            | Member 3's numbers, passed straight through — not copied,
            | recomputed or reformatted here. The progress bar on the button
            | and the one on her unlock page read the same source, so they
            | cannot disagree.
            */
            'paid' => $paid ? [
                'route'              => $paid['route'],
                'karma_cost'         => $paid['karma_cost'],
                'karma_balance'      => $paid['karma_balance'],
                'volunteer_progress' => $paid['volunteer_progress'],
                'volunteer_target'   => $paid['volunteer_target'],
                'paid_used'          => $paid['paid_used'],
                'paid_slots'         => $paid['paid_slots'],

                // The visible proof that resetting the progress bar does not
                // delete history — worth having on screen.
                'lifetime_volunteer' => $paid['total_volunteer_count'],
            ] : null,
        ];
    }

    // =================================================================
    // Writes
    // =================================================================

    /**
     * Create the application.
     *
     * The unique(study_id, participant_id) index is the real duplicate guard.
     * blockingReason() gives a good message; the index is what makes the rule
     * true even if two requests arrive at once.
     *
     * PAID STUDIES ARE DELEGATED WHOLESALE.
     * -------------------------------------
     * Member 3's applyToPaidStudy() creates the participation, spends the
     * karma, increments paid_used and resets the cycle — all inside ONE
     * transaction. Doing my own half here would split an atomic operation
     * across two services, and a failure between them would leave karma spent
     * with no application, or an application with no karma spent.
     *
     * So: I own the study-level checks (open, deadline, seats, duplicate) and
     * she owns the money. Nothing about the cycle is duplicated here.
     */
    public function apply(User $participant, Study $study): StudyParticipation
    {
        $reason = $this->blockingReason($participant, $study);

        if ($reason !== null) {
            abort(self::REASON_STATUS[$reason], self::REASON_MESSAGE[$reason]);
        }

        if (! $this->isVolunteer($study)) {
            return $this->unlock->applyToPaidStudy($participant, $study)['participation'];
        }

        try {
            return StudyParticipation::create([
                'study_id'       => $study->id,
                'participant_id' => $participant->id,
                'stage'          => PipelineStage::APPLIED,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Lost a race with another request from the same person. The
            // constraint did its job; report it as the 409 it is.
            abort(409, self::REASON_MESSAGE['already_applied']);
        }
    }

    /**
     * Withdraw, allowed only while the researcher has not acted.
     *
     * The row is DELETED rather than moved to a `withdrawn` stage, because
     * PipelineStage is Member 2's enum and adding a case to it would be
     * editing her state machine to solve my problem.
     */
    public function withdraw(User $participant, Study $study): void
    {
        abort_unless(
            (bool) config('platform.apply.allow_withdraw'),
            403,
            'Applications cannot be withdrawn.'
        );

        $existing = $this->participationFor($participant, $study);

        abort_if(! $existing, 404, 'You have not applied to this study.');

        abort_unless(
            $existing->stage === PipelineStage::APPLIED,
            409,
            'This application has already been reviewed and can no longer be withdrawn.'
        );

        $existing->delete();
    }

    public function canWithdraw(User $participant, Study $study): bool
    {
        if (! config('platform.apply.allow_withdraw')) {
            return false;
        }

        return $this->participationFor($participant, $study)?->stage === PipelineStage::APPLIED;
    }

    // =================================================================
    // Reads
    // =================================================================

    /** The participant's own applications, newest first. */
    public function myApplications(User $participant): Collection
    {
        return StudyParticipation::with('study:id,title,incentive_type,status,deadline')
            ->where('participant_id', $participant->id)
            ->latest('id')
            ->get();
    }

    /**
     * Seats left.
     *
     * An `applied` row does NOT consume a seat. An application is a request,
     * not a booking — if applications took seats, ten applicants would close a
     * five-seat study before the researcher had read one of them. Only the
     * committed stages count, which is exactly what seatsTaken() measures.
     */
    public function seatsRemaining(Study $study): int
    {
        if ((int) $study->slots <= 0) {
            return PHP_INT_MAX;   // no declared cap
        }

        return max(0, (int) $study->slots - $this->invitations->seatsTaken($study));
    }

    // =================================================================

    private function participationFor(User $participant, Study $study): ?StudyParticipation
    {
        return StudyParticipation::where('study_id', $study->id)
            ->where('participant_id', $participant->id)
            ->first();
    }

    private function isVolunteer(Study $study): bool
    {
        $type = $study->incentive_type instanceof IncentiveType
            ? $study->incentive_type
            : IncentiveType::tryFrom((string) $study->incentive_type);

        return $type === IncentiveType::VOLUNTEER;
    }

    /** Guard used by the controller. Here so the rule lives with the others. */
    public function assertParticipant(?User $user): User
    {
        abort_unless(
            $user && $user->role === UserRole::PARTICIPANT,
            403,
            'Only participants can apply to studies.'
        );

        abort_if(
            ! $user->participantProfile,
            404,
            'No participant profile found for this account.'
        );

        return $user;
    }
}
