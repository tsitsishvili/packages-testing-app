<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductStatistic;
use App\Models\TempProductAddToCartEvents;
use App\Models\TempProductAppearanceEvents;
use App\Models\TempProductViewEvents;
use App\Repositories\ProductStatisticsRepository;
use App\Services\Statistics\StatisticAggregationService;
use Illuminate\Support\Collection;
use RuntimeException;
use Tests\TestCase;

class AggregateProductStatisticsCommandTest extends TestCase
{
    public function test_it_clears_the_events_it_counted(): void
    {
        $product = $this->productWithEvents();

        $this->artisan('product:aggregate-statistics')->assertSuccessful();

        $this->assertDatabaseCount('temp_product_view_events', 0);
        $this->assertDatabaseCount('temp_product_appearance_events', 0);
        $this->assertDatabaseCount('temp_product_add_to_cart_events', 0);

        $this->assertDatabaseHas('product_statistics', [
            'product_id' => $product->id,
            'view_count' => 5,
            'search_appearance_count' => 3,
            'add_to_cart_count' => 2,
        ]);
    }

    public function test_it_does_not_count_the_same_events_twice(): void
    {
        $this->productWithEvents();

        $this->artisan('product:aggregate-statistics')->assertSuccessful();
        $this->artisan('product:aggregate-statistics')->assertSuccessful();

        $statistic = ProductStatistic::sole();

        $this->assertSame(5, (int) $statistic->view_count);
        $this->assertSame(3, (int) $statistic->search_appearance_count);
        $this->assertSame(2, (int) $statistic->add_to_cart_count);
    }

    public function test_it_keeps_events_written_while_it_was_counting(): void
    {
        $product = Product::factory()->create();
        TempProductViewEvents::factory()->count(3)->create(['product_id' => $product->id]);

        $service = $this->app->make(StatisticAggregationService::class);

        $counted = $service->getAggregatedStatistics();
        $this->assertSame(3, (int) $counted->firstWhere('product_id', $product->id)['view_count']);

        // A view lands after counting finished but before the cleanup runs.
        TempProductViewEvents::factory()->create(['product_id' => $product->id]);

        $service->flushConsumedEvents();

        $this->assertDatabaseCount('temp_product_view_events', 1);
    }

    public function test_it_keeps_the_events_when_the_write_fails(): void
    {
        $this->productWithEvents();

        $this->app->bind(ProductStatisticsRepository::class, fn () => new class extends ProductStatisticsRepository
        {
            public function upsert(Collection $statistics): void
            {
                throw new RuntimeException('write failed');
            }
        });

        try {
            $this->artisan('product:aggregate-statistics')->run();
            $this->fail('Expected the aggregation to fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('write failed', $e->getMessage());
        }

        $this->assertDatabaseCount('temp_product_view_events', 5);
        $this->assertDatabaseCount('temp_product_appearance_events', 3);
        $this->assertDatabaseCount('temp_product_add_to_cart_events', 2);
        $this->assertDatabaseCount('product_statistics', 0);
    }

    private function productWithEvents(): Product
    {
        $product = Product::factory()->create();

        TempProductViewEvents::factory()->count(5)->create(['product_id' => $product->id]);
        TempProductAppearanceEvents::factory()->count(3)->create(['product_id' => $product->id]);
        TempProductAddToCartEvents::factory()->count(2)->create(['product_id' => $product->id]);

        return $product;
    }
}
