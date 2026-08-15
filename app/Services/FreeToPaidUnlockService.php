<?php

namespace App\Services;

use App\Enums\IncentiveType;
use App\Enums\PipelineStage;
use App\Mail\PaidAccessUnlockedMail;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class FreeToPaidUnlockService
{
    public function __construct(private NotificationService $notifications) {}

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

        Mail::to($user->email)->send(new PaidAccessUnlockedMail($user, $count, $target));
    }
}