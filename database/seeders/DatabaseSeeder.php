<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (config('siaa.demo_enabled')) {
            $this->call(DemoSeeder::class);
        }
    }
}
