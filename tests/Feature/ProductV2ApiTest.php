<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductV2ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_a_product_in_the_v2_shape(): void
    {
        $product = Product::factory()->create([
            'name' => 'Widget',
            'description' => 'A nice widget',
            'price' => 12.34,
        ]);

        $this->getJson("/api/v2/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.type', 'product')
            ->assertJsonPath('data.name', 'Widget')
            ->assertJsonPath('data.pricing.amount', 12.34)
            ->assertJsonPath('data.pricing.formatted', '12.34')
            ->assertJsonPath('data.links.self', url("/api/v2/products/{$product->id}"))
            ->assertJsonStructure(['data' => ['timestamps' => ['created_at', 'updated_at']]]);
    }

    public function test_it_lists_products_in_the_v2_shape(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/v2/products')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'type', 'pricing' => ['amount', 'formatted']]]]);
    }

    public function test_it_groups_daily_statistics_per_metric(): void
    {
        $product = Product::factory()->create();
        $product->statistics()->create([
            'event_date' => '2026-06-25',
            'search_appearance_count' => 5,
            'view_count' => 9,
            'unique_users_view_count' => 7,
            'add_to_cart_count' => 3,
            'unique_users_add_to_cart_count' => 2,
        ]);

        $this->getJson("/api/v2/products/{$product->id}/statistics")
            ->assertOk()
            ->assertJsonPath('data.0.metrics.views.total', 9)
            ->assertJsonPath('data.0.metrics.views.unique_users', 7)
            ->assertJsonPath('data.0.metrics.add_to_cart.total', 3)
            ->assertJsonPath('data.0.metrics.search_appearances.total', 5);
    }

    public function test_guests_cannot_create_products(): void
    {
        $this->postJson('/api/v2/products', [
            'name' => 'Widget',
            'price' => 10.00,
        ])->assertUnauthorized();
    }

    public function test_authenticated_users_can_create_a_product(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v2/products', [
            'name' => 'Widget',
            'description' => 'A nice widget',
            'price' => 10.50,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Widget')
            ->assertJsonPath('data.pricing.amount', 10.5);

        $this->assertDatabaseHas('products', ['name' => 'Widget']);
    }
}
