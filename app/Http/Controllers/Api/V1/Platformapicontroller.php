<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CredentialLevel;
use App\Enums\PipelineStage;
use App\Enums\StudyStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * API — public platform numbers  (Member 1)
 *
 * PUBLIC ON PURPOSE
 * -----------------
 * This is the only controller outside the auth:sanctum group. The login page
 * and the landing page are seen by people who are not logged in, so they
 * cannot call a protected endpoint — but they still display real database
 * numbers rather than figures typed into a view.
 *
 * Nothing here exposes anything about an individual. Counts, thresholds, and
 * studies that are already public on the platform. Before adding a field, ask
 * whether a stranger should be able to read it, because anyone on the
 * internet can call this.
 *
 * Display strings (labels, formatted method names) are built here rather than
 * in JavaScript, so the enums stay the only place those words are written.
 */
class PlatformApiController extends Controller
{
    /**
     * GET /api/v1/platform/stats
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                // From config/platform.php, so all four members agree on it.
                'unlock_target' => (int) config('platform.free_forms_to_unlock_paid'),

                // Real tiers only — CredentialLevel::NONE is "unranked", not a tier.
                'tier_count'    => count(CredentialLevel::cases()) - 1,

                // Counted at the moment the page loads.
                'counts' => [
                    'open_studies'  => Study::where('status', StudyStatus::OPEN)->count(),
                    'participants'  => User::where('role', UserRole::PARTICIPANT)->count(),
                    'researchers'   => User::where('role', UserRole::RESEARCHER)->count(),
                    'organizations' => User::where('role', UserRole::ORGANIZATION)->count(),
                    'completed'     => StudyParticipation::where('stage', PipelineStage::COMPLETED)->count(),
                ],

                // The ladder, built from the enum rather than written by hand.
                'tiers' => collect([
                    CredentialLevel::BRONZE,
                    CredentialLevel::GOLD,
                    CredentialLevel::EXPERT,
                ])->map(fn (CredentialLevel $tier) => [
                    'key'   => $tier->value,
                    'name'  => $tier->label(),
                    'min'   => $tier->minCompletions(),
                    'perk'  => match ($tier) {
                        CredentialLevel::BRONZE => 'Full access to standard listings across the platform.',
                        CredentialLevel::GOLD   => 'Eligible for mid-tier paid studies and higher search visibility.',
                        CredentialLevel::EXPERT => 'Invitation-only research and niche, high-paying programs.',
                        default => '',
                    },
                    'note'  => match ($tier) {
                        CredentialLevel::BRONZE => 'Entry credential',
                        CredentialLevel::GOLD   => 'Mid-tier unlock',
                        CredentialLevel::EXPERT => 'Top-tier unlock',
                        default => '',
                    },
                ]),

                // Three studies that are genuinely open right now. These are
                // already visible to anyone browsing the platform, so there
                // is nothing private here.
                'open_studies' => $this->openStudies(),
            ],
        ], 200);
    }

    /**
     * The landing page's study preview cards.
     *
     * Every value is pre-shaped for display: the incentive label comes from
     * the IncentiveType enum, and the method name is already formatted. The
     * page renders these strings without deciding anything.
     */
    private function openStudies(): Collection
    {
        return Study::query()
            ->with('researcher:id,name')
            ->where('status', StudyStatus::OPEN)
            ->latest('id')
            ->take(3)
            ->get()
            ->map(fn (Study $study) => [
                'id'               => $study->id,
                'title'            => $study->title,
                'description'      => $study->description,
                'researcher_name'  => $study->researcher?->name,
                'duration_minutes' => $study->duration_minutes,

                // 'in_person' becomes 'In person'
                'method_label'     => ucfirst(str_replace('_', ' ', (string) $study->method)),

                // Null-safe: rows created before the incentive migration may
                // not have a type set.
                'incentive_label'  => $study->incentive_type?->label(),
                'requires_escrow'  => (bool) $study->incentive_type?->requiresEscrow(),

                'irb_flagged'      => (bool) $study->irb_flagged,
            ]);
    }
}