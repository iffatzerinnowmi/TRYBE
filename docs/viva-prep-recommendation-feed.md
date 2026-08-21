# The Study Recommendation Feed + Skill-Gap Coach

**Member 4 (Sarah Chowdhury, 23341048) — plain-English walkthrough for the viva**

Read this top to bottom once. It explains what the feature does, what happens when
someone loads the page, every endpoint, the maths, and the questions you are most
likely to be asked.

---

## 1. What the feature is, in one paragraph

A participant opens `/participant/feed` and sees the open studies they should apply
to, **best first**. Beside the list is a coach panel that says: *here are the studies
you nearly qualified for, here is the one skill that keeps blocking you, and here is
what to do about it.* The ranking is ordinary arithmetic done by our own code. Only
the last part — the *advice* — comes from an external AI, and the page works
completely without it.

That split is the whole design, and it is the thing to say first if you are asked
"where's the AI in this?"

> **The engine decides. The AI only advises.**

---

## 2. The two halves

| | Ranking | Coach |
|---|---|---|
| Question it answers | Which studies should I see? | Why am I not qualifying? |
| Who computes it | `StudyFeedService` + `StudyMatchingService` | `SkillGapService` (analysis) + `AiClient` (advice) |
| Needs the internet | No | Only for the advice sentence |
| If the AI is down | Unaffected | Analysis still shows; last saved advice still shows |

**Why it is split this way.** An external API can be slow, rate-limited, retired or
unreachable — all four happened while building this. If the model decided the
ranking, every one of those becomes a broken page. Because it only writes prose,
every one of them is a grey note in a panel.

---

## 3. Lifecycle — which file calls which

### 3a. Loading the page

```
Browser: GET /participant/feed
   │
   ▼
routes/web.php  ─────────────►  FeedController@index
   │                                   │
   │                                   └── return view('participant.feed')
   │                                        (NO data passed — decision.md §4)
   ▼
resources/views/participant/feed.blade.php
   │   empty containers only: [data-feed-list], [data-gap-analysis], …
   ▼
resources/js/feed.js   (bundled by Vite, runs on DOMContentLoaded)
   │
   ├──►  GET /api/v1/participants/me/feed/filters      → build the filter bar
   ├──►  GET /api/v1/participants/me/feed              → render the study cards
   └──►  GET /api/v1/participants/me/skill-gap         → render the coach panel
```

**The point to make:** the HTML arrives empty. Open the Network tab, reload, and you
see the document first and then three JSON calls. That is the proof the page is
API-driven rather than Blade-rendered, which is what `decision.md` §4 requires.

### 3b. Asking for advice (the only thing that touches the internet)

```
Click "Get advice"  (resources/js/feed.js, delegated click handler)
   │
   ▼
POST /api/v1/participants/me/skill-gap/refresh
   │   throttle:6,60  ← from config('platform.feed.advice_refresh_per_hour')
   ▼
SkillGapApiController@refresh
   │
   ▼
SkillGapService@generate
   │
   ├── analyse()          — database only, finds the gap
   ├── inputsHash()       — has anything changed? if not, STOP (no call made)
   │
   ▼
AiClient@skillAdvice  ────►  Google Gemini  ────►  validate()  ────►  saved
                                   │
                                   └── any failure → null, error recorded,
                                       previous advice untouched
```

---

## 4. Lifecycle — function by function

Follow one feed request all the way down:

1. **`FeedApiController@index`**
   - `participantOrFail()` — 403 if not a participant, 404 if no profile
   - `$request->validate([...])` — a bad filter value is a **422**, never a silently
     unfiltered list
   - calls `StudyFeedService@feed`

2. **`StudyFeedService@feed`**
   - calls `StudyMatchingService@recommendStudiesForParticipant($user, 50)`
     → this is where `match_score` comes from. **The feed does not score people.**
   - `passesFilters()` on each study
   - `breakdownFor()` — computes the three weighted contributions
   - sorts by `feed_score`, then `match_score`, then `id` (a stable tiebreak, so the
     order never wobbles between reloads)

3. **`StudyFeedService@breakdownFor`** calls
   - `recencySubScore()` — exponential decay on `created_at`
   - `urgencySubScore()` — based on `deadline`

4. **`FeedApiController@payload`** shapes each study into JSON.

And one coach request:

1. **`SkillGapApiController@show`** → `SkillGapService@refreshAnalysis`
2. **`refreshAnalysis`** → `analyse()` → saves to `skill_gap_advice`, **no network**
3. **`analyse()`** finds studies scoring in the near-miss band and, for each one,
   inspects `match_factors` to see which factor lost the most points

---

## 5. The four endpoints

Base URL for the demo: `http://127.0.0.1:1048`
Auth: session cookie (browser) or `Authorization: Bearer <token>` (Postman).

### 5.1 `GET /api/v1/participants/me/feed`

The ranked list.

**Query parameters** (all optional)

| Name | Type | Notes |
|---|---|---|
| `incentive_type` | string | must be a real `IncentiveType` value |
| `method` | string | `online` or `in_person` |
| `max_duration` | integer | 1–600 minutes |
| `topic_ids[]` | array of int | max 10, each must exist in `topics` |
| `strong_only` | boolean | only studies at or above the strong threshold |
| `limit` | integer | 1–50, defaults to `config('platform.feed.page_size')` |

**Response (trimmed)**

```json
{
  "data": {
    "user_id": 40,
    "count": 6,
    "filters_applied": {},
    "weights": { "match": 85, "recency": 10, "urgency": 5 },
    "strong_threshold": 70,
    "studies": [
      {
        "study_id": 29,
        "title": "Two-week sleep and focus diary",
        "match_score": 68.2,
        "strong_match": false,
        "feed_score": 63.5,
        "feed_breakdown": {
          "match":   { "sub_score": 68.2, "weight": 85, "contribution": 58.0 },
          "recency": { "sub_score": 61.0, "weight": 10, "contribution": 6.1 },
          "urgency": { "sub_score": 0.0,  "weight": 5,  "contribution": 0.0 }
        }
      }
    ]
  }
}
```

**Errors**

| Code | When | Message |
|---|---|---|
| 401 | no session and no token | Unauthenticated |
| 403 | logged in as a researcher | *Only participants have a study feed.* |
| 404 | participant with no `participant_profiles` row | *No participant profile found…* |
| 422 | `?incentive_type=nonsense` | validation error naming the field |

### 5.2 `GET /api/v1/participants/me/feed/filters`

Returns the options the page should offer — incentive types, methods, duration
buckets, topics.

**Why this endpoint exists at all:** so the filter bar is never hardcoded in
JavaScript. Add a topic to the `topics` table and the interface offers it with no
frontend change. Same errors as 5.1.

### 5.3 `GET /api/v1/participants/me/skill-gap`

The coach panel's data.

```json
{
  "data": {
    "user_id": 40,
    "analysis": {
      "near_miss_count": 12,
      "band": { "floor": 40, "threshold": 70 },
      "biggest_blocker": "topics",
      "blocker_counts": { "topics": 11, "skills": 1 },
      "missing_skills": [
        { "skill": "bilingual interviewing", "blocks_studies": 2 },
        { "skill": "reaction-time tasks",    "blocks_studies": 1 }
      ],
      "near_misses": [
        { "study_id": 29, "title": "Two-week sleep and focus diary",
          "match_score": 68.2, "shortfall": 1.8 }
      ]
    },
    "advice": { "focus_skill": "bilingual interviewing", "...": "..." },
    "ai_available": true,
    "stale": false,
    "generated_on": "21 Aug 2026",
    "model": "gemini-3.5-flash",
    "last_error": null,
    "nothing_to_say": false
  }
}
```

**The single most important fact about this endpoint: it never makes an outbound
HTTP call.** It recomputes the analysis from the database and returns whatever
advice is stored. `NoOutboundCallOnGetTest` asserts this, so a later refactor cannot
quietly reintroduce a blocking page load.

*If asked why:* generating inline on a GET means a page load can block on a
25-second timeout plus a retry — fifty seconds of spinner, in front of an examiner,
holding a request thread open for a third party.

### 5.4 `POST /api/v1/participants/me/skill-gap/refresh`

The **only** endpoint permitted to call the AI. Empty body.

- **Throttled** to 6 per hour per user, from
  `config('platform.feed.advice_refresh_per_hour')` — not a bare number in the route
  file, per `decision.md` §8.
- **Always returns 200**, even when the provider fails. The participant's own
  analysis is still perfectly good and the page should render it. The failure is
  reported in `message` and `data.last_error`.

| Situation | `message` |
|---|---|
| Success | `Advice updated.` |
| No near misses | `Nothing to advise on yet — no studies are close enough to analyse.` |
| Provider failed | `AI service unavailable: <the actual reason> Showing your last saved advice.` |
| Over the limit | HTTP **429**, Laravel's `Too Many Attempts.` |

---

## 6. The maths, worked through

### Feed score

```
feed_score = match×0.85 + recency×0.10 + urgency×0.05
```

Weights live in `config/platform.php` and are **returned in the payload**, so the
arithmetic can be checked on screen. Using the example above:

```
58.0 + 6.1 + 0.0 = 64.1  →  feed_score 63.5 after rounding each part
```

**Point at the reconciliation.** The three contributions add up to the total. That
means any surprising position is explainable from the payload rather than being
mysterious.

- **recency** — exponential decay on `created_at`. Posted today = 100, one half-life
  (14 days) ago = 50, and so on.
- **urgency** — 100 if the deadline is within 7 days, tapering to 0 at 14 days. No
  deadline = 0, because a study with no closing date is not urgent, it is just open.

### What is deliberately NOT in the ranking

**Karma, post boosts, and researcher subscription tier** — even though the brief
lists all three. Two reasons, and the second is the stronger one:

1. Karma measures how much someone has *engaged* with the platform. It says nothing
   about whether they suit a study. Ranking research opportunities by an in-app
   currency is the same category error as ranking them by who paid for a boost.
2. **It would double-count.** Every person-side signal — skills, credential level,
   endorsements, reliability, completed-study topics — is *already inside*
   `match_score`. Adding any of them again at the feed layer counts them twice.

So this layer only ever asks *"is this study worth surfacing right now?"*, never
*"is this person any good?"*. That question was already answered.

### The near-miss band

`analyse()` looks at studies scoring **40 to 70** (floor from config, threshold from
the matching service).

- Above 70 they already qualify — no advice needed.
- Below 40 there is no realistic advice to give.
- In between is where a single missing skill is the difference.

For each near-miss study it reads `match_factors` and finds the **applicable** factor
that lost the most points:

```
loss = (100 − sub_score) × effective_weight / 100
```

That is how `biggest_blocker` is derived. For the demo participant it comes out as
`topics` (11 of 12 studies), not skills — which is a sharper answer than the obvious
one if an examiner asks what is really holding a participant back.

---

## 7. How the AI is kept safe

Four properties `AiClient` guarantees, all worth naming:

1. **It never throws.** Any failure returns `null` and records why. A dead third
   party must never take a page down.
2. **It never sends personal data.** The payload is skills, topic names, a credential
   level and a count. No name, no email, no user id, no study titles.
3. **It never decides anything.** Our engine picks the gap. `validate()` checks the
   returned `focus_skill` against the skills we actually sent, so the model cannot
   invent a gap that is not in our data — and a response with no actionable step is
   rejected outright.
4. **It never logs the key.**

There is deliberately **no free-text headline field**. An earlier version had one and
it was not validated against the analysis, so a study *topic* ("reading") appeared in
a sentence about *skills*. The gap is now named by our engine from the validated
`focus_skill`, and the model only writes the explanation and the steps.

### The vendor seam

`AiClient` is the only class that knows which vendor is in use. It speaks two wire
formats behind one method:

| `AI_PROVIDER` | Endpoint | Auth | Response path |
|---|---|---|---|
| `gemini` (default) | `/chat/completions` | Bearer token | `choices.0.message.content` |
| `gemini-native` | `/models/{model}:generateContent` | `x-goog-api-key` header | `candidates.0.content.parts.0.text` |

Both exist because Google changed its key format mid-project: newer keys start `AQ.`
and are rejected by the OpenAI-compatibility layer while working on the native
endpoint. Switching is one line in `.env`.

---

## 8. Failure modes — every one of these actually happened

Good answer if asked "what happens when the AI breaks?": *it broke four different
ways while I was building it, and the page never went down once.*

| Failure | What the user saw | Why it did not break the page |
|---|---|---|
| No CA bundle on Windows (cURL 60) | grey note: TLS verification failed, with the fix named | `AiClient` returns null; analysis renders |
| Model `gemini-2.5-flash` retired | grey note quoting Google's own message | same |
| 8-second timeout too short (cURL 28) | grey note naming the timeout | same |
| Rate limit hit (6/hour) | `Too Many Attempts.` | it is *our* limiter, not Google's |

Two of these produced permanent improvements worth mentioning: the CA bundle path is
now a config key (`AI_CA_BUNDLE`) so the app carries its own answer instead of
depending on each machine's `php.ini`, and the error message now distinguishes
"no bundle configured" from "bundle configured but missing" from "bundle used and
still failed".

---

## 9. Questions you are likely to get

**"Why is this an API and not just a Blade page?"**
`decision.md` §4. Every page in TRYBE renders from JSON so the same endpoint serves
the web page, Postman, and any future mobile client. The controller passes no data to
the view — you can check: `FeedController@index` is one line.

**"Is the AI doing the recommendations?"**
No. Ranking is arithmetic in `StudyFeedService`, fully tested with no network. The AI
writes one paragraph of advice about a gap our engine already identified.

**"What if the AI returns nonsense?"**
`validate()` discards it. An unparseable response, a skill we never sent, or advice
with no actionable step are all rejected, and the previously stored advice survives
untouched.

**"Why 85/10/5?"**
So suitability decides the order in every realistic case; recency and urgency only
break ties and keep the top of the list fresh. They are config values — an examiner
asking "make deadlines matter more" is a one-line edit with no code change.

**"Why doesn't karma affect the feed?"** — see §6.

**"How do you stop repeated AI calls costing money?"**
Two ways. A hash of the analysis inputs: if nothing about the participant has
changed, no call is made. And a per-user throttle of 6 refreshes an hour, read from
config.

**"What's the weakest part?"**
`study_match_criteria.required_skills` is free text, so a researcher can type anything
— which is exactly how "reading" became a coachable skill. A controlled vocabulary is
the real fix; seeding credible values is the mitigation. Say this before they find it.

---

## 10. Demo checklist

```bash
php artisan migrate
php artisan db:seed --class=MatchingTopicsSeeder
php artisan db:seed --class=FeedDemoSeeder
npm run build
php artisan serve --port=1048
```

Log in as `feed.demo@trybe.test` / `password`.

- [ ] Feed lists studies, best first, each with a match % and a breakdown
- [ ] **The three contributions add up to the feed score** ← point at this
- [ ] Filters refetch; Clear resets; an empty result says so gracefully
- [ ] The dashboard "Recommended for you" panel agrees with the top of the feed
- [ ] Coach panel shows near misses and blocking skills **with the wifi off**
- [ ] "Get advice" produces advice about `bilingual interviewing`

**If the AI fails on the day, do not troubleshoot in front of the examiner.** Say:
*"the coaching layer is unreachable, which is the fallback path — the analysis is
ours and it still works"*, and carry on with the panel. That is a better answer than
a working demo of something you cannot explain.
