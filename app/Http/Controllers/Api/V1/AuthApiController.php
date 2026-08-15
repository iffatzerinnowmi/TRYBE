<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\NotificationPreference;
use App\Models\ParticipantProfile;
use App\Models\ResearcherProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * API — authentication  (Member 1)
 *
 * ONE ROUTE, TWO KINDS OF CALLER
 * ------------------------------
 * Our own login page and Postman both hit POST /api/v1/auth/login. They need
 * different things back, so this controller gives both:
 *
 *   Browser  -> Auth::attempt() starts a SESSION and sets a cookie. That
 *               cookie is what every later page request is authenticated by.
 *
 *   Postman  -> a Bearer TOKEN is returned in the JSON, to be sent in the
 *               Authorization header on later calls.
 *
 * The session part is skipped when there is no session on the request, which
 * is exactly the case for Postman. hasSession() is what tells them apart —
 * Sanctum's stateful middleware only attaches a session to requests that came
 * from our own frontend.
 *
 * This is why the login page and the examiner's Postman collection can use
 * the same endpoint without either one needing a special case.
 */
class AuthApiController extends Controller
{
    /**
     * POST /api/v1/auth/register
     *
     * NOTE: this is not yet at parity with the web signup form. It does not
     * handle file uploads, and it does not create VerificationRequest rows,
     * so a researcher registered here will NOT appear in the admin
     * verification queue. Use the web form for researchers and organizations
     * until that is fixed.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone'             => ['nullable', 'string', 'max:20'],
            'password'          => ['required', 'string', 'min:8', 'confirmed'],
            'role'              => ['required', Rule::in(['participant', 'researcher', 'organization'])],
            'location'          => ['nullable', 'string', 'max:255'],
            'organization_name' => ['nullable', 'required_if:role,organization', 'string', 'max:255'],
            'organization_type' => ['nullable', 'required_if:role,organization', 'string', 'max:255'],
        ]);

        // Participants are live immediately. Researchers/orgs wait for admin review.
        $status = $data['role'] === 'participant' ? 'unverified' : 'pending';

        // A transaction = all rows are written, or none are. No half-made users.
        $user = DB::transaction(function () use ($data, $status) {
            $user = User::create([
                'name'                => $data['name'],
                'email'               => $data['email'],
                'phone'               => $data['phone'] ?? null,
                'password'            => $data['password'],   // auto-hashed by the 'hashed' cast
                'role'                => $data['role'],
                'location'            => $data['location'] ?? null,
                'verification_status' => $status,
                'organization_name'   => $data['organization_name'] ?? null,
                'organization_type'   => $data['organization_type'] ?? null,
            ]);

            // Every user gets one of these, whatever their role.
            NotificationPreference::create(['user_id' => $user->id]);

            if ($data['role'] === 'participant') {
                ParticipantProfile::create(['user_id' => $user->id]);
            } elseif ($data['role'] === 'researcher') {
                ResearcherProfile::create([
                    'user_id'             => $user->id,
                    'institutional_email' => $user->email,
                ]);
            }

            return $user;
        });

        $token = $user->createToken('trybe-api')->plainTextToken;

        $user->load(['participantProfile', 'researcherProfile']);

        return response()->json([
            'message'     => 'Registration successful.',
            'token'       => $token,
            'token_type'  => 'Bearer',
            'user'        => new UserResource($user),
            'redirect_to' => '/dashboard',
        ], 201);
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        // Auth::attempt hashes the submitted password and compares it for us.
        // On success it also logs the user into the session guard, which is
        // what sets the cookie the browser will use from here on.
        $attempt = Auth::attempt(
            ['email' => $data['email'], 'password' => $data['password']],
            $request->boolean('remember')
        );

        if (! $attempt) {
            // Deliberately the same message for a wrong password and an
            // unknown email, so nobody can fish for valid addresses.
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // Only browser requests carry a session. Regenerating the id here
        // prevents session-fixation attacks. Postman has no session, so this
        // is skipped and only the token below matters to it.
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $token = $user->createToken('trybe-api')->plainTextToken;

        $user->load(['participantProfile', 'researcherProfile']);

        return response()->json([
            'message'    => 'Login successful.',
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => new UserResource($user),

            // Where the frontend should go next. Decided here rather than in
            // JavaScript, so the routing rule stays on the server.
            'redirect_to' => '/dashboard',
        ], 200);
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Handles both callers. A Postman request has a real PersonalAccessToken
     * to delete; a browser request has a TransientToken, which is a stand-in
     * object with no delete() method — calling delete() on it is a fatal
     * error, which is why the type is checked first.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message'     => 'Logged out.',
            'redirect_to' => '/',
        ], 200);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'participantProfile',
            'researcherProfile',
            'notificationPreference',
        ]);

        return response()->json(['user' => new UserResource($user)], 200);
    }
}