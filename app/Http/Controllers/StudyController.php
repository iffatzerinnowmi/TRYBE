<?php

namespace App\Http\Controllers;

use App\Enums\IncentiveType;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Http\Requests\StoreStudyRequest;
use App\Models\Study;
use App\Models\StudyParticipation;
use Illuminate\Support\Facades\DB;

/**
 * FEATURE — Incentive Variety Settings
 *
 * Researchers pick one of four ways to compensate participants:
 *   Cash / Voucher    -> amount is required and locked into escrow
 *                        before the listing is allowed to go live.
 *   Course Credit     -> institution + supporting document required.
 *   Volunteer/Unpaid  -> nothing further to collect.
 */
class StudyController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $studies = Study::query()
            ->with('researcher')
            ->where('status', StudyStatus::OPEN)
            ->latest('id')
            ->get();

        /* ---- Match scores per study (Member 4) used to be attached here,
           one assessUserForStudy() call per listing. The participant's own
           ranked list is now GET /api/v1/participants/me/matched-studies. ---- */

        return view('studies.index', [
            'user' => $user,
            'studies' => $studies,
        ]);
    }

    public function create()
    {
        return view('studies.create', [
            'incentiveTypes' => IncentiveType::cases(),
        ]);
    }

    public function store(StoreStudyRequest $request)
    {
        $data = $request->validated();
        $incentiveType = IncentiveType::from($data['incentive_type']);

        $study = DB::transaction(function () use ($data, $request, $incentiveType) {

            // Starts as a draft — it only flips to OPEN once the incentive
            // side of things (escrow lock / documentation) is settled below.
            $study = Study::create([
                'researcher_id'       => auth()->id(),
                'title'               => $data['title'],
                'description'         => $data['description'] ?? null,
                'category'            => $data['category'] ?? null,
                'method'              => $data['method'],
                'duration_minutes'    => $data['duration_minutes'],
                'slots'               => $data['slots'],
                'deadline'            => $data['deadline'] ?? null,
                'incentive_type'      => $incentiveType,
                'compensation_amount' => $incentiveType->requiresEscrow() ? $data['compensation_amount'] : 0,
                'status'              => StudyStatus::DRAFT,
            ]);

            if ($incentiveType->requiresEscrow()) {
                // Simulated escrow lock — swap this for a real wallet /
                // payment-gateway call once that service exists. The
                // listing is only allowed to go live if the lock succeeds.
                $locked = $this->lockEscrow($study);

                $study->update([
                    'escrow_locked'    => $locked,
                    'escrow_locked_at' => $locked ? now() : null,
                    'status'           => $locked ? StudyStatus::OPEN : StudyStatus::DRAFT,
                ]);
            } elseif ($incentiveType->requiresDocumentation()) {
                $study->update([
                    'course_credit_institution'   => $data['course_credit_institution'],
                    'course_credit_document_path' => $request->file('course_credit_document')
                        ->store('course-credit-documents', 'public'),
                    'status' => StudyStatus::OPEN,
                ]);
            } else {
                $study->update(['status' => StudyStatus::OPEN]);
            }

            return $study;
        });

        return redirect()->route('dashboard')->with(
            'status',
            $study->status === StudyStatus::OPEN
                ? "Study posted — it's live for participants now."
                : "Study saved as a draft — the escrow lock didn't go through, try again."
        );
    }

    public function show(Study $study)
    {
        $user = auth()->user();
        $study->load('researcher');

        abort_if($user->role?->value === 'researcher' && $study->researcher_id !== $user->id, 403);

        /* ---- Matching, invitations and the suggested-participant list
           (Member 4) used to be built here. They are now API-driven:

               GET /api/v1/studies/{study}/candidates
               GET /api/v1/studies/{study}/match-criteria
               GET /api/v1/invitations

           so this controller passes none of it. The invite/accept/decline
           POST forms that lived on this page are gone too — responding is
           PATCH /api/v1/invitations/{invitation}. ---- */

        $currentParticipants = collect();

        if ($user->role?->value === 'researcher' && $study->researcher_id === $user->id) {
            $currentParticipants = StudyParticipation::query()
                ->with(['participant.participantProfile'])
                ->where('study_id', $study->id)
                ->where('stage', PipelineStage::CONFIRMED->value)
                ->latest('updated_at')
                ->get();
        }

        return view('studies.show', [
            'user' => $user,
            'study' => $study,
            'currentParticipants' => $currentParticipants,
        ]);
    }

    /**
     * Locks the compensation amount in escrow so it can't be spent
     * elsewhere while the study runs. No payment gateway exists yet in
     * this codebase, so this always succeeds for now.
     */
    private function lockEscrow(Study $study): bool
    {
        return true;
    }
}
