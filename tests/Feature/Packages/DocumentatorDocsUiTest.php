<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use Tests\TestCase;
use Tsitsishvili\Documentator\Documentator;

/**
 * Covers the documentator HTTP surface as wired into this app: the docs UI, the
 * raw OpenAPI endpoint, and the two gates that guard them (EnsureDocsEnabled and
 * the Documentator::auth() callback registered in AppServiceProvider).
 */
class DocumentatorDocsUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Build the spec live so tests never depend on a stale cache file.
        config(['documentator.cache.enabled' => false]);
    }

    public function test_the_docs_ui_is_served(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertHeaderMissing('X-Powered-By-Nothing')
            ->assertSee('html', false);
    }

    public function test_it_serves_the_raw_openapi_json(): void
    {
        $response = $this->getJson('/docs/openapi.json')->assertOk();

        $spec = $response->json();
        $this->assertStringStartsWith('3.', $spec['openapi']);
        $this->assertArrayHasKey('/api/orders', $spec['paths']);
    }

    public function test_docs_are_hidden_when_disabled(): void
    {
        // EnsureDocsEnabled: an explicit false forces a 404 regardless of env.
        config(['documentator.enabled' => false]);

        $this->get('/docs')->assertNotFound();
        $this->getJson('/docs/openapi.json')->assertNotFound();
    }

    public function test_docs_access_can_be_gated_by_the_auth_callback(): void
    {
        // AppServiceProvider wires the gate open (fn () => true); here we deny it
        // and expect the Authorize middleware to reject with a 403.
        Documentator::auth(fn () => false);

        $this->get('/docs')->assertForbidden();

        Documentator::auth(fn () => true);
    }
}
