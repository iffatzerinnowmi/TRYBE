<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Study;
use App\Models\StudySlot;
use App\Models\StudySlotBooking;
use App\Models\StudyParticipation;
use App\Enums\PipelineStage;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleApiController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request, Study $study): JsonResponse
    {
        $slots = StudySlot::query()
            ->where('study_id', $study->id)
            ->orderBy('starts_at')
            ->get()
            ->map(function (StudySlot $slot) {
                $booked = $slot->bookings()->where('status', 'booked')->count();
                $slot->booked_count = $booked;
                $currentBooking = $slot->bookings()
                    ->where('participant_id', auth()->id())
                    ->where('status', 'booked')
                    ->first();

                return [
                    'id' => $slot->id,
                    'study_id' => $slot->study_id,
                    'starts_at' => $slot->starts_at->toISOString(),
                    'ends_at' => $slot->ends_at->toISOString(),
                    'capacity' => $slot->capacity,
                    'booked_count' => $booked,
                    'remaining' => max(0, $slot->capacity - $booked),
                    'available' => $booked < $slot->capacity,
                    'status' => $booked >= $slot->capacity ? 'full' : 'open',
                    'booking_id' => $currentBooking?->id,
                    'google_calendar_event_id' => $currentBooking?->google_calendar_event_id,
                ];
            });

        return response()->json(['data' => ['slots' => $slots]], 200);
    }

    public function store(Request $request, Study $study): JsonResponse
    {
        $user = $request->user();

        if (! $user || ($user->id !== $study->researcher_id && $user->role?->value !== 'admin')) {
            return response()->json(['message' => 'You can only create slots for your own study.'], 403);
        }

        $data = $request->validate([
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $slot = StudySlot::create([
            'study_id' => $study->id,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'capacity' => $data['capacity'],
            'booked_count' => 0,
        ]);

        return response()->json([
            'message' => 'Slot created.',
            'data' => [
                'id' => $slot->id,
                'study_id' => $slot->study_id,
                'starts_at' => $slot->starts_at->toISOString(),
                'ends_at' => $slot->ends_at->toISOString(),
                'capacity' => $slot->capacity,
                'booked_count' => 0,
                'remaining' => $slot->capacity,
                'available' => true,
            ],
        ], 201);
    }

    public function book(Request $request, Study $study, $slotId): JsonResponse
    {
        $user = $request->user();

        if (! $user || $user->role?->value !== 'participant') {
            return response()->json(['message' => 'Only participants can book a slot.'], 403);
        }

        $slot = StudySlot::where('study_id', $study->id)->findOrFail($slotId);
        $bookedCount = $slot->bookings()->where('status', 'booked')->count();

        if ($bookedCount >= $slot->capacity) {
            return response()->json(['message' => 'This slot is full.'], 422);
        }

        $existing = $slot->bookings()->where('participant_id', $user->id)->first();
        if ($existing && $existing->status === 'booked') {
            return response()->json([
                'message' => 'You already booked this slot.',
                'data' => ['booking' => $existing],
            ], 422);
        }

        $booking = StudySlotBooking::create([
            'study_id' => $study->id,
            'study_slot_id' => $slot->id,
            'participant_id' => $user->id,
            'status' => 'booked',
            'booked_at' => now(),
        ]);

        StudyParticipation::updateOrCreate(
            ['study_id' => $study->id, 'participant_id' => $user->id],
            ['stage' => PipelineStage::SCHEDULED]
        );

        $slot->update([
            'booked_count' => $slot->fresh()->bookings()->where('status', 'booked')->count(),
        ]);

        $this->notifications->send(
            $user,
            'studies',
            'Session confirmed',
            "You booked a session for {$slot->starts_at->format('M d, Y \a\t h:i A')} with {$study->researcher->name}.",
            route('studies.show', $study)
        );

        if ($slot->starts_at->diffInHours(now()) <= 24) {
            $this->notifications->send(
                $user,
                'studies',
                'Session reminder',
                "Reminder: your session starts at {$slot->starts_at->format('M d, Y \a\t h:i A')}.",
                route('studies.show', $study)
            );
        }

        return response()->json([
            'message' => 'Slot booked.',
            'data' => [
                'slot' => [
                    'id' => $slot->id,
                    'starts_at' => $slot->starts_at->toISOString(),
                    'ends_at' => $slot->ends_at->toISOString(),
                    'remaining' => max(0, $slot->capacity - $slot->fresh()->bookings()->where('status', 'booked')->count()),
                ],
                'booking' => $booking->fresh(),
            ],
        ], 200);
    }
}
