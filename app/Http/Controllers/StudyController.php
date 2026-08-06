<?php

namespace App\Http\Controllers;

use App\Enums\IncentiveType;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Models\Study;
use App\Models\StudyParticipation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The study listing board.
 *
 * One route (/studies) serves two very different screens depending on who
 * is looking at it:
 *   - a participant gets a filterable feed of open studies to apply to
 *   - a researcher / organization gets a management list of their own posts
 *
 * FIELD NAMES ARE FROZEN for the create/edit form the same way AuthController
 * freezes signup fields: title, description, category, eligibility_criteria,
 * method, duration_minutes, incentive_type, compensation_amount, slots, deadline.
 */
class StudyController extends Controller
{
    /* =====================================================================
       BOARD (role-aware)
       ===================================================================== */

    public function index(Request $request)
    {
        $user = $request->user();

        return match ($user->role) {
            UserRole::PARTICIPANT => $this->browse($request, $user),
            UserRole::RESEARCHER, UserRole::ORGANIZATION => $this->manage($request, $user),
            // Admin (or anyone else) gets a read-only look at the same feed.
            default => $this->browse($request, $user),
        };
    }

    /** Participant side: browse open studies, filtered by interest/qualification. */
    private function browse(Request $request, $user)
    {
        $filters = [
            'q'              => trim((string) $request->query('q', '')),
            'category'       => $request->query('category', ''),
            'method'         => $request->query('method', ''),
            'incentive_type' => $request->query('incentive_type', ''),
            'sort'           => $request->query('sort', 'newest'),
        ];

        $studies = Study::query()
            ->with('researcher')
            ->withCount('participations')
            ->where('status', StudyStatus::OPEN)
            ->where(fn ($q) => $q->whereNull('deadline')->orWhereDate('deadline', '>=', now()->toDateString()))
            ->when($filters['q'] !== '', fn ($q) => $q->where(
                fn ($qq) => $qq->where('title', 'like', "%{$filters['q']}%")
                    ->orWhere('description', 'like', "%{$filters['q']}%")
                    ->orWhere('category', 'like', "%{$filters['q']}%")
            ))
            ->when($filters['category'] !== '', fn ($q) => $q->where('category', $filters['category']))
            ->when($filters['method'] !== '', fn ($q) => $q->where('method', $filters['method']))
            ->when($filters['incentive_type'] !== '', fn ($q) => $q->where('incentive_type', $filters['incentive_type']))
            ->when($filters['sort'] === 'deadline', fn ($q) => $q->orderByRaw('deadline IS NULL, deadline asc'))
            ->when($filters['sort'] === 'compensation', fn ($q) => $q->orderByDesc('compensation_amount'))
            ->when($filters['sort'] === 'newest', fn ($q) => $q->latest('id'))
            ->paginate(9)
            ->withQueryString();

        // Category chips are built from what's actually posted, not hard-coded.
        $categories = Study::query()
            ->where('status', StudyStatus::OPEN)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $profile = $user->role === UserRole::PARTICIPANT ? $user->participantProfile : null;

        // Which of the listed studies has this participant already applied to.
        $appliedStudyIds = $profile
            ? StudyParticipation::where('participant_id', $user->id)->pluck('study_id')
            : collect();

        $unlockTarget = (int) config('platform.free_forms_to_unlock_paid');
        $completedCount = $profile?->completed_studies_count ?? 0;
        $paidUnlocked = $completedCount >= $unlockTarget;

        return view('studies.browse', compact(
            'studies', 'filters', 'categories', 'appliedStudyIds',
            'paidUnlocked', 'unlockTarget', 'completedCount', 'profile'
        ));
    }

    /** Researcher / organization side: manage the listings you posted. */
    private function manage(Request $request, $user)
    {
        $status = $request->query('status', '');

        $studies = Study::query()
            ->withCount('participations')
            ->where('researcher_id', $user->id)
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $statuses = collect(StudyStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]);

        return view('studies.manage', compact('studies', 'status', 'statuses'));
    }

    /* =====================================================================
       CREATE / PUBLISH
       ===================================================================== */

    public function create()
    {
        return view('studies.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $study = Study::create($data + [
            'researcher_id'       => $request->user()->id,
            'status'              => StudyStatus::OPEN,
            'participants_count'  => 0,
        ]);

        return redirect()->route('studies.show', $study)
            ->with('status', 'Your study is live on the listing board.');
    }

    /* =====================================================================
       SHOW
       ===================================================================== */

    public function show(Request $request, Study $study)
    {
        $study->load('researcher.researcherProfile')->loadCount('participations');

        $user = $request->user();
        $myApplication = null;
        $canApply = false;
        $blockReason = null;

        if ($user->role === UserRole::PARTICIPANT) {
            $myApplication = StudyParticipation::query()
                ->where('study_id', $study->id)
                ->where('participant_id', $user->id)
                ->first();

            $blockReason = $this->applicationBlockReason($study, $user, $myApplication);
            $canApply = ! $myApplication && ! $blockReason;
        }

        return view('studies.show', compact('study', 'myApplication', 'canApply', 'blockReason'));
    }

    /* =====================================================================
       EDIT / UPDATE / DELETE  (owner only)
       ===================================================================== */

    public function edit(Request $request, Study $study)
    {
        abort_unless($study->isOwnedBy($request->user()), 403, 'You can only edit your own study listings.');

        return view('studies.edit', $this->formOptions() + ['study' => $study]);
    }

    public function update(Request $request, Study $study)
    {
        abort_unless($study->isOwnedBy($request->user()), 403, 'You can only edit your own study listings.');

        $data = $this->validated($request);

        // Status is only editable here — a brand new listing is always OPEN.
        $validStatuses = array_column(StudyStatus::cases(), 'value');
        $data['status'] = in_array($request->input('status'), $validStatuses, true)
            ? $request->input('status')
            : $study->status->value;

        $study->update($data);

        return redirect()->route('studies.show', $study)->with('status', 'Listing updated.');
    }

    public function destroy(Request $request, Study $study)
    {
        abort_unless($study->isOwnedBy($request->user()), 403, 'You can only remove your own study listings.');

        $study->delete();

        return redirect()->route('studies.index')->with('status', 'Listing removed.');
    }

    /* =====================================================================
       APPLY  (participant only)
       ===================================================================== */

    public function apply(Request $request, Study $study)
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::PARTICIPANT, 403, 'Only participants can apply to studies.');

        $existing = StudyParticipation::query()
            ->where('study_id', $study->id)
            ->where('participant_id', $user->id)
            ->first();

        if ($existing) {
            return back()->with('status', "You've already applied to this study.");
        }

        if ($reason = $this->applicationBlockReason($study, $user, null)) {
            return back()->withErrors(['apply' => $reason]);
        }

        StudyParticipation::create([
            'study_id'       => $study->id,
            'participant_id' => $user->id,
            'stage'          => PipelineStage::APPLIED,
        ]);

        return redirect()->route('studies.show', $study)
            ->with('status', 'Application submitted — good luck!');
    }

    /* =====================================================================
       HELPERS
       ===================================================================== */

    /** Shared validation rules for both the create and the edit form. */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title'                => ['required', 'string', 'max:180'],
            'description'          => ['required', 'string', 'max:5000'],
            'category'             => ['nullable', 'string', 'max:80'],
            'eligibility_criteria' => ['nullable', 'string', 'max:3000'],
            'method'                => ['required', Rule::in(['online', 'in_person'])],
            'duration_minutes'      => ['nullable', 'integer', 'min:1', 'max:1440'],
            'incentive_type'        => ['required', Rule::in(array_column(IncentiveType::cases(), 'value'))],
            'compensation_amount'   => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'slots'                 => ['required', 'integer', 'min:1', 'max:100000'],
            'deadline'              => ['nullable', 'date', 'after_or_equal:today'],
        ]);
    }

    /** Dropdown options shared by the create and edit views. */
    private function formOptions(): array
    {
        return [
            'methods' => ['online' => 'Online', 'in_person' => 'In person'],
            'incentiveTypes' => collect(IncentiveType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]),
        ];
    }

    /**
     * Why a participant can't apply right now, or null if they're clear to.
     * Centralised so show() and apply() never disagree with each other.
     */
    private function applicationBlockReason(Study $study, $user, ?StudyParticipation $existing): ?string
    {
        if ($existing) {
            return null;
        }

        if ($study->status !== StudyStatus::OPEN) {
            return 'This study is not currently open for applications.';
        }

        if ($study->deadline && $study->deadline->isPast()) {
            return 'The application deadline has passed.';
        }

        $taken = $study->participations_count ?? $study->participations()->count();
        if ($taken >= $study->slots) {
            return 'All slots for this study have been filled.';
        }

        // Free-to-paid unlock rule: paid studies stay locked until the
        // participant has volunteered for the platform's threshold count.
        if ($study->incentive_type->requiresEscrow()) {
            $profile = $user->participantProfile;
            $unlockTarget = (int) config('platform.free_forms_to_unlock_paid');
            $completed = $profile?->completed_studies_count ?? 0;

            if ($completed < $unlockTarget) {
                return "Complete {$unlockTarget} volunteer studies to unlock paid studies — you're at {$completed}.";
            }
        }

        return null;
    }
}
