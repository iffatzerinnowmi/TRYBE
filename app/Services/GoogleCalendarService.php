<?php

namespace App\Services;

use App\Models\StudySlotBooking;
use App\Models\User;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\EventAttendee;
use Illuminate\Support\Str;

class GoogleCalendarService
{
    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function client(): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->setScopes([Calendar::CALENDAR_EVENTS]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    public function authorizationUrl(string $state): string
    {
        $client = $this->client();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function quickAddUrl(StudySlotBooking $booking): string
    {
        $booking->loadMissing(['study', 'slot']);

        $parameters = [
            'action' => 'TEMPLATE',
            'text' => 'TRYBE study: ' . $booking->study->title,
            'details' => "Study session booked through TRYBE.\n\n" . ($booking->study->description ?? ''),
            'dates' => $booking->slot->starts_at->copy()->utc()->format('Ymd\\THis\\Z')
                . '/' . $booking->slot->ends_at->copy()->utc()->format('Ymd\\THis\\Z'),
        ];

        return 'https://calendar.google.com/calendar/render?' . http_build_query($parameters);
    }

    public function storeToken(User $user, array $token): void
    {
        $user->update([
            'google_calendar_token' => $token['access_token'] ?? null,
            'google_calendar_refresh_token' => $token['refresh_token'] ?? $user->google_calendar_refresh_token,
            'google_calendar_token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
        ]);
    }

    public function createBookingEvent(User $user, StudySlotBooking $booking): string
    {
        $client = $this->authenticatedClient($user);
        $calendar = new Calendar($client);
        $booking->loadMissing(['study.researcher', 'slot', 'participant']);

        $event = new Event([
            'summary' => 'TRYBE study: ' . $booking->study->title,
            'description' => "Study session booked through TRYBE.\n\n" . ($booking->study->description ?? ''),
            'start' => new EventDateTime([
                'dateTime' => $booking->slot->starts_at->toRfc3339String(),
                'timeZone' => config('app.timezone', 'UTC'),
            ]),
            'end' => new EventDateTime([
                'dateTime' => $booking->slot->ends_at->toRfc3339String(),
                'timeZone' => config('app.timezone', 'UTC'),
            ]),
            'attendees' => collect([$booking->participant, $booking->study->researcher])
                ->filter(fn ($person) => filled($person?->email))
                ->unique('email')
                ->map(fn ($person) => new EventAttendee(['email' => $person->email]))
                ->values()
                ->all(),
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 24 * 60],
                    ['method' => 'popup', 'minutes' => 60],
                ],
            ],
        ]);

        $created = $calendar->events->insert('primary', $event, ['sendUpdates' => 'all']);

        $calendarLink = $created->getHtmlLink();
        $booking->update(['google_calendar_event_id' => $calendarLink]);

        return $calendarLink;
    }

    private function authenticatedClient(User $user): Client
    {
        $client = $this->client();
        $client->setAccessToken([
            'access_token' => $user->google_calendar_token,
            'refresh_token' => $user->google_calendar_refresh_token,
            'expires_in' => max(0, now()->diffInSeconds($user->google_calendar_token_expires_at, false)),
            'created' => now()->timestamp,
        ]);

        if ($client->isAccessTokenExpired() && $user->google_calendar_refresh_token) {
            $token = $client->fetchAccessTokenWithRefreshToken($user->google_calendar_refresh_token);
            $this->storeToken($user, $token);
            $client->setAccessToken($token);
        }

        return $client;
    }
}
