# TRYBE API Reference — v1

**Project:** TRYBE, a research-participant marketplace (Laravel 12 · MySQL · Sanctum)
**Group:** CSE471 Group 01
**Generated from:** `routes/api.php` at the current state of the working branch.

---

## Scope and accuracy note

Endpoints, methods, paths, auth requirements and ownership below are taken directly
from `routes/api.php` and are accurate.

Request and response **payloads** are documented in full only for **Member 4's**
endpoints, which is the code this document's author owns and has read end to end. For
Members 1–3 the purpose and signature are given, but the exact response shape should
be confirmed by the owner before anyone relies on it — documenting a payload from a
route name is guesswork, and guesswork in an API reference is worse than an honest
gap.

Sections marked **PLACEHOLDER** are routes that exist but whose controllers were still
stubs at the time of writing.

---

## 1. Conventions

### Base URL

```
http://127.0.0.1:1048/api/v1
```

Laravel prefixes `routes/api.php` with `/api` automatically; `/v1` is added by the
`Route::prefix('v1')` group. So a route written as `auth/login` is reachable at
`POST /api/v1/auth/login`.

### Authentication

Sanctum, accepting **two** credentials on the same routes:

| Client | Credential |
|---|---|
| Our own web pages | Session cookie set at login (stateful) |
| Postman / external | `Authorization: Bearer <token>` |

For session auth to work, the host and port **must** appear in
`SANCTUM_STATEFUL_DOMAINS`. Running on port 1048 requires:

```dotenv
APP_URL=http://127.0.0.1:1048
SANCTUM_STATEFUL_DOMAINS=127.0.0.1:1048,localhost:1048,127.0.0.1,localhost
```

Without this the login succeeds but no session cookie is issued, and every
subsequent call returns 401.

### Response envelope

Success responses wrap the payload in `data`:

```json
{ "data": { "...": "..." } }
```

Some endpoints add a sibling `message`. Collections are returned as arrays inside
`data`, alongside a `count`.

### Error format

| Code | Meaning | Body |
|---|---|---|
| 401 | Not authenticated | `{"message": "Unauthenticated."}` |
| 403 | Authenticated, wrong role | `{"message": "<reason>"}` |
| 404 | Missing record or profile | `{"message": "<reason>"}` |
| 422 | Validation failed | `{"message": "...", "errors": {"field": ["..."]}}` |
| 429 | Throttled | `{"message": "Too Many Attempts."}` |

### JSON key naming

Keys match database column names wherever a column exists (`decision.md` §5). This is
deliberate: it removes a translation layer between the API and the schema, and means
a reader can find the source of any value by grepping for the key.

---

## 2. Endpoint index

### Public — no authentication

| Method | Path | Owner | Purpose |
|---|---|---|---|
| POST | `/auth/register` | M1 | Create an account |
| POST | `/auth/login` | M1 | Log in, receive session cookie / token |
| GET | `/platform/stats` | M1 | Counts and thresholds for the landing page |
| GET | `/referrals/validate/{code}` | M4 | Check a referral code, returns one name |

### Member 1 — Nowmi · auth, credentials, reliability, endorsements, notifications

| Method | Path | Purpose |
|---|---|---|
| POST | `/auth/logout` | End the session |
| GET | `/auth/me` | The current user |
| GET | `/participants/{user}/reliability` | Reliability score + weights |
| POST | `/participants/{user}/reliability/recalculate` | Recompute from participation data |
| PATCH | `/admin/participants/{user}/reliability` | Admin override |
| GET | `/participants/{user}/credentials` | Bronze / Gold / Expert standing |
| POST | `/participants/{user}/credentials/recalculate` | Recount completed studies |
| GET | `/participants/{user}/credentials/completed-studies` | The list behind the count |
| GET | `/endorsements/pending` | Endorsements awaiting the researcher |
| POST | `/endorsements` | Endorse a participant (max tags from config) |
| GET | `/participants/{user}/endorsements` | Endorsement standing + badge progress |
| GET | `/notifications/unread-summary` | Navbar bell; runs on every page load |
| GET | `/notifications` | Notification centre |
| POST | `/notifications/preferences` | Update per-channel preferences |
| POST | `/notifications/subscribe` | Register a Web Push subscription |
| POST | `/notifications/unsubscribe` | Remove one |
| POST | `/notifications/test` | Send a test push |
| POST | `/notifications/read-all` | Mark everything read |
| POST | `/notifications/{notification}/read` | Mark one read |
| GET | `/studies/{study}/auction-ranking` | Rank participants for limited seats |

> **Route-ordering note.** `notifications/unread-summary` is registered *before*
> `notifications/{notification}/read`. Reversed, Laravel would read
> `unread-summary` as a notification id. This is `decision.md` §6 and it is the kind
> of bug that produces a 404 nobody can explain.

### Member 2 — Roza · studies, screeners, scheduling, pipelines

| Method | Path | Purpose |
|---|---|---|
| GET | `/studies` | Study listing board, with filtering |
| GET | `/studies/{study}` | One study |
| POST | `/studies` | Create |
| PATCH | `/studies/{study}` | Update |
| DELETE | `/studies/{study}` | Delete |
| GET | `/studies/{study}/rerecruit-candidates` | Past participants worth re-inviting |
| POST | `/studies/{study}/rerecruit` | Bulk invite them |
| GET | `/studies/{study}/pipeline` | Participants by stage |
| POST | `/studies/{study}/pipeline/stage` | Move someone between stages |
| POST | `/studies/{study}/messages` | Message everyone at a stage |
| GET | `/participants/{participant}/notes` | Session notes |
| POST | `/participants/{participant}/notes` | Add a note |
| GET | `/researchers/me/tier` | Researcher subscription tier |
| GET / POST | `/studies/{study}/screeners` | **PLACEHOLDER** — screener builder |
| GET / PATCH / DELETE | `/studies/{study}/screeners/{question}` | **PLACEHOLDER** |
| GET / POST | `/studies/{study}/slots` | **PLACEHOLDER** — slot scheduling |
| POST | `/studies/{study}/slots/{slot}/book` | **PLACEHOLDER** |

### Member 3 — Lamia · karma, payments, escrow, free-to-paid unlock

| Method | Path | Purpose |
|---|---|---|
| GET | `/karma/me` | Balance and how to earn more |
| GET | `/karma/me/transactions` | Ledger |
| GET | `/participants/{user}/unlock-status` | Progress toward paid studies |
| POST | `/participants/{user}/unlock-status/recalculate` | Recount volunteer studies |

> Karma and unlock endpoints are scoped to `/me` where the answer is only ever about
> yourself. That removes a whole class of authorisation bug rather than guarding
> against it.

### Member 4 — Sarah · matching, invitations, referrals, feed

| Method | Path | Purpose |
|---|---|---|
| GET | `/studies/{study}/candidates` | Ranked candidates for a study |
| GET | `/studies/{study}/candidates/{user}` | One candidate's full breakdown |
| GET | `/topics` | Shared topic vocabulary |
| GET | `/studies/{study}/match-criteria` | The criteria behind the ranking |
| PUT/PATCH | `/studies/{study}/match-criteria` | Replace the criteria |
| GET | `/studies/{study}/invitations` | Invitations for a study |
| POST | `/studies/{study}/invitations` | Invite a participant |
| GET | `/invitations` | My invitations (participant) |
| GET | `/invitations/{invitation}` | One invitation |
| PATCH | `/invitations/{invitation}` | Accept or decline |
| DELETE | `/invitations/{invitation}` | Withdraw (researcher) |
| GET | `/participants/me/matched-studies` | Participant side of matching |
| GET | `/participants/me/feed` | **Ranked recommendation feed** |
| GET | `/participants/me/feed/filters` | Filter options |
| GET | `/participants/me/skill-gap` | Skill-gap analysis + stored advice |
| POST | `/participants/me/skill-gap/refresh` | Generate advice (throttled, AI) |
| GET | `/referrals/me` | My code, link and progress |
| POST | `/referrals/me/code` | Rotate my code |
| GET | `/referrals/me/referred-users` | Who signed up through me |
| GET | `/referrals/me/rewards` | Rewards granted |
| POST | `/referrals/me/sync` | Reconcile qualification and grant what is due |
| GET | `/researchers/me/post-credits` | Researcher reward ledger |

---

## 3. Member 4 endpoints in detail

### 3.1 Smart Participant Matching

#### `GET /studies/{study}/candidates`

Ranked participants for a study. Researcher only, and only for their own study.

**Query:** `limit` (optional, defaults to `config('platform.matching.candidates_limit')`)

**Response**

```json
{
  "data": {
    "study_id": 12,
    "count": 5,
    "strong_threshold": 70,
    "weights": {
      "topics": 25, "age": 15, "location": 15, "skills": 15,
      "credential": 10, "reliability": 10, "availability": 5, "standing": 5
    },
    "candidates": [
      {
        "user_id": 7,
        "name": "Iffat Zerin Nowmi",
        "match_score": 82.4,
        "strong_match": true,
        "match_reasons": ["Shares 2 of 3 topics", "Meets the credential floor"],
        "match_factors": {
          "topics": {
            "applicable": true, "sub_score": 88.0,
            "effective_weight": 25, "contribution": 22.0,
            "matched_from_history": [3, 5]
          }
        },
        "invitation_status": "pending"
      }
    ]
  }
}
```

**The eight contributions sum to `match_score`.** Weights are returned so the total
can be verified from the payload. Any factor that does not apply to a study (no
location requirement, say) is marked `applicable: false` and the remaining weights
are **renormalised**, so the total is still out of 100.

**Errors:** 403 if not the study's researcher · 404 unknown study.

#### `GET /studies/{study}/match-criteria` · `PUT|PATCH` same path

Read or replace the criteria row driving the ranking.

**Body for PUT/PATCH**

```json
{
  "age_min": 18,
  "age_max": 45,
  "location": "Dhaka",
  "credential_min": "bronze",
  "required_skills": ["think-aloud protocol", "usability testing"],
  "availability_days": 30,
  "topic_ids": [2, 7]
}
```

`PUT` is the honest verb — the row is replaced wholesale, so sending the same body
twice gives the same result. `PATCH` is accepted on the same route because the shared
`api.js` helper exposes only get/post/patch/delete, and forking that helper is against
team rules (`decision.md` §7). One route, two verbs, one handler.

**Errors:** 403 not the owner · 422 `age_min > age_max`, unknown topic id, or an
invalid credential value.

### 3.2 Invitations

#### `POST /studies/{study}/invitations`

```json
{ "participant_id": 7 }
```

Returns **201** with the created invitation, including
`match_score_at_invite` — the score **frozen at the moment of sending**, so a later
profile change never rewrites history.

**Errors**

| Code | Cause |
|---|---|
| 403 | not the study's researcher |
| 409 | this participant already has an invitation for this study |
| 422 | unknown `participant_id`, or the user is not a participant |
| 422 | study is not open |

#### `PATCH /invitations/{invitation}`

Accept or decline. Participant only, and only their own invitation.

```json
{ "status": "accepted" }
```

Accepting writes a `study_participations` row at the applied stage. Declining does
not. Both stamp `responded_at`.

**Errors:** 403 not yours · 409 already responded · 422 status not `accepted` or
`declined`.

#### `DELETE /invitations/{invitation}`

Withdraw. Researcher only, and only while still `pending` — a 409 otherwise, because
withdrawing an invitation somebody has already accepted would silently remove them
from a study they think they are in.

### 3.3 Referral system

#### `GET /referrals/me`

```json
{
  "data": {
    "code": "K7M2PQXR",
    "share_url": "http://127.0.0.1:1048/signup?ref=K7M2PQXR",
    "required_to_unlock": 3,
    "qualified_count": 3,
    "pending_count": 1,
    "granted_credential_level": "bronze",
    "credit_floor_applied": false,
    "post_credits_available": 1
  }
}
```

`credit_floor_applied: false` is deliberate and honest: the reward is **recorded**,
but `participant_profiles.credential_level` has exactly one writer
(`CredentialService`, Member 1), and the referral system never writes it. It exposes
`grantedLevel()` as a floor for that service to read. The payload says out loud when
the two have not yet been connected, rather than the features fighting over one
column.

#### `POST /referrals/me/sync`

Reconcile qualification from live participation data and grant any milestone reward
now due.

**Safe to call as often as you like.** Running it ten times grants one reward —
enforced by a unique index on `(user_id, type, milestone)`, not by an if-statement.

*Why derived rather than event-driven:* nothing in this codebase fires an event when
a study is completed. Rather than hang a feature on an event that is never
dispatched, `sync()` recomputes from live data every time. There is no missed-event
failure mode to explain, because there is no event.

#### `GET /referrals/validate/{code}` — public

Returns a shortened referrer name and nothing else. **No email, no id, no counts.**
Referral codes are guessable by design, so widening this response would be a privacy
decision rather than a convenience one.

### 3.4 Recommendation feed and skill-gap coach

Documented in full, with worked examples and failure modes, in
[`viva-prep-recommendation-feed.md`](./viva-prep-recommendation-feed.md) §5.

Summary:

| Method | Path | Calls the AI? |
|---|---|---|
| GET | `/participants/me/feed` | No |
| GET | `/participants/me/feed/filters` | No |
| GET | `/participants/me/skill-gap` | **No** — returns stored data only |
| POST | `/participants/me/skill-gap/refresh` | Yes — the only route that does |

The refresh route is throttled to `config('platform.feed.advice_refresh_per_hour')`
per hour per user and **always returns 200**: a provider failure is reported in the
body, because the participant's own analysis is still valid and the page should
render it.

---

## 4. Configuration that changes API behaviour

No behavioural number is hardcoded (`decision.md` §8). These are the keys an examiner
is most likely to ask you to change live:

| Key | Effect |
|---|---|
| `platform.matching.weights.*` | The eight matching factors; must total 100 |
| `platform.matching.strong_threshold` | What counts as a strong match (70) |
| `platform.feed.weights.*` | match / recency / urgency (85 / 10 / 5) |
| `platform.feed.near_miss_floor` | Bottom of the coachable band (40) |
| `platform.feed.advice_refresh_per_hour` | The AI throttle (6) |
| `platform.referrals.required_to_unlock` | Referrals needed for a reward (3) |
| `services.ai.provider` | `gemini` (OpenAI-compatible) or `gemini-native` |
| `services.ai.ca_bundle` | Path to a CA bundle, for Windows PHP |
| `services.ai.timeout` | Seconds before giving up (25) |

Changing a weight changes both the maths **and** the on-screen explanation, because
the weights are returned in the payload rather than duplicated in the frontend.

---

## 5. Adding an endpoint — the team rules

From the header of `routes/api.php`:

1. Add routes **only inside your own member block**. Git merges this file line by
   line; four people editing four separate blocks almost never conflict, four people
   editing the same lines always do.
2. Anything touching user data goes **inside** the `auth:sanctum` group. A route left
   in the public section is one anyone on the internet can call.
3. Import controllers with a `use` statement at the top, alphabetically. Duplicate
   imports are the usual cause of a merge git accepts but PHP refuses to run.
4. Register **fixed segments before wildcards**.
5. Run `php artisan route:list` after **every** merge. `git diff` will not tell you
   this file is broken. `route:list` will.

---

## 6. Quick verification

```bash
php artisan route:list --path=api/v1        # every endpoint above
php artisan route:list --path=feed          # Member 4's feed routes
php artisan test                            # the full suite
```

A route that does not appear in `route:list` does not exist, whatever this document
says. If the two ever disagree, `route:list` is right and this file is stale.
