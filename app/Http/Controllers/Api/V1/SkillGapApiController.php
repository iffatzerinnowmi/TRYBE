<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\AiClient;
use App\Services\SkillGapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API — the skill-gap coach  (Member 4)
 *
 * THE HARD RULE THIS CONTROLLER ENFORCES
 * --------------------------------------
 * show()    NEVER makes an outbound HTTP call. It returns what is stored.
 * refresh() is the ONLY endpoint that may reach the AI provider.
 *
 * Why it matters: generating inline on a GET would mean a page load could
 * block on an 8-second timeout plus a retry — sixteen seconds of spinner,
 * potentially in front of an examiner, with a request thread held open for a
 * third party. The page therefore always renders instantly; if the advice is
 * stale, it says so and offers the button.
 *
 * NoOutboundCallOnGetTest asserts this, so a later refactor cannot quietly
 * reintroduce a blocking page load.
 */
class SkillGapApiController extends Controller
{
    public function __construct(
        private SkillGapService $skillGap,
        private AiClient $ai,
    ) {}

    /**
     * GET /api/v1/participants/me/skill-gap
     *
     * Recomputes the DETERMINISTIC analysis (pure database work) and returns
     * it with whatever advice is stored. No network.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $this->participantOrFail($request);

        $record       = $this->skillGap->refreshAnalysis($user);
        $currentHash  = $this->skillGap->inputsHash($record->analysis);
        $hasAdvice    = $record->advice !== null;

        return response()->json([
            'data' => [
                'user_id' => $user->id,

                // Always present, always computed by us, never by the model.
                'analysis' => $record->analysis,

                // Null until the AI has run successfully at least once.
                'advice' => $record->advice,

                // Is there an AI provider configured at all?
                'ai_available' => $this->ai->enabled(),

                // True when the stored advice was generated from an older
                // analysis — the profile has moved on since.
                'stale' => $hasAdvice && $record->inputs_hash !== $currentHash,

                'generated_at' => $record->generated_at?->toIso8601String(),
                'generated_on' => $record->generated_at?->format('d M Y'),
                'model'        => $record->model,
                'last_error'   => $record->last_error,

                // Nothing to advise on, and why. The page branches on this
                // rather than showing an empty panel.
                'nothing_to_say' => $this->skillGap->hasNothingToSay($record->analysis),
                'reason'         => $record->analysis['reason'] ?? null,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/participants/me/skill-gap/refresh
     *
     * The only endpoint that may call the provider. Throttled by
     * config('platform.feed.advice_refresh_per_hour').
     *
     * Always returns 200. A provider failure is reported in the body rather
     * than as an error status, because the participant's own analysis is
     * still perfectly good and the page should render it.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $this->participantOrFail($request);

        /*
        | Was there already current advice BEFORE we asked?
        |
        | generate() refuses to spend an API call when the inputs hash has not
        | moved — correct, and the whole cost control. But the response used to
        | look identical to a successful regeneration, so the button appeared
        | to do nothing and the only way to tell was to read the timestamp.
        |
        | Captured here rather than inferred afterwards, because after
        | generate() returns, a skipped call and a successful one are
        | indistinguishable.
        */
        $before = $this->skillGap->refreshAnalysis($user);

        $wasAlreadyCurrent = $before->advice !== null
            && $before->isCurrent($this->skillGap->inputsHash($before->analysis));

        $record = $this->skillGap->generate($user);

        $ok = $record->advice !== null && $record->last_error === null;

        return response()->json([
            'message' => match (true) {
                $this->skillGap->hasNothingToSay($record->analysis)
                    => 'Nothing to advise on yet — no studies are close enough to analyse.',

                // Say so, rather than leaving a button that looks broken.
                $wasAlreadyCurrent
                    => 'Your profile has not changed since this advice was written, '
                       . 'so it still stands. Add a skill or complete a study to get new advice.',

                $ok => 'Advice updated.',
                // Surface the provider's actual reason rather than a generic
                // "could not reach" — an SSL misconfiguration, a rejected key
                // and a rate limit are very different problems, and hiding
                // which one it is costs an hour of guessing.
                default => 'AI service unavailable: ' . ($record->last_error ?: 'unknown error')
                           . ' Showing your last saved advice.',
            },
            'generated' => $ok,
            'data'      => $this->show($request)->getData(true)['data'],
        ], 200);
    }

    // -----------------------------------------------------------------

    private function participantOrFail(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user->role === UserRole::PARTICIPANT,
            403,
            'Only participants have a skill-gap analysis.'
        );

        abort_if(
            ! $user->participantProfile,
            404,
            'No participant profile found for this account.'
        );

        return $user;
    }
}
