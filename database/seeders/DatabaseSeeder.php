<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Note: Indonesia region master data is seeded separately via
     * `php artisan db:seed --class="Laravolt\Indonesia\Seeds\ProvincesSeeder"`
     * (and CitiesSeeder) because it is large and rarely changes.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
