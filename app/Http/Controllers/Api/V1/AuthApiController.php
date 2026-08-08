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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    /**
     * POST /api/v1/auth/register
     * Creates the user PLUS the same related rows the seeder creates.
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

            if ($data['role'] === 'participant') {
                ParticipantProfile::create(['user_id' => $user->id]);
                NotificationPreference::create(['user_id' => $user->id]);
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
            'message'    => 'Registration successful.',
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => new UserResource($user),
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
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            // Same message either way, so nobody can fish for valid emails.
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $token = $user->createToken('trybe-api')->plainTextToken;

        $user->load(['participantProfile', 'researcherProfile']);

        return response()->json([
            'message'    => 'Login successful.',
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => new UserResource($user),
        ], 200);
    }

    /**
     * POST /api/v1/auth/logout   (requires Bearer token)
     * Deletes only the token used for THIS request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.'], 200);
    }

    /**
     * GET /api/v1/auth/me   (requires Bearer token)
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