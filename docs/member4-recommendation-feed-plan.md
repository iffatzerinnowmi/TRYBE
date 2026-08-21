# Member 4 — Study Recommendation Feed + AI Skill-Gap Coach

**Owner:** Sarah (Member 4)
**Feature:** Module 4, feature 2 — *Study Recommendation Feed*
**External API:** Google Gemini via AI Studio (the assignment's third-party-API requirement).
NVIDIA NIM was ruled out — phone verification, and Bangladesh not in its country list.
See `docs/ai-provider-setup.md`.
**Status:** plan only — nothing written
**Checked against:** `decision.md`, and the code actually on the `Apply-to-Study` branch

---

## 0. What already exists, and what the brief still wants

The **scoring engine is done**. `StudyMatchingService::recommendStudiesForParticipant()`
and `GET /api/v1/participants/me/matched-studies` already score every open study against
a participant using the same eight weighted factors as the researcher-side ranking.

What does **not** exist:

| Brief requirement | Status |
|---|---|
| Ranking by profile match score | ✅ done |
| Ranking by credential level | ✅ already inside `match_score` (weight 10) — see §4.0 |
| Ranking by **karma balance** | ❌ **deliberately dropped** — §4.0 |
| Ranking by study recency | ⚠️ trivial, not done |
| **Boosted listings at the top** | ❌ **deliberately dropped** — §4.0 |
| **Paid-tier researcher studies at the top** | ❌ **deliberately dropped** — §4.0. (Note: `GET /api/v1/researchers/me/tier` now exists, so this *could* be wired up. Still dropped, for the reasons in §4.0.) |
| Filters: compensation type, format, duration, topic | ❌ none |
| A feed **page** | ❌ nothing consumes the endpoint except `study-match.js` |
| "Real time using Livewire" | ❌ Livewire not installed, and `decision.md` §7 forbids it — §7.4 |

So this feature is: **turn an existing endpoint into a ranked, filterable feed with a
page**, and add the AI coach on top.

---

## 1. What it must do

1. A participant opens their feed and sees open studies ranked for them.
2. Ranking is driven by **suitability** — the eight-factor match score — adjusted only
   by how fresh and how urgent the *study* is. No in-app currency, no boosts.
3. They can filter by compensation type, format, duration and topic.
4. Alongside the feed, a **skill-gap coach** shows the studies they *narrowly missed*
   and what would fix that, using Gemini for the advice.
5. Nothing on the page breaks when the AI provider is down. There are no teammate dependencies left
   to break — §2.

---

## 2. Every teammate dependency has a fallback

This is the section that matters most. **No part of this feature blocks on anybody.**

| Dependency | Owner | Exists? | Fallback if missing |
|---|---|---|---|
| Karma balance | Member 3 | ✅ exists | **Not used.** Deliberately excluded — §4.0. This removes a dependency rather than adding a fallback. |
| Boosted listings | Member 3 | ❌ no | **Not used.** §4.0. |
| Researcher tier | Member 3 | ⚠️ endpoint exists (`researchers/me/tier`), no table | **Not used.** §4.0 — dropped on principle, not for lack of availability. Worth saying that way round if asked. |
| Applicant/pipeline data | Member 2 | partial | Feed only *reads* `study_participations` to exclude studies the participant is already in. Read-only, no dependency. |
| Screener / apply button | Member 2 / me | ❌ no | The feed links to the study page. It does **not** need an apply button to be useful. |
| AI provider (Gemini) | external | — | §5.4 — three layers of graceful degradation. Provider is swappable via config; see `docs/ai-provider-setup.md`. |

### 2.1 No contract is needed any more

An earlier draft proposed a `FeedSignalProvider` seam so boosts and researcher tier
could be plugged in later. **That is now deleted**, because §4.0 removes both from the
ranking entirely.

That is the better outcome: the cleanest way to handle a dependency is not to have one.
This feature now reads only `studies`, `participant_profiles`, `study_participations`,
`topics` and my own tables — all of which exist.

---

## 3. Database changes

**One new table. Zero columns added to anything existing.** The feed itself needs no
schema — ranking is computed and filters are query parameters. The table is for the AI.

Generate with `php artisan make:migration` (`decision.md` §2).

### 3.1 `skill_gap_advice`

```php
Schema::create('skill_gap_advice', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

    // What the deterministic analysis found — stored so the page can render
    // the gap even when the AI half is unavailable.
    $t->json('analysis');

    // What the model returned, already parsed into our own shape.
    $t->json('advice')->nullable();

    // Hash of the analysis input. If it has not changed, do not call the API.
    $t->string('inputs_hash', 64)->index();

    $t->string('model')->nullable();          // which model produced it
    $t->timestamp('generated_at')->nullable();
    $t->string('last_error')->nullable();     // why the last attempt failed

    $t->timestamps();
});
```

Three reasons this is a table rather than `Cache::remember()`:

1. **The demo survives a dead network.** The last generated advice is on disk. If the provider is
   unreachable during the viva, the page still shows real advice and says when it was
   generated.
2. **`inputs_hash` is the invalidation rule**, and it is deterministic and explainable:
   hash the analysis payload; if the hash matches the stored one, the advice is still
   current and no API call happens. That is a much better answer than "it's cached for
   an hour".
3. **It survives `php artisan cache:clear`**, which somebody will run at the worst
   moment.

`user_id` is `unique` — one current advice row per participant, replaced in place.

---

## 4. The ranking

### 4.0 What the feed does NOT rank on, and why

An earlier draft followed the brief literally and ranked partly on **karma balance**,
**boosted listings** and **researcher subscription tier**. All three are removed.

**The reasoning, which is worth being able to give:**

> Karma measures how much somebody has *engaged with the platform*. It says nothing
> about whether they are the right person for a particular study. Ranking research
> opportunities by an in-app currency is the same category error as ranking them by who
> paid for a boost — it optimises for platform activity, not for research quality. A
> participant should see the studies they are genuinely suited to, in the order they are
> suited to them.

There is also a concrete design bug the first draft had: **it double-counted credential
level.** Credential is already one of the eight factors inside `match_score` (weight
10), and the draft added it *again* as a separate feed weight. The same would have been
true of any person-side signal added at the feed layer.

That gives the clean separation this feature now uses:

| Layer | Question it answers | Signals |
|---|---|---|
| **`match_score`** (existing engine) | *Is this person right for this study?* | topics from **completed** studies, skills, credential level, reliability, endorsements, availability, age, location |
| **Feed adjustment** (new, small) | *Is this study worth surfacing right now?* | study freshness, deadline urgency |

Nothing about the participant is counted twice, and everything about the participant is
a competence signal rather than a currency.

### 4.1 The formula

```php
'feed' => [
    'weights' => [
        // Suitability. The eight-factor engine already blends skills,
        // credential level, endorsements, reliability and completed-study
        // topics — so this one number IS the candidate-quality signal.
        'match'   => env('TRYBE_FEED_W_MATCH', 85),

        // Study-side only. Nothing about the participant.
        'recency' => env('TRYBE_FEED_W_RECENCY', 10),   // newly posted surfaces
        'urgency' => env('TRYBE_FEED_W_URGENCY', 5),    // closing soon surfaces
    ],

    'recency_half_life_days' => env('TRYBE_FEED_RECENCY_HALFLIFE', 14),
    'urgency_window_days'    => env('TRYBE_FEED_URGENCY_WINDOW', 7),

    'near_miss_floor' => env('TRYBE_FEED_NEAR_MISS_FLOOR', 40),
    'page_size'       => env('TRYBE_FEED_PAGE_SIZE', 10),

    // How many manual refreshes of the AI advice per hour, per user.
    // In config rather than a bare throttle:6,1 in the route file, because
    // decision.md section 8 says numbers live here.
    'advice_refresh_per_hour' => env('TRYBE_FEED_ADVICE_REFRESHES', 6),
],
```

Weights sum to 100, so `feed_score` stays a readable percentage and the three
contributions reconcile with it — same discipline as the matching payload.

**Match sub-score** — `match_score` straight from `StudyMatchingService`. Not
recomputed, not adjusted.

**Recency sub-score** — exponential decay on `studies.created_at`. A study posted today
scores 100; one posted a half-life ago scores 50.

**Urgency sub-score** — 100 when the deadline is inside `urgency_window_days`, tapering
to 0 further out, and 0 when there is no deadline. This surfaces a closing study without
letting it outrank a genuinely better fit: at weight 5, urgency can move a listing a
little, never to the top.

At 85/10/5 the ordering is decided by suitability in almost every realistic case. The
two study-side terms only break ties and freshen the top of the list.

### 4.1.1 Optional: make competence weigh more *inside* the match score

Your instinct — "skills, credential level and endorsements" — is about the **match
engine**, not the feed, because that is where those already live. Current weights:

| Factor | Weight | Kind |
|---|---|---|
| topics (from **completed** studies) | 25 | experience |
| age | 15 | eligibility |
| location | 15 | eligibility |
| skills | 15 | competence |
| credential | 10 | competence |
| reliability | 10 | track record |
| availability | 5 | logistics |
| standing (verified + streak + endorsements) | 5 | reputation |

Two observations, both optional changes and both config-only except the last:

1. **Age and location are eligibility, not competence.** They are *already* hard filters
   in phase 1 of the candidate query, so scoring them again at 15 each mostly rewards
   sitting mid-band. Dropping them to 10 each frees 10 points for competence.
2. **Endorsements are worth about one point out of a hundred.** They sit inside
   `standing` (20% of a factor weighted 5). If endorsements matter to you — and a
   researcher vouching for someone is one of the strongest competence signals you have —
   splitting them into their own factor is a small code change plus a weight.

A competence-first split, if you want it:

```
topics 25 · skills 20 · credential 15 · endorsements 10 · reliability 10
age 10 · location 10 · availability 0
```

Still sums to 100. Everything except splitting `endorsements` out of `standing` is a
config edit with no code change — which is exactly the CO5 point about thresholds living
in config.

### 4.2 Filters

Query parameters on the feed endpoint, all optional:

| Param | Maps to |
|---|---|
| `incentive_type` | `studies.incentive_type` (cash / voucher / course_credit / volunteer) |
| `method` | `studies.method` (online / in_person) |
| `max_duration` | `studies.duration_minutes <=` |
| `topic_ids[]` | join `study_topic` |
| `strong_only` | score ≥ `strong_threshold` — **applied after scoring**, see below |

**One of these is not like the others.** `incentive_type`, `method`, `max_duration` and
`topic_ids` are column filters and run in SQL *before* scoring, so they shrink the set
the scorer touches. `strong_only` depends on the score itself, so it can only be applied
*after* — it trims the result, it does not save any work. Worth knowing so §4.3 is not
read as claiming otherwise.

All are `sometimes`-validated. Unknown values are rejected with 422 rather than silently
ignored, so a typo'd filter doesn't quietly return everything.

### 4.3 Performance

`recommendStudiesForParticipant()` already bulk-loads criteria and topics, so it is a
fixed handful of queries regardless of study count. Add:

- SQL-level filtering **before** scoring, so filters shrink the set the scorer touches.
- `page_size` cap.
- The same two-phase shape as the candidate query: filter in SQL, score in PHP.

---

## 5. The AI skill-gap coach

### 5.1 The split — this is the part to get right

**Deterministic, no AI:**

1. Score every open study (existing engine).
2. Take those in the **near-miss band**: `near_miss_floor` (40) up to `strong_threshold`
   (70). These are studies they *almost* qualified for — the interesting set.
3. For each, find which factor lost the most points, using the `factors.*.contribution`
   values the engine already returns.
4. Aggregate across the band: which skills appear most often in
   `factors.skills.missing`, which topics they lack history in, whether credential or
   availability is the recurring blocker.

That produces something like:

```json
{
  "near_miss_count": 6,
  "biggest_blocker": "skills",
  "missing_skills": [
    { "skill": "ui testing", "blocks_studies": 4 },
    { "skill": "python",     "blocks_studies": 2 }
  ],
  "missing_topics": [ { "topic": "Usability", "blocks_studies": 3 } ],
  "credential_blocking": 1,
  "current_skills": ["survey", "interviews", "bangla"],
  "credential_level": "bronze"
}
```

**All of that is computed by my code.** It is stored in `skill_gap_advice.analysis` and
rendered on the page **whether or not the AI ever runs**.

**AI, and only this:** turn that structured gap into concrete, actionable guidance —
what "UI testing" means in practice, a realistic first step, roughly how long. That is
knowledge the platform does not and cannot hold.

> **The line for the viva:** *"The AI never scores anything and never decides which gap
> matters. My engine does the analysis; the model only advises on how to close a gap I
> already identified. If the model is wrong, the ranking is unaffected."*

### 5.2 What is sent — and what is not

Sent: skills, topics, counts, credential level. **Never** names, emails, ids, study
titles, or anything that identifies a person or a researcher's private study. The
payload above is the whole payload.

Say this before you are asked. "What personal data goes to Google?" is a certain
question and "none" is a strong answer.

### 5.3 Structured output, not prose

Ask for JSON and validate it:

```json
{
  "headline": "UI testing is your biggest gap",
  "focus_skill": "ui testing",
  "why_it_matters": "…",
  "steps": [
    { "action": "…", "effort": "a weekend" }
  ],
  "encouragement": "…"
}
```

If the response does not parse, or `focus_skill` is not one of the skills we sent,
**discard it and keep the previous advice**. The model does not get to invent a skill
that isn't in our data. Low temperature (0.2–0.3).

### 5.4 Three layers of graceful degradation

| Failure | Behaviour |
|---|---|
| No API key configured | Feed and analysis render fully. Coach panel shows the gap, hides the advice. `ai_available: false`. |
| API times out / errors | Serve the stored advice with `generated_at`. Record `last_error`. Never a 500. |
| Response unparseable | Discard, keep previous, log. |

Timeout ≤ 8 seconds, one retry, never in a database transaction.

**When it runs:** only when `inputs_hash` changes. Opening the feed twice makes one API
call, not two. Plus `php artisan trybe:advice:generate` to pre-warm before a demo.

---

## 6. The REST API

Inside the **`MEMBER 4 — Sarah`** block, inside `auth:sanctum`. JSON keys match column
names.

| Method | URI (after `/api/v1`) | Purpose |
|---|---|---|
| `GET` | `/participants/me/feed` | The ranked, filtered feed. Query params from §4.2. |
| `GET` | `/participants/me/feed/filters` | Available filter options — topics, incentive types, duration buckets — so the UI is not hardcoded. |
| `GET` | `/participants/me/skill-gap` | Stored analysis + advice. **Never calls the API.** §6.2. |
| `POST` | `/participants/me/skill-gap/refresh` | The only endpoint that may call the AI provider. §6.2. Throttled — see below. |

**Why `/participants/me/…` and not a bare `/feed`.** Two reasons:

1. **Consistency.** Every personal endpoint I have already shipped uses this shape —
   `participants/me/matched-studies`, `referrals/me`, `referrals/me/rewards`,
   `researchers/me/post-credits`. A bare `/feed` would be the odd one out.
2. **`/feed` is a land-grab in a shared namespace.** `routes/api.php` is one file that
   four people edit. A generic top-level noun is exactly the kind of thing another member
   might also register, and `decision.md` §6 warns that Laravel silently uses whichever
   was registered first. Scoping it under `participants/me` makes a collision impossible.

`GET /participants/me/matched-studies` **stays** — `study-match.js` uses it on the study
page. The feed is a superset with ranking and filters; it does not replace it.

### 6.1 Feed response shape

```json
{
  "data": {
    "count": 10,
    "filters_applied": { "incentive_type": "cash" },
    "weights": { "match": 85, "recency": 10, "urgency": 5 },
    "studies": [
      {
        "study_id": 12,
        "title": "…",
        "incentive_type": "cash",
        "compensation_amount": "500.00",
        "method": "online",
        "duration_minutes": 30,
        "deadline": "30 Aug 2026",
        "researcher_name": "Dr. Anisa Rahman",
        "topics": [{ "id": 3, "name": "Usability" }],

        "match_score": 78.5,
        "strong_match": true,
        "match_reasons": ["…"],

        "feed_score": 76.8,
        "feed_breakdown": {
          "match":   { "sub_score": 78.5, "weight": 85, "contribution": 66.7 },
          "recency": { "sub_score": 51,   "weight": 10, "contribution": 5.1  },
          "urgency": { "sub_score": 100,  "weight": 5,  "contribution": 5.0  }
        },

        "url": "/studies/12"
      }
    ]
  }
}
```

`feed_breakdown` contributions add up to `feed_score` — the same reconciliation
discipline as the matching payload, and the same viva moment.

---

### 6.2 The GET never calls the AI provider — this is a hard rule

`GET /participants/me/skill-gap` returns **only what is already stored**. It never makes
an outbound HTTP call, no matter how stale `inputs_hash` is.

An earlier draft left this ambiguous, saying only "it regenerates when the hash changes".
That would have meant a page load could block on an 8-second timeout plus a retry —
sixteen seconds of a spinner, in the worst case during a live demo, and a request thread
held open for a third-party service.

So:

| Endpoint | May call the AI provider? |
|---|---|
| `GET /participants/me/feed` | **No** |
| `GET /participants/me/skill-gap` | **No** — returns stored advice, plus `stale: true` when the hash has moved on |
| `POST /participants/me/skill-gap/refresh` | **Yes** — explicit user action, shows a spinner on a button, not a page |
| `php artisan trybe:advice:generate` | **Yes** — the pre-warm path |

The page therefore always renders instantly. If the advice is stale, the panel says so
and offers the refresh button. This also makes the demo predictable: pre-warm beforehand,
and the only network call during the viva is one you chose to trigger.

## 7. Service, pages, files

### 7.1 `StudyFeedService`

```php
feed(User $user, array $filters, int $limit): Collection
filterOptions(): array
rankingWeights(): array
```

It **calls** `StudyMatchingService` for the match score — it does not reimplement
scoring. One engine, two consumers.

### 7.2 `SkillGapService`

```php
analyse(User $user): array          // deterministic, no network
inputsHash(array $analysis): string
adviceFor(User $user, bool $force = false): SkillGapAdvice
```

### 7.3 `AiClient`

A thin wrapper over `Http::withToken(...)->timeout(8)->post(...)`. **The only class that
knows which vendor we use.** Gemini, Groq and OpenRouter all speak an OpenAI-compatible
shape, so switching provider is a `.env` change, not a rewrite.

Reads `config('services.ai.*')` — never `env()` outside a config file, or `config:cache`
silently returns null everywhere else. See `docs/ai-provider-setup.md`.

The config key and the `.env` names are deliberately generic (`services.ai`, `AI_API_KEY`,
`AI_BASE_URL`, `AI_MODEL`) rather than vendor-named, so changing provider is a value
change and not a rename across three files. That decision is what made the NVIDIA → Gemini
switch cost nothing.

### 7.4 Pages

- `GET /participant/feed` → `FeedController::index()` → bare `view()`, no data.
  Registered **inside the existing `['auth', 'role:participant']` prefix group** in
  `routes/web.php`, so `auth` runs before `role:` (`decision.md` §6). No wildcard
  sibling, and the URL is not registered anywhere else — verified.
- Blade ships empty; `resources/js/feed.js` fetches and renders.
- Coach panel is a partial I own, on the same page.
- **No Livewire.** The brief says Livewire; `decision.md` §7 mandates the shared `api`
  fetch helper, and Livewire is not installed. The instructor's later "fully API-driven"
  instruction supersedes the brief. "Real time" is delivered by refetching on filter
  change and on `visibilitychange` — the same approach as the candidates panel. **Flag
  this to the group so all four of you answer it the same way.**

### 7.5 The dashboard panel this feature collides with — **and a rule I am currently breaking**

`ParticipantDashboardController` line 42 already does:

```php
$recommended = $matching->recommendStudiesForParticipant($user, 4);
// ...
return view('dashboards.participant', compact(..., 'recommended', ...));
```

and `dashboards/participant.blade.php` renders a **"Recommended for you"** panel from it.

Two problems, both mine:

1. **It violates `decision.md` §4** — "No controller passes data to a view. No second
   argument to `view()`." That call is to *my* service, so this is my mess, not a
   teammate's.
2. **The new feed page would duplicate it**, and the two could disagree if the dashboard
   keeps calling the raw matcher while the feed applies ranking.

Handled as part of this feature, not left for later:

- Extract the panel into `resources/views/participant/partials/recommended-panel.blade.php`
  — my file, ships empty — and `@include` it from the dashboard.
- It fetches `GET /api/v1/feed?limit=4`, so the dashboard preview and the full feed are
  **the same ranking by construction**, exactly as the researcher panel and the
  candidates API are.
- Delete `$recommended` from the controller and the `compact()`.
- Add a "See all" link to `/participant/feed`.

The diff to `ParticipantDashboardController` is deleting two lines. That file is shared,
so mention it before pushing — but the change removes a rule violation rather than adding
one.

---

## 8. Group coordination

### 8.1 → Lamia — nothing needed, but tell her anyway

She may expect the feed to consume karma, boosts and researcher tier, because the brief
says it does. It does not, and that is deliberate.

> *"Heads up — my recommendation feed does not rank on karma, boosts or researcher tier,
> even though the brief lists them. Group feedback was that there are too many credit and
> boost systems, and I agree: karma measures engagement, not whether someone suits a
> study. The feed ranks on the match score — skills, credential level, endorsements,
> completed-study topics, reliability — plus how fresh and how urgent the study is.
>
> Practically this means I no longer depend on `post_boosts` or the tier system at all,
> so nothing of mine is waiting on you. If the group later decides karma *should*
> influence the feed, it is one weight in config plus one call — but I would argue
> against it."*

**Nothing in this feature waits on anybody.**

### 8.2 → Everyone — the Livewire discrepancy

§7.4. One answer for the group.

### 8.3 → Nowmi — nothing needed

The feed reads `participant_profiles` (credential, skills, topics) only.

---

## 9. Issues I foresee

| # | Issue | Handling |
|---|---|---|
| 1 | **Cold-start participants** — a brand-new participant matches nothing, so the near-miss band is empty and the coach has nothing to say. | Detect `near_miss_count === 0` and branch: if they have no profile data, the advice is "complete your profile"; if they genuinely match everything, say so. Do not send an empty analysis to the model. |
| 2 | **A study-side signal outranking suitability.** A brand-new but badly-matched study surfacing above a great fit. | Recency and urgency are weighted 10 and 5 against match's 85, so they can only reorder near-equals. The breakdown is in the payload, so any surprise ordering is explainable rather than mysterious. |
| 3 | **AI latency on first load.** | Only regenerates when `inputs_hash` changes, so it is once per meaningful profile change. Pre-warm with the artisan command before a demo. |
| 4 | **Model invents a skill we never sent.** | Validate `focus_skill` against the input set; discard the response if it does not match. |
| 5 | **Cost / rate limits.** | Hash-gated regeneration means roughly one call per profile change per participant. Free tier is ample for a demo. |
| 6 | **Filters return nothing.** | Return an empty array with `filters_applied` echoed back, and let the page say "no studies match these filters" with a clear button. Never a 404. |
| 7 | **The feed and the study page disagree.** | Both call `StudyMatchingService`. Impossible by construction — same reason the researcher panel and the API agree. |
| 8 | **The coach has nothing to show in a demo.** The near-miss band is 40–70; seeded participants may score above or below it, in which case the panel is empty through no fault of the code. | The feed needs no seeder, but the **coach does**. Extend `MatchingTopicsSeeder` (mine) or `trybe:asg3:setup` so at least one demo participant has 3–4 studies genuinely scoring in the band — e.g. matching topics and age but missing one required skill. Verify with the artisan command before the viva rather than discovering it live. |

---

## 10. `decision.md` compliance

| Rule | How |
|---|---|
| §1 shared DB, no `migrate:fresh` | One `Schema::create`. |
| §1 NOT NULL needs a default | `analysis` and `inputs_hash` are always written on create; nullable ones are nullable. |
| §2 `make:migration`, no column on `users` | Yes; none added anywhere. |
| §3 logic in a service | `StudyFeedService`, `SkillGapService`, `AiClient`. Controllers guard and shape JSON. |
| §3 one writer per column | Writes only `skill_gap_advice`. Reads `participant_profiles`, `studies`, `study_participations`, `topics` — nothing else, and writes none of them. |
| §4 no data to views | `FeedController` returns a bare `view()`. **And §7.5 fixes an existing violation** — the dashboard currently passes `$recommended` from my service into a view. |
| §4 no web POST | Refresh is `POST /api/v1/...`. |
| §5 own block, `auth:sanctum`, `Api/V1` | Yes. No public routes. |
| §5 JSON keys = column names | Column-backed keys use exact names: `study_id`, `incentive_type`, `duration_minutes`, `compensation_amount`, `deadline`. Computed keys (`match_score`, `feed_score`, `feed_breakdown`) are not columns and cannot be — same precedent as `ReliabilityApiController`'s `band` and `live_score`. |
| §6 every route in a middleware group; `auth` before `role:`; fixed before wildcards; never the same URL twice | `/participant/feed` sits inside the existing `['auth','role:participant']` group, has no wildcard sibling, and is not registered elsewhere (checked). API routes are all scoped under `participants/me/…` so they cannot collide with another member's block — §6. |
| §7 shared `api` helper, `DOMContentLoaded`, `esc()`, full Tailwind names | As with every page so far. |
| §8 no hardcoded numbers | All weights, the near-miss floor, page size, half-life and timeout in config. |
| §8 no status strings | Reuses `IncentiveType`, `StudyStatus`, `CredentialLevel`. |
| §9 seeders attach only | Feed needs no seeder; a demo command pre-warms advice. |
| §10 checklist | §12. |

---

## 11. Build order

| # | Step | Verify |
|---|---|---|
| 1 | `config/platform.php` feed block | weights sum to 100 |
| 2 | `StudyFeedService` — ranking only, no filters | breakdown sums to `feed_score` |
| 3 | `GET /participants/me/feed` + `/filters` | Postman: ranking order is sane |
| 4 | Filters | each filter narrows; a bad value 422s |
| 5 | Feed page + `feed.js` | JSON after HTML; filters refetch |
| 6 | **Convert the dashboard panel** (§7.5) | `ParticipantDashboardController` stops passing `$recommended` |
| 7 | Migration + `SkillGapService::analyse()` — **deterministic only** | near-miss list correct with no API key set |
| 8 | `AiClient` + advice + hash gating | second load makes no API call |
| 9 | Coach panel + refresh button | unplug the network: page still renders |
| 10 | `trybe:advice:generate` + demo data (§9 issue 8) | a participant with real near-misses exists |
| 11 | Tests | §13 |

**Step 7 before step 8 is deliberate.** The feature must be demonstrably complete and
useful *before* the AI is attached. If the provider fails on the day, you still have a
working graded feature.

---

## 12. Pre-push checklist

- [ ] `php artisan route:list` clean
- [ ] `php artisan migrate` clean
- [ ] Feed loads with JSON after the HTML; filters refetch
- [ ] `FeedController` passes nothing to its view
- [ ] Feed works with **no** `AI_API_KEY` set
- [ ] Feed reads no karma, boost or tier value anywhere
- [ ] No bare numbers; no status strings
- [ ] `.env` still untracked; key never in a Blade or JS file
- [ ] `feed_breakdown` contributions sum to `feed_score`
- [ ] **No GET endpoint makes an outbound HTTP call** — only the refresh POST and the
      artisan command may reach the AI provider (§6.2)
- [ ] Every new API route is scoped under `participants/me/…`

---

## 13. Tests

- `FeedRankingTest` — breakdown sums to `feed_score`; weights total 100; a better-matched
  study outranks a newer worse-matched one (**suitability beats freshness**); a newer
  study wins only between equal matches; **no karma, boost or tier value can change the
  ordering**, because none is read.
- `FeedFilterTest` — each filter narrows correctly; an invalid value 422s; empty results
  return `[]` not 404.
- `SkillGapAnalysisTest` — **runs with no API key**; near-miss band excludes strong
  matches and hopeless ones; `missing_skills` counts are right; a cold-start participant
  produces the profile-completion branch.
- `SkillGapAdviceTest` — HTTP faked, so the suite never touches the network: a good
  response is stored; a timeout keeps the previous advice and records `last_error`; an
  unparseable response is discarded; an invented `focus_skill` is rejected; **unchanged
  inputs make no second call**.
- `NoOutboundCallOnGetTest` — `Http::fake()` with `Http::assertNothingSent()` around
  `GET /participants/me/feed` and `GET /participants/me/skill-gap`. This is the §6.2 rule
  as a test, so a later refactor cannot quietly reintroduce a blocking page load.
