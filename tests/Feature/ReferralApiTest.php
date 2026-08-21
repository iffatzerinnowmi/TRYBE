<?php

namespace Tests\Feature;

use App\Enums\CredentialLevel;
use App\Enums\UserRole;
use App\Models\ParticipantProfile;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The referral REST API and the pages behind it.
 */
class ReferralApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_endpoint_returns_everything_the_page_needs(): void
    {
        $user = $this->participant();

        $this->actingAs($user)
            ->getJson('/api/v1/referrals/me')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user_id', 'code', 'share_url', 'visits',
                    'progress' => ['qualified_count', 'required_to_unlock', 'remaining', 'percent', 'label', 'reward_eligible'],
                    'reward_preview',
                    'referred_users',
                    'rewards',
                    'share_messages' => ['whatsapp', 'sms', 'email', 'generic'],
                ],
            ]);
    }

    /** The code is created lazily, so no backfill was needed for existing users. */
    public function test_the_code_is_created_on_first_read(): void
    {
        $user = $this->participant();

        $this->assertDatabaseCount('referral_codes', 0);

        $code = $this->actingAs($user)->getJson('/api/v1/referrals/me')->json('data.code');

        $this->assertNotEmpty($code);
        $this->assertDatabaseCount('referral_codes', 1);
    }

    public function test_creating_a_code_returns_201_and_rotating_returns_200(): void
    {
        $user = $this->participant();

        $first = $this->actingAs($user)
            ->postJson('/api/v1/referrals/me/code')
            ->assertStatus(201)
            ->json('data.code');

        $second = $this->actingAs($user)
            ->postJson('/api/v1/referrals/me/code', ['rotate' => true])
            ->assertStatus(200)
            ->json('data.code');

        $this->assertNotSame($first, $second);
    }

    /** The share URL must actually carry the code, or the whole thing is decorative. */
    public function test_the_share_url_contains_the_code(): void
    {
        $user = $this->participant();

        $data = $this->actingAs($user)->getJson('/api/v1/referrals/me')->json('data');

        $this->assertStringContainsString('ref=' . $data['code'], $data['share_url']);
    }

    /**
     * The referred-users list is a list of people who did not ask to be on
     * it. It must not carry full names or emails.
     */
    public function test_the_referred_list_shortens_names_and_hides_emails(): void
    {
        $referrer = $this->participant();
        $referred = $this->participant('Nayeem Hasan', 'nayeem@example.com');

        app(ReferralService::class)->attribute($referred, app(ReferralService::class)->codeFor($referrer)->code);

        $response = $this->actingAs($referrer)->getJson('/api/v1/referrals/me/referred-users')->assertOk();

        $response->assertJsonPath('data.referred_users.0.name', 'Nayeem H.');
        $this->assertStringNotContainsString('nayeem@example.com', $response->getContent());
    }

    public function test_a_researcher_can_read_their_post_credit_ledger(): void
    {
        $this->actingAs($this->researcher())
            ->getJson('/api/v1/researchers/me/post-credits')
            ->assertOk()
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.spending_available', false);
    }

    public function test_a_participant_cannot_read_the_post_credit_ledger(): void
    {
        $this->actingAs($this->participant())
            ->getJson('/api/v1/researchers/me/post-credits')
            ->assertStatus(403);
    }

    public function test_referral_endpoints_require_a_login(): void
    {
        $this->getJson('/api/v1/referrals/me')->assertStatus(401);
        $this->postJson('/api/v1/referrals/me/sync')->assertStatus(401);
    }

    /** Team convention §4: the page controller passes nothing to its view. */
    public function test_the_referral_page_passes_no_data_to_its_view(): void
    {
        $response = $this->actingAs($this->participant())->get('/participant/referrals');

        $response->assertOk();

        $data = $response->original->getData();
        unset($data['errors'], $data['obLevel'], $data['__env'], $data['app']);

        $this->assertSame([], $data);
    }

    public function test_the_researcher_referral_page_is_locked_to_researchers(): void
    {
        $this->actingAs($this->researcher())->get('/researcher/referrals')->assertOk();
        $this->actingAs($this->participant())->get('/researcher/referrals')->assertForbidden();
    }

    /** No web POST route exists anywhere in this feature. */
    public function test_the_feature_registers_no_web_post_routes(): void
    {
        $offenders = collect(app('router')->getRoutes())
            ->filter(function ($route) {
                return in_array('POST', $route->methods(), true)
                    && str_contains($route->uri(), 'referral')
                    && ! str_starts_with($route->uri(), 'api/');
            });

        $this->assertCount(0, $offenders);
    }

    // -----------------------------------------------------------------

    private function researcher(): User
    {
        return User::create([
            'name'                => 'Researcher ' . uniqid(),
            'email'               => uniqid('researcher_', true) . '@example.com',
            'password'            => 'password',
            'role'                => UserRole::RESEARCHER->value,
            'verification_status' => 'verified',
        ]);
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
