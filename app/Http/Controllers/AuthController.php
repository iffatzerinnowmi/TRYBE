<?php

namespace App\Http\Controllers;

use App\Enums\CredentialLevel;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\NotificationPreference;
use App\Models\ParticipantProfile;
use App\Models\ResearcherProfile;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Login, signup and logout.
 *
 * These are shared workflows, not graded features — but every later page
 * depends on them, because "who is logged in" decides what the dashboards,
 * the credential pages and the endorsement pages are allowed to show.
 *
 * FIELD NAMES ARE FROZEN. The name="" on every input must stay exactly as it
 * is here, because the other three members validate against the same strings:
 *   role, name, email, phone, password, password_confirmation, location,
 *   institutional_affiliation, credential_document,
 *   organization_name, organization_type, registration_documents
 */
class AuthController extends Controller
{
    /* =====================================================================
       LOGIN
       ===================================================================== */

    /** Show the login form. */
    public function showLogin()
    {
        return view('auth.login', [
            'unlockTarget' => (int) config('platform.free_forms_to_unlock_paid'),
            'tierCount'    => count(CredentialLevel::cases()) - 1, // minus "none"
        ]);
    }

    /** Check the credentials and start the session. */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Auth::attempt hashes the password and compares it for us.
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => "Those credentials don't match an account."]);
        }

        // Stops session-fixation attacks: new session id after logging in.
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    /* =====================================================================
       SIGNUP
       ===================================================================== */

    /** Show the signup form, optionally pre-selecting a role from ?role=. */
    public function showSignup(Request $request)
    {
        return view('auth.signup', [
            'selectedRole' => $this->safeRole($request->query('role')),
            'unlockTarget' => (int) config('platform.free_forms_to_unlock_paid'),
            'orgTypes'     => [
                'university'   => 'University',
                'research_lab' => 'Research lab',
                'company'      => 'Company',
            ],
        ]);
    }

    /** Create the account, its profile row, and any verification request. */
    public function signup(Request $request)
    {
        $role = $this->safeRole($request->input('role'));

        // ---- 1. Rules everyone must satisfy -----------------------------
        $rules = [
            'role'     => ['required', Rule::in($this->publicRoleValues())],
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:180', 'unique:users,email'],
            'phone'    => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'location' => ['nullable', 'string', 'max:120'],
        ];

        // ---- 2. Extra rules that only apply to some roles ---------------
        if ($role === UserRole::RESEARCHER) {
            $rules['institutional_affiliation'] = ['required', 'string', 'max:180'];
            $rules['credential_document'] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'];
        }

        if ($role === UserRole::ORGANIZATION) {
            $rules['organization_name'] = ['required', 'string', 'max:180'];
            $rules['organization_type'] = ['required', Rule::in(['university', 'research_lab', 'company'])];
            $rules['location'] = ['required', 'string', 'max:120'];
            $rules['registration_documents'] = ['required', 'file', 'mimes:pdf', 'max:4096'];
        }

        $data = $request->validate($rules);

        // ---- 3. Save uploaded files -------------------------------------
        // store() returns the path it saved to, which is what goes in the DB.
        $credentialPath = $request->hasFile('credential_document')
            ? $request->file('credential_document')->store('credential-documents', 'public')
            : null;

        $registrationPath = $request->hasFile('registration_documents')
            ? $request->file('registration_documents')->store('registration-documents', 'public')
            : null;

        // ---- 4. Create everything in one transaction --------------------
        // If any step fails, the whole thing rolls back — no half-made users.
        $user = DB::transaction(function () use ($data, $role, $credentialPath, $registrationPath) {

            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'],
                'password' => $data['password'],   // the model casts this to 'hashed'
                'role'     => $role,
                'location' => $data['location'] ?? null,

                // Participants are live immediately. Researchers and
                // organizations wait for an admin decision.
                'verification_status' => $role === UserRole::PARTICIPANT
                    ? VerificationStatus::UNVERIFIED
                    : VerificationStatus::PENDING,

                'organization_name' => $data['organization_name'] ?? null,
                'organization_type' => $data['organization_type'] ?? null,
                'registration_documents_path' => $registrationPath,
            ]);

            // Every user gets a notification preference row.
            NotificationPreference::create(['user_id' => $user->id]);

            // Role-specific profile row.
            if ($role === UserRole::PARTICIPANT) {
                ParticipantProfile::create(['user_id' => $user->id]);
            }

            if ($role === UserRole::RESEARCHER) {
                ResearcherProfile::create([
                    'user_id'             => $user->id,
                    'institution'         => $data['institutional_affiliation'],
                    'institutional_email' => $data['email'],
                ]);

                VerificationRequest::create([
                    'user_id'                   => $user->id,
                    'role'                      => $role,
                    'institutional_email'       => $data['email'],
                    'institutional_affiliation' => $data['institutional_affiliation'],
                    'credential_document_path'  => $credentialPath,
                    'status'                    => VerificationStatus::PENDING,
                ]);
            }

            if ($role === UserRole::ORGANIZATION) {
                VerificationRequest::create([
                    'user_id'                     => $user->id,
                    'role'                        => $role,
                    'organization_name'           => $data['organization_name'],
                    'organization_type'           => $data['organization_type'],
                    'registration_documents_path' => $registrationPath,
                    'status'                      => VerificationStatus::PENDING,
                ]);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/dashboard')->with('status', 'Welcome to TRYBE, ' . $user->name . '.');
    }

    /* =====================================================================
       LOGOUT
       ===================================================================== */

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /* =====================================================================
       HELPERS
       ===================================================================== */

    /** Roles a stranger is allowed to sign up as. Admin is never one of them. */
    private function publicRoleValues(): array
    {
        return [
            UserRole::PARTICIPANT->value,
            UserRole::RESEARCHER->value,
            UserRole::ORGANIZATION->value,
        ];
    }

    /** Turn whatever arrived in the request into a real UserRole, safely. */
    private function safeRole(?string $value): UserRole
    {
        return in_array($value, $this->publicRoleValues(), true)
            ? UserRole::from($value)
            : UserRole::PARTICIPANT;
    }
}
