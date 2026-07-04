<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\FulfillmentPriority;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reference' => 'ORD-'.strtoupper(Str::random(10)),
            'status' => $this->faker->randomElement(OrderStatus::cases()),
            'currency' => $this->faker->randomElement(Currency::cases()),
            'priority' => $this->faker->randomElement(FulfillmentPriority::cases()),
            'total' => $this->faker->randomFloat(2, 10, 5000),
            'notes' => $this->faker->optional()->sentence(),
            'placed_at' => now(),
        ];
    }
}
