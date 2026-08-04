<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use App\Models\Product;
use App\Models\User;
use App\Repositories\ProductStatisticsRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\AuditedCatalogSyncJob;
use Tsitsishvili\ElasticAudit\DataTransferObjects\ExecutionOrigin;

/**
 * Covers the source fields elastic-audit stamps on every document: the
 * application identity (`service.*`, read from config/app.php) and the
 * execution origin (`execution.*`) it snapshots before the indexing job is
 * dispatched — HTTP route, queue job, Artisan command, or an explicit manual
 * origin supplied on the log context.
 */
class AuditSourceTest extends ElasticAuditTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['http_logs.enabled' => true, 'activity_logs.enabled' => true]);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    public function test_it_stamps_the_application_identity_on_indexed_documents(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->postJson("/api/products/{$product->id}/sync")->assertOk();

        $doc = $this->es->lastIndexedDocument();
        $this->assertSame(config('app.name'), $doc['service']['name']);
        $this->assertSame('testing', $doc['service']['environment']);
    }

    public function test_it_records_the_route_and_controller_as_the_execution_origin(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->postJson("/api/products/{$product->id}/sync")->assertOk();

        $doc = $this->es->lastIndexedDocument();
        $this->assertSame(ExecutionOrigin::TYPE_HTTP, $doc['execution']['type']);
        $this->assertSame('api/products/{product}/sync', $doc['execution']['name']);
        $this->assertStringContainsString('ProductController@sync', $doc['execution']['action']);
    }

    public function test_it_records_the_queue_job_as_the_execution_origin(): void
    {
        // The `sync` driver still raises JobProcessing/JobProcessed, which is
        // what the package listens to when resolving the origin.
        AuditedCatalogSyncJob::dispatch();

        $doc = $this->es->lastIndexedDocument();
        $this->assertSame(ExecutionOrigin::TYPE_QUEUE, $doc['execution']['type']);
        $this->assertSame(AuditedCatalogSyncJob::class, $doc['execution']['name']);
    }

    public function test_it_records_the_artisan_command_as_the_execution_origin(): void
    {
        $this->enableConsoleCommandEvents();

        $product = Product::factory()->create();

        // A model update inside a command: ActivityLoggable captures the diff,
        // the resolver attributes it to the running command.
        Artisan::command('audit-test:rename-product', function () use ($product): void {
            $product->update(['name' => 'Renamed from a command']);
        });

        $this->artisan('audit-test:rename-product')->assertSuccessful();

        $doc = $this->es->lastIndexedDocument();
        $this->assertSame('product.updated', $doc['action']);
        $this->assertSame(ExecutionOrigin::TYPE_CONSOLE, $doc['execution']['type']);
        $this->assertSame('audit-test:rename-product', $doc['execution']['name']);
    }

    public function test_an_explicit_manual_origin_overrides_the_detected_one(): void
    {
        $this->enableConsoleCommandEvents();

        $product = Product::factory()->create();

        DB::table('temp_product_view_events')->insert([
            ['product_id' => $product->id, 'user_id' => null],
        ]);

        $this->stubStatisticsRepository();

        // Runs as an Artisan command, but the aggregation pins its own origin.
        $this->artisan('product:aggregate-statistics')->assertSuccessful();

        $doc = $this->es->lastIndexedDocument();
        $this->assertSame('product_statistics.aggregated', $doc['action']);
        $this->assertSame(ExecutionOrigin::TYPE_MANUAL, $doc['execution']['type']);
        $this->assertSame('product.statistics_aggregation', $doc['execution']['name']);
        $this->assertSame('aggregate', $doc['execution']['action']);
    }

    public function test_it_stamps_the_source_on_incoming_callbacks(): void
    {
        $this->postJson('/api/webhooks/stripe', ['id' => 'evt_1P9abcD', 'amount' => 1999]);

        $doc = $this->es->lastIndexedDocument();
        $this->assertSame('incoming', $doc['direction']);
        $this->assertSame(config('app.name'), $doc['service']['name']);
        $this->assertSame(ExecutionOrigin::TYPE_HTTP, $doc['execution']['type']);
        $this->assertSame('api/webhooks/stripe', $doc['execution']['name']);
    }

    /**
     * Laravel deliberately stops rerouting Symfony's console events to
     * `CommandStarting`/`CommandFinished` while running tests, and those are
     * exactly the events the package listens to for the console origin. Opting
     * back in keeps this test on the same code path as a real Artisan run.
     */
    private function enableConsoleCommandEvents(): void
    {
        $this->app->make(Kernel::class)->rerouteSymfonyCommandEvents();
    }

    /**
     * `ProductStatisticsRepository::upsert()` writes MySQL-only raw SQL
     * (`column + VALUES(column)`), which SQLite cannot run. The audit event is
     * recorded by the command, not the repository, so a no-op stand-in keeps
     * this test on the real command path.
     */
    private function stubStatisticsRepository(): void
    {
        $this->app->instance(ProductStatisticsRepository::class, new class extends ProductStatisticsRepository
        {
            public function upsert(Collection $statistics): void {}
        });
    }
}
