# Viva prep — Referral System (Member 4)

Everything below is checked against the code as it stands, not from memory.

---

## 0. The 30-second answer

> "Every user has a unique referral link. When somebody signs up through it, I record
> the attribution. When that person actually completes a study, the referral *qualifies*.
> At three qualified referrals a reward fires automatically — participants skip to the
> next credential tier, researchers earn a free paid-post credit.
>
> The two things I'd point at in the design are: **qualification is derived, not
> event-driven**, and **reward granting is idempotent because of a database
> constraint, not an if-statement**."

If you only memorise one thing, memorise those last two clauses. Almost every hard
question lands on one of them.

---

## 1. What fires what — the lifecycle

There are **five** distinct paths. Only one of them starts with a user clicking
something on my page.

| # | Trigger | Entry point | Ends at |
|---|---|---|---|
| **1** | Anyone opens any web URL with `?ref=CODE` | `CaptureReferralCode` middleware | a cookie |
| **2** | A user row is created, by any route | `ReferralAttributionObserver` | a `referrals` row |
| **3** | Someone opens the Refer a Friend page | `ReferralController` | an **empty** view |
| **4** | JavaScript, or Postman, calls the API | `ReferralApiController` | JSON |
| **5** | Scheduled or manual | `php artisan trybe:referrals:sync` | rewards granted |

**These labels are used consistently in §2 and §3 below.**

Three things to be able to say about how they relate:

- **1 and 2 are decoupled.** The link click sets a cookie that lasts
  `attribution_days` (30). The signup can happen days later, on a different page, and
  still be attributed. Neither knows about the other except through the cookie.
- **3 triggers 4.** The page controller returns a view with no data at all; the page
  then fetches from the API. That is the whole point of an API-driven page — 3 decides
  whether the page *exists*, 4 supplies everything *on* it.
- **5 exists because 4 only runs when somebody looks.** A referrer who never opens
  their page would otherwise never have their reward granted, so the command covers
  them. Both call the identical `sync()`.

**Neither 1 nor 2 needs my UI.** Path 2 fires on `User::created` no matter what created
the user — the web signup form, `POST /api/v1/auth/register`, or a seeder. That is why
the demo collection can register users through Postman and still have them attributed:
it just sends the cookie as a header.

### Why there is no "study completed" event

There is no event anywhere in this codebase that fires when a study completes.
`CredentialService::recalculate()` only runs when a human clicks a button or an artisan
command runs. So instead of hanging the feature on an event that is never dispatched,
`sync()` **recomputes qualification from live data every time it runs**, and a unique
index makes granting twice impossible.

That is why `sync()` can safely run on every page load, from the API, and from a
scheduled command, and always produce the same answer.

---

## 2. Flow A — file to file

### Path 1 — link click → cookie

```
Browser  GET /signup?ref=K7QF9M2P
   ↓
bootstrap/app.php                     (middleware registered on the 'web' group)
   ↓
app/Http/Middleware/CaptureReferralCode.php
   ↓
app/Services/ReferralService.php      findByCode()
   ↓
app/Models/ReferralCode.php           → referral_codes table (visits++)
   ↓
signed cookie 'trybe_ref' set for 30 days
```

### Path 2 — signup → attribution

```
POST /signup            (or POST /api/v1/auth/register — BOTH paths work)
   ↓
app/Http/Controllers/AuthController.php    User::create()
   ↓  (Eloquent 'created' event)
app/Providers/AppServiceProvider.php       User::observe(...)
   ↓
app/Observers/ReferralAttributionObserver.php
   ↓
app/Services/ReferralService.php           attribute() → passesGuards()
   ↓
app/Models/Referral.php                    → referrals table (status = pending)
```

### Path 4 — API request → JSON

```
Browser / Postman
   ↓
routes/api.php                                   (auth:sanctum, Member 4 block)
   ↓
app/Http/Controllers/Api/V1/ReferralApiController.php
   ↓
app/Services/ReferralService.php                 ← ALL logic lives here
   ↓
app/Models/{Referral, ReferralCode, ReferralReward, ResearcherPostCredit}.php
   ↓
MySQL
```

### Path 3 → Path 4 — page loads, then fetches

```
routes/web.php
   ↓
app/Http/Controllers/ReferralController.php      returns view() with NO data
   ↓
resources/views/participant/referrals.blade.php  ships empty, ids only
   ↓
resources/js/referrals.js                        fetches the API
   ↓
resources/js/api.js  (Member 1's shared helper — I do not fork it)
   ↓
GET /api/v1/referrals/me
```

---

## 3. Flow B — function to function

### `GET /api/v1/referrals/me` — the one that does the most

```
ReferralApiController::me()
├── ReferralService::codeFor($user)
│     └── ReferralCode::where(...)->first()          ← returns early if it exists
│     └── generateCode()                             ← 8 chars, retries on collision
│     └── ReferralCode::create()
│
├── ReferralService::sync($user)                     ← THE IMPORTANT ONE
│   ├── refreshQualification($referrer)
│   │     └── Referral::pending()->get()
│   │     └── foreach → qualifyingEvidence($referredUser)
│   │           ├── if RESEARCHER  → Study::where('researcher_id')     [count]
│   │           └── if PARTICIPANT → StudyParticipation::whereIn(stage,
│   │                                  [COMPLETED, PAID])              [count]
│   │     └── if count >= config('...studies_to_qualify')
│   │           → $referral->update(status = QUALIFIED,
│   │                               qualified_at = evidence['at'])
│   │
│   └── grantDueRewards($referrer)
│         ├── ReferralRewardType::forRole($role)     ← null for org/admin → stop
│         ├── eligibleQualifiedReferrals()           ← qualified AND same role
│         ├── milestones = intdiv(count, required)
│         ├── if !repeatable → min(1, milestones)
│         └── foreach milestone → grantOnce()
│               └── DB::transaction
│                     ├── ReferralReward::where(user,type,milestone)->first()
│                     │     → if found, return null  ← IDEMPOTENT
│                     ├── nextLevelFor()  (participants only)
│                     ├── ReferralReward::create()
│                     └── ResearcherPostCredit::create(+1)  (researchers only)
│
└── payload($request)
      ├── codeFor(), shareUrl()
      ├── progressFor($user)
      ├── Referral::with('referredUser')->get()
      ├── rewardsFor() → credentialFloorApplied()
      └── shareMessages()
```

### The other six

| Endpoint | Controller method | Service calls |
|---|---|---|
| `POST /referrals/me/code` | `storeCode()` | `codeFor()` **or** `rotateCodeFor()` → `generateCode()` |
| `GET /referrals/me/referred-users` | `referredUsers()` | none — reads `Referral` directly, formats via `shortName()` |
| `GET /referrals/me/rewards` | `rewards()` | `postCreditBalance()`, `credentialFloorApplied()` |
| `POST /referrals/me/sync` | `sync()` | `sync()` — same chain as above |
| `GET /referrals/validate/{code}` | `validateCode()` | `findByCode()` → `shortName()` |
| `GET /researchers/me/post-credits` | `postCredits()` | `postCreditBalance()` → `SUM(delta)` |

### Attribution

```
CaptureReferralCode::handle()
├── $request->query('ref')                 → return early if absent
├── ReferralService::findByCode()          → return early if unknown
├── $referralCode->increment('visits')
└── Cookie::queue('trybe_ref', code, attribution_days * 24 * 60)

ReferralAttributionObserver::created(User $user)
└── try {
      request()->cookie('trybe_ref')       → return if absent
      ReferralService::attribute($user, $code)
      ├── findByCode()                     → null → return null
      ├── passesGuards($referrer, $newUser)
      │     ├── referrer->id !== newUser->id        (self-referral)
      │     ├── emails differ                        (same person twice)
      │     └── not already referred                 (unique index backup)
      └── Referral::create(status = PENDING)
    } catch (Throwable) { Log::warning(); }   ← NEVER breaks a signup
```

---

## 4. API documentation

Common to all except `validate`: `Authorization: Bearer <token>`, `Accept: application/json`.
Missing/expired token → **401**.

---

### 4.1 `GET /api/v1/referrals/me`

Everything the page needs in one call.

**What makes it work:** creates the code lazily on first read, then runs `sync()` so
opening the page reconciles any reward that came due while you were away. This is what
delivers the "no manual claim required" promise for anyone who visits the page; the
artisan command covers everyone who doesn't.

**Returns:** `code`, `share_url`, `visits`, `progress`, `reward_preview`,
`referred_users`, `rewards`, `share_messages`.

**Errors:** 401 only. It cannot 404 — if you have no code, it makes one.

---

### 4.2 `POST /api/v1/referrals/me/code`

Body: `{ "rotate": true }` (optional)

**201** when the code did not exist. **200** when it did.
`rotate: true` regenerates; the old link stops working immediately, but historical
referrals keep the code they actually used because `referrals.code_used` is a frozen
copy, not a foreign key.

**Errors:** 401. 422 if `rotate` is not a boolean. In theory 500 if code generation
exhausts 20 attempts — unreachable with a 32⁸ space (≈1.1 × 10¹²).

---

### 4.3 `GET /api/v1/referrals/me/referred-users`

**Privacy is the design point here.** Names are truncated to first name + last initial
(`shortName()`), and emails are never in the payload. These are people who did not ask
to be on a list.

Each row carries `counts_towards_reward` — false for a mixed-role pair. Without that
field a page could read "3 of 3" and pay nothing, which looks broken.

**Errors:** 401.

---

### 4.4 `GET /api/v1/referrals/me/rewards`

**Watch this field:** `applied`. It is `true` only when `CredentialService` has actually
honoured the granted tier as a floor. Right now it returns **false**, because Member 1
has not made that one-line change yet. The API reports the truth rather than pretending
the reward landed. See §7.

**Errors:** 401.

---

### 4.5 `POST /api/v1/referrals/me/sync`

Body: `{}`

Forces a reconcile. Returns `granted_now` — the number of rewards granted *by this
call*, not the total.

**This is the endpoint to demo idempotency.** First call `granted_now: 1`; every call
after `granted_now: 0`.

**Errors:** 401.

---

### 4.6 `GET /api/v1/referrals/validate/{code}` — PUBLIC

The **only** unauthenticated endpoint in either of my features. It is registered above
the `auth:sanctum` group in `routes/api.php`.

**Why public:** a guest standing on the signup page has to call it to see "You were
invited by Ayesha". They have no token by definition.

**Why it is safe:** it returns exactly one field beyond the boolean — a shortened name.
No email, no id, no counts. Referral codes are guessable by design, so the response is
deliberately worthless to a guesser.

**Errors:** 404 for an unknown code. Never 401.

---

### 4.7 `GET /api/v1/researchers/me/post-credits`

Balance + full ledger.

**Balance is `SUM(delta)`, never a stored column.** A running total on
`researcher_profiles` would drift, could not be audited, and would be a column on a
table I do not own.

`spending_available` is `false` and says so in the payload — nothing spends these yet,
because the researcher tier system is Member 3's and does not exist.

**Errors:** 401. **403** for a participant.

---

### 4.8 The two web pages

`GET /participant/referrals` → 404 if the account has no participant profile.
`GET /researcher/referrals` → 403 if not a researcher.

Both controllers `return view(...)` with **no second argument**. That is team rule §4 —
no controller passes data to a view.

---

## 5. Code rundown — things that could catch you out

### 5.1 Why `referral_codes` is a table, not a column on `users`

Team rule §2: don't add a column to `users` without asking, because two migrations both
touching `users` will fail. A 1:1 table with `unique(user_id)` is the same thing without
the conversation.

### 5.2 Why the code alphabet excludes I, 1, O, 0

`ABCDEFGHJKLMNPQRSTUVWXYZ23456789` — people retype these from screenshots.

### 5.3 Why attribution is an observer, not a hidden form field

Two reasons, and the second is the one that wins the argument:

1. `AuthController`'s field names are frozen — three other people validate against them.
2. **There are two signup paths.** The web form *and* `POST /api/v1/auth/register`. A
   hidden field only covers one. The Eloquent `created` hook covers both.

Note it is **not** Laravel's `Registered` event — `AuthController` calls `User::create()`
and `Auth::login()` directly, so `Registered` is never dispatched.

### 5.4 Why the observer can never break a signup

The whole body is wrapped in `try/catch(Throwable)` that logs and returns. Somebody
unable to create an account because a referral cookie was malformed is a far worse bug
than a lost attribution. There is a named test for this:
`test_a_malformed_referral_cookie_does_not_break_signup`.

### 5.5 Why `qualified_at` is not `now()`

It is the participation's `completed_at`. If sync runs three days late, the record still
says when it actually happened.

### 5.6 Why researchers qualify differently

Participants qualify by **completing** a study. Researchers qualify by **posting** one —
researchers don't complete studies, so requiring that would make a researcher referral
impossible to satisfy.

### 5.7 Why a mixed-role pair pays nothing

`eligibleQualifiedReferrals()` filters `referredUser->role === referrer->role`. A
researcher referring a participant earns neither reward, because the brief only defines
rewards for matching pairs. It is still recorded and still shown, flagged
`counts_towards_reward: false`.

### 5.8 The one that matters — why I never write `credential_level`

`CredentialService` is the only writer of that column (team rule §3). If I wrote a tier
there, the next `recalculate()` would wipe it, and that's reachable three ways: the
button on Member 1's page, the API endpoint, and `php artisan trybe:recalculate`.

So the grant is recorded in `referral_rewards.granted_credential_level` and exposed via
`grantedLevel()`, which `CredentialService` should read as a **floor**:

```php
$earned  = CredentialLevel::fromCompletions($count);
$granted = $this->referrals->grantedLevel($user);
$after   = $this->higherOf($earned, $granted);
```

Until she makes that change, `credentialFloorApplied()` returns false and the API says
`applied: false`.

### 5.9 Why the ledger, not a balance

`researcher_post_credits` has `delta` rows: `+1` earned, `-1` spent. Balance is
`SUM(delta)`. Always auditable, never drifts, and it isn't a column on a table I don't own.

### 5.10 Numbers all come from config

`config/platform.php` → `referrals` key: `required_to_unlock` (3),
`studies_to_qualify` (1), `post_credits_per_referral` (1), `repeatable`, `code_length`,
`code_alphabet`, `attribution_days`.

**If asked "make it 5 referrals instead of 3"** — one line in config, no logic touched.
That's the CO5 mark. `studies_to_qualify` is a *count*, not a boolean, deliberately: the
rule is a number, not a bare `> 0` in code.

---

## 6. The database

See `docs/trybe-erd.png`. Purple = my referral tables, green = my matching tables,
grey = teammates' tables I only read.

**Four new tables, zero columns added to any existing table.**

| Table | Load-bearing constraint | Why |
|---|---|---|
| `referral_codes` | `unique(user_id)`, `unique(code)` | 1:1 without touching `users` |
| `referrals` | **`unique(referred_user_id)`** | a person can be referred once, ever, by one person |
| `referral_rewards` | **`unique(user_id, type, milestone)`** | makes `sync()` idempotent |
| `researcher_post_credits` | `index(user_id, created_at)` | ledger, balance is `SUM(delta)` |

Learn those two bolded ones. They are the answer to most abuse and correctness questions.

---

## 7. Known gaps — say these before you're asked

Being first to name a limitation reads as rigour. Being caught out doesn't.

1. **The tier reward is recorded but not yet reflected on the credentials page.**
   It needs one line in `CredentialService` (§5.8). I deliberately did not write
   another feature's column to force it. `docs/handoff-asks-referral.md` §1 has the
   exact change. There is a test (`CredentialFloorTest`) that currently *skips* with an
   explanatory message and starts asserting the moment she lands it.

2. **Post credits are earned but cannot be spent.** The researcher tier system that
   would consume one is Member 3's and doesn't exist. `spending_available: false` says
   so in the payload rather than showing a number that does nothing.
   `spendPostCredit()` is written and ready for her to call.

3. **The honest abuse limit.** Self-referral, re-attribution and duplicate codes are all
   blocked — `referred_user_id` is unique at the database level. But someone who creates
   three fake accounts **and gets a researcher to mark each one complete** does win.
   `ReferralStatus::FLAGGED` exists so an admin can exclude a referral, and `sync()`
   already ignores flagged rows. A full fraud system is out of scope for one semester.

---

## 8. Likely questions, with answers

**"Where does the reward actually get granted?"**
`ReferralService::grantOnce()`, inside a `DB::transaction`. It checks for an existing
row first and returns `null` if found, so nothing is granted twice and the caller
doesn't notify twice.

**"What if two requests hit sync at the same moment?"**
Both could pass the existence check. The `unique(user_id, type, milestone)` index
rejects the second insert, and the `catch` logs it and returns `null`. The constraint is
the guarantee; the check is just an optimisation.

**"Why not fire an event when a study completes?"**
There is no such event in this codebase — `CredentialService::recalculate()` only runs
on a button press or an artisan command. Deriving qualification means there's no
missed-event failure mode, because there's no event.

**"How do you stop someone referring themselves?"**
Three layers: the cookie can't point at yourself in practice; `passesGuards()` checks
both user id and email; and `unique(referred_user_id)` is the backstop if the first two
are ever bypassed.

**"What happens if the referral cookie is corrupted?"**
Nothing visible. The observer catches every `Throwable`, logs a warning and returns, so
the signup completes normally and the attribution is simply lost. Tested.

**"Why is one endpoint public?"**
A guest on the signup page has no token but must be able to see who invited them. It
returns one shortened name and nothing else.

**"Show me that changing the threshold works."**
`config/platform.php` → `'required_to_unlock' => 5`. Then
`php artisan trybe:referrals:sync`. Progress recalculates against 5, no code changes.

**"Which of these tables do you own?"**
All four referral tables. I read `users`, `participant_profiles`, `studies` and
`study_participations`, and write none of them.

---

## 9. Live demo script (2 minutes)

```bash
php artisan serve --port=1048
```

1. `GET /api/v1/referrals/me` — point at `progress.label` ("2 of 3 …") and `code`.
2. Mark a referred participant's study complete (or use an account that already has one).
3. `POST /api/v1/referrals/me/sync` → **`granted_now: 1`**.
4. `POST /api/v1/referrals/me/sync` again → **`granted_now: 0`**. Say: *"idempotent —
   that's the unique index, not an if-statement."*
5. `GET /api/v1/researchers/me/post-credits` → balance 1, ledger shows the `+1` row.
6. `GET /api/v1/referrals/validate/{code}` with **no auth header** → 200, one name.

Step 4 is the moment worth engineering the demo around.
