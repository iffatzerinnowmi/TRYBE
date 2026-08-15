<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\VerificationRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * FEATURE — Researcher Verification Badge, the applicant's half.
 *
 * Section 5 built the admin's side: a queue, Approve and Reject. This is the
 * other end — where a researcher or organization submits documents in the
 * first place, watches the status, reads a rejection reason, and applies
 * again with better paperwork.
 *
 * Without this, a rejected researcher has no way back into the queue.
 */
class VerificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index()
    {
        $user = auth()->user();

        $latest = VerificationRequest::where('user_id', $user->id)
            ->latest('id')
            ->first();

        $history = VerificationRequest::with('reviewer')
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        return view('verification.index', [
            'user'      => $user,
            'latest'    => $latest,
            'history'   => $history,
            // You may only apply when you have never applied, or were rejected.
            'canApply'  => $this->canApply($user->verification_status),
            'orgTypes'  => [
                'university'   => 'University',
                'research_lab' => 'Research lab',
                'company'      => 'Company',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        // Guard the server side too — hiding the form isn't protection.
        abort_unless($this->canApply($user->verification_status), 403,
            'You already have a verification request in progress.');

        $rules = [];

        if ($user->role === UserRole::RESEARCHER) {
            $rules = [
                'institutional_email'       => ['required', 'email', 'max:180'],
                'institutional_affiliation' => ['required', 'string', 'max:180'],
                'credential_document'       => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            ];
        }

        if ($user->role === UserRole::ORGANIZATION) {
            $rules = [
                'organization_name'      => ['required', 'string', 'max:180'],
                'organization_type'      => ['required', Rule::in(['university', 'research_lab', 'company'])],
                'registration_documents' => ['required', 'file', 'mimes:pdf', 'max:4096'],
            ];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($request, $user, $data) {

            $payload = [
                'user_id' => $user->id,
                'role'    => $user->role,
                'status'  => VerificationStatus::PENDING,
            ];

            if ($user->role === UserRole::RESEARCHER) {
                $payload['institutional_email'] = $data['institutional_email'];
                $payload['institutional_affiliation'] = $data['institutional_affiliation'];
                $payload['credential_document_path'] =
                    $request->file('credential_document')->store('credential-documents', 'public');

                // Keep the profile in step with what was just claimed.
                $user->researcherProfile?->update([
                    'institution'         => $data['institutional_affiliation'],
                    'institutional_email' => $data['institutional_email'],
                ]);
            }

            if ($user->role === UserRole::ORGANIZATION) {
                $payload['organization_name'] = $data['organization_name'];
                $payload['organization_type'] = $data['organization_type'];
                $payload['registration_documents_path'] =
                    $request->file('registration_documents')->store('registration-documents', 'public');

                $user->update([
                    'organization_name' => $data['organization_name'],
                    'organization_type' => $data['organization_type'],
                ]);
            }

            VerificationRequest::create($payload);

            // Back into the queue.
            $user->update(['verification_status' => VerificationStatus::PENDING]);

            $this->notifications->send(
                $user,
                'verify',
                'Verification request submitted',
                'An admin will review your documents. You can post studies in the meantime, with limited reach.',
                url('/verification')
            );
        });

        return back()->with('status',
            'Submitted. Your request is now in the admin review queue.');
    }

    /** Never applied, or the last attempt was rejected. */
    private function canApply(VerificationStatus $status): bool
    {
        return in_array($status, [
            VerificationStatus::UNVERIFIED,
            VerificationStatus::REJECTED,
        ], true);
    }
}
