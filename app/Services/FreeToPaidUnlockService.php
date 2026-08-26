<?php

namespace App\Services;

use App\Enums\IncentiveType;
use App\Enums\KarmaSource;
use App\Enums\PipelineStage;
use App\Mail\PaidAccessUnlockedMail;
use App\Models\ParticipantProfile;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class FreeToPaidUnlockService
{
    public function __construct(
        private NotificationService $notifications,
        private KarmaService $karma,
    ) {}

    /* ---------------- legacy one-time boolean unlock (unchanged) ---------------- */

    public function target(): int
    {
        return (int) config('platform.free_forms_to_unlock_paid');
    }

    public function freeCompletedCount(User $user): int
    {
        return StudyParticipation::query()
            ->where('participant_id', $user->id)
            ->where('stage', PipelineStage::COMPLETED)
            ->whereHas('study', fn ($q) => $q->where('incentive_type', IncentiveType::VOLUNTEER))
            ->count();
    }

    public function isUnlocked(User $user): bool
    {
        return (bool) ($user->participantProfile?->paid_studies_unlocked ?? false);
    }

    public function canApplyToStudy(User $user, Study $study): bool
    {
        if ($study->incentive_type === IncentiveType::VOLUNTEER) return true;
        return $this->isUnlocked($user);
    }

    public function progress(User $user): array
    {
        $target = $this->target();
        $count = $this->freeCompletedCount($user);

        return [
            'target'    => $target,
            'count'     => $count,
            'remaining' => max(0, $target - $count),
            'unlocked'  => $this->isUnlocked($user) || $count >= $target,
        ];
    }

    public function evaluate(User $user): array
    {
        $profile = $user->participantProfile;
        abort_if(! $profile, 404, 'No participant profile found for this account.');

        $target = $this->target();
        $count = $this->freeCompletedCount($user);
        $wasUnlocked = (bool) $profile->paid_studies_unlocked;
        $justUnlocked = ! $wasUnlocked && $count >= $target;

        $profile->fill(['free_studies_completed' => $count]);

        if ($justUnlocked) {
            $profile->fill(['paid_studies_unlocked' => true, 'paid_studies_unlocked_at' => now()]);
        }

        $profile->save();

        if ($justUnlocked) {
            $this->notifyUnlocked($user, $count, $target);
        }

        return [
            'profile' => $profile->fresh(), 'count' => $count, 'target' => $target,
            'remaining' => max(0, $target - $count),
            'unlocked' => $wasUnlocked || $justUnlocked,
            'just_unlocked' => $justUnlocked,
        ];
    }

    private function notifyUnlocked(User $user, int $count, int $target): void
    {
        $this->notifications->send(
            $user, 'unlock', 'Paid study access unlocked 🎉',
            "You completed {$count} of {$target} free studies — you can now apply to paid studies.",
            route('studies.index')
        );

        try {
            Mail::to($user->email)->send(new PaidAccessUnlockedMail($user, $count, $target));
        } catch (\Throwable $e) {
            Log::warning('Paid-access-unlocked email failed for user ' . $user->id . ': ' . $e->getMessage());
        }
    }

    /* ---------------- volunteer / paid cycle (Feature A) ---------------- */

    public function cycleTarget(): int
    {
        return (int) config('platform.volunteer_cycle_target');
    }

    public function paidSlotsPerCycle(): int
    {
        return (int) config('platform.paid_slots_per_cycle');
    }

    public function recordVolunteerCompletion(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $profile = ParticipantProfile::where('user_id', $user->id)->lockForUpdate()->first();
            abort_if(! $profile, 404, 'No participant profile found for this account.');

            $target = $this->cycleTarget();

            $profile->total_volunteer_count += 1;
            if ($profile->volunteer_progress < $target) {
                $profile->volunteer_progress += 1;
            }
            $profile->save();

            return $this->cycleStatus($user, $profile->fresh());
        });
    }
    // CAN APPLY TO PAID STUDY 
    public function canApplyPaidCycle(User $user): bool
    {
        $profile = $user->participantProfile;
        if (! $profile) return false;

        return $profile->volunteer_progress >= $this->cycleTarget()
            && $profile->paid_used < $this->paidSlotsPerCycle();
    }

    public function cycleStatus(User $user, ?ParticipantProfile $profile = null): array
    {
        $profile ??= $user->participantProfile;
        abort_if(! $profile, 404, 'No participant profile found for this account.');

        $target = $this->cycleTarget();
        $slots  = $this->paidSlotsPerCycle();

        return [
            'total_volunteer_count' => $profile->total_volunteer_count,
            'volunteer_progress'    => $profile->volunteer_progress,
            'volunteer_target'      => $target,
            'paid_used'             => $profile->paid_used,
            'paid_slots'            => $slots,
            'paid_remaining'        => max(0, $slots - $profile->paid_used),
            'can_apply_paid'        => $profile->volunteer_progress >= $target && $profile->paid_used < $slots,
        ];
    }

    /* ---------------- Karma alternate unlock (Feature B, NEW) ---------------- */

    public function karmaUnlockCost(): int
    {
        return (int) config('platform.karma_paid_unlock_cost');
    }

    /**
     * Read-only snapshot the Apply button is built from. Never mutates
     * anything, so it's safe to call as often as the UI wants.
     */
    public function eligibility(User $user): array
    {
        $profile = $user->participantProfile;
        abort_if(! $profile, 404, 'No participant profile found for this account.');

        $target       = $this->cycleTarget();
        $slots        = $this->paidSlotsPerCycle();
        $cost         = $this->karmaUnlockCost();
        $karmaBalance = $this->karma->balanceFor($user);

        $hasSlot         = $profile->paid_used < $slots;
        $volunteerReady  = $profile->volunteer_progress >= $target;
        // Karma route only ever applies when the volunteer route hasn't
        // already been earned — rule 2 of the spec.
        $karmaReady      = ! $volunteerReady && $karmaBalance >= $cost;

        return [
            'total_volunteer_count' => $profile->total_volunteer_count,
            'volunteer_progress'    => $profile->volunteer_progress,
            'volunteer_target'      => $target,
            'paid_used'             => $profile->paid_used,
            'paid_slots'            => $slots,
            'karma_balance'         => $karmaBalance,
            'karma_cost'            => $cost,
            'can_apply_free'        => $hasSlot && $volunteerReady,
            'can_apply_karma'       => $hasSlot && $karmaReady,
            'can_apply'             => $hasSlot && ($volunteerReady || $karmaReady),
            'route'                 => $volunteerReady ? 'free' : ($karmaReady ? 'karma' : null),
        ];
    }

    /**
     * Atomically applies `$user` to a paid `$study`.
     *
     *  - Locks the participant profile row and re-checks eligibility from
     *    scratch — never trusts a client-side eligibility check from a
     *    moment ago.
     *  - Locks and rejects duplicate applications to the same study.
     *  - Uses the free volunteer route whenever volunteer_progress has
     *    already hit target; Karma is only ever spent as the fallback,
     *    per rule 2 of the spec.
     *  - The Karma spend goes through KarmaService::spend(), which does
     *    its own locked balance check — so the balance check and the
     *    deduction can't race a second concurrent request.
     *  - Everything — eligibility checks, duplicate check, Karma spend,
     *    application row, and paid_used/volunteer_progress updates — is
     *    inside one DB transaction. Any abort()/exception anywhere in
     *    here rolls back the entire thing, including a Karma spend that
     *    already happened moments earlier in the same call.
     */
    public function applyToPaidStudy(User $user, Study $study): array
    {
        abort_if(
            $study->incentive_type === IncentiveType::VOLUNTEER,
            422,
            'This is a volunteer study — no application slot is needed.'
        );

        return DB::transaction(function () use ($user, $study) {
            $profile = ParticipantProfile::where('user_id', $user->id)->lockForUpdate()->first();
            abort_if(! $profile, 404, 'No participant profile found for this account.');

            $existing = StudyParticipation::where('study_id', $study->id)
                ->where('participant_id', $user->id)
                ->lockForUpdate()
                ->first();
            abort_if($existing, 409, 'You have already applied to this study.');

            $target = $this->cycleTarget();
            $slots  = $this->paidSlotsPerCycle();
            $cost   = $this->karmaUnlockCost();

            abort_unless(
                $profile->paid_used < $slots,
                403,
                'No paid application slots available this cycle.'
            );

            $useFreeRoute = $profile->volunteer_progress >= $target;

            if (! $useFreeRoute) {
                abort_unless(
                    $this->karma->balanceFor($user) >= $cost,
                    403,
                    'Complete more volunteer studies, or earn more Karma, to apply.'
                );
            }

            $participation = StudyParticipation::create([
                'study_id'       => $study->id,
                'participant_id' => $user->id,
                'stage'          => PipelineStage::APPLIED->value,
            ]);

            if (! $useFreeRoute) {
                $spend = $this->karma->spend(
                    $user, $cost, KarmaSource::PAID_APPLICATION_UNLOCK,
                    'Unlocked paid application for "' . $study->title . '"'
                );

                // Re-checked here in case balance moved between the read
                // above and this locked spend — same defence-in-depth
                // pattern KarmaService already uses for itself.
                abort_if(! $spend, 403, 'Not enough Karma to unlock a paid application.');
            }

            $profile->paid_used += 1;

            // Slot cap reached — cycle resets, total_volunteer_count untouched.
            if ($profile->paid_used >= $slots) {
                $profile->volunteer_progress = 0;
                $profile->paid_used = 0;
            }

            $profile->save();

            return [
                'participation' => $participation->fresh(),
                'route'         => $useFreeRoute ? 'free' : 'karma',
                'cycle'         => $this->cycleStatus($user, $profile->fresh()),
            ];
        });
    }
}