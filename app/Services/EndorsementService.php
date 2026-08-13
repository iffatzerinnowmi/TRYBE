<?php

namespace App\Services;

use App\Enums\PipelineStage;
use App\Models\Endorsement;
use App\Models\StudyParticipation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FEATURE — Researcher Endorsement → Verified Participant badge.
 *
 * The rule: endorsements from N DIFFERENT researchers earn the badge.
 * Counting distinct researchers is the whole point — one researcher
 * endorsing the same person five times must not unlock anything.
 *
 * This class is the single owner of the rule. The Blade page is gone as a
 * source of data, and EndorsementApiController holds no logic of its own:
 * it calls the methods below and shapes the answer as JSON.
 */
class EndorsementService
{
    public function __construct(private NotificationService $notifications) {}

    /** How many endorsements are needed. Shared config, not a literal. */
    public function required(): int
    {
        return (int) config('platform.endorsements_for_verified_badge');
    }

    /** How many tags one endorsement may carry. Also config, not a literal. */
    public function maxTags(): int
    {
        return (int) config('platform.endorsement_max_tags');
    }

    /** Distinct researchers who have endorsed this participant. */
    public function distinctEndorserCount(User $participant): int
    {
        return Endorsement::where('participant_id', $participant->id)
            ->distinct('researcher_id')
            ->count('researcher_id');
    }

    /**
     * Every tag this participant has ever been given, with how many times.
     * Sorted most-used first — that ordering is the tag cloud on the page.
     */
    public function receivedTags(User $participant): Collection
    {
        return Endorsement::where('participant_id', $participant->id)
            ->pluck('tags')
            ->flatten()
            ->countBy()
            ->sortDesc();
    }

    /**
     * Completed sessions where this researcher has not endorsed the
     * participant yet — the queue on the endorsement page.
     */
    public function pendingFor(User $researcher)
    {
        $alreadyEndorsed = Endorsement::where('researcher_id', $researcher->id)
            ->get()
            ->map(fn ($e) => $e->participant_id . ':' . $e->study_id);

        return StudyParticipation::query()
            ->with(['participant.participantProfile', 'study'])
            ->whereIn('stage', [PipelineStage::COMPLETED, PipelineStage::PAID])
            ->whereHas('study', fn ($q) => $q->where('researcher_id', $researcher->id))
            ->latest('completed_at')
            ->get()
            ->reject(fn ($session) =>
                $alreadyEndorsed->contains($session->participant_id . ':' . $session->study_id)
            )
            ->values();
    }

    /**
     * The one session in this researcher's queue that matches the pair the
     * browser asked to endorse, or null.
     *
     * This is the security check behind POST /api/v1/endorsements. Without
     * it, anyone logged in as a researcher could post any participant id and
     * award an endorsement for a session that was never theirs.
     */
    public function eligibleSession(User $researcher, int $participantId, ?int $studyId): ?StudyParticipation
    {
        return $this->pendingFor($researcher)
            ->first(fn ($session) =>
                (int) $session->participant_id === $participantId
                && (int) $session->study_id === (int) $studyId
            );
    }

    /**
     * Record one endorsement, refresh the participant's standing, and notify
     * them. All of it in a transaction, so a half-written endorsement can
     * never leave the counter wrong.
     */
    public function endorse(
        User $researcher,
        User $participant,
        ?int $studyId,
        array $tags
    ): array {

        return DB::transaction(function () use ($researcher, $participant, $studyId, $tags) {

            $endorsement = Endorsement::create([
                'researcher_id'  => $researcher->id,
                'participant_id' => $participant->id,
                'study_id'       => $studyId,
                'tags'           => array_values($tags),
            ]);

            $profile = $participant->participantProfile;
            $count = $this->distinctEndorserCount($participant);
            $required = $this->required();

            $wasVerified = (bool) $profile->is_verified_participant;
            $isVerified = $count >= $required;

            $profile->update([
                'endorsement_count'       => $count,
                'is_verified_participant' => $isVerified,
            ]);

            /* ---- tell the participant ---- */
            $tagList = implode(', ', $tags);

            if ($isVerified && ! $wasVerified) {
                $this->notifications->send(
                    $participant,
                    'endorse',
                    'You are now a Verified Participant',
                    $researcher->name . " endorsed you ({$tagList}) — that was your "
                        . $required . "th endorsement. The badge is now on your profile.",
                    url('/participant/credentials')
                );
            } else {
                $remaining = max(0, $required - $count);

                $this->notifications->send(
                    $participant,
                    'endorse',
                    $researcher->name . ' endorsed you',
                    "Tagged you {$tagList}" . ($remaining > 0
                        ? " — {$remaining} more to Verified Participant."
                        : '.'),
                    url('/participant/credentials')
                );
            }

            return [
                'endorsement'  => $endorsement,
                'count'        => $count,
                'required'     => $required,
                'justVerified' => $isVerified && ! $wasVerified,
            ];
        });
    }
}