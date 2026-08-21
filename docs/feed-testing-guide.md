# Testing the recommendation feed

Everything below works **without an AI key**. Do steps 1–4 first; the AI is step 5 and
is deliberately the last thing attached.

---

## 1. Get it running

```bash
php artisan migrate            # adds skill_gap_advice — NOT migrate:fresh
npm run build                  # feed.js is new; without this the page sits on "Loading…"
php artisan serve --port=1048
```

Then check the routes registered:

```bash
php artisan route:list --path=feed
php artisan route:list --path=skill-gap
```

You should see five: two GETs under `participants/me/feed`, a GET and a POST under
`participants/me/skill-gap`, and the web page at `participant/feed`.

---

## 2. Automated tests — start here

```bash
php artisan test --filter=FeedRankingTest
php artisan test --filter=SkillGapTest
```

Both suites run with **no API key and `Http::fake()`**, so they never touch the network.
That is the point: the graded feature has to be provably complete before the AI exists.

The tests worth knowing by name:

| Test | Proves |
|---|---|
| `test_suitability_beats_freshness` | a well-matched old study outranks a badly-matched new one |
| `test_breakdown_contributions_add_up_to_the_feed_score` | the arithmetic reconciles |
| `test_no_karma_boost_or_tier_value_can_affect_the_ordering` | 5,000 karma changes nothing |
| `test_the_analysis_works_with_no_api_key_and_no_network` | the deterministic half stands alone |
| `test_the_skill_gap_get_endpoint_makes_no_outbound_request` | a page load can never block on the AI |
| `test_an_invented_focus_skill_is_rejected` | the model cannot make up a gap |
| `test_unchanged_inputs_make_no_second_call` | hash gating actually gates |

---

## 3. In the browser

Log in as a participant and click **Feed** in the navbar (`/participant/feed`).

**Open the Network tab and reload.** You should see the HTML arrive first, then four
JSON requests. That is the proof the page is API-driven — nothing is rendered by Blade.

Check in order:

1. **Studies appear, ranked.** Each card shows a match percentage and, underneath,
   `feed score 76.8 = match 66.7 + recency 5.1 + urgency 5.0`. **Add those three up.**
   They must equal the feed score — that reconciliation is the thing to point at in a
   viva.
2. **Filters work.** Change Compensation, Format or Duration; the list refetches. Click
   topic chips to toggle them. **Clear** resets everything.
3. **An empty result is graceful** — pick filters that match nothing. You should get
   "Nothing matches. Try clearing a filter", not an error and not a 404.
4. **The dashboard preview agrees with the feed.** Go to `/dashboard`. The "Recommended
   for you" panel now calls the same endpoint with `limit=4`, so its top item must be
   the same study as the top of the feed. If they ever disagree, something is wrong —
   they are the same query by construction.

---

## 4. The coach, without any AI

Still with no key configured. The right-hand **Close the gap** panel should show:

- how many studies are "just out of reach"
- **Missing skills**, with how many studies each one blocks
- **Near misses** — the studies themselves, with how far short you fell
- a grey note saying AI coaching is not configured

That is the whole deterministic feature, and it is what you demo if the network dies.

**If the panel says "Nothing to analyse yet"**, the participant has no studies in the
near-miss band — the band is 40–70%, so they either qualify for everything or match
nothing. That is correct behaviour, not a bug, but it makes a poor demo. Fix it by
giving a participant a partial skill match:

```bash
php artisan tinker
```

```php
// A study needing two skills where the participant has only one
$p = App\Models\User::where('email','sarah.participant@trybe.test')->first();
$p->participantProfile->update(['skills' => 'Survey', 'age' => 25]);

$s = App\Models\Study::where('status','open')->first();
App\Models\StudyMatchCriteria::updateOrCreate(
    ['study_id' => $s->id],
    ['age_min'=>18,'age_max'=>40,'required_skills'=>['survey','ui testing'],'availability_days'=>60]
);
```

Reload the feed. That study should now appear as a near miss with `ui testing` listed as
the blocker.

---

## 5. Turn the AI on

Add to `.env` (see `docs/ai-provider-setup.md` for the key):

```dotenv
AI_API_KEY=AIza-your-key
```

```bash
php artisan config:clear
php artisan trybe:advice:generate --user=sarah.participant@trybe.test
```

You should see the participant's email and a one-line headline. Then reload the feed —
the coach panel now shows the advice with a **Refresh advice** button.

**Three things to verify, because they are the design claims:**

```bash
# 1. Idempotence — a second run makes no call, because the hash has not moved
php artisan trybe:advice:generate --user=sarah.participant@trybe.test
#    -> reports the same headline, no new generation

# 2. Force it anyway
php artisan trybe:advice:generate --user=sarah.participant@trybe.test --force
```

**3. Kill the network and reload the page.** Disconnect wifi, hard-refresh the feed. The
feed still renders, the analysis still renders, and the stored advice still shows with
its generated date. Nothing 500s. That is the fallback working.

---

## 6. Postman

| Method | URL |
|---|---|
| GET | `http://127.0.0.1:1048/api/v1/participants/me/feed?limit=5` |
| GET | `http://127.0.0.1:1048/api/v1/participants/me/feed?incentive_type=volunteer&method=online` |
| GET | `http://127.0.0.1:1048/api/v1/participants/me/feed?incentive_type=nonsense` → **422** |
| GET | `http://127.0.0.1:1048/api/v1/participants/me/feed/filters` |
| GET | `http://127.0.0.1:1048/api/v1/participants/me/skill-gap` |
| POST | `http://127.0.0.1:1048/api/v1/participants/me/skill-gap/refresh` |

Log in as a participant first (the existing collection's login request works).

Two error paths worth capturing:

- **A bad filter value returns 422**, not a silently unfiltered list. Try
  `?incentive_type=nonsense`.
- **A researcher gets 403** on the feed — it is a participant surface.

And the throttle: hit the refresh POST seven times in a minute. The seventh returns
**429**, because the limit comes from `config('platform.feed.advice_refresh_per_hour')`.

---

## 7. What to check before you push

- [ ] `php artisan route:list` clean
- [ ] `php artisan migrate` clean
- [ ] Both test suites green **with no API key set**
- [ ] Network tab shows JSON arriving after the HTML
- [ ] The three feed-score contributions add up on screen
- [ ] The dashboard preview and the feed agree on the top study
- [ ] Feed renders with the wifi off
- [ ] `.env` still untracked (`git status --short | grep .env` → nothing)

---

## 8. Things that will look like bugs but are not

| You see | Why |
|---|---|
| Coach panel says "Nothing to analyse yet" | No studies in the 40–70% band. §4 shows how to create one. |
| A study with a closer deadline sits below a better match | Working as designed — urgency is weighted 5 against match's 85. |
| Advice says "Based on an earlier profile" | The analysis changed since the advice was generated. Click Refresh. |
| Second `advice:generate` run does nothing | Hash gating. Use `--force`. |
| Feed shows fewer studies than exist | Studies you are already in are excluded, and closed studies never appear. |
