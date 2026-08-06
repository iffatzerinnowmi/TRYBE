<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;

/**
 * /dashboard is a signpost, not a page.
 *
 * It looks at who is logged in and sends them to the dashboard built for
 * their role. Each role then has its own controller, so no single class ends
 * up doing four unrelated jobs.
 */
class DashboardController extends Controller
{
    public function index()
    {
        return redirect()->route(match (auth()->user()->role) {
            UserRole::PARTICIPANT  => 'participant.dashboard',
            UserRole::RESEARCHER   => 'researcher.dashboard',
            UserRole::ORGANIZATION => 'organization.dashboard',
            UserRole::ADMIN        => 'admin.dashboard',
        });
    }
}
