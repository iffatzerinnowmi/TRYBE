<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\EndorsementController;
use App\Http\Controllers\KarmaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationDashboardController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ParticipantDashboardController;
use App\Http\Controllers\ParticipantProfileController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReliabilityController;
use App\Http\Controllers\ResearcherDashboardController;
use App\Http\Controllers\ResearcherParticipantController;
use App\Http\Controllers\ResearcherProfileController;
use App\Http\Controllers\StudyController;
use App\Http\Controllers\VerificationController;

/*
|------------------------------------------------------------------------------
| TRYBE — web routes
|------------------------------------------------------------------------------
|
| These serve HTML PAGES. Data comes from routes/api.php.
|
| TEAM RULES
|
| 1. Every route that is not the landing page, login or signup belongs inside
|    a middleware group. A route with no middleware is open to the whole
|    internet — that is how /studies/create ended up public.
|
| 2. Put 'auth' before 'role:'. The role middleware reads the logged-in user,
|    so a guest reaching it crashes instead of being sent to the login page.
|
| 3. Fixed segments must be registered BEFORE wildcards. /studies/create has
|    to come before /studies/{study}, or Laravel treats "create" as a study id.
|
| 4. Never register the same URL twice. Laravel silently uses whichever came
|    first, which is almost never the one you meant.
|
| 5. Run `php artisan route:list` after every merge.
|
*/


/* =============================================================================
   PUBLIC
   ============================================================================= */

Route::get('/', [PageController::class, 'landing'])->name('landing');


/* =============================================================================
   GUEST ONLY — not reachable once you are logged in
   ============================================================================= */

Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    

    Route::get('/signup',  [AuthController::class, 'showSignup'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup']);
});


/* =============================================================================
   ANY LOGGED-IN USER
   ============================================================================= */

Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Works out your role and forwards you to the right dashboard.
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

    /* ---- Study creation (Member 3 — incentive variety settings) ----
       Registered before /studies/{study} so "create" is not read as an id. */
    Route::middleware('role:researcher,organization')->group(function () {
        Route::get('/studies/create', [StudyController::class, 'create'])->name('studies.create');
        Route::post('/studies',       [StudyController::class, 'store'])->name('studies.store');
    });

    /* ---- Browsing studies ---- */
    Route::get('/studies',          [StudyController::class, 'index'])->name('studies.index');
    Route::get('/studies/{study}',  [StudyController::class, 'show'])->name('studies.show');

    
});


/* =============================================================================
   ADMIN
   ============================================================================= */

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::post('/verifications/{verificationRequest}/approve', [AdminDashboardController::class, 'approve'])
        ->name('verifications.approve');

    Route::post('/verifications/{verificationRequest}/reject', [AdminDashboardController::class, 'reject'])
        ->name('verifications.reject');

    Route::get('/verifications/{verificationRequest}/document/{type}', [AdminDashboardController::class, 'document'])
        ->name('verifications.document');
});


/* =============================================================================
   PARTICIPANT
   ============================================================================= */

Route::middleware(['auth', 'role:participant'])->prefix('participant')->name('participant.')->group(function () {

    /* ---- FEATURE 1 — Credentialing (API-DRIVEN) ----
       One GET only. The page fetches from /api/v1/participants/{user}/credentials
       and recalculates through the API, so no web POST route exists here. */
    Route::get('/credentials', [CredentialController::class, 'index'])
        ->name('credentials');
    

    /* ---- FEATURE 2 — Reliability score (API-DRIVEN) ----
       One GET only. The page fetches its data from
       /api/v1/participants/{user}/reliability and recalculates through
       POST /api/v1/participants/{user}/reliability/recalculate, so no web
       POST route exists here any more. */
    Route::get('/reliability', [ReliabilityController::class, 'index'])
        ->name('reliability');

    /* ---- FEATURE — Study invitations (Member 4, API-DRIVEN) ----
       One GET only. The page fetches GET /api/v1/invitations and responds
       through PATCH /api/v1/invitations/{invitation}, so the two web POST
       routes that used to accept and decline are gone — along with the
       redirect-only GET /studies/{study}/invitation they sat beside. */
    Route::get('/invitations', [InvitationController::class, 'index'])
        ->name('invitations');

    /* ---- FEATURE — Study Recommendation Feed (Member 4, API-DRIVEN) ----
       One GET. The ranked list, the filters and the skill-gap coach all come
       from /api/v1/participants/me/*. No web POST: refreshing the AI advice
       is POST /api/v1/participants/me/skill-gap/refresh. */
    Route::get('/feed', [FeedController::class, 'index'])
        ->name('feed');

    /* ---- FEATURE — Referral system (Member 4, API-DRIVEN) ----
       One GET. The link, progress, referred users and rewards all come from
       /api/v1/referrals/me. There is no web POST: generating a code is
       POST /api/v1/referrals/me/code. */
    Route::get('/referrals', [ReferralController::class, 'index'])
        ->name('referrals');
    /* ---- FEATURE — Karma Credits (Member 3, API-DRIVEN) ----
   One GET. Balance, earn rates and the ledger all come from
   /api/v1/karma/me. No web POST — karma is only ever granted by the
   backend (study completion, on-time attendance, reviews, referrals). */
    Route::get('/karma', [KarmaController::class, 'index'])
        ->name('karma');

    /* ---- Profile builder ---- */
    Route::get('/profile',             [ParticipantProfileController::class, 'edit'])->name('profile');
    Route::patch('/profile',           [ParticipantProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/account',   [ParticipantProfileController::class, 'updateAccount'])->name('profile.account');
    Route::patch('/profile/password',  [ParticipantProfileController::class, 'updatePassword'])->name('profile.password');
});


/* =============================================================================
   RESEARCHER
   ============================================================================= */

Route::middleware(['auth', 'role:researcher'])->prefix('researcher')->name('researcher.')->group(function () {

    /* ---- FEATURE 3 — Endorsements (API-DRIVEN) ----
       One GET only. The queue, the tag picker, the standing ring and the
       submit all go through /api/v1/endorsements* — so the POST that used
       to live on this line has been deleted, along with
       EndorsementController::store(). If you see a 405 on this URL, some
       old Blade form is still pointing at it. */
    Route::get('/endorsements',  [EndorsementController::class, 'index'])->name('endorsements');

    /* ---- FEATURE — Smart Participant Matching (Member 4, API-DRIVEN) ----
       The candidate profile page. One GET; it fetches
       GET /api/v1/studies/{study}/candidates/{user}.

       The POST that used to send an invitation is gone — inviting is now
       POST /api/v1/studies/{study}/invitations. */
    Route::get('/studies/{study}/participants/{participant}', [ResearcherParticipantController::class, 'show'])
        ->name('studies.participants.show');

    /* ---- FEATURE — Referral rewards (Member 4, API-DRIVEN) ----
       Free paid-post credits earned by referring other researchers.
       Registered before /studies/{study}/... is irrelevant here — no
       wildcard sibling shares this prefix. */
    Route::get('/referrals', [ReferralController::class, 'researcher'])
        ->name('referrals');

    /* ---- Profile builder ---- */
    Route::get('/profile',             [ResearcherProfileController::class, 'edit'])->name('profile');
    Route::patch('/profile',           [ResearcherProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/account',   [ResearcherProfileController::class, 'updateAccount'])->name('profile.account');
    Route::patch('/profile/password',  [ResearcherProfileController::class, 'updatePassword'])->name('profile.password');
});


/* =============================================================================
   RESEARCHERS AND ORGANIZATIONS — verification submission
   ============================================================================= */

Route::middleware(['auth', 'role:researcher,organization'])->group(function () {
    Route::get('/verification',  [VerificationController::class, 'index'])->name('verification.index');
    Route::post('/verification', [VerificationController::class, 'store'])->name('verification.store');
});

/* ---- Notification centre (Member 1 — every logged-in role) ----
   One GET only. The page is API-driven: the feed, preferences, push
   setup and mark-as-read all go through /api/v1/notifications/*. */
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
});