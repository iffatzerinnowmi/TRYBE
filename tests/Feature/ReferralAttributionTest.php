<?php

namespace Tests\Feature;

use App\Enums\CredentialLevel;
use App\Enums\UserRole;
use App\Models\ParticipantProfile;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Attribution — how a signup gets linked to a referrer, and all the ways it
 * must refuse to.
 */
class ReferralAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_up_through_a_referral_link_creates_one_referral(): void
    {
        $referrer = $this->participant();
        $code     = app(ReferralService::class)->codeFor($referrer);

        $this->withUnencryptedCookie(ReferralService::COOKIE, $code->code)
            ->post('/signup', $this->signupPayload('newbie@example.com'))
            ->assertRedirect('/dashboard');

        $referral = Referral::first();

        $this->assertNotNull($referral);
        $this->assertSame($referrer->id, $referral->referrer_id);
        $this->assertSame($code->code, $referral->code_used);
        $this->assertSame('pending', $referral->status->value);
    }

    public function test_signing_up_without_a_referral_cookie_creates_nothing(): void
    {
        $this->post('/signup', $this->signupPayload('plain@example.com'))
            ->assertRedirect('/dashboard');

        $this->assertSame(0, Referral::count());
    }

    /**
     * THE test that matters most for not breaking anyone else's work: a
     * broken referral cookie must never stop somebody creating an account.
     */
    public function test_a_malformed_referral_cookie_does_not_break_signup(): void
    {
        $this->withUnencryptedCookie(ReferralService::COOKIE, 'NOT-A-REAL-CODE-!!')
            ->post('/signup', $this->signupPayload('resilient@example.com'))
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['email' => 'resilient@example.com']);
        $this->assertSame(0, Referral::count());
    }

    public function test_a_user_cannot_be_referred_twice(): void
    {
        $first  = $this->participant();
        $second = $this->participant();
        $victim = $this->participant();

        $service = app(ReferralService::class);

        $this->assertNotNull($service->attribute($victim, $service->codeFor($first)->code));
        $this->assertNull($service->attribute($victim, $service->codeFor($second)->code));

        $this->assertSame(1, Referral::where('referred_user_id', $victim->id)->count());
    }

    public function test_self_referral_is_refused(): void
    {
        $user    = $this->participant();
        $service = app(ReferralService::class);

        $this->assertNull($service->attribute($user, $service->codeFor($user)->code));
        $this->assertSame(0, Referral::count());
    }

    public function test_an_unknown_code_attributes_nothing(): void
    {
        $user = $this->participant();

        $this->assertNull(app(ReferralService::class)->attribute($user, 'ZZZZZZZZ'));
        $this->assertSame(0, Referral::count());
    }

    /** Visiting a link bumps the counter, which grants nothing on its own. */
    public function test_opening_a_referral_link_increments_visits(): void
    {
        $referrer = $this->participant();
        $code     = app(ReferralService::class)->codeFor($referrer);

        $this->get('/signup?ref=' . $code->code)->assertOk();

        $this->assertSame(1, $code->fresh()->visits);
        $this->assertSame(0, Referral::count(), 'A visit is not a referral.');
    }

    public function test_codes_are_unique_and_use_the_configured_alphabet(): void
    {
        $service  = app(ReferralService::class);
        $alphabet = config('platform.referrals.code_alphabet');
        $length   = (int) config('platform.referrals.code_length');

        $codes = [];

        for ($i = 0; $i < 15; $i++) {
            $code = $service->codeFor($this->participant())->code;

            $this->assertSame($length, strlen($code));
            $this->assertSame(0, preg_match('/[^' . $alphabet . ']/', $code),
                'Codes must avoid ambiguous characters like I, 1, O and 0.');

            $codes[] = $code;
        }

        $this->assertSame(count($codes), count(array_unique($codes)));
    }

    /** Rotating invalidates the old link but does not rewrite history. */
    public function test_rotating_a_code_leaves_past_referrals_intact(): void
    {
        $referrer = $this->participant();
        $service  = app(ReferralService::class);

        $original = $service->codeFor($referrer)->code;
        $service->attribute($this->participant(), $original);

        $rotated = $service->rotateCodeFor($referrer)->code;

        $this->assertNotSame($original, $rotated);
        $this->assertSame($original, Referral::first()->code_used);
        $this->assertNull($service->findByCode($original));
        $this->assertSame(1, ReferralCode::where('user_id', $referrer->id)->count());
    }

    /** The public validate endpoint must be reachable by a guest and leak nothing. */
    public function test_the_public_validate_endpoint_returns_a_name_and_no_email(): void
    {
        $referrer = $this->participant('Ayesha Rahman', 'ayesha@example.com');
        $code     = app(ReferralService::class)->codeFor($referrer);

        $response = $this->getJson('/api/v1/referrals/validate/' . $code->code)
            ->assertOk()
            ->assertJsonPath('data.referrer_name', 'Ayesha R.');

        $this->assertStringNotContainsString('ayesha@example.com', $response->getContent());

        $this->getJson('/api/v1/referrals/validate/ZZZZZZZZ')->assertStatus(404);
    }

    // -----------------------------------------------------------------

    private function signupPayload(string $email): array
    {
        return [
            'role'                  => 'participant',
            'name'                  => 'New Person',
            'email'                 => $email,
            'phone'                 => '01700000' . random_int(100, 999),
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ];
    }

    private function participant(string $name = null, string $email = null): User
    {
        $user = User::create([
            'name'                => $name ?? ('Participant ' . uniqid()),
            'email'               => $email ?? (uniqid('participant_', true) . '@example.com'),
            'password'            => 'password',
            'role'                => UserRole::PARTICIPANT->value,
            'verification_status' => 'unverified',
        ]);

        ParticipantProfile::create([
            'user_id'          => $user->id,
            'age'              => 25,
            'credential_level' => CredentialLevel::NONE->value,
        ]);

        return $user->fresh();
    }
}
