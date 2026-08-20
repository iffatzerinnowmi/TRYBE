<?php

namespace App\Http\Controllers;

use App\Models\StudySlotBooking;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleCalendarController extends Controller
{
    public function __construct(private GoogleCalendarService $calendar) {}

    public function connect(Request $request, StudySlotBooking $booking): RedirectResponse
    {
        if (! $this->calendar->isConfigured()) {
            abort_unless($booking->participant_id === $request->user()->id, 403,
                'Only the participant who booked this session can add it to their calendar.');

            return redirect()->away($this->calendar->quickAddUrl($booking));
        }

        abort_unless($booking->participant_id === $request->user()->id, 403,
            'Only the participant who booked this session can add it to their calendar.');

        abort_if($booking->google_calendar_event_id, 409,
            'This session is already on your Google Calendar.');

        $state = Str::random(40);
        $request->session()->put('google_calendar_state', [
            'value' => $state,
            'booking_id' => $booking->id,
        ]);

        return redirect()->away($this->calendar->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $stored = $request->session()->pull('google_calendar_state');

        abort_unless($stored && hash_equals($stored['value'], (string) $request->query('state')), 419,
            'The Google Calendar authorization expired. Please try again.');

        if ($request->filled('error')) {
            return redirect()->route('studies.show', $this->bookingId($stored))
                ->with('status', 'Google Calendar connection was cancelled.');
        }

        $token = $this->calendar->client()->fetchAccessTokenWithAuthCode((string) $request->query('code'));
        abort_if(isset($token['error']), 502, 'Google Calendar authorization failed.');

        $user = $request->user();
        $this->calendar->storeToken($user, $token);

        $booking = StudySlotBooking::with('study')->findOrFail($stored['booking_id']);
        abort_unless($booking->participant_id === $user->id, 403);

        $link = $this->calendar->createBookingEvent($user, $booking);

        return redirect()->route('studies.show', $booking->study_id)
            ->with('status', 'Session added to Google Calendar. Open it here: ' . $link);
    }

    private function bookingId(array $state): int
    {
        return (int) StudySlotBooking::whereKey($state['booking_id'])->value('study_id');
    }
}
