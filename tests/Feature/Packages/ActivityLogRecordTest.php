<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Repositories\ProductStatisticsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Covers the explicit `ActivityLog::record()` calls the application makes for
 * writes the `ActivityLoggable` trait cannot represent: a domain event richer
 * than the underlying Eloquent diff (order cancellation) and a query-builder
 * write that fires no Eloquent event at all (statistics aggregation).
 */
class ActivityLogRecordTest extends ElasticAuditTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['activity_logs.enabled' => true]);
    }

    public function test_it_records_an_order_cancellation_as_a_domain_event(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);

        $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel")->assertOk();

        $doc = $this->documentForAction('order.cancelled');

        $this->assertSame('user', $doc['actor']['type']);
        $this->assertSame((string) $user->id, $doc['actor']['id']);
        $this->assertSame('order', $doc['entity']['type']);
        $this->assertSame((string) $order->id, $doc['entity']['id']);
        $this->assertSame('pending', $doc['changes']['status']['old']);
        $this->assertSame('cancelled', $doc['changes']['status']['new']);
        $this->assertSame($order->reference, $doc['metadata']['reference']);
    }

    public function test_the_cancellation_event_accompanies_the_automatic_model_diff(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);

        $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel")->assertOk();

        // ActivityLoggable still logs the raw `updated` diff; the explicit event
        // is additional domain context, not a replacement.
        $this->assertNotNull($this->documentForAction('order.updated'));
        $this->assertNotNull($this->documentForAction('order.cancelled'));
    }

    public function test_it_records_the_statistics_aggregation_run(): void
    {
        $product = Product::factory()->create();

        DB::table('temp_product_view_events')->insert([
            ['product_id' => $product->id, 'user_id' => null],
            ['product_id' => $product->id, 'user_id' => null],
        ]);

        // MySQL-only raw SQL lives in the repository, so it is stubbed out here;
        // the aggregation command still runs for real.
        $this->app->instance(ProductStatisticsRepository::class, new class extends ProductStatisticsRepository
        {
            public function upsert(Collection $statistics): void {}
        });

        $this->artisan('product:aggregate-statistics')->assertSuccessful();

        $doc = $this->documentForAction('product_statistics.aggregated');

        $this->assertSame('system', $doc['actor']['type']);
        $this->assertNull($doc['actor']['id']);
        $this->assertSame('product_statistics', $doc['entity']['type']);
        $this->assertSame(now()->toDateString(), $doc['entity']['id']);
        $this->assertSame(1, $doc['metadata']['products']);
        $this->assertIsInt($doc['metadata']['duration_ms']);
    }

    public function test_it_records_nothing_when_activity_logging_is_disabled(): void
    {
        config(['activity_logs.enabled' => false]);

        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);

        $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel")->assertOk();

        $this->assertSame([], $this->es->indexCalls);
    }

    /**
     * @return array<string, mixed>
     */
    private function documentForAction(string $action): array
    {
        foreach ($this->es->indexCalls as $call) {
            if (($call['body']['action'] ?? null) === $action) {
                return $call['body'];
            }
        }

        $this->fail("No indexed activity document with action [{$action}].");
    }
}
