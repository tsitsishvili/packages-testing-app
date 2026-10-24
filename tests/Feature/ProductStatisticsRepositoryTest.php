<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Repositories\ProductStatisticsRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the incremental upsert directly. The aggregation tests exercise the
 * service, so the repository's raw SQL is the one step they never reach — and
 * its dialect differs per driver.
 */
class ProductStatisticsRepositoryTest extends TestCase
{
    private const EVENT_DATE = '2026-10-24';

    public function test_it_inserts_statistics_for_a_new_product_and_date(): void
    {
        $product = Product::factory()->create();

        (new ProductStatisticsRepository)->upsert(
            $this->statisticsFor($product->id, viewCount: 5, addToCartCount: 2)
        );

        $this->assertDatabaseCount('product_statistics', 1);
        $this->assertDatabaseHas('product_statistics', [
            'product_id' => $product->id,
            'event_date' => self::EVENT_DATE,
            'view_count' => 5,
            'add_to_cart_count' => 2,
        ]);
    }

    public function test_it_adds_counts_onto_an_existing_product_and_date(): void
    {
        $product = Product::factory()->create();
        $repository = new ProductStatisticsRepository;

        $repository->upsert($this->statisticsFor($product->id, viewCount: 5, addToCartCount: 2));
        $repository->upsert($this->statisticsFor($product->id, viewCount: 3, addToCartCount: 1));

        $this->assertDatabaseCount('product_statistics', 1);

        $statistic = DB::table('product_statistics')
            ->where('product_id', $product->id)
            ->where('event_date', self::EVENT_DATE)
            ->sole();

        $this->assertSame(8, (int) $statistic->view_count);
        $this->assertSame(3, (int) $statistic->add_to_cart_count);
        $this->assertSame(2, (int) $statistic->search_appearance_count);
        $this->assertSame(2, (int) $statistic->unique_users_view_count);
        $this->assertSame(2, (int) $statistic->unique_users_add_to_cart_count);
    }

    public function test_it_keeps_separate_rows_per_product(): void
    {
        $first = Product::factory()->create();
        $second = Product::factory()->create();

        (new ProductStatisticsRepository)->upsert(
            $this->statisticsFor($first->id, viewCount: 5, addToCartCount: 2)
                ->concat($this->statisticsFor($second->id, viewCount: 7, addToCartCount: 4))
        );

        $this->assertDatabaseCount('product_statistics', 2);
        $this->assertDatabaseHas('product_statistics', ['product_id' => $first->id, 'view_count' => 5]);
        $this->assertDatabaseHas('product_statistics', ['product_id' => $second->id, 'view_count' => 7]);
    }

    public function test_it_does_nothing_for_an_empty_collection(): void
    {
        (new ProductStatisticsRepository)->upsert(collect());

        $this->assertDatabaseCount('product_statistics', 0);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function statisticsFor(int $productId, int $viewCount, int $addToCartCount): Collection
    {
        return collect([[
            'product_id' => $productId,
            'event_date' => self::EVENT_DATE,
            'search_appearance_count' => 1,
            'view_count' => $viewCount,
            'unique_users_view_count' => 1,
            'add_to_cart_count' => $addToCartCount,
            'unique_users_add_to_cart_count' => 1,
        ]]);
    }
}
