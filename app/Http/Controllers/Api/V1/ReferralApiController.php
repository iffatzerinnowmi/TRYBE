<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReferralRewardType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralReward;
use App\Models\ResearcherPostCredit;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API — Referral system  (Member 4, graded feature)
 *
 * No logic here. Guard, call ReferralService, shape JSON.
 *
 * WHY /referrals/me AND NOT /participants/{user}/referrals
 * --------------------------------------------------------
 * A referral dashboard is only ever your own — there is no case for reading
 * somebody else's. Making that structural removes a whole class of
 * authorisation bug instead of guarding against it on every endpoint.
 *
 * PRIVACY
 * -------
 * The referred-users list is a list of people who do not know they are on
 * it. Names are truncated to first name plus last initial and emails are
 * never returned. The public validate endpoint returns one name and nothing
 * else.
 */
class ReferralApiController extends Controller
{
    public function __construct(private ReferralService $referrals) {}

    /**
     * GET /api/v1/referrals/me
     *
     * Everything the Refer a Friend page needs, in one call.
     * Runs sync() first, so opening the page reconciles any reward that
     * became due while the person was away.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        // Creating the code and reconciling on read is what delivers the
        // "no manual claim required" promise for anyone who opens the page.
        // The scheduled command covers everyone who does not.
        $this->referrals->codeFor($user);
        $this->referrals->sync($user);

        return response()->json(['data' => $this->payload($request)], 200);
    }

    /**
     * POST /api/v1/referrals/me/code
     *
     * Creates the code if absent (201), or rotates it (200). Rotating
     * invalidates the old link immediately; historical referrals keep the
     * code they actually used, because referrals.code_used is a frozen copy.
     */
    public function storeCode(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'rotate' => ['sometimes', 'boolean'],
        ]);

        $rotate   = (bool) ($data['rotate'] ?? false);
        $existed  = ReferralCode::where('user_id', $user->id)->exists();

        $code = ($rotate && $existed)
            ? $this->referrals->rotateCodeFor($user)
            : $this->referrals->codeFor($user);

        return response()->json([
            'message' => match (true) {
                ! $existed => 'Referral link created.',
                $rotate    => 'Your referral link has been regenerated. The old one no longer works.',
                default    => 'Referral link ready.',
            },
            'data' => [
                'code'      => $code->code,
                'share_url' => $this->referrals->shareUrl($code),
                'visits'    => (int) $code->visits,
            ],
        ], $existed ? 200 : 201);
    }

    /**
     * GET /api/v1/referrals/me/referred-users
     */
    public function referredUsers(Request $request): JsonResponse
    {
        $user = $request->user();

        $referrals = Referral::with(['referredUser:id,name,role', 'qualifyingStudy:id,title'])
            ->where('referrer_id', $user->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'count'          => $referrals->count(),
                'referred_users' => $referrals->map(fn ($r) => $this->referralPayload($r, $user))->all(),
            ],
        ], 200);
    }

    /**
     * GET /api/v1/referrals/me/rewards
     */
    public function rewards(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'rewards' => $this->rewardsFor($request),
                'post_credit_balance' => $user->role === UserRole::RESEARCHER
                    ? $this->referrals->postCreditBalance($user)
                    : null,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/referrals/me/sync
     *
     * Force a reconcile. Handy for the live demo: mark a study complete in
     * one tab, hit this in another, watch the milestone fire.
     */
    public function sync(Request $request): JsonResponse
    {
        $user   = $request->user();
        $result = $this->referrals->sync($user);

        return response()->json([
            'message' => $result['granted']->isEmpty()
                ? 'Referrals up to date.'
                : 'Reward unlocked.',
            'granted_now' => $result['granted']->count(),
            'data'        => $this->payload($request),
        ], 200);
    }

    /**
     * GET /api/v1/referrals/validate/{code}   — PUBLIC
     *
     * Lets the signup page show "You were invited by Ayesha". This is the
     * only route in this feature outside auth:sanctum, because a guest on
     * the signup page has to be able to call it.
     *
     * It returns a name and nothing else. Referral codes are guessable by
     * design, so treat any request to widen this response as a privacy
     * question, not a convenience one.
     */
    public function validateCode(string $code): JsonResponse
    {
        $referralCode = $this->referrals->findByCode($code);

        if (! $referralCode || ! $referralCode->user) {
            return response()->json(['message' => 'That referral link is not valid.'], 404);
        }

        return response()->json([
            'data' => [
                'valid'         => true,
                'referrer_name' => $this->shortName($referralCode->user->name),
            ],
        ], 200);
    }

    /**
     * GET /api/v1/researchers/me/post-credits
     */
    public function postCredits(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->role === UserRole::RESEARCHER, 403,
            'Only researchers earn free post credits.');

        $ledger = ResearcherPostCredit::where('user_id', $user->id)
            ->latest('id')
            ->get();

        return response()->json([
            'data' => [
                'balance' => $this->referrals->postCreditBalance($user),

                // Nothing spends these yet — the researcher tier system that
                // would consume one is Member 3's and does not exist. Stated
                // in the payload rather than left to be discovered.
                'spending_available' => false,
                'spending_note'      => 'Credits are earned now. Spending them needs the researcher tier system.',

                'ledger' => $ledger->map(fn ($row) => [
                    'delta'        => $row->delta,
                    'reason'       => $row->reason->value,
                    'reason_label' => $row->reason->label(),
                    'created_on'   => $row->created_at?->format('d M Y'),
                ])->all(),
            ],
        ], 200);
    }

    // -----------------------------------------------------------------
    // Shared shape
    // -----------------------------------------------------------------

    private function payload(Request $request): array
    {
        $user = $request->user()->fresh();
        $code = $this->referrals->codeFor($user);
        $url  = $this->referrals->shareUrl($code);

        $progress = $this->referrals->progressFor($user);

        $referrals = Referral::with(['referredUser:id,name,role', 'qualifyingStudy:id,title'])
            ->where('referrer_id', $user->id)
            ->orderByDesc('id')
            ->get();

        return [
            'user_id'   => $user->id,
            'role'      => $user->role?->value,
            'code'      => $code->code,
            'share_url' => $url,
            'visits'    => (int) $code->visits,

            'progress'       => $progress,
            'reward_preview' => $this->rewardPreview($user, $progress),

            'referred_users' => $referrals->map(fn ($r) => $this->referralPayload($r, $user))->all(),
            'rewards'        => $this->rewardsFor($request),

            'share_messages' => $this->referrals->shareMessages($user, $url),
        ];
    }

    private function referralPayload(Referral $referral, $referrer): array
    {
        $referred = $referral->referredUser;

        return [
            'referred_user_id' => $referral->referred_user_id,

            // First name plus last initial. These people did not ask to be
            // on a list; there is no reason for it to carry full names.
            'name'   => $this->shortName($referred?->name),
            'role'   => $referred?->role?->value,

            'status'       => $referral->status->value,
            'status_label' => $referral->status->label(),

            // A mixed-role pair is recorded and shown but pays nothing.
            // Saying so stops "3 of 3 and no reward" looking broken.
            'counts_towards_reward' => $referred?->role === $referrer->role,

            'signed_up_at'        => $referral->signed_up_at?->toIso8601String(),
            'signed_up_on'        => $referral->signed_up_at?->format('d M Y'),
            'qualified_at'        => $referral->qualified_at?->toIso8601String(),
            'qualified_on'        => $referral->qualified_at?->format('d M Y'),
            'qualifying_study_id' => $referral->qualifying_study_id,
            'qualifying_study'    => $referral->qualifyingStudy?->title,
        ];
    }

    private function rewardsFor(Request $request): array
    {
        $user = $request->user();

        return ReferralReward::where('user_id', $user->id)
            ->orderBy('milestone')
            ->get()
            ->map(fn (ReferralReward $reward) => [
                'id'           => $reward->id,
                'type'         => $reward->type->value,
                'type_label'   => $reward->type->label(),
                'milestone'    => $reward->milestone,
                'post_credits' => $reward->post_credits,
                'granted_credential_level' => $reward->granted_credential_level?->value,
                'granted_on'   => $reward->created_at?->format('d M Y'),

                // TRUE once CredentialService honours the granted level as a
                // floor. FALSE means the reward is recorded but Member 1's
                // one-line change is still outstanding, so her credentials
                // page still shows the earned level. Reported rather than
                // pretended.
                'applied' => $reward->type === ReferralRewardType::CREDENTIAL_TIER_UNLOCK
                    ? $this->referrals->credentialFloorApplied($user)
                    : true,
            ])
            ->all();
    }

    private function rewardPreview($user, array $progress): ?array
    {
        $type = ReferralRewardType::tryFrom((string) $progress['reward_type']);

        if (! $type) {
            return [
                'type'        => null,
                'description' => 'Referrals from this account type do not unlock a reward.',
            ];
        }

        $preview = [
            'type'        => $type->value,
            'type_label'  => $type->label(),
            'description' => $type->description(),
        ];

        if ($type === ReferralRewardType::CREDENTIAL_TIER_UNLOCK) {
            $current = $user->participantProfile?->credential_level;

            $preview['current_credential_level'] = $current?->value;
            $preview['would_grant'] = $this->referrals->grantedLevel($user)?->value;
        }

        if ($type === ReferralRewardType::RESEARCHER_POST_CREDIT) {
            $preview['post_credits_per_milestone'] = (int) config('platform.referrals.post_credits_per_referral');
            $preview['balance'] = $this->referrals->postCreditBalance($user);
        }

        return $preview;
    }

    /** "Ayesha Rahman" -> "Ayesha R." */
    private function shortName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $parts = array_values(array_filter($parts));

        if (! $parts) {
            return 'A TRYBE user';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0] . ' ' . strtoupper(substr(end($parts), 0, 1)) . '.';
    }
}
