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

        // Docs routes are opt-in as of documentator 1.6.3 (disabled by default);
        // enable them so the happy-path assertions below can reach the UI. The
        // disable path is still exercised by test_docs_are_hidden_when_disabled.
        config(['documentator.enabled' => true]);

        // Build the spec live so tests never depend on a stale cache file.
        config(['documentator.cache.enabled' => false]);
    }

    public function test_the_docs_ui_is_served(): void
    {
        // grouping.sections is configured (config/documentator.php), so the bare
        // /docs landing redirects to the first section — "API v2", whose pattern
        // must be listed before the api/* catch-all so it wins during matching.
        // The section page itself serves the UI.
        $this->get('/docs')->assertRedirect('/docs/api-v2');

        foreach (['api-v2', 'api'] as $section) {
            $this->get("/docs/{$section}")
                ->assertOk()
                ->assertHeaderMissing('X-Powered-By-Nothing')
                ->assertSee('html', false);
        }
    }

    public function test_it_serves_the_raw_openapi_json(): void
    {
        $response = $this->getJson('/docs/openapi.json')->assertOk();

        $spec = $response->json();
        $this->assertSame('3.2.0', $spec['openapi']);
        $this->assertArrayHasKey('/api/orders', $spec['paths']);
        $this->assertArrayHasKey('query', $spec['paths']['/api/orders']);
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
