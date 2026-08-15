<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Study;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\StudyMatchingService;

class StudyInvitationController extends Controller
{
    public function store(Study $study, User $participant, StudyMatchingService $matching)
    {
        $researcher = auth()->user();

        abort_unless($researcher->id === $study->researcher_id, 403);
        abort_unless($participant->role === UserRole::PARTICIPANT, 422, 'Only participants can be invited.');
        abort_unless($participant->participantProfile, 404, 'Participant profile not found.');

        $match = $matching->assessUserForStudy($participant, $matching->criteriaForStudy($study));

        abort_if($match['score'] < $matching->strongThreshold(), 422, 'This participant is not a strong match for the study.');

        $notification = UserNotification::firstOrNew([
            'user_id' => $participant->id,
            'type' => 'studies',
            'title' => $matching->invitationTitle($study),
        ]);

        $isNew = ! $notification->exists;

        $notification->fill([
            'icon' => '✉️',
            'body' => $researcher->name . ' invited you to "' . $study->title . '" because your profile is a strong match (' . $match['score'] . '/100).',
            'url' => $matching->invitationUrl($study),
        ])->save();

        if ($isNew) {
            return back()->with('status', 'Invitation sent to ' . $participant->name . '.');
        }

        return back()->with('status', 'That participant already has this invitation.');
    }
}
