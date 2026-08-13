<?php

use App\Http\Controllers\Api\V1\AuthApiController;
use App\Http\Controllers\Api\V1\CandidateApiController;
use App\Http\Controllers\Api\V1\CredentialApiController;
use App\Http\Controllers\Api\V1\MatchedStudyApiController;
use App\Http\Controllers\Api\V1\NotificationApiController;
use App\Http\Controllers\Api\V1\PlatformApiController;
use App\Http\Controllers\Api\V1\ReliabilityApiController;
use App\Http\Controllers\Api\V1\StudyInvitationApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TRYBE API — v1
|--------------------------------------------------------------------------
|
| Laravel prefixes everything in this file with /api automatically, and we
| add /v1 on top. So a route written as 'auth/login' is reachable at:
|
|     POST /api/v1/auth/login
|
| ------------------------------------------------------------------------
| TEAM RULES — please read before adding anything
| ------------------------------------------------------------------------
|
| 1. Add your routes ONLY inside your own member block below. Git merges
|    this file line by line, so four people editing four separate blocks
|    almost never conflict — but four people editing the same lines will.
|
| 2. Anything that touches user data goes inside the auth:sanctum group.
|    A route left in the public section is a route anyone on the internet
|    can call without logging in.
|
| 3. Import controllers with a `use` statement at the top, in alphabetical
|    order. Duplicate imports are the usual cause of a merge that git
|    accepts but PHP refuses to run.
|
| 4. Register FIXED segments before WILDCARDS. 'notifications/unread-summary'
|    must come before 'notifications/{notification}/read', or Laravel reads
|    "unread-summary" as a notification id.
|
| 5. Run `php artisan route:list` after EVERY merge. git diff will not tell
|    you this file is broken. route:list will.
|
*/

Route::prefix('v1')->group(function () {

    /*
    |----------------------------------------------------------------------
    | PUBLIC — no token required
    |
    | Only add here if a stranger genuinely should be able to read it.
    | Everything else belongs in the protected group below.
    |----------------------------------------------------------------------
    */

    Route::post('auth/register', [AuthApiController::class, 'register']);
    Route::post('auth/login',    [AuthApiController::class, 'login']);

    // Counts and thresholds for the landing and login pages, which are
    // seen by people who are not logged in. No personal data.
    Route::get('platform/stats', [PlatformApiController::class, 'stats']);


    /*
    |----------------------------------------------------------------------
    | PROTECTED
    |
    | Two ways in, both accepted by auth:sanctum:
    |   - our own pages: the session cookie set at login
    |   - Postman:       Authorization: Bearer <token>
    |----------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        // ==================================================================
        // MEMBER 1 — Nowmi
        // auth · credentials · reliability · endorsements · notifications
        // ==================================================================

        // --- Session ---
        Route::post('auth/logout', [AuthApiController::class, 'logout']);
        Route::get('auth/me',      [AuthApiController::class, 'me']);

        // --- Reliability score ---
        Route::get(
            'participants/{user}/reliability',
            [ReliabilityApiController::class, 'show']
        );
        Route::post(
            'participants/{user}/reliability/recalculate',
            [ReliabilityApiController::class, 'recalculate']
        );
        Route::patch(
            'admin/participants/{user}/reliability',
            [ReliabilityApiController::class, 'update']
        );

        // --- Credentialing (Bronze / Gold / Expert) ---
        Route::get(
            'participants/{user}/credentials',
            [CredentialApiController::class, 'show']
        );
        Route::post(
            'participants/{user}/credentials/recalculate',
            [CredentialApiController::class, 'recalculate']
        );
        Route::get(
            'participants/{user}/credentials/completed-studies',
            [CredentialApiController::class, 'completedStudies']
        );

        // --- Notification centre + Web Push ---
        // unread-summary is the navbar bell; it runs on every page load.
        Route::get(
            'notifications/unread-summary',
            [NotificationApiController::class, 'unreadSummary']
        );
        Route::get(
            'notifications',
            [NotificationApiController::class, 'index']
        );
        Route::post(
            'notifications/preferences',
            [NotificationApiController::class, 'updatePreferences']
        );
        Route::post(
            'notifications/subscribe',
            [NotificationApiController::class, 'subscribe']
        );
        Route::post(
            'notifications/unsubscribe',
            [NotificationApiController::class, 'unsubscribe']
        );
        Route::post(
            'notifications/test',
            [NotificationApiController::class, 'test']
        );
        Route::post(
            'notifications/read-all',
            [NotificationApiController::class, 'markAllRead']
        );
        Route::post(
            'notifications/{notification}/read',
            [NotificationApiController::class, 'markRead']
        );

        // --- Cross-module: ranks participants for a study's limited seats ---
        Route::get(
            'studies/{study}/auction-ranking',
            [ReliabilityApiController::class, 'auctionRanking']
        );


        // ==================================================================
        // MEMBER 2 — Roza
        // study creation · listings · screener forms · pipelines
        // ==================================================================

        // (add your routes here)


        // ==================================================================
        // MEMBER 3 — Lamia
        // karma · payments · escrow · free-to-paid unlock
        // ==================================================================

        // (add your routes here)


        // ==================================================================
        // MEMBER 4 — Sarah
        // smart matching · invitations · competitions · buddies · referral · feed
        // ==================================================================

        /* ---- Smart Participant Matching ----
           Ranked candidates for a study, and the criteria that produced them.
           The researcher dashboard panel renders entirely from these. */
        Route::get(
            'studies/{study}/candidates',
            [CandidateApiController::class, 'index']
        );
        Route::get(
            'studies/{study}/candidates/{user}',
            [CandidateApiController::class, 'show']
        );
        /* The shared topic vocabulary, so the criteria editor offers real
           options rather than a free-text box. */
        Route::get('topics', [CandidateApiController::class, 'topics']);

        Route::get(
            'studies/{study}/match-criteria',
            [CandidateApiController::class, 'criteria']
        );
        /* PUT is the honest verb — the criteria row is replaced wholesale,
           so sending the same body twice gives the same result. PATCH is
           accepted on the same route because the shared api.js helper only
           exposes get/post/patch/delete, and forking that helper is against
           team rules. One route, two verbs, one handler. */
        Route::match(
            ['put', 'patch'],
            'studies/{study}/match-criteria',
            [CandidateApiController::class, 'updateCriteria']
        );

        /* ---- Invitations — nested under a study to create and list ---- */
        Route::get(
            'studies/{study}/invitations',
            [StudyInvitationApiController::class, 'index']
        );
        Route::post(
            'studies/{study}/invitations',
            [StudyInvitationApiController::class, 'store']
        );

        /* ---- Invitations — top-level to read and respond ----
           The id is globally unique, and a participant reading their own
           invitations has no reason to know the study id first. */
        Route::get('invitations', [StudyInvitationApiController::class, 'mine']);
        Route::get('invitations/{invitation}', [StudyInvitationApiController::class, 'show']);
        Route::patch('invitations/{invitation}', [StudyInvitationApiController::class, 'update']);
        Route::delete('invitations/{invitation}', [StudyInvitationApiController::class, 'destroy']);

        /* ---- The participant's side of matching ---- */
        Route::get(
            'participants/me/matched-studies',
            [MatchedStudyApiController::class, 'index']
        );

    });
});