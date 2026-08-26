<?php

namespace App\Services;

use App\Contracts\PipelineWriter;
use App\Enums\AuctionStatus;
use App\Enums\SeatApplicationStatus;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\Study;
use App\Models\StudySeatApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FEATURE — Limited Seat Auctions (Member 3)
 *
 * The only writer for studies.auction_* and study_seat_applications.
 *
 *   Study created, researcher opts in    -> enableIfRequested()
 *   Participant applies                  -> apply()
 *   48h elapses OR 3x slots have applied  -> closeAuction() (triggered
 *                                            lazily from payload()/apply(),
 *                                            or from the scheduler via
 *                                            closeAllDue())
 *.
 */
class SeatAuctionService
{
    public function __construct(
        private PipelineWriter $pipeline,
        private NotificationService $notifications,
    ) {
    }

    // ---------------------------------------
    // Eligibility of a study for auction mode 
    // ---------------------------------------

    public function isEligible(Study $study): bool
    {
        return $this->eligibilityReason($study) === null;
    }

    /** Null when eligible; otherwise a human-readable reason it is not. */
    public function eligibilityReason(Study $study): ?string
    {
        if (! $study->incentive_type?->requiresEscrow()) {
            return 'Auction mode is only available for cash or voucher studies.';
        }

        $maxSlots = (int) config('platform.auction.max_slots', 3);
        if ((int) $study->slots > $maxSlots) {
            return "Auction mode is only available for studies with {$maxSlots} or fewer slots.";
        }
        // min 1000 is needed to pass auction eligibility
        $minCompensation = (float) config('platform.auction.min_compensation', 1000);
        if ((float) $study->compensation_amount < $minCompensation) {
            return 'Auction mode requires compensation of at least ৳'
                . number_format($minCompensation, 0) . ' per participant.';
        }

        return null;
    }

    // -----------------------------------------------------------------
    // Turning auction mode on
    // -----------------------------------------------------------------

    /**

     */
    public function enableIfRequested(Study $study, bool $requested): Study
    {
        if (! $requested || ! $this->isEligible($study) || $study->status !== StudyStatus::OPEN) {
            return $study;
        }

        $now = now();

        $study->update([
            'auction_mode'      => true,
            'auction_status'    => AuctionStatus::OPEN,
            'auction_opened_at' => $now,
            'auction_closes_at' => $now->copy()->addHours((int) config('platform.auction.duration_hours', 48)),
        ]);

        return $study->fresh();
    }

    // -----------------------------------------------------------------
    // Applying
    // -----------------------------------------------------------------

    public function apply(Study $study, User $participant): StudySeatApplication
    {
        abort_unless($participant->role === UserRole::PARTICIPANT, 422,
            'Only participant accounts may apply to a seat auction.');

        abort_unless($participant->participantProfile, 422,
            'Complete your participant profile before applying.');

        abort_unless((bool) $study->auction_mode, 422,
            'This study is not running a seat auction.');

        // Close it first if it's overdue — someone applying to a stale
        // listing shouldn't sneak into a supposedly-closed auction just
        // because the scheduler hasn't ticked yet.
        $this->closeIfDue($study);
        $study->refresh();

        abort_unless($study->auction_status === AuctionStatus::OPEN, 422,
            'This seat auction has already closed.');

        $existing = StudySeatApplication::where('study_id', $study->id)
            ->where('participant_id', $participant->id)
            ->first();

        abort_if($existing, 422, 'You have already applied to this seat auction.');

        $application = DB::transaction(function () use ($study, $participant) {
            return StudySeatApplication::create([
                'study_id'                   => $study->id,
                'participant_id'             => $participant->id,
                'reliability_score_snapshot' => (int) ($participant->participantProfile?->reliability_score ?? 0),
                'status'                     => SeatApplicationStatus::APPLIED,
                'applied_at'                 => now(),
            ]);
        });

        // Fill-trigger: 3x the seats have applied -> close right now,
        // without waiting for the 48h deadline.
        $this->closeIfFilled($study);

        return $application;
    }

    // -----------------------------------------------------------------
    // Closing
    // -----------------------------------------------------------------

    public function closeIfDue(Study $study): bool
    {
        if ($study->auction_status !== AuctionStatus::OPEN) {
            return false;
        }

        if (! $study->auction_closes_at || $study->auction_closes_at->isFuture()) {
            return false;
        }

        $this->closeAuction($study, 'deadline');
        return true;
    }

    public function closeIfFilled(Study $study): bool
    {
        if ($study->auction_status !== AuctionStatus::OPEN) {
            return false;
        }

        $target = (int) $study->slots * (int) config('platform.auction.fill_multiplier', 3);
        $count  = StudySeatApplication::where('study_id', $study->id)->count();

        if ($target <= 0 || $count < $target) {
            return false;
        }

        $this->closeAuction($study, 'filled');
        return true;
    }

    /** Called by the scheduled command for every open auction past its deadline. */
    public function closeAllDue(): int
    {
        $closed = 0;

        Study::query()
            ->where('auction_mode', true)
            ->where('auction_status', AuctionStatus::OPEN->value)
            ->where('auction_closes_at', '<=', now())
            ->each(function (Study $study) use (&$closed) {
                $this->closeAuction($study, 'deadline');
                $closed++;
            });

        return $closed;
    }

    /**
     * Rank every applicant by their snapshot reliability score, award the
     * top `slots` seats, confirm winners into the pipeline, and notify
     * everyone. Idempotent — a study that's already closed is left alone.
     */
    public function closeAuction(Study $study, string $reason = 'manual'): void
    {
        if ($study->auction_status !== AuctionStatus::OPEN) {
            return;
        }

        DB::transaction(function () use ($study) {
            $seats = (int) $study->slots;

            // Highest score first; ties broken by who applied earliest,
            // then by id — deterministic and reproducible.
            $applications = StudySeatApplication::where('study_id', $study->id)
                ->orderByDesc('reliability_score_snapshot')
                ->orderBy('applied_at')
                ->orderBy('id')
                ->get();

            $now = now();

            $applications->values()->each(function (StudySeatApplication $application, int $index) use ($seats, $now, $study) {
                $won = $index < $seats;
                // system deciding winners and losers

                $application->update([
                    'status'     => $won ? SeatApplicationStatus::WON : SeatApplicationStatus::LOST,
                    'rank'       => $index + 1,
                    'decided_at' => $now,
                ]);

                if ($won) {
                    // Member 2's pipeline seam — confirms the winner exactly
                    // the way an accepted invitation would.
                    $this->pipeline->confirm($study, $application->participant);
                }
            });

            $study->update([
                'auction_status'    => AuctionStatus::CLOSED,
                'auction_closed_at' => $now,
                // Seats are decided; stop surfacing the listing as open.
                'status'            => StudyStatus::FULL,
            ]);
        });

        $this->notifyResults($study->fresh(['seatApplications.participant']));

        Log::info('Seat auction closed.', [
            'study_id' => $study->id,
            'reason'   => $reason,
            'seats'    => $study->slots,
        ]);
    }

    private function notifyResults(Study $study): void
    {
        foreach ($study->seatApplications as $application) {
            $participant = $application->participant;
            if (! $participant) {
                continue;
            }

            if ($application->status === SeatApplicationStatus::WON) {
                $this->notifications->send(
                    $participant,
                    'auction',
                    'You won a seat! 🎯',
                    "You ranked #{$application->rank} for \"{$study->title}\" and were awarded a seat.",
                    route('studies.show', $study)
                );
            } else {
                $this->notifications->send(
                    $participant,
                    'auction',
                    'Seat auction results',
                    "\"{$study->title}\" has closed. You ranked #{$application->rank} — the seats went to higher-reliability applicants this time.",
                    route('studies.show', $study)
                );
            }
        }
    }

    // -----------------------------------------------------------------
    // Reading — drives both the API payload and the Blade page
    // -----------------------------------------------------------------

    /** Ranked list of real applicants for this study's auction. */
    public function ranking(Study $study): array
    {
        $seats = (int) $study->slots;

        return StudySeatApplication::where('study_id', $study->id)
            ->with('participant:id,name')
            ->orderByDesc('reliability_score_snapshot')
            ->orderBy('applied_at')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(fn (StudySeatApplication $application, int $index) => [
                'rank'              => $application->rank ?? $index + 1,
                'participant_id'    => $application->participant_id,
                'name'              => $application->participant?->name ?? 'Unknown participant',
                'reliability_score' => $application->reliability_score_snapshot,
                'status'            => $application->status->value,
                'wins_seat'         => $application->status === SeatApplicationStatus::WON
                                       || ($study->auction_status === AuctionStatus::OPEN && $index < $seats),
            ])
            ->all();
    }

    /** Full payload for GET /api/v1/studies/{study}/auction. */
    public function payload(Study $study, ?User $viewer = null): array
    {
        // Pick up a just-missed deadline before reporting status, so the
        // page never shows a stale "open" after the clock has run out.
        $this->closeIfDue($study);
        $study->refresh();

        $seats = (int) $study->slots;
        $applications = StudySeatApplication::where('study_id', $study->id)->count();

        $myApplication = null;
        if ($viewer && $viewer->role === UserRole::PARTICIPANT) {
            $application = StudySeatApplication::where('study_id', $study->id)
                ->where('participant_id', $viewer->id)
                ->first();

            if ($application) {
                $myApplication = [
                    'status'            => $application->status->value,
                    'status_label'      => $application->status->label(),
                    'rank'              => $application->rank,
                    'reliability_score' => $application->reliability_score_snapshot,
                    'applied_at'        => $application->applied_at?->toIso8601String(),
                ];
            }
        }

        return [
            'study_id'           => $study->id,
            'eligible'           => $this->isEligible($study),
            'eligibility_reason' => $this->eligibilityReason($study),
            'enabled'            => (bool) $study->auction_mode,
            'status'             => $study->auction_status?->value,
            'status_label'       => $study->auction_status?->label(),
            'seats'              => $seats,
            'applications_count' => $applications,
            'fill_target'        => $seats * (int) config('platform.auction.fill_multiplier', 3),
            'opened_at'          => $study->auction_opened_at?->toIso8601String(),
            'closes_at'          => $study->auction_closes_at?->toIso8601String(),
            'closed_at'          => $study->auction_closed_at?->toIso8601String(),
            'my_application'     => $myApplication,
            'ranking'            => $this->ranking($study),
        ];
    }
}