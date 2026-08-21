<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;

class KarmaController extends Controller
{
    /** Karma Credits — participants. */
    public function index()
    {
        abort_if(! auth()->user()->participantProfile, 404, 'No participant profile found for this account.');

        return view('participant.karma');
    }

    
}