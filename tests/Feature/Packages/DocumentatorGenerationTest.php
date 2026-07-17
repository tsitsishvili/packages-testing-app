<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use Tests\TestCase;
use Tsitsishvili\Documentator\Documentator;

/**
 * Exercises the documentator package against this application's real routes,
 * FormRequests and Resources — i.e. it verifies the package still produces a
 * coherent OpenAPI document for the app it is installed in.
 */
class DocumentatorGenerationTest extends TestCase
{
    private function openApi(): array
    {
        // The generator uses stdClass for map-like nodes (e.g. securitySchemes)
        // so they serialize as JSON objects; normalize to arrays for assertions.
        $spec = $this->app->make(Documentator::class)->toOpenApi();

        return json_decode(json_encode($spec), true);
    }

    public function test_it_generates_a_valid_openapi_document(): void
    {
        $spec = $this->openApi();

        $this->assertArrayHasKey('openapi', $spec);
        $this->assertSame('3.2.0', $spec['openapi']);
        $this->assertSame(config('documentator.title'), $spec['info']['title']);
        $this->assertSame(config('documentator.version'), $spec['info']['version']);
        $this->assertIsArray($spec['paths']);
        $this->assertNotEmpty($spec['paths']);
    }

    public function test_it_documents_the_apps_api_routes(): void
    {
        $paths = array_keys($this->openApi()['paths']);

        $this->assertContains('/api/orders', $paths);
        $this->assertContains('/api/products', $paths);
        $this->assertContains('/api/v2/products', $paths);
    }

    public function test_it_respects_the_route_match_and_exclude_config(): void
    {
        $paths = array_keys($this->openApi()['paths']);

        // config('documentator.routes.match') is ['api/*']: only api routes appear...
        foreach ($paths as $path) {
            $this->assertStringStartsWith('/api/', $path);
        }

        // ...and the excluded sanctum/* routes never leak in.
        $this->assertEmpty(array_filter($paths, fn (string $p) => str_contains($p, 'sanctum')));
    }

    public function test_it_describes_operations_with_methods(): void
    {
        $orders = $this->openApi()['paths']['/api/orders'];

        // The index/store handlers map onto GET and POST operations.
        $this->assertArrayHasKey('get', $orders);
        $this->assertArrayHasKey('post', $orders);
    }

    public function test_it_documents_http_query_with_a_request_body(): void
    {
        $operation = $this->openApi()['paths']['/api/orders']['query'];
        $queryParameters = array_column($operation['parameters'] ?? [], 'name');
        $bodyProperties = $operation['requestBody']['content']['application/json']['schema']['properties'];

        $this->assertArrayHasKey('requestBody', $operation);
        $this->assertArrayHasKey('application/json', $operation['requestBody']['content']);
        $this->assertArrayHasKey('status', $bodyProperties);
        $this->assertArrayHasKey('currency', $bodyProperties);
        $this->assertArrayHasKey('min_total', $bodyProperties);
        $this->assertArrayHasKey('page', $bodyProperties);
        $this->assertArrayHasKey('per_page', $bodyProperties);
        $this->assertNotContains('status', $queryParameters);
        $this->assertNotContains('currency', $queryParameters);
        $this->assertNotContains('min_total', $queryParameters);
    }

    public function test_it_declares_the_configured_security_schemes(): void
    {
        $schemes = $this->openApi()['components']['securitySchemes'] ?? [];

        $this->assertArrayHasKey('default', $schemes);
        $this->assertArrayHasKey('admin', $schemes);
        $this->assertSame('http', $schemes['default']['type']);
        $this->assertSame('bearer', $schemes['default']['scheme']);
    }
}
