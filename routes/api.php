<?php

use App\Http\Controllers\Api\V1\ApplyApiController;
use App\Http\Controllers\Api\V1\AuthApiController;
use App\Http\Controllers\Api\V1\CandidateApiController;
use App\Http\Controllers\Api\V1\CompletionApiController;
use App\Http\Controllers\Api\V1\CredentialApiController;
use App\Http\Controllers\Api\V1\EndorsementApiController;
use App\Http\Controllers\Api\V1\FeedApiController;
use App\Http\Controllers\Api\V1\KarmaApiController;
use App\Http\Controllers\Api\V1\MatchedStudyApiController;
use App\Http\Controllers\Api\V1\NotificationApiController;
use App\Http\Controllers\Api\V1\PlatformApiController;
use App\Http\Controllers\Api\V1\ReferralApiController;
use App\Http\Controllers\Api\V1\ReRecruitApiController;
use App\Http\Controllers\Api\V1\ReliabilityApiController;
use App\Http\Controllers\Api\V1\SkillGapApiController;
use App\Http\Controllers\Api\V1\StudyInvitationApiController;
use App\Http\Controllers\Api\V1\UnlockApiController;
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

    /* MEMBER 4 — referral link check.
       Public because a GUEST standing on the signup page has to call it to
       see "You were invited by Ayesha". It returns one shortened name and
       nothing else: no email, no id, no counts. Referral codes are guessable
       by design, so widening this response is a privacy decision, not a
       convenience one. */
    Route::get('referrals/validate/{code}', [ReferralApiController::class, 'validateCode']);


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

        // --- Endorsements → Verified Participant badge ---
        // 'endorsements/pending' is a fixed segment and there is no
        // 'endorsements/{id}' route, so ordering is not a trap here yet.
        // If anyone ever adds one, it must go BELOW this line.
        Route::get(
            'endorsements/pending',
            [EndorsementApiController::class, 'pending']
        );
        Route::post(
            'endorsements',
            [EndorsementApiController::class, 'store']
        );
        Route::get(
            'participants/{user}/endorsements',
            [EndorsementApiController::class, 'standing']
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
        // Re-recruit past participants: candidate discovery and bulk invites.
        Route::get(
            'studies/{study}/rerecruit-candidates',
            [ReRecruitApiController::class, 'candidates']
        );

        Route::post(
            'studies/{study}/rerecruit',
            [ReRecruitApiController::class, 'inviteBulk']
        );

        /* Study Listing Board (CRUD + filtering) */
        Route::get('studies', [\App\Http\Controllers\Api\V1\StudyApiController::class, 'index']);
        Route::get('studies/{study}', [\App\Http\Controllers\Api\V1\StudyApiController::class, 'show']);
        Route::post('studies', [\App\Http\Controllers\Api\V1\StudyApiController::class, 'store']);
        Route::patch('studies/{study}', [\App\Http\Controllers\Api\V1\StudyApiController::class, 'update']);
        Route::delete('studies/{study}', [\App\Http\Controllers\Api\V1\StudyApiController::class, 'destroy']);

        /* Screener Survey Builder (placeholder endpoints) */
        Route::get('studies/{study}/screeners', [\App\Http\Controllers\Api\V1\ScreenerApiController::class, 'index']);
        Route::post('studies/{study}/screeners', [\App\Http\Controllers\Api\V1\ScreenerApiController::class, 'store']);
        Route::get('studies/{study}/screeners/{question}', [\App\Http\Controllers\Api\V1\ScreenerApiController::class, 'show']);
        Route::patch('studies/{study}/screeners/{question}', [\App\Http\Controllers\Api\V1\ScreenerApiController::class, 'update']);
        Route::delete('studies/{study}/screeners/{question}', [\App\Http\Controllers\Api\V1\ScreenerApiController::class, 'destroy']);

        /* Slot Scheduling (placeholder endpoints) */
        Route::get('studies/{study}/slots', [\App\Http\Controllers\Api\V1\ScheduleApiController::class, 'index']);
        Route::post('studies/{study}/slots', [\App\Http\Controllers\Api\V1\ScheduleApiController::class, 'store']);
        Route::post('studies/{study}/slots/{slot}/book', [\App\Http\Controllers\Api\V1\ScheduleApiController::class, 'book']);

        /* Participant Pipeline Tracker */
        Route::get('studies/{study}/pipeline', [\App\Http\Controllers\Api\V1\PipelineApiController::class, 'index']);
        Route::post('studies/{study}/pipeline/stage', [\App\Http\Controllers\Api\V1\PipelineApiController::class, 'updateStage']);

        /* Bulk messaging participants by stage */
        Route::post('studies/{study}/messages', [\App\Http\Controllers\Api\V1\MessagingApiController::class, 'sendToStage']);

        /* Session notes & tagging */
        Route::get('participants/{participant}/notes', [\App\Http\Controllers\Api\V1\NotesApiController::class, 'index']);
        Route::post('participants/{participant}/notes', [\App\Http\Controllers\Api\V1\NotesApiController::class, 'store']);

        /* Researcher tier info */
        Route::get('researchers/me/tier', [\App\Http\Controllers\Api\V1\TierApiController::class, 'myTier']);


        // ==================================================================
        // MEMBER 3 — Lamia
        // karma · payments · escrow · free-to-paid unlock
        // ==================================================================

        /* ---- Karma Credits System ----
           Everything is scoped to /me, same reasoning as /referrals/me: a
           karma balance is only ever your own, so there is no {user} to
           authorise. */
        Route::get('karma/me', [KarmaApiController::class, 'me']);
        Route::get('karma/me/transactions', [KarmaApiController::class, 'transactions']);
        /* ---- Free-to-paid unlock ----
           Complete enough VOLUNTEER studies and paid studies open up. show()
           reads the stored counter; recalculate() recounts from
           study_participations and fires the notification on the crossing. */
        Route::get(
            'participants/{user}/unlock-status',
            [UnlockApiController::class, 'show']
        );
        Route::post(
            'participants/{user}/unlock-status/recalculate',
            [UnlockApiController::class, 'recalculate']
        );




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

        /* "What does this study require, and which of it do I meet?"
           Separate from matched-studies because that list excludes studies the
           participant is already in — so their match detail would vanish the
           moment they applied, which is exactly when a study page needs it. */
        Route::get(
            'studies/{study}/requirements',
            [MatchedStudyApiController::class, 'requirements']
        );

        /* ---- Study Recommendation Feed ----
           Scoped under participants/me/ for two reasons: it matches every
           other personal endpoint here, and a bare 'feed' would be a generic
           noun in a file four people edit — decision.md section 6 warns that
           Laravel silently uses whichever duplicate registered first. */
        Route::get('participants/me/feed', [FeedApiController::class, 'index']);
        Route::get('participants/me/feed/filters', [FeedApiController::class, 'filters']);

        /* ---- The skill-gap coach ----
           show() NEVER calls the AI provider; it returns stored data only.
           refresh() is the single endpoint allowed to make an outbound call,
           and it is throttled from config rather than a bare number here. */
        Route::get('participants/me/skill-gap', [SkillGapApiController::class, 'show']);
        Route::post('participants/me/skill-gap/refresh', [SkillGapApiController::class, 'refresh'])
            ->middleware('throttle:' . config('platform.feed.advice_refresh_per_hour') . ',60');

        /* ---- Referral system ----
           Everything is scoped to /me. A referral dashboard is only ever
           your own, so there is no {user} to authorise — which removes a
           whole class of authorisation bug rather than guarding for it. */
        Route::get('referrals/me', [ReferralApiController::class, 'me']);
        Route::post('referrals/me/code', [ReferralApiController::class, 'storeCode']);
        Route::get('referrals/me/referred-users', [ReferralApiController::class, 'referredUsers']);
        Route::get('referrals/me/rewards', [ReferralApiController::class, 'rewards']);
        Route::post('referrals/me/sync', [ReferralApiController::class, 'sync']);

        /* Researcher reward ledger. Earns today; spending it needs Member
           3's tier system, which the payload says out loud. */
        Route::get('researchers/me/post-credits', [ReferralApiController::class, 'postCredits']);

        /* ---- Apply to Study (VOLUNTEER studies only) ----
           An application is a study_participations row at stage `applied` —
           no new table, no new column. Member 2's pipeline reads that table
           by stage, so applications reach her tracker with no change from her.

           Paid studies are refused here with a 422 until Member 3's karma /
           unlock eligibility surface exists. The refusal is server-side, not
           just a disabled button: a disabled control is an affordance, and
           anyone can POST to this route directly. */
        Route::get('studies/{study}/apply-status', [ApplyApiController::class, 'show']);
        Route::post('studies/{study}/apply', [ApplyApiController::class, 'store'])
            ->middleware('throttle:' . config('platform.apply.per_minute') . ',1');
        Route::delete('studies/{study}/apply', [ApplyApiController::class, 'destroy']);

        Route::get('participants/me/applications', [ApplyApiController::class, 'mine']);

        /* ---- Completing a study (participant side) ----
           A claim is a STATEMENT, not a completion. Nothing here writes
           study_participations.stage — that value drives credentials, karma,
           paid-study progress and referral qualification, so the participant
           never sets it. The researcher confirms through Member 2's pipeline.

           'completion-claims' is registered before nothing ambiguous, but note
           the ordering rule anyway: fixed segments before wildcards. */
        Route::get('studies/{study}/completion', [CompletionApiController::class, 'show']);
        Route::post('studies/{study}/completion', [CompletionApiController::class, 'store'])
            ->middleware('throttle:' . config('platform.completion.claims_per_hour') . ',60');
        Route::patch('studies/{study}/completion/opened', [CompletionApiController::class, 'opened']);

        /* Researcher-facing, read-only. Exists so Member 2's pipeline can show
           a "claimed complete" marker without joining my table. If she never
           calls it, nothing of hers changes. */
        Route::get('studies/{study}/completion-claims', [CompletionApiController::class, 'forStudy']);

        Route::get('participants/me/completions', [CompletionApiController::class, 'mine']);

    });
});