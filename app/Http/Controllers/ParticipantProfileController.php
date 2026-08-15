<?php

namespace App\Http\Controllers;

use App\Models\ParticipantProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Participant Profile Builder.
 *
 * Two forms on one page:
 *   - Account   → columns on the users table
 *   - Profile   → columns on participant_profiles
 *
 * The "profile strength" ring is worked out here, in PHP, from the saved row.
 * The page also updates it live in the browser as you type, but the number you
 * see on load is the real, saved one.
 */
class ParticipantProfileController extends Controller
{
    /** The fields that count towards profile strength, in display order. */
    private const STRENGTH_FIELDS = [
        'age', 'gender', 'occupation', 'health_background',
        'interests', 'skills', 'linkedin', 'github',
    ];

    public function edit()
    {
        $user = auth()->user();

        $profile = $user->participantProfile
            ?? ParticipantProfile::create(['user_id' => $user->id]);

        return view('participant.profile', [
            'user'     => $user,
            'profile'  => $profile,
            'strength' => $this->strength($profile),
            'genders'  => ['Woman', 'Man', 'Non-binary', 'Prefer not to say'],
            'fields'   => self::STRENGTH_FIELDS,
        ]);
    }

    /** Save the demographic and background fields. */
    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'age'               => ['nullable', 'integer', 'min:16', 'max:120'],
            'gender'            => ['nullable', 'string', 'max:40'],
            'occupation'        => ['nullable', 'string', 'max:120'],
            'health_background' => ['nullable', 'string', 'max:1000'],
            'interests'         => ['nullable', 'string', 'max:500'],
            'skills'            => ['nullable', 'string', 'max:500'],
            'linkedin'          => ['nullable', 'string', 'max:180'],
            'github'            => ['nullable', 'string', 'max:180'],
        ]);

        $user->participantProfile->update($data);

        return back()->with('status', 'Profile saved — your matches just got sharper.');
    }

    /** Save name, location, email. */
    public function updateAccount(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'email'    => ['required', 'email', 'max:180',
                           Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return back()->with('status', 'Account details updated.');
    }

    /** Change password, after proving you know the current one. */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($request->input('current_password'), auth()->user()->password)) {
            return back()->withErrors([
                'current_password' => 'That is not your current password.',
            ]);
        }

        auth()->user()->update(['password' => $request->input('password')]);

        return back()->with('status', 'Password changed.');
    }

    /** What percentage of the optional fields are filled in. */
    private function strength(ParticipantProfile $profile): int
    {
        $filled = 0;

        foreach (self::STRENGTH_FIELDS as $field) {
            if (filled($profile->{$field})) {
                $filled++;
            }
        }

        return (int) round($filled / count(self::STRENGTH_FIELDS) * 100);
    }
}
