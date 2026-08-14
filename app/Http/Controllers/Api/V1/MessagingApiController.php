<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Study;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessagingApiController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function sendToStage(Request $request, Study $study): JsonResponse
    {
        $data = $request->validate([
            'stage' => ['required', 'string'],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:3000'],
        ]);

        // Read participants in the stage and message them via NotificationService.
        $participants = \App\Models\StudyParticipation::where('study_id', $study->id)
            ->where('stage', $data['stage'])
            ->pluck('participant_id');

        $sent = 0;

        foreach ($participants as $pid) {
            $user = User::find($pid);

            if (! $user) continue;

            $this->notifications->send($user, 'studies', $data['subject'], $data['body'], route('studies.show', $study));
            $sent++;
        }

        return response()->json(['message' => 'Messages queued.', 'sent' => $sent], 200);
    }
}
