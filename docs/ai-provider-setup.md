# AI provider — setup and key safety

**NVIDIA NIM is out.** Its signup requires phone verification and Bangladesh is not in
its country list. Manual verification is too slow for this deadline.

**Use Google Gemini via AI Studio.** Bangladesh **is** on Google's supported region
list (verified against `ai.google.dev/gemini-api/docs/available-regions`, listed between
Bahrain and Barbados), and it needs **no phone number** — just a Google account.

---

## 1. Provider comparison

| Provider | Free | Phone needed | Bangladesh | Notes |
|---|---|---|---|---|
| **Google Gemini (AI Studio)** ← **use this** | yes | **no** | **yes, confirmed** | Google account, 18+, age-verified. ~10 req/min, 250/day. Plenty. |
| Groq | yes | no | likely | Very fast. ~8K context on free tier — fine for our short payloads. Good backup. |
| OpenRouter | yes | no | likely | One key, 35+ free models. 200 req/day. Useful if you want to A/B models. |
| NVIDIA NIM | yes | **yes** | **blocked** | Ruled out. |

Have **one backup key ready** before the viva. Getting a Groq key takes two minutes and
costs nothing; discovering a rate limit mid-demo is expensive.

---

## 2. Get the Gemini key

1. **aistudio.google.com** → sign in with Google.
2. If it blocks you, it is one of three things, and the error page says which: region,
   under 18, or **age not verified on your Google account**. The third is the common one
   and is fixable in Google account settings.
3. Click **Get API Key** → **Create API key**. Starts `AIza…`.
4. Copy it **straight into `.env`** (§3). Not into a chat, a commit, a screenshot, or a
   `tinker` session that gets written to disk.

**Model:** `gemini-2.5-flash` — fast, cheap, more than good enough for a few sentences of
structured advice.

### 2.1 Two ways to call it

Gemini has a **native** API and an **OpenAI-compatible** endpoint. Prefer the
OpenAI-compatible one if it is available on your key, because it keeps the client
swappable — Groq and OpenRouter speak the same shape, so a provider change stays a config
edit rather than a rewrite.

Check the current base URL in Google's docs when you wire it up; the compatibility layer
has moved before. If the compatible endpoint gives you trouble, use the native REST
endpoint — it is one `Http::post()` either way, and `AiClient` is the only class that
knows the difference.

**Rename `NimClient` → `AiClient`** in the feed plan. Nothing else changes: it was always
"the only class that knows which vendor we use", and that is exactly why swapping is
cheap.

---

## 3. Where the key goes — three files, one secret

Follow the pattern already in this project for VAPID keys (`config/webpush.php`).

### 3.1 `.env` — the real key. **Never committed.**

```dotenv
AI_PROVIDER=gemini
AI_API_KEY=AIza-your-real-key-here
AI_BASE_URL=https://generativelanguage.googleapis.com/v1beta/openai
AI_MODEL=gemini-2.5-flash
AI_TIMEOUT=8
```

Verified: `.env` is in `.gitignore` (line 3) and is not tracked.

Generic names (`AI_*`, not `GEMINI_*`) so switching provider is a value change, not a
rename across three files.

### 3.2 `.env.example` — names only, no values. **Committed.**

```dotenv
AI_PROVIDER=gemini
AI_API_KEY=
AI_BASE_URL=https://generativelanguage.googleapis.com/v1beta/openai
AI_MODEL=gemini-2.5-flash
AI_TIMEOUT=8
```

This is how teammates learn the variable exists without seeing your key. Each gets their
own free key, or runs without one — the feed degrades gracefully by design.

### 3.3 `config/services.php` — reads env, holds no secret. **Committed.**

```php
'ai' => [
    'provider' => env('AI_PROVIDER', 'gemini'),
    'key'      => env('AI_API_KEY'),
    'base_url' => env('AI_BASE_URL'),
    'model'    => env('AI_MODEL', 'gemini-2.5-flash'),
    'timeout'  => (int) env('AI_TIMEOUT', 8),
    'enabled'  => env('AI_API_KEY') !== null,
],
```

---

## 4. The rules that actually keep it safe

**1. `config()` in code, `env()` only inside `config/`.**

```php
$key = config('services.ai.key');   // ✅
$key = env('AI_API_KEY');           // ❌ returns NULL after config:cache
```

The classic Laravel trap. `php artisan config:cache` freezes config files and `env()`
stops working everywhere else — silently.

**2. The key never leaves the server.** All calls happen in `AiClient` (PHP). Never in a
Blade file, never in `resources/js/`, never in an API response. The browser calls *your*
endpoint; your server calls Google.

**3. Never log it.** Log the status code and message on failure, never the headers.

**4. Bots scrape GitHub for `AIza` prefixes.** Google's own secret scanning also catches
them. Before every push:

```bash
git status --short | grep -i "\.env$"      # should return nothing
git ls-files | grep -x ".env"              # should return nothing
```

**5. If it is ever committed, rotate it — don't just delete the line.** Git keeps
history; the key stays readable in the earlier commit. Revoke in AI Studio, create a new
one. Assume any key that touched a commit is burned.

**6. Check repo visibility.** If `github.com/iffatzerinnowmi/TRYBE` is public, all of the
above matters more. If unsure, assume public.

**7. Not in the Postman collection.** That file is committed. Postman only ever talks to
`127.0.0.1:1048`.

---

## 5. Verify

```bash
php artisan config:clear
php artisan tinker
```

```php
>>> config('services.ai.enabled')
=> true
>>> substr(config('services.ai.key'), 0, 4)
=> "AIza"
```

Print **only the prefix** — tinker history is a file on disk. (There is already a stray
`toArray())` file in this repo from a mistyped tinker command, so this is not
hypothetical.)

Then a real call:

```bash
php artisan trybe:advice:generate --user=sarah.participant@trybe.test
```

---

## 6. Before the viva

- [ ] Key in `.env`; `.env.example` has the name with no value
- [ ] `git status` shows no `.env`
- [ ] `php artisan config:clear` after editing `.env`
- [ ] Advice **pre-generated** for the demo account — no GET ever calls the API (feed
      plan §6.2), so nothing waits on the network during the demo
- [ ] **Tested with the key removed** — the feed must still render. That's the fallback
      you designed; confirm it works before you need it
- [ ] A **backup Groq key** in a note, and you know which two `.env` lines to change
- [ ] Your answer to *"what personal data goes to Google?"* → **none**: skills, topic
      names and counts. No names, emails, ids or study titles

---

## 7. If it fails on the day

In order:

1. **Stored advice still renders** from `skill_gap_advice`, with `generated_at` shown.
2. **The deterministic analysis still renders** — the near-miss studies and the exact
   missing skills. That is your feature; the AI is commentary on top.
3. **Swap to Groq**: change `AI_BASE_URL` to `https://api.groq.com/openai/v1`,
   `AI_MODEL` to a Groq model, `AI_API_KEY` to the backup key, then
   `php artisan config:clear`. `AiClient` does not change.

Keep a screenshot of the coach panel working, just in case.
