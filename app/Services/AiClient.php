<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The ONLY class that knows which AI vendor we use.  (Member 4)
 *
 * Currently Google Gemini through AI Studio. NVIDIA NIM was the original
 * choice but requires phone verification and does not list Bangladesh.
 *
 * Gemini, Groq and OpenRouter all expose an OpenAI-compatible
 * /chat/completions endpoint, so switching provider is three lines in .env
 * and nothing here changes. That is why every config key is generic
 * (services.ai.*, AI_API_KEY) rather than vendor-named.
 *
 * SAFETY PROPERTIES THIS CLASS GUARANTEES
 * ---------------------------------------
 * 1. It NEVER throws. A failure returns null and records why, because a dead
 *    third party must never take a page down.
 * 2. It NEVER receives or sends personal data. The caller passes an analysis
 *    of skills and topic names — no names, emails, ids or study titles.
 * 3. It NEVER trusts the response. Output is parsed, shape-checked, and the
 *    focus skill is validated against the skills we actually sent, so the
 *    model cannot invent a gap that is not in our data.
 * 4. It NEVER logs the API key.
 */
class AiClient
{
    private ?string $lastError = null;

    public function enabled(): bool
    {
        return ! blank(config('services.ai.key'));
    }

    public function model(): string
    {
        return (string) config('services.ai.model');
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Turn a skill gap into concrete advice.
     *
     * @param  array  $analysis  from SkillGapService::analyse()
     * @return array|null        validated advice, or null on any failure
     */
    public function skillAdvice(array $analysis): ?array
    {
        $this->lastError = null;

        if (! $this->enabled()) {
            $this->lastError = 'No AI API key configured.';

            return null;
        }

        $allowedSkills = collect($analysis['missing_skills'] ?? [])
            ->pluck('skill')->map(fn ($s) => strtolower(trim($s)))->all();

        if (empty($allowedSkills)) {
            $this->lastError = 'No missing skills to advise on.';

            return null;
        }

        $response = $this->chat($this->prompt($analysis));

        if ($response === null) {
            return null;   // lastError already set
        }

        return $this->validate($response, $allowedSkills);
    }

    // -----------------------------------------------------------------

    /**
     * The payload sent to the provider. Read it and note what is absent:
     * no name, no email, no user id, no study titles. Skills and topic
     * names only.
     */
    private function prompt(array $analysis): string
    {
        $payload = [
            'missing_skills'   => $analysis['missing_skills'] ?? [],
            'missing_topics'   => $analysis['missing_topics'] ?? [],
            'current_skills'   => $analysis['current_skills'] ?? [],
            'credential_level' => $analysis['credential_level'] ?? null,
            'near_miss_count'  => $analysis['near_miss_count'] ?? 0,
        ];

        return <<<PROMPT
        You are advising a participant on a research-study platform — someone
        who takes part in studies as a subject, not someone who runs them.

        Our own scoring engine has already worked out which skills are
        blocking them from qualifying for studies. Do NOT re-rank, re-score,
        or second-guess that analysis.

        THE READER ALREADY KNOWS WHAT THEIR GAP IS. It is printed on screen
        directly above your answer. So do NOT begin by naming it, do NOT tell
        them that improving it would help, and do NOT write anything of the
        form "learning X is the biggest step to unlocking more studies". That
        sentence is worthless to them: it is what they just read.

        Write the part the platform CANNOT work out for itself — what this
        skill actually involves in a research setting, and what a person does
        this week to acquire it.

        Analysis:
        {$this->json($payload)}

        Reply with ONLY a JSON object, no markdown fence, in exactly this shape:

        {
          "focus_skill": "must be copied exactly from missing_skills",
          "why_it_matters": "what researchers actually use this skill FOR in a
                             study, in one or two plain sentences. Concrete.
                             Not 'it is in demand' or 'it unlocks studies'.",
          "steps": [
            {"action": "something specific they can start this week",
             "effort": "e.g. an afternoon"}
          ],
          "encouragement": "one short sentence, grounded in what they already have"
        }

        Give two or three steps. Each must name a specific thing to DO — a
        task to practise, a document to write, a session to sit in on. Not
        "take a course", not "read about it", not "practise more".

        If the skill is so vague or general that no useful research-specific
        advice exists, say so plainly in why_it_matters rather than inventing
        substance. An honest "this is too broad to advise on precisely" is
        more useful than confident filler.
        PROMPT;
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * One chat call. Returns the raw assistant text, or null.
     *
     * TWO WIRE FORMATS, ONE METHOD
     * ----------------------------
     * AI_PROVIDER=gemini-native  -> Google's own generateContent endpoint,
     *                               authenticated with an x-goog-api-key
     *                               header.
     * anything else              -> the OpenAI-compatible /chat/completions
     *                               shape, authenticated with a Bearer token.
     *                               Gemini's compat layer, Groq and
     *                               OpenRouter all speak this.
     *
     * Both exist because Google is midway through changing its key format.
     * Older keys start "AIza"; newly issued ones start "AQ." and are reported
     * to work on the native endpoint while being rejected by the
     * compatibility layer. Supporting both means a key-format change is a
     * one-line .env edit rather than a debugging session.
     */
    private function chat(string $prompt): ?string
    {
        $native = config('services.ai.provider') === 'gemini-native';

        try {
            $response = $native
                ? $this->postNativeGemini($prompt)
                : $this->postOpenAiCompatible($prompt);
        } catch (\Throwable $e) {
            // Network-level failure — nothing reached the provider.
            // Truncated so a long error cannot bloat the database column.
            $message = $e->getMessage();

            // A TLS failure is a machine-configuration problem, not an
            // outage, and it looks identical to one unless we say so. Name
            // the fix in the message rather than leaving it to be rediscovered.
            $isTls = Str::contains($message, ['SSL certificate', 'certificate verify', 'cURL error 60']);

            $bundle = (string) config('services.ai.ca_bundle');

            // Three distinct situations that all arrive here as error 60.
            // Saying "set AI_CA_BUNDLE" to somebody who has already set it
            // sends them to check the one thing that is not wrong.
            $this->lastError = match (true) {
                ! $isTls        => 'Request failed: ' . Str::limit($message, 180),
                $bundle === ''  => 'TLS verification failed and no AI_CA_BUNDLE is configured. '
                                   . 'Set it in .env to the full path of a cacert.pem.',
                ! is_file($bundle) => 'TLS verification failed: AI_CA_BUNDLE points at "' . $bundle
                                   . '", which does not exist. Check the path.',
                default         => 'TLS verification failed even though AI_CA_BUNDLE ("' . $bundle
                                   . '") was used. The bundle may be stale, or the connection '
                                   . 'is being intercepted.',
            };

            Log::warning('AI request failed.', [
                'error'            => $message,
                'tls'              => $isTls,
                'ca_bundle'        => $bundle,
                'ca_bundle_exists' => $bundle !== '' && is_file($bundle),
            ]);

            return null;
        }

        if (! $response->successful()) {
            // Include the provider's own message — an HTTP status alone does
            // not distinguish a bad key from a bad model name.
            $detail = Str::limit((string) $response->body(), 140);

            $this->lastError = 'Provider returned HTTP ' . $response->status() . '. ' . $detail;
            Log::warning('AI provider error.', [
                'status' => $response->status(),
                'body'   => $detail,
            ]);

            return null;
        }

        $text = $native
            ? data_get($response->json(), 'candidates.0.content.parts.0.text')
            : data_get($response->json(), 'choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            $this->lastError = 'Provider returned an empty message.';

            return null;
        }

        return $text;
    }

    /**
     * The shared request builder: timeout, one retry, and the CA bundle.
     *
     * Both transports go through here so a TLS fix can never be applied to
     * one and forgotten on the other.
     */
    private function request()
    {
        $pending = Http::timeout((int) config('services.ai.timeout', 8))
            ->retry(1, 250, throw: false)
            ->acceptJson();

        $bundle = (string) config('services.ai.ca_bundle');

        // Only when it is actually there. A path pointing at a file that does
        // not exist produces cURL error 77, which reads like a different
        // problem entirely and costs an hour — better to fall back to
        // php.ini and fail with the familiar error 60 than to invent a new one.
        if ($bundle !== '' && is_file($bundle)) {
            $pending = $pending->withOptions(['verify' => $bundle]);
        }

        return $pending;
    }

    /** OpenAI-compatible: Gemini's compat layer, Groq, OpenRouter. */
    private function postOpenAiCompatible(string $prompt)
    {
        $base = rtrim((string) config('services.ai.base_url'), '/');

        return $this->request()
            ->withToken((string) config('services.ai.key'))
            ->post($base . '/chat/completions', [
                'model'       => $this->model(),
                'temperature' => 0.3,
                'messages'    => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);
    }

    /**
     * Google's native generateContent. Note the key goes in a header, not a
     * query string — a key in a URL ends up in server logs and proxy caches.
     */
    private function postNativeGemini(string $prompt)
    {
        $base  = rtrim((string) config('services.ai.base_url'), '/');
        $model = $this->model();

        return $this->request()
            ->withHeaders(['x-goog-api-key' => (string) config('services.ai.key')])
            ->post($base . '/models/' . $model . ':generateContent', [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => ['temperature' => 0.3],
            ]);
    }

    /**
     * Parse and shape-check. An unparseable or out-of-scope response is
     * discarded, never stored — the caller keeps whatever advice it had.
     */
    private function validate(string $raw, array $allowedSkills): ?array
    {
        // Models often wrap JSON in a ```json fence despite being told not to.
        $clean = trim($raw);
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $clean);

        $data = json_decode(trim($clean), true);

        if (! is_array($data)) {
            $this->lastError = 'Response was not valid JSON.';

            return null;
        }

        // NOTE: there is deliberately no "headline" field any more. The gap is
        // named by OUR engine and rendered from focus_skill, so the model
        // cannot introduce a term we never sent — which is exactly what
        // happened when a free-text headline was allowed: a study TOPIC
        // ("reading") appeared in a sentence about skills, because only
        // focus_skill was ever checked against the analysis.
        foreach (['focus_skill', 'why_it_matters'] as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key]) || trim($data[$key]) === '') {
                $this->lastError = 'Response was missing "' . $key . '".';

                return null;
            }
        }

        // The model does not get to invent a skill we never sent.
        if (! in_array(strtolower(trim($data['focus_skill'])), $allowedSkills, true)) {
            $this->lastError = 'Model returned a skill that was not in the analysis.';
            Log::info('AI returned an out-of-scope focus_skill.', [
                'returned' => $data['focus_skill'],
            ]);

            return null;
        }

        $steps = [];

        foreach (array_slice($data['steps'] ?? [], 0, 4) as $step) {
            if (is_array($step) && ! blank($step['action'] ?? null)) {
                $steps[] = [
                    'action' => (string) $step['action'],
                    'effort' => (string) ($step['effort'] ?? ''),
                ];
            }
        }

        // Advice with no actionable step is not advice. Rejecting it here
        // means the panel keeps the previous answer rather than showing a
        // paragraph that tells the reader nothing to do.
        if (empty($steps)) {
            $this->lastError = 'Response contained no actionable steps.';

            return null;
        }

        return [
            'focus_skill'    => (string) $data['focus_skill'],
            'why_it_matters' => (string) $data['why_it_matters'],
            'steps'          => $steps,
            'encouragement'  => (string) ($data['encouragement'] ?? ''),
        ];
    }
}
