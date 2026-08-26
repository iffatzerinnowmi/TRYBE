<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The landing page renders for a visitor who is not signed in.
 *
 * This is Laravel's stock example test, kept because it is the only thing
 * covering the public front page.
 *
 * WHY RefreshDatabase WAS ADDED
 * -----------------------------
 * It shipped commented out, because a brand-new Laravel landing page touches
 * no database. Ours does: PageController::landing() counts open studies,
 * completed participations and verified researchers for the stats strip. With
 * no migrations run, the in-memory SQLite database has no `studies` table and
 * the page 500s.
 *
 * So the failure was real — the page genuinely cannot render without a
 * schema — and the fix is to give the test one, not to stop asserting.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * The counters are aggregates over an empty database here, so this also
     * pins down that the landing page copes with zero of everything —
     * the state every fresh install starts in.
     */
    public function test_the_landing_page_renders_with_an_empty_database(): void
    {
        $this->assertSame(0, \App\Models\Study::count());

        $this->get('/')->assertStatus(200);
    }
}
