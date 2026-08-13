<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Models\Follow;
use App\Models\Study;
use App\Models\StudyParticipation;

/**
 * The researcher's home screen: their listings, their pipeline, and whether
 * the admin has verified them yet.
 */
class ResearcherDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $profile = $user->researcherProfile;

        /* ---- their studies, with a live applicant count each ---- */
        $studies = Study::query()
            ->withCount('participations')
            ->where('researcher_id', $user->id)
            ->latest('id')
            ->get();

        /* ---- how many applicants sit at each pipeline stage, per study ----
           One grouped query for the whole page instead of one per study. */
        $stageCounts = StudyParticipation::query()
            ->selectRaw('study_id, stage, COUNT(*) as total')
            ->whereIn('study_id', $studies->pluck('id'))
            ->groupBy('study_id', 'stage')
            ->get()
            ->groupBy('study_id');

        /* ---- headline numbers ---- */
        $stats = [
            'active_listings' => $studies->where('status', StudyStatus::OPEN)->count(),
            'applicants'      => $studies->sum('participations_count'),
            'avg_rating'      => $profile?->avg_rating ?? 0,
            'followers'       => Follow::where('researcher_id', $user->id)->count(),
        ];

        /* ---- most recent movement in the pipeline ---- */
        $activity = StudyParticipation::query()
            ->with(['participant', 'study'])
            ->whereIn('study_id', $studies->pluck('id'))
            ->latest('updated_at')
            ->take(5)
            ->get();

        /* ---- Suggested participants (Member 4 — Smart Participant Matching)
           used to be built here: one rankParticipantsForStudy() call per
           study, each running one hasInvitation() query per candidate. It is
           now API-driven. The panel partial ships empty and fetches
           GET /api/v1/studies/{study}/candidates, so this controller passes
           no matching data at all — and the dashboard no longer runs dozens
           of queries on load. ---- */

        /* ---- the colour and label for each pipeline stage ---- */
        $stageStyles = [
            PipelineStage::APPLIED->value   => ['label' => 'Applied',   'class' => 'bg-steel'],
            PipelineStage::SCREENED->value  => ['label' => 'Screened',  'class' => 'bg-star'],
            PipelineStage::CONFIRMED->value => ['label' => 'Confirmed', 'class' => 'bg-plum'],
            PipelineStage::SCHEDULED->value => ['label' => 'Scheduled', 'class' => 'bg-ink'],
            PipelineStage::COMPLETED->value => ['label' => 'Completed', 'class' => 'bg-ok'],
            PipelineStage::PAID->value      => ['label' => 'Paid',      'class' => 'bg-ok'],
            PipelineStage::NO_SHOW->value   => ['label' => 'No show',   'class' => 'bg-flame'],
            PipelineStage::REJECTED->value  => ['label' => 'Rejected',  'class' => 'bg-danger'],
        ];

        return view('dashboards.researcher', compact(
            'user',
            'profile',
            'studies',
            'stageCounts',
            'stats',
            'activity',
            'stageStyles'
        ));
    }
}
