<?php

namespace App\Http\Controllers;

use App\Enums\StudyStatus;
use App\Models\Study;
use App\Models\VerificationRequest;

/**
 * Organizations post studies under their institution's name, so their
 * dashboard is a slimmer version of the researcher one plus their
 * verification state.
 */
class OrganizationDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $studies = Study::query()
            ->withCount('participations')
            ->where('researcher_id', $user->id)
            ->latest('id')
            ->get();

        $stats = [
            'total_studies'  => $studies->count(),
            'open_studies'   => $studies->where('status', StudyStatus::OPEN)->count(),
            'applicants'     => $studies->sum('participations_count'),
        ];

        // Their most recent application to be verified, if any.
        $request = VerificationRequest::where('user_id', $user->id)
            ->latest('id')
            ->first();

        return view('dashboards.organization', compact('user', 'studies', 'stats', 'request'));
    }
}
