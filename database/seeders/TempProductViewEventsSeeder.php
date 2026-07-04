<?php

namespace Database\Seeders;

use App\Models\TempProductViewEvents;
use Illuminate\Database\Seeder;

class TempProductViewEventsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed events for the last 30 days
        $productCount = rand(1000, 10000);

        for ($i = 0; $i < $productCount; $i++) {
            $eventCount = rand(100, 1000);
            $events = [];

            for ($j = 0; $j < $eventCount; $j++) {
                $events[] = [
                    'product_id' => rand(1, 20000),
                    'user_id' => rand(1, 10000),
                ];
            }

            TempProductViewEvents::insert($events);
        }
    }
}
