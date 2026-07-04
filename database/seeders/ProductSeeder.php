<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [];

        $total = 20000;
        $chunkSize = 1000;

        for ($i = 1; $i <= $total; $i++) {
            $name = fake()->words(3, true);
            $data[] = [
                'name' => ucfirst($name),
                'description' => fake()->paragraph(),
                'price' => fake()->randomFloat(2, 10, 1000),
            ];

            if ($i % $chunkSize === 0) {
                Product::insert($data);
                $data = [];
            }
        }

        // Insert remaining rows
        if (! empty($data)) {
            Product::insert($data);
        }
    }
}
