<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrganizationDashboardController;
use App\Http\Controllers\ParticipantDashboardController;
use App\Http\Controllers\ResearcherDashboardController;

use App\Http\Controllers\PageController;

Route::get('/', [PageController::class, 'landing'])->name('landing');



/* =============================================================================
   PASTE THESE LINES INTO routes/web.php
   Put the "use" line at the very top with the other use statements,
   and the Route::get line at the bottom of the file.
   ============================================================================= */





/* ---- Guest only: you cannot see these while logged in ---- */
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/signup',  [AuthController::class, 'showSignup'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup']);
});

/* ---- Logged in only ---- */
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout',   [AuthController::class, 'logout'])->name('logout');
});
Route::middleware('auth')->group(function () {

    // The signpost: works out your role and forwards you.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // One dashboard per role, each locked to that role.
    Route::get('/participant', [ParticipantDashboardController::class, 'index'])
        ->middleware('role:participant')
        ->name('participant.dashboard');

    Route::get('/researcher', [ResearcherDashboardController::class, 'index'])
        ->middleware('role:researcher')
        ->name('researcher.dashboard');

    Route::get('/organization', [OrganizationDashboardController::class, 'index'])
        ->middleware('role:organization')
        ->name('organization.dashboard');

    // Admin: the overview plus the two decision buttons.
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::post('/verifications/{verificationRequest}/approve',
            [AdminDashboardController::class, 'approve'])->name('verifications.approve');

        Route::post('/verifications/{verificationRequest}/reject',
            [AdminDashboardController::class, 'reject'])->name('verifications.reject');
    });
});


/* =============================================================================
   bootstrap/app.php

   The 'role:' middleware needs a name before routes can use it.
   Find the ->withMiddleware(...) block and add the alias line inside it,
   so it reads like this:
   ============================================================================= */

/*
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
*/
