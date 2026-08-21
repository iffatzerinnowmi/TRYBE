<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Services\CompetitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API — Competition & Hackathon Board  (Member 4, graded feature)
 *
 * No rules here. Guard, validate, call CompetitionService, shape JSON.
 *
 * THE VALIDATION RULE THAT MATTERS
 * --------------------------------
 *     'external_url' => ['required', 'url', 'max:2048', 'starts_with:https://']
 *
 * There is deliberately no check that a link points at a real competition —
 * that needs moderation or crawling, and neither is in scope. But `https`
 * only is not editorial judgement, it is safety: a stored `javascript:` URL
 * executes in the session of whoever clicks it. That is stored XSS, not an
 * unverified link, and it costs one rule to prevent.
 */
class CompetitionApiController extends Controller
{
    public function __construct(private CompetitionService $competitions) {}

    /**
     * GET /api/v1/competitions
     *
     * The board: open listings only, newest first. No filters — a board of
     * this size is read, not searched, and a filter bar nobody uses is a
     * maintenance cost with no reader.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['sometimes', 'integer', 'between:1,50'],
        ]);

        $user = $request->user();

        $board = $this->competitions->board($user, $data['limit'] ?? null);

        return response()->json([
            'data' => [
                'count'    => $board->count(),

                // Both permissions come from the server rather than being
                // inferred in JavaScript from the other one. An unverified
                // researcher can neither post nor save, and guessing "can
                // save = cannot post" would offer them a button the API then
                // refuses.
                'can_post' => $this->competitions->canPost($user),
                'can_save' => $user?->role === \App\Enums\UserRole::PARTICIPANT,

                'competitions' => $board->all(),
            ],
        ], 200);
    }

    /**
     * GET /api/v1/competitions/mine
     *
     * A poster's own listings, INCLUDING closed ones — they are hidden from
     * the board, not from the person who wrote them.
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $this->competitions->assertCanPost($request->user());

        $listings = $this->competitions->postedBy($user);

        return response()->json([
            'data' => [
                'count'        => $listings->count(),
                'competitions' => $listings->all(),
            ],
        ], 200);
    }

    /** POST /api/v1/competitions */
    public function store(Request $request): JsonResponse
    {
        $user = $this->competitions->assertCanPost($request->user());

        $data = $this->validated($request);

        $competition = $this->competitions->create($user, $data);

        return response()->json([
            'message' => 'Competition posted.',
            'data'    => ['competition_id' => $competition->id],
        ], 201);
    }

    /** PATCH /api/v1/competitions/{competition} */
    public function update(Request $request, Competition $competition): JsonResponse
    {
        $this->competitions->assertCanPost($request->user());
        $this->competitions->assertOwner($competition, $request->user());

        $this->competitions->update($competition, $this->validated($request, true));

        return response()->json(['message' => 'Competition updated.'], 200);
    }

    /** DELETE /api/v1/competitions/{competition} */
    public function destroy(Request $request, Competition $competition): JsonResponse
    {
        $this->competitions->assertCanPost($request->user());
        $this->competitions->assertOwner($competition, $request->user());

        $competition->delete();

        return response()->json(['message' => 'Competition removed.'], 200);
    }

    // -----------------------------------------------------------------
    // Saving
    // -----------------------------------------------------------------

    /**
     * POST /api/v1/competitions/{competition}/save
     *
     * 200 rather than 201, and idempotent: saving something already saved is
     * not an error the user should have to understand. The unique index makes
     * a double click impossible to turn into two rows.
     */
    public function save(Request $request, Competition $competition): JsonResponse
    {
        $user = $this->competitions->assertParticipant($request->user());

        $data = $request->validate([
            'looking_for_team' => ['sometimes', 'boolean'],
        ]);

        $save = $this->competitions->save(
            $user,
            $competition,
            (bool) ($data['looking_for_team'] ?? false)
        );

        return response()->json([
            'message' => 'Saved.',
            'data'    => [
                'saved'            => true,
                'looking_for_team' => $save->looking_for_team,
                'saved_count'      => $this->competitions->savedCountFor($user),
            ],
        ], 200);
    }

    /**
     * PATCH /api/v1/competitions/{competition}/save
     *
     * Turn the "looking for a team" signal on or off.
     *
     * Separate from save() on purpose: saving is private, and this is the
     * act that publishes your name to other people on the same listing.
     * Making it its own call keeps the consent explicit rather than a side
     * effect of bookmarking something.
     */
    public function updateSave(Request $request, Competition $competition): JsonResponse
    {
        $user = $this->competitions->assertParticipant($request->user());

        $data = $request->validate([
            'looking_for_team' => ['required', 'boolean'],
        ]);

        $save = $this->competitions->setLookingForTeam(
            $user,
            $competition,
            (bool) $data['looking_for_team']
        );

        return response()->json([
            'message' => $save->looking_for_team
                ? 'Others looking at this competition can now see you.'
                : 'You are no longer listed as looking for a team.',
            'data' => ['looking_for_team' => $save->looking_for_team],
        ], 200);
    }

    /**
     * GET /api/v1/competitions/{competition}/teammates
     *
     * Who else is looking for a team here.
     *
     * Only people who ticked the box appear, and only their display name,
     * credential level and a few skills. Ticking the box is the consent;
     * un-ticking removes them immediately.
     *
     * EMAIL IS RECIPROCAL. It is returned only when the viewer has also
     * ticked the box on this competition — otherwise anybody could sweep the
     * board and collect addresses while disclosing nothing of their own.
     * `you_are_listed` tells the interface which case it is in, so it can
     * explain the trade rather than silently omitting a field.
     */
    public function teammates(Request $request, Competition $competition): JsonResponse
    {
        $user = $this->competitions->assertParticipant($request->user());

        $people  = $this->competitions->teammatesFor($competition, $user);
        $listed  = $this->competitions->isLookingForTeam($user, $competition);

        return response()->json([
            'data' => [
                'competition_id' => $competition->id,
                'count'          => $people->count(),
                'you_are_listed' => $listed,
                'people'         => $people->all(),
            ],
        ], 200);
    }

    /** DELETE /api/v1/competitions/{competition}/save */
    public function unsave(Request $request, Competition $competition): JsonResponse
    {
        $user = $this->competitions->assertParticipant($request->user());

        $this->competitions->unsave($user, $competition);

        return response()->json([
            'message' => 'Removed from your saved list.',
            'data'    => ['saved' => false, 'saved_count' => $this->competitions->savedCountFor($user)],
        ], 200);
    }

    /**
     * GET /api/v1/participants/me/competitions
     *
     * The saved list, soonest deadline first. Closed ones are NOT hidden
     * here: something you saved going past its deadline is information you
     * want, and silently dropping it looks like data loss.
     */
    public function saved(Request $request): JsonResponse
    {
        $user = $this->competitions->assertParticipant($request->user());

        $saved = $this->competitions->savedFor($user);

        return response()->json([
            'data' => [
                'count'        => $saved->count(),
                'competitions' => $saved->all(),
            ],
        ], 200);
    }

    // -----------------------------------------------------------------

    /**
     * @param  bool  $partial  PATCH sends only what changed
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name'         => [$required, 'string', 'max:160'],

            // https only — see the class docblock. This is the one link check
            // that is about safety rather than editorial judgement.
            'external_url' => [$required, 'url', 'max:2048', 'starts_with:https://'],

            'organizer'    => ['sometimes', 'nullable', 'string', 'max:120'],
            'description'  => ['sometimes', 'nullable', 'string', 'max:2000'],
            'deadline'     => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
            'team_min'     => ['sometimes', 'nullable', 'integer', 'between:1,50'],
            'team_max'     => ['sometimes', 'nullable', 'integer', 'between:1,50', 'gte:team_min'],
            'prize'        => ['sometimes', 'nullable', 'string', 'max:160'],
            'location'     => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);
    }
}
