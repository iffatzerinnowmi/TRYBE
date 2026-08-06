<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Endorsement;
use App\Models\User;
use App\Services\EndorsementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * FEATURE 3 — Researcher endorsements.
 */
class EndorsementController extends Controller
{
    public function __construct(private EndorsementService $endorsements) {}

    public function index(Request $request)
    {
        $researcher = auth()->user();

        $pending = $this->endorsements->pendingFor($researcher);

        // The person shown in the big form: either the one clicked, or the
        // first in the queue.
        $selected = $request->filled('participant')
            ? $pending->firstWhere('participant_id', (int) $request->query('participant'))
            : $pending->first();

        $standing = null;

        if ($selected) {
            $participant = $selected->participant;
            $count = $this->endorsements->distinctEndorserCount($participant);

            $standing = [
                'participant' => $participant,
                'count'       => $count,
                'required'    => $this->endorsements->required(),
                'remaining'   => max(0, $this->endorsements->required() - $count),
                'verified'    => (bool) $participant->participantProfile?->is_verified_participant,
                'tags'        => Endorsement::where('participant_id', $participant->id)
                    ->pluck('tags')
                    ->flatten()
                    ->countBy()
                    ->sortDesc(),
            ];
        }

        $given = Endorsement::with('participant')
            ->where('researcher_id', $researcher->id)
            ->latest('id')
            ->take(6)
            ->get();

        return view('researcher.endorsements', [
            'pending'     => $pending,
            'selected'    => $selected,
            'standing'    => $standing,
            'given'       => $given,
            'allTags'     => Endorsement::TAGS,
            'maxTags'     => 3,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'participant_id' => ['required', 'integer', 'exists:users,id'],
            'study_id'       => ['nullable', 'integer', 'exists:studies,id'],
            'tags'           => ['required', 'array', 'min:1', 'max:3'],
            'tags.*'         => ['string', Rule::in(Endorsement::TAGS)],
        ]);

        $participant = User::where('id', $data['participant_id'])
            ->where('role', UserRole::PARTICIPANT)
            ->firstOrFail();

        // The database has a unique index on (participant, researcher, study),
        // but checking here gives a friendly message instead of a 500.
        $duplicate = Endorsement::where('researcher_id', auth()->id())
            ->where('participant_id', $participant->id)
            ->where('study_id', $data['study_id'] ?? null)
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'tags' => 'You have already endorsed this participant for that session.',
            ]);
        }

        $result = $this->endorsements->endorse(
            auth()->user(),
            $participant,
            $data['study_id'] ?? null,
            $data['tags']
        );

        $message = $result['justVerified']
            ? $participant->name . ' just reached ' . $result['required']
              . ' endorsements and is now a Verified Participant.'
            : 'Endorsement recorded — ' . $participant->name . ' is at '
              . $result['count'] . ' of ' . $result['required'] . '.';

        return redirect()->route('researcher.endorsements')->with('status', $message);
    }
}
