<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStage;
use App\Models\StudyParticipation;
use App\Services\CredentialService;

/**
 * FEATURE 1 — Participant Credentialing.
 *
 * The controller does no maths of its own. It asks CredentialService for
 * everything, so the page and the artisan command can never disagree.
 */
class CredentialController extends Controller
{
    public function __construct(private CredentialService $credentials) {}

    public function index()
    {
        $user = auth()->user();
        $profile = $user->participantProfile;

        abort_if(! $profile, 404, 'No participant profile found for this account.');

        $count = $profile->completed_studies_count;
        $current = $profile->credential_level;
        $next = $this->credentials->nextTier($current);

        // The studies that earned the current level.
        $completed = StudyParticipation::query()
            ->with('study.researcher')
            ->where('participant_id', $user->id)
            ->whereIn('stage', [PipelineStage::COMPLETED, PipelineStage::PAID])
            ->latest('completed_at')
            ->get();

        return view('participant.credentials', [
            'user'      => $user,
            'profile'   => $profile,
            'count'     => $count,
            'current'   => $current,
            'next'      => $next,
            'progress'  => $this->credentials->progressPercent($count, $current),
            'remaining' => $next ? max(0, $next->minCompletions() - $count) : 0,
            'ladder'    => $this->credentials->ladder(),
            'completed' => $completed,
        ]);
    }

    /**
     * Recount from study_participations and save.
     *
     * In the finished platform this runs automatically whenever a researcher
     * marks a session complete. The button exists so the whole rule can be
     * demonstrated in one click.
     */
    public function recalculate()
    {
        $result = $this->credentials->recalculate(auth()->user());

        $message = $result['promoted']
            ? 'Promoted from ' . $result['from']->label() . ' to ' . $result['to']->label() . '.'
            : 'Recounted: ' . $result['count'] . ' completed studies. Level unchanged ('
              . $result['to']->label() . ').';

        return back()->with('status', $message);
    }
}
