<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_run_the_publication_fixture(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/products/{$product->id}/publish", [
                'channel' => 'web',
                'published_at' => '2026-08-04T12:00:00Z',
                'notify_subscribers' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertMatchesDocumentation();
    }
}
