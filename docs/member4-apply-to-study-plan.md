# Member 4 — Apply to Study (participant side)

**Owner:** Sarah (Member 4)
**Branch:** `Apply-to-Study`
**Status:** plan only — nothing written
**Cross-checked against:** `decision.md`, and the code actually on this branch (Member 3's
unlock and Member 2's `PipelineService` are both merged in already)

---

## 0. What I found before planning

Three things, and the first one changes the design.

### 0.1 The free-to-paid unlock is already here, and it is PERMANENT

`app/Services/FreeToPaidUnlockService.php` (Member 3) is on this branch. Its public surface:

```php
target(): int                                  // config('platform.free_forms_to_unlock_paid') = 5
freeCompletedCount(User): int                  // lifetime count of COMPLETED volunteer studies
isUnlocked(User): bool                         // reads participant_profiles.paid_studies_unlocked
canApplyToStudy(User, Study): bool             // ← THE METHOD I NEED
progress(User): array                          // target, count, remaining, unlocked
evaluate(User): array                          // recount + flip the flag + notify
```

API surface:

```
GET  /api/v1/participants/{user}/unlock-status
POST /api/v1/participants/{user}/unlock-status/recalculate
```

Columns it added to `participant_profiles`: `free_studies_completed`,
`paid_studies_unlocked`, `paid_studies_unlocked_at`.

**The problem.** Her `canApplyToStudy()` is:

```php
if ($study->incentive_type === IncentiveType::VOLUNTEER) return true;
return $this->isUnlocked($user);
```

and `isUnlocked()` reads a boolean that `evaluate()` only ever sets to **true**:

```php
$justUnlocked = ! $wasUnlocked && $count >= $target;
```

Nothing anywhere sets it back to false. Her count is lifetime, not windowed.

So **Member 3's unlock is a one-time, permanent upgrade.** Her brief says exactly that:
*"the participant's account is automatically upgraded to allow applications to paid
studies."* No mention of it being spent.

**The rule I have been given is different:** applying to a paid study *consumes* the
unlock, and the participant must complete five more free studies before the next one.
That is a **consumable allowance**, not a permanent upgrade. The two models disagree,
and this has to be resolved before any code is written — see §6.1.

### 0.2 Member 2's `PipelineService` now exists

`app/Services/PipelineService.php` implements my `App\Contracts\PipelineWriter`, so
invitation-accept now reaches her pipeline properly. Good news, but note what it
contains: **only `confirm()`**. There is no `apply()`. Applying is a different stage
transition and it is still Member 2's column. §3.2.

### 0.3 Nothing anywhere lists applicants for a researcher

I searched every remote branch — `member2Main`, `member3Main`, `Member3main_new`,
`api-driven`, `demoMain`, `main` — for a pipeline controller, applicant controller, or
apply endpoint. **There is nothing.** The only pipeline artefacts are the
`PipelineStage` enum and the stage-count badges on the researcher dashboard.

**So you were right to leave that endpoint out.** It is Member 2's *Participant Pipeline
Tracker* (Module 2, feature 4) — "researchers can move participants between stages with
a single action". Building it would be taking her graded feature. §6.2 has the message
to send her.

---

## 1. What this feature must do

1. A participant browsing a study sees an **Apply** button.
2. Applying to a **volunteer** study always works.
3. Applying to a **paid** study requires an unspent allowance — five completed free
   studies since their last paid application.
4. Applying to a paid study **consumes** the allowance; the counter restarts.
5. The participant can see how close they are, and withdraw an application they have
   not yet been confirmed for.
6. The application lands in the researcher's pipeline as `APPLIED`, so her tracker can
   pick it up.

---

## 2. Database changes

**One new table. Zero columns added to any existing table.**

Generate with `php artisan make:migration` — never hand-type the filename
(`decision.md` §2).

### 2.1 `study_applications`

```
php artisan make:migration create_study_applications_table
```

```php
Schema::create('study_applications', function (Blueprint $t) {
    $t->id();
    $t->foreignId('study_id')->constrained()->cascadeOnDelete();
    $t->foreignId('participant_id')->constrained('users')->cascadeOnDelete();

    $t->string('status')->default('applied');      // App\Enums\ApplicationStatus

    // Frozen at the moment of applying — the eligibility context.
    $t->boolean('was_paid_study')->default(false);
    $t->boolean('consumed_allowance')->default(false);
    $t->unsignedInteger('free_studies_at_apply')->default(0);

    $t->timestamp('applied_at');
    $t->timestamp('withdrawn_at')->nullable();

    $t->timestamps();

    $t->unique(['study_id', 'participant_id']);
    $t->index(['participant_id', 'consumed_allowance']);
    $t->index(['study_id', 'status']);
});
```

**Why this table exists at all, given `study_participations` already has an `APPLIED`
stage.** Three reasons, and they are the same reasons `study_invitations` exists:

1. **The allowance window needs an anchor.** "Five free studies since your last paid
   application" requires knowing *when* the last paid application happened.
   `study_participations` has no such record — a row can be advanced to CONFIRMED,
   moved backwards, or deleted, and the fact that an allowance was spent would vanish
   with it.
2. **`study_participations.stage` is Member 2's column.** I cannot store my feature's
   state in it.
3. **It freezes the eligibility context.** `was_paid_study`, `consumed_allowance` and
   `free_studies_at_apply` record *why* the application was permitted, exactly as
   `match_score_at_invite` does on invitations. If the threshold changes later, history
   still explains itself.

`unique(study_id, participant_id)` makes double-applying impossible at the database
level, the same way it does for invitations.

### 2.2 New enum

`app/Enums/ApplicationStatus.php`:

```php
enum ApplicationStatus: string {
    case APPLIED    = 'applied';      // submitted, awaiting the researcher
    case WITHDRAWN  = 'withdrawn';    // participant pulled out
    case CONFIRMED  = 'confirmed';    // researcher accepted — mirrors the pipeline
    case REJECTED   = 'rejected';     // researcher declined

    public function label(): string { ... }
    public function isOpen(): bool { return $this === self::APPLIED; }
}
```

`decision.md` §8 — status strings come from enums, never literals.

**Note:** `CONFIRMED` and `REJECTED` here are a *mirror* of what Member 2's pipeline
decides, not a second source of truth. My service never sets them; they exist so her
tracker can report back. If the group would rather I not mirror them at all, drop both
cases and read her `study_participations.stage` instead — §6.2.

### 2.3 Config

Add to the existing `platform.php`. Nothing new is invented: the threshold is
**already** `free_forms_to_unlock_paid`, which Member 3's service reads.

```php
'applications' => [
    // Whether applying to a paid study consumes the allowance and restarts
    // the count. FALSE reverts to Member 3's permanent-unlock behaviour, so
    // the group can settle §6.1 with a config flag rather than a rewrite.
    'consume_allowance_on_paid_apply' => env('TRYBE_APPLY_CONSUMES_UNLOCK', true),

    // Can a participant withdraw after applying? Off means applications are final.
    'allow_withdraw' => env('TRYBE_APPLY_ALLOW_WITHDRAW', true),
],
```

The "5" is **not** duplicated here. It stays `config('platform.free_forms_to_unlock_paid')`,
read through Member 3's `target()`, so there is one number and one owner.

---

## 3. The two integration seams

### 3.1 Eligibility — read Member 3, then add my window on top

`StudyApplicationService::eligibility(User, Study)` returns a small struct, and it is
built in two layers:

```
1. FreeToPaidUnlockService::canApplyToStudy($user, $study)
      volunteer study            → true, done. Apply.
      paid study, never unlocked → false. Blocked, reason = "complete N free studies".

2. If layer 1 says yes AND the study is paid AND consume_allowance_on_paid_apply:
      countFreeCompletedSince($user, $lastPaidApplicationAt)  >= target ?
          yes → allowed, this application will consume the allowance
          no  → blocked, reason = "you have used your unlock; complete N more"
```

**Layer 1 is Member 3's rule and I do not reimplement it.** Layer 2 is the windowed
rule I have been given, which her service has no concept of. Neither writes the other's
data:

- I never write `paid_studies_unlocked`, `paid_studies_unlocked_at` or
  `free_studies_completed`. Those are hers.
- She never reads `study_applications`. Mine.

`countFreeCompletedSince()` is my own query — completed volunteer participations with
`completed_at` after the anchor:

```php
StudyParticipation::where('participant_id', $user->id)
    ->where('stage', PipelineStage::COMPLETED)
    ->whereHas('study', fn ($q) => $q->where('incentive_type', IncentiveType::VOLUNTEER))
    ->when($since, fn ($q) => $q->where('completed_at', '>', $since))
    ->count();
```

That is deliberately the same shape as her `freeCompletedCount()` with one extra
`where`. If she ever exposes a `freeCompletedSince($user, $since)` I should delete mine
and call hers — §6.1.

### 3.2 The pipeline — a second contract, not a change to the first

Applying must put the participant in the researcher's pipeline at `APPLIED`. That is
`study_participations.stage`, which Member 2 owns, so it goes through a contract exactly
as invitation-accept does.

**Do NOT add `apply()` to the existing `PipelineWriter` interface.** `PipelineService`
already implements it; adding a method would make her class abstract-incomplete and
fatal on merge. Instead add a second, separate interface:

`app/Contracts/PipelineApplicant.php`

```php
interface PipelineApplicant
{
    /** Record a participant applying. Must be idempotent. */
    public function apply(Study $study, User $participant): void;

    public function isAvailable(): bool;
}
```

Bound in `AppServiceProvider` the same way — resolve `PipelineService` if it implements
the interface, otherwise a null implementation that logs and records nothing. Member 2
opts in by adding `implements PipelineApplicant` and one method; nothing of hers breaks
until she does, and my feature runs meanwhile with `pipeline_available: false` in the
payload.

This is interface segregation, and it is worth being able to say why: a contract should
be small enough that adding to the system never breaks an existing implementor.

---

## 4. The REST API

All routes inside the **`MEMBER 4 — Sarah`** block in `routes/api.php`, inside
`auth:sanctum`. Controller in `app/Http/Controllers/Api/V1/`.
JSON keys match column names (`study_id`, `participant_id`, `status`, `applied_at`).

| Method | URI (after `/api/v1`) | Who | Purpose |
|---|---|---|---|
| `GET` | `/studies/{study}/application` | participant | **Can I apply, and have I already?** Drives the button state. |
| `POST` | `/studies/{study}/applications` | participant | Apply. `201`. |
| `GET` | `/applications` | participant | My applications, `?status=applied` |
| `GET` | `/applications/{application}` | participant or study owner | One application |
| `DELETE` | `/applications/{application}` | participant | Withdraw. `200` with the updated resource. |
| `GET` | `/participants/me/apply-eligibility` | participant | Allowance status across the board, for the dashboard |

**Deliberately NOT built — Member 2's:**

```
GET   /api/v1/studies/{study}/applications        list applicants     ← her pipeline tracker
PATCH /api/v1/applications/{application}          confirm / reject    ← her stage moves
```

Leave these unregistered. Registering an empty stub would be worse than nothing — it
looks like the feature exists. §6.2.

### 4.1 `GET /studies/{study}/application` — the button-state endpoint

This is the one the page calls on load. Everything the Apply button needs:

```json
{
  "data": {
    "study_id": 12,
    "is_paid_study": true,
    "incentive_type": "cash",

    "can_apply": false,
    "blocked_reason": "allowance_spent",
    "message": "You have used your paid-study unlock. Complete 3 more free studies to apply to another paid study.",

    "allowance": {
      "target": 5,
      "completed_since_last_paid": 2,
      "remaining": 3,
      "unlocked_ever": true,
      "last_paid_application_at": "2026-07-30T10:12:00+06:00"
    },

    "application": null,
    "pipeline_available": true
  }
}
```

`blocked_reason` is a machine-readable enum-ish string (`already_applied`,
`never_unlocked`, `allowance_spent`, `study_closed`, `study_full`, `not_a_participant`)
with `message` as the human sentence. The page switches on the former and displays the
latter, so wording changes never require a JS change.

### 4.2 `POST /studies/{study}/applications`

Body: none required — the study is in the URL and the participant is the token.

**201** on success. Response includes whether the allowance was consumed, because that
is the thing the participant most needs told:

```json
{
  "message": "Applied. This used your paid-study unlock — complete 5 more free studies to unlock another.",
  "data": { "id": 3, "status": "applied", "consumed_allowance": true, ... }
}
```

### 4.3 Status codes

| Situation | Code |
|---|---|
| Applied | `201` |
| Already applied (any status except withdrawn) | `409` |
| Re-applying after withdrawing | `201`, row re-opened |
| Not a participant | `403` |
| Study not `OPEN` | `422` |
| Study full (`slots` reached) | `409` |
| Paid study, never unlocked | `422`, `blocked_reason: never_unlocked` |
| Paid study, allowance spent | `422`, `blocked_reason: allowance_spent` |
| Withdrawing someone else's application | `403` |
| Withdrawing an already-confirmed application | `409` |

---

## 5. Service, pages, files

### 5.1 `StudyApplicationService` — the only writer of `study_applications`

```php
eligibility(User $user, Study $study): array      // the §4.1 payload, no writes
apply(Study $study, User $participant): StudyApplication
withdraw(StudyApplication $a, User $participant): StudyApplication
allowanceFor(User $user): array                   // target, since, completed, remaining
lastPaidApplicationAt(User $user): ?Carbon        // the window anchor
countFreeCompletedSince(User $user, ?Carbon $since): int
```

`apply()` runs inside `DB::transaction` with the study row read for capacity, mirroring
`StudyInvitationService::respond()`:

1. Guards (role, study open, capacity, not already applied).
2. `eligibility()` — abort 422 with the reason if blocked.
3. Create the `study_applications` row, setting `consumed_allowance` when the study is
   paid and consumption is on.
4. `PipelineApplicant::apply()` — hand off to Member 2.
5. Notify the researcher through `NotificationService` (never write
   `user_notifications` directly).

**Karma:** Member 3's `KarmaService` is on this branch, and `KarmaSource` has no
`STUDY_APPLIED` case. Applying earns no karma unless she adds one — do not invent a case
in her enum. §6.3.

### 5.2 Pages

- `resources/views/studies/show.blade.php` gets an **Apply** panel. That file is Member
  2's, so the block goes in a partial I own —
  `resources/views/participant/partials/apply-panel.blade.php` — included with one line,
  exactly as the candidates panel is. It ships **empty**; `resources/js/apply.js` fills
  it from §4.1.
- `resources/views/participant/applications.blade.php` — my applications, a new page at
  `GET /participant/applications` inside the existing `['auth','role:participant']`
  group.
- No web POST routes. Applying is `POST /api/v1/...` (`decision.md` §4).

### 5.3 Files

**New:** 1 migration, 1 enum, 1 model, 1 contract + null implementation, 1 service,
1 API controller, 1 web controller, 2 blades, 1 JS module, 1 seeder, ~3 test files.

**Edited (additive):** `routes/api.php` (my block), `routes/web.php` (2 GETs in existing
groups), `config/platform.php` (one key), `resources/js/app.js` (one import),
`AppServiceProvider` (one binding), `DatabaseSeeder` (one `call()`).

**Edited (teammate's, one line):** `resources/views/studies/show.blade.php` — an
`@include`. Flag it before pushing.

---

## 6. Raise with the group — before writing code

### 6.1 → Lamia: the unlock is permanent, my rule says it is consumable — **blocking**

This is a genuine conflict, not an integration detail. Her `FreeToPaidUnlockService`
sets `paid_studies_unlocked = true` once and never back; her brief describes a one-time
upgrade. My requirement says applying to a paid study spends it.

Three ways to settle it, in order of preference:

1. **She adds consumption to her service** — `consume(User)` sets the flag back to false
   and stamps a `paid_unlock_consumed_at`. Her feature keeps ownership of the whole
   rule, I just call it. Cleanest, and it keeps one writer on those columns.
2. **I own the window, she owns the gate** (what this plan assumes). Her boolean means
   "has ever unlocked"; my `study_applications` supplies the "since when" anchor. No
   column is written by two features. Costs a little duplication in the counting query.
3. **The rule is dropped** and paid access stays permanent. Then
   `consume_allowance_on_paid_apply = false` and my feature is a thin apply button.

I have written the plan for **2** with a config flag that falls back to **3**, so
whichever way the group goes it is a setting rather than a rewrite. But ask her first —
if she prefers 1, most of §3.1 layer 2 disappears.

> **Message:** *"Your unlock is permanent — `paid_studies_unlocked` is only ever set
> true. My apply feature has been specced so that applying to a paid study spends it and
> the participant needs 5 more free studies. Do you want to own that consumption inside
> `FreeToPaidUnlockService` (a `consume()` method), or should I track the window in my
> own applications table and leave your columns alone?"*

### 6.2 → Roza: two things

**(a) The applicant list and confirm/reject are yours, and I have left them out.**
Nothing on any branch implements them. They are your Participant Pipeline Tracker
(Module 2 feature 4). I am deliberately not registering stub routes for them.

**(b) I need one more contract method.** Applying has to write
`study_participations.stage = APPLIED`, which is your column.

> **Message / prompt:** *"I need participants to be able to apply to studies, which
> means writing `stage = APPLIED`. Same pattern as `PipelineWriter`: I have added
> `App\Contracts\PipelineApplicant` with `apply(Study $study, User $participant): void`
> — idempotent, and it must not downgrade anyone already past APPLIED. Add
> `implements PipelineApplicant` to `PipelineService` and one method; the container
> binding picks it up automatically. It is a separate interface on purpose so your class
> does not break the moment I merge.*
>
> *Also — the applicant list and confirm/reject endpoints are yours and I have not built
> them. When you do, `study_applications` has a row per applicant with the eligibility
> context frozen; read it or ignore it, whichever suits."*

### 6.3 → Lamia: does applying earn karma?

`KarmaSource` has `STUDY_COMPLETED`, `SESSION_ON_TIME`, `REVIEW_LEFT`,
`REFERRAL_SUCCESS` — nothing for applying. Her brief lists earning on *completion*, not
application, so probably correct as is. I will not add a case to her enum. Confirm and
move on.

### 6.4 → Everyone: an index, again

```php
// study_participations — needed by the windowed free-study count
$t->index(['participant_id', 'stage']);
```

This is the **third** feature to need it (matching, referral qualification, now the
allowance window). Index only, no columns. Somebody should just run it.

---

## 7. Issues I can foresee

| # | Issue | Handling |
|---|---|---|
| 1 | **Race: two tabs applying to the last seat.** | `apply()` in a transaction with the capacity count inside it, and `unique(study_id, participant_id)` as the backstop — same as invitations. |
| 2 | **Invited *and* applied.** A participant can be invited (`study_invitations`) and also apply. Two rows, two features, one pipeline row. | `PipelineApplicant::apply()` must not downgrade someone already `CONFIRMED` by accepting an invitation. Stated in the contract. Also worth surfacing "you have already been invited" in §4.1 rather than letting them apply redundantly. |
| 3 | **Withdraw then re-apply resets the allowance?** If applying to a paid study consumes the allowance and the participant immediately withdraws, do they get it back? | **Recommend: no.** Otherwise it is trivially farmable — apply, withdraw, apply elsewhere. `consumed_allowance` stays `true` on a withdrawn row and the window anchor still counts it. Say this explicitly in the viva; it is the obvious abuse question. |
| 4 | **`completed_at` can be null** on a COMPLETED participation, since nothing enforces it. A null would fall outside `where('completed_at', '>', $since)` and silently not count. | Fall back to `updated_at` in the count, exactly as `ReferralService::qualifyingEvidence()` already does. |
| 5 | **What counts as "free"?** Member 3 counts `IncentiveType::VOLUNTEER` only. `COURSE_CREDIT` is unpaid but not volunteer. | Use her definition unchanged — one rule, one owner. Flag it to her as a question rather than diverging. |
| 6 | **Member 3's `notifyUnlocked()` sends the same email twice** — once unguarded, once in a try/catch. Pre-existing bug in her file, will double-mail on unlock. | Not mine to fix. Mention it to her. |
| 7 | **A study's `incentive_type` could change after an application.** | `was_paid_study` is frozen on the row, so history stays honest. |

---

## 8. `decision.md` compliance

| Rule | How this plan satisfies it |
|---|---|
| §1 shared DB, no `migrate:fresh` | One `Schema::create`. Safe under plain `migrate`. |
| §1 new NOT NULL needs a default | `status`, `was_paid_study`, `consumed_allowance`, `free_studies_at_apply` all defaulted. |
| §2 never hand-type a migration filename | §2.1 gives the `make:migration` command. |
| §2 don't add a column to `users` | None added — to `users` or to anything existing. |
| §3 business logic in a service | `StudyApplicationService`. Controllers guard and shape JSON. |
| §3 **one writer per column** | I never write `paid_studies_unlocked` / `free_studies_completed` (Member 3), never write `study_participations.stage` directly (Member 2 — via `PipelineApplicant`), never write `user_notifications` (Member 1 — via `NotificationService`). |
| §4 no controller passes data to a view | Both page controllers return a bare `view()`. |
| §4 delete the web POST once converted | None created. |
| §5 routes in my block, under `auth:sanctum`, controllers in `Api/V1` | §4. No public routes this time. |
| §5 JSON keys match column names | `study_id`, `participant_id`, `status`, `applied_at`, `consumed_allowance`. |
| §6 web routes inside a middleware group, `auth` before `role:` | Both inside the existing `['auth','role:participant']` group. |
| §6 fixed before wildcards | `/participant/applications` has no wildcard sibling. |
| §7 shared `api` helper, `DOMContentLoaded`, `esc()`, full Tailwind names | As with every page I have built. |
| §8 no hardcoded numbers | The "5" stays `free_forms_to_unlock_paid`, read via Member 3's `target()`. Only two new booleans in config. |
| §8 no status strings | New `ApplicationStatus` enum; `PipelineStage`, `IncentiveType`, `StudyStatus` reused. |
| §9 seeders attach to existing users | `ApplicationSeeder` creates nobody. |
| §10 pre-push checklist | §9 below. |

**One tension, resolved rather than broken.** §3's "one writer per column" would be
violated by any apply feature that writes `stage` itself. §3.2 keeps that write inside
Member 2's service and moves only the *request* across the boundary — which is what
§3's own line, *"Need it changed? Call the service"*, prescribes.

---

## 9. Build order

| # | Step | Verify |
|---|---|---|
| 1 | Send §6.1 and §6.2 | agreement before code |
| 2 | Migration + `ApplicationStatus` + `StudyApplication` model | `php artisan migrate` clean |
| 3 | `config/platform.php` applications key | no bare numbers |
| 4 | `PipelineApplicant` contract + null implementation + binding | resolves either way |
| 5 | `StudyApplicationService` — eligibility first, then apply/withdraw | unit-test the allowance window |
| 6 | `StudyApplicationApiController` + routes | Postman: every row in §4.3 |
| 7 | Apply panel partial + `apply.js`, `@include` in `studies/show` | Network tab shows JSON after HTML |
| 8 | My applications page | withdraw works, button states correct |
| 9 | Seeder + tests | §10 |

---

## 10. Tests worth writing

- `ApplyEligibilityTest` — volunteer always allowed; paid blocked when never unlocked;
  paid allowed on first unlock; **paid blocked immediately after a paid application**;
  allowed again after N more free completions.
- `ApplyToStudyTest` — 201; second apply 409; closed study 422; full study 409;
  withdraw then re-apply works; **withdrawing does not refund the allowance** (issue 3).
- `PipelineHandoffTest` — applying calls `PipelineApplicant::apply()`; with the null
  implementation bound the application still records and reports
  `pipeline_available: false`.
- `NoDataInViewsTest` — extend the existing one to cover the two new pages.

---

## 11. Pre-push checklist

- [ ] `php artisan route:list` clean
- [ ] `php artisan migrate` clean on a fresh copy
- [ ] Apply panel loads with JSON arriving after the HTML
- [ ] Neither new controller passes a second argument to `view()`
- [ ] The "5" appears nowhere except `config('platform.free_forms_to_unlock_paid')`
- [ ] Nothing outside `FreeToPaidUnlockService` writes `paid_studies_unlocked`
- [ ] Nothing outside `PipelineService` writes `study_participations.stage`
- [ ] You can explain the two-layer eligibility check and why withdrawing does not refund
