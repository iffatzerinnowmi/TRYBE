<?php

namespace App\Http\Controllers;

use App\Models\Study;
use App\Models\User;
use App\Enums\UserRole;

/**
 * Temporary page that proves two things at once:
 *   1. Tailwind + the TRYBE design tokens compiled correctly.
 *   2. Section 1's seeded database is readable through Eloquent.
 *
 * Delete this controller, its view and its route once Section 5 is done.
 */
class DesignCheckController extends Controller
{
    public function index()
    {
        // Real counts from the seeded database — nothing typed by hand.
        $stats = [
            'users'        => User::count(),
            'participants' => User::where('role', UserRole::PARTICIPANT)->count(),
            'researchers'  => User::where('role', UserRole::RESEARCHER)->count(),
            'studies'      => Study::count(),
        ];

        $sampleUsers = User::query()
            ->with('participantProfile')
            ->latest('id')
            ->take(5)
            ->get();

        return view('design-check', compact('stats', 'sampleUsers'));
    }
}
