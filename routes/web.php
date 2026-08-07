<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrganizationDashboardController;
use App\Http\Controllers\ParticipantDashboardController;
use App\Http\Controllers\ParticipantStudyInvitationController;
use App\Http\Controllers\ResearcherDashboardController;
use App\Http\Controllers\StudyInvitationController;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\ReliabilityController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\EndorsementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParticipantProfileController;
use App\Http\Controllers\ResearcherProfileController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\StudyController;

Route::get('/studies/create', [StudyController::class, 'create']);
Route::post('/studies', [StudyController::class, 'store'])->name('studies.store');

Route::get('/', [PageController::class, 'landing'])->name('landing');



/* =============================================================================
   
   ============================================================================= */





/* ---- Guest only: you cannot see these while logged in ---- */
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/signup',  [AuthController::class, 'showSignup'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup']);
});
/* ---- FEATURE: Incentive Variety Settings (study creation) ---- */
Route::middleware('role:researcher,organization')->group(function () {
    Route::get('/studies/create', [StudyController::class, 'create'])->name('studies.create');
    Route::post('/studies', [StudyController::class, 'store'])->name('studies.store');
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

        Route::post(
            '/verifications/{verificationRequest}/approve',
            [AdminDashboardController::class, 'approve']
        )->name('verifications.approve');

        Route::post(
            '/verifications/{verificationRequest}/reject',
            [AdminDashboardController::class, 'reject']
        )->name('verifications.reject');
        Route::get(
            '/verifications/{verificationRequest}/document/{type}',
            [AdminDashboardController::class, 'document']
        )->name('verifications.document');
    });
});

Route::middleware('role:participant')->prefix('participant')->name('participant.')->group(function () {

    // FEATURE 1 — Credentialing
    Route::get('/credentials', [CredentialController::class, 'index'])
        ->name('credentials');
    Route::post('/credentials/recalculate', [CredentialController::class, 'recalculate'])
        ->name('credentials.recalculate');

    // FEATURE 2 — Reliability score
    Route::get('/reliability', [ReliabilityController::class, 'index'])
        ->name('reliability');
    Route::post('/reliability/recalculate', [ReliabilityController::class, 'recalculate'])
        ->name('reliability.recalculate');

    Route::get('/studies/{study}/invitation', [ParticipantStudyInvitationController::class, 'show'])
        ->name('studies.invitation');
    Route::post('/studies/{study}/invitation/accept', [ParticipantStudyInvitationController::class, 'accept'])
        ->name('studies.invitation.accept');
    Route::post('/studies/{study}/invitation/decline', [ParticipantStudyInvitationController::class, 'decline'])
        ->name('studies.invitation.decline');
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
/* ---- FEATURE 3: endorsements (researchers only) ---- */
Route::middleware('role:researcher')->prefix('researcher')->name('researcher.')->group(function () {
    Route::get('/endorsements', [EndorsementController::class, 'index'])
        ->name('endorsements');
    Route::post('/endorsements', [EndorsementController::class, 'store'])
        ->name('endorsements.store');
    Route::post('/studies/{study}/invite/{participant}', [StudyInvitationController::class, 'store'])
        ->name('studies.invite');
});

/* ---- FEATURE 4: notification centre (every logged-in role) ---- */
Route::prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('/preferences', [NotificationController::class, 'updatePreferences'])->name('preferences');
    Route::post('/subscribe', [NotificationController::class, 'subscribe'])->name('subscribe');
    Route::post('/unsubscribe', [NotificationController::class, 'unsubscribe'])->name('unsubscribe');
    Route::post('/test', [NotificationController::class, 'test'])->name('test');
    Route::post('/read', [NotificationController::class, 'markAllRead'])->name('read');
    Route::post('/read/{notification}', [NotificationController::class, 'markRead'])->name('readOne');
});

/* ---- Participant profile builder ---- */
Route::middleware('role:participant')->prefix('participant')->name('participant.')->group(function () {
    Route::get('/profile', [ParticipantProfileController::class, 'edit'])->name('profile');
    Route::patch('/profile', [ParticipantProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/account', [ParticipantProfileController::class, 'updateAccount'])->name('profile.account');
    Route::patch('/profile/password', [ParticipantProfileController::class, 'updatePassword'])->name('profile.password');
});

/* ---- Researcher profile builder ---- */
Route::middleware('role:researcher')->prefix('researcher')->name('researcher.')->group(function () {
    Route::get('/profile', [ResearcherProfileController::class, 'edit'])->name('profile');
    Route::patch('/profile', [ResearcherProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/account', [ResearcherProfileController::class, 'updateAccount'])->name('profile.account');
    Route::patch('/profile/password', [ResearcherProfileController::class, 'updatePassword'])->name('profile.password');
});

/* ---- Verification: researchers AND organizations ---- */
Route::middleware('role:researcher,organization')->group(function () {
    Route::get('/verification', [VerificationController::class, 'index'])->name('verification.index');
    Route::post('/verification', [VerificationController::class, 'store'])->name('verification.store');
});
