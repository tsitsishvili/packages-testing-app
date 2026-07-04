<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_places_an_order_and_prices_lines_from_the_catalog(): void
    {
        $user = User::factory()->create();
        $cheap = Product::factory()->create(['price' => 10.00]);
        $pricey = Product::factory()->create(['price' => 100.00]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'currency' => 'USD',
            'priority' => 2,
            'notes' => 'Leave at the door',
            'items' => [
                ['product_id' => $cheap->id, 'quantity' => 3],
                ['product_id' => $pricey->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('currency', 'USD')
            ->assertJsonPath('priority', 2)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('total', 130)
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.line_total', 30);

        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'total' => 130.00]);
        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_it_rejects_an_order_with_an_invalid_currency(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->postJson('/api/orders', [
            'currency' => 'XYZ',
            'priority' => 1,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrorFor('currency');
    }

    public function test_it_rejects_an_order_for_a_missing_product(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/orders', [
            'currency' => 'EUR',
            'priority' => 1,
            'items' => [['product_id' => 999999, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrorFor('items.0.product_id');
    }

    public function test_guests_cannot_place_orders(): void
    {
        $this->postJson('/api/orders', [])->assertUnauthorized();
    }

    public function test_it_lists_and_filters_a_users_orders(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user)->create(['status' => OrderStatus::Paid]);
        Order::factory()->for($user)->create(['status' => OrderStatus::Cancelled]);
        Order::factory()->create(['status' => OrderStatus::Paid]); // another user

        $this->actingAs($user)->getJson('/api/orders?status=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'paid');
    }

    public function test_it_shows_an_order_with_its_lines(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();
        $order->items()->create([
            'product_id' => Product::factory()->create(['price' => 25])->id,
            'quantity' => 2,
            'unit_price' => 25,
        ]);

        $this->actingAs($user)->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('id', $order->id)
            ->assertJsonPath('items.0.line_total', 50);
    }

    public function test_it_updates_an_order_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);

        $this->actingAs($user)->putJson("/api/orders/{$order->id}", [
            'status' => 'shipped',
            'priority' => 3,
            'notes' => 'Express shipment',
        ])->assertOk()->assertJsonPath('status', 'shipped')->assertJsonPath('priority', 3);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped']);
    }

    public function test_it_cancels_an_order_via_the_deprecated_endpoint(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);

        $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('cancelled', true)
            ->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_it_deletes_an_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $this->actingAs($user)->deleteJson("/api/orders/{$order->id}")->assertNoContent();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_it_stores_an_optional_gift_message_when_present(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 20]);

        $this->actingAs($user)->postJson('/api/orders', [
            'currency' => 'GBP',
            'priority' => 1,
            'gift_message' => 'Happy birthday!',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('gift_message', 'Happy birthday!');

        $this->assertDatabaseHas('orders', ['gift_message' => 'Happy birthday!']);
    }

    public function test_the_gift_message_is_null_when_omitted(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 20]);

        $this->actingAs($user)->postJson('/api/orders', [
            'currency' => 'USD',
            'priority' => 1,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('gift_message', null);
    }

    public function test_it_ships_an_order_and_returns_the_bare_shipment_model(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['status' => OrderStatus::Paid]);

        $this->actingAs($user)->post("/api/orders/{$order->id}/ship", [
            'tracking_number' => 'AB123456789CD',
            'carrier' => 'fedex',
            'weight_grams' => '1500',
            'declared_value' => '199.99',
            'parcel_count' => 2,
            'origin_ip' => '10.0.0.1',
            'label' => UploadedFile::fake()->image('label.jpg'),
        ])->assertCreated()
            ->assertJsonPath('tracking_number', 'AB123456789CD')
            ->assertJsonPath('weight_grams', 1500)
            ->assertJsonPath('parcel_count', 2);

        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'carrier' => 'fedex']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped']);
    }

    public function test_it_rejects_a_shipment_with_an_invalid_tracking_number_or_ip(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $this->actingAs($user)->postJson("/api/orders/{$order->id}/ship", [
            'tracking_number' => 'not-a-tracking-number',
            'carrier' => 'fedex',
            'weight_grams' => '1500',
            'declared_value' => '199.99',
            'parcel_count' => 2,
            'origin_ip' => '999.999.999.999',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['tracking_number', 'origin_ip']);
    }

    public function test_it_shows_an_order_shipment(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();
        Shipment::factory()->for($order)->create(['tracking_number' => 'ZZ000000000ZZ']);

        $this->actingAs($user)->getJson("/api/orders/{$order->id}/shipment")
            ->assertOk()
            ->assertJsonPath('tracking_number', 'ZZ000000000ZZ');
    }

    public function test_it_imports_orders_from_an_uploaded_file(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent(
            'orders.csv',
            "name,price\nWidget,9.99\nGadget,19.99\n"
        );

        $this->actingAs($user)->post('/api/orders/import', [
            'file' => $file,
            'source' => 'shopify',
            'effective_date' => '2026-06-25',
            'dry_run' => true,
        ])->assertStatus(202)
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('rows', 2)
            ->assertJsonPath('dry_run', true);
    }
}
