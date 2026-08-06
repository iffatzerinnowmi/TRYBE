<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStage;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Study;
use App\Models\StudyParticipation;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The admin overview, plus the two buttons that actually decide whether a
 * researcher or organization becomes Verified.
 *
 * Those two buttons matter beyond this page: Member 1's Researcher
 * Verification Badge feature is only meaningful because an admin approved
 * something here.
 */
class AdminDashboardController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index()
    {
        /* ---- the approvals queue ---- */
        $pending = VerificationRequest::query()
            ->with('user')
            ->pending()
            ->oldest('created_at')
            ->get();

        /* ---- headline numbers, all counted live ---- */
        $stats = [
            'pending'      => $pending->count(),
            'users'        => User::count(),
            'studies'      => Study::count(),
            'completed'    => StudyParticipation::where('stage', PipelineStage::COMPLETED)->count(),
            'researchers'  => $pending->where('role', UserRole::RESEARCHER)->count(),
            'organizations'=> $pending->where('role', UserRole::ORGANIZATION)->count(),
        ];

        /* ---- what the admin has already decided ---- */
        $recent = VerificationRequest::query()
            ->with(['user', 'reviewer'])
            ->whereNotNull('reviewed_at')
            ->latest('reviewed_at')
            ->take(6)
            ->get();

        /* ---- platform rules, read straight out of config ----
           Editing these from the UI needs a settings table — that is Section 8.
           Showing them here proves where the numbers come from. */
        $settings = [
            ['key' => 'free_forms_to_unlock_paid', 'label' => 'Free forms to unlock paid',
             'value' => config('platform.free_forms_to_unlock_paid'),
             'desc' => 'Volunteer studies a participant must complete before paid access unlocks.'],

            ['key' => 'endorsements_for_verified_badge', 'label' => 'Endorsements for verified badge',
             'value' => config('platform.endorsements_for_verified_badge'),
             'desc' => 'Endorsements from different researchers needed for Verified Participant.'],

            ['key' => 'streak_badge_weeks', 'label' => 'Streak badge weeks',
             'value' => config('platform.streak_badge_weeks'),
             'desc' => 'Consecutive active weeks that earn the Reliable Participant badge.'],

            ['key' => 'streak_karma_bonus_weeks', 'label' => 'Streak karma bonus weeks',
             'value' => config('platform.streak_karma_bonus_weeks'),
             'desc' => 'Consecutive active weeks that trigger the one-time karma bonus.'],
        ];

        return view('dashboards.admin', compact('pending', 'stats', 'recent', 'settings'));
    }

    /** Approve a pending request and mark the applicant Verified. */
    public function approve(VerificationRequest $verificationRequest)
    {
        $this->decide($verificationRequest, VerificationStatus::VERIFIED);

        return back()->with('status', $verificationRequest->user->name . ' is now verified.');
    }

    /** Reject a pending request, with an optional reason. */
    public function reject(Request $request, VerificationRequest $verificationRequest)
    {
        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->decide(
            $verificationRequest,
            VerificationStatus::REJECTED,
            $data['rejection_reason'] ?? null
        );

        return back()->with('status', 'Request from ' . $verificationRequest->user->name . ' was rejected.');
    }

    /**
     * Both buttons do the same two writes, so they share one method:
     * stamp the request, then update the user's badge.
     */
    private function decide(
        VerificationRequest $req,
        VerificationStatus $status,
        ?string $reason = null
    ): void {
        DB::transaction(function () use ($req, $status, $reason) {
            $req->update([
                'status'           => $status,
                'reviewed_by'      => auth()->id(),
                'reviewed_at'      => now(),
                'rejection_reason' => $reason,
            ]);

            // This is the line that puts the badge on the researcher's profile.
            $req->user->update(['verification_status' => $status]);

            // SECTION 7: tell them about it. The notification is stored either
            // way, and pushed to their browser if they enabled push.
            if ($status === VerificationStatus::VERIFIED) {
                $this->notifications->send(
                    $req->user,
                    'verify',
                    "You're verified!",
                    'Your ' . $req->role->label() . ' badge is now live on your profile.',
                    url('/dashboard')
                );
            } else {
                $this->notifications->send(
                    $req->user,
                    'verify',
                    'Your verification needs attention',
                    $reason ?: 'An admin could not approve your documents. You can submit new ones.',
                    url('/dashboard')
                );
            }
        });
    }
}
