<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Competition;
use App\Models\CompetitionSave;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * FEATURE — Competition & Hackathon Board  (Member 4, graded feature)
 *
 * WHAT THIS FEATURE IS, AND IS NOT
 * --------------------------------
 * A listing is a POINTER. TRYBE does not run competitions, take entries,
 * form teams or handle money — it helps people find one and sends them to
 * where it actually lives. Everything here supports that and nothing more,
 * which is why there is no registration table and no checkout.
 *
 * WHO MAY POST, AND WHY IT IS NOT OPEN
 * ------------------------------------
 * Verified researchers, verified organizations and admins. Not participants.
 *
 * The alternative — anyone shares anything, LinkedIn-style — means inheriting
 * a moderation problem: spam, dead links, affiliate links, and no way to tell
 * a hackathon from a referral scheme. Moderation is a whole feature of its own
 * (reports, a queue, an admin screen) and none of it is in this module.
 *
 * Curated posting means every listing carries the name and verified badge of
 * whoever posted it. That accountability is what replaces link moderation:
 * a bad link has an owner.
 */
class CompetitionService
{
    /** Roles allowed to post a listing. */
    private const POSTER_ROLES = [
        UserRole::RESEARCHER,
        UserRole::ORGANIZATION,
        UserRole::ADMIN,
    ];

    // =================================================================
    // Permissions
    // =================================================================

    /**
     * May this user post a listing?
     *
     * Researchers and organizations must be verified; admins are trusted by
     * definition. Verification is Member 1's column — read only, never
     * written here.
     */
    public function canPost(?User $user): bool
    {
        if (! $user || ! in_array($user->role, self::POSTER_ROLES, true)) {
            return false;
        }

        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        /*
        | users.verification_status is CAST to VerificationStatus, so it comes
        | back as an enum instance, never a string. Comparing it to 'verified'
        | with === is always false — which silently hid the posting panel from
        | every verified researcher, with no error anywhere.
        |
        | StudyInvitationService already compares against the enum case; this
        | now matches it.
        */
        return $user->verification_status === VerificationStatus::VERIFIED;
    }

    public function assertCanPost(?User $user): User
    {
        abort_unless(
            $this->canPost($user),
            403,
            'Only verified researchers, organizations and admins can post competitions.'
        );

        return $user;
    }

    /** Only the person who posted it may edit or remove it. */
    public function assertOwner(Competition $competition, ?User $user): void
    {
        abort_unless(
            $user && $competition->posted_by === $user->id,
            403,
            'You may only manage competitions you posted.'
        );
    }

    public function assertParticipant(?User $user): User
    {
        abort_unless(
            $user && $user->role === UserRole::PARTICIPANT,
            403,
            'Only participants can save competitions.'
        );

        return $user;
    }

    // =================================================================
    // Reads
    // =================================================================

    /**
     * The board.
     *
     * Newest first, and closed listings are excluded — a competition whose
     * deadline has passed is not an opportunity, and leaving it on the board
     * wastes the reader's attention. Posters still see their own closed
     * listings through mine().
     */
    public function board(?User $viewer = null, ?int $limit = null): Collection
    {
        $limit = $limit ?? (int) config('platform.competitions.page_size');

        $competitions = Competition::with('poster:id,name,role,verification_status')
            ->open()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->decorate($competitions, $viewer);
    }

    /** One user's saved competitions, soonest deadline first. */
    public function savedFor(User $user): Collection
    {
        $competitions = Competition::with('poster:id,name,role,verification_status')
            ->whereIn('id', CompetitionSave::where('user_id', $user->id)->select('competition_id'))
            /*
            | Soonest deadline first, undated last. MySQL sorts NULL before
            | everything on ASC, which would put "no deadline" at the top of a
            | list whose entire purpose is what closes soonest — hence the
            | explicit null ordering.
            */
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->orderByDesc('created_at')
            ->get();

        return $this->decorate($competitions, $user);
    }

    /** A poster's own listings, including ones that have closed. */
    public function postedBy(User $user): Collection
    {
        $competitions = Competition::with('poster:id,name,role,verification_status')
            ->where('posted_by', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->decorate($competitions, $user);
    }

    // =================================================================
    // Writes
    // =================================================================

    public function create(User $poster, array $data): Competition
    {
        return Competition::create([
            'posted_by'    => $poster->id,
            'name'         => $data['name'],
            'organizer'    => $data['organizer'] ?? null,
            'description'  => $data['description'] ?? null,
            'external_url' => $data['external_url'],
            'deadline'     => $data['deadline'] ?? null,
            'team_min'     => $data['team_min'] ?? null,
            'team_max'     => $data['team_max'] ?? null,
            'prize'        => $data['prize'] ?? null,
            'location'     => $data['location'] ?? null,
        ]);
    }

    public function update(Competition $competition, array $data): Competition
    {
        $competition->update(array_intersect_key($data, array_flip([
            'name', 'organizer', 'description', 'external_url',
            'deadline', 'team_min', 'team_max', 'prize', 'location',
        ])));

        return $competition->fresh();
    }

    /**
     * Save, idempotently.
     *
     * firstOrCreate on the unique pair, so a double click produces one row
     * rather than a 409 the user has to understand. Saving is not a
     * destructive act — there is nothing to warn anybody about.
     */
    public function save(User $user, Competition $competition, bool $lookingForTeam = false): CompetitionSave
    {
        $save = CompetitionSave::firstOrCreate([
            'competition_id' => $competition->id,
            'user_id'        => $user->id,
        ]);

        if ($lookingForTeam && ! $save->looking_for_team) {
            $save->update(['looking_for_team' => true]);
        }

        return $save->fresh();
    }

    /**
     * Turn the "looking for a team" signal on or off.
     *
     * THE CONSENT MODEL, WHICH IS THE WHOLE POINT
     * -------------------------------------------
     * Ticking this box is what publishes your name to other people looking at
     * the same competition. Nothing is shared by default: saving a
     * competition is private, and only this flag makes you visible. Turning
     * it off removes you immediately — there is no cached copy and no
     * "recently looking" list.
     *
     * It requires an existing save rather than creating one, so the flag can
     * never be set on a competition somebody has not chosen to follow.
     */
    public function setLookingForTeam(User $user, Competition $competition, bool $looking): CompetitionSave
    {
        $save = CompetitionSave::where('competition_id', $competition->id)
            ->where('user_id', $user->id)
            ->first();

        abort_if(! $save, 404, 'Save this competition first.');

        $save->update(['looking_for_team' => $looking]);

        return $save->fresh();
    }

    /**
     * Who else is looking for a team on this competition.
     *
     * CONTACT DETAILS ARE RECIPROCAL
     * ------------------------------
     * A teammate list with no way to reach anybody is useless, so email is
     * included — but only for a viewer who has ALSO ticked "looking for a
     * team" on this same competition.
     *
     * Why not simply show it to everyone who asks: a participant could save
     * every listing on the board, call this endpoint once each, and walk away
     * with every address on the platform, having disclosed nothing. Requiring
     * the viewer to be looking too means the only way to see contact details
     * is to publish your own, to the same small group, on the same listing.
     * That is a fair trade; one-way disclosure is not.
     *
     * Everything else — name, credential level, a few skills — is visible to
     * anyone who has saved the competition, and matches what the public
     * participant page already shows.
     */
    public function teammatesFor(Competition $competition, ?User $viewer = null): Collection
    {
        $viewerIsLooking = $viewer && $this->isLookingForTeam($viewer, $competition);

        return CompetitionSave::with('user.participantProfile')
            ->where('competition_id', $competition->id)
            ->where('looking_for_team', true)
            ->when($viewer, fn ($q) => $q->where('user_id', '!=', $viewer->id))
            ->latest('updated_at')
            ->get()
            ->filter(fn (CompetitionSave $s) => $s->user !== null)
            ->map(fn (CompetitionSave $s) => [
                'user_id'          => $s->user->id,
                'name'             => $s->user->name,
                'credential_level' => $s->user->participantProfile?->credential_level?->value,
                'skills'           => array_slice(
                    array_filter(array_map(
                        'trim',
                        explode(',', (string) ($s->user->participantProfile?->skills ?? ''))
                    )),
                    0,
                    4
                ),

                // Null unless the viewer has published their own, on this
                // same competition. Never a partial or masked address —
                // either you are in this exchange or you are not.
                'email'            => $viewerIsLooking ? $s->user->email : null,

                'since'            => $s->updated_at?->format('d M Y'),
            ])
            ->values();
    }

    public function isLookingForTeam(User $user, Competition $competition): bool
    {
        return CompetitionSave::where('competition_id', $competition->id)
            ->where('user_id', $user->id)
            ->where('looking_for_team', true)
            ->exists();
    }

    /** Unsave. Silent when it was not saved — the end state is the same. */
    public function unsave(User $user, Competition $competition): void
    {
        CompetitionSave::where('competition_id', $competition->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    public function hasSaved(User $user, Competition $competition): bool
    {
        return CompetitionSave::where('competition_id', $competition->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function savedCountFor(User $user): int
    {
        return CompetitionSave::where('user_id', $user->id)->count();
    }

    // =================================================================
    // Payload
    // =================================================================

    /**
     * Add the per-viewer bits in ONE query rather than one per card.
     *
     * Without this, a twelve-card board asks "has this person saved it?"
     * twelve times. Same reasoning as bulk-loading criteria in the matcher.
     */
    private function decorate(Collection $competitions, ?User $viewer): Collection
    {
        $ids = $competitions->pluck('id');

        // The viewer's own saves, and whether they flagged each one — one
        // query, keyed by competition.
        $mine = $viewer
            ? CompetitionSave::where('user_id', $viewer->id)
                ->whereIn('competition_id', $ids)
                ->get()
                ->keyBy('competition_id')
            : collect();

        /*
        | How many people are looking for a team on each competition, as ONE
        | grouped query rather than one per card. A twelve-card board would
        | otherwise fire twelve counts — the same reason the matcher
        | bulk-loads criteria.
        */
        $teamCounts = CompetitionSave::selectRaw('competition_id, COUNT(*) as total')
            ->whereIn('competition_id', $ids)
            ->where('looking_for_team', true)
            ->groupBy('competition_id')
            ->pluck('total', 'competition_id');

        return $competitions->map(function (Competition $c) use ($mine, $teamCounts) {
            $days = $c->daysLeft();

            return collect([
                'competition_id' => $c->id,
                'name'           => $c->name,
                'organizer'      => $c->organizer,
                'description'    => $c->description,
                'external_url'   => $c->external_url,

                // Shown beside the link so a reader can see where a button
                // goes before pressing it.
                'destination'    => $c->destinationHost(),

                'deadline'       => $c->deadline?->toDateString(),
                'deadline_label' => $c->deadline?->format('d M Y'),
                'days_left'      => $days,

                // Rendered in flame under this many days — the one number
                // that matters on a deadline board.
                'closing_soon'   => $days !== null
                    && $days <= (int) config('platform.competitions.closing_soon_days'),
                'closed'         => $days !== null && $days < 0,

                'team_size'      => $c->teamSizeLabel(),
                'prize'          => $c->prize,
                'location'       => $c->location,

                'posted_by'      => $c->poster?->name,
                'poster_verified' => $c->poster?->verification_status === VerificationStatus::VERIFIED,
                'posted_on'      => $c->created_at?->format('d M Y'),

                'saved'            => $mine->has($c->id),

                // My own signal on this competition.
                'looking_for_team' => (bool) $mine->get($c->id)?->looking_for_team,

                // How many OTHER people are looking. Excluding myself is what
                // makes the number mean "people I could team up with" rather
                // than a count that mysteriously includes me.
                'teammates_count'  => max(
                    0,
                    (int) ($teamCounts[$c->id] ?? 0)
                        - ((bool) $mine->get($c->id)?->looking_for_team ? 1 : 0)
                ),
            ])->all();
        })->values();
    }
}
