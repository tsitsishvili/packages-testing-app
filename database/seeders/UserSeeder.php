<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [];

        $total = 10000;
        $chunkSize = 1000;

        $pass = Hash::make('password');

        for ($i = 1; $i <= $total; $i++) {
            $data[] = [
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'password' => $pass,
            ];

            if ($i % $chunkSize === 0) {
                User::insert($data);
                $data = [];
            }
        }

        // Insert remaining rows
        if (! empty($data)) {
            User::insert($data);
        }
    }
}
