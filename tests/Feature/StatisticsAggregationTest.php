<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Statistics\Counters\AppearanceCounter;
use App\Services\Statistics\Counters\ViewsCounter;
use App\Services\Statistics\StatisticAggregationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatisticsAggregationTest extends TestCase
{
    public function test_it_aggregates_statistics_from_different_sources(): void
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Product 1: 2 views (1 unique user), 3 appearances
        DB::table('temp_product_view_events')->insert([
            ['product_id' => $product1->id, 'user_id' => $user1->id],
            ['product_id' => $product1->id, 'user_id' => $user1->id],
        ]);
        DB::table('temp_product_appearance_events')->insert([
            ['product_id' => $product1->id, 'user_id' => $user1->id],
            ['product_id' => $product1->id, 'user_id' => $user2->id],
            ['product_id' => $product1->id, 'user_id' => null],
        ]);

        // Product 2: 1 view (1 unique user), 0 appearances
        DB::table('temp_product_view_events')->insert([
            ['product_id' => $product2->id, 'user_id' => $user2->id],
        ]);

        $service = new StatisticAggregationService([
            new ViewsCounter,
            new AppearanceCounter,
        ]);
        $results = $service->getAggregatedStatistics();

        $this->assertCount(2, $results);

        $product1Stats = collect($results)->firstWhere('product_id', $product1->id);
        $this->assertEquals(1, $product1Stats['unique_users_view_count']);
        $this->assertEquals(2, $product1Stats['view_count']);
        $this->assertEquals(3, $product1Stats['search_appearance_count']);

        $product2Stats = collect($results)->firstWhere('product_id', $product2->id);
        $this->assertEquals(1, $product2Stats['unique_users_view_count']);
        $this->assertEquals(1, $product2Stats['view_count']);
        $this->assertEquals(0, $product2Stats['search_appearance_count']);
    }
}
