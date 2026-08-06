<?php

namespace App\Http\Controllers;

use App\Models\ResearcherProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Researcher Profile Builder.
 *
 * Same shape as the participant one, but these fields are what participants
 * read before deciding whether to apply to a study — so completeness here
 * affects recruitment, not matching.
 */
class ResearcherProfileController extends Controller
{
    private const STRENGTH_FIELDS = [
        'title', 'institution', 'department', 'bio',
        'research_areas', 'linkedin', 'institutional_email',
    ];

    public function edit()
    {
        $user = auth()->user();

        $profile = $user->researcherProfile
            ?? ResearcherProfile::create([
                'user_id'             => $user->id,
                'institutional_email' => $user->email,
            ]);

        return view('researcher.profile', [
            'user'     => $user,
            'profile'  => $profile,
            'strength' => $this->strength($profile),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'title'               => ['nullable', 'string', 'max:120'],
            'institution'         => ['nullable', 'string', 'max:180'],
            'department'          => ['nullable', 'string', 'max:180'],
            'bio'                 => ['nullable', 'string', 'max:1200'],
            'research_areas'      => ['nullable', 'string', 'max:400'],
            'linkedin'            => ['nullable', 'string', 'max:180'],
            'institutional_email' => ['nullable', 'email', 'max:180'],
        ]);

        auth()->user()->researcherProfile->update($data);

        return back()->with('status', 'Profile saved — your public page is looking sharp.');
    }

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

    private function strength(ResearcherProfile $profile): int
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
