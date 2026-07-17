<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Drives the documentator artisan commands end to end and asserts they emit
 * valid, non-empty artifacts for this application's routes.
 */
class DocumentatorCommandsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/documentator-'.uniqid());
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function decode(string $path): array
    {
        $this->assertFileExists($path);

        return json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_generate_writes_the_cached_openapi_spec(): void
    {
        $path = $this->dir.'/openapi.json';

        $this->artisan('documentator:generate', ['--path' => $path])
            ->assertSuccessful();

        $spec = $this->decode($path);
        $this->assertSame('3.2.0', $spec['openapi']);
        $this->assertNotEmpty($spec['paths']);
    }

    public function test_export_writes_an_openapi_document(): void
    {
        $path = $this->dir.'/exported.json';

        $this->artisan('documentator:export', ['path' => $path])
            ->assertSuccessful();

        $spec = $this->decode($path);
        $this->assertSame('3.2.0', $spec['openapi']);
        $this->assertArrayHasKey('/api/orders', $spec['paths']);
        $this->assertArrayHasKey('query', $spec['paths']['/api/orders']);
    }

    public function test_postman_writes_a_collection(): void
    {
        $path = $this->dir.'/collection.json';

        $this->artisan('documentator:postman', ['path' => $path])
            ->assertSuccessful();

        $collection = $this->decode($path);
        $orders = collect($collection['item'])->firstWhere('name', 'Orders');
        $queryRequest = collect($orders['item'])->first(
            fn (array $item): bool => data_get($item, 'request.method') === 'QUERY'
        );

        $this->assertArrayHasKey('info', $collection);
        $this->assertArrayHasKey('item', $collection);
        $this->assertNotEmpty($collection['item']);
        $this->assertNotNull($queryRequest);
        $this->assertSame('raw', data_get($queryRequest, 'request.body.mode'));
        $this->assertSame('bearer', data_get($queryRequest, 'request.auth.type'));
    }
}
