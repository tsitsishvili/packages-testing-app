<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Statistics\StatisticAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AddToCartStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_add_to_cart_statistics(): void
    {
        $product = Product::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // 3 add to cart events, 2 unique users
        DB::table('temp_product_add_to_cart_events')->insert([
            ['product_id' => $product->id, 'user_id' => $user1->id],
            ['product_id' => $product->id, 'user_id' => $user1->id],
            ['product_id' => $product->id, 'user_id' => $user2->id],
        ]);

        $service = $this->app->make(StatisticAggregationService::class);
        $results = $service->getAggregatedStatistics();

        $stats = collect($results)->firstWhere('product_id', $product->id);

        $this->assertEquals(3, $stats['add_to_cart_count']);
        $this->assertEquals(2, $stats['unique_users_add_to_cart_count']);
    }

    public function test_it_includes_add_to_cart_in_all_stats(): void
    {
        $product = Product::factory()->create();

        $service = $this->app->make(StatisticAggregationService::class);
        $results = $service->getAggregatedStatistics();

        // Even with no events, it should be in the list of keys if there were other events,
        // but here we just check if it returns 0 for a product with other events

        DB::table('temp_product_view_events')->insert([
            ['product_id' => $product->id, 'user_id' => null],
        ]);

        $results = $service->getAggregatedStatistics();
        $stats = collect($results)->firstWhere('product_id', $product->id);

        $this->assertArrayHasKey('add_to_cart_count', $stats);
        $this->assertArrayHasKey('unique_users_add_to_cart_count', $stats);
        $this->assertEquals(0, $stats['add_to_cart_count']);
    }
}
