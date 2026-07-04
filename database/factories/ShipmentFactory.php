<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tracking_number' => strtoupper($this->faker->bothify('??#########??')),
            'carrier' => $this->faker->randomElement(['fedex', 'ups', 'dhl']),
            'weight_grams' => $this->faker->numberBetween(100, 50000),
            'declared_value' => $this->faker->randomFloat(2, 10, 2000),
            'parcel_count' => $this->faker->numberBetween(1, 5),
            'origin_ip' => $this->faker->ipv4(),
            'label_filename' => null,
            'shipped_at' => now(),
        ];
    }
}
