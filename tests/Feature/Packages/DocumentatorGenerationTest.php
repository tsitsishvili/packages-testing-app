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
        $this->assertSame(config('documentator.description'), $spec['info']['description']);
        $this->assertCount(1, $spec['servers']);
        $this->assertSame('Application', $spec['servers'][0]['description']);
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

    public function test_it_declares_only_the_enforced_security_scheme(): void
    {
        $schemes = $this->openApi()['components']['securitySchemes'] ?? [];

        $this->assertArrayHasKey('default', $schemes);
        $this->assertArrayNotHasKey('admin', $schemes);
        $this->assertSame('http', $schemes['default']['type']);
        $this->assertSame('bearer', $schemes['default']['scheme']);
    }

    public function test_registration_preserves_form_request_constraints(): void
    {
        $schema = $this->openApi()['paths']['/api/register']['post']['requestBody']['content']['application/json']['schema'];

        $this->assertSame(255, $schema['properties']['name']['maxLength']);
        $this->assertSame('email', $schema['properties']['email']['format']);
        $this->assertSame(8, $schema['properties']['password']['minLength']);
        $this->assertContains('password_confirmation', $schema['required']);
    }

    public function test_it_omits_contract_features_the_application_does_not_implement(): void
    {
        $spec = $this->openApi();
        $productIndex = $spec['paths']['/api/products']['get'];
        $productStore = $spec['paths']['/api/products']['post'];
        $productSync = $spec['paths']['/api/products/{product}/sync']['post'];
        $orderDelete = $spec['paths']['/api/orders/{order}']['delete'];

        $this->assertNotContains('preview_token', array_column($productIndex['parameters'], 'name'));
        $this->assertNotContains('Idempotency-Key', array_column($productStore['parameters'] ?? [], 'name'));
        $this->assertArrayNotHasKey('headers', $productStore['responses']['201']);
        $this->assertArrayNotHasKey('servers', $productSync);
        $this->assertSame([['default' => []]], $orderDelete['security']);
    }

    public function test_singleton_resources_use_the_runtime_data_envelope(): void
    {
        $spec = $this->openApi();

        foreach ([
            $spec['paths']['/api/user']['get']['responses']['200'],
            $spec['paths']['/api/products/{product}']['get']['responses']['200'],
            $spec['paths']['/api/v2/products/{product}']['get']['responses']['200'],
        ] as $response) {
            $schema = $response['content']['application/json']['schema'];

            $this->assertArrayHasKey('data', $schema['properties']);
            $this->assertContains('data', $schema['required']);
        }
    }

    public function test_manual_response_overrides_retain_concrete_schemas(): void
    {
        $spec = $this->openApi();
        $register = $spec['paths']['/api/register']['post']['responses']['201'];
        $sync = $spec['paths']['/api/products/{product}/sync']['post']['responses']['200'];
        $import = $spec['paths']['/api/orders/import']['post']['responses']['202'];
        $publish = $spec['paths']['/api/products/{product}/publish']['post']['responses'];
        $shipment = $spec['paths']['/api/orders/{order}/ship']['post']['responses']['201'];
        $webhookFailure = $spec['paths']['/api/webhooks/stripe']['post']['responses']['422'];

        $this->assertSame('object', $register['content']['application/json']['schema']['type']);
        $this->assertSame('boolean', $sync['content']['application/json']['schema']['properties']['synced']['type']);
        $this->assertSame('integer', $import['content']['application/json']['schema']['properties']['rows']['type']);
        $this->assertArrayHasKey('200', $publish);
        $this->assertArrayNotHasKey('201', $publish);
        $this->assertSame('string', $shipment['content']['application/json']['schema']['properties']['declared_value']['type']);
        $this->assertSame('date-time', $shipment['content']['application/json']['schema']['properties']['created_at']['format']);
        $this->assertArrayHasKey('oneOf', $webhookFailure['content']['application/json']['schema']);
    }
}
